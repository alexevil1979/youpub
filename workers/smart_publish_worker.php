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

set_time_limit(300);
ini_set('memory_limit', '512M');

$configPath = __DIR__ . '/../config/env.php';
if (!file_exists($configPath)) {
    error_log("Smart publish worker: config not found at {$configPath}");
    exit(1);
}
$config = require $configPath;
if (empty($config['DB_HOST']) || empty($config['DB_NAME']) || empty($config['DB_USER'])) {
    error_log('Smart publish worker: invalid DB config');
    exit(1);
}

// Установка часового пояса
$timezone = $config['TIMEZONE'] ?? 'Europe/Samara';
date_default_timezone_set($timezone);

// Инициализация БД
Database::init($config);

// Логирование
$logFile = $config['WORKER_LOG_DIR'] . '/smart_publish_' . date('Y-m-d') . '.log';
if (!is_dir($config['WORKER_LOG_DIR'])) {
    if (!@mkdir($config['WORKER_LOG_DIR'], 0755, true) && !is_dir($config['WORKER_LOG_DIR'])) {
        error_log('Smart publish worker: failed to create log directory');
        exit(1);
    }
}

function logMessage(string $message, string $logFile): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// Блокировка, чтобы не запускать параллельно
$lockFile = sys_get_temp_dir() . '/youpub_smart_publish_worker.lock';
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
    $schedules = $scheduleRepo->findActiveGroupSchedules(50);

    logMessage('Found ' . count($schedules) . ' group schedules to process', $logFile);
    
    // Детальное логирование найденных расписаний
    foreach ($schedules as $idx => $sched) {
        logMessage("Schedule #{$idx}: ID={$sched['id']}, Type={$sched['schedule_type']}, Status={$sched['status']}, Publish_at={$sched['publish_at']}, Interval={$sched['interval_minutes']}, Group={$sched['group_name']}", $logFile);
    }

    foreach ($schedules as $schedule) {
        if ($shutdown) {
            logMessage('Shutdown signal received, stopping loop', $logFile);
            break;
        }
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }

        try {
            // Пропускаем приостановленные расписания
            if (($schedule['status'] ?? '') === 'paused') {
                logMessage("Schedule ID {$schedule['id']} is paused, skipping", $logFile);
                continue;
            }
            
            // Если расписание помечено как 'published', но есть неопубликованные видео - возвращаем в 'pending'
            if (($schedule['status'] ?? '') === 'published' && !empty($schedule['content_group_id'])) {
                $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
                $nextFile = $fileRepo->findNextUnpublished($schedule['content_group_id']);
                
                if ($nextFile) {
                    // Есть неопубликованные видео - возвращаем в pending и обновляем publish_at
                    $delayMinutes = $schedule['delay_between_posts'] ?? 30;
                    $currentPublishAt = strtotime($schedule['publish_at'] ?? 'now');
                    $nextPublishAt = date('Y-m-d H:i:s', $currentPublishAt + ($delayMinutes * 60));
                    
                    $scheduleRepo->update($schedule['id'], [
                        'status' => 'pending',
                        'publish_at' => $nextPublishAt
                    ]);
                    
                    // Обновляем локальную копию расписания
                    $schedule['status'] = 'pending';
                    $schedule['publish_at'] = $nextPublishAt;
                    
                    logMessage("Schedule ID {$schedule['id']} restored to 'pending' (has unpublished videos), next publish: {$nextPublishAt}", $logFile);
                } else {
                    // Нет неопубликованных видео - пропускаем
                    logMessage("Schedule ID {$schedule['id']} is 'published' and all videos are published, skipping", $logFile);
                    continue;
                }
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
                
                // Для интервальных расписаний, если время прошло, но расписание не готово (из-за лимитов),
                // обновляем publish_at на следующее время, чтобы не блокировать следующие попытки
                if (($schedule['schedule_type'] ?? '') === 'interval' && !empty($schedule['publish_at'])) {
                    $publishAt = strtotime($schedule['publish_at']);
                    $now = time();
                    if ($publishAt <= $now) {
                        // Время прошло, но расписание не готово (вероятно, из-за лимитов)
                        // Обновляем publish_at на следующее время
                        $nextTime = $scheduleEngine->getNextPublishTime($schedule);
                        if ($nextTime) {
                            $scheduleRepo->update($schedule['id'], ['publish_at' => $nextTime]);
                            logMessage("Schedule ID {$schedule['id']} (interval) publish_at updated to {$nextTime} because schedule not ready (likely due to limits)", $logFile);
                        }
                    }
                }
                
                continue;
            }
            
            logMessage("Schedule ID {$schedule['id']} is ready for publishing. Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish, $logFile);

            logMessage("Processing group schedule ID: {$schedule['id']}, Group: {$schedule['group_name']}, Platform: {$schedule['platform']}, Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . $timeUntilPublish, $logFile);

            // Обрабатываем расписание с группой
            try {
                $scheduleRepo->update($schedule['id'], ['status' => 'processing']);
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
                
                // Для интервальных расписаний даже при ошибке обновляем publish_at, чтобы не блокировать следующие попытки
                if (($schedule['schedule_type'] ?? '') === 'interval') {
                    $nextTime = $scheduleEngine->getNextPublishTime($schedule);
                    if ($nextTime) {
                        $scheduleRepo->update($schedule['id'], [
                            'status' => 'pending',
                            'publish_at' => $nextTime,
                            'error_message' => $result['message'] ?? 'Unknown error'
                        ]);
                        logMessage("Schedule ID {$schedule['id']} (interval) publish_at updated to {$nextTime} despite error", $logFile);
                    } else {
                        $scheduleRepo->update($schedule['id'], [
                            'status' => 'pending',
                            'error_message' => $result['message'] ?? 'Unknown error'
                        ]);
                    }
                } else {
                    $scheduleRepo->update($schedule['id'], [
                        'status' => 'pending',
                        'error_message' => $result['message'] ?? 'Unknown error'
                    ]);
                }
            }

        } catch (\Exception $e) {
            logMessage("Error processing schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
            try {
                $scheduleRepo->update($schedule['id'], [
                    'status' => 'pending',
                    'error_message' => $e->getMessage()
                ]);
            } catch (\Exception $updateError) {
                logMessage("Failed to update schedule status: " . $updateError->getMessage(), $logFile);
            }
        }
    }

    logMessage('Smart publish worker finished', $logFile);

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
