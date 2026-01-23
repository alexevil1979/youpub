<?php

namespace App\Modules\ContentGroups\Services;

use Core\Service;
use App\Repositories\ScheduleRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupFileRepository;

/**
 * Сервис для работы с гибкими расписаниями
 */
class ScheduleEngineService extends Service
{
    private ScheduleRepository $scheduleRepo;
    private ContentGroupRepository $groupRepo;
    private ContentGroupFileRepository $fileRepo;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleRepo = new ScheduleRepository();
        $this->groupRepo = new ContentGroupRepository();
        $this->fileRepo = new ContentGroupFileRepository();
    }

    /**
     * Проверить, готово ли расписание к публикации
     */
    public function isScheduleReady(array $schedule): bool
    {
        $now = time();
        $publishAt = strtotime($schedule['publish_at']);

        // Базовая проверка времени
        if ($publishAt > $now) {
            return false;
        }

        // Проверка типа расписания
        $scheduleType = $schedule['schedule_type'] ?? 'fixed';
        
        switch ($scheduleType) {
            case 'fixed':
                return $this->checkFixedSchedule($schedule);
            
            case 'interval':
                return $this->checkIntervalSchedule($schedule);
            
            case 'batch':
                return $this->checkBatchSchedule($schedule);
            
            case 'random':
                return $this->checkRandomSchedule($schedule);
            
            case 'wave':
                return $this->checkWaveSchedule($schedule);
            
            default:
                return $publishAt <= $now;
        }
    }

    /**
     * Проверить фиксированное расписание
     */
    private function checkFixedSchedule(array $schedule): bool
    {
        $now = time();
        
        // Проверяем наличие времени публикации
        if (empty($schedule['publish_at'])) {
            return false;
        }
        
        $publishAt = strtotime($schedule['publish_at']);

        // Для фиксированных расписаний время должно наступить
        if ($publishAt > $now) {
            return false;
        }

        // Проверка дней недели
        if (!empty($schedule['weekdays'])) {
            $currentDay = (int)date('N'); // 1-7 (пн-вс)
            $allowedDays = array_map('intval', explode(',', $schedule['weekdays']));
            if (!in_array($currentDay, $allowedDays)) {
                return false;
            }
        }

        // Проверка часов активности
        if (!empty($schedule['active_hours_start']) && !empty($schedule['active_hours_end'])) {
            $currentTime = date('H:i:s');
            if ($currentTime < $schedule['active_hours_start'] || $currentTime > $schedule['active_hours_end']) {
                return false;
            }
        }

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить интервальное расписание
     */
    private function checkIntervalSchedule(array $schedule): bool
    {
        if (empty($schedule['interval_minutes'])) {
            return false;
        }

        $now = time();
        $publishAt = strtotime($schedule['publish_at']);
        $interval = $schedule['interval_minutes'] * 60;

        // Проверяем, прошло ли достаточно времени с последней публикации
        $lastPublication = $this->getLastPublicationTime($schedule);
        if ($lastPublication && ($now - $lastPublication) < $interval) {
            return false;
        }

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить пакетное расписание
     */
    private function checkBatchSchedule(array $schedule): bool
    {
        if (empty($schedule['batch_count']) || empty($schedule['batch_window_hours'])) {
            return false;
        }

        // Проверяем, сколько уже опубликовано в текущем окне
        $publishedInWindow = $this->getPublishedCountInWindow($schedule, $schedule['batch_window_hours']);
        
        if ($publishedInWindow >= $schedule['batch_count']) {
            return false;
        }

        // Проверяем задержку между публикациями
        if (!empty($schedule['delay_between_posts'])) {
            $lastPublication = $this->getLastPublicationTime($schedule);
            if ($lastPublication && (time() - $lastPublication) < ($schedule['delay_between_posts'] * 60)) {
                return false;
            }
        }

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить случайное расписание
     */
    private function checkRandomSchedule(array $schedule): bool
    {
        if (empty($schedule['random_window_start']) || empty($schedule['random_window_end'])) {
            return false;
        }

        $currentTime = date('H:i:s');
        if ($currentTime < $schedule['random_window_start'] || $currentTime > $schedule['random_window_end']) {
            return false;
        }

        // Случайная проверка (50% вероятность в каждый момент)
        // Можно сделать более сложную логику
        if (rand(1, 100) < 50) {
            return false;
        }

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить волновое расписание
     */
    private function checkWaveSchedule(array $schedule): bool
    {
        if (empty($schedule['wave_config'])) {
            return false;
        }

        $config = json_decode($schedule['wave_config'], true);
        if (!$config) {
            return false;
        }

        $currentHour = (int)date('H');
        $currentDay = (int)date('N');

        // Проверяем активные периоды
        if (!empty($config['active_periods'])) {
            $isActive = false;
            foreach ($config['active_periods'] as $period) {
                if ($currentHour >= $period['start'] && $currentHour < $period['end']) {
                    $isActive = true;
                    break;
                }
            }
            if (!$isActive) {
                return false;
            }
        }

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить лимиты публикаций
     */
    private function checkLimits(array $schedule): bool
    {
        // Дневной лимит
        if (!empty($schedule['daily_limit'])) {
            $publishedToday = $this->getPublishedCountToday($schedule);
            if ($publishedToday >= $schedule['daily_limit']) {
                return false;
            }
        }

        // Часовой лимит
        if (!empty($schedule['hourly_limit'])) {
            $publishedThisHour = $this->getPublishedCountThisHour($schedule);
            if ($publishedThisHour >= $schedule['hourly_limit']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Получить время последней публикации
     */
    private function getLastPublicationTime(array $schedule): ?int
    {
        $stmt = $this->db->prepare("
            SELECT MAX(p.published_at) as last_published
            FROM publications p
            WHERE p.user_id = ? AND p.platform = ?
            AND p.status = 'success'
            AND DATE(p.published_at) = CURDATE()
        ");
        $stmt->execute([$schedule['user_id'], $schedule['platform']]);
        $result = $stmt->fetch();
        
        return $result && $result['last_published'] ? strtotime($result['last_published']) : null;
    }

    /**
     * Получить количество публикаций сегодня
     */
    private function getPublishedCountToday(array $schedule): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM publications
            WHERE user_id = ? AND platform = ? AND status = 'success'
            AND DATE(published_at) = CURDATE()
        ");
        $stmt->execute([$schedule['user_id'], $schedule['platform']]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Получить количество публикаций в текущем часе
     */
    private function getPublishedCountThisHour(array $schedule): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM publications
            WHERE user_id = ? AND platform = ? AND status = 'success'
            AND DATE_FORMAT(published_at, '%Y-%m-%d %H') = DATE_FORMAT(NOW(), '%Y-%m-%d %H')
        ");
        $stmt->execute([$schedule['user_id'], $schedule['platform']]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Получить количество публикаций в окне времени
     */
    private function getPublishedCountInWindow(array $schedule, int $windowHours): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM publications
            WHERE user_id = ? AND platform = ? AND status = 'success'
            AND published_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ");
        $stmt->execute([$schedule['user_id'], $schedule['platform'], $windowHours]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Получить следующее время публикации для расписания
     */
    public function getNextPublishTime(array $schedule): ?string
    {
        $scheduleType = $schedule['schedule_type'] ?? 'fixed';
        
        switch ($scheduleType) {
            case 'interval':
                $interval = ($schedule['interval_minutes'] ?? 60) * 60;
                return date('Y-m-d H:i:s', time() + $interval);
            
            case 'batch':
                $delay = ($schedule['delay_between_posts'] ?? 30) * 60;
                return date('Y-m-d H:i:s', time() + $delay);
            
            case 'random':
                // Случайное время в пределах окна
                $start = strtotime($schedule['random_window_start']);
                $end = strtotime($schedule['random_window_end']);
                $random = rand($start, $end);
                return date('Y-m-d H:i:s', $random);
            
            default:
                return null;
        }
    }
}
