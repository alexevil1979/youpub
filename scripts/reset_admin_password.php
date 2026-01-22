<?php
/**
 * Скрипт для сброса пароля администратора
 * Использование: php scripts/reset_admin_password.php [новый_пароль]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;

// Загрузка конфигурации
$config = require __DIR__ . '/../config/env.php';

// Инициализация БД
Database::init($config);

$db = Database::getInstance();

// Получить новый пароль из аргументов или использовать admin123
$newPassword = $argv[1] ?? 'admin123';

// Генерация хеша пароля
$passwordHash = password_hash($newPassword, PASSWORD_BCRYPT);

// Обновление пароля администратора
$stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = 'admin@you.1tlt.ru' AND role = 'admin'");
$result = $stmt->execute([$passwordHash]);

if ($result) {
    $affected = $stmt->rowCount();
    if ($affected > 0) {
        echo "✓ Пароль администратора успешно обновлен!\n";
        echo "Email: admin@you.1tlt.ru\n";
        echo "Пароль: {$newPassword}\n";
    } else {
        echo "⚠ Администратор с email admin@you.1tlt.ru не найден.\n";
        echo "Создание нового администратора...\n";
        
        // Создание администратора
        $stmt = $db->prepare(
            "INSERT INTO users (email, password_hash, name, role, status) VALUES (?, ?, 'Administrator', 'admin', 'active')"
        );
        $stmt->execute(['admin@you.1tlt.ru', $passwordHash]);
        
        echo "✓ Администратор создан!\n";
        echo "Email: admin@you.1tlt.ru\n";
        echo "Пароль: {$newPassword}\n";
    }
} else {
    echo "✗ Ошибка при обновлении пароля.\n";
    exit(1);
}
