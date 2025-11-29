<?php

namespace App\Model;

class SubscriberModel
{
    private string $filePath;

    public function __construct(array $pathConfig)
    {
        // Создаем директорию, если нет
        if (!is_dir($pathConfig['storage'])) {
            mkdir($pathConfig['storage'], 0755, true);
        }

        $this->filePath = rtrim($pathConfig['storage'], '/') . '/' . $pathConfig['subscribers_file'];

        // Создаем файл, если нет
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([]));
        }
    }

    public function addSubscriber(int $chatId): bool
    {
        $ids = $this->getAll();
        if (!in_array($chatId, $ids)) {
            $ids[] = $chatId;
            $this->save($ids);
            return true;
        }
        return false;
    }

    public function removeSubscriber(int $chatId): bool
    {
        $ids = $this->getAll();
        if (($key = array_search($chatId, $ids)) !== false) {
            unset($ids[$key]);
            $this->save(array_values($ids));
            return true;
        }
        return false;
    }

    public function getAll(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }
        $content = file_get_contents($this->filePath);
        return json_decode($content, true) ?? [];
    }

    private function save(array $ids): void
    {
        file_put_contents($this->filePath, json_encode($ids));
    }
}

