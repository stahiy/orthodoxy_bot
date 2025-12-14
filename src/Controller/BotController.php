<?php

namespace App\Controller;

use SergiX44\Nutgram\Nutgram;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\View\BotView;
use App\Lib\DeepSeekService;

class BotController
{
    private ?DeepSeekService $deepSeekService = null;

    public function __construct(
        private CalendarModel   $calendar,
        private ContentModel    $content,
        private SubscriberModel $subscribers,
        ?string                 $deepSeekApiKey = null,
        ?string                 $deepSeekSystemPrompt = null
    )
    {
        // Инициализируем DeepSeek сервис, если передан API ключ
        if ($deepSeekApiKey !== null && $deepSeekApiKey !== '') {
            $this->deepSeekService = new DeepSeekService(
                $deepSeekApiKey,
                $deepSeekSystemPrompt ?? ''
            );
        }
    }

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

    /**
     * Команда для отправки сообщения в DeepSeek
     * Использование: /ask Ваш вопрос
     */
    public function ask(Nutgram $bot): void
    {
        // Проверяем наличие API ключа
        if ($this->deepSeekService === null) {
            $bot->sendMessage("❌ Сервис DeepSeek не настроен. Обратитесь к администратору.");
            return;
        }

        // Отправляем действие "печатает"
        $bot->sendChatAction('typing');

        // Получаем текст сообщения
        $message = $bot->message();
        $text = $message?->text ?? '';

        // Убираем команду /ask из начала текста
        $question = null;
        if (preg_match('/^\/ask\s+(.+)$/is', $text, $matches)) {
            $question = trim($matches[1]);
        }

        // Если вопрос не указан, отправляем подсказку
        if (empty($question)) {
            $bot->sendMessage(
                "❓ Пожалуйста, укажите ваш вопрос после команды /ask\n\n" .
                "Пример: /ask Что такое православие?"
            );
            return;
        }

        try {
            // Отправляем запрос в DeepSeek
            $answer = $this->deepSeekService->ask($question);

            // Отправляем ответ пользователю
            $bot->sendMessage($answer);
        } catch (\Exception $e) {
            // Логируем ошибку
            error_log("DeepSeek error: " . $e->getMessage());

            // Формируем понятное сообщение для пользователя
            $errorMessage = $e->getMessage();

            // Если это ошибка баланса, показываем специальное сообщение
            if (strpos($errorMessage, '402') !== false || strpos($errorMessage, 'Insufficient Balance') !== false) {
                $bot->sendMessage(
                    "❌ Недостаточно средств на балансе DeepSeek API.\n\n" .
                    "Пожалуйста, пополните баланс на https://platform.deepseek.com\n\n" .
                    "Обратитесь к администратору для решения проблемы."
                );
            } else {
                $bot->sendMessage(
                    "❌ Произошла ошибка при обращении к DeepSeek:\n" .
                    $errorMessage . "\n\n" .
                    "Попробуйте позже или обратитесь к администратору."
                );
            }
        }
    }
}
