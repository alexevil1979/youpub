<?php

/**
 * Точка входа приложения
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Настройка логирования ошибок
$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$errorLogFile = $logDir . '/error.log';

// Создаем файл, если его нет
if (!file_exists($errorLogFile)) {
    @touch($errorLogFile);
    @chmod($errorLogFile, 0664);
}

// Устанавливаем владельца файла (если возможно)
$phpUser = get_current_user();
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    $phpUser = $processUser['name'] ?? 'www-data';
}

// Пытаемся установить права через chown (только если запущено от root)
if (posix_geteuid() === 0 && file_exists($errorLogFile)) {
    @chown($errorLogFile, $phpUser);
    @chgrp($errorLogFile, $phpUser);
}

ini_set('error_log', $errorLogFile);

// Функция для логирования с прямой записью в файл
function writeLog($message) {
    global $errorLogFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    @file_put_contents($errorLogFile, $logMessage, FILE_APPEND | LOCK_EX);
    @error_log($message);
}

writeLog("=== Application starting ===");
writeLog("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
writeLog("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Включить буферизацию вывода
ob_start();

try {
    writeLog("Loading autoloader...");
    require_once __DIR__ . '/vendor/autoload.php';
    writeLog("Autoloader loaded");

    use Core\Database;
    use Core\Router;
    use Core\Auth;

    // Загрузка конфигурации
    writeLog("Loading config...");
    $config = require __DIR__ . '/config/env.php';
    writeLog("Config loaded");

    // Установка часового пояса
    $timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
    writeLog("Setting timezone to: {$timezone}");
    try {
        date_default_timezone_set($timezone);
        writeLog("Timezone set successfully");
    } catch (\Throwable $tzError) {
        writeLog("Failed to set timezone {$timezone}: " . $tzError->getMessage());
        // Пробуем альтернативный часовой пояс
        try {
            date_default_timezone_set('UTC');
            writeLog("Fallback to UTC timezone");
        } catch (\Throwable $utcError) {
            writeLog("Failed to set UTC timezone: " . $utcError->getMessage());
        }
    }

    // Инициализация БД
    writeLog("Initializing database...");
    Database::init($config);
    writeLog("Database initialized");

    // Инициализация сессий
    writeLog("Creating Auth instance...");
    $auth = new Auth();
    writeLog("Auth instance created");
    
    writeLog("Starting session...");
    $auth->startSession();
    writeLog("Session started");

    // Создание роутера
    writeLog("Creating router...");
    $router = new Router();
    writeLog("Router created");

    // Загрузка маршрутов
    writeLog("Loading routes...");
    require __DIR__ . '/routes/web.php';
    writeLog("Web routes loaded");
    
    require __DIR__ . '/routes/api.php';
    writeLog("API routes loaded");
    
    require __DIR__ . '/routes/admin.php';
    writeLog("Admin routes loaded");

    // Обработка запроса
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    writeLog("Dispatching request: {$method} {$uri}");
    
    $router->dispatch($method, $uri);
    writeLog("Request dispatched successfully");
    
} catch (\Throwable $e) {
    // Очистить буфер при ошибке
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Логирование ошибки с прямой записью в файл
    $errorMessage = 'Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    $stackTrace = 'Stack trace: ' . $e->getTraceAsString();
    
    writeLog($errorMessage);
    writeLog($stackTrace);
    
    // Также логируем через error_log
    error_log($errorMessage);
    error_log($stackTrace);
    
    // Вывод ошибки (в production лучше показывать общее сообщение)
    http_response_code(500);
    $debug = false;
    try {
        if (file_exists(__DIR__ . '/config/env.php')) {
            $config = require __DIR__ . '/config/env.php';
            $debug = $config['APP_DEBUG'] ?? false;
        }
    } catch (\Throwable $configError) {
        writeLog("Failed to load config for debug: " . $configError->getMessage());
        $debug = false;
    }
    
    // Проверяем, это AJAX запрос или обычный?
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
        // AJAX запрос - возвращаем JSON
        if ($debug) {
            echo json_encode([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            echo json_encode(['error' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        // Обычный запрос - показываем HTML
        $title = 'Ошибка';
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Ошибка</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 2rem; }
                .error { background: #fee; border: 1px solid #fcc; padding: 1rem; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>Ошибка сервера</h1>
                <?php if ($debug): ?>
                    <p><strong>Сообщение:</strong> <?= htmlspecialchars($e->getMessage()) ?></p>
                    <p><strong>Файл:</strong> <?= htmlspecialchars($e->getFile()) ?></p>
                    <p><strong>Строка:</strong> <?= $e->getLine() ?></p>
                <?php else: ?>
                    <p>Произошла внутренняя ошибка сервера. Пожалуйста, попробуйте позже.</p>
                <?php endif; ?>
                <p><a href="/dashboard">Вернуться на главную</a></p>
            </div>
        </body>
        </html>
        <?php
        echo ob_get_clean();
    }
    exit; // Завершаем выполнение после вывода ошибки
}

// Отправить буфер только если он существует и не был очищен
$bufferLevel = ob_get_level();
if ($bufferLevel > 0) {
    try {
        ob_end_flush();
    } catch (\Throwable $e) {
        // Игнорируем ошибки буфера, если он уже был очищен
        error_log("Buffer flush error (ignored): " . $e->getMessage());
    }
}
