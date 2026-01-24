<?php

/**
 * Smart Worker для публикации видео из групп контента
 * Запускается через cron каждую минуту
 * Работает параллельно с publish_worker.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use App\Repositories\ScheduleRepository;
use App\Modules\ContentGroups\Services\SmartQueueService;
use App\Modules\ContentGroups\Services\ScheduleEngineService;
use App\Services\YoutubeService;
use App\Services\TelegramService;

// Загрузка конфигурации
$config = require __DIR__ . '/../config/env.php';

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/smart_publish_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    mkdir($config['WORKER_LOG_DIR'], 0755, true);
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

logMessage('Smart publish worker started', $logFile);

try {
    $scheduleRepo = new ScheduleRepository();
    $smartQueue = new SmartQueueService();
    $scheduleEngine = new ScheduleEngineService();
    
    // Очищаем зависшие расписания 'processing' (старше 10 минут)
    $cleaned = $scheduleRepo->cleanupStuckProcessing(10);
    if ($cleaned > 0) {
        logMessage("Cleaned up {$cleaned} stuck processing schedules", $logFile);
    }

    // Найти расписания с группами, готовые к публикации
    $schedules = $scheduleRepo->findActiveGroupSchedules();

    logMessage('Found ' . count($schedules) . ' group schedules to process', $logFile);

    foreach ($schedules as $schedule) {
        try {
            // Пропускаем приостановленные расписания
            if (($schedule['status'] ?? '') === 'paused') {
                logMessage("Schedule ID {$schedule['id']} is paused, skipping", $logFile);
                continue;
            }
            
            // Вычисляем время до публикации для логирования
            $timeUntilPublish = '';
            if (!empty($schedule['publish_at'])) {
                $publishAt = strtotime($schedule['publish_at']);
                $now = time();
                $diff = $publishAt - $now;
                
                if ($diff > 0) {
                    $days = floor($diff / 86400);
                    $hours = floor(($diff % 86400) / 3600);
                    $minutes = floor(($diff % 3600) / 60);
                    $seconds = $diff % 60;
                    
                    $timeUntilPublish = ' (осталось: ';
                    if ($days > 0) {
                        $timeUntilPublish .= "{$days}д ";
                    }
                    if ($hours > 0) {
                        $timeUntilPublish .= "{$hours}ч ";
                    }
                    if ($minutes > 0) {
                        $timeUntilPublish .= "{$minutes}м ";
                    }
                    $timeUntilPublish .= "{$seconds}с)";
                } elseif ($diff <= 0) {
                    $timeUntilPublish = ' (время наступило, готово к публикации)';
                }
            }
            
            // Проверяем, готово ли расписание (с учетом типа и лимитов)
            if (!$scheduleEngine->isScheduleReady($schedule)) {
                logMessage("Schedule ID {$schedule['id']} not ready (limits or timing). Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish . ", Status: " . ($schedule['status'] ?? 'NULL') . ", Type: " . ($schedule['schedule_type'] ?? 'NULL'), $logFile);
                continue;
            }
            
            logMessage("Schedule ID {$schedule['id']} is ready for publishing. Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish, $logFile);

            logMessage("Processing group schedule ID: {$schedule['id']}, Group: {$schedule['group_name']}, Platform: {$schedule['platform']}, Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish, $logFile);

            // Обрабатываем расписание с группой
            try {
                $result = $smartQueue->processGroupSchedule($schedule);
                
                logMessage("Schedule ID {$schedule['id']} processGroupSchedule result: success=" . ($result['success'] ? 'true' : 'false') . ", message=" . ($result['message'] ?? 'no message'), $logFile);
            } catch (\Exception $e) {
                logMessage("Schedule ID {$schedule['id']} processGroupSchedule exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), $logFile);
                $result = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($result['success']) {
                logMessage("Schedule ID {$schedule['id']} published successfully", $logFile);
                
                // Для интервальных расписаний обновляем время следующей публикации, но не меняем статус на 'published'
                // Статус остается 'pending' для продолжения работы
                if (($schedule['schedule_type'] ?? '') === 'interval' || ($schedule['schedule_type'] ?? '') === 'batch') {
                    $nextTime = $scheduleEngine->getNextPublishTime($schedule);
                    if ($nextTime) {
                        $scheduleRepo->update($schedule['id'], ['publish_at' => $nextTime]);
                        logMessage("Schedule ID {$schedule['id']} next publish time updated to {$nextTime}", $logFile);
                    }
                } else {
                    // Для фиксированных расписаний проверяем, есть ли еще неопубликованные видео
                    if (!empty($schedule['content_group_id'])) {
                        $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
                        $nextFile = $fileRepo->findNextUnpublished($schedule['content_group_id']);
                        
                        if ($nextFile) {
                            // Есть еще неопубликованные видео - обновляем publish_at на следующее время
                            // Вычисляем следующее время публикации на основе delay_between_posts
                            $delayMinutes = $schedule['delay_between_posts'] ?? 30;
                            $currentPublishAt = strtotime($schedule['publish_at']);
                            $nextPublishAt = date('Y-m-d H:i:s', $currentPublishAt + ($delayMinutes * 60));
                            
                            $scheduleRepo->update($schedule['id'], [
                                'publish_at' => $nextPublishAt,
                                'status' => 'pending' // Оставляем pending для продолжения публикации
                            ]);
                            logMessage("Schedule ID {$schedule['id']} has more videos, next publish time updated to {$nextPublishAt}", $logFile);
                        } else {
                            // Все видео опубликованы - меняем статус на 'published'
                            $scheduleRepo->update($schedule['id'], ['status' => 'published']);
                            logMessage("Schedule ID {$schedule['id']} all videos published, marking as 'published'", $logFile);
                        }
                    } else {
                        // Нет группы - обычное фиксированное расписание, помечаем как 'published'
                        $scheduleRepo->update($schedule['id'], ['status' => 'published']);
                        logMessage("Schedule ID {$schedule['id']} (no group) marked as 'published'", $logFile);
                    }
                }
            } else {
                logMessage("Schedule ID {$schedule['id']} failed: " . ($result['message'] ?? 'Unknown error'), $logFile);
                
                // Не меняем статус основного расписания при ошибке, чтобы оно могло повторить попытку
                // Только логируем ошибку
            }

        } catch (\Exception $e) {
            logMessage("Error processing schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
            // Не меняем статус при исключении, чтобы не блокировать повторные попытки
        }
    }

    // Обрабатываем обычные расписания (без групп) - обратная совместимость
    $regularSchedules = $scheduleRepo->findDueForPublishing();
    $regularSchedules = array_filter($regularSchedules, function($s) {
        return empty($s['content_group_id']);
    });

    if (!empty($regularSchedules)) {
        logMessage('Processing ' . count($regularSchedules) . ' regular schedules', $logFile);
        
        $youtubeService = new YoutubeService();
        $telegramService = new TelegramService();

        foreach ($regularSchedules as $schedule) {
            try {
                // Вычисляем время до публикации для логирования
                $timeUntilPublish = '';
                if (!empty($schedule['publish_at'])) {
                    $publishAt = strtotime($schedule['publish_at']);
                    $now = time();
                    $diff = $publishAt - $now;
                    
                    if ($diff > 0) {
                        $days = floor($diff / 86400);
                        $hours = floor(($diff % 86400) / 3600);
                        $minutes = floor(($diff % 3600) / 60);
                        $seconds = $diff % 60;
                        
                        $timeUntilPublish = ' (осталось: ';
                        if ($days > 0) {
                            $timeUntilPublish .= "{$days}д ";
                        }
                        if ($hours > 0) {
                            $timeUntilPublish .= "{$hours}ч ";
                        }
                        if ($minutes > 0) {
                            $timeUntilPublish .= "{$minutes}м ";
                        }
                        $timeUntilPublish .= "{$seconds}с)";
                    } elseif ($diff <= 0) {
                        $timeUntilPublish = ' (время наступило, готово к публикации)';
                    }
                }
                
                logMessage("Processing regular schedule ID: {$schedule['id']}, Platform: {$schedule['platform']}, Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish, $logFile);

                $result = null;
                if ($schedule['platform'] === 'youtube') {
                    $result = $youtubeService->publishVideo($schedule['id']);
                } elseif ($schedule['platform'] === 'telegram') {
                    $result = $telegramService->publishVideo($schedule['id']);
                }

                if ($result && $result['success']) {
                    logMessage("Regular schedule ID {$schedule['id']} published successfully", $logFile);
                    $scheduleRepo->update($schedule['id'], ['status' => 'published']);
                } else {
                    logMessage("Regular schedule ID {$schedule['id']} failed", $logFile);
                    $scheduleRepo->update($schedule['id'], [
                        'status' => 'failed',
                        'error_message' => $result['message'] ?? 'Unknown error'
                    ]);
                }
            } catch (\Exception $e) {
                logMessage("Error processing regular schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
            }
        }
    }

    logMessage('Smart publish worker finished', $logFile);

} catch (\Exception $e) {
    logMessage('Fatal error: ' . $e->getMessage(), $logFile);
    exit(1);
}
