<?php

use Core\Router;
use App\Controllers\Admin\AdminDashboardController;
use App\Controllers\Admin\AdminUsersController;
use App\Controllers\Admin\AdminVideosController;
use App\Controllers\Admin\AdminSchedulesController;
use App\Controllers\Admin\AdminLogsController;
use App\Middlewares\AdminMiddleware;

/** @var Router $router */

// Админ маршруты
$router->get('/admin', [AdminDashboardController::class, 'index'], [AdminMiddleware::class]);
$router->get('/admin/dashboard', [AdminDashboardController::class, 'index'], [AdminMiddleware::class]);

// Пользователи
$router->get('/admin/users', [AdminUsersController::class, 'index'], [AdminMiddleware::class]);
$router->get('/admin/users/{id}', [AdminUsersController::class, 'show'], [AdminMiddleware::class]);
$router->put('/admin/users/{id}', [AdminUsersController::class, 'update'], [AdminMiddleware::class]);
$router->delete('/admin/users/{id}', [AdminUsersController::class, 'delete'], [AdminMiddleware::class]);

// Видео
$router->get('/admin/videos', [AdminVideosController::class, 'index'], [AdminMiddleware::class]);
$router->get('/admin/videos/{id}', [AdminVideosController::class, 'show'], [AdminMiddleware::class]);

// Расписания
$router->get('/admin/schedules', [AdminSchedulesController::class, 'index'], [AdminMiddleware::class]);
$router->get('/admin/schedules/{id}', [AdminSchedulesController::class, 'show'], [AdminMiddleware::class]);

// Логи
$router->get('/admin/logs', [AdminLogsController::class, 'index'], [AdminMiddleware::class]);
$router->get('/admin/logs/{id}', [AdminLogsController::class, 'show'], [AdminMiddleware::class]);

// Настройки
$router->get('/admin/settings', [AdminDashboardController::class, 'settings'], [AdminMiddleware::class]);
$router->post('/admin/settings', [AdminDashboardController::class, 'updateSettings'], [AdminMiddleware::class]);
