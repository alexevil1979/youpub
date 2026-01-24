<?php

/**
 * Скрипт для выполнения миграций базы данных
 * Использование: php database/migrate.php [номер_миграции]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;

$config = require __DIR__ . '/../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

echo "=== Миграция базы данных ===\n\n";

$targetMigration = $argv[1] ?? null;

if (!$targetMigration) {
    echo "Использование: php database/migrate.php <номер_миграции>\n";
    echo "Пример: php database/migrate.php 011\n\n";
    echo "Доступные миграции:\n";

    $migrationFiles = glob(__DIR__ . '/migrations/*.sql');
    foreach ($migrationFiles as $file) {
        $filename = basename($file, '.sql');
        echo "- $filename\n";
    }
    exit(1);
}

// Проверяем существование файла миграции
$migrationFile = __DIR__ . "/migrations/{$targetMigration}.sql";

if (!file_exists($migrationFile)) {
    echo "Ошибка: Миграция {$targetMigration} не найдена\n";
    echo "Файл: {$migrationFile}\n";
    exit(1);
}

echo "Выполняем миграцию: {$targetMigration}\n";
echo "Файл: {$migrationFile}\n\n";

// Читаем файл миграции
$sql = file_get_contents($migrationFile);

if (!$sql) {
    echo "Ошибка: Не удалось прочитать файл миграции\n";
    exit(1);
}

// Разделяем на отдельные команды (если есть несколько)
$commands = array_filter(array_map('trim', explode(';', $sql)));

$db = Database::getInstance();

try {
    foreach ($commands as $command) {
        if (!empty($command)) {
            echo "Выполняем: " . substr($command, 0, 50) . "...\n";
            $stmt = $db->prepare($command);
            $stmt->execute();
            echo "✓ Успешно\n";
        }
    }

    echo "\n🎉 Миграция {$targetMigration} выполнена успешно!\n";

} catch (Exception $e) {
    echo "\n❌ Ошибка выполнения миграции:\n";
    echo $e->getMessage() . "\n";

    // Показываем проблемную команду
    if (isset($command)) {
        echo "\nПроблемная команда:\n";
        echo $command . "\n";
    }

    exit(1);
}

echo "\nДля проверки результата выполните:\n";
echo "mysql -u " . ($config['DB_USER'] ?? 'youpub_user') . " -p" . ($config['DB_PASS'] ?? '') . " " . ($config['DB_NAME'] ?? 'youpub') . " -e 'DESCRIBE publication_templates;'\n";
