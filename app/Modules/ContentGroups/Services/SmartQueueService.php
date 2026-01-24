<?php

namespace App\Modules\ContentGroups\Services;

use Core\Service;
use App\Repositories\ScheduleRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupFileRepository;
use App\Modules\ContentGroups\Services\TemplateService;
use App\Services\YoutubeService;
use App\Services\TelegramService;

/**
 * Сервис Smart Queue для автоматической публикации из групп
 */
class SmartQueueService extends Service
{
    private ScheduleRepository $scheduleRepo;
    private ContentGroupRepository $groupRepo;
    private ContentGroupFileRepository $fileRepo;
    private TemplateService $templateService;
    private ScheduleEngineService $scheduleEngine;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->groupRepo = new ContentGroupRepository();
        $this->fileRepo = new ContentGroupFileRepository();
        $this->templateService = new TemplateService();
        $this->scheduleEngine = new ScheduleEngineService();
    }

    /**
     * Обработать расписание с группой
     */
    public function processGroupSchedule(array $schedule): array
    {
        if (empty($schedule['content_group_id'])) {
            return ['success' => false, 'message' => 'No content group specified'];
        }

        // Проверяем, готово ли расписание (проверка времени и лимитов)
        if (!$this->scheduleEngine->isScheduleReady($schedule)) {
            error_log("SmartQueueService::processGroupSchedule: Schedule ID {$schedule['id']} not ready. Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . ", Status: " . ($schedule['status'] ?? 'NULL'));
            return ['success' => false, 'message' => 'Schedule not ready (limits or timing)'];
        }

        // Получаем группу
        $group = $this->groupRepo->findById($schedule['content_group_id']);
        if (!$group || $group['status'] !== 'active') {
            error_log("SmartQueueService::processGroupSchedule: Group not found or inactive. Group ID: " . ($schedule['content_group_id'] ?? 'NULL') . ", Group status: " . ($group['status'] ?? 'not found'));
            return ['success' => false, 'message' => 'Group not found or inactive'];
        }
        
        error_log("SmartQueueService::processGroupSchedule: Group found. Group ID: {$group['id']}, Status: {$group['status']}");

        // Проверяем, нужно ли пропускать опубликованные
        $skipPublished = $schedule['skip_published'] ?? true;

        // Получаем следующее видео из группы
        $groupFile = $this->fileRepo->findNextUnpublished($schedule['content_group_id']);
        
        if (!$groupFile) {
            error_log("SmartQueueService::processGroupSchedule: No unpublished file found. Group ID: {$schedule['content_group_id']}, Skip published: " . ($skipPublished ? 'true' : 'false'));
            
            // Все видео опубликованы или нет доступных
            if ($skipPublished) {
                return ['success' => false, 'message' => 'No unpublished videos in group'];
            }
            
            // Если не пропускаем, берем любое видео из группы
            $files = $this->fileRepo->findByGroupId($schedule['content_group_id'], ['order_index' => 'ASC']);
            if (empty($files)) {
                error_log("SmartQueueService::processGroupSchedule: No files in group. Group ID: {$schedule['content_group_id']}");
                return ['success' => false, 'message' => 'No videos in group'];
            }
            $groupFile = $files[0];
            error_log("SmartQueueService::processGroupSchedule: Using first file from group. File ID: {$groupFile['id']}, Video ID: {$groupFile['video_id']}, Status: {$groupFile['status']}");
        } else {
            error_log("SmartQueueService::processGroupSchedule: Found unpublished file. File ID: {$groupFile['id']}, Video ID: {$groupFile['video_id']}, File status: " . ($groupFile['status'] ?? 'unknown') . ", Video status: " . ($groupFile['video_status'] ?? 'unknown'));
        }

        // Обновляем статус файла в группе
        error_log("SmartQueueService::processGroupSchedule: Updating file status to 'queued'. File ID: {$groupFile['id']}");
        $updateResult = $this->fileRepo->updateFileStatus($groupFile['id'], 'queued');
        if (!$updateResult) {
            error_log("SmartQueueService::processGroupSchedule: Failed to update file status to 'queued'. File ID: {$groupFile['id']}");
        }

        // Применяем шаблон, если есть
        $templateId = $schedule['template_id'] ?? $group['template_id'] ?? null;
        $video = [
            'id' => $groupFile['video_id'],
            'title' => $groupFile['title'] ?? '',
            'description' => '',
            'tags' => '',
        ];

        $context = [
            'group_name' => $group['name'],
            'index' => $groupFile['order_index'],
            'platform' => $schedule['platform'],
        ];

        $templated = $this->templateService->applyTemplate($templateId, $video, $context);
        error_log("SmartQueueService::processGroupSchedule: Template applied. Template ID: " . ($templateId ?? 'null'));

        // Очищаем ВСЕ зависшие расписания 'processing' для этого видео (старше 2 минут)
        // 2 минуты достаточно для публикации, если дольше - значит зависло
        error_log("SmartQueueService::processGroupSchedule: Checking for stuck processing schedules for video {$groupFile['video_id']}");
        $stmt = $this->db->prepare("
            SELECT id, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) as age_seconds
            FROM schedules 
            WHERE video_id = ? 
            AND status = 'processing' 
            AND content_group_id IS NOT NULL
            AND created_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        ");
        $stmt->execute([$groupFile['video_id']]);
        $stuckProcessing = $stmt->fetchAll();
        
        if (!empty($stuckProcessing)) {
            error_log("SmartQueueService::processGroupSchedule: Found " . count($stuckProcessing) . " stuck processing schedules (older than 2 minutes), cleaning up");
            foreach ($stuckProcessing as $stuck) {
                $ageSeconds = $stuck['age_seconds'] ?? 0;
                error_log("SmartQueueService::processGroupSchedule: Cleaning up stuck schedule ID: {$stuck['id']}, Created at: {$stuck['created_at']}, Age: {$ageSeconds} seconds");
                $this->scheduleRepo->update($stuck['id'], [
                    'status' => 'failed',
                    'error_message' => 'Processing timeout (2 minutes)'
                ]);
            }
        }
        
        // Проверяем активные расписания 'processing' для этого видео (младше 2 минут)
        // Если есть активное расписание, которое действительно обрабатывается (старше 5 секунд), пропускаем
        $stmt = $this->db->prepare("
            SELECT id, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) as age_seconds
            FROM schedules 
            WHERE video_id = ? 
            AND status = 'processing' 
            AND content_group_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
            AND created_at < DATE_SUB(NOW(), INTERVAL 5 SECOND)
        ");
        $stmt->execute([$groupFile['video_id']]);
        $activeProcessing = $stmt->fetchAll();
        
        if (!empty($activeProcessing)) {
            // Уже есть активное расписание в обработке для этого видео, пропускаем
            error_log("SmartQueueService::processGroupSchedule: Video {$groupFile['video_id']} already being processed. Active processing schedules: " . count($activeProcessing));
            foreach ($activeProcessing as $proc) {
                $ageSeconds = $proc['age_seconds'] ?? 0;
                error_log("SmartQueueService::processGroupSchedule: Active processing schedule ID: {$proc['id']}, Created at: " . ($proc['created_at'] ?? 'unknown') . ", Age: {$ageSeconds} seconds");
            }
            return ['success' => false, 'message' => 'Video already being processed'];
        }
        
        error_log("SmartQueueService::processGroupSchedule: No active processing schedules found for video {$groupFile['video_id']}, proceeding with publication");

        // Создаем временное расписание для публикации
        error_log("SmartQueueService::processGroupSchedule: Creating temporary schedule for video {$groupFile['video_id']}, platform: {$schedule['platform']}");
        $tempScheduleId = $this->scheduleRepo->create([
            'user_id' => $schedule['user_id'],
            'video_id' => $groupFile['video_id'],
            'content_group_id' => $schedule['content_group_id'],
            'platform' => $schedule['platform'],
            'publish_at' => date('Y-m-d H:i:s'),
            'status' => 'processing',
        ]);
        
        if (!$tempScheduleId) {
            error_log("SmartQueueService::processGroupSchedule: Failed to create temporary schedule");
            return ['success' => false, 'message' => 'Failed to create temporary schedule'];
        }
        
        error_log("SmartQueueService::processGroupSchedule: Temporary schedule created. ID: {$tempScheduleId}");

        // Публикуем
        error_log("SmartQueueService::processGroupSchedule: Calling publishVideo. Platform: {$schedule['platform']}, Temp schedule ID: {$tempScheduleId}");
        $result = $this->publishVideo($schedule['platform'], $tempScheduleId, $templated);
        error_log("SmartQueueService::processGroupSchedule: publishVideo result. Success: " . ($result['success'] ? 'true' : 'false') . ", Message: " . ($result['message'] ?? 'no message'));

        // Обновляем статус временного расписания и файла в группе
        if ($result['success']) {
            error_log("SmartQueueService::processGroupSchedule: Publication successful. Updating file status to 'published'");
            $publicationId = $result['data']['publication_id'] ?? null;
            $this->fileRepo->updateFileStatus($groupFile['id'], 'published', $publicationId);
            // Обновляем статус временного расписания на 'published'
            $this->scheduleRepo->update($tempScheduleId, [
                'status' => 'published',
                'error_message' => null
            ]);
        } else {
            $this->fileRepo->updateFileStatus($groupFile['id'], 'error');
            $this->fileRepo->update($groupFile['id'], ['error_message' => $result['message'] ?? 'Unknown error']);
            // Обновляем статус временного расписания на 'failed'
            $this->scheduleRepo->update($tempScheduleId, [
                'status' => 'failed',
                'error_message' => $result['message'] ?? 'Unknown error'
            ]);
        }

        // Обновляем время следующей публикации для интервальных расписаний
        if ($schedule['schedule_type'] === 'interval' || $schedule['schedule_type'] === 'batch') {
            $nextTime = $this->scheduleEngine->getNextPublishTime($schedule);
            if ($nextTime) {
                $this->scheduleRepo->update($schedule['id'], ['publish_at' => $nextTime]);
            }
        }

        return $result;
    }

    /**
     * Публиковать видео
     */
    private function publishVideo(string $platform, int $scheduleId, array $templated): array
    {
        switch ($platform) {
            case 'youtube':
                $service = new YoutubeService();
                // Обновляем видео с шаблонными данными перед публикацией
                $this->updateVideoMetadata($scheduleId, $templated);
                return $service->publishVideo($scheduleId);
            
            case 'telegram':
                $service = new TelegramService();
                $this->updateVideoMetadata($scheduleId, $templated);
                return $service->publishVideo($scheduleId);
            
            default:
                return ['success' => false, 'message' => 'Unsupported platform'];
        }
    }


    /**
     * Обновить метаданные видео перед публикацией
     */
    private function updateVideoMetadata(int $scheduleId, array $templated): void
    {
        try {
            $schedule = $this->scheduleRepo->findById($scheduleId);
            if (!$schedule || empty($schedule['video_id'])) {
                error_log("SmartQueueService::updateVideoMetadata: Schedule or video_id not found for schedule ID: {$scheduleId}");
                return;
            }

            $videoRepo = new \App\Repositories\VideoRepository();
            $updateData = [];

            if (!empty($templated['title'])) {
                $updateData['title'] = $templated['title'];
            }
            if (!empty($templated['description'])) {
                $updateData['description'] = $templated['description'];
            }
            if (!empty($templated['tags'])) {
                $updateData['tags'] = $templated['tags'];
            }

            if (!empty($updateData)) {
                $videoRepo->update($schedule['video_id'], $updateData);
                error_log("SmartQueueService::updateVideoMetadata: Updated video ID {$schedule['video_id']} with template data");
            }
        } catch (\Exception $e) {
            error_log("SmartQueueService::updateVideoMetadata: Error - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        }
    }

    /**
     * Автоматическое перемешивание видео в группе
     */
    public function shuffleGroup(int $groupId, int $userId): array
    {
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Group not found'];
        }

        $files = $this->fileRepo->findByGroupId($groupId);
        
        // Перемешиваем
        shuffle($files);

        // Обновляем order_index
        foreach ($files as $index => $file) {
            $this->fileRepo->update($file['id'], ['order_index' => $index + 1]);
        }

        return [
            'success' => true,
            'message' => 'Group shuffled successfully',
            'data' => ['shuffled_count' => count($files)]
        ];
    }

    /**
     * Пауза группы при ошибках
     */
    public function pauseGroupOnErrors(int $groupId, int $maxErrors = 5): void
    {
        $errorCount = $this->fileRepo->findByGroupIdAndStatus($groupId, 'error');
        
        if (count($errorCount) >= $maxErrors) {
            $this->groupRepo->update($groupId, ['status' => 'paused']);
            error_log("Group {$groupId} paused due to {$maxErrors} errors");
        }
    }
}
