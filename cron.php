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
use App\Model\NewTestamentQuoteModel;
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
$prayers = require $config['paths']['prayers_file'];
$quotes = require $config['paths']['quotes_file'];

// Модели
$holidaysJsonPath = $config['paths']['holidays_json_file'] ?? __DIR__ . '/storage/holidays_next_week.json';
$calendar = new CalendarModel($holidaysJsonPath);
$subscribers = new SubscriberModel($config['paths']);
$content = new ContentModel($prayers, $quotes);
$bibleQuote = new NewTestamentQuoteModel();

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
        $safeHoliday = BotView::escapeHtml($holiday);
        $holidayMessage = "🔔 <b>Православный календарь</b>\n\n{$safeHoliday}";

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

    // 2. Отправка цитаты из Библии (из new_testament_quotes.json с толкованием)
    $quote = $bibleQuote->getRandomQuote();
    $safeQuote = BotView::escapeHtml($quote['text']);
    $quoteMessage = "📖 <b>Ежедневная цитата из Библии</b>\n\n{$safeQuote}";
    if (!empty($quote['interpretation'])) {
        $safeInterpretation = BotView::escapeHtml($quote['interpretation']);
        $quoteMessage .= "\n\n📜 <b>Толкование:</b>\n{$safeInterpretation}";
    }

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
    $quote = $bibleQuote->getRandomQuote();
    $safeQuote = BotView::escapeHtml($quote['text']);
    $quoteMessage = "📖 <b>Цитата из Библии</b>\n\n{$safeQuote}";
    if (!empty($quote['interpretation'])) {
        $safeInterpretation = BotView::escapeHtml($quote['interpretation']);
        $quoteMessage .= "\n\n📜 <b>Толкование:</b>\n{$safeInterpretation}";
    }

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
    $safeQuote = BotView::escapeHtml($quoteText);

    $quoteMessage = "📿 <b>Цитата святого</b>\n\n{$safeQuote}";
    if ($quoteAuthor) {
        $safeAuthor = BotView::escapeHtml($quoteAuthor);
        $quoteMessage .= "\n\n— <b>{$safeAuthor}</b>";
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
