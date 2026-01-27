<?php

use Core\Router;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\VideoApiController;
use App\Controllers\Api\ScheduleApiController;
use App\Controllers\Api\StatsApiController;
use App\Middlewares\ApiAuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Controllers\Api\DebugApiController;
use App\Middlewares\RateLimitingMiddleware;

/** @var Router $router */

// Загружаем конфигурацию
$config = require __DIR__ . '/../config/env.php';

// Rate limiting для авторизации (более строгий)
$authRateLimit = new RateLimitingMiddleware(
    $config['RATE_LIMIT_AUTH_REQUESTS'] ?? 10,
    $config['RATE_LIMIT_AUTH_WINDOW'] ?? 600,
    'api_auth'
);

// Rate limiting для API (общий)
$apiRateLimit = new RateLimitingMiddleware(
    $config['RATE_LIMIT_API_REQUESTS'] ?? 200,
    $config['RATE_LIMIT_API_WINDOW'] ?? 3600,
    'api'
);

// Rate limiting для загрузки файлов
$uploadRateLimit = new RateLimitingMiddleware(
    $config['RATE_LIMIT_UPLOAD_REQUESTS'] ?? 20,
    $config['RATE_LIMIT_UPLOAD_WINDOW'] ?? 3600,
    'api_upload'
);

// API маршруты авторизации (с более строгим rate limiting)
$router->post('/api/auth/login', [AuthApiController::class, 'login'], [$authRateLimit]);
$router->post('/api/auth/register', [AuthApiController::class, 'register'], [$authRateLimit]);

// Защищенные API маршруты (с общим rate limiting и авторизацией)
$router->get('/api/videos', [VideoApiController::class, 'list'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->post('/api/videos/upload', [VideoApiController::class, 'upload'], [ApiAuthMiddleware::class, $uploadRateLimit]);
$router->get('/api/videos/{id}', [VideoApiController::class, 'show'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->get('/api/videos/{id}/publications', [VideoApiController::class, 'publications'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->delete('/api/videos/{id}', [VideoApiController::class, 'delete'], [ApiAuthMiddleware::class, $apiRateLimit]);

$router->get('/api/schedules', [ScheduleApiController::class, 'list'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->post('/api/schedules', [ScheduleApiController::class, 'create'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->get('/api/schedules/{id}', [ScheduleApiController::class, 'show'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->delete('/api/schedules/{id}', [ScheduleApiController::class, 'delete'], [ApiAuthMiddleware::class, $apiRateLimit]);

$router->get('/api/stats', [StatsApiController::class, 'index'], [ApiAuthMiddleware::class, $apiRateLimit]);
$router->get('/api/stats/export', [StatsApiController::class, 'export'], [ApiAuthMiddleware::class, $apiRateLimit]);

// Глобальный поиск
$router->get('/api/search', [\App\Controllers\SearchController::class, 'search'], [ApiAuthMiddleware::class, $apiRateLimit]);

// Debug-эндпоинты
// По умолчанию отключены и доступны только администраторам при включённом флаге ENABLE_DEBUG_API
if (!empty($config['ENABLE_DEBUG_API'])) {
    $router->get(
        '/api/debug/db-snapshot',
        [DebugApiController::class, 'dbSnapshot'],
        [ApiAuthMiddleware::class, AdminMiddleware::class, $apiRateLimit]
    );
    $router->get(
        '/api/debug/worker-log',
        [DebugApiController::class, 'workerLog'],
        [ApiAuthMiddleware::class, AdminMiddleware::class, $apiRateLimit]
    );
}
