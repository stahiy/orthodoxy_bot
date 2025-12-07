<?php

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\View\BotView;

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Загружаем данные из отдельных файлов
$fixedHolidays = require $config['paths']['fixed_holidays_file'];
$prayers = require $config['paths']['prayers_file'];
$quotes = require $config['paths']['quotes_file'];

// Модели
$calendar = new CalendarModel($fixedHolidays);
$subscribers = new SubscriberModel($config['paths']);
$content = new ContentModel($prayers, $quotes);

// Инициализация бота
$bot = new Nutgram(
    token: $config['bot']['token'],
    config: new Configuration(
        clientTimeout: $config['bot']['client']['timeout'] ?? 60
    )
);
$view = new BotView($bot);

$ids = $subscribers->getAll();
echo "Найдено подписчиков: " . count($ids) . "\n";

if (empty($ids)) {
    echo "Нет подписчиков для рассылки.\n";
    exit(0);
}

// 1. Отправка информации о празднике (если есть)
$holiday = $calendar->getHoliday();
if ($holiday) {
    $holidayMessage = "🔔 Православный календарь\n\nСегодня праздник: *$holiday*";
    
    foreach ($ids as $chatId) {
        try {
            $view->sendMessage($holidayMessage, $chatId);
            usleep(50000); // Небольшая задержка между сообщениями
        } catch (\Throwable $e) {
            echo "Ошибка отправки праздника {$chatId}: " . $e->getMessage() . "\n";
        }
    }
    echo "Рассылка о празднике завершена.\n";
} else {
    echo "Праздников сегодня нет.\n";
}

// 2. Отправка ежедневной цитаты из Библии
$quote = $content->getRandomQuote();
$quoteMessage = "📖 *Ежедневная цитата из Библии*\n\n{$quote}";

foreach ($ids as $chatId) {
    try {
        $view->sendMessage($quoteMessage, $chatId);
        usleep(50000); // Небольшая задержка между сообщениями
    } catch (\Throwable $e) {
        echo "Ошибка отправки цитаты {$chatId}: " . $e->getMessage() . "\n";
    }
}
echo "Рассылка цитат завершена.\n";
echo "Общая рассылка завершена.\n";
