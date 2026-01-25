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
        error_log("SmartQueueService::processGroupSchedule: ===== START PROCESSING SCHEDULE ID {$schedule['id']} =====");
        error_log("SmartQueueService::processGroupSchedule: Schedule details - Group: " . ($schedule['group_name'] ?? 'unknown') . ", Platform: {$schedule['platform']}, Status: {$schedule['status']}, Publish_at: {$schedule['publish_at']}");

        if (empty($schedule['content_group_id'])) {
            error_log("SmartQueueService::processGroupSchedule: ERROR - No content group specified");
            return ['success' => false, 'message' => 'No content group specified'];
        }

        // Проверяем, готово ли расписание (проверка времени и лимитов)
        if (!$this->scheduleEngine->isScheduleReady($schedule)) {
            error_log("SmartQueueService::processGroupSchedule: Schedule ID {$schedule['id']} not ready. Publish_at: " . ($schedule['publish_at'] ?? 'NULL') . ", Status: " . ($schedule['status'] ?? 'NULL'));
            return ['success' => false, 'message' => 'Schedule not ready (limits or timing)'];
        }

        error_log("SmartQueueService::processGroupSchedule: Schedule is ready for processing");

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
                // Останавливаем расписание, чтобы не попадать в повторную обработку
                $this->scheduleRepo->update($schedule['id'], [
                    'status' => 'published',
                    'publish_at' => null
                ]);
                error_log("SmartQueueService::processGroupSchedule: Group schedule {$schedule['id']} marked as published (no unpublished videos)");
                return ['success' => true, 'message' => 'No unpublished videos in group'];
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

        // Статус файла обновляется в транзакции при создании временного расписания

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
        
        // Используем транзакцию и блокировку для предотвращения race condition
        $this->db->beginTransaction();
        
        try {
            // Проверяем активные расписания 'processing' для этого видео с блокировкой строк
            // SELECT FOR UPDATE блокирует строки до конца транзакции
            $stmt = $this->db->prepare("
                SELECT id, created_at, TIMESTAMPDIFF(SECOND, created_at, NOW()) as age_seconds
                FROM schedules 
                WHERE video_id = ? 
                AND status = 'processing' 
                AND content_group_id IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                AND created_at < DATE_SUB(NOW(), INTERVAL 5 SECOND)
                FOR UPDATE
            ");
            $stmt->execute([$groupFile['video_id']]);
            $activeProcessing = $stmt->fetchAll();
            
            if (!empty($activeProcessing)) {
                // Уже есть активное расписание в обработке для этого видео, пропускаем
                $this->db->rollBack();
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
                $this->db->rollBack();
                error_log("SmartQueueService::processGroupSchedule: Failed to create temporary schedule");
                return ['success' => false, 'message' => 'Failed to create temporary schedule'];
            }
            
            // Обновляем статус файла в группе на 'queued' в той же транзакции
            $this->fileRepo->updateFileStatus($groupFile['id'], 'queued');
            
            // Коммитим транзакцию - теперь временное расписание создано и файл в очереди
            $this->db->commit();
            
            error_log("SmartQueueService::processGroupSchedule: Temporary schedule created. ID: {$tempScheduleId}");
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("SmartQueueService::processGroupSchedule: Transaction failed - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create temporary schedule: ' . $e->getMessage()];
        }

        // Публикуем
        error_log("SmartQueueService::processGroupSchedule: ===== CALLING PUBLISH VIDEO =====");
        error_log("SmartQueueService::processGroupSchedule: Platform: {$schedule['platform']}, Temp schedule ID: {$tempScheduleId}, Video ID: {$groupFile['video_id']}");
        error_log("SmartQueueService::processGroupSchedule: Template applied: " . (!empty($templated) ? 'yes' : 'no'));

        try {
            $result = $this->publishVideo($schedule['platform'], $tempScheduleId, $templated);
            error_log("SmartQueueService::processGroupSchedule: ===== PUBLISH VIDEO COMPLETED =====");
            error_log("SmartQueueService::processGroupSchedule: publishVideo result. Success: " . ($result['success'] ? 'true' : 'false') . ", Message: " . ($result['message'] ?? 'no message'));
        } catch (Exception $e) {
            error_log("SmartQueueService::processGroupSchedule: ===== PUBLISH VIDEO EXCEPTION =====");
            error_log("SmartQueueService::processGroupSchedule: Exception in publishVideo: " . $e->getMessage());
            $result = ['success' => false, 'message' => 'Exception during publication: ' . $e->getMessage()];
        }

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

            // Для групповых расписаний: проверяем, остались ли ещё видео для публикации
            error_log("SmartQueueService::processGroupSchedule: Checking for remaining unpublished videos in group {$schedule['content_group_id']}");
            $remainingFiles = $this->fileRepo->findNextUnpublished($schedule['content_group_id']);

            if ($remainingFiles) {
                // Есть ещё видео для публикации - обновляем время следующей публикации
                error_log("SmartQueueService::processGroupSchedule: Found remaining videos. Updating publish_at for next video");
                $nextPublishTime = $this->scheduleEngine->getNextPublishTime($schedule);
                if ($nextPublishTime) {
                    $this->scheduleRepo->update($schedule['id'], [
                        'publish_at' => $nextPublishTime,
                        'status' => 'pending' // Оставляем активным для следующих видео
                    ]);
                    error_log("SmartQueueService::processGroupSchedule: Updated schedule {$schedule['id']} publish_at to {$nextPublishTime}, status to 'pending'");
                } else {
                    error_log("SmartQueueService::processGroupSchedule: Could not calculate next publish time, keeping schedule active");
                }
            } else {
                // Все видео опубликованы - завершаем групповое расписание
                error_log("SmartQueueService::processGroupSchedule: No remaining videos found. Completing group schedule {$schedule['id']}");
                $this->scheduleRepo->update($schedule['id'], [
                    'status' => 'published',
                    'publish_at' => null // Убираем время публикации
                ]);
                error_log("SmartQueueService::processGroupSchedule: Group schedule {$schedule['id']} marked as published and publish_at set to null");
            }
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

        error_log("SmartQueueService::processGroupSchedule: ===== END PROCESSING SCHEDULE ID {$schedule['id']} =====");
        error_log("SmartQueueService::processGroupSchedule: Final result - Success: " . ($result['success'] ? 'true' : 'false') . ", Message: " . ($result['message'] ?? 'no message'));

        return $result;
    }

    /**
     * Опубликовать конкретный файл группы прямо сейчас
     */
    public function publishGroupFileNow(int $groupId, int $fileId, int $userId): array
    {
        try {
            // Инициализируем сессию, если не инициализирована
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            if ((int)$group['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }
            if (($group['status'] ?? '') === 'archived') {
                return ['success' => false, 'message' => 'Группа в архиве'];
            }

            $groupFile = $this->fileRepo->findById($fileId);
            if (!$groupFile || (int)$groupFile['group_id'] !== $groupId) {
                return ['success' => false, 'message' => 'Файл не найден в группе'];
            }

            $allowedStatuses = ['new', 'queued', 'paused', 'error'];
            if (!in_array($groupFile['status'], $allowedStatuses, true)) {
                return ['success' => false, 'message' => 'Этот файл нельзя опубликовать сейчас'];
            }

            $videoRepo = new \App\Repositories\VideoRepository();
            $video = $videoRepo->findById((int)$groupFile['video_id']);
            if (!$video) {
                return ['success' => false, 'message' => 'Видео не найдено'];
            }
            if (!file_exists($video['file_path'])) {
                return ['success' => false, 'message' => 'Файл видео не найден'];
            }

            $latestSchedules = $this->scheduleRepo->findLatestByGroupIds([$groupId]);
            $schedule = $latestSchedules[$groupId] ?? null;
            $platform = $schedule['platform'] ?? 'youtube';
            $templateId = $schedule['template_id'] ?? $group['template_id'] ?? null;

            // Проверяем, есть ли сохраненное оформление в сессии (из превью)
            $previewKey = "{$groupId}_{$fileId}";
            $templated = null;
            
            if (isset($_SESSION['publish_previews'][$previewKey])) {
                $templated = $_SESSION['publish_previews'][$previewKey];
                // Удаляем из сессии после использования
                unset($_SESSION['publish_previews'][$previewKey]);
                error_log("SmartQueueService::publishGroupFileNow: Using saved preview for key: {$previewKey}");
                error_log("SmartQueueService::publishGroupFileNow: Saved preview title: " . ($templated['title'] ?? 'N/A'));
                error_log("SmartQueueService::publishGroupFileNow: Saved preview description: " . mb_substr($templated['description'] ?? 'N/A', 0, 100));
                error_log("SmartQueueService::publishGroupFileNow: Saved preview tags: " . ($templated['tags'] ?? 'N/A'));
            } else {
                error_log("SmartQueueService::publishGroupFileNow: No saved preview found for key: {$previewKey}");
                error_log("SmartQueueService::publishGroupFileNow: Session keys: " . (isset($_SESSION['publish_previews']) ? implode(', ', array_keys($_SESSION['publish_previews'])) : 'no publish_previews in session'));
            }
            
            // Если сохраненного оформления нет, генерируем новое
            if (!$templated) {
                $context = [
                    'group_name' => $group['name'] ?? '',
                    'index' => $groupFile['order_index'] ?? 0,
                    'platform' => $platform,
                ];

                $templated = $this->templateService->applyTemplate($templateId, [
                    'id' => $video['id'],
                    'title' => $video['title'] ?? $video['file_name'] ?? '',
                    'description' => $video['description'] ?? '',
                    'tags' => $video['tags'] ?? '',
                ], $context);
                error_log("SmartQueueService::publishGroupFileNow: Generated new template (no saved preview found)");
                error_log("SmartQueueService::publishGroupFileNow: Generated title: " . ($templated['title'] ?? 'N/A'));
            }

            // Проверяем, не публикуется ли уже этот файл
            $fileStatus = $groupFile['status'] ?? 'new';
            if ($fileStatus === 'queued' || $fileStatus === 'published') {
                // Проверяем, есть ли активные расписания для этого видео
                $stmt = $this->db->prepare("
                    SELECT id 
                    FROM schedules 
                    WHERE video_id = ? 
                    AND status IN ('processing', 'pending')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    LIMIT 1
                ");
                $stmt->execute([(int)$groupFile['video_id']]);
                if ($stmt->fetch()) {
                    error_log("SmartQueueService::publishGroupFileNow: Video {$groupFile['video_id']} already has active schedule");
                    return ['success' => false, 'message' => 'Этот файл уже публикуется или в очереди'];
                }
            }

            $this->db->beginTransaction();
            try {
                // Блокируем строку файла для предотвращения параллельной публикации
                $fileLockStmt = $this->db->prepare("
                    SELECT id, status 
                    FROM content_group_files 
                    WHERE id = ? 
                    FOR UPDATE
                ");
                $fileLockStmt->execute([(int)$groupFile['id']]);
                $lockedFile = $fileLockStmt->fetch();
                
                if (!$lockedFile) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Файл не найден'];
                }
                
                // Проверяем статус файла еще раз после блокировки
                if (!in_array($lockedFile['status'], $allowedStatuses, true)) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Этот файл нельзя опубликовать сейчас (статус: ' . $lockedFile['status'] . ')'];
                }
                
                // Проверяем только РЕАЛЬНО активные расписания (processing, pending), не published
                // Блокируем только активные расписания для этого видео
                $stmt = $this->db->prepare("
                    SELECT id, status, created_at 
                    FROM schedules 
                    WHERE video_id = ? 
                    AND status IN ('processing', 'pending')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    FOR UPDATE
                ");
                $stmt->execute([(int)$groupFile['video_id']]);
                $activeSchedules = $stmt->fetchAll();
                
                if (!empty($activeSchedules)) {
                    $this->db->rollBack();
                    error_log("SmartQueueService::publishGroupFileNow: Video {$groupFile['video_id']} already has active schedule(s): " . count($activeSchedules));
                    foreach ($activeSchedules as $sched) {
                        error_log("SmartQueueService::publishGroupFileNow: Active schedule ID: {$sched['id']}, status: {$sched['status']}, created: {$sched['created_at']}");
                    }
                    return ['success' => false, 'message' => 'Видео уже публикуется, попробуйте позже'];
                }
                
                // Проверяем успешные публикации только за последние 2 минуты (чтобы не блокировать повторные публикации)
                $pubStmt = $this->db->prepare("
                    SELECT id, platform_id, created_at 
                    FROM publications 
                    WHERE video_id = ? 
                    AND platform = ?
                    AND status = 'success'
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $pubStmt->execute([(int)$groupFile['video_id'], $platform]);
                $recentPub = $pubStmt->fetch();
                if ($recentPub) {
                    $this->db->rollBack();
                    error_log("SmartQueueService::publishGroupFileNow: Video {$groupFile['video_id']} was just published to {$platform} (publication ID: {$recentPub['id']}, created: {$recentPub['created_at']})");
                    return ['success' => false, 'message' => 'Это видео только что было опубликовано на ' . $platform . '. Подождите несколько секунд.'];
                }
                
                error_log("SmartQueueService::publishGroupFileNow: All checks passed, creating schedule for video {$groupFile['video_id']}");

                // Создаем расписание с проверкой на дубликаты на уровне БД
                // Используем INSERT IGNORE или проверку перед вставкой
                $tempScheduleId = $this->scheduleRepo->create([
                    'user_id' => $userId,
                    'video_id' => (int)$groupFile['video_id'],
                    'content_group_id' => $groupId,
                    'template_id' => $templateId,
                    'platform' => $platform,
                    'publish_at' => date('Y-m-d H:i:s'),
                    'status' => 'processing',
                ]);
                
                if (!$tempScheduleId) {
                    $this->db->rollBack();
                    error_log("SmartQueueService::publishGroupFileNow: Failed to create schedule");
                    return ['success' => false, 'message' => 'Не удалось создать расписание'];
                }
                
                error_log("SmartQueueService::publishGroupFileNow: Created schedule ID: {$tempScheduleId} for video {$groupFile['video_id']}");
                
                // Финальная проверка: убеждаемся, что мы единственное активное расписание для этого видео
                // Проверяем только в рамках этой транзакции (другие транзакции не видят наше расписание до коммита)
                // Эта проверка нужна на случай, если два запроса прошли первую проверку одновременно
                $finalCheckStmt = $this->db->prepare("
                    SELECT COUNT(*) as count
                    FROM schedules 
                    WHERE video_id = ? 
                    AND status IN ('processing', 'pending')
                    AND id != ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                ");
                $finalCheckStmt->execute([(int)$groupFile['video_id'], $tempScheduleId]);
                $finalCheck = $finalCheckStmt->fetch();
                
                if ($finalCheck && (int)$finalCheck['count'] > 0) {
                    // Отменяем только что созданное расписание
                    $this->scheduleRepo->update($tempScheduleId, [
                        'status' => 'cancelled',
                        'error_message' => 'Another schedule was found after creation'
                    ]);
                    $this->db->rollBack();
                    error_log("SmartQueueService::publishGroupFileNow: Found other active schedules after creation, cancelled schedule {$tempScheduleId}");
                    return ['success' => false, 'message' => 'Видео уже публикуется другим расписанием'];
                }

                if (!$tempScheduleId) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => 'Не удалось создать временное расписание'];
                }

                $this->fileRepo->updateFileStatus((int)$groupFile['id'], 'queued');
                $this->db->commit();
            } catch (\Exception $e) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Ошибка подготовки публикации: ' . $e->getMessage()];
            }

            // ВАЖНО: Обновляем метаданные видео ПЕРЕД публикацией, чтобы YoutubeService использовал правильные данные
            error_log("SmartQueueService::publishGroupFileNow: Updating video metadata before publication");
            error_log("SmartQueueService::publishGroupFileNow: Template data - title: " . mb_substr($templated['title'] ?? 'N/A', 0, 100));
            error_log("SmartQueueService::publishGroupFileNow: Template data - description: " . mb_substr($templated['description'] ?? 'N/A', 0, 100));
            error_log("SmartQueueService::publishGroupFileNow: Template data - tags: " . mb_substr($templated['tags'] ?? 'N/A', 0, 200));
            
            // Обновляем метаданные видео СРАЗУ после создания расписания
            try {
                $this->updateVideoMetadata($tempScheduleId, $templated);
                error_log("SmartQueueService::publishGroupFileNow: Video metadata updated successfully");
            } catch (\Exception $e) {
                error_log("SmartQueueService::publishGroupFileNow: Error updating metadata: " . $e->getMessage());
                // Продолжаем публикацию, даже если обновление метаданных не удалось
            }
            
            error_log("SmartQueueService::publishGroupFileNow: Starting publishVideo for schedule {$tempScheduleId}");
            try {
                $result = $this->publishVideo($platform, $tempScheduleId, $templated);
                error_log("SmartQueueService::publishGroupFileNow: publishVideo completed. Success: " . ($result['success'] ? 'true' : 'false') . ", Message: " . ($result['message'] ?? 'no message'));
            } catch (\Exception $e) {
                error_log("SmartQueueService::publishGroupFileNow: Exception in publishVideo: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                $result = [
                    'success' => false,
                    'message' => 'Ошибка при публикации: ' . $e->getMessage()
                ];
            }

            if ($result['success']) {
                $publicationId = $result['data']['publication_id'] ?? null;
                $this->fileRepo->updateFileStatus((int)$groupFile['id'], 'published', $publicationId ? (int)$publicationId : null);
                $this->scheduleRepo->update($tempScheduleId, [
                    'status' => 'published',
                    'error_message' => null,
                ]);
                error_log("SmartQueueService::publishGroupFileNow: Publication successful. Publication ID: {$publicationId}");
            } else {
                $errorMessage = $result['message'] ?? 'Unknown error';
                error_log("SmartQueueService::publishGroupFileNow: Publication failed. Error: {$errorMessage}");
                $this->fileRepo->updateFileStatus((int)$groupFile['id'], 'error');
                $this->fileRepo->update((int)$groupFile['id'], [
                    'error_message' => $errorMessage
                ]);
                $this->scheduleRepo->update($tempScheduleId, [
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            error_log("SmartQueueService::publishGroupFileNow error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ошибка публикации: ' . $e->getMessage()];
        }
    }

    /**
     * Публиковать видео
     */
    private function publishVideo(string $platform, int $scheduleId, array $templated): array
    {
        // Метаданные уже обновлены в publishGroupFileNow перед вызовом publishVideo
        // Здесь только публикуем с уже обновленными данными
        switch ($platform) {
            case 'youtube':
                $service = new YoutubeService();
                // Метаданные уже обновлены, просто публикуем
                error_log("SmartQueueService::publishVideo: Publishing to YouTube with schedule ID: {$scheduleId}");
                return $service->publishVideo($scheduleId);
            
            case 'telegram':
                $service = new TelegramService();
                // Метаданные уже обновлены, просто публикуем
                error_log("SmartQueueService::publishVideo: Publishing to Telegram with schedule ID: {$scheduleId}");
                return $service->publishVideo($scheduleId);
            
            case 'both':
                // Метаданные уже обновлены, просто публикуем
                error_log("SmartQueueService::publishVideo: Publishing to both platforms with schedule ID: {$scheduleId}");
                $youtubeService = new YoutubeService();
                $telegramService = new TelegramService();
                $youtubeResult = $youtubeService->publishVideo($scheduleId);
                $telegramResult = $telegramService->publishVideo($scheduleId);
                
                $success = $youtubeResult['success'] && $telegramResult['success'];
                $messages = [];
                if (!$youtubeResult['success']) {
                    $messages[] = 'YouTube: ' . ($youtubeResult['message'] ?? 'Ошибка');
                }
                if (!$telegramResult['success']) {
                    $messages[] = 'Telegram: ' . ($telegramResult['message'] ?? 'Ошибка');
                }
                
                $publicationId = $youtubeResult['data']['publication_id'] ?? $telegramResult['data']['publication_id'] ?? null;
                
                return [
                    'success' => $success,
                    'message' => $success ? 'Published on both platforms' : implode('; ', $messages),
                    'data' => [
                        'youtube' => $youtubeResult,
                        'telegram' => $telegramResult,
                        'publication_id' => $publicationId,
                    ],
                ];
            
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
                error_log("SmartQueueService::updateVideoMetadata: Title to update: " . mb_substr($templated['title'], 0, 100));
            }
            // Обновляем description, если он сгенерирован шаблоном (не пустой)
            // TemplateService теперь всегда генерирует description (с fallback), поэтому проверяем !empty
            if (!empty($templated['description'])) {
                $updateData['description'] = $templated['description'];
                error_log("SmartQueueService::updateVideoMetadata: Description to update (length: " . mb_strlen($templated['description']) . "): " . mb_substr($templated['description'], 0, 100));
            } else {
                error_log("SmartQueueService::updateVideoMetadata: Description is empty in templated data");
            }
            if (!empty($templated['tags'])) {
                $updateData['tags'] = $templated['tags'];
                error_log("SmartQueueService::updateVideoMetadata: Tags to update: " . mb_substr($templated['tags'], 0, 200));
            }

            if (!empty($updateData)) {
                $videoRepo->update($schedule['video_id'], $updateData);
                error_log("SmartQueueService::updateVideoMetadata: Updated video ID {$schedule['video_id']} with template data. Fields: " . implode(', ', array_keys($updateData)));
                
                // Проверяем, что данные действительно обновились
                $updatedVideo = $videoRepo->findById($schedule['video_id']);
                error_log("SmartQueueService::updateVideoMetadata: Verified update - title: " . mb_substr($updatedVideo['title'] ?? 'N/A', 0, 100));
                error_log("SmartQueueService::updateVideoMetadata: Verified update - description: " . mb_substr($updatedVideo['description'] ?? 'N/A', 0, 100));
            } else {
                error_log("SmartQueueService::updateVideoMetadata: No data to update for video ID {$schedule['video_id']}");
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
