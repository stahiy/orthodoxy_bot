<?php

declare(strict_types=1);

/**
 * Скрипт для установки webhook в Telegram
 * 
 * Использование:
 * 1. Откройте в браузере: https://ваш-домен.ru/set_webhook.php
 * 2. Или запустите через командную строку: php set_webhook.php
 * 
 * После успешной установки webhook этот файл можно удалить или защитить
 */

require __DIR__ . '/vendor/autoload.php';

// Загружаем конфиг
$config = require __DIR__ . '/config.php';

// Определяем URL webhook
// Если указан в конфиге - используем его, иначе определяем автоматически
$webhookUrl = $config['bot']['webhook_url'] ?? null;

if ($webhookUrl === null) {
    // Автоматическое определение URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $webhookUrl = $protocol . '://' . $host . '/webhook.php';
}

$token = $config['bot']['token'];

if (empty($token) || $token === 'YOUR_BOT_TOKEN_HERE') {
    die('Ошибка: Токен бота не настроен в config.php');
}

// Установка webhook
$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";
$params = [
    'url' => $webhookUrl
];

// Отправка запроса
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Парсим ответ
$result = json_decode($response, true);

// Вывод результата
header('Content-Type: application/json; charset=utf-8');

if ($httpCode === 200 && isset($result['ok']) && $result['ok'] === true) {
    echo json_encode([
        'success' => true,
        'message' => 'Webhook успешно установлен',
        'webhook_url' => $webhookUrl,
        'telegram_response' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка установки webhook',
        'webhook_url' => $webhookUrl,
        'http_code' => $httpCode,
        'error' => $error ?: ($result['description'] ?? 'Неизвестная ошибка'),
        'telegram_response' => $result
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

