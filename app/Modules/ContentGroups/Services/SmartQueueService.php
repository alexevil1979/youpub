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
            return ['success' => false, 'message' => 'Group not found or inactive'];
        }

        // Проверяем, нужно ли пропускать опубликованные
        $skipPublished = $schedule['skip_published'] ?? true;

        // Получаем следующее видео из группы
        $groupFile = $this->fileRepo->findNextUnpublished($schedule['content_group_id']);
        
        if (!$groupFile) {
            // Все видео опубликованы или нет доступных
            if ($skipPublished) {
                return ['success' => false, 'message' => 'No unpublished videos in group'];
            }
            
            // Если не пропускаем, берем любое видео из группы
            $files = $this->fileRepo->findByGroupId($schedule['content_group_id'], ['order_index' => 'ASC']);
            if (empty($files)) {
                return ['success' => false, 'message' => 'No videos in group'];
            }
            $groupFile = $files[0];
        }

        // Обновляем статус файла в группе
        $this->fileRepo->updateFileStatus($groupFile['id'], 'queued');

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

        // Проверяем, нет ли уже расписания 'processing' для этого видео
        // И очищаем старые зависшие расписания 'processing' (старше 10 минут)
        $stmt = $this->db->prepare("
            SELECT id FROM schedules 
            WHERE video_id = ? 
            AND status = 'processing' 
            AND content_group_id IS NOT NULL
            AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute([$groupFile['video_id']]);
        $oldProcessing = $stmt->fetchAll();
        
        // Очищаем старые зависшие расписания
        foreach ($oldProcessing as $old) {
            $this->scheduleRepo->update($old['id'], [
                'status' => 'failed',
                'error_message' => 'Processing timeout (10 minutes)'
            ]);
        }
        
        // Проверяем активные расписания 'processing' для этого видео
        $stmt = $this->db->prepare("
            SELECT id FROM schedules 
            WHERE video_id = ? 
            AND status = 'processing' 
            AND content_group_id IS NOT NULL
            AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute([$groupFile['video_id']]);
        $activeProcessing = $stmt->fetchAll();
        
        if (!empty($activeProcessing)) {
            // Уже есть активное расписание в обработке для этого видео, пропускаем
            return ['success' => false, 'message' => 'Video already being processed'];
        }

        // Создаем временное расписание для публикации
        $tempScheduleId = $this->scheduleRepo->create([
            'user_id' => $schedule['user_id'],
            'video_id' => $groupFile['video_id'],
            'content_group_id' => $schedule['content_group_id'],
            'platform' => $schedule['platform'],
            'publish_at' => date('Y-m-d H:i:s'),
            'status' => 'processing',
        ]);

        // Публикуем
        $result = $this->publishVideo($schedule['platform'], $tempScheduleId, $templated);

        // Обновляем статус временного расписания и файла в группе
        if ($result['success']) {
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
