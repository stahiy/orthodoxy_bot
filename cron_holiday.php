<?php

declare(strict_types=1);

/**
 * Скрипт для ежедневной рассылки информации о православных праздниках
 * 
 * Использование:
 *   php cron_holiday.php
 * 
 * Настройка cron (пример - каждый день в 9:00):
 *   0 9 * * * /usr/bin/php /var/www/html/cron_holiday.php
 */

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\CalendarModel;
use App\Model\SubscriberModel;
use App\View\BotView;
use App\Lib\NewsletterService;

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Загружаем данные о праздниках
$fixedHolidays = require $config['paths']['fixed_holidays_file'];

// Инициализация моделей
$calendar = new CalendarModel($fixedHolidays);
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

// Получаем информацию о празднике
$holiday = $calendar->getHoliday();

if ($holiday) {
    $holidayMessage = "🔔 Православный календарь\n\nСегодня праздник: *{$holiday}*";
    $stats = $newsletter->sendToAll($holidayMessage, 'праздник');
    echo "Статистика: Всего подписчиков: {$stats['total']}, Успешно отправлено: {$stats['success']}, Ошибок: {$stats['failed']}\n";
} else {
    echo "Праздников сегодня нет.\n";
}

echo "Рассылка праздников завершена.\n";

