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
    try {
        // Пытаемся получить время, если часовой пояс не установлен, используем UTC
        try {
            $timestamp = date('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            $timestamp = gmdate('Y-m-d H:i:s') . ' UTC';
        }
        $logMessage = "[{$timestamp}] {$message}\n";
        @file_put_contents($errorLogFile, $logMessage, FILE_APPEND | LOCK_EX);
        @error_log($message);
    } catch (\Throwable $e) {
        // Игнорируем ошибки логирования, чтобы не ломать приложение
    }
}

writeLog("=== Application starting ===");
writeLog("Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
writeLog("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));

// Включить буферизацию вывода
ob_start();

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;
use Core\Router;
use Core\Auth;
use Core\ErrorHandler;
use Core\LoggerFactory;

$errorHandler = null;

try {
    $config = require __DIR__ . '/config/env.php';

    $logger = LoggerFactory::create($config);
    $errorHandler = new ErrorHandler(
        $logger,
        (bool)($config['APP_DEBUG'] ?? false),
        (string)($config['APP_ENV'] ?? 'production')
    );
    $errorHandler->register();

    $timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
    try {
        date_default_timezone_set($timezone);
    } catch (\Throwable $tzError) {
        $logger->warning('Failed to set timezone: ' . $tzError->getMessage());
        try {
            date_default_timezone_set('UTC');
        } catch (\Throwable $utcError) {
            // ignore
        }
    }

    Database::init($config);

    $auth = new Auth();
    $auth->startSession();

    $router = new Router();
    require __DIR__ . '/routes/web.php';
    require __DIR__ . '/routes/api.php';
    require __DIR__ . '/routes/admin.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];
    $router->dispatch($method, $uri);
} catch (\Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if ($errorHandler !== null) {
        $errorHandler->handleException($e);
    } else {
        writeLog('Bootstrap failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        error_log($e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Internal Server Error'], JSON_UNESCAPED_UNICODE);
        exit(1);
    }
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
