<?php

use Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\VideoController;
use App\Controllers\ScheduleController;
use App\Modules\ContentGroups\Controllers\GroupController;
use App\Modules\ContentGroups\Controllers\TemplateController;
use App\Modules\ContentGroups\Controllers\SmartScheduleController;
use App\Middlewares\AuthMiddleware;

/** @var Router $router */

// Публичные маршруты
$router->get('/', function() {
    if (isset($_SESSION['user_id'])) {
        header('Location: /dashboard', true, 302);
    } else {
        header('Location: /login', true, 302);
    }
    exit;
});

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

// Защищенные маршруты
$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class]);
$router->get('/profile', [DashboardController::class, 'profile'], [AuthMiddleware::class]);

// Видео
$router->get('/videos', [VideoController::class, 'index'], [AuthMiddleware::class]);
$router->get('/videos/upload', [VideoController::class, 'showUpload'], [AuthMiddleware::class]);
$router->post('/videos/upload', [VideoController::class, 'upload'], [AuthMiddleware::class]);
$router->get('/videos/{id}', [VideoController::class, 'show'], [AuthMiddleware::class]);
$router->post('/videos/{id}/publish', [VideoController::class, 'publishNow'], [AuthMiddleware::class]);
$router->delete('/videos/{id}', [VideoController::class, 'delete'], [AuthMiddleware::class]);

// Расписания
$router->get('/schedules', [ScheduleController::class, 'index'], [AuthMiddleware::class]);
$router->get('/schedules/create', [ScheduleController::class, 'showCreate'], [AuthMiddleware::class]);
$router->post('/schedules/create', [ScheduleController::class, 'create'], [AuthMiddleware::class]);
$router->get('/schedules/{id}', [ScheduleController::class, 'show'], [AuthMiddleware::class]);
$router->delete('/schedules/{id}', [ScheduleController::class, 'delete'], [AuthMiddleware::class]);

// Интеграции
$router->get('/integrations', [DashboardController::class, 'integrations'], [AuthMiddleware::class]);
$router->get('/integrations/youtube', [DashboardController::class, 'youtubeConnect'], [AuthMiddleware::class]);
$router->get('/integrations/youtube/callback', [DashboardController::class, 'youtubeCallback'], [AuthMiddleware::class]);
$router->get('/integrations/youtube/disconnect', [DashboardController::class, 'youtubeDisconnect'], [AuthMiddleware::class]);
$router->post('/integrations/telegram', [DashboardController::class, 'telegramConnect'], [AuthMiddleware::class]);
$router->get('/integrations/tiktok', [DashboardController::class, 'tiktokConnect'], [AuthMiddleware::class]);
$router->get('/integrations/tiktok/callback', [DashboardController::class, 'tiktokCallback'], [AuthMiddleware::class]);
$router->get('/integrations/instagram', [DashboardController::class, 'instagramConnect'], [AuthMiddleware::class]);
$router->get('/integrations/instagram/callback', [DashboardController::class, 'instagramCallback'], [AuthMiddleware::class]);
$router->get('/integrations/pinterest', [DashboardController::class, 'pinterestConnect'], [AuthMiddleware::class]);
$router->get('/integrations/pinterest/callback', [DashboardController::class, 'pinterestCallback'], [AuthMiddleware::class]);

// Статистика
$router->get('/statistics', [DashboardController::class, 'statistics'], [AuthMiddleware::class]);
$router->get('/publications', [DashboardController::class, 'publications'], [AuthMiddleware::class]);

// Группы контента
$router->get('/content-groups', [GroupController::class, 'index'], [AuthMiddleware::class]);
$router->get('/content-groups/create', [GroupController::class, 'showCreate'], [AuthMiddleware::class]);
$router->post('/content-groups/create', [GroupController::class, 'create'], [AuthMiddleware::class]);
$router->get('/content-groups/{id}', [GroupController::class, 'show'], [AuthMiddleware::class]);
$router->post('/content-groups/{id}/add-video', [GroupController::class, 'addVideo'], [AuthMiddleware::class]);
$router->post('/content-groups/{id}/add-videos', [GroupController::class, 'addVideos'], [AuthMiddleware::class]);
$router->delete('/content-groups/{groupId}/videos/{videoId}', [GroupController::class, 'removeVideo'], [AuthMiddleware::class]);
$router->post('/content-groups/{id}/shuffle', [GroupController::class, 'shuffle'], [AuthMiddleware::class]);
$router->delete('/content-groups/{id}', [GroupController::class, 'delete'], [AuthMiddleware::class]);

// Шаблоны публикаций
$router->get('/content-groups/templates', [TemplateController::class, 'index'], [AuthMiddleware::class]);
$router->get('/content-groups/templates/create', [TemplateController::class, 'showCreate'], [AuthMiddleware::class]);
$router->post('/content-groups/templates/create', [TemplateController::class, 'create'], [AuthMiddleware::class]);
$router->post('/content-groups/templates/{id}/preview', [TemplateController::class, 'preview'], [AuthMiddleware::class]);
$router->delete('/content-groups/templates/{id}', [TemplateController::class, 'delete'], [AuthMiddleware::class]);

// Умные расписания
$router->get('/content-groups/schedules/create', [SmartScheduleController::class, 'showCreate'], [AuthMiddleware::class]);
$router->post('/content-groups/schedules/create', [SmartScheduleController::class, 'create'], [AuthMiddleware::class]);
