<?php


declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', "1");


// Защита от прямого GET-доступа (браузер, health-check)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Method Not Allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Webhook;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\Controller\BotController;

require __DIR__ . '/vendor/autoload.php';

// 1. Загружаем конфиг
$config = require __DIR__ . '/config.php';

// 2. Инициализация моделей
$calendar = new CalendarModel($config['fixed_holidays']);
$subscribers = new SubscriberModel($config['paths']);
$content = new ContentModel($config['prayers'], $config['quotes']);

// 3. Инициализация контроллера — ДО бота!
$controller = new BotController($calendar, $content, $subscribers);

// 4. Создаём бота
$bot = new Nutgram($config['bot']['token']);

// 5. Явно устанавливаем Webhook-режим — КЛЮЧЕВОЙ ШАГ для v4.39.2
$bot->setRunningMode(new Webhook());

// 6. Роутинг — $controller уже существует
$bot->onCommand('start', [$controller, 'start']);
$bot->onCommand('holiday', [$controller, 'holiday']);
$bot->onCommand('pray', [$controller, 'pray']);
$bot->onCommand('quote', [$controller, 'quote']);
$bot->onCommand('subscribe', [$controller, 'subscribe']);
$bot->onCommand('unsubscribe', [$controller, 'unsubscribe']);

// 7. Запуск обработки webhook-запроса
$bot->run();
/*
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\Controller\BotController;
use SergiX44\Nutgram\RunningMode\Webhook;


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






// ... инициализация моделей и контроллера ...



// Роутинг
$bot->onCommand('start', [$controller, 'start']);
// Роутинг (Маршрутизация)
$bot->onCommand('start', [$controller, 'start']);
$bot->onCommand('holiday', [$controller, 'holiday']);
$bot->onCommand('pray', [$controller, 'pray']);
$bot->onCommand('quote', [$controller, 'quote']);
$bot->onCommand('subscribe', [$controller, 'subscribe']);
$bot->onCommand('unsubscribe', [$controller, 'unsubscribe']);

file_put_contents('./nutgram_debug.log', json_encode([
    'sapi' => php_sapi_name(),
    'server_keys' => array_keys($_SERVER),
    'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
    'argv' => $argv ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), FILE_APPEND);

file_put_contents( './env_debug.log', json_encode([
    'NUTGRAM_RUNNING_MODE' => getenv('NUTGRAM_RUNNING_MODE') ?: 'NOT SET',
    '_ENV' => $_ENV['NUTGRAM_RUNNING_MODE'] ?? 'NOT IN _ENV',
    'get_cfg_var' => get_cfg_var('NUTGRAM_RUNNING_MODE') ?: 'NOT IN cfg',
], JSON_PRETTY_PRINT));

// Обработка webhook
$bot->run();
*/


