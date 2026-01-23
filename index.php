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
ini_set('error_log', $errorLogFile);

// Включить буферизацию вывода
ob_start();

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;
use Core\Router;
use Core\Auth;

try {

    // Загрузка конфигурации
    $config = require __DIR__ . '/config/env.php';

    // Инициализация БД
    Database::init($config);

    // Инициализация сессий
    $auth = new Auth();
    $auth->startSession();

    // Создание роутера
    $router = new Router();

    // Загрузка маршрутов
    require __DIR__ . '/routes/web.php';
    require __DIR__ . '/routes/api.php';
    require __DIR__ . '/routes/admin.php';

    // Обработка запроса
    $method = $_SERVER['REQUEST_METHOD'];
    $uri = $_SERVER['REQUEST_URI'];

    $router->dispatch($method, $uri);
    
} catch (\Throwable $e) {
    // Очистить буфер при ошибке
    ob_clean();
    
    // Логирование ошибки
    error_log('Fatal error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    // Вывод ошибки (в production лучше показывать общее сообщение)
    http_response_code(500);
    $debug = false;
    if (file_exists(__DIR__ . '/config/env.php')) {
        $config = require __DIR__ . '/config/env.php';
        $debug = $config['APP_DEBUG'] ?? false;
    }
    
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
}

// Отправить буфер
ob_end_flush();
