<?php

declare(strict_types=1);

/**
 * Парсер цитат с сайта azbyka.ru/quotes/
 * 
 * Использование:
 * php parsing/parse_quotes.php [--max-pages=N] [--output=data/quotes.php]
 */

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

// Загружаем автолоадер
require __DIR__ . '/../vendor/autoload.php';

// Загружаем конфиг для получения путей
$config = require __DIR__ . '/../config.php';

// Парсим аргументы командной строки
$maxPages = 530; // По умолчанию парсим 10 страниц
$outputFile = $config['paths']['quotes_file'] ?? __DIR__ . '/../data/quotes.php';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--max-pages=')) {
        $maxPages = (int)substr($arg, strlen('--max-pages='));
    }
    if (str_starts_with($arg, '--output=')) {
        $outputFile = substr($arg, strlen('--output='));
        // Если относительный путь, делаем абсолютным
        if (!str_starts_with($outputFile, '/')) {
            $outputFile = __DIR__ . '/../' . ltrim($outputFile, '/');
        }
    }
}

class QuotesParser
{
    private const BASE_URL = 'https://azbyka.ru/quotes/';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    private const TIMEOUT = 30;
    
    private array $quotes = [];
    private int $parsedPages = 0;
    
    /**
     * Получение HTML страницы через cURL
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
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '', // Поддержка gzip/deflate
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            echo "Ошибка cURL: {$error}\n";
            return null;
        }
        
        if ($httpCode !== 200) {
            echo "HTTP код: {$httpCode} для URL: {$url}\n";
            return null;
        }
        
        return $html;
    }
    
    /**
     * Парсинг HTML страницы с цитатами
     */
    private function parsePage(string $html): void
    {
        // Создаем DOMDocument для парсинга
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        
        // Преобразуем HTML в UTF-8 и загружаем
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Ищем все блоки с цитатами
        // На основе анализа структуры: цитаты находятся в article или div.post
        // Текст цитаты внутри H2, автор внутри .avtor
        $quoteNodes = $xpath->query(
            "//article | " .
            "//div[contains(@class, 'post')] | " .
            "//div[contains(@class, 'quote')] "
        );
        
        foreach ($quoteNodes as $node) {
            $quoteText = $this->extractQuoteText($node, $xpath);
            $author = $this->extractAuthor($node, $xpath);
            
            // Если текст пустой, возможно мы взяли не тот контейнер
            // Пробуем найти текст напрямую в H2 если это article
            if (empty($quoteText) && $node->nodeName === 'article') {
                 $h2 = $xpath->query(".//h2", $node)->item(0);
                 if ($h2) $quoteText = trim($h2->textContent);
            }
            
            if (!empty($quoteText) && strlen(trim($quoteText)) > 10) {
                // Формируем массив данных
                $quoteData = [
                    'name' => $author ?: 'Неизвестный автор',
                    'text' => trim($quoteText)
                ];
                
                // Проверяем на дубликаты по тексту
                $isDuplicate = false;
                foreach ($this->quotes as $existingQuote) {
                    if ($existingQuote['text'] === $quoteData['text']) {
                        $isDuplicate = true;
                        break;
                    }
                }
                
                if (!$isDuplicate) {
                    $this->quotes[] = $quoteData;
                }
            }
        }
    }
    
    /**
     * Извлечение текста цитаты
     */
    private function extractQuoteText(DOMNode $node, DOMXPath $xpath): string
    {
        // 1. Приоритет: текст внутри H2 (согласно анализу структуры)
        $h2Nodes = $xpath->query(".//h2", $node);
        if ($h2Nodes->length > 0) {
            $text = trim($h2Nodes->item(0)->textContent);
            // Очищаем от лишних символов если нужно
            return $text;
        }

        // 2. Если нет H2, ищем в .text или .quote-text
        $textNodes = $xpath->query(".//div[contains(@class, 'text')] | .//div[contains(@class, 'quote-content')]", $node);
        if ($textNodes->length > 0) {
            return trim($textNodes->item(0)->textContent);
        }

        // 3. Fallback: ищем самый длинный текстовый узел
        // Но исключаем автора и метаданные
        $paragraphs = $xpath->query(".//p", $node);
        $longestText = '';
        foreach ($paragraphs as $p) {
             $text = trim($p->textContent);
             if (strlen($text) > strlen($longestText)) {
                 $longestText = $text;
             }
        }
        
        if (!empty($longestText)) {
            return $longestText;
        }
        
        return '';
    }
    
    /**
     * Извлечение автора цитаты
     */
    private function extractAuthor(DOMNode $node, DOMXPath $xpath): string
    {
        // 1. Приоритет: .avtor (согласно анализу)
        $authorNodes = $xpath->query(".//*[contains(@class, 'avtor')]", $node);
        if ($authorNodes->length > 0) {
            $author = trim($authorNodes->item(0)->textContent);
            // Убираем лишнее, если есть (например "Все цитаты автора")
            $author = explode('•', $author)[0]; // Обычно там разделитель
            return trim($author);
        }

        // 2. Ищем по ключевым словам святых
        $potentialAuthors = $xpath->query(
            ".//strong[contains(text(), 'прав.') or contains(text(), 'прп.') or contains(text(), 'свт.')] | " .
            ".//span[contains(text(), 'прав.') or contains(text(), 'прп.') or contains(text(), 'свт.')]"
        );
        
        foreach ($potentialAuthors as $authorNode) {
             return trim($authorNode->textContent);
        }

        return 'Автор неизвестен';
    }
    
    /**
     * Поиск следующей страницы (пагинация)
     */
    private function findNextPage(string $html): ?string
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Ищем ссылку на следующую страницу
        $nextLinks = $xpath->query(
            "//a[contains(text(), 'Следующая') or contains(text(), '→') or contains(@class, 'next') or contains(@rel, 'next')]"
        );
        
        foreach ($nextLinks as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                if (!empty($href)) {
                    return $this->makeAbsoluteUrl($href);
                }
            }
        }
        
        // Альтернативный поиск: ищем ссылки с номерами страниц
        $currentPage = $this->parsedPages + 1;
        $nextPage = $currentPage + 1;
        
        // Ищем ссылки на страницы
        $pageLinks = $xpath->query("//a[contains(@href, '/quotes/page/') or contains(@href, '?page=') or contains(@href, '/page/')]");
        
        foreach ($pageLinks as $link) {
            if ($link instanceof DOMElement) {
                $href = $link->getAttribute('href');
                $linkText = trim($link->textContent);
                
                // Проверяем, что это ссылка на следующую страницу
                if ($linkText == (string)$nextPage || 
                    str_contains($href, "page/{$nextPage}") || 
                    str_contains($href, "?page={$nextPage}") ||
                    str_contains($href, "/page/{$nextPage}/")) {
                    return $this->makeAbsoluteUrl($href);
                }
            }
        }
        
        // Если не нашли явную ссылку, пробуем сформировать URL следующей страницы
        if ($currentPage === 1) {
            // На первой странице пробуем перейти на вторую
            return self::BASE_URL . 'page/2/';
        }
        
        return null;
    }
    
    /**
     * Преобразование относительного URL в абсолютный
     */
    private function makeAbsoluteUrl(string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }
        
        if (str_starts_with($href, '/')) {
            return 'https://azbyka.ru' . $href;
        }
        
        return self::BASE_URL . ltrim($href, '/');
    }
    
    /**
     * Основной метод парсинга
     */
    public function parse(int $maxPages): array
    {
        echo "Начинаем парсинг цитат с azbyka.ru/quotes/\n";
        echo "Максимум страниц: {$maxPages}\n\n";
        
        $currentUrl = self::BASE_URL;
        $this->parsedPages = 0;
        
        while ($this->parsedPages < $maxPages) {
            echo "Парсинг страницы " . ($this->parsedPages + 1) . ": {$currentUrl}\n";
            
            $html = $this->fetchPage($currentUrl);
            
            if ($html === null) {
                echo "Не удалось загрузить страницу. Пропускаем.\n\n";
                break;
            }
            
            $quotesBefore = count($this->quotes);
            $this->parsePage($html);
            $quotesAfter = count($this->quotes);
            $newQuotes = $quotesAfter - $quotesBefore;
            
            echo "Найдено цитат на странице: {$newQuotes}\n";
            echo "Всего цитат: {$quotesAfter}\n\n";
            
            $this->parsedPages++;
            
            // Ищем следующую страницу
            $nextUrl = $this->findNextPage($html);
            
            if ($nextUrl === null || $nextUrl === $currentUrl) {
                echo "Следующая страница не найдена. Завершаем парсинг.\n";
                break;
            }
            
            $currentUrl = $nextUrl;
            
            // Небольшая задержка, чтобы не перегружать сервер
            sleep(2);
        }
        
        echo "Парсинг завершен. Всего собрано цитат: " . count($this->quotes) . "\n";
        
        return $this->quotes;
    }
    
    /**
     * Сохранение цитат в файл
     */
    public function saveToFile(array $quotes, string $filename): void
    {
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Православные цитаты с azbyka.ru/quotes/\n";
        $content .= " * Сгенерировано: " . date('Y-m-d H:i:s') . "\n";
        $content .= " * Всего цитат: " . count($quotes) . "\n";
        $content .= " */\n";
        $content .= "return [\n";
        
        foreach ($quotes as $quote) {
            $name = $quote['name'];
            $text = $quote['text'];
            
            // Экранируем кавычки и спецсимволы
            $escapedName = str_replace(["\\", "'"], ["\\\\", "\'"], $name);
            $escapedText = str_replace(["\\", '"'], ["\\\\", '\"'], $text);
            $escapedText = str_replace(["\r\n", "\r", "\n"], "\\n", $escapedText);
            $escapedText = str_replace('$', '\\$', $escapedText);
            
            $content .= "    [\n";
            $content .= "        \"name\" => '{$escapedName}',\n";
            $content .= "        \"text\" => \"{$escapedText}\",\n";
            $content .= "    ],\n";
        }
        
        $content .= "];\n";
        
        // Создаем директорию, если её нет
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($filename, $content);
        echo "Цитаты сохранены в файл: {$filename}\n";
    }
}

// Запуск парсера
try {
    $parser = new QuotesParser();
    $quotes = $parser->parse($maxPages);
    
    if (!empty($quotes)) {
        $parser->saveToFile($quotes, $outputFile);
        echo "\nГотово! Парсинг завершен успешно.\n";
        echo "Собрано цитат: " . count($quotes) . "\n";
    } else {
        echo "\nВнимание: не удалось собрать цитаты. Проверьте структуру HTML на сайте.\n";
        echo "Запустите parsing/parse_quotes_debug.php для анализа структуры HTML.\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "Ошибка при парсинге: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

