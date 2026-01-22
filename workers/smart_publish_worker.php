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

    // Найти расписания с группами, готовые к публикации
    $schedules = $scheduleRepo->findActiveGroupSchedules();

    logMessage('Found ' . count($schedules) . ' group schedules to process', $logFile);

    foreach ($schedules as $schedule) {
        try {
            // Проверяем, готово ли расписание (с учетом типа и лимитов)
            if (!$scheduleEngine->isScheduleReady($schedule)) {
                logMessage("Schedule ID {$schedule['id']} not ready (limits or timing)", $logFile);
                continue;
            }

            logMessage("Processing group schedule ID: {$schedule['id']}, Group: {$schedule['group_name']}, Platform: {$schedule['platform']}", $logFile);

            // Обрабатываем расписание с группой
            $result = $smartQueue->processGroupSchedule($schedule);

            if ($result['success']) {
                logMessage("Schedule ID {$schedule['id']} published successfully", $logFile);
                
                // Обновляем статус расписания
                $scheduleRepo->update($schedule['id'], ['status' => 'published']);
            } else {
                logMessage("Schedule ID {$schedule['id']} failed: " . ($result['message'] ?? 'Unknown error'), $logFile);
                
                // Обновляем статус расписания
                $scheduleRepo->update($schedule['id'], [
                    'status' => 'failed',
                    'error_message' => $result['message'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            logMessage("Error processing schedule ID {$schedule['id']}: " . $e->getMessage(), $logFile);
            $scheduleRepo->update($schedule['id'], [
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
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
                logMessage("Processing regular schedule ID: {$schedule['id']}, Platform: {$schedule['platform']}", $logFile);

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
