<?php

declare(strict_types=1);

namespace App\Lib;

use App\Model\SubscriberModel;
use App\View\BotView;

/**
 * Сервис для рассылки сообщений подписчикам
 */
class NewsletterService
{
    private const DELAY_BETWEEN_MESSAGES = 50000; // микросекунды (50ms)

    public function __construct(
        private SubscriberModel $subscribers,
        private BotView $view
    ) {}

    /**
     * Отправка сообщения всем подписчикам
     * 
     * @param string $message Текст сообщения для отправки
     * @param string $type Тип сообщения (для логирования)
     * @return array Статистика отправки ['total' => int, 'success' => int, 'failed' => int]
     */
    public function sendToAll(string $message, string $type = 'message'): array
    {
        $ids = $this->subscribers->getAll();
        $total = count($ids);
        $success = 0;
        $failed = 0;

        if (empty($ids)) {
            echo "Нет подписчиков для рассылки.\n";
            return [
                'total' => 0,
                'success' => 0,
                'failed' => 0
            ];
        }

        echo "Найдено подписчиков: {$total}\n";
        echo "Начинаем рассылку типа: {$type}\n";

        foreach ($ids as $chatId) {
            try {
                $this->view->sendMessage($message, $chatId);
                $success++;
                usleep(self::DELAY_BETWEEN_MESSAGES);
            } catch (\Throwable $e) {
                $failed++;
                echo "Ошибка отправки {$type} для {$chatId}: " . $e->getMessage() . "\n";
            }
        }

        echo "Рассылка {$type} завершена. Успешно: {$success}, Ошибок: {$failed}\n";

        return [
            'total' => $total,
            'success' => $success,
            'failed' => $failed
        ];
    }

    /**
     * Получение списка всех подписчиков
     * 
     * @return array Массив chat_id подписчиков
     */
    public function getSubscribers(): array
    {
        return $this->subscribers->getAll();
    }
}

