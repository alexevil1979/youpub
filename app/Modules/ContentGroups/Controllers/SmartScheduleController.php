<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Services\ScheduleService;
use App\Modules\ContentGroups\Services\GroupService;
use App\Modules\ContentGroups\Services\TemplateService;

/**
 * Контроллер для управления умными расписаниями
 */
class SmartScheduleController extends Controller
{
    private ScheduleService $scheduleService;
    private GroupService $groupService;
    private TemplateService $templateService;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleService = new ScheduleService();
        $this->groupService = new GroupService();
        $this->templateService = new TemplateService();
    }

    /**
     * Показать форму создания умного расписания
     */
    public function showCreate(): void
    {
        $userId = $_SESSION['user_id'];
        $groups = $this->groupService->getUserGroups($userId);
        $templates = $this->templateService->getUserTemplates($userId, true);
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        if (!isset($groups)) {
            $groups = [];
        }
        if (!isset($templates)) {
            $templates = [];
        }
        
        include __DIR__ . '/../../../../views/content_groups/schedules/create.php';
    }

    /**
     * Создать умное расписание
     */
    public function create(): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $_SESSION['error'] = 'Необходима авторизация';
                header('Location: /content-groups/schedules/create');
                exit;
            }
            
            error_log("SmartScheduleController::create: Starting for user {$userId}");
            
            // Обработка weekdays из массива checkbox
            $weekdaysArray = $_POST['weekdays'] ?? [];
            $weekdays = null;
            if (!empty($weekdaysArray) && is_array($weekdaysArray)) {
                $weekdays = implode(',', array_map('intval', $weekdaysArray));
            }
            
            $data = [
                'user_id' => $userId,
                'content_group_id' => $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null,
                'video_id' => $this->getParam('video_id') ? (int)$this->getParam('video_id') : null,
                'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
                'platform' => $this->getParam('platform', 'youtube'),
                'schedule_type' => $this->getParam('schedule_type', 'fixed'),
                'publish_at' => $this->getParam('publish_at') ? date('Y-m-d H:i:s', strtotime($this->getParam('publish_at'))) : date('Y-m-d H:i:s'),
                'interval_minutes' => $this->getParam('interval_minutes') ? (int)$this->getParam('interval_minutes') : null,
                'batch_count' => $this->getParam('batch_count') ? (int)$this->getParam('batch_count') : null,
                'batch_window_hours' => $this->getParam('batch_window_hours') ? (int)$this->getParam('batch_window_hours') : null,
                'random_window_start' => $this->getParam('random_window_start') ?: null,
                'random_window_end' => $this->getParam('random_window_end') ?: null,
                'weekdays' => $weekdays ?: null,
                'active_hours_start' => $this->getParam('active_hours_start') ?: null,
                'active_hours_end' => $this->getParam('active_hours_end') ?: null,
                'daily_limit' => $this->getParam('daily_limit') ? (int)$this->getParam('daily_limit') : null,
                'hourly_limit' => $this->getParam('hourly_limit') ? (int)$this->getParam('hourly_limit') : null,
                'delay_between_posts' => $this->getParam('delay_between_posts') ? (int)$this->getParam('delay_between_posts') : null,
                'skip_published' => $this->getParam('skip_published', '1') === '1',
                'status' => 'pending',
            ];
            
            error_log("SmartScheduleController::create: Data prepared - content_group_id: {$data['content_group_id']}, video_id: {$data['video_id']}, platform: {$data['platform']}, schedule_type: {$data['schedule_type']}");

        // Валидация
        if (empty($data['content_group_id']) && empty($data['video_id'])) {
            $_SESSION['error'] = 'Необходимо указать группу или видео';
            header('Location: /content-groups/schedules/create');
            exit;
        }

        // Обработка нескольких точек времени для fixed типа
        $dailyTimePoints = null;
        $dailyPointsStartDate = null;
        $dailyPointsEndDate = null;
        
        if ($data['schedule_type'] === 'fixed') {
            $fixedTimeMode = $this->getParam('fixed_time_mode', 'single');
            
            if ($fixedTimeMode === 'multiple') {
                // Режим нескольких точек времени
                $timePointsArray = $_POST['daily_time_points'] ?? [];
                $timePoints = array_filter($timePointsArray, function($time) {
                    return !empty(trim($time));
                });
                
                if (empty($timePoints)) {
                    $_SESSION['error'] = 'Укажите хотя бы одну точку времени';
                    header('Location: /content-groups/schedules/create');
                    exit;
                }
                
                // Валидация формата времени
                foreach ($timePoints as $time) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                        $_SESSION['error'] = 'Неверный формат времени. Используйте HH:MM';
                        header('Location: /content-groups/schedules/create');
                        exit;
                    }
                }
                
                $dailyTimePoints = json_encode(array_values($timePoints));
                $dailyPointsStartDate = $this->getParam('fixed_start_date');
                $dailyPointsEndDate = $this->getParam('fixed_end_date');
                
                if (empty($dailyPointsStartDate)) {
                    $_SESSION['error'] = 'Укажите начальную дату';
                    header('Location: /content-groups/schedules/create');
                    exit;
                }
            } else {
                // Обычный режим одной точки времени
                if (empty($data['publish_at'])) {
                    $_SESSION['error'] = 'Укажите дату и время публикации';
                    header('Location: /content-groups/schedules/create');
                    exit;
                }
            }
        }

        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        
        // Если указаны несколько точек времени, создаем несколько расписаний
        if ($dailyTimePoints && $dailyPointsStartDate) {
            $timePointsArray = json_decode($dailyTimePoints, true);
            $startDate = strtotime($dailyPointsStartDate);
            $endDate = $dailyPointsEndDate ? strtotime($dailyPointsEndDate . ' 23:59:59') : null;
            
            if ($startDate === false) {
                $_SESSION['error'] = 'Неверный формат начальной даты';
                header('Location: /content-groups/schedules/create');
                exit;
            }
            
            $createdSchedules = [];
            $currentDate = $startDate;
            
            // Учитываем дни недели, если указаны
            $weekdaysArray = $weekdays ? explode(',', $weekdays) : null;
            
            // Создаем расписания для каждого дня в диапазоне
            while ($endDate === null || $currentDate <= $endDate) {
                $dayOfWeek = (int)date('N', $currentDate); // 1-7 (пн-вс)
                
                // Проверяем, нужно ли создавать расписание для этого дня недели
                if ($weekdaysArray && !in_array($dayOfWeek, $weekdaysArray)) {
                    $currentDate = strtotime('+1 day', $currentDate);
                    continue;
                }
                
                $dateStr = date('Y-m-d', $currentDate);
                
                // Создаем расписание для каждой точки времени
                foreach ($timePointsArray as $timePoint) {
                    $publishDateTime = $dateStr . ' ' . $timePoint . ':00';
                    
                    $scheduleData = array_merge($data, [
                        'publish_at' => $publishDateTime,
                        'daily_time_points' => $dailyTimePoints,
                    ]);
                    
                    $scheduleId = $scheduleRepo->create($scheduleData);
                    $createdSchedules[] = $scheduleId;
                }
                
                // Переходим к следующему дню
                $currentDate = strtotime('+1 day', $currentDate);
            }
            
            $_SESSION['success'] = 'Создано ' . count($createdSchedules) . ' расписаний';
        } else {
            // Обычное создание одного расписания
            try {
                $scheduleId = $scheduleRepo->create($data);
                if (!$scheduleId) {
                    throw new \Exception('Не удалось создать расписание. Проверьте данные.');
                }
                error_log("SmartScheduleController::create: Schedule created successfully with ID {$scheduleId}");
                $_SESSION['success'] = 'Умное расписание создано успешно';
            } catch (\Exception $e) {
                error_log("SmartScheduleController::create: Error creating schedule - " . $e->getMessage());
                $_SESSION['error'] = 'Ошибка при создании расписания: ' . $e->getMessage();
                header('Location: /content-groups/schedules/create');
                exit;
            }
        }
        
        header('Location: /schedules');
        exit;
        } catch (\Exception $e) {
            error_log("SmartScheduleController::create: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['error'] = 'Произошла ошибка при создании расписания: ' . $e->getMessage();
            header('Location: /content-groups/schedules/create');
            exit;
        }
    }
}
