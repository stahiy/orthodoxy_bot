<?php

namespace App\View;

use SergiX44\Nutgram\Nutgram;

class BotView
{
    public function __construct(
        private Nutgram $bot
    ) {}

    public function sendWelcome(): void
    {
        $this->bot->sendMessage(
            text: "Мир вам! 🙏\nЯ буду уведомлять вас о православных праздниках.\n\n" .
            "Команды:\n" .
            "/pray — Случайная молитва\n" .
            "/quote — Цитата из Библии\n" .
            "/saint — Цитата святого (или /saint Имя)\n" .
            "/holiday — Какой сегодня праздник?\n" .
            "/subscribe — Подписаться на ежедневные уведомления\n" .
            "/unsubscribe — Отписаться от уведомлений"
        );
    }

    public function sendHoliday(?string $holiday): void
    {
        if ($holiday) {
            $this->bot->sendMessage(text: "📅 Сегодня: *" . $holiday . "*", parse_mode: 'Markdown');
        } else {
            $this->bot->sendMessage(text: "Сегодня нет великих двунадесятых праздников.");
        }
    }

    public function sendPrayer(string $title, string $text): void
    {
        $this->bot->sendMessage(text: "🙏 *{$title}*\n\n{$text}", parse_mode: 'Markdown');
    }

    /**
     * Отправка цитаты (из Библии или от святого)
     * 
     * @param array $quote Массив с ключами 'name' (автор, опционально) и 'text' (текст цитаты)
     */
    public function sendQuote(array $quote): void
    {
        $text = "📖 {$quote['text']}";
        
        // Если есть автор (для цитат святых), добавляем его в конец
        if (!empty($quote['name'])) {
            $text .= "\n\n— *{$quote['name']}*";
        }
        
        $this->bot->sendMessage(text: $text, parse_mode: 'Markdown');
    }

    public function sendMessage(string $text, int $chatId): void
    {
        $this->bot->sendMessage(
            text: $text,
            chat_id: $chatId,
            parse_mode: 'Markdown'
        );
    }
}

