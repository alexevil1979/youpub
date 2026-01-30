<?php

/**
 * Worker для сбора статистики с платформ
 * Запускается через cron каждый час
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Repositories\PublicationRepository;
use App\Repositories\StatisticsRepository;
use App\Services\YoutubeService;

set_time_limit(300);
ini_set('memory_limit', '512M');

$configPath = __DIR__ . '/../config/env.php';
if (!file_exists($configPath)) {
    error_log("Stats worker: config not found at {$configPath}");
    exit(1);
}
$config = require $configPath;
if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
    error_log('Stats worker: invalid DB config');
    exit(1);
}

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/stats_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    if (!@mkdir($config['WORKER_LOG_DIR'], 0755, true) && !is_dir($config['WORKER_LOG_DIR'])) {
        error_log('Stats worker: failed to create log directory');
        exit(1);
    }
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// Блокировка, чтобы не запускать параллельно
$lockFile = sys_get_temp_dir() . '/youpub_stats_worker.lock';
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

logMessage('Stats worker started', $logFile);

try {
    $publicationRepo = new PublicationRepository();
    $statsRepo = new StatisticsRepository();

    // Найти все успешные публикации за последние 7 дней
    $db = Database::getInstance();
    $stmt = $db->prepare(
        "SELECT * FROM publications 
         WHERE status = 'success' 
         AND published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY published_at DESC"
    );
    $stmt->execute();
    $publications = $stmt->fetchAll();

    logMessage('Found ' . count($publications) . ' publications to update', $logFile);

    foreach ($publications as $publication) {
        if ($shutdown) {
            logMessage('Shutdown signal received, stopping loop', $logFile);
            break;
        }
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        try {
            $stats = null;
            $platform = $publication['platform'] ?? '';

            if ($platform === 'youtube' && !empty(trim($publication['platform_id'] ?? ''))) {
                $youtubeService = new YoutubeService();
                $stats = $youtubeService->fetchYouTubeStatsForPublication((int)$publication['id']);
            }

            if ($stats) {
                // Проверить, есть ли уже статистика за сегодня
                $existing = $statsRepo->findByPublicationAndDate($publication['id'], date('Y-m-d'));
                
                if ($existing) {
                    // Обновить существующую запись
                    $statsRepo->update($existing['id'], [
                        'views' => $stats['views'],
                        'likes' => $stats['likes'],
                        'comments' => $stats['comments'],
                        'shares' => $stats['shares'],
                    ]);
                } else {
                    // Создать новую запись
                    $statsRepo->create([
                        'publication_id' => $publication['id'],
                        'platform' => $publication['platform'],
                        'views' => $stats['views'],
                        'likes' => $stats['likes'],
                        'comments' => $stats['comments'],
                        'shares' => $stats['shares'],
                    ]);
                }

                logMessage("Updated stats for publication ID {$publication['id']} ({$platform})", $logFile);
            } else {
                if ($platform !== 'youtube' || empty(trim($publication['platform_id'] ?? ''))) {
                    logMessage("Skip publication ID {$publication['id']}: platform {$platform}, no platform_id or not YouTube", $logFile);
                } else {
                    logMessage("No stats received for YouTube publication ID {$publication['id']}", $logFile);
                }
            }

        } catch (\Exception $e) {
            logMessage("Error updating stats for publication ID {$publication['id']}: " . $e->getMessage(), $logFile);
        }
    }

    logMessage('Stats worker finished', $logFile);

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
