<?php

/**
 * Скрипт для ежедневной рассылки подписчикам
 * 
 * Использование:
 *   php cron.php                    - Праздник + цитата из Библии (по умолчанию)
 *   php cron.php --bible            - Только цитата из Библии
 *   php cron.php -b                 - Только цитата из Библии (короткий флаг)
 *   php cron.php --saint            - Только цитата святого
 *   php cron.php -s                 - Только цитата святого (короткий флаг)
 *   php cron.php --bible --saint    - Цитата из Библии + цитата святого
 *   php cron.php -b -s               - Цитата из Библии + цитата святого
 */

use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Configuration;
use App\Model\CalendarModel;
use App\Model\ContentModel;
use App\Model\SubscriberModel;
use App\View\BotView;

require __DIR__ . '/vendor/autoload.php';

// Обработка аргументов командной строки
$options = getopt('bs', ['bible', 'saint'], $optind);
$sendBible = isset($options['b']) || isset($options['bible']);
$sendSaint = isset($options['s']) || isset($options['saint']);

// Если указаны оба флага - отправляем оба типа цитат
// Если не указан ни один флаг - отправляем праздник + цитату из Библии (по умолчанию)
$defaultMode = !$sendBible && !$sendSaint;

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

// Режим по умолчанию: праздник + цитата из Библии
if ($defaultMode) {
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

    // 2. Отправка цитаты из Библии
    $quote = $content->getRandomQuote();
    $quoteText = $quote['text'];
    $quoteMessage = "📖 *Ежедневная цитата из Библии*\n\n{$quoteText}";

    foreach ($ids as $chatId) {
        try {
            $view->sendMessage($quoteMessage, $chatId);
            usleep(50000); // Небольшая задержка между сообщениями
        } catch (\Throwable $e) {
            echo "Ошибка отправки цитаты из Библии {$chatId}: " . $e->getMessage() . "\n";
        }
    }
    echo "Рассылка цитат из Библии завершена.\n";
}

// Отправка цитаты из Библии (если указан флаг --bible или -b)
if ($sendBible) {
    $quote = $content->getRandomQuote();
    $quoteText = $quote['text'];
    $quoteMessage = "📖 *Цитата из Библии*\n\n{$quoteText}";

    foreach ($ids as $chatId) {
        try {
            $view->sendMessage($quoteMessage, $chatId);
            usleep(50000); // Небольшая задержка между сообщениями
        } catch (\Throwable $e) {
            echo "Ошибка отправки цитаты из Библии {$chatId}: " . $e->getMessage() . "\n";
        }
    }
    echo "Рассылка цитат из Библии завершена.\n";
}

// Отправка цитаты святого (если указан флаг --saint или -s)
if ($sendSaint) {
    $quote = $content->getSaintQuote();
    $quoteText = $quote['text'];
    $quoteAuthor = $quote['name'] ?? null;
    
    $quoteMessage = "📿 *Цитата святого*\n\n{$quoteText}";
    if ($quoteAuthor) {
        $quoteMessage .= "\n\n— *{$quoteAuthor}*";
    }

    foreach ($ids as $chatId) {
        try {
            $view->sendMessage($quoteMessage, $chatId);
            usleep(50000); // Небольшая задержка между сообщениями
        } catch (\Throwable $e) {
            echo "Ошибка отправки цитаты святого {$chatId}: " . $e->getMessage() . "\n";
        }
    }
    echo "Рассылка цитат святых завершена.\n";
}

echo "Общая рассылка завершена.\n";
