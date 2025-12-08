<?php

namespace App\Model;

class ContentModel
{
    // Список популярных книг и диапазон глав для случайной выборки
    private const BIBLE_BOOKS = [
        'Matthew' => 28,
        'Mark' => 16,
        'Luke' => 24,
        'John' => 21,
        'Psalms' => 150,
        'Proverbs' => 31,
        'Romans' => 16,
        '1 Corinthians' => 16,
        'Ephesians' => 6,
        'James' => 5,
        '1 John' => 5,
    ];

    public function __construct(
        private array $prayers,
        private array $quotes
    ) {}

    /**
     * Получает случайную молитву
     * 
     * @param string $lang Язык молитвы: 'русский' или 'цс' (церковнославянский). По умолчанию 'русский'
     * @return array ['title' => string|null, 'text' => string]
     */
    public function getRandomPrayer(string $lang = 'русский'): array
    {
        if (empty($this->prayers)) {
            return ['title' => null, 'text' => "Список молитв пуст."];
        }

        // Выбираем случайную молитву
        $randomIndex = array_rand($this->prayers);
        $prayer = $this->prayers[$randomIndex];

        // Получаем название
        $title = $prayer['name'] ?? null;
        
        // Получаем текст на выбранном языке, если нет - берем русский
        $text = $prayer['data'][$lang] ?? $prayer['data']['русский'] ?? '';
        
        // Если текст пустой, возвращаем сообщение об ошибке
        if (empty($text)) {
            return ['title' => $title, 'text' => "Текст молитвы не найден."];
        }

        return ['title' => $title, 'text' => $text];
    }

    /**
     * Получает случайную цитату из Библии из XML файла:
     * 1. Использует индексный файл для быстрого доступа
     * 2. Читает только нужный стих из JSON Lines файла (не загружает весь файл)
     * 
     * @return array ['name' => null, 'text' => string] - цитата из Библии
     */
    public function getRandomQuote(): array
    {
        $xmlPath = __DIR__ . '/../../storage/rus-synodal.zefania.xml';
        $jsonlPath = __DIR__ . '/../../storage/bible_verses.jsonl';
        $indexPath = __DIR__ . '/../../storage/bible_index.json';
        
        // Проверяем существование XML файла
        if (!file_exists($xmlPath)) {
            return [
                'name' => null,
                'text' => "Файл Библии не найден."
            ];
        }
        
        // Инициализируем кэш (если нужно)
        $this->ensureBibleCache($xmlPath, $jsonlPath, $indexPath);
        
        // Загружаем индекс (легкий файл, ~1 КБ)
        $index = $this->loadIndex($indexPath);
        if (empty($index) || empty($index['count']) || $index['count'] <= 0) {
            return [
                'name' => null,
                'text' => "Не удалось загрузить индекс Библии."
            ];
        }
        
        // Выбираем случайный номер стиха (от 0 до count-1)
        $randomVerseNumber = rand(0, $index['count'] - 1);
        
        // Загружаем только нужный стих из JSON Lines (читаем только одну строку)
        $verse = $this->loadVerseByIndex($jsonlPath, $randomVerseNumber);
        
        if (empty($verse)) {
            return [
                'name' => null,
                'text' => "Не удалось загрузить стих."
            ];
        }
        
        // Формируем текст цитаты
        $text = $verse['text'];
        $reference = "{$verse['book']} {$verse['chapter']}:{$verse['verse']}";
        
        return [
            'name' => null,
            'text' => $text . "\n\n(" . $reference . ")"
        ];
    }

    /**
     * Получает список всех уникальных святых из цитат
     * 
     * @return array Массив имен святых
     */
    public function getSaintsList(): array
    {
        if (empty($this->quotes)) {
            return [];
        }

        $saints = [];
        foreach ($this->quotes as $quote) {
            if (is_array($quote) && isset($quote['name']) && !empty($quote['name'])) {
                $saintName = $quote['name'];
                if (!in_array($saintName, $saints, true)) {
                    $saints[] = $saintName;
                }
            }
        }

        return $saints;
    }

    /**
     * Получает случайную цитату святого
     * 
     * @param string|null $saintName Имя святого (опционально). Если не указано - случайный святой
     * @return array ['name' => string, 'text' => string] или ['name' => null, 'text' => string] для ошибок
     */
    public function getSaintQuote(?string $saintName = null): array
    {
        if (empty($this->quotes)) {
            return [
                'name' => null,
                'text' => "Список цитат пуст."
            ];
        }

        // Фильтруем цитаты по имени святого, если указано
        $filteredQuotes = [];
        if ($saintName !== null && $saintName !== '') {
            $saintNameLower = mb_strtolower(trim($saintName));
            foreach ($this->quotes as $quote) {
                if (is_array($quote) && isset($quote['name']) && isset($quote['text'])) {
                    $quoteNameLower = mb_strtolower($quote['name']);
                    // Поиск по частичному совпадению
                    if (mb_strpos($quoteNameLower, $saintNameLower) !== false) {
                        $filteredQuotes[] = $quote;
                    }
                }
            }

            if (empty($filteredQuotes)) {
                return [
                    'name' => null,
                    'text' => "Цитаты святого '{$saintName}' не найдены."
                ];
            }
        } else {
            // Берем все цитаты святых (исключаем цитаты без имени)
            foreach ($this->quotes as $quote) {
                if (is_array($quote) && isset($quote['name']) && isset($quote['text']) && !empty($quote['name'])) {
                    $filteredQuotes[] = $quote;
                }
            }

            if (empty($filteredQuotes)) {
                return [
                    'name' => null,
                    'text' => "Цитаты святых не найдены."
                ];
            }
        }

        // Выбираем случайную цитату из отфильтрованных
        $randomIndex = array_rand($filteredQuotes);
        $quote = $filteredQuotes[$randomIndex];

        return [
            'name' => $quote['name'],
            'text' => $quote['text']
        ];
    }

    /**
     * Инициализирует кэш: парсит XML и создает JSON Lines + индекс
     * 
     * @param string $xmlPath Путь к XML файлу
     * @param string $jsonlPath Путь к JSON Lines файлу
     * @param string $indexPath Путь к индексному файлу
     */
    private function ensureBibleCache(string $xmlPath, string $jsonlPath, string $indexPath): void
    {
        // Если кэш существует и свежий - ничего не делаем
        if (file_exists($jsonlPath) && file_exists($indexPath)) {
            $jsonlMtime = filemtime($jsonlPath);
            $xmlMtime = filemtime($xmlPath);
            
            // Проверяем, что кэш новее XML файла
            if ($jsonlMtime >= $xmlMtime) {
                return;
            }
        }
        
        // Парсим XML и создаем кэш
        $verses = $this->parseBibleXml($xmlPath);
        
        if (empty($verses)) {
            return;
        }
        
        // Сохраняем в JSON Lines формат (каждая строка = один JSON объект)
        $this->saveVersesAsJsonLines($verses, $jsonlPath);
        
        // Сохраняем индекс (только метаданные)
        $index = [
            'count' => count($verses),
            'created_at' => time(),
            'xml_mtime' => filemtime($xmlPath)
        ];
        
        file_put_contents(
            $indexPath,
            json_encode($index, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * Загружает индексный файл
     * 
     * @param string $indexPath Путь к индексному файлу
     * @return array Массив с метаданными ['count' => int, 'created_at' => int, ...]
     */
    private function loadIndex(string $indexPath): array
    {
        if (!file_exists($indexPath)) {
            return [];
        }
        
        $content = file_get_contents($indexPath);
        $index = json_decode($content, true);
        
        return is_array($index) ? $index : [];
    }

    /**
     * Загружает конкретный стих по индексу из JSON Lines файла
     * Читает только нужную строку, не загружая весь файл в память
     * 
     * @param string $jsonlPath Путь к JSON Lines файлу
     * @param int $index Номер строки (начиная с 0)
     * @return array|null Массив стиха ['book' => string, 'chapter' => int, 'verse' => int, 'text' => string] или null
     */
    private function loadVerseByIndex(string $jsonlPath, int $index): ?array
    {
        if (!file_exists($jsonlPath)) {
            return null;
        }
        
        try {
            $file = new \SplFileObject($jsonlPath, 'r');
            
            // Переходим к нужной строке (индекс = номер строки)
            $file->seek($index);
            
            // Читаем текущую строку
            $line = $file->current();
            
            if ($line === false || $line === '') {
                return null;
            }
            
            // Декодируем JSON из строки
            $verse = json_decode(trim($line), true);
            
            return is_array($verse) ? $verse : null;
            
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Сохраняет стихи в JSON Lines формат (каждая строка = один JSON объект)
     * 
     * @param array $verses Массив стихов
     * @param string $jsonlPath Путь к файлу для сохранения
     */
    private function saveVersesAsJsonLines(array $verses, string $jsonlPath): void
    {
        $file = fopen($jsonlPath, 'w');
        
        if ($file === false) {
            return;
        }
        
        foreach ($verses as $verse) {
            $jsonLine = json_encode($verse, JSON_UNESCAPED_UNICODE) . "\n";
            fwrite($file, $jsonLine);
        }
        
        fclose($file);
    }

    /**
     * Парсит XML файл Библии и извлекает все стихи
     * 
     * @param string $xmlPath Путь к XML файлу
     * @return array Массив стихов [['book' => string, 'chapter' => int, 'verse' => int, 'text' => string], ...]
     */
    private function parseBibleXml(string $xmlPath): array
    {
        $verses = [];
        
        try {
            // Загружаем XML файл
            $xml = simplexml_load_file($xmlPath);
            
            if ($xml === false) {
                return [];
            }
            
            // Проходим по всем книгам
            foreach ($xml->BIBLEBOOK as $book) {
                // Пропускаем книги Ветхого Завета (bnumber < 40)
                // Оставляем только Новый Завет (начиная с bnumber = 40)
                $bookNumber = (int)$book['bnumber'];
                if ($bookNumber < 40) {
                    continue;
                }
                
                $bookName = (string)$book['bname'];
                
                // Проходим по всем главам
                foreach ($book->CHAPTER as $chapter) {
                    $chapterNumber = (int)$chapter['cnumber'];
                    
                    // Проходим по всем стихам
                    foreach ($chapter->VERS as $verse) {
                        $verseNumber = (int)$verse['vnumber'];
                        $verseText = trim((string)$verse);
                        
                        // Пропускаем пустые стихи
                        if (empty($verseText)) {
                            continue;
                        }
                        
                        $verses[] = [
                            'book' => $bookName,
                            'chapter' => $chapterNumber,
                            'verse' => $verseNumber,
                            'text' => $verseText
                        ];
                    }
                }
            }
            
        } catch (\Throwable $e) {
            // В случае ошибки возвращаем пустой массив
            return [];
        }
        
        return $verses;
    }
}
