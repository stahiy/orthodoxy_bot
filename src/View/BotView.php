<?php

namespace App\View;

use SergiX44\Nutgram\Nutgram;

class BotView
{
    public function __construct(
        private Nutgram $bot
    ) {}

    /**
     * Экранирование текста для Telegram HTML (parse_mode: HTML).
     * Экранируются только < > &
     */
    public static function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

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
            $safe = self::escapeHtml($holiday);
            $this->bot->sendMessage(
                text: "🔔 <b>Православный календарь</b>\n\n{$safe}",
                parse_mode: 'HTML'
            );
        } else {
            $this->bot->sendMessage(text: "Сегодня нет великих двунадесятых праздников.");
        }
    }

    public function sendPrayer(string $title, string $text): void
    {
        $safeTitle = self::escapeHtml($title);
        $safeText = self::escapeHtml($text);
        $this->bot->sendMessage(
            text: "🙏 <b>Молитва</b>\n\n<b>{$safeTitle}</b>\n\n{$safeText}",
            parse_mode: 'HTML'
        );
    }

    /**
     * Отправка цитаты (из Библии или от святого) с заголовком.
     *
     * @param array $quote Массив с ключами 'name' (автор, опционально), 'text' (текст цитаты),
     *                     'interpretation' (толкование, опционально — для цитат из Библии)
     * @param string $type 'bible' — заголовок «Ежедневная цитата из Библии», 'saint' — «Цитата святого»
     */
    public function sendQuote(array $quote, string $type = 'bible'): void
    {
        $header = $type === 'saint'
            ? "📿 <b>Цитата святого</b>"
            : "📖 <b>Ежедневная цитата из Библии</b>";
        $body = self::escapeHtml($quote['text']);
        $text = "{$header}\n\n{$body}";

        if (!empty($quote['interpretation'])) {
            $interpretation = self::escapeHtml($quote['interpretation']);
            $text .= "\n\n📜 <b>Толкование:</b>\n{$interpretation}";
        }

        if (!empty($quote['name'])) {
            $name = self::escapeHtml($quote['name']);
            $text .= "\n\n— <b>{$name}</b>";
        }

        $this->bot->sendMessage(text: $text, parse_mode: 'HTML');
    }

    /**
     * Отправка сообщения подписчику (рассылка).
     * По умолчанию HTML, чтобы заголовки и разметка не ломались из-за * _ в тексте.
     */
    public function sendMessage(string $text, int $chatId, string $parseMode = 'HTML'): void
    {
        $this->bot->sendMessage(
            text: $text,
            chat_id: $chatId,
            parse_mode: $parseMode
        );
    }
}

