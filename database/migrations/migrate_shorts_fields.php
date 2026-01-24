<?php

/**
 * Миграция: Добавление полей для оптимизации YouTube Shorts
 * Версия: 11.0
 * Дата: 2026-01-24
 * Добавляет новые поля для создания уникальных шаблонов Shorts
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Core\Database;

$config = require __DIR__ . '/../../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);
$db = Database::getInstance();

echo "=== Миграция Shorts полей ===\n\n";

$columns = [
    'hook_type' => "enum('emotional','intriguing','atmospheric','visual','educational') DEFAULT NULL COMMENT 'Тип контента (триггер)'",
    'focus_points' => "text COMMENT 'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)'",
    'title_variants' => "text COMMENT 'JSON: массив вариантов названий для A/B тестирования'",
    'description_variants' => "text COMMENT 'JSON: объект с вариантами описаний по типам триггеров'",
    'emoji_groups' => "text COMMENT 'JSON: объект с группами emoji по типам контента'",
    'base_tags' => "text COMMENT 'Основные теги (всегда присутствуют)'",
    'tag_variants' => "text COMMENT 'JSON: массив вариантов ротации тегов'",
    'questions' => "text COMMENT 'JSON: массив вопросов для вовлечённости'",
    'pinned_comments' => "text COMMENT 'JSON: массив вариантов закрепленных комментариев'",
    'cta_types' => "text COMMENT 'JSON: массив типов CTA (call to action)'",
    'enable_ab_testing' => "tinyint(1) DEFAULT 1 COMMENT 'Включить A/B тестирование названий'"
];

$added = 0;
$skipped = 0;

foreach ($columns as $columnName => $columnDef) {
    try {
        // Проверяем существование колонки
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$columnName]);
        $result = $stmt->fetch();

        if ($result['count'] > 0) {
            echo "✓ Колонка '$columnName' уже существует\n";
            $skipped++;
            continue;
        }

        // Определяем позицию AFTER
        $afterColumn = 'variants'; // для первой колонки
        if ($columnName !== 'hook_type') {
            // Для остальных колонок определяем предыдущую колонку
            $prevColumns = array_keys($columns);
            $currentIndex = array_search($columnName, $prevColumns);
            if ($currentIndex > 0) {
                $afterColumn = $prevColumns[$currentIndex - 1];
            }
        }

        // Добавляем колонку
        $sql = "ALTER TABLE `publication_templates` ADD COLUMN `$columnName` $columnDef AFTER `$afterColumn`";
        echo "Добавляем колонку '$columnName'...\n";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        echo "✓ Колонка '$columnName' успешно добавлена\n";
        $added++;

    } catch (Exception $e) {
        echo "❌ Ошибка с колонкой '$columnName': " . $e->getMessage() . "\n";
    }
}

echo "\n=== Результат миграции ===\n";
echo "Добавлено колонок: $added\n";
echo "Пропущено (уже существуют): $skipped\n";

if ($added > 0) {
    echo "\n🎉 Миграция завершена успешно!\n";
} else {
    echo "\nℹ️  Все колонки уже существуют, миграция не требуется.\n";
}

echo "\nДля проверки результата выполните:\n";
echo "mysql -u " . ($config['DB_USER'] ?? 'youpub_user') . " -p" . ($config['DB_PASS'] ?? '') . " " . ($config['DB_NAME'] ?? 'youpub') . " -e 'DESCRIBE publication_templates;'\n";