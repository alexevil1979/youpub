<?php

/**
 * Worker для сбора статистики с платформ
 * Запускается через cron каждый час
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Repositories\PublicationRepository;
use App\Repositories\StatisticsRepository;

// Загрузка конфигурации
$config = require __DIR__ . '/../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/stats_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    mkdir($config['WORKER_LOG_DIR'], 0755, true);
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
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
        try {
            $stats = null;

            if ($publication['platform'] === 'youtube') {
                // TODO: Получить статистику через YouTube Data API
                // $stats = $youtubeService->getVideoStats($publication['platform_id']);
                $stats = [
                    'views' => 0,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                ];
            } elseif ($publication['platform'] === 'telegram') {
                // TODO: Получить статистику через Telegram Bot API
                // $stats = $telegramService->getMessageStats($publication['platform_id']);
                $stats = [
                    'views' => 0,
                    'likes' => 0,
                    'comments' => 0,
                    'shares' => 0,
                ];
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

                logMessage("Updated stats for publication ID {$publication['id']}", $logFile);
            }

        } catch (\Exception $e) {
            logMessage("Error updating stats for publication ID {$publication['id']}: " . $e->getMessage(), $logFile);
        }
    }

    logMessage('Stats worker finished', $logFile);

} catch (\Exception $e) {
    logMessage('Fatal error: ' . $e->getMessage(), $logFile);
    exit(1);
}
