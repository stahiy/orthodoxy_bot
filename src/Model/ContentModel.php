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
     * Выбирает цитату целиком, начиная со стиха с заглавной буквы (начало предложения)
     * до выбранного стиха включительно
     * Использует индекс по главам для оптимизации загрузки
     * 
     * @return array ['name' => null, 'text' => string] - цитата из Библии
     */
    public function getRandomQuote(): array
    {
        $xmlPath = __DIR__ . '/../../storage/rus-synodal.zefania.xml';
        $jsonlPath = __DIR__ . '/../../storage/bible_verses.jsonl';
        $indexPath = __DIR__ . '/../../storage/bible_index.json';
        $chaptersIndexPath = __DIR__ . '/../../storage/bible_chapters_index.json';
        
        // Проверяем существование XML файла
        if (!file_exists($xmlPath)) {
            return [
                'name' => null,
                'text' => "Файл Библии не найден."
            ];
        }
        
        // Инициализируем кэш (если нужно)
        $this->ensureBibleCache($xmlPath, $jsonlPath, $indexPath, $chaptersIndexPath);
        
        // Загружаем индекс
        $index = $this->loadIndex($indexPath);
        if (empty($index) || empty($index['count']) || $index['count'] <= 0) {
            return [
                'name' => null,
                'text' => "Не удалось загрузить индекс Библии."
            ];
        }
        
        // Выбираем случайный номер стиха (от 0 до count-1)
        $randomVerseNumber = rand(0, $index['count'] - 1);
        
        // Загружаем случайный стих
        $randomVerse = $this->loadVerseByIndex($jsonlPath, $randomVerseNumber);
        
        if (empty($randomVerse)) {
            return [
                'name' => null,
                'text' => "Не удалось загрузить стих."
            ];
        }
        
        // Загружаем стихи той же главы используя индекс по главам
        $chapterVerses = $this->loadChapterVersesByIndex(
            $jsonlPath,
            $chaptersIndexPath,
            $randomVerse['book'],
            $randomVerse['chapter']
        );
        
        if (empty($chapterVerses)) {
            // Fallback: возвращаем один стих
            $text = $randomVerse['text'];
            $reference = "{$randomVerse['book']} {$randomVerse['chapter']}:{$randomVerse['verse']}";
            return [
                'name' => null,
                'text' => $text . "\n\n(" . $reference . ")"
            ];
        }
        
        // Находим индекс случайного стиха в массиве стихов главы
        $verseIndexInChapter = -1;
        foreach ($chapterVerses as $idx => $verse) {
            if ($verse['verse'] === $randomVerse['verse']) {
                $verseIndexInChapter = $idx;
                break;
            }
        }
        
        if ($verseIndexInChapter === -1) {
            // Если не нашли - используем первый стих
            $verseIndexInChapter = 0;
        }
        
        // Проверяем первую букву случайного стиха
        $currentVerseText = trim($chapterVerses[$verseIndexInChapter]['text']);
        $firstChar = mb_substr($currentVerseText, 0, 1);
        $startIndex = $verseIndexInChapter;
        
        // Если первая буква строчная - идём вверх до заглавной (начало предложения)
        if ($this->isLowerCase($firstChar)) {
            // Идём вверх по стихам, пока не найдём стих с заглавной буквы
            for ($i = $verseIndexInChapter - 1; $i >= 0; $i--) {
                $verseText = trim($chapterVerses[$i]['text']);
                if (empty($verseText)) {
                    continue;
                }
                
                $char = mb_substr($verseText, 0, 1);
                // Если первая буква заглавная - нашли начало предложения
                if ($this->isUpperCase($char)) {
                    $startIndex = $i;
                    break;
                }
            }
        }
        
        // Ищем конец предложения: идём вниз от выбранного стиха
        // Предложение заканчивается на точку, восклицательный или вопросительный знак
        // или когда начинается следующий стих с заглавной буквы
        $endIndex = $verseIndexInChapter;
        $chapterVersesCount = count($chapterVerses);
        $foundEnd = false;
        
        for ($i = $verseIndexInChapter; $i < $chapterVersesCount; $i++) {
            $verseText = trim($chapterVerses[$i]['text']);
            if (empty($verseText)) {
                continue;
            }
            
            // Проверяем, заканчивается ли стих на знак окончания предложения
            // Точка, восклицательный или вопросительный знак - это конец предложения
            // Точка с запятой (;) НЕ является концом предложения
            // Учитываем возможные пробелы после знака препинания
            $trimmedVerse = rtrim($verseText);
            $lastChar = mb_substr($trimmedVerse, -1);
            if (in_array($lastChar, ['。', '.', '!', '?'], true)) {
                $endIndex = $i;
                $foundEnd = true;
                break;
            }
            
            // Если следующий стих начинается с заглавной буквы - это конец предложения
            // Это означает, что текущий стих - последний в предложении
            if ($i + 1 < $chapterVersesCount) {
                $nextVerseText = trim($chapterVerses[$i + 1]['text']);
                if (!empty($nextVerseText)) {
                    $nextFirstChar = mb_substr($nextVerseText, 0, 1);
                    // Проверяем, что это действительно начало нового предложения
                    // (заглавная буква, а не просто цифра или другой символ)
                    if ($this->isUpperCase($nextFirstChar)) {
                        // Если следующий стих начинается с заглавной буквы - это явный признак нового предложения
                        // Останавливаемся на текущем стихе
                        $endIndex = $i;
                        $foundEnd = true;
                        break;
                    }
                }
            }
        }
        
        // Если не нашли конец предложения - берем только выбранный стих
        // Это более консервативный подход, чтобы не захватывать лишние предложения
        if (!$foundEnd) {
            $endIndex = $verseIndexInChapter;
        }
        
        // Собираем цитату от начала предложения до выбранного стиха включительно
        $quoteVerses = [];
        for ($i = $startIndex; $i <= $endIndex; $i++) {
            $quoteVerses[] = $chapterVerses[$i];
        }
        
        if (empty($quoteVerses)) {
            // Если не удалось собрать цитату - возвращаем хотя бы один стих
            $text = $randomVerse['text'];
            $reference = "{$randomVerse['book']} {$randomVerse['chapter']}:{$randomVerse['verse']}";
            return [
                'name' => null,
                'text' => $text . "\n\n(" . $reference . ")"
            ];
        }
        
        // Объединяем текст стихов через пробел, так как это одно предложение
        // (мы уже ограничили цитату одним предложением выше)
        $versesTexts = array_filter(array_map('trim', array_column($quoteVerses, 'text')), fn($t) => !empty($t));
        $text = implode(' ', $versesTexts);
        
        // Формируем ссылку (например: "От Матфея 5:3-7")
        $startVerse = $quoteVerses[0]['verse'];
        $endVerse = $quoteVerses[count($quoteVerses) - 1]['verse'];
        
        if ($startVerse === $endVerse) {
            $reference = "{$quoteVerses[0]['book']} {$quoteVerses[0]['chapter']}:{$startVerse}";
        } else {
            $reference = "{$quoteVerses[0]['book']} {$quoteVerses[0]['chapter']}:{$startVerse}-{$endVerse}";
        }
        
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
     * Инициализирует кэш: парсит XML и создает JSON Lines + индексы
     * 
     * @param string $xmlPath Путь к XML файлу
     * @param string $jsonlPath Путь к JSON Lines файлу
     * @param string $indexPath Путь к индексному файлу
     * @param string $chaptersIndexPath Путь к индексному файлу по главам
     */
    private function ensureBibleCache(string $xmlPath, string $jsonlPath, string $indexPath, string $chaptersIndexPath): void
    {
        // Если кэш существует и свежий - ничего не делаем
        if (file_exists($jsonlPath) && file_exists($indexPath) && file_exists($chaptersIndexPath)) {
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
        
        // Создаем индекс по главам (диапазоны строк для каждой главы)
        $chaptersIndex = $this->buildChaptersIndex($verses);
        
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
        
        // Сохраняем индекс по главам
        file_put_contents(
            $chaptersIndexPath,
            json_encode(['chapters' => $chaptersIndex], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
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

    /**
     * Проверяет, является ли символ строчной буквой
     * 
     * @param string $char Символ для проверки
     * @return bool true если строчная буква
     */
    private function isLowerCase(string $char): bool
    {
        // Проверяем, что это буква и она в нижнем регистре
        return mb_strlen($char) === 1 
            && mb_strtolower($char) === $char 
            && $char !== mb_strtoupper($char)
            && preg_match('/\p{L}/u', $char);
    }

    /**
     * Проверяет, является ли символ заглавной буквой
     * 
     * @param string $char Символ для проверки
     * @return bool true если заглавная буква
     */
    private function isUpperCase(string $char): bool
    {
        // Проверяем, что это буква и она в верхнем регистре
        return mb_strlen($char) === 1 
            && mb_strtoupper($char) === $char 
            && $char !== mb_strtolower($char)
            && preg_match('/\p{L}/u', $char);
    }

    /**
     * Загружает все стихи указанной главы из JSON Lines файла используя индекс по главам
     * 
     * @param string $jsonlPath Путь к JSON Lines файлу
     * @param string $chaptersIndexPath Путь к индексному файлу по главам
     * @param string $bookName Название книги
     * @param int $chapterNumber Номер главы
     * @return array Массив стихов главы [['book' => string, 'chapter' => int, 'verse' => int, 'text' => string], ...]
     */
    private function loadChapterVersesByIndex(string $jsonlPath, string $chaptersIndexPath, string $bookName, int $chapterNumber): array
    {
        if (!file_exists($jsonlPath) || !file_exists($chaptersIndexPath)) {
            return [];
        }
        
        // Загружаем индекс по главам
        $chaptersIndex = $this->loadChaptersIndex($chaptersIndexPath);
        if (empty($chaptersIndex)) {
            return [];
        }
        
        // Получаем диапазон стихов для нужной главы
        $chapterKey = (string)$chapterNumber;
        if (!isset($chaptersIndex[$bookName][$chapterKey])) {
            return [];
        }
        
        $chapterRange = $chaptersIndex[$bookName][$chapterKey];
        $startLine = $chapterRange['start'];
        $endLine = $chapterRange['end'];
        
        // Загружаем только нужные строки из файла
        $chapterVerses = [];
        
        try {
            $file = new \SplFileObject($jsonlPath, 'r');
            
            // Переходим к началу главы
            $file->seek($startLine);
            
            // Читаем стихи от startLine до endLine включительно
            for ($i = $startLine; $i <= $endLine; $i++) {
                if ($file->eof()) {
                    break;
                }
                
                $line = $file->current();
                if ($line === false || $line === '') {
                    $file->next();
                    continue;
                }
                
                $verse = json_decode(trim($line), true);
                if (is_array($verse) && $verse['book'] === $bookName && $verse['chapter'] === $chapterNumber) {
                    $chapterVerses[] = $verse;
                }
                
                $file->next();
            }
            
        } catch (\Throwable $e) {
            return [];
        }
        
        // Сортируем по номеру стиха на случай, если порядок нарушен
        usort($chapterVerses, function ($a, $b) {
            return $a['verse'] <=> $b['verse'];
        });
        
        return $chapterVerses;
    }

    /**
     * Загружает индекс по главам
     * 
     * @param string $chaptersIndexPath Путь к индексному файлу
     * @return array Индекс вида ['book_name' => ['chapter_num' => ['start' => int, 'end' => int], ...], ...]
     */
    private function loadChaptersIndex(string $chaptersIndexPath): array
    {
        if (!file_exists($chaptersIndexPath)) {
            return [];
        }
        
        $content = file_get_contents($chaptersIndexPath);
        $index = json_decode($content, true);
        
        // Проверяем наличие структуры chapters
        if (!is_array($index) || !isset($index['chapters'])) {
            return [];
        }
        
        return $index['chapters'];
    }

    /**
     * Создает индекс по главам: для каждой книги+главы хранит диапазон индексов стихов
     * 
     * @param array $verses Массив всех стихов
     * @return array Индекс вида ['book_name' => ['chapter_num' => ['start' => int, 'end' => int], ...], ...]
     */
    private function buildChaptersIndex(array $verses): array
    {
        $chaptersIndex = [];
        $currentBook = null;
        $currentChapter = null;
        $chapterStart = null;
        
        foreach ($verses as $index => $verse) {
            $book = $verse['book'];
            $chapter = (string)$verse['chapter'];
            
            // Если сменилась книга или глава - сохраняем предыдущую главу
            if ($currentBook !== null && $currentChapter !== null) {
                if ($currentBook !== $book || $currentChapter !== $chapter) {
                    // Сохраняем диапазон предыдущей главы
                    if (!isset($chaptersIndex[$currentBook])) {
                        $chaptersIndex[$currentBook] = [];
                    }
                    $chaptersIndex[$currentBook][$currentChapter] = [
                        'start' => $chapterStart,
                        'end' => $index - 1
                    ];
                    
                    // Начинаем новую главу
                    $chapterStart = $index;
                }
            } else {
                // Первая глава
                $chapterStart = $index;
            }
            
            $currentBook = $book;
            $currentChapter = $chapter;
        }
        
        // Сохраняем последнюю главу
        if ($currentBook !== null && $currentChapter !== null) {
            if (!isset($chaptersIndex[$currentBook])) {
                $chaptersIndex[$currentBook] = [];
            }
            $chaptersIndex[$currentBook][$currentChapter] = [
                'start' => $chapterStart,
                'end' => count($verses) - 1
            ];
        }
        
        return $chaptersIndex;
    }
}
