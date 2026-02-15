<?php

declare(strict_types=1);

/**
 * Парсер праздников на следующую неделю с days.pravoslavie.ru.
 * Результат сохраняется в storage/holidays_next_week.json
 *
 * Использование:
 *   php parsing/parse_week_pravoslavie.php
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    require __DIR__ . '/../src/Lib/PravoslavieDaysParser.php';
}

use App\Lib\PravoslavieDaysParser;

$parser = new PravoslavieDaysParser();
$results = [];

$start = new \DateTimeImmutable('today');
for ($i = 0; $i < 7; $i++) {
    $date = $start->modify("+{$i} days")->format('Y-m-d');
    $day = $parser->parseDay($date);
    $results[$day['date']] = $day;
}

$storageDir = __DIR__ . '/../storage';
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$outputFile = $storageDir . '/holidays_next_week.json';
$json = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "Ошибка записи в {$outputFile}\n");
    exit(1);
}

echo "Сохранено: {$outputFile}\n";
echo "Дней распарсено: " . count($results) . "\n";
