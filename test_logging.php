<?php

/**
 * Тестовый скрипт для проверки логирования в SmartQueueService
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;

$config = require __DIR__ . '/config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

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