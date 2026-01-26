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

        $videoId = isset($data['video_id']) ? (int)$data['video_id'] : 0;
        if ($videoId <= 0) {
            $errors['video_id'] = 'Video ID is required';
        } else {
            $video = $this->videoRepo->findById($videoId);
            if (!$video || $video['user_id'] !== $userId) {
                $errors['video_id'] = 'Video not found';
            }
        }

        $platform = $data['platform'] ?? '';
        $allowedPlatforms = ['youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'both'];
        if ($platform === '' || !in_array($platform, $allowedPlatforms, true)) {
            $errors['platform'] = 'Invalid platform';
        }

        $timezone = $this->normalizeTimezone($data['timezone'] ?? 'UTC');
        if ($timezone === null) {
            $errors['timezone'] = 'Invalid timezone';
        }

        $dailyTimePoints = null;
        $dailyPointsStartDate = $data['daily_points_start_date'] ?? null;
        $dailyPointsEndDate = $data['daily_points_end_date'] ?? null;

        if (!empty($data['daily_time_points']) && is_array($data['daily_time_points'])) {
            $timePoints = array_values(array_filter($data['daily_time_points'], function ($time) {
                return !empty(trim((string)$time));
            }));

            if (empty($timePoints)) {
                $errors['daily_time_points'] = 'At least one time point is required';
            } else {
                foreach ($timePoints as $time) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', (string)$time)) {
                        $errors['daily_time_points'] = 'Invalid time format. Use HH:MM';
                        break;
                    }
                }
                if (empty($errors['daily_time_points'])) {
                    $dailyTimePoints = json_encode($timePoints, JSON_UNESCAPED_UNICODE);
                    if (empty($dailyPointsStartDate)) {
                        $errors['daily_points_start_date'] = 'Start date is required for multiple time points';
                    }
                }
            }
        }

        $publishAtValue = $data['publish_at'] ?? null;
        $publishAt = null;
        if ($dailyTimePoints === null) {
            if (empty($publishAtValue)) {
                $errors['publish_at'] = 'Publish date is required';
            } else {
                $publishAt = $this->parseDateTime($publishAtValue, $timezone ?? 'UTC');
                if ($publishAt === null) {
                    $errors['publish_at'] = 'Invalid publish date';
                } elseif ($publishAt->getTimestamp() < time()) {
                    $errors['publish_at'] = 'Publish date must be in the future';
                }
            }
        }

        $repeatUntilValue = $data['repeat_until'] ?? null;
        $repeatUntil = null;
        if (!empty($repeatUntilValue)) {
            $repeatUntil = $this->parseDateTime($repeatUntilValue, $timezone ?? 'UTC');
            if ($repeatUntil === null) {
                $errors['repeat_until'] = 'Invalid repeat until date';
            } elseif ($publishAt && $repeatUntil < $publishAt) {
                $errors['repeat_until'] = 'Repeat until must be after publish date';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }

        $db = \Core\Database::getInstance();
        $db->beginTransaction();
        try {
            if ($dailyTimePoints !== null && $dailyPointsStartDate) {
                $timePointsArray = json_decode($dailyTimePoints, true);
                $startDate = $this->parseDateTime($dailyPointsStartDate, $timezone ?? 'UTC');
                if ($startDate === null) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Invalid start date',
                        'errors' => ['daily_points_start_date' => 'Invalid date format']
                    ];
                }
                if ($startDate->getTimestamp() < strtotime('today')) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Start date must be today or later',
                        'errors' => ['daily_points_start_date' => 'Start date must be today or later']
                    ];
                }

                $endDate = null;
                if (!empty($dailyPointsEndDate)) {
                    $endDate = $this->parseDateTime($dailyPointsEndDate . ' 23:59:59', $timezone ?? 'UTC');
                    if ($endDate === null) {
                        $db->rollBack();
                        return [
                            'success' => false,
                            'message' => 'Invalid end date',
                            'errors' => ['daily_points_end_date' => 'Invalid date format']
                        ];
                    }
                }

                $createdSchedules = [];
                $currentDate = clone $startDate;

                while ($endDate === null || $currentDate <= $endDate) {
                    $dateStr = $currentDate->format('Y-m-d');
                    foreach ($timePointsArray as $timePoint) {
                        $publishDateTime = $dateStr . ' ' . $timePoint . ':00';
                        $publishAtDate = $this->parseDateTime($publishDateTime, $timezone ?? 'UTC');
                        if ($publishAtDate === null || $publishAtDate->getTimestamp() <= time()) {
                            continue;
                        }
                        $scheduleId = $this->scheduleRepo->create([
                            'user_id' => $userId,
                            'video_id' => $videoId,
                            'platform' => $platform,
                            'publish_at' => $publishDateTime,
                            'timezone' => $timezone,
                            'repeat_type' => 'once',
                            'repeat_until' => null,
                            'status' => 'pending',
                            'daily_time_points' => $dailyTimePoints,
                        ]);
                        $createdSchedules[] = $scheduleId;
                    }
                    $currentDate->modify('+1 day');
                }

                if (empty($createdSchedules)) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'Все точки времени находятся в прошлом',
                        'errors' => ['daily_time_points' => 'All time points are in the past']
                    ];
                }

                $db->commit();
                return [
                    'success' => true,
                    'message' => 'Schedules created successfully (' . count($createdSchedules) . ' schedules)',
                    'data' => ['ids' => $createdSchedules, 'count' => count($createdSchedules)]
                ];
            }

            $scheduleId = $this->scheduleRepo->create([
                'user_id' => $userId,
                'video_id' => $videoId,
                'platform' => $platform,
                'publish_at' => $publishAt ? $publishAt->format('Y-m-d H:i:s') : null,
                'timezone' => $timezone,
                'repeat_type' => $data['repeat_type'] ?? 'once',
                'repeat_until' => $repeatUntil ? $repeatUntil->format('Y-m-d H:i:s') : null,
                'status' => 'pending',
            ]);
            $db->commit();

            return [
                'success' => true,
                'message' => 'Schedule created successfully',
                'data' => ['id' => $scheduleId]
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('ScheduleService::createSchedule error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create schedule'];
        }
    }

    /**
     * Получить расписания пользователя
     */
    public function getUserSchedules(int $userId): array
    {
        return $this->scheduleRepo->findByUserId($userId, ['publish_at' => 'DESC']);
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

        $updateData = [];
        if (isset($data['video_id'])) {
            $videoId = (int)$data['video_id'];
            if ($videoId <= 0) {
                $errors['video_id'] = 'Video ID is required';
            } else {
                $video = $this->videoRepo->findById($videoId);
                if (!$video || $video['user_id'] !== $userId) {
                    $errors['video_id'] = 'Video not found';
                } else {
                    $updateData['video_id'] = $videoId;
                }
            }
        }

        if (isset($data['platform'])) {
            $platform = $data['platform'];
            $allowedPlatforms = ['youtube', 'telegram', 'tiktok', 'instagram', 'pinterest', 'both'];
            if (!in_array($platform, $allowedPlatforms, true)) {
                $errors['platform'] = 'Invalid platform';
            } else {
                $updateData['platform'] = $platform;
            }
        }

        $timezone = null;
        if (isset($data['timezone'])) {
            $timezone = $this->normalizeTimezone($data['timezone']);
            if ($timezone === null) {
                $errors['timezone'] = 'Invalid timezone';
            } else {
                $updateData['timezone'] = $timezone;
            }
        } else {
            $timezone = $schedule['timezone'] ?? 'UTC';
        }

        if (isset($data['publish_at'])) {
            $publishAt = $this->parseDateTime($data['publish_at'], $timezone ?? 'UTC');
            if ($publishAt === null) {
                $errors['publish_at'] = 'Invalid publish date';
            } elseif ($publishAt->getTimestamp() < time()) {
                $errors['publish_at'] = 'Publish date must be in the future';
            } else {
                $updateData['publish_at'] = $publishAt->format('Y-m-d H:i:s');
            }
        }

        if (isset($data['repeat_type'])) {
            $updateData['repeat_type'] = $data['repeat_type'];
        }

        if (array_key_exists('repeat_until', $data)) {
            if (!empty($data['repeat_until'])) {
                $repeatUntil = $this->parseDateTime($data['repeat_until'], $timezone ?? 'UTC');
                if ($repeatUntil === null) {
                    $errors['repeat_until'] = 'Invalid repeat until date';
                } else {
                    $updateData['repeat_until'] = $repeatUntil->format('Y-m-d H:i:s');
                }
            } else {
                $updateData['repeat_until'] = null;
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'message' => 'Validation failed', 'errors' => $errors];
        }

        if (empty($updateData)) {
            return ['success' => false, 'message' => 'No data to update'];
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

        $basePublishAt = $schedule['publish_at'] ?? date('Y-m-d H:i:s');
        $newPublishAt = date('Y-m-d H:i:s', strtotime($basePublishAt . ' +1 day'));
        
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
        $results = [];
        foreach ($ids as $id) {
            $scheduleId = (int)$id;
            if ($scheduleId <= 0) {
                $results[$id] = ['success' => false, 'message' => 'Invalid schedule ID'];
                continue;
            }
            $results[$scheduleId] = $this->pauseSchedule($scheduleId, $userId);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failedCount = count($results) - $successCount;

        return [
            'success' => $successCount > 0,
            'message' => "Paused {$successCount} of " . count($results) . " schedules",
            'data' => [
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ]
        ];
    }

    /**
     * Массовое возобновление
     */
    public function bulkResume(array $ids, int $userId): array
    {
        $results = [];
        foreach ($ids as $id) {
            $scheduleId = (int)$id;
            if ($scheduleId <= 0) {
                $results[$id] = ['success' => false, 'message' => 'Invalid schedule ID'];
                continue;
            }
            $results[$scheduleId] = $this->resumeSchedule($scheduleId, $userId);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failedCount = count($results) - $successCount;

        return [
            'success' => $successCount > 0,
            'message' => "Resumed {$successCount} of " . count($results) . " schedules",
            'data' => [
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ]
        ];
    }

    /**
     * Массовое удаление
     */
    public function bulkDelete(array $ids, int $userId): array
    {
        $results = [];
        foreach ($ids as $id) {
            $scheduleId = (int)$id;
            if ($scheduleId <= 0) {
                $results[$id] = ['success' => false, 'message' => 'Invalid schedule ID'];
                continue;
            }
            $results[$scheduleId] = $this->deleteSchedule($scheduleId, $userId);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failedCount = count($results) - $successCount;

        return [
            'success' => $successCount > 0,
            'message' => "Deleted {$successCount} of " . count($results) . " schedules",
            'data' => [
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'failed' => $failedCount
                ]
            ]
        ];
    }

    private function normalizeTimezone(string $timezone): ?string
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return null;
        }
        return $timezone;
    }

    private function parseDateTime(string $value, string $timezone): ?\DateTime
    {
        try {
            $tz = new \DateTimeZone($timezone);
            return new \DateTime($value, $tz);
        } catch (\Exception $e) {
            return null;
        }
    }
}
