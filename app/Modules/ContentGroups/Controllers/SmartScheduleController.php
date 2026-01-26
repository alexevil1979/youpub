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
                
                // Показываем все расписания для групп (без video_id)
                $smartSchedules = array_filter($allSchedules, function($schedule) {
                    return empty($schedule['video_id']);
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
            
            // Вычисляем следующие публикации для каждого расписания
            $nextPublications = [];
            $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
            
            foreach ($smartSchedules as $schedule) {
                $scheduleId = (int)($schedule['id'] ?? 0);
                if (!$scheduleId) continue;
                
                $groupId = (int)($schedule['content_group_id'] ?? 0);
                $scheduleType = $schedule['schedule_type'] ?? 'fixed';
                $now = time();
                
                $publications = [];
                
                // Для интервальных расписаний вычисляем следующие 10 публикаций
                if ($scheduleType === 'interval' && !empty($schedule['interval_minutes'])) {
                    $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                    $interval = (int)$schedule['interval_minutes'] * 60;
                    
                    // Находим следующее время публикации
                    if ($baseTime <= $now) {
                        $elapsed = $now - $baseTime;
                        $intervalsPassed = floor($elapsed / $interval);
                        $nextTime = $baseTime + (($intervalsPassed + 1) * $interval);
                    } else {
                        $nextTime = $baseTime;
                    }
                    
                    // Генерируем следующие 10 публикаций
                    for ($i = 0; $i < 10; $i++) {
                        $publishTime = $nextTime + ($i * $interval);
                        if ($publishTime > $now) {
                            $publications[] = [
                                'time' => $publishTime,
                                'date' => date('Y-m-d H:i:s', $publishTime),
                                'formatted' => date('d.m.Y H:i', $publishTime)
                            ];
                        }
                    }
                }
                // Для фиксированных расписаний с daily_time_points
                elseif ($scheduleType === 'fixed' && !empty($schedule['daily_time_points'])) {
                    $timePoints = json_decode($schedule['daily_time_points'], true);
                    if (is_array($timePoints) && !empty($timePoints)) {
                        $startDate = !empty($schedule['daily_points_start_date']) 
                            ? strtotime($schedule['daily_points_start_date']) 
                            : $now;
                        $endDate = !empty($schedule['daily_points_end_date']) 
                            ? strtotime($schedule['daily_points_end_date']) 
                            : strtotime('+30 days', $now);
                        
                        $currentDate = max($startDate, $now);
                        $count = 0;
                        $maxCount = 10;
                        
                        while ($currentDate <= $endDate && $count < $maxCount) {
                            foreach ($timePoints as $timePoint) {
                                if ($count >= $maxCount) break;
                                
                                $timeStr = is_array($timePoint) ? ($timePoint['time'] ?? '') : $timePoint;
                                if (empty($timeStr)) continue;
                                
                                $publishTime = strtotime(date('Y-m-d', $currentDate) . ' ' . $timeStr);
                                
                                if ($publishTime > $now) {
                                    $publications[] = [
                                        'time' => $publishTime,
                                        'date' => date('Y-m-d H:i:s', $publishTime),
                                        'formatted' => date('d.m.Y H:i', $publishTime)
                                    ];
                                    $count++;
                                }
                            }
                            
                            $currentDate = strtotime('+1 day', $currentDate);
                        }
                    }
                }
                // Для обычных фиксированных расписаний
                elseif (!empty($schedule['publish_at'])) {
                    $publishTime = strtotime($schedule['publish_at']);
                    if ($publishTime > $now) {
                        $publications[] = [
                            'time' => $publishTime,
                            'date' => date('Y-m-d H:i:s', $publishTime),
                            'formatted' => date('d.m.Y H:i', $publishTime)
                        ];
                    }
                }
                
                $nextPublications[$scheduleId] = $publications;
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
                <h2>Ошибка при загрузке расписаний</h2>
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
        $userId = $_SESSION['user_id'] ?? null;
        
        if (!$userId) {
            header('Location: /login');
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
        
        include __DIR__ . '/../../../../views/content_groups/schedules/create.php';
    }

    /**
     * Создать умное расписание
     */
    public function create(): void
    {
        try {
            if (!$this->validateCsrf()) {
                // Сохраняем данные формы в сессии для восстановления
                $_SESSION['schedule_form_data'] = $_POST;
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/schedules/create');
                exit;
            }

            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                // Сохраняем данные формы в сессии для восстановления
                $_SESSION['schedule_form_data'] = $_POST;
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
            
            // Генерируем название расписания, если не указано
            $scheduleName = trim($this->getParam('name', ''));
            if (empty($scheduleName)) {
                $scheduleName = $this->generateScheduleName([
                    'schedule_type' => $this->getParam('schedule_type', 'fixed'),
                    'platform' => $this->getParam('platform'),
                    'content_group_id' => $contentGroupId,
                    'publish_at' => $this->getParam('publish_at'),
                    'groups' => $groups,
                ]);
            }
            
            $data = [
                'user_id' => $userId,
                'name' => $scheduleName,
                'content_group_id' => $contentGroupId,
                'platform' => $this->getParam('platform') ?: null,
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
            
            $videoIdLog = $data['video_id'] ?? null;
            error_log("SmartScheduleController::create: Data prepared - content_group_id: {$data['content_group_id']}, video_id: {$videoIdLog}, platform: {$data['platform']}, schedule_type: {$data['schedule_type']}");

        // Валидация - расписание может быть создано без группы и видео (будет использоваться группами через schedule_id)
        // Убрана обязательность группы и видео

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
                    $_SESSION['schedule_form_data'] = $_POST;
                    $_SESSION['error'] = 'Укажите хотя бы одну точку времени';
                    header('Location: /content-groups/schedules/create');
                    exit;
                }
                
                // Валидация формата времени
                foreach ($timePoints as $time) {
                    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
                        $_SESSION['schedule_form_data'] = $_POST;
                        $_SESSION['error'] = 'Неверный формат времени. Используйте HH:MM';
                        header('Location: /content-groups/schedules/create');
                        exit;
                    }
                }
                
                $dailyTimePoints = json_encode(array_values($timePoints));
                $dailyPointsStartDate = $this->getParam('fixed_start_date');
                $dailyPointsEndDate = $this->getParam('fixed_end_date');
                
                if (empty($dailyPointsStartDate)) {
                    $_SESSION['schedule_form_data'] = $_POST;
                    $_SESSION['error'] = 'Укажите начальную дату';
                    header('Location: /content-groups/schedules/create');
                    exit;
                }
            } else {
                // Обычный режим одной точки времени
                if (empty($data['publish_at'])) {
                    $_SESSION['schedule_form_data'] = $_POST;
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
                $_SESSION['schedule_form_data'] = $_POST;
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
                $_SESSION['schedule_form_data'] = $_POST;
                $_SESSION['error'] = 'Ошибка при создании расписания: ' . $e->getMessage();
                header('Location: /content-groups/schedules/create');
                exit;
            }
        }
        
        // Успешное создание - очищаем сохраненные данные формы
        unset($_SESSION['schedule_form_data']);
        header('Location: /content-groups/schedules');
        exit;
        } catch (\Exception $e) {
            error_log("SmartScheduleController::create: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['schedule_form_data'] = $_POST;
            $_SESSION['error'] = 'Произошла ошибка при создании расписания.';
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
                    
                    // Вычисляем время публикации для каждого файла
                    // Для интервальных расписаний вычисляем на основе базового времени и интервала
                    $now = time();
                    $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                    $intervalMinutes = isset($schedule['interval_minutes']) ? (int)$schedule['interval_minutes'] : 0;
                    $scheduleType = $schedule['schedule_type'] ?? 'fixed';
                    
                    // Подсчитываем количество уже опубликованных файлов (для пропуска)
                    $unpublishedIndex = 0; // Индекс для неопубликованных файлов
                    
                    // Для интервальных расписаний вычисляем время для каждого файла
                    foreach ($files as $index => $file) {
                        $nextPublishAt = null;
                        $fileStatus = $file['status'] ?? 'new';
                        
                        // Если файл уже опубликован, не показываем время публикации
                        if (in_array($fileStatus, ['published'])) {
                            $scheduledFiles[] = [
                                'file' => $file,
                                'schedule' => $schedule,
                                'publish_at' => null,
                                'is_published' => true
                            ];
                            continue;
                        }
                        
                        // Вычисляем время публикации в зависимости от типа расписания
                        if ($scheduleType === 'interval' && $intervalMinutes > 0) {
                            // Для интервальных: базовое время + (позиция неопубликованного файла * интервал)
                            
                            // Если базовое время прошло, вычисляем от текущего момента
                            if ($baseTime <= $now) {
                                // Находим следующий интервал от текущего момента
                                $elapsed = $now - $baseTime;
                                $intervalsPassed = floor($elapsed / ($intervalMinutes * 60));
                                
                                // Время публикации для этого файла: следующий интервал + позиция файла
                                $publishTime = $baseTime + (($intervalsPassed + 1 + $unpublishedIndex) * $intervalMinutes * 60);
                            } else {
                                // Базовое время еще не наступило
                                $publishTime = $baseTime + ($unpublishedIndex * $intervalMinutes * 60);
                            }
                            
                            $nextPublishAt = date('Y-m-d H:i:s', $publishTime);
                            $unpublishedIndex++; // Увеличиваем индекс для следующего неопубликованного файла
                        } elseif ($scheduleType === 'fixed' && !empty($schedule['publish_at'])) {
                            // Для фиксированных расписаний учитываем задержку между публикациями и лимиты
                            $delayMinutes = isset($schedule['delay_between_posts']) ? (int)$schedule['delay_between_posts'] : 0;
                            $dailyLimit = isset($schedule['daily_limit']) ? (int)$schedule['daily_limit'] : 0;
                            $hourlyLimit = isset($schedule['hourly_limit']) ? (int)$schedule['hourly_limit'] : 0;
                            $activeHoursStart = $schedule['active_hours_start'] ?? null;
                            $activeHoursEnd = $schedule['active_hours_end'] ?? null;
                            
                            // Базовое время первой публикации
                            $currentPublishTime = $baseTime;
                            
                            // Если это не первый неопубликованный файл, добавляем задержку
                            if ($unpublishedIndex > 0 && $delayMinutes > 0) {
                                // Добавляем задержку для каждого следующего файла
                                $currentPublishTime = $baseTime + ($unpublishedIndex * $delayMinutes * 60);
                            }
                            
                            // Учитываем активные часы
                            if ($activeHoursStart && $activeHoursEnd) {
                                $currentHour = (int)date('H', $currentPublishTime);
                                $currentMinute = (int)date('i', $currentPublishTime);
                                $currentTimeMinutes = $currentHour * 60 + $currentMinute;
                                
                                list($startHour, $startMinute) = explode(':', $activeHoursStart);
                                list($endHour, $endMinute) = explode(':', $activeHoursEnd);
                                $startTimeMinutes = (int)$startHour * 60 + (int)$startMinute;
                                $endTimeMinutes = (int)$endHour * 60 + (int)$endMinute;
                                
                                // Если текущее время вне активных часов, переносим на начало активных часов
                                if ($currentTimeMinutes < $startTimeMinutes || $currentTimeMinutes >= $endTimeMinutes) {
                                    // Если время до начала активных часов, переносим на начало
                                    if ($currentTimeMinutes < $startTimeMinutes) {
                                        $currentPublishTime = strtotime(date('Y-m-d', $currentPublishTime) . ' ' . $activeHoursStart . ':00');
                                    } else {
                                        // Если время после окончания активных часов, переносим на начало следующего дня
                                        $currentPublishTime = strtotime(date('Y-m-d', $currentPublishTime) . ' ' . $activeHoursStart . ':00');
                                        $currentPublishTime = strtotime('+1 day', $currentPublishTime);
                                    }
                                }
                            }
                            
                            // Учитываем часовой лимит
                            if ($hourlyLimit > 0) {
                                $hourStart = strtotime(date('Y-m-d H:00:00', $currentPublishTime));
                                $hourEnd = $hourStart + 3600;
                                
                                // Подсчитываем, сколько файлов уже запланировано в этом часе
                                $filesInThisHour = 0;
                                foreach ($scheduledFiles as $prevItem) {
                                    if (isset($prevItem['publish_at'])) {
                                        $prevTime = strtotime($prevItem['publish_at']);
                                        if ($prevTime >= $hourStart && $prevTime < $hourEnd) {
                                            $filesInThisHour++;
                                        }
                                    }
                                }
                                
                                // Если в этом часе уже достигнут лимит, переносим на следующий час
                                if ($filesInThisHour >= $hourlyLimit) {
                                    $currentPublishTime = $hourEnd;
                                    
                                    // Проверяем активные часы после переноса
                                    if ($activeHoursStart && $activeHoursEnd) {
                                        $newHour = (int)date('H', $currentPublishTime);
                                        $newMinute = (int)date('i', $currentPublishTime);
                                        $newTimeMinutes = $newHour * 60 + $newMinute;
                                        
                                        list($startHour, $startMinute) = explode(':', $activeHoursStart);
                                        list($endHour, $endMinute) = explode(':', $activeHoursEnd);
                                        $startTimeMinutes = (int)$startHour * 60 + (int)$startMinute;
                                        $endTimeMinutes = (int)$endHour * 60 + (int)$endMinute;
                                        
                                        // Если новый час вне активных часов, переносим на начало активных часов
                                        if ($newTimeMinutes < $startTimeMinutes || $newTimeMinutes >= $endTimeMinutes) {
                                            $currentPublishTime = strtotime(date('Y-m-d', $currentPublishTime) . ' ' . $activeHoursStart . ':00');
                                            // Если это уже следующий день, добавляем день
                                            if ($newTimeMinutes >= $endTimeMinutes) {
                                                $currentPublishTime = strtotime('+1 day', $currentPublishTime);
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // Учитываем дневной лимит
                            if ($dailyLimit > 0) {
                                $dayStart = strtotime(date('Y-m-d 00:00:00', $currentPublishTime));
                                $dayEnd = $dayStart + 86400;
                                
                                // Подсчитываем, сколько файлов уже запланировано в этот день
                                $filesInThisDay = 0;
                                foreach ($scheduledFiles as $prevItem) {
                                    if (isset($prevItem['publish_at'])) {
                                        $prevTime = strtotime($prevItem['publish_at']);
                                        if ($prevTime >= $dayStart && $prevTime < $dayEnd) {
                                            $filesInThisDay++;
                                        }
                                    }
                                }
                                
                                // Если в этот день уже достигнут лимит, переносим на следующий день
                                if ($filesInThisDay >= $dailyLimit) {
                                    $currentPublishTime = $dayEnd;
                                    
                                    // Устанавливаем на начало активных часов следующего дня
                                    if ($activeHoursStart) {
                                        $currentPublishTime = strtotime(date('Y-m-d', $currentPublishTime) . ' ' . $activeHoursStart . ':00');
                                    }
                                }
                            }
                            
                            $nextPublishAt = date('Y-m-d H:i:s', $currentPublishTime);
                            $unpublishedIndex++; // Увеличиваем индекс для следующего неопубликованного файла
                        }
                        
                        $scheduledFiles[] = [
                            'file' => $file,
                            'schedule' => $schedule,
                            'publish_at' => $nextPublishAt,
                            'is_published' => false
                        ];
                    }
                    
                    // Сортируем файлы по времени публикации (опубликованные в конце)
                    usort($scheduledFiles, function($a, $b) {
                        if (isset($a['is_published']) && $a['is_published']) {
                            return 1; // Опубликованные в конец
                        }
                        if (isset($b['is_published']) && $b['is_published']) {
                            return -1;
                        }
                        $timeA = $a['publish_at'] ? strtotime($a['publish_at']) : 9999999999;
                        $timeB = $b['publish_at'] ? strtotime($b['publish_at']) : 9999999999;
                        return $timeA <=> $timeB;
                    });
                }
            }
            
            // Получаем шаблон из группы, если есть
            $template = null;
            if (!empty($group['template_id'])) {
                $templateRepo = new \App\Modules\ContentGroups\Repositories\PublicationTemplateRepository();
                $template = $templateRepo->findById((int)$group['template_id']);
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
            if (!$this->validateCsrf()) {
                $_SESSION['error'] = 'Invalid CSRF token';
                header('Location: /content-groups/schedules/' . $id . '/edit');
                exit;
            }

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
            
            // Получаем группы для генерации названия
            $groups = $this->groupService->getUserGroups($userId);
            if (!isset($groups)) {
                $groups = [];
            }
            
            // Генерируем название расписания, если не указано
            $scheduleName = trim($this->getParam('name', ''));
            if (empty($scheduleName)) {
                $scheduleName = $this->generateScheduleName([
                    'schedule_type' => $this->getParam('schedule_type', 'fixed'),
                    'platform' => $this->getParam('platform'),
                    'content_group_id' => $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null,
                    'publish_at' => $this->getParam('publish_at'),
                    'groups' => $groups,
                ]);
            }
            
            $updateData = [
                'name' => $scheduleName,
                'content_group_id' => $this->getParam('content_group_id') ? (int)$this->getParam('content_group_id') : null,
                'platform' => $this->getParam('platform') ?: null,
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
            $_SESSION['error'] = 'Ошибка при обновлении расписания.';
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
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }

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
            $this->error('Произошла ошибка при приостановке расписания', 500);
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
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }

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
            $this->error('Произошла ошибка при возобновлении расписания', 500);
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
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }
            
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $schedule = $scheduleRepo->findById($id);
            
            if (!$schedule || $schedule['user_id'] !== $userId) {
                $this->error('Расписание не найдено', 404);
                return;
            }
            
            // Проверяем, что это групповое расписание (с группой контента)
            if (empty($schedule['content_group_id'])) {
                $this->error('Это не групповое расписание', 400);
                return;
            }
            
            $scheduleRepo->delete($id);
            $this->success([], 'Расписание успешно удалено');
        } catch (\Exception $e) {
            error_log("SmartScheduleController::delete: Exception - " . $e->getMessage());
            $this->error('Произошла ошибка при удалении расписания', 500);
        }
    }

    /**
     * Массовое приостановление
     */
    public function bulkPause(): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkPause($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }

    /**
     * Массовое возобновление
     */
    public function bulkResume(): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkResume($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }

    /**
     * Массовое удаление
     */
    public function bulkDelete(): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $data = $this->getRequestData();
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $this->error('Invalid IDs', 400);
            return;
        }

        $result = $this->scheduleService->bulkDelete($ids, $userId);
        $this->success($result['data'] ?? [], $result['message']);
    }
    
    /**
     * Генерировать название расписания автоматически
     */
    private function generateScheduleName(array $params): string
    {
        $scheduleType = $params['schedule_type'] ?? 'fixed';
        $platform = $params['platform'] ?? '';
        $contentGroupId = $params['content_group_id'] ?? null;
        $publishAt = $params['publish_at'] ?? null;
        $groups = $params['groups'] ?? [];
        
        $parts = [];
        
        // Тип расписания
        $typeNames = [
            'fixed' => 'Фиксированное',
            'interval' => 'Интервальное',
            'batch' => 'Пакетное',
            'random' => 'Случайное',
            'wave' => 'Волновое',
        ];
        $parts[] = $typeNames[$scheduleType] ?? 'Расписание';
        
        // Платформа
        if (!empty($platform)) {
            $platformNames = [
                'youtube' => 'YouTube',
                'telegram' => 'Telegram',
                'tiktok' => 'TikTok',
                'instagram' => 'Instagram',
                'pinterest' => 'Pinterest',
            ];
            $parts[] = $platformNames[$platform] ?? ucfirst($platform);
        }
        
        // Группа
        if ($contentGroupId) {
            foreach ($groups as $group) {
                if ((int)$group['id'] === (int)$contentGroupId) {
                    $parts[] = $group['name'];
                    break;
                }
            }
        }
        
        // Дата публикации
        if ($publishAt) {
            try {
                $date = new \DateTime($publishAt);
                $parts[] = $date->format('d.m.Y H:i');
            } catch (\Exception $e) {
                // Игнорируем ошибку парсинга даты
            }
        }
        
        return implode(' • ', $parts);
    }
}
