<?php

/**
 * Worker для публикации видео по расписанию
 * Запускается через cron каждую минуту
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Repositories\ScheduleRepository;
use App\Services\YoutubeService;
use App\Services\TelegramService;
use App\Services\TiktokService;
use App\Services\InstagramService;
use App\Services\PinterestService;

// Загрузка конфигурации
$config = require __DIR__ . '/../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/publish_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    mkdir($config['WORKER_LOG_DIR'], 0755, true);
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

logMessage('Publish worker started', $logFile);

try {
    $scheduleRepo = new ScheduleRepository();
    $youtubeService = new YoutubeService();
    $telegramService = new TelegramService();
    $tiktokService = new TiktokService();
    $instagramService = new InstagramService();
    $pinterestService = new PinterestService();

    // Найти расписания, готовые к публикации
    $schedules = $scheduleRepo->findDueForPublishing();

    logMessage('Found ' . count($schedules) . ' schedules to process', $logFile);

    foreach ($schedules as $schedule) {
        try {
            logMessage("Processing schedule ID: {$schedule['id']}, Platform: {$schedule['platform']}", $logFile);

            $result = null;
            $platform = strtolower(trim($schedule['platform'] ?? ''));

            // Публикация в зависимости от платформы
            if ($platform === 'youtube') {
                $result = $youtubeService->publishVideo($schedule['id']);
            } elseif ($platform === 'telegram') {
                $result = $telegramService->publishVideo($schedule['id']);
            } elseif ($platform === 'tiktok') {
                $result = $tiktokService->publishVideo($schedule['id']);
            } elseif ($platform === 'instagram') {
                $result = $instagramService->publishReel($schedule['id']);
            } elseif ($platform === 'pinterest') {
                $result = $pinterestService->publishPin($schedule['id']);
            } elseif ($platform === 'both') {
                // Публикация на обе платформы (YouTube и Telegram)
                $youtubeResult = $youtubeService->publishVideo($schedule['id']);
                $telegramResult = $telegramService->publishVideo($schedule['id']);
                
                $result = [
                    'success' => $youtubeResult['success'] && $telegramResult['success'],
                    'message' => 'Published on both platforms',
                ];
            } else {
                $scheduleRepo->update($schedule['id'], [
                    'status' => 'failed',
                    'error_message' => 'Unsupported platform: ' . ($schedule['platform'] ?? 'empty')
                ]);
                logMessage("Schedule ID {$schedule['id']} failed: unsupported platform " . ($schedule['platform'] ?? 'empty'), $logFile);
                continue;
            }

            if ($result && $result['success']) {
                logMessage("Schedule ID {$schedule['id']} published successfully", $logFile);
            } else {
                logMessage("Schedule ID {$schedule['id']} failed: " . ($result['message'] ?? 'Unknown error'), $logFile);
            }

        } catch (\Exception $e) {
            logMessage("Error processing schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
        }
    }

    logMessage('Publish worker finished', $logFile);

} catch (\Exception $e) {
    logMessage('Fatal error: ' . $e->getMessage(), $logFile);
    exit(1);
}
