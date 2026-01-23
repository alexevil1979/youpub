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
     * Список умных расписаний
     */
    public function index(): void
    {
        error_log("SmartScheduleController::index: START - " . date('Y-m-d H:i:s'));
        
        try {
            // Проверяем сессию
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $userId = $_SESSION['user_id'] ?? null;
            error_log("SmartScheduleController::index: userId = " . ($userId ?? 'NULL'));
            
            if (!$userId) {
                error_log("SmartScheduleController::index: No user ID, redirecting to login");
                header('Location: /login');
                exit;
            }
            
            // Инициализируем переменные
            $smartSchedules = [];
            $groups = [];
            
            try {
                error_log("SmartScheduleController::index: Loading schedules for user {$userId}");
                
                // Проверяем, что репозиторий может быть создан
                try {
                    $scheduleRepo = new \App\Repositories\ScheduleRepository();
                    error_log("SmartScheduleController::index: ScheduleRepository created successfully");
                } catch (\Exception $e) {
                    error_log("SmartScheduleController::index: Error creating ScheduleRepository: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                    throw $e;
                }
                
                // Получаем все умные расписания (с группами контента) для пользователя
                try {
                    $allSchedules = $scheduleRepo->findByUserId($userId);
                    error_log("SmartScheduleController::index: Found " . (is_array($allSchedules) ? count($allSchedules) : 0) . " schedules");
                } catch (\Exception $e) {
                    error_log("SmartScheduleController::index: Error in findByUserId: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                    $allSchedules = [];
                }
                
                if (!is_array($allSchedules)) {
                    error_log("SmartScheduleController::index: findByUserId returned non-array: " . gettype($allSchedules));
                    $allSchedules = [];
                }
                
                $smartSchedules = array_filter($allSchedules, function($schedule) {
                    return !empty($schedule['content_group_id']) && is_numeric($schedule['content_group_id']);
                });
                
                // Преобразуем в массив с числовыми индексами
                $smartSchedules = array_values($smartSchedules);
                error_log("SmartScheduleController::index: Filtered to " . count($smartSchedules) . " smart schedules");
                
                // Получаем информацию о группах для расписаний
                $groupIds = [];
                foreach ($smartSchedules as $schedule) {
                    if (!empty($schedule['content_group_id']) && is_numeric($schedule['content_group_id'])) {
                        $groupIds[] = (int)$schedule['content_group_id'];
                    }
                }
                $groupIds = array_unique($groupIds);
                error_log("SmartScheduleController::index: Found " . count($groupIds) . " unique group IDs");
                
                if (!empty($groupIds)) {
                    foreach ($groupIds as $groupId) {
                        try {
                            error_log("SmartScheduleController::index: Loading group {$groupId}");
                            $group = $this->groupService->getGroupWithStats($groupId, $userId);
                            if ($group && is_array($group)) {
                                $groups[$groupId] = $group;
                                error_log("SmartScheduleController::index: Group {$groupId} loaded successfully");
                            } else {
                                error_log("SmartScheduleController::index: Group {$groupId} returned null or non-array");
                            }
                        } catch (\Exception $e) {
                            error_log("SmartScheduleController::index: Error loading group {$groupId}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                            // Продолжаем работу, даже если группа не найдена
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log("SmartScheduleController::index: Error in data loading: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
                error_log("SmartScheduleController::index: Stack trace: " . $e->getTraceAsString());
                // Продолжаем с пустыми массивами
            }
            
            // Убеждаемся, что переменные определены
            if (!isset($smartSchedules)) {
                $smartSchedules = [];
            }
            if (!isset($groups)) {
                $groups = [];
            }
            
            // Проверяем существование файла представления
            $viewPath = __DIR__ . '/../../../../views/content_groups/schedules/index.php';
            if (!file_exists($viewPath)) {
                throw new \Exception("View file not found: {$viewPath}");
            }
            
            // Включаем представление
            include $viewPath;
            
        } catch (\Exception $e) {
            error_log("SmartScheduleController::index: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            
            // Очищаем буфер вывода, если он был начат
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Показываем HTML страницу с ошибкой
            $title = 'Ошибка';
            ob_start();
            ?>
            <div class="alert alert-error">
                <h2>Ошибка при загрузке умных расписаний</h2>
                <p><?= htmlspecialchars($e->getMessage()) ?></p>
                <p><a href="/dashboard" class="btn btn-secondary">Вернуться на главную</a></p>
            </div>
            <?php
            $content = ob_get_clean();
            
            $layoutPath = __DIR__ . '/../../../../views/layout.php';
            if (file_exists($layoutPath)) {
                include $layoutPath;
            } else {
                echo $content;
            }
        } catch (\Throwable $e) {
            error_log("SmartScheduleController::index: FATAL - " . $e->getMessage());
            http_response_code(500);
            echo "Internal Server Error. Please check server logs.";
        }
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
            
            // Подготавливаем данные, исключая NULL значения для video_id
            $contentGroupId = $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null;
            $videoId = $this->getParam('video_id') ? (int)$this->getParam('video_id') : null;
            $templateId = $this->getParam('template_id') ? (int)$this->getParam('template_id') : null;
            
            $data = [
                'user_id' => $userId,
                'content_group_id' => $contentGroupId,
                'template_id' => $templateId,
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
            
            // Добавляем video_id только если он указан (для расписаний групп контента video_id должен быть NULL)
            if ($videoId !== null) {
                $data['video_id'] = $videoId;
            }
            
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

    /**
     * Показать умное расписание с каталогом файлов
     */
    public function show($id): void
    {
        $id = (int)$id;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            header('Location: /login');
            exit;
        }
        
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $_SESSION['error'] = 'Расписание не найдено';
                header('Location: /content-groups/schedules');
                exit;
            }
            
            // Получаем группу
            $group = null;
            $files = [];
            $scheduledFiles = [];
            
            if (!empty($schedule['content_group_id'])) {
                $group = $this->groupService->getGroupWithStats((int)$schedule['content_group_id'], $userId);
                if ($group) {
                    $files = $this->groupService->getGroupFiles((int)$schedule['content_group_id'], $userId);
                    
                    // Получаем все расписания для этой группы (только pending и будущие)
                    $allSchedules = $scheduleRepo->findByGroupId((int)$schedule['content_group_id']);
                    
                    // Сортируем расписания по времени публикации
                    usort($allSchedules, function($a, $b) {
                        $timeA = strtotime($a['publish_at'] ?? '9999-12-31');
                        $timeB = strtotime($b['publish_at'] ?? '9999-12-31');
                        return $timeA <=> $timeB;
                    });
                    
                    // Создаем массив для связи файлов с расписаниями
                    // Для каждого файла находим следующее расписание
                    foreach ($files as $file) {
                        $nextSchedule = null;
                        $nextPublishAt = null;
                        
                        // Ищем следующее расписание для этого файла
                        // Если расписание интервальное, вычисляем время на основе интервала
                        foreach ($allSchedules as $sched) {
                            if ($sched['schedule_type'] === 'interval' && !empty($sched['interval_minutes'])) {
                                // Для интервальных расписаний вычисляем время для каждого файла
                                $baseTime = strtotime($sched['publish_at'] ?? 'now');
                                $fileIndex = array_search($file, $files);
                                $publishTime = $baseTime + ($fileIndex * (int)$sched['interval_minutes'] * 60);
                                
                                if ($publishTime > time()) {
                                    $nextSchedule = $sched;
                                    $nextPublishAt = date('Y-m-d H:i:s', $publishTime);
                                    break;
                                }
                            } else {
                                // Для фиксированных расписаний берем время из publish_at
                                $publishTime = strtotime($sched['publish_at'] ?? '9999-12-31');
                                if ($publishTime > time()) {
                                    $nextSchedule = $sched;
                                    $nextPublishAt = $sched['publish_at'];
                                    break;
                                }
                            }
                        }
                        
                        $scheduledFiles[] = [
                            'file' => $file,
                            'schedule' => $nextSchedule,
                            'publish_at' => $nextPublishAt
                        ];
                    }
                    
                    // Сортируем файлы по времени публикации
                    usort($scheduledFiles, function($a, $b) {
                        $timeA = $a['publish_at'] ? strtotime($a['publish_at']) : 9999999999;
                        $timeB = $b['publish_at'] ? strtotime($b['publish_at']) : 9999999999;
                        return $timeA <=> $timeB;
                    });
                }
            }
            
            // Получаем шаблон, если есть
            $template = null;
            if (!empty($schedule['template_id'])) {
                $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
                $template = $templateRepo->findById((int)$schedule['template_id']);
            }
            
            include __DIR__ . '/../../../../views/content_groups/schedules/show.php';
        } catch (\Exception $e) {
            error_log("SmartScheduleController::show: Error - " . $e->getMessage());
            $_SESSION['error'] = 'Ошибка при загрузке расписания: ' . $e->getMessage();
            header('Location: /content-groups/schedules');
            exit;
        }
    }

    /**
     * Показать форму редактирования умного расписания
     */
    public function showEdit($id): void
    {
        $id = (int)$id;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            header('Location: /login');
            exit;
        }
        
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $_SESSION['error'] = 'Расписание не найдено';
                header('Location: /content-groups/schedules');
                exit;
            }
            
            $groups = $this->groupService->getUserGroups($userId);
            $templates = $this->templateService->getUserTemplates($userId, true);
            $csrfToken = (new \Core\Auth())->generateCsrfToken();
            
            if (!isset($groups)) {
                $groups = [];
            }
            if (!isset($templates)) {
                $templates = [];
            }
            
            include __DIR__ . '/../../../../views/content_groups/schedules/edit.php';
        } catch (\Exception $e) {
            error_log("SmartScheduleController::showEdit: Error - " . $e->getMessage());
            $_SESSION['error'] = 'Ошибка при загрузке формы редактирования: ' . $e->getMessage();
            header('Location: /content-groups/schedules');
            exit;
        }
    }

    /**
     * Обновить умное расписание
     */
    public function update($id): void
    {
        $id = (int)$id;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $_SESSION['error'] = 'Необходима авторизация';
            header('Location: /content-groups/schedules');
            exit;
        }
        
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $_SESSION['error'] = 'Расписание не найдено';
                header('Location: /content-groups/schedules');
                exit;
            }
            
            // Обработка weekdays
            $weekdaysArray = $_POST['weekdays'] ?? [];
            $weekdays = null;
            if (!empty($weekdaysArray) && is_array($weekdaysArray)) {
                $weekdays = implode(',', array_map('intval', $weekdaysArray));
            }
            
            $updateData = [
                'content_group_id' => $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null,
                'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
                'platform' => $this->getParam('platform', 'youtube'),
                'schedule_type' => $this->getParam('schedule_type', 'fixed'),
                'publish_at' => $this->getParam('publish_at') ? date('Y-m-d H:i:s', strtotime($this->getParam('publish_at'))) : null,
                'interval_minutes' => $this->getParam('interval_minutes') ? (int)$this->getParam('interval_minutes') : null,
                'batch_count' => $this->getParam('batch_count') ? (int)$this->getParam('batch_count') : null,
                'batch_window_hours' => $this->getParam('batch_window_hours') ? (int)$this->getParam('batch_window_hours') : null,
                'random_window_start' => $this->getParam('random_window_start') ?: null,
                'random_window_end' => $this->getParam('random_window_end') ?: null,
                'weekdays' => $weekdays,
                'active_hours_start' => $this->getParam('active_hours_start') ?: null,
                'active_hours_end' => $this->getParam('active_hours_end') ?: null,
                'daily_limit' => $this->getParam('daily_limit') ? (int)$this->getParam('daily_limit') : null,
                'hourly_limit' => $this->getParam('hourly_limit') ? (int)$this->getParam('hourly_limit') : null,
                'delay_between_posts' => $this->getParam('delay_between_posts') ? (int)$this->getParam('delay_between_posts') : null,
                'skip_published' => $this->getParam('skip_published', '1') === '1',
            ];
            
            // Удаляем NULL значения
            $updateData = array_filter($updateData, function($value) {
                return $value !== null;
            });
            
            $scheduleRepo->update($id, $updateData);
            
            $_SESSION['success'] = 'Расписание успешно обновлено';
            header('Location: /content-groups/schedules/' . $id);
            exit;
        } catch (\Exception $e) {
            error_log("SmartScheduleController::update: Error - " . $e->getMessage());
            $_SESSION['error'] = 'Ошибка при обновлении расписания: ' . $e->getMessage();
            header('Location: /content-groups/schedules/' . $id . '/edit');
            exit;
        }
    }

    /**
     * Приостановить умное расписание
     */
    public function pause($id): void
    {
        $id = (int)$id;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->error('Необходима авторизация', 401);
            return;
        }
        
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $this->error('Расписание не найдено', 404);
                return;
            }
            
            $scheduleRepo->update($id, ['status' => 'paused']);
            $this->success([], 'Расписание приостановлено');
        } catch (\Exception $e) {
            error_log("SmartScheduleController::pause: Exception - " . $e->getMessage());
            $this->error('Произошла ошибка при приостановке расписания: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Возобновить умное расписание
     */
    public function resume($id): void
    {
        $id = (int)$id;
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            $this->error('Необходима авторизация', 401);
            return;
        }
        
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $this->error('Расписание не найдено', 404);
                return;
            }
            
            $scheduleRepo->update($id, ['status' => 'pending']);
            $this->success([], 'Расписание возобновлено');
        } catch (\Exception $e) {
            error_log("SmartScheduleController::resume: Exception - " . $e->getMessage());
            $this->error('Произошла ошибка при возобновлении расписания: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Удалить умное расписание
     */
    public function delete(int $id): void
    {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $this->error('Необходима авторизация', 401);
                return;
            }
            
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $this->error('Расписание не найдено', 404);
                return;
            }
            
            // Проверяем, что это умное расписание (с группой контента)
            if (empty($schedule['content_group_id'])) {
                $this->error('Это не умное расписание', 400);
                return;
            }
            
            $scheduleRepo->delete($id);
            $this->success([], 'Расписание успешно удалено');
        } catch (\Exception $e) {
            error_log("SmartScheduleController::delete: Exception - " . $e->getMessage());
            $this->error('Произошла ошибка при удалении расписания: ' . $e->getMessage(), 500);
        }
    }
}
