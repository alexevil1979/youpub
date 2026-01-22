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
}
