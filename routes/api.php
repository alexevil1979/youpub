<?php

use Core\Router;
use App\Controllers\Api\AuthApiController;
use App\Controllers\Api\VideoApiController;
use App\Controllers\Api\ScheduleApiController;
use App\Controllers\Api\StatsApiController;
use App\Middlewares\ApiAuthMiddleware;

/** @var Router $router */

// API маршруты
$router->post('/api/auth/login', [AuthApiController::class, 'login']);
$router->post('/api/auth/register', [AuthApiController::class, 'register']);

// Защищенные API маршруты
$router->get('/api/videos', [VideoApiController::class, 'list'], [ApiAuthMiddleware::class]);
$router->post('/api/videos/upload', [VideoApiController::class, 'upload'], [ApiAuthMiddleware::class]);
$router->get('/api/videos/{id}', [VideoApiController::class, 'show'], [ApiAuthMiddleware::class]);
$router->delete('/api/videos/{id}', [VideoApiController::class, 'delete'], [ApiAuthMiddleware::class]);

$router->get('/api/schedules', [ScheduleApiController::class, 'list'], [ApiAuthMiddleware::class]);
$router->post('/api/schedules', [ScheduleApiController::class, 'create'], [ApiAuthMiddleware::class]);
$router->get('/api/schedules/{id}', [ScheduleApiController::class, 'show'], [ApiAuthMiddleware::class]);
$router->delete('/api/schedules/{id}', [ScheduleApiController::class, 'delete'], [ApiAuthMiddleware::class]);

$router->get('/api/stats', [StatsApiController::class, 'index'], [ApiAuthMiddleware::class]);
$router->get('/api/stats/export', [StatsApiController::class, 'export'], [ApiAuthMiddleware::class]);
