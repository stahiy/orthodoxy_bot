<?php

declare(strict_types=1);

namespace App\Lib;

use DOMDocument;
use DOMXPath;

/**
 * Парсер страницы дня с сайта Православный календарь (days.pravoslavie.ru).
 * Извлекает: пост, седмицу/неделю, глас, праздники дня.
 *
 * Входящая дата (новый стиль, григорианский календарь) перед запросом
 * преобразуется в старый стиль (юлианский), так как сайт использует в URL дату по ст. ст.
 *
 * URL формата: https://days.pravoslavie.ru/Days/YYYYMMDD.html (дата в старом стиле)
 */
class PravoslavieDaysParser
{
    private const BASE_URL = 'https://days.pravoslavie.ru/Days/';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const TIMEOUT = 15;

    /** Разница в днях: григорианский минус юлианский (для 1901–2099 гг. = 13) */
    private const GREGORIAN_JULIAN_OFFSET_DAYS = 13;

    /**
     * Загрузить и распарсить страницу дня.
     * Дата передаётся в новом стиле (григорианский); для запроса к сайту переводится в старый стиль.
     *
     * @param string $date Дата в новом стиле: Y-m-d или Ymd (например 2026-02-15 или 20260215)
     * @return array{date: string, dateOldStyle: string, fastInfo: string, weekName: string|null, voice: string|null, feasts: array<int, string>}
     */
    public function parseDay(string $date = 'now'): array
    {
        $dtGregorian = new \DateTimeImmutable($date);
        $dateGregorian = $dtGregorian->format('Y-m-d');
        $dateOldStyle = $this->gregorianToJulianDateString($dateGregorian);

        $ymdJulian = $this->gregorianToJulianYmd($dtGregorian);
        $url = self::BASE_URL . $ymdJulian . '.html';

        $html = $this->fetchPage($url);
        if ($html === null || $html === '') {
            return $this->emptyResult($dateGregorian, $dateOldStyle);
        }

        return $this->parseHtml($html, $dateGregorian, $dateOldStyle);
    }

    /**
     * Преобразование даты из нового стиля (григорианский) в старый (юлианский).
     * Возвращает Ymd для использования в URL сайта.
     * Для периода 1901–2099 разница между календарями — 13 дней.
     */
    private function gregorianToJulianYmd(\DateTimeImmutable $gregorian): string
    {
        $julian = $gregorian->modify('-' . self::GREGORIAN_JULIAN_OFFSET_DAYS . ' days');
        return $julian->format('Ymd');
    }

    /**
     * Дата по старому стилю (юлианский календарь) в формате Y-m-d.
     */
    private function gregorianToJulianDateString(string $dateGregorian): string
    {
        $dt = new \DateTimeImmutable($dateGregorian);
        $julian = $dt->modify('-' . self::GREGORIAN_JULIAN_OFFSET_DAYS . ' days');
        return $julian->format('Y-m-d');
    }

    /**
     * Форматирует результат парсинга в одну строку для вывода в боте (аналогично CalendarModel::getHoliday).
     */
    public function formatDayAsText(array $parsed): ?string
    {
        $parts = [];

        if ($parsed['weekName'] !== null) {
            $parts[] = '📅 ' . $parsed['weekName'];
            if ($parsed['voice'] !== null) {
                $parts[] = $parsed['voice'];
            }
        } elseif ($parsed['voice'] !== null) {
            $parts[] = '📅 ' . $parsed['voice'];
        }

        if ($parsed['fastInfo'] !== '') {
            $parts[] = '🍽 ' . $parsed['fastInfo'];
        }

        foreach ($parsed['feasts'] as $feast) {
            $parts[] = '🗓 ' . $feast;
        }

        if (empty($parts)) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Загрузка HTML страницы через cURL.
     */
    private function fetchPage(string $url): ?string
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => '',
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error || $httpCode !== 200) {
            return null;
        }

        return is_string($html) ? $html : null;
    }

    /**
     * Парсинг HTML и извлечение данных дня.
     */
    private function parseHtml(string $html, string $date, string $dateOldStyle): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $fastInfo = $this->extractFastInfo($xpath);
        $weekName = $this->extractWeekName($xpath);
        $voice = $this->extractVoice($xpath);
        $feasts = $this->extractFeasts($xpath);

        return [
            'date' => $date,
            'dateOldStyle' => $dateOldStyle,
            'fastInfo' => $fastInfo,
            'weekName' => $weekName,
            'voice' => $voice,
            'feasts' => $feasts,
        ];
    }

    private function extractFastInfo(DOMXPath $xpath): string
    {
        $parts = [];

        // Основной статус поста: "Поста нет." / "Строгий пост." и т.д.
        $nodes = $xpath->query("//*[contains(@class, 'DD_TPTXT')]");
        foreach ($nodes as $node) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $parts[] = $text;
                break;
            }
        }

        // Дополнение: "Заговенье на мясо.", "Разрешается рыба." и т.д.
        $nodes = $xpath->query("//*[contains(@class, 'DD_POST')]");
        foreach ($nodes as $node) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                $parts[] = $text;
            }
        }

        return implode(' ', $parts);
    }

    private function extractWeekName(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//*[contains(@class, 'DD_NED')]//a[contains(@class, 'DA')]");
        foreach ($nodes as $node) {
            $title = $node->getAttribute('title');
            if ($title !== '') {
                return trim($title);
            }
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                return $text;
            }
        }
        return null;
    }

    private function extractVoice(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query("//*[contains(@class, 'DD_GLAS')]");
        foreach ($nodes as $node) {
            $text = trim($node->textContent ?? '');
            if ($text !== '') {
                return $text;
            }
        }
        return null;
    }

    /**
     * Извлечь список праздников из блока DD_TEXT (параграфы DP_TEXT).
     * Используется полный текст параграфа без дублирования из нескольких ссылок.
     */
    private function extractFeasts(DOMXPath $xpath): array
    {
        $feasts = [];

        $paragraphs = $xpath->query("//*[contains(@class, 'DD_TEXT')]//p[contains(@class, 'DP_TEXT')]");
        foreach ($paragraphs as $p) {
            $text = trim($p->textContent ?? '');
            $text = preg_replace('/\s+/u', ' ', $text);
            if ($text !== '') {
                $feasts[] = $text;
            }
        }

        if (empty($feasts)) {
            $imgs = $xpath->query("//*[contains(@class, 'DD_ICON')]//img[contains(@class, 'DI')]");
            foreach ($imgs as $img) {
                $alt = trim($img->getAttribute('alt') ?? '');
                if ($alt !== '') {
                    $feasts[] = $alt;
                }
            }
        }

        return $feasts;
    }

    private function emptyResult(string $date, string $dateOldStyle): array
    {
        return [
            'date' => $date,
            'dateOldStyle' => $dateOldStyle,
            'fastInfo' => '',
            'weekName' => null,
            'voice' => null,
            'feasts' => [],
        ];
    }
}
