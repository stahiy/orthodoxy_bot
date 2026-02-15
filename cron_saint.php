<?php

declare(strict_types=1);

/**
 * Скрипт для ежедневной рассылки цитат святых
 * 
 * Использование:
 *   php cron_saint.php
 * 
 * Настройка cron (пример - каждый день в 11:00):
 *   0 11 * * * /usr/bin/php /var/www/html/cron_saint.php
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

// Получаем случайную цитату святого
$quote = $content->getSaintQuote();

// Проверяем, что цитата получена
if (empty($quote['text'])) {
    echo "Ошибка: не удалось получить цитату святого.\n";
    exit(1);
}

$quoteText = $quote['text'];
$quoteAuthor = $quote['name'] ?? null;
$safeQuote = BotView::escapeHtml($quoteText);

// Формируем сообщение с заголовком в HTML (чтобы * _ в цитатах не ломали разметку)
$quoteMessage = "📿 <b>Цитата святого</b>\n\n{$safeQuote}";
if ($quoteAuthor) {
    $safeAuthor = BotView::escapeHtml($quoteAuthor);
    $quoteMessage .= "\n\n— <b>{$safeAuthor}</b>";
}

// Отправляем рассылку
$stats = $newsletter->sendToAll($quoteMessage, 'цитата святого');
echo "Статистика: Всего подписчиков: {$stats['total']}, Успешно отправлено: {$stats['success']}, Ошибок: {$stats['failed']}\n";

echo "Рассылка цитат святых завершена.\n";

