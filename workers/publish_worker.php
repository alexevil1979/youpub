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

set_time_limit(300);
ini_set('memory_limit', '512M');

$configPath = __DIR__ . '/../config/env.php';
if (!file_exists($configPath)) {
    error_log("Publish worker: config not found at {$configPath}");
    exit(1);
}
$config = require $configPath;
if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
    error_log('Publish worker: invalid DB config');
    exit(1);
}

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/publish_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    if (!@mkdir($config['WORKER_LOG_DIR'], 0755, true) && !is_dir($config['WORKER_LOG_DIR'])) {
        error_log('Publish worker: failed to create log directory');
        exit(1);
    }
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// Блокировка, чтобы не запускать параллельно
$lockFile = sys_get_temp_dir() . '/youpub_publish_worker.lock';
$lockHandle = fopen($lockFile, 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    exit(0);
}
ftruncate($lockHandle, 0);
fwrite($lockHandle, (string)getmypid());

$shutdown = false;
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use (&$shutdown) { $shutdown = true; });
    pcntl_signal(SIGINT, function() use (&$shutdown) { $shutdown = true; });
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
    $schedules = $scheduleRepo->findDueForPublishing(50, true);

    logMessage('Found ' . count($schedules) . ' schedules to process', $logFile);

    foreach ($schedules as $schedule) {
        if ($shutdown) {
            logMessage('Shutdown signal received, stopping loop', $logFile);
            break;
        }
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        try {
            logMessage("Processing schedule ID: {$schedule['id']}, Platform: {$schedule['platform']}", $logFile);

            $scheduleRepo->update((int)$schedule['id'], ['status' => 'processing']);
            $result = null;

            // Публикация в зависимости от платформы
            if ($schedule['platform'] === 'youtube') {
                $result = $youtubeService->publishVideo($schedule['id']);
            } elseif ($schedule['platform'] === 'telegram') {
                $result = $telegramService->publishVideo($schedule['id']);
            } elseif ($schedule['platform'] === 'tiktok') {
                $result = $tiktokService->publishVideo($schedule['id']);
            } elseif ($schedule['platform'] === 'instagram') {
                $result = $instagramService->publishReel($schedule['id']);
            } elseif ($schedule['platform'] === 'pinterest') {
                $result = $pinterestService->publishPin($schedule['id']);
            } elseif ($schedule['platform'] === 'both') {
                // Публикация на обе платформы (YouTube и Telegram)
                $youtubeResult = $youtubeService->publishVideo($schedule['id']);
                $telegramResult = $telegramService->publishVideo($schedule['id']);
                
                $result = [
                    'success' => $youtubeResult['success'] && $telegramResult['success'],
                    'message' => 'Published on both platforms',
                ];
            }

            if ($result && $result['success']) {
                logMessage("Schedule ID {$schedule['id']} published successfully", $logFile);
                $scheduleRepo->update((int)$schedule['id'], ['status' => 'published']);
            } else {
                logMessage("Schedule ID {$schedule['id']} failed: " . ($result['message'] ?? 'Unknown error'), $logFile);
                $scheduleRepo->update((int)$schedule['id'], [
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            logMessage("Error processing schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
            try {
                $scheduleRepo->update((int)$schedule['id'], [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            } catch (\Exception $updateError) {
                logMessage("Failed to update schedule status: " . $updateError->getMessage(), $logFile);
            }
        }
    }

    logMessage('Publish worker finished', $logFile);

} catch (\Exception $e) {
    logMessage('Fatal error: ' . $e->getMessage(), $logFile);
    exit(1);
} finally {
    Database::close();
    if (isset($lockHandle) && is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
}
