<?php

declare(strict_types=1);

/**
 * Парсер страницы дня с days.pravoslavie.ru
 *
 * Использование:
 *   php parsing/parse_day_pravoslavie.php              — сегодня
 *   php parsing/parse_day_pravoslavie.php 2026-02-02   — указанная дата
 *   php parsing/parse_day_pravoslavie.php --json       — вывод в JSON
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Lib\PravoslavieDaysParser;

$date = 'now';
$json = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json' || $arg === '-j') {
        $json = true;
    } elseif (strpos($arg, '-') !== 0) {
        $date = $arg;
    }
}

$parser = new PravoslavieDaysParser();
$result = $parser->parseDay($date);

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo "Дата: " . $result['date'] . "\n";
    echo "Пост: " . ($result['fastInfo'] ?: '—') . "\n";
    echo "Неделя: " . ($result['weekName'] ?? '—') . "\n";
    echo "Глас: " . ($result['voice'] ?? '—') . "\n";
    echo "Праздники:\n";
    foreach ($result['feasts'] as $f) {
        echo "  • " . $f . "\n";
    }
    echo "\n--- Форматированный текст для бота ---\n";
    echo $parser->formatDayAsText($result) ?? "(пусто)\n";
}
