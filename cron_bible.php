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
use App\Model\NewTestamentQuoteModel;
use App\Model\SubscriberModel;
use App\View\BotView;
use App\Lib\NewsletterService;

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Инициализация модели цитат из Библии
$bibleQuote = new NewTestamentQuoteModel();
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

// Получаем случайную цитату из Библии (из new_testament_quotes.json с толкованием)
$quote = $bibleQuote->getRandomQuote();

// Проверяем, что цитата получена
if (empty($quote['text'])) {
    echo "Ошибка: не удалось получить цитату из Библии.\n";
    exit(1);
}

$safeQuote = BotView::escapeHtml($quote['text']);
$quoteMessage = "📖 <b>Ежедневная цитата из Библии</b>\n\n{$safeQuote}";
if (!empty($quote['interpretation'])) {
    $quoteMessage .= BotView::formatInterpretationBlock($quote['interpretation']);
}

// Отправляем рассылку
$stats = $newsletter->sendToAll($quoteMessage, 'цитата из Библии');
echo "Статистика: Всего подписчиков: {$stats['total']}, Успешно отправлено: {$stats['success']}, Ошибок: {$stats['failed']}\n";

echo "Рассылка цитат из Библии завершена.\n";

