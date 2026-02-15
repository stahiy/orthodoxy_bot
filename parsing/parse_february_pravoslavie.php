<?php

declare(strict_types=1);

/**
 * Дополняет storage/holidays_next_week.json данными на весь февраль (новый стиль).
 * Уже имеющиеся в файле даты не перезапрашиваются.
 *
 * Использование:
 *   php parsing/parse_february_pravoslavie.php
 */

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
} else {
    require __DIR__ . '/../src/Lib/PravoslavieDaysParser.php';
}

use App\Lib\PravoslavieDaysParser;

$storageDir = __DIR__ . '/../storage';
$outputFile = $storageDir . '/holidays_next_week.json';

// Загружаем существующие данные
$existing = [];
if (is_file($outputFile)) {
    $raw = file_get_contents($outputFile);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
}

// Год берём из существующих данных или текущий
$year = (int) date('Y');
if ($existing !== []) {
    $firstKey = array_key_first($existing);
    if (is_string($firstKey) && preg_match('/^(\d{4})-/', $firstKey, $m)) {
        $year = (int) $m[1];
    }
}

// Все даты февраля по новому стилю
$februaryDates = [];
$lastDay = (int) (new \DateTimeImmutable("{$year}-02-01"))->format('t');
for ($d = 1; $d <= $lastDay; $d++) {
    $februaryDates[] = sprintf('%04d-%02d-%02d', $year, 2, $d);
}

// Только те даты, которых ещё нет в файле
$toFetch = array_diff($februaryDates, array_keys($existing));
$toFetch = array_values($toFetch);
sort($toFetch);

if ($toFetch === []) {
    echo "Все дни февраля уже есть в файле.\n";
    exit(0);
}

$parser = new PravoslavieDaysParser();
$added = 0;

foreach ($toFetch as $date) {
    $day = $parser->parseDay($date);
    $existing[$day['date']] = $day;
    $added++;
    echo "Добавлено: {$date}\n";
}

ksort($existing);

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$json = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (file_put_contents($outputFile, $json) === false) {
    fwrite(STDERR, "Ошибка записи в {$outputFile}\n");
    exit(1);
}

echo "\nСохранено: {$outputFile}\n";
echo "Добавлено дней: {$added}, всего в файле: " . count($existing) . "\n";
