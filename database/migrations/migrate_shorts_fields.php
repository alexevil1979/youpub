<?php

/**
 * Миграция: Добавление полей для оптимизации YouTube Shorts
 * Версия: 11.0
 * Дата: 2026-01-24
 * Добавляет новые поля для создания уникальных шаблонов Shorts
 * Совместимо с MySQL 5.7 / Percona Server 5.7
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

echo "=== Миграция Shorts полей (MySQL 5.7 Compatible) ===\n\n";

$columns = [
    'hook_type' => [
        'definition' => "enum('emotional','intriguing','atmospheric','visual','educational') DEFAULT NULL COMMENT 'Тип контента (триггер)'",
        'after' => 'variants'
    ],
    'focus_points' => [
        'definition' => "text COMMENT 'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)'",
        'after' => 'hook_type'
    ],
    'title_variants' => [
        'definition' => "text COMMENT 'JSON: массив вариантов названий для A/B тестирования'",
        'after' => 'focus_points'
    ],
    'description_variants' => [
        'definition' => "text COMMENT 'JSON: объект с вариантами описаний по типам триггеров'",
        'after' => 'title_variants'
    ],
    'emoji_groups' => [
        'definition' => "text COMMENT 'JSON: объект с группами emoji по типам контента'",
        'after' => 'description_variants'
    ],
    'base_tags' => [
        'definition' => "text COMMENT 'Основные теги (всегда присутствуют)'",
        'after' => 'emoji_groups'
    ],
    'tag_variants' => [
        'definition' => "text COMMENT 'JSON: массив вариантов ротации тегов'",
        'after' => 'base_tags'
    ],
    'questions' => [
        'definition' => "text COMMENT 'JSON: массив вопросов для вовлечённости'",
        'after' => 'tag_variants'
    ],
    'pinned_comments' => [
        'definition' => "text COMMENT 'JSON: массив вариантов закрепленных комментариев'",
        'after' => 'questions'
    ],
    'cta_types' => [
        'definition' => "text COMMENT 'JSON: массив типов CTA (call to action)'",
        'after' => 'pinned_comments'
    ],
    'enable_ab_testing' => [
        'definition' => "tinyint(1) DEFAULT 1 COMMENT 'Включить A/B тестирование названий'",
        'after' => 'cta_types'
    ]
];

$added = 0;
$skipped = 0;
$errors = 0;

foreach ($columns as $columnName => $columnInfo) {
    try {
        // Проверяем существование колонки через SHOW COLUMNS (более совместимый способ для MySQL 5.7)
        $stmt = $db->prepare("SHOW COLUMNS FROM `publication_templates` LIKE ?");
        $stmt->execute([$columnName]);
        $result = $stmt->fetch();

        if ($result) {
            echo "✓ Колонка '$columnName' уже существует\n";
            $skipped++;
            continue;
        }

        // Добавляем колонку
        $sql = "ALTER TABLE `publication_templates` ADD COLUMN `$columnName` {$columnInfo['definition']} AFTER `{$columnInfo['after']}`";
        echo "Добавляем колонку '$columnName'...\n";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        echo "✓ Колонка '$columnName' успешно добавлена\n";
        $added++;

    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
        // Игнорируем ошибку дублирования колонки
        if (strpos($errorMessage, 'Duplicate column name') !== false) {
            echo "✓ Колонка '$columnName' уже существует (проигнорирована ошибка дублирования)\n";
            $skipped++;
        } else {
            echo "❌ Ошибка с колонкой '$columnName': " . $errorMessage . "\n";
            $errors++;
        }
    }
}

echo "\n=== Результат миграции ===\n";
echo "Добавлено колонок: $added\n";
echo "Пропущено (уже существуют): $skipped\n";
echo "Ошибок: $errors\n";

if ($errors === 0) {
    if ($added > 0) {
        echo "\n🎉 Миграция завершена успешно!\n";
    } else {
        echo "\nℹ️  Все колонки уже существуют, миграция не требуется.\n";
    }
} else {
    echo "\n⚠️  Миграция завершена с ошибками. Проверьте структуру таблицы.\n";
}

echo "\nДля проверки результата выполните:\n";
echo "mysql -u " . ($config['DB_USER'] ?? 'youpub_user') . " -p" . ($config['DB_PASS'] ?? '') . " " . ($config['DB_NAME'] ?? 'youpub') . " -e 'DESCRIBE publication_templates;'\n";