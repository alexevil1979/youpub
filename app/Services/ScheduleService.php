<?php

namespace App\Services;

use Core\Service;
use App\Repositories\ScheduleRepository;
use App\Repositories\VideoRepository;

/**
 * Сервис для работы с расписаниями
 */
class ScheduleService extends Service
{
    private ScheduleRepository $scheduleRepo;
    private VideoRepository $videoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->videoRepo = new VideoRepository();
    }

    /**
     * Создать расписание
     */
    public function createSchedule(int $userId, array $data): array
    {
        $errors = [];

        // Валидация
        if (empty($data['video_id'])) {
            $errors['video_id'] = 'Video ID is required';
        } else {
            $video = $this->videoRepo->findById($data['video_id']);
            if (!$video || $video['user_id'] !== $userId) {
                $errors['video_id'] = 'Video not found';
            }
        }

        if (empty($data['platform'])) {
            $errors['platform'] = 'Platform is required';
        } elseif (!in_array($data['platform'], ['youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'both'])) {
            $errors['platform'] = 'Invalid platform';
        }

        if (empty($data['publish_at'])) {
            $errors['publish_at'] = 'Publish date is required';
        } else {
            $publishAt = strtotime($data['publish_at']);
            if ($publishAt === false || $publishAt < time()) {
                $errors['publish_at'] = 'Invalid publish date';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }

        // Создание расписания
        $scheduleId = $this->scheduleRepo->create([
            'user_id' => $userId,
            'video_id' => $data['video_id'],
            'platform' => $data['platform'],
            'publish_at' => date('Y-m-d H:i:s', strtotime($data['publish_at'])),
            'timezone' => $data['timezone'] ?? 'UTC',
            'repeat_type' => $data['repeat_type'] ?? 'once',
            'repeat_until' => !empty($data['repeat_until']) ? date('Y-m-d H:i:s', strtotime($data['repeat_until'])) : null,
            'status' => 'pending',
        ]);

        return [
            'success' => true,
            'message' => 'Schedule created successfully',
            'data' => ['id' => $scheduleId]
        ];
    }

    /**
     * Получить расписания пользователя
     */
    public function getUserSchedules(int $userId): array
    {
        return $this->scheduleRepo->findByUserId($userId, ['publish_at' => 'ASC']);
    }

    /**
     * Получить расписание
     */
    public function getSchedule(int $id, int $userId): ?array
    {
        $schedule = $this->scheduleRepo->findById($id);
        
        if (!$schedule || $schedule['user_id'] !== $userId) {
            return null;
        }

        return $schedule;
    }

    /**
     * Удалить расписание
     */
    public function deleteSchedule(int $id, int $userId): array
    {
        $schedule = $this->getSchedule($id, $userId);
        
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        if ($schedule['status'] === 'processing') {
            return ['success' => false, 'message' => 'Cannot delete schedule in processing'];
        }

        $this->scheduleRepo->delete($id);

        return ['success' => true, 'message' => 'Schedule deleted successfully'];
    }

    /**
     * Приостановить расписание
     */
    public function pauseSchedule(int $id, int $userId): array
    {
        $schedule = $this->getSchedule($id, $userId);
        
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        if ($schedule['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Can only pause pending schedules'];
        }

        $this->scheduleRepo->update($id, ['status' => 'paused']);

        return ['success' => true, 'message' => 'Schedule paused successfully'];
    }

    /**
     * Возобновить расписание
     */
    public function resumeSchedule(int $id, int $userId): array
    {
        $schedule = $this->getSchedule($id, $userId);
        
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        if ($schedule['status'] !== 'paused') {
            return ['success' => false, 'message' => 'Can only resume paused schedules'];
        }

        $this->scheduleRepo->update($id, ['status' => 'pending']);

        return ['success' => true, 'message' => 'Schedule resumed successfully'];
    }

    /**
     * Копировать расписание
     */
    public function duplicateSchedule(int $id, int $userId): array
    {
        $schedule = $this->getSchedule($id, $userId);
        
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        // Создаем копию с новой датой (через 1 день)
        $newPublishAt = date('Y-m-d H:i:s', strtotime($schedule['publish_at'] . ' +1 day'));
        
        $newScheduleData = [
            'user_id' => $userId,
            'video_id' => $schedule['video_id'],
            'content_group_id' => $schedule['content_group_id'] ?? null,
            'platform' => $schedule['platform'],
            'publish_at' => $newPublishAt,
            'timezone' => $schedule['timezone'] ?? 'UTC',
            'repeat_type' => $schedule['repeat_type'] ?? 'once',
            'repeat_until' => $schedule['repeat_until'] ?? null,
            'status' => 'pending',
            'template_id' => $schedule['template_id'] ?? null,
            'schedule_type' => $schedule['schedule_type'] ?? null,
            'integration_id' => $schedule['integration_id'] ?? null,
            'integration_type' => $schedule['integration_type'] ?? null,
        ];

        // Копируем дополнительные поля для умных расписаний
        $smartFields = [
            'interval_minutes', 'batch_count', 'batch_window_hours',
            'random_window_start', 'random_window_end', 'wave_config',
            'weekdays', 'active_hours_start', 'active_hours_end',
            'daily_limit', 'hourly_limit', 'delay_between_posts', 'skip_published'
        ];
        
        foreach ($smartFields as $field) {
            if (isset($schedule[$field])) {
                $newScheduleData[$field] = $schedule[$field];
            }
        }

        $newScheduleId = $this->scheduleRepo->create($newScheduleData);

        return [
            'success' => true,
            'message' => 'Schedule duplicated successfully',
            'data' => ['id' => $newScheduleId]
        ];
    }

    /**
     * Массовое приостановление
     */
    public function bulkPause(array $ids, int $userId): array
    {
        $paused = 0;
        foreach ($ids as $id) {
            $result = $this->pauseSchedule($id, $userId);
            if ($result['success']) {
                $paused++;
            }
        }

        return [
            'success' => true,
            'message' => "Paused {$paused} of " . count($ids) . " schedules",
            'data' => ['paused' => $paused, 'total' => count($ids)]
        ];
    }

    /**
     * Массовое возобновление
     */
    public function bulkResume(array $ids, int $userId): array
    {
        $resumed = 0;
        foreach ($ids as $id) {
            $result = $this->resumeSchedule($id, $userId);
            if ($result['success']) {
                $resumed++;
            }
        }

        return [
            'success' => true,
            'message' => "Resumed {$resumed} of " . count($ids) . " schedules",
            'data' => ['resumed' => $resumed, 'total' => count($ids)]
        ];
    }

    /**
     * Массовое удаление
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        $deleted = 0;
        foreach ($ids as $id) {
            $result = $this->deleteSchedule($id, $userId);
            if ($result['success']) {
                $deleted++;
            }
        }

        return [
            'success' => true,
            'message' => "Deleted {$deleted} of " . count($ids) . " schedules",
            'data' => ['deleted' => $deleted, 'total' => count($ids)]
        ];
    }
}
