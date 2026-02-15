<?php

declare(strict_types=1);

namespace App\Model;

class NewTestamentQuoteModel
{
    private const QUOTES_FILE = __DIR__ . '/../../storage/new_testament_quotes.json';

    /**
     * Получает случайную цитату из Нового Завета с толкованием.
     * Данные берутся из storage/new_testament_quotes.json
     *
     * @return array{name: null, text: string, interpretation?: string} — цитата из Библии
     */
    public function getRandomQuote(): array
    {
        if (!file_exists(self::QUOTES_FILE)) {
            return [
                'name' => null,
                'text' => "Файл с цитатами Нового Завета не найден."
            ];
        }

        $content = file_get_contents(self::QUOTES_FILE);
        if ($content === false) {
            return [
                'name' => null,
                'text' => "Не удалось прочитать файл с цитатами."
            ];
        }

        $data = json_decode($content, true);
        if (!is_array($data) || empty($data['quotes']) || !is_array($data['quotes'])) {
            return [
                'name' => null,
                'text' => "Файл с цитатами пуст или повреждён."
            ];
        }

        $quotes = $data['quotes'];
        $randomIndex = array_rand($quotes);
        $quote = $quotes[$randomIndex];

        $text = trim($quote['text'] ?? '');
        $book = $quote['book'] ?? '';
        $chapter = (int) ($quote['chapter'] ?? 0);
        $verse = (int) ($quote['verse'] ?? 0);
        $interpretation = trim($quote['interpretation'] ?? '');

        if (empty($text)) {
            return [
                'name' => null,
                'text' => "Текст цитаты не найден."
            ];
        }

        // Формируем ссылку в том же стиле, что и старая модель: "От Матфея 5:3"
        $reference = empty($book) ? '' : "{$book} {$chapter}:{$verse}";
        $textWithReference = $reference ? $text . "\n\n(" . $reference . ")" : $text;

        $result = [
            'name' => null,
            'text' => $textWithReference
        ];

        if (!empty($interpretation)) {
            $result['interpretation'] = $interpretation;
        }

        return $result;
    }
}
