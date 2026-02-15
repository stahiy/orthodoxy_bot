<?php

declare(strict_types=1);

namespace App\Model;

use App\Lib\PravoslavieDaysParser;

class CalendarModel
{
    private ?array $holidaysData = null;

    public function __construct(
        private string $holidaysJsonPath
    ) {}

    /**
     * Получить полное описание дня из JSON-календаря: пост, седмица, глас, праздники.
     * Если даты нет в JSON — парсим эту дату плюс неделю с days.pravoslavie.ru и дополняем файл.
     */
    public function getHoliday(string $date = 'now'): ?string
    {
        $dt = new \DateTime($date);
        $dateKey = $dt->format('Y-m-d');

        $data = $this->loadHolidaysData();

        if ($data === null || !isset($data[$dateKey])) {
            $this->parseAndAppendRange($dateKey, 7);
            $data = $this->holidaysData;
        }

        if ($data === null || !isset($data[$dateKey])) {
            return null;
        }

        return $this->formatDay($data[$dateKey]);
    }

    /**
     * Парсит диапазон дней (начиная с даты) и дополняет JSON-файл и кэш.
     */
    private function parseAndAppendRange(string $startDate, int $days): void
    {
        $parser = new PravoslavieDaysParser();
        $start = new \DateTimeImmutable($startDate);
        $newData = [];

        for ($i = 0; $i < $days; $i++) {
            $d = $start->modify("+{$i} days")->format('Y-m-d');
            $day = $parser->parseDay($d);
            $newData[$day['date']] = $day;
        }

        $existing = $this->holidaysData ?? [];
        if ($this->holidaysData === null && is_readable($this->holidaysJsonPath)) {
            $json = file_get_contents($this->holidaysJsonPath);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                $existing = is_array($decoded) ? $decoded : [];
            }
        }

        $merged = array_merge($existing, $newData);
        ksort($merged);

        $dir = dirname($this->holidaysJsonPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = file_put_contents(
            $this->holidaysJsonPath,
            json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        if ($written !== false) {
            $this->holidaysData = $merged;
        }
    }

    /** @var array<int, string> Месяцы по-русски (1–12) */
    private static array $monthsRu = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /**
     * Форматирует дату Y-m-d в читаемый вид (например: «1 февраля» или «19 января (по старому стилю)»).
     */
    private function formatDate(string $dateStr, bool $oldStyle = false): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateStr);
        if ($dt === false) {
            return $dateStr . ($oldStyle ? ' (по старому стилю)' : '');
        }
        $day = (int) $dt->format('j');
        $month = (int) $dt->format('n');
        $monthName = self::$monthsRu[$month] ?? $dt->format('m');
        $suffix = $oldStyle ? ' (по старому стилю)' : '';
        return "{$day} {$monthName}{$suffix}";
    }

    /**
     * Форматирует запись дня в строку для бота.
     */
    private function formatDay(array $day): ?string
    {
        $parts = [];

        $dateNew = !empty($day['date']) ? $this->formatDate($day['date'], false) : null;
        $dateOld = !empty($day['dateOldStyle']) ? $this->formatDate($day['dateOldStyle'], true) : null;
        if ($dateNew !== null && $dateOld !== null) {
            $parts[] = "{$dateNew} / {$dateOld}";
        } elseif ($dateNew !== null) {
            $parts[] = $dateNew;
        } elseif ($dateOld !== null) {
            $parts[] = $dateOld;
        }
        if (!empty($day['feasts']) && is_array($day['feasts'])) {
            $parts[] = '🗓 ' . implode("\n\n", $day['feasts']);
        }
        if (!empty($day['fastInfo'])) {
            $parts[] = $day['fastInfo'];
        }
        if (!empty($day['weekName'])) {
            $parts[] = $day['weekName'];
        }
        if (!empty($day['voice'])) {
            $parts[] = $day['voice'];
        }

        if (empty($parts)) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Загружает и кэширует данные из JSON-файла календаря.
     */
    private function loadHolidaysData(): ?array
    {
        if ($this->holidaysData !== null) {
            return $this->holidaysData;
        }

        if (!is_readable($this->holidaysJsonPath)) {
            return null;
        }

        $json = file_get_contents($this->holidaysJsonPath);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $this->holidaysData = $decoded;
        return $this->holidaysData;
    }
}
