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

        // Проверка для нескольких точек времени
        $dailyTimePoints = null;
        $dailyPointsStartDate = null;
        $dailyPointsEndDate = null;
        
        if (!empty($data['daily_time_points']) && is_array($data['daily_time_points'])) {
            // Фильтруем пустые значения
            $timePoints = array_filter($data['daily_time_points'], function($time) {
                return !empty(trim($time));
            });
            
            if (empty($timePoints)) {
                $errors['daily_time_points'] = 'At least one time point is required';
            } else {
                // Валидация формата времени
                foreach ($timePoints as $time) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                        $errors['daily_time_points'] = 'Invalid time format. Use HH:MM';
                        break;
                    }
                }
                if (empty($errors['daily_time_points'])) {
                    $dailyTimePoints = json_encode(array_values($timePoints));
                    $dailyPointsStartDate = $data['daily_points_start_date'] ?? null;
                    $dailyPointsEndDate = $data['daily_points_end_date'] ?? null;
                    
                    if (empty($dailyPointsStartDate)) {
                        $errors['daily_points_start_date'] = 'Start date is required for multiple time points';
                    }
                }
            }
        } else {
            // Обычная проверка одной даты
            if (empty($data['publish_at'])) {
                $errors['publish_at'] = 'Publish date is required';
            } else {
                $publishAt = strtotime($data['publish_at']);
                if ($publishAt === false || $publishAt < time()) {
                    $errors['publish_at'] = 'Invalid publish date';
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }

        // Если указаны несколько точек времени, создаем несколько расписаний
        if ($dailyTimePoints && $dailyPointsStartDate) {
            $timePointsArray = json_decode($dailyTimePoints, true);
            $startDate = strtotime($dailyPointsStartDate);
            $endDate = $dailyPointsEndDate ? strtotime($dailyPointsEndDate . ' 23:59:59') : null;
            
            if ($startDate === false) {
                return ['success' => false, 'message' => 'Invalid start date', 'errors' => ['daily_points_start_date' => 'Invalid date format']];
            }
            
            $createdSchedules = [];
            $currentDate = $startDate;
            
            // Создаем расписания для каждого дня в диапазоне
            while ($endDate === null || $currentDate <= $endDate) {
                $dateStr = date('Y-m-d', $currentDate);
                
                // Создаем расписание для каждой точки времени
                foreach ($timePointsArray as $timePoint) {
                    $publishDateTime = $dateStr . ' ' . $timePoint . ':00';
                    
                    $scheduleId = $this->scheduleRepo->create([
                        'user_id' => $userId,
                        'video_id' => $data['video_id'],
                        'platform' => $data['platform'],
                        'publish_at' => $publishDateTime,
                        'timezone' => $data['timezone'] ?? 'UTC',
                        'repeat_type' => 'once',
                        'repeat_until' => null,
                        'status' => 'pending',
                        'daily_time_points' => $dailyTimePoints,
                    ]);
                    
                    $createdSchedules[] = $scheduleId;
                }
                
                // Переходим к следующему дню
                $currentDate = strtotime('+1 day', $currentDate);
            }
            
            return [
                'success' => true,
                'message' => 'Schedules created successfully (' . count($createdSchedules) . ' schedules)',
                'data' => ['ids' => $createdSchedules, 'count' => count($createdSchedules)]
            ];
        } else {
            // Обычное создание одного расписания
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
     * Обновить расписание
     */
    public function updateSchedule(int $id, int $userId, array $data): array
    {
        $schedule = $this->getSchedule($id, $userId);
        
        if (!$schedule) {
            return ['success' => false, 'message' => 'Schedule not found'];
        }

        // Нельзя редактировать опубликованные или обрабатываемые расписания
        if (in_array($schedule['status'], ['published', 'processing'])) {
            return ['success' => false, 'message' => 'Cannot edit published or processing schedules'];
        }

        $errors = [];

        // Валидация
        if (!empty($data['video_id'])) {
            $video = $this->videoRepo->findById($data['video_id']);
            if (!$video || $video['user_id'] !== $userId) {
                $errors['video_id'] = 'Video not found';
            }
        }

        if (!empty($data['platform'])) {
            if (!in_array($data['platform'], ['youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'both'])) {
                $errors['platform'] = 'Invalid platform';
            }
        }

        if (!empty($data['publish_at'])) {
            $publishAt = strtotime($data['publish_at']);
            if ($publishAt === false) {
                $errors['publish_at'] = 'Invalid publish date';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }

        // Подготавливаем данные для обновления
        $updateData = [];
        
        if (isset($data['video_id'])) {
            $updateData['video_id'] = $data['video_id'];
        }
        
        if (isset($data['platform'])) {
            $updateData['platform'] = $data['platform'];
        }
        
        if (isset($data['publish_at'])) {
            $updateData['publish_at'] = date('Y-m-d H:i:s', strtotime($data['publish_at']));
        }
        
        if (isset($data['timezone'])) {
            $updateData['timezone'] = $data['timezone'];
        }
        
        if (isset($data['repeat_type'])) {
            $updateData['repeat_type'] = $data['repeat_type'];
        }
        
        if (isset($data['repeat_until'])) {
            $updateData['repeat_until'] = !empty($data['repeat_until']) ? date('Y-m-d H:i:s', strtotime($data['repeat_until'])) : null;
        }

        $this->scheduleRepo->update($id, $updateData);

        return [
            'success' => true,
            'message' => 'Schedule updated successfully',
            'data' => ['id' => $id]
        ];
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

        // Разрешаем удаление расписаний 'processing' (включая зависшие)
        // Если расписание в процессе обработки, сначала меняем статус на 'failed'
        if ($schedule['status'] === 'processing') {
            // Проверяем, не зависло ли расписание (старше 10 минут)
            $createdAt = strtotime($schedule['created_at'] ?? 'now');
            $now = time();
            $minutesOld = ($now - $createdAt) / 60;
            
            if ($minutesOld > 10) {
                // Зависшее расписание - можно удалить
                error_log("Deleting stuck processing schedule ID: {$id} (created {$minutesOld} minutes ago)");
            } else {
                // Активное расписание - предупреждаем, но разрешаем удаление
                error_log("Deleting active processing schedule ID: {$id} (user requested)");
            }
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

        // Можно приостановить только pending расписания
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

        // Можно возобновить paused, published, failed, cancelled расписания
        if (!in_array($schedule['status'], ['paused', 'published', 'failed', 'cancelled'])) {
            return ['success' => false, 'message' => 'Cannot resume schedule with status: ' . $schedule['status']];
        }

        // Если расписание уже опубликовано или завершено, создаем новое на основе старого
        if (in_array($schedule['status'], ['published', 'failed', 'cancelled'])) {
            // Проверяем, не прошла ли дата публикации
            $publishAt = strtotime($schedule['publish_at']);
            $now = time();
            
            if ($publishAt <= $now) {
                // Дата прошла, создаем новое расписание на завтра
                $newPublishAt = date('Y-m-d H:i:s', strtotime('+1 day', $publishAt));
            } else {
                // Дата еще не прошла, используем старую
                $newPublishAt = $schedule['publish_at'];
            }
            
            // Обновляем существующее расписание
            $this->scheduleRepo->update($id, [
                'status' => 'pending',
                'publish_at' => $newPublishAt
            ]);
        } else {
            // Просто меняем статус с paused на pending
            $this->scheduleRepo->update($id, ['status' => 'pending']);
        }

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
