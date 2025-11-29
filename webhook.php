<?php

declare(strict_types=1);

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\Controller\BotController;

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Инициализация Моделей
$calendar = new CalendarModel($config['fixed_holidays']);
$subscribers = new SubscriberModel($config['paths']);
$content = new ContentModel($config['prayers'], $config['quotes']);

// Инициализация Контроллера
$controller = new BotController($calendar, $content, $subscribers);

// Инициализация Бота
$bot = new Nutgram(
    token: $config['bot']['token'],
    config: new Configuration(
        clientTimeout: $config['bot']['client']['timeout'] ?? 60
    )
);

// Роутинг (Маршрутизация)
$bot->onCommand('start', [$controller, 'start']);
$bot->onCommand('holiday', [$controller, 'holiday']);
$bot->onCommand('pray', [$controller, 'pray']);
$bot->onCommand('quote', [$controller, 'quote']);
$bot->onCommand('subscribe', [$controller, 'subscribe']);
$bot->onCommand('unsubscribe', [$controller, 'unsubscribe']);

// Обработка webhook
$bot->run();

