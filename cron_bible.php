<?php

declare(strict_types=1);

/**
 * Скрипт для ежедневной рассылки цитат из Библии
 * 
 * Использование:
 *   php cron_bible.php
 * 
 * Настройка cron (пример - каждый день в 10:00):
 *   0 10 * * * /usr/bin/php /var/www/html/cron_bible.php
 */

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\View\BotView;
use App\Lib\NewsletterService;

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Загружаем данные
$prayers = require $config['paths']['prayers_file'];
$quotes = require $config['paths']['quotes_file'];

// Инициализация моделей
$content = new ContentModel($prayers, $quotes);
$subscribers = new SubscriberModel($config['paths']);

// Инициализация бота
$bot = new Nutgram(
    token: $config['bot']['token'],
    config: new Configuration(
        clientTimeout: $config['bot']['client']['timeout'] ?? 60
    )
);
$view = new BotView($bot);

// Инициализация сервиса рассылки
$newsletter = new NewsletterService($subscribers, $view);

// Получаем случайную цитату из Библии
$quote = $content->getRandomQuote();

// Проверяем, что цитата получена
if (empty($quote['text'])) {
    echo "Ошибка: не удалось получить цитату из Библии.\n";
    exit(1);
}

$quoteText = $quote['text'];
$quoteMessage = "📖 *Ежедневная цитата из Библии*\n\n{$quoteText}";

// Отправляем рассылку
$stats = $newsletter->sendToAll($quoteMessage, 'цитата из Библии');
echo "Статистика: Всего подписчиков: {$stats['total']}, Успешно отправлено: {$stats['success']}, Ошибок: {$stats['failed']}\n";

echo "Рассылка цитат из Библии завершена.\n";

