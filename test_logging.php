<?php

/**
 * Тестовый скрипт для проверки логирования в SmartQueueService
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;

$configPath = __DIR__ . '/config/env.php';
if (!file_exists($configPath)) {
    echo "SKIP: config/env.php not found\n";
    exit(0);
}
$config = require $configPath;

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
try {
    Database::init($config);
    Database::getInstance();
} catch (\Throwable $e) {
    echo "SKIP: Database connection failed: " . $e->getMessage() . "\n";
    exit(0);
}

echo "=== Тест логирования SmartQueueService ===\n\n";

// Имитируем вызов processGroupSchedule с тестовыми данными
$schedule = [
    'id' => 59,
    'content_group_id' => 4,
    'group_name' => 'Тестовая группа',
    'platform' => 'youtube',
    'status' => 'pending',
    'publish_at' => '2026-01-24 14:30:00'
];

$smartQueueService = new \App\Modules\ContentGroups\Services\SmartQueueService();

echo "Вызываем processGroupSchedule с тестовыми данными...\n\n";

$result = $smartQueueService->processGroupSchedule($schedule);

echo "\nРезультат: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";

echo "\nПроверьте логи воркера, должны появиться новые сообщения с ===== маркерами!\n";