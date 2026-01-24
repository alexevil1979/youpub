<?php

/**
 * Скрипт для исправления статуса проблемного расписания
 */

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;

$config = require __DIR__ . '/config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);
$db = Database::getInstance();

echo "=== Исправление статуса расписания ===\n\n";

// Проверяем расписание ID 59
$scheduleId = 59;

$stmt = $db->prepare("SELECT * FROM schedules WHERE id = ?");
$stmt->execute([$scheduleId]);
$schedule = $stmt->fetch();

if (!$schedule) {
    echo "Расписание ID {$scheduleId} не найдено\n";
    exit(1);
}

echo "Текущее состояние расписания ID {$scheduleId}:\n";
echo "- Статус: {$schedule['status']}\n";
echo "- Время публикации: {$schedule['publish_at']}\n";
echo "- Группа контента: {$schedule['content_group_id']}\n\n";

// Проверяем наличие неопубликованных видео в группе
$stmt = $db->prepare("
    SELECT COUNT(*) as count
    FROM content_group_files cgf
    JOIN videos v ON v.id = cgf.video_id
    WHERE cgf.group_id = ?
    AND cgf.status IN ('new', 'queued', 'paused')
    AND v.status IN ('uploaded', 'ready')
");
$stmt->execute([$schedule['content_group_id']]);
$result = $stmt->fetch();
$unpublishedCount = $result['count'];

echo "Неопубликованных видео в группе: {$unpublishedCount}\n\n";

if ($unpublishedCount > 0 && $schedule['status'] === 'published') {
    echo "ПРОБЛЕМА: Расписание опубликовано, но есть неопубликованные видео!\n";
    echo "Исправляем статус на 'pending' и устанавливаем publish_at на текущий момент...\n";

    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("UPDATE schedules SET status = 'pending', publish_at = ? WHERE id = ?");
    $result = $stmt->execute([$now, $scheduleId]);

    if ($result) {
        echo "✅ Расписание ID {$scheduleId} исправлено!\n";
        echo "- Статус: pending\n";
        echo "- Время публикации: {$now}\n";
    } else {
        echo "❌ Ошибка при исправлении расписания\n";
    }
} elseif ($unpublishedCount === 0 && $schedule['status'] === 'published') {
    echo "✅ Расписание корректно завершено - все видео опубликованы\n";
} else {
    echo "ℹ️  Статус расписания корректный\n";
}

echo "\n=== Проверка завершена ===\n";