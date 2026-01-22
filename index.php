<?php

/**
 * Точка входа приложения
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use Core\Database;
use Core\Router;
use Core\Auth;

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
