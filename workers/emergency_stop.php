<?php

/**
 * Экстренная остановка публикации и очистка зависших расписаний
 * Использование: php workers/emergency_stop.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Repositories\ScheduleRepository;

// Загрузка конфигурации
$config = require __DIR__ . '/../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

$scheduleRepo = new ScheduleRepository();
$db = Database::getInstance();

echo "=== Экстренная остановка публикации ===\n\n";

// 1. Останавливаем все pending расписания с группами
echo "1. Остановка всех pending расписаний с группами...\n";
$stmt = $db->prepare("
    UPDATE schedules 
    SET status = 'paused',
        error_message = 'Emergency stop - manually paused'
    WHERE status = 'pending'
    AND content_group_id IS NOT NULL
");
$stmt->execute();
$paused = $stmt->rowCount();
echo "   Остановлено расписаний: {$paused}\n\n";

// 2. Очищаем все зависшие processing расписания
echo "2. Очистка зависших processing расписаний...\n";
$stmt = $db->prepare("
    UPDATE schedules 
    SET status = 'failed',
        error_message = 'Emergency stop - stuck processing cleared'
    WHERE status = 'processing'
    AND content_group_id IS NOT NULL
");
$stmt->execute();
$cleaned = $stmt->rowCount();
echo "   Очищено зависших расписаний: {$cleaned}\n\n";

// 3. Возвращаем файлы в статус 'new' из 'queued'
echo "3. Возврат файлов из 'queued' в 'new'...\n";
$stmt = $db->prepare("
    UPDATE content_group_files 
    SET status = 'new'
    WHERE status = 'queued'
");
$stmt->execute();
$resetFiles = $stmt->rowCount();
echo "   Возвращено файлов: {$resetFiles}\n\n";

// 4. Показываем статистику
echo "4. Текущая статистика:\n";
$stats = [
    'pending' => $db->query("SELECT COUNT(*) FROM schedules WHERE status = 'pending' AND content_group_id IS NOT NULL")->fetchColumn(),
    'processing' => $db->query("SELECT COUNT(*) FROM schedules WHERE status = 'processing' AND content_group_id IS NOT NULL")->fetchColumn(),
    'paused' => $db->query("SELECT COUNT(*) FROM schedules WHERE status = 'paused' AND content_group_id IS NOT NULL")->fetchColumn(),
    'queued_files' => $db->query("SELECT COUNT(*) FROM content_group_files WHERE status = 'queued'")->fetchColumn(),
];

echo "   Pending расписаний: {$stats['pending']}\n";
echo "   Processing расписаний: {$stats['processing']}\n";
echo "   Paused расписаний: {$stats['paused']}\n";
echo "   Queued файлов: {$stats['queued_files']}\n\n";

echo "=== Остановка завершена ===\n";
echo "Для возобновления публикации:\n";
echo "1. Проверьте расписания в админ-панели\n";
echo "2. Возобновите нужные расписания (кнопка 'Воспроизвести')\n";
echo "3. Или используйте SQL: UPDATE schedules SET status = 'pending' WHERE id = <ID>\n";
