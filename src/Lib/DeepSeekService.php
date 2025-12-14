<?php

declare(strict_types=1);

namespace App\Lib;

class DeepSeekService
{
    private string $apiKey;
    private string $apiUrl = 'https://api.deepseek.com/v1/chat/completions';
    private int $timeout = 30;
    private string $systemPrompt;

    public function __construct(string $apiKey, string $systemPrompt = '')
    {
        // Обрезаем пробелы и проверяем, что ключ не пустой
        $this->apiKey = trim($apiKey);
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('DeepSeek API ключ не может быть пустым');
        }
        $this->systemPrompt = $systemPrompt;
    }

    /**
     * Отправка сообщения в DeepSeek и получение ответа
     */
    public function ask(string $message): string
    {
        // Формируем массив сообщений
        $messages = [];
        
        // Добавляем системный промпт, если он задан
        if (!empty($this->systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $this->systemPrompt
            ];
        }
        
        // Добавляем пользовательское сообщение
        $messages[] = [
            'role' => 'user',
            'content' => $message
        ];

        // Подготовка данных для запроса
        $data = [
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];

        // Формируем заголовки
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ];

        // Инициализация curl
        $ch = curl_init($this->apiUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_VERBOSE => false // Отключаем verbose для production
        ]);

        // Выполнение запроса
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        // Обработка ошибок curl
        if ($error) {
            throw new \RuntimeException("Ошибка соединения с DeepSeek: {$error}");
        }

        // Обработка HTTP ошибок
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            
            // Логируем полную информацию об ошибке для отладки
            error_log("DeepSeek API Error [{$httpCode}]: " . ($response ?: 'Empty response'));
            if ($errorData) {
                error_log("DeepSeek Error Details: " . json_encode($errorData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            
            // Формируем понятное сообщение об ошибке
            if ($httpCode === 401) {
                $errorMessage = $errorData['error']['message'] ?? 'Неверный API ключ или отсутствует авторизация';
                throw new \RuntimeException("Ошибка авторизации DeepSeek (401): {$errorMessage}. Проверьте правильность API ключа в config.php");
            }
            
            if ($httpCode === 402) {
                $errorMessage = $errorData['error']['message'] ?? 'Недостаточно средств на балансе';
                throw new \RuntimeException("Ошибка DeepSeek (402): {$errorMessage}. Пополните баланс на https://platform.deepseek.com");
            }
            
            $errorMessage = $errorData['error']['message'] ?? "HTTP ошибка: {$httpCode}";
            throw new \RuntimeException("Ошибка API DeepSeek ({$httpCode}): {$errorMessage}");
        }

        // Парсинг ответа
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
            throw new \RuntimeException("Неожиданный формат ответа от DeepSeek");
        }

        return $responseData['choices'][0]['message']['content'];
    }
}

