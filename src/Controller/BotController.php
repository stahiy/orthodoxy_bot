<?php

namespace App\Controller;

use SergiX44\Nutgram\Nutgram;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\View\BotView;

class BotController
{
    public function __construct(
        private CalendarModel $calendar,
        private ContentModel $content,
        private SubscriberModel $subscribers
    ) {}

    public function start(Nutgram $bot): void
    {
        $this->subscribers->addSubscriber($bot->chatId());

        $view = new BotView($bot);
        $view->sendWelcome();
    }

    public function holiday(Nutgram $bot): void
    {
        $holiday = $this->calendar->getHoliday();

        $view = new BotView($bot);
        $view->sendHoliday($holiday);
    }

    public function pray(Nutgram $bot): void
    {
        $prayer = $this->content->getRandomPrayer();

        $view = new BotView($bot);
        // $prayer is ['title' => ..., 'text' => ...]
        if ($prayer['title'] === null) {
             $bot->sendMessage($prayer['text']); // Fallback for empty
        } else {
             $view->sendPrayer($prayer['title'], $prayer['text']);
        }
    }

    public function quote(Nutgram $bot): void
    {
        // Отправляем действие "печатает", пока грузится цитата
        $bot->sendChatAction('typing');

        $quote = $this->content->getRandomQuote();
        $view = new BotView($bot);
        $view->sendQuote($quote);
    }

    /**
     * Команда для получения цитаты святого
     * Использование: /saint или /saint Иоанн Кронштадтский
     */
    public function saint(Nutgram $bot): void
    {
        // Отправляем действие "печатает"
        $bot->sendChatAction('typing');

        // Получаем аргумент команды (имя святого)
        $message = $bot->message();
        $text = $message?->text ?? '';

        // Убираем команду /saint из начала текста
        $saintName = null;
        if (preg_match('/^\/saint\s+(.+)$/i', $text, $matches)) {
            $saintName = trim($matches[1]);
        }

        // Получаем цитату святого
        $quote = $this->content->getSaintQuote($saintName);

        $view = new BotView($bot);

        // Если цитата не найдена, отправляем сообщение об ошибке
        if ($quote['name'] === null && strpos($quote['text'], 'не найдены') !== false) {
            $saintsList = $this->content->getSaintsList();
            $messageText = "❌ {$quote['text']}";

            if (!empty($saintsList)) {
                $messageText .= "\n\n📿 Доступные святые:\n" .
                    implode("\n", array_map(fn($s) => "• {$s}", $saintsList));
            }

            $bot->sendMessage($messageText);
        } else {
            $view->sendQuote($quote);
        }
    }

    public function subscribe(Nutgram $bot): void
    {
        $added = $this->subscribers->addSubscriber($bot->chatId());

        if ($added) {
            $bot->sendMessage("✅ Вы подписались на ежедневные уведомления о православных праздниках и цитатах из Библии.");
        } else {
            $bot->sendMessage("ℹ️ Вы уже подписаны на уведомления.");
        }
    }

    public function unsubscribe(Nutgram $bot): void
    {
        $removed = $this->subscribers->removeSubscriber($bot->chatId());

        if ($removed) {
            $bot->sendMessage("❌ Вы отписались от ежедневных уведомлений.");
        } else {
            $bot->sendMessage("ℹ️ Вы не были подписаны на уведомления.");
        }
    }
}
