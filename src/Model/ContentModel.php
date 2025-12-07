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
     * Получает случайную цитату:
     * 1. Сначала пробует загрузить случайный стих через Bible API.
     * 2. Если не удалось (ошибка сети), берет из локального конфига.
     */
    public function getRandomQuote(): string
    {
        $apiQuote = $this->fetchFromApi();
        
        if ($apiQuote) {
            return $apiQuote;
        }
        
        // Fallback на локальные цитаты
        if (empty($this->quotes)) {
            return "Список цитат пуст.";
        }
        return $this->quotes[array_rand($this->quotes)];
    }

    /**
     * @return string|null
     */
    private function fetchFromApi(): ?string
    {
        try {
            // Выбираем случайную книгу
            $book = array_rand(self::BIBLE_BOOKS);
            $maxChapter = self::BIBLE_BOOKS[$book];
            
            // Выбираем случайную главу
            $chapter = rand(1, $maxChapter);
            
            // Формируем запрос (берем всю главу или первые стихи, API вернет случайный стих если указать random, но bible-api.com работает по ссылкам)
            // bible-api.com/Matthew+5?translation=rus-synodal вернет всю главу. Это много.
            // Лучше запросить случайный стих. Но API bible-api.com требует точной ссылки.
            // Есть эндпоинт /random, но он выдает на английском по умолчанию, перевод там ограничен.
            // Поэтому сделаем хитрее: запросим конкретную главу и выберем из ответа случайный стих (или несколько).
            // Или проще: будем запрашивать конкретный стих, например, 1. (Почти во всех главах есть стих 1)
            // Чтобы было интереснее, попробуем угадать стих. В среднем в главе 20-30 стихов.
            $verse = rand(1, 20);
            
            $url = "https://bible-api.com/{$book}+{$chapter}:{$verse}?translation=rus-synodal";
            
            // Используем file_get_contents с таймаутом
            $ctx = stream_context_create(['http' => ['timeout' => 3]]);
            $response = @file_get_contents($url, false, $ctx);
            
            if (!$response) {
                return null;
            }

            $data = json_decode($response, true);
            if (!isset($data['text'])) {
                return null;
            }

            // Формируем красивый ответ
            // data['reference'] содержит ссылку (например, Иоанна 3:16)
            // data['text'] содержит текст
            return trim($data['text']) . "\n\n(" . $data['reference'] . ")";

        } catch (\Throwable $e) {
            return null;
        }
    }
}
