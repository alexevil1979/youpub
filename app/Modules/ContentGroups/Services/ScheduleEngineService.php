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
        // Проверяем статус расписания
        if (($schedule['status'] ?? '') === 'paused') {
            return false;
        }
        
        $now = time();
        
        // Проверяем наличие времени публикации
        if (empty($schedule['publish_at'])) {
            return false;
        }
        
        $publishAt = strtotime($schedule['publish_at']);

        // Проверка типа расписания
        $scheduleType = $schedule['schedule_type'] ?? 'fixed';
        
        // Для фиксированных расписаний время должно наступить
        if ($scheduleType === 'fixed' && $publishAt > $now) {
            return false;
        }
        
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
            error_log("ScheduleEngineService::checkFixedSchedule: No publish_at for schedule ID " . ($schedule['id'] ?? 'unknown'));
            return false;
        }
        
        $publishAt = strtotime($schedule['publish_at']);

        // Для фиксированных расписаний время должно наступить
        if ($publishAt > $now) {
            error_log("ScheduleEngineService::checkFixedSchedule: Publish time not reached for schedule ID " . ($schedule['id'] ?? 'unknown') . ", publish_at: " . $schedule['publish_at'] . ", now: " . date('Y-m-d H:i:s', $now));
            return false;
        }

        // Проверка дней недели - проверяем день, когда должно было быть опубликовано
        if (!empty($schedule['weekdays'])) {
            $publishDay = (int)date('N', $publishAt); // День недели времени публикации
            $allowedDays = array_map('intval', explode(',', $schedule['weekdays']));
            if (!in_array($publishDay, $allowedDays)) {
                error_log("ScheduleEngineService::checkFixedSchedule: Publish day {$publishDay} not in allowed days for schedule ID " . ($schedule['id'] ?? 'unknown'));
                return false;
            }
        }

        // Для фиксированных расписаний НЕ проверяем активные часы при публикации,
        // так как время публикации уже было рассчитано с учетом активных часов.
        // Активные часы используются только при расчете времени публикации, а не при проверке готовности.
        // Если время публикации наступило, публикуем независимо от текущего времени.

        return $this->checkLimits($schedule);
    }

    /**
     * Проверить интервальное расписание
     */
    private function checkIntervalSchedule(array $schedule): bool
    {
        if (empty($schedule['interval_minutes'])) {
            error_log("ScheduleEngineService::checkIntervalSchedule: No interval_minutes for schedule ID " . ($schedule['id'] ?? 'unknown'));
            return false;
        }

        $now = time();
        $interval = (int)$schedule['interval_minutes'] * 60;
        
        // Для интервальных расписаний поле publish_at используется как "следующее время публикации".
        // Если оно задано и ещё не наступило — публиковать рано.
        if (!empty($schedule['publish_at'])) {
            $publishAt = strtotime($schedule['publish_at']);
            
            // Если время публикации наступило (или прошло), можно публиковать
            // Не требуем точного совпадения, так как worker может запускаться с небольшой задержкой
            if ($publishAt <= $now) {
                // Время наступило - можно публиковать
                error_log("ScheduleEngineService::checkIntervalSchedule: Publish time reached for schedule ID " . ($schedule['id'] ?? 'unknown') . ", publish_at: " . $schedule['publish_at'] . ", now: " . date('Y-m-d H:i:s', $now) . ", overdue by: " . ($now - $publishAt) . " seconds");
                // Проверяем только лимиты, не проверяем время с последней публикации
                // так как publish_at уже учитывает интервал
                return $this->checkLimits($schedule);
            } else {
                // Время еще не наступило
                $timeUntilPublish = $publishAt - $now;
                error_log("ScheduleEngineService::checkIntervalSchedule: Publish time not reached for schedule ID " . ($schedule['id'] ?? 'unknown') . ", publish_at: " . $schedule['publish_at'] . ", now: " . date('Y-m-d H:i:s', $now) . ", time until: {$timeUntilPublish}s");
                return false;
            }
        } else {
            // Если publish_at не задано, используем проверку последней публикации как fallback
            error_log("ScheduleEngineService::checkIntervalSchedule: No publish_at for schedule ID " . ($schedule['id'] ?? 'unknown') . ", using last publication time check");
            $lastPublication = $this->getLastPublicationTimeForSchedule($schedule);
            if ($lastPublication && ($now - $lastPublication) < $interval) {
                error_log("ScheduleEngineService::checkIntervalSchedule: Not enough time since last publication for schedule ID " . ($schedule['id'] ?? 'unknown') . ", time since: " . ($now - $lastPublication) . "s, required: {$interval}s");
                return false;
            }
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
     * Получить время последней публикации для конкретного расписания
     * Ищет публикации, созданные через это расписание (через временные расписания)
     */
    private function getLastPublicationTimeForSchedule(array $schedule): ?int
    {
        // Пытаемся найти последнюю публикацию через временные расписания этого расписания
        // Временные расписания создаются в SmartQueueService и имеют parent_schedule_id
        $stmt = $this->db->prepare("
            SELECT MAX(p.published_at) as last_published
            FROM publications p
            JOIN schedules temp_schedule ON temp_schedule.id = p.schedule_id
            WHERE temp_schedule.parent_schedule_id = ?
            AND p.status = 'success'
            AND p.published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$schedule['id']]);
        $result = $stmt->fetch();
        
        if ($result && $result['last_published']) {
            return strtotime($result['last_published']);
        }
        
        // Fallback: ищем по user_id и platform (для обратной совместимости)
        $stmt = $this->db->prepare("
            SELECT MAX(p.published_at) as last_published
            FROM publications p
            WHERE p.user_id = ? AND p.platform = ?
            AND p.status = 'success'
            AND p.published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$schedule['user_id'], $schedule['platform']]);
        $result = $stmt->fetch();
        
        return $result && $result['last_published'] ? strtotime($result['last_published']) : null;
    }
    
    /**
     * Получить время последней публикации (старый метод для обратной совместимости)
     */
    private function getLastPublicationTime(array $schedule): ?int
    {
        return $this->getLastPublicationTimeForSchedule($schedule);
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
                $intervalMinutes = (int)($schedule['interval_minutes'] ?? 60);
                $interval = $intervalMinutes * 60;
                $now = time();
                
                // Для интервальных расписаний вычисляем следующее время от текущего момента
                // с учетом интервала
                $nextTime = $now + $interval;
                
                error_log("ScheduleEngineService::getNextPublishTime: Interval schedule ID " . ($schedule['id'] ?? 'unknown') . ", interval: {$intervalMinutes} minutes, next time: " . date('Y-m-d H:i:s', $nextTime));
                
                return date('Y-m-d H:i:s', $nextTime);
            
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
