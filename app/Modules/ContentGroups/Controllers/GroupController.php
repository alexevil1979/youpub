<?php

namespace App\Modules\ContentGroups\Controllers;

use Core\Controller;
use App\Modules\ContentGroups\Services\GroupService;
use App\Modules\ContentGroups\Services\TemplateService;

/**
 * Контроллер для управления группами контента
 */
class GroupController extends Controller
{
    private GroupService $groupService;
    private TemplateService $templateService;

    public function __construct()
    {
        parent::__construct();
        $this->groupService = new GroupService();
        $this->templateService = new TemplateService();
    }

    /**
     * Список групп
     */
    public function index(): void
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /login');
                exit;
            }
            
            $userId = $_SESSION['user_id'];
            $groups = $this->groupService->getUserGroups($userId, true);
            
            include __DIR__ . '/../../../../views/content_groups/index.php';
        } catch (\Throwable $e) {
            error_log("GroupController::index error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            http_response_code(500);
            echo "Ошибка при загрузке страницы. Пожалуйста, попробуйте позже.";
        }
    }

    /**
     * Показать форму создания группы
     */
    public function showCreate(): void
    {
        $userId = $_SESSION['user_id'];
        $templates = $this->templateService->getUserTemplates($userId, true);
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        // Получаем все расписания пользователя для выбора
        $schedules = [];
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $allSchedules = $scheduleRepo->findByUserId($userId);
            // Фильтруем только расписания для групп (без video_id)
            $schedules = array_filter($allSchedules, fn($s) => empty($s['video_id']) && !empty($s['content_group_id']));
        } catch (\Throwable $e) {
            $schedules = [];
        }
        
        // Получаем все доступные интеграции для отображения статуса
        $youtubeAccounts = [];
        $telegramAccounts = [];
        $tiktokAccounts = [];
        $instagramAccounts = [];
        $pinterestAccounts = [];
        try {
            $youtubeAccounts = (new \App\Repositories\YoutubeIntegrationRepository())->findByUserId($userId);
            $youtubeAccounts = array_filter($youtubeAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $youtubeAccounts = [];
        }
        try {
            $telegramAccounts = (new \App\Repositories\TelegramIntegrationRepository())->findByUserId($userId);
            $telegramAccounts = array_filter($telegramAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $telegramAccounts = [];
        }
        try {
            $tiktokAccounts = (new \App\Repositories\TiktokIntegrationRepository())->findByUserId($userId);
            $tiktokAccounts = array_filter($tiktokAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $tiktokAccounts = [];
        }
        try {
            $instagramAccounts = (new \App\Repositories\InstagramIntegrationRepository())->findByUserId($userId);
            $instagramAccounts = array_filter($instagramAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $instagramAccounts = [];
        }
        try {
            $pinterestAccounts = (new \App\Repositories\PinterestIntegrationRepository())->findByUserId($userId);
            $pinterestAccounts = array_filter($pinterestAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $pinterestAccounts = [];
        }
        
        include __DIR__ . '/../../../../views/content_groups/create.php';
    }

    /**
     * Создать группу
     */
    public function create(): void
    {
        if (!$this->validateCsrf()) {
            $_SESSION['error'] = 'Invalid CSRF token';
            header('Location: /content-groups/create');
            exit;
        }

        $userId = $_SESSION['user_id'];
        
        // Получаем выбранные интеграции (формат: platform_id, например "youtube_1", "telegram_2")
        $integrations = $this->getParam('integrations', []);
        if (!is_array($integrations)) {
            $integrations = [];
        }
        
        // Парсим интеграции: разделяем на platform и integration_id
        $integrationsList = [];
        foreach ($integrations as $integrationStr) {
            if (preg_match('/^(youtube|telegram|tiktok|instagram|pinterest)_(\d+)$/', $integrationStr, $matches)) {
                $integrationsList[] = [
                    'platform' => $matches[1],
                    'integration_id' => (int)$matches[2],
                ];
            }
        }
        
        $settings = [];
        if (!empty($integrationsList)) {
            $settings['integrations'] = $integrationsList;
        }
        
        $data = [
            'name' => $this->getParam('name', ''),
            'description' => $this->getParam('description', ''),
            'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
            'schedule_id' => $this->getParam('schedule_id') ? (int)$this->getParam('schedule_id') : null,
            'status' => $this->getParam('status', 'active'),
            'settings' => !empty($settings) ? $settings : null,
        ];

        $result = $this->groupService->createGroup($userId, $data);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            header('Location: /content-groups');
        } else {
            $_SESSION['error'] = $result['message'];
            header('Location: /content-groups/create');
        }
        exit;
    }

    /**
     * Показать группу
     */
    public function show($id): void
    {
        // Приводим к int, если передана строка
        $id = (int)$id;
        
        if ($id <= 0) {
            http_response_code(404);
            echo "Группа не найдена";
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            http_response_code(404);
            echo 'Group not found';
            return;
        }

        $files = $this->groupService->getGroupFiles($id, $userId);
        
        // Получаем публикации для всех видео в группе
        $publicationRepo = new \App\Repositories\PublicationRepository();
        $videoIds = array_unique(array_map(static fn($file) => (int)($file['video_id'] ?? 0), $files));
        $filePublications = $publicationRepo->findLatestSuccessfulByVideoIds($videoIds);
        
        // Получаем расписание группы (нужно для отображения информации о расписании)
        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $schedule = null;
        if (!empty($group['schedule_id'])) {
            $schedule = $scheduleRepo->findById($group['schedule_id']);
        }
        
        // Если расписание не найдено через schedule_id, ищем через content_group_id (старая логика)
        if (!$schedule) {
            $schedules = $scheduleRepo->findByGroupId($id);
            if (!empty($schedules)) {
                $schedule = $schedules[0];
            }
        }
        
        // Получаем следующую дату публикации для каждого файла (только если группа активна)
        $nextPublishDates = [];
        $nextPublishInfo = []; // Дополнительная информация о следующей публикации
        if ($group['status'] === 'active' && !empty($files) && $schedule) {
            if ($schedule) {
                $scheduleType = $schedule['schedule_type'] ?? 'fixed';
                $platform = $schedule['platform'] ?? 'youtube';
                $now = time();
                
                // Фильтруем файлы, которые еще не опубликованы
                $unpublishedFiles = array_filter($files, fn($f) => in_array($f['status'], ['new', 'queued', 'paused']));
                
                if ($scheduleType === 'interval' && !empty($schedule['interval_minutes'])) {
                    // Интервальное расписание: рассчитываем время для каждого файла
                    $baseTime = strtotime($schedule['publish_at'] ?? 'now');
                    $intervalMinutes = (int)$schedule['interval_minutes'];
                    $interval = $intervalMinutes * 60;
                    
                    // Сортируем файлы по order_index для правильного расчета
                    usort($unpublishedFiles, fn($a, $b) => ($a['order_index'] ?? 0) <=> ($b['order_index'] ?? 0));
                    
                    // Вычисляем базовое время следующей публикации
                    if ($baseTime <= $now) {
                        $elapsed = $now - $baseTime;
                        $intervalsPassed = floor($elapsed / $interval);
                        $nextBaseTime = $baseTime + (($intervalsPassed + 1) * $interval);
                    } else {
                        $nextBaseTime = $baseTime;
                    }
                    
                    // Для каждого файла рассчитываем время публикации
                    $fileIndex = 0;
                    foreach ($unpublishedFiles as $file) {
                        $publishTime = $nextBaseTime + ($fileIndex * $interval);
                        $publishDate = date('Y-m-d H:i:s', $publishTime);
                        
                        $nextPublishDates[$file['id']] = $publishDate;
                        $nextPublishInfo[$file['id']] = [
                            'date' => $publishDate,
                            'platform' => $platform,
                            'schedule_id' => $schedule['id'] ?? null
                        ];
                        $fileIndex++;
                    }
                } else {
                    // Фиксированное или другое расписание: используем publish_at из расписания
                    $nextPublishDate = $schedule['publish_at'];
                    
                    foreach ($unpublishedFiles as $file) {
                        $nextPublishDates[$file['id']] = $nextPublishDate;
                        $nextPublishInfo[$file['id']] = [
                            'date' => $nextPublishDate,
                            'platform' => $platform,
                            'schedule_id' => $schedule['id'] ?? null
                        ];
                    }
                }
            }
        }
        
        // Сохраняем информацию о расписании для передачи в представление
        $scheduleInfo = null;
        if (isset($schedule) && $schedule) {
            $scheduleInfo = [
                'id' => $schedule['id'],
                'name' => $schedule['name'] ?? null,
                'schedule_type' => $schedule['schedule_type'] ?? 'fixed',
                'platform' => $schedule['platform'] ?? 'youtube',
                'publish_at' => $schedule['publish_at'] ?? null,
                'interval_minutes' => $schedule['interval_minutes'] ?? null,
                'batch_count' => $schedule['batch_count'] ?? null,
                'batch_window_hours' => $schedule['batch_window_hours'] ?? null,
                'random_window_start' => $schedule['random_window_start'] ?? null,
                'random_window_end' => $schedule['random_window_end'] ?? null,
                'status' => $schedule['status'] ?? 'pending',
            ];
        }
        
        // Формируем план отправки (список файлов с датами публикации)
        $publicationPlan = [];
        if ($group['status'] === 'active' && !empty($files) && !empty($nextPublishInfo)) {
            // Сортируем файлы по дате публикации
            $filesWithDates = [];
            foreach ($files as $file) {
                if (isset($nextPublishInfo[$file['id']])) {
                    $filesWithDates[] = [
                        'file' => $file,
                        'publish_info' => $nextPublishInfo[$file['id']],
                        'publish_timestamp' => strtotime($nextPublishInfo[$file['id']]['date'])
                    ];
                }
            }
            
            // Сортируем по времени публикации
            usort($filesWithDates, fn($a, $b) => $a['publish_timestamp'] <=> $b['publish_timestamp']);
            
            $publicationPlan = $filesWithDates;
        }
        
        $templates = $this->templateService->getUserTemplates($userId, true);
        
        // Получаем выбранные интеграции из settings группы и загружаем информацию о каналах
        $selectedIntegrations = [];
        $integrationAccounts = [];
        if (!empty($group['settings'])) {
            $settings = is_string($group['settings']) ? json_decode($group['settings'], true) : $group['settings'];
            if (isset($settings['integrations']) && is_array($settings['integrations'])) {
                $selectedIntegrations = $settings['integrations'];
                
                // Загружаем информацию о каналах
                $youtubeRepo = new \App\Repositories\YoutubeIntegrationRepository();
                $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
                $tiktokRepo = new \App\Repositories\TiktokIntegrationRepository();
                $instagramRepo = new \App\Repositories\InstagramIntegrationRepository();
                $pinterestRepo = new \App\Repositories\PinterestIntegrationRepository();
                
                foreach ($selectedIntegrations as $integration) {
                    $platform = $integration['platform'] ?? '';
                    $integrationId = isset($integration['integration_id']) ? (int)$integration['integration_id'] : null;
                    
                    if (!$platform || !$integrationId) {
                        continue;
                    }
                    
                    $account = null;
                    try {
                        switch ($platform) {
                            case 'youtube':
                                $account = $youtubeRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'telegram':
                                $account = $telegramRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'tiktok':
                                $account = $tiktokRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'instagram':
                                $account = $instagramRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'pinterest':
                                $account = $pinterestRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                        }
                        
                        if ($account) {
                            $integrationAccounts[] = [
                                'platform' => $platform,
                                'integration_id' => $integrationId,
                                'account' => $account
                            ];
                        }
                    } catch (\Throwable $e) {
                        error_log("Error loading integration {$platform} ID {$integrationId}: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Применяем шаблон для превью каждого файла в группе
        $filePreviews = [];
        if ($group['status'] === 'active' && !empty($files)) {
            // Определяем шаблон из группы
            $templateId = $group['template_id'] ?? null;
            
            // Определяем платформу (из расписания или по умолчанию)
            $platform = 'youtube';
            if (!empty($schedules) && isset($schedules[0]['platform'])) {
                $platform = $schedules[0]['platform'];
            }
            
            $videoRepo = new \App\Repositories\VideoRepository();
            
            foreach ($files as $file) {
                // Применяем шаблон только для неопубликованных видео
                if (in_array($file['status'], ['new', 'queued', 'paused'])) {
                    $video = $videoRepo->findById($file['video_id']);
                    
                    if ($video) {
                        $context = [
                            'group_name' => $group['name'],
                            'index' => $file['order_index'],
                            'platform' => $platform,
                        ];
                        
                        $preview = $this->templateService->applyTemplate($templateId, [
                            'id' => $video['id'],
                            'title' => $file['title'] ?? $video['title'] ?? $video['file_name'] ?? '',
                            'description' => $video['description'] ?? '',
                            'tags' => $video['tags'] ?? '',
                        ], $context);
                        
                        $filePreviews[$file['id']] = $preview;
                    }
                }
            }
        }
        
        // Находим следующее видео в очереди и применяем к нему шаблон для превью
        $nextVideoPreview = null;
        if ($group['status'] === 'active' && !empty($files)) {
            $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
            $nextFile = $fileRepo->findNextUnpublished($id);
            
            if ($nextFile) {
                $videoRepo = new \App\Repositories\VideoRepository();
                $nextVideo = $videoRepo->findById($nextFile['video_id']);
                
                if ($nextVideo) {
                    // Определяем шаблон из группы
                    $templateId = $group['template_id'] ?? null;
                    
                    // Определяем платформу (из расписания или по умолчанию)
                    $platform = 'youtube';
                    if (!empty($schedules) && isset($schedules[0]['platform'])) {
                        $platform = $schedules[0]['platform'];
                    }
                    
                    // Применяем шаблон для превью
                    $context = [
                        'group_name' => $group['name'],
                        'index' => $nextFile['order_index'],
                        'platform' => $platform,
                    ];
                    
                    $preview = $this->templateService->applyTemplate($templateId, [
                        'id' => $nextVideo['id'],
                        'title' => $nextFile['title'] ?? $nextVideo['title'] ?? $nextVideo['file_name'] ?? '',
                        'description' => $nextVideo['description'] ?? '',
                        'tags' => $nextVideo['tags'] ?? '',
                    ], $context);
                    
                    $nextVideoPreview = [
                        'video' => $nextVideo,
                        'file' => $nextFile,
                        'template_id' => $templateId,
                        'template_name' => null,
                        'preview' => $preview,
                        'platform' => $platform,
                    ];
                    
                    // Получаем название шаблона
                    if ($templateId) {
                        foreach ($templates as $template) {
                            if ($template['id'] == $templateId) {
                                $nextVideoPreview['template_name'] = $template['name'];
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        include __DIR__ . '/../../../../views/content_groups/show.php';
    }

    /**
     * Добавить видео в группу
     */
    public function addVideo(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        
        // Поддержка как POST form-data, так и JSON
        $videoId = (int)($this->getParam('video_id', 0) ?: ($_POST['video_id'] ?? 0));

        if (!$videoId) {
            $this->error('Video ID is required', 400);
            return;
        }

        $result = $this->groupService->addVideoToGroup($id, $videoId, $userId);

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Массовое добавление видео
     */
    public function addVideos(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        
        // Поддержка как JSON, так и form-data
        $requestData = $this->getRequestData();
        $videoIds = $requestData['video_ids'] ?? $this->getParam('video_ids', []);

        if (empty($videoIds) || !is_array($videoIds)) {
            $this->error('Video IDs are required', 400);
            return;
        }

        $videoIds = array_map('intval', $videoIds);
        $result = $this->groupService->addVideosToGroup($id, $videoIds, $userId);

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Удалить видео из группы
     */
    public function removeVideo(int $groupId, int $videoId): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        
        // Проверяем права доступа
        $group = $this->groupService->getGroupWithStats($groupId, $userId);
        if (!$group) {
            $this->error('Group not found', 404);
            return;
        }

        $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
        $file = $fileRepo->findByGroupId($groupId);
        $file = array_filter($file, fn($f) => $f['video_id'] == $videoId);
        
        if (empty($file)) {
            $this->error('Video not found in group', 404);
            return;
        }

        $fileRepo->delete(reset($file)['id']);
        $this->success([], 'Video removed from group');
    }

    /**
     * Перемешать видео в группе
     */
    public function shuffle(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        $result = $this->groupService->shuffleGroup($id, $userId);

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Показать форму редактирования группы
     */
    public function showEdit(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            http_response_code(404);
            echo 'Group not found';
            return;
        }

        $templates = $this->templateService->getUserTemplates($userId, true);
        
        // Получаем все расписания пользователя для выбора (без video_id - только для групп)
        $schedules = [];
        try {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            $allSchedules = $scheduleRepo->findByUserId($userId);
            // Фильтруем только расписания для групп (без video_id) - content_group_id не обязателен
            $schedules = array_filter($allSchedules, fn($s) => empty($s['video_id']));
        } catch (\Throwable $e) {
            $schedules = [];
        }
        
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        if (!isset($templates)) {
            $templates = [];
        }
        
        // Получаем список видео пользователя для добавления в группу
        $videoService = new \App\Services\VideoService();
        $videos = $videoService->getUserVideos($userId);
        
        // Получаем видео, которые уже в группе
        $groupFiles = $this->groupService->getGroupFiles($id, $userId);
        $groupVideoIds = array_map(static fn($file) => (int)($file['video_id'] ?? 0), $groupFiles);
        
        // Фильтруем видео - показываем только те, которых еще нет в группе
        $availableVideos = array_filter($videos, static fn($video) => !in_array((int)$video['id'], $groupVideoIds, true));
        
        // Получаем все доступные интеграции для отображения статуса
        $youtubeAccounts = [];
        $telegramAccounts = [];
        $tiktokAccounts = [];
        $instagramAccounts = [];
        $pinterestAccounts = [];
        try {
            $youtubeAccounts = (new \App\Repositories\YoutubeIntegrationRepository())->findByUserId($userId);
            $youtubeAccounts = array_filter($youtubeAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $youtubeAccounts = [];
        }
        try {
            $telegramAccounts = (new \App\Repositories\TelegramIntegrationRepository())->findByUserId($userId);
            $telegramAccounts = array_filter($telegramAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $telegramAccounts = [];
        }
        try {
            $tiktokAccounts = (new \App\Repositories\TiktokIntegrationRepository())->findByUserId($userId);
            $tiktokAccounts = array_filter($tiktokAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $tiktokAccounts = [];
        }
        try {
            $instagramAccounts = (new \App\Repositories\InstagramIntegrationRepository())->findByUserId($userId);
            $instagramAccounts = array_filter($instagramAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $instagramAccounts = [];
        }
        try {
            $pinterestAccounts = (new \App\Repositories\PinterestIntegrationRepository())->findByUserId($userId);
            $pinterestAccounts = array_filter($pinterestAccounts, fn($acc) => ($acc['status'] ?? '') === 'connected');
        } catch (\Throwable $e) {
            $pinterestAccounts = [];
        }
        
        // Получаем выбранные интеграции из settings
        $selectedIntegrations = [];
        if (!empty($group['settings'])) {
            $settings = is_string($group['settings']) ? json_decode($group['settings'], true) : $group['settings'];
            if (isset($settings['integrations']) && is_array($settings['integrations'])) {
                $selectedIntegrations = $settings['integrations'];
            }
        }
        
        include __DIR__ . '/../../../../views/content_groups/edit.php';
    }

    /**
     * Обновить группу
     */
    public function update(int $id): void
    {
        if (!$this->validateCsrf()) {
            $_SESSION['error'] = 'Invalid CSRF token';
            header('Location: /content-groups/' . $id . '/edit');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            $_SESSION['error'] = 'Group not found';
            header('Location: /content-groups');
            exit;
        }

        // Получаем выбранные интеграции (формат: platform_id, например "youtube_1", "telegram_2")
        $integrations = $this->getParam('integrations', []);
        if (!is_array($integrations)) {
            $integrations = [];
        }
        
        // Парсим интеграции: разделяем на platform и integration_id
        $integrationsList = [];
        foreach ($integrations as $integrationStr) {
            if (preg_match('/^(youtube|telegram|tiktok|instagram|pinterest)_(\d+)$/', $integrationStr, $matches)) {
                $integrationsList[] = [
                    'platform' => $matches[1],
                    'integration_id' => (int)$matches[2],
                ];
            }
        }
        
        // Получаем текущие settings группы
        $currentSettings = [];
        if (!empty($group['settings'])) {
            $currentSettings = is_string($group['settings']) ? json_decode($group['settings'], true) : $group['settings'];
            if (!is_array($currentSettings)) {
                $currentSettings = [];
            }
        }
        
        // Обновляем интеграции в settings
        if (!empty($integrationsList)) {
            $currentSettings['integrations'] = $integrationsList;
        } else {
            unset($currentSettings['integrations']);
        }
        
        $data = [
            'name' => $this->getParam('name', ''),
            'description' => $this->getParam('description', ''),
            'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
            'schedule_id' => $this->getParam('schedule_id') ? (int)$this->getParam('schedule_id') : null,
            'status' => $this->getParam('status', 'active'),
            'settings' => !empty($currentSettings) ? $currentSettings : null,
        ];

        try {
            $result = $this->groupService->updateGroup($id, $userId, $data);

            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                header('Location: /content-groups/' . $id);
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: /content-groups/' . $id . '/edit');
            }
        } catch (\Exception $e) {
            error_log("GroupController::update error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $_SESSION['error'] = 'Ошибка при обновлении группы: ' . $e->getMessage();
            header('Location: /content-groups/' . $id . '/edit');
        }
        exit;
    }

    /**
     * Переключить статус группы (включить/выключить)
     */
    public function toggleStatus(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            $this->error('Group not found', 404);
            return;
        }

        $newStatus = $group['status'] === 'active' ? 'paused' : 'active';
        $result = $this->groupService->updateGroup($id, $userId, ['status' => $newStatus]);

        if ($result['success']) {
            $this->success(['status' => $newStatus], $newStatus === 'active' ? 'Группа включена' : 'Группа приостановлена');
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Копировать группу
     */
    public function duplicate(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        $result = $this->groupService->duplicateGroup($id, $userId);

        if ($result['success']) {
            $this->success($result['data'], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }

    /**
     * Удалить группу
     */
    public function delete(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            $this->error('Group not found', 404);
            return;
        }

        $groupRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupRepository();
        $groupRepo->delete($id);

        $this->success([], 'Group deleted successfully');
    }

    /**
     * Переключить статус файла в группе
     */
    public function toggleFileStatus(int $id, int $fileId): void
    {
        try {
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }

            $userId = $_SESSION['user_id'] ?? null;
            
            if (!$userId) {
                $this->error('Необходима авторизация', 401);
                return;
            }
            
            error_log("GroupController::toggleFileStatus: groupId={$id}, fileId={$fileId}, userId={$userId}");
            
            $data = $this->getRequestData();
            $newStatus = $data['status'] ?? null;

            if (!$newStatus) {
                error_log("GroupController::toggleFileStatus: Status not provided in request data");
                $this->error('Статус не указан', 400);
                return;
            }

            error_log("GroupController::toggleFileStatus: New status requested: {$newStatus}");

            $result = $this->groupService->toggleFileStatus($id, $fileId, $userId, $newStatus);

            if ($result['success']) {
                error_log("GroupController::toggleFileStatus: Success - status changed to {$newStatus}");
                $this->success(['status' => $result['data']['status'] ?? null], $result['message'] ?? 'Статус файла изменен');
            } else {
                error_log("GroupController::toggleFileStatus: Failed - " . ($result['message'] ?? 'Unknown error'));
                $this->error($result['message'] ?? 'Не удалось изменить статус файла', 400);
            }
        } catch (\Exception $e) {
            error_log("GroupController::toggleFileStatus: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            $this->error('Произошла ошибка при изменении статуса', 500);
        }
    }

    /**
     * Очистить статус опубликованности для одного файла
     */
    public function clearFilePublication(int $id, int $fileId): void
    {
        try {
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }

            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $this->error('Необходима авторизация', 401);
                return;
            }

            $result = $this->groupService->clearFilePublication($id, $fileId, $userId);
            if ($result['success']) {
                $this->success($result['data'] ?? [], $result['message'] ?? 'Статус опубликованности очищен');
            } else {
                $this->error($result['message'] ?? 'Не удалось очистить статус', 400);
            }
        } catch (\Exception $e) {
            error_log("GroupController::clearFilePublication: " . $e->getMessage());
            $this->error('Произошла ошибка при очистке статуса', 500);
        }
    }

    /**
     * Очистить статус опубликованности для нескольких файлов
     */
    public function clearFilesPublication(int $id): void
    {
        try {
            if (!$this->validateCsrf()) {
                $this->error('Invalid CSRF token', 403);
                return;
            }

            $userId = $_SESSION['user_id'] ?? null;
            if (!$userId) {
                $this->error('Необходима авторизация', 401);
                return;
            }

            $data = $this->getRequestData();
            $fileIds = $data['file_ids'] ?? [];
            if (!is_array($fileIds)) {
                $this->error('Некорректный список файлов', 400);
                return;
            }

            $result = $this->groupService->clearFilesPublication($id, $fileIds, $userId);
            if ($result['success']) {
                $this->success($result['data'] ?? [], $result['message'] ?? 'Статус опубликованности очищен');
            } else {
                $this->error($result['message'] ?? 'Не удалось очистить статус', 400);
            }
        } catch (\Exception $e) {
            error_log("GroupController::clearFilesPublication: " . $e->getMessage());
            $this->error('Произошла ошибка при очистке статуса', 500);
        }
    }

    /**
     * Показать страницу публикации файла сейчас
     */
    public function showPublishNow(int $id, int $fileId): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            header('Location: /login');
            exit;
        }

        $group = $this->groupService->getGroupWithStats($id, $userId);
        if (!$group) {
            http_response_code(404);
            echo 'Группа не найдена';
            return;
        }

        $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
        $file = $fileRepo->findById($fileId);
        if (!$file || (int)$file['group_id'] !== (int)$id) {
            http_response_code(404);
            echo 'Файл не найден';
            return;
        }

        $videoRepo = new \App\Repositories\VideoRepository();
        $video = $videoRepo->findById((int)$file['video_id']);
        if (!$video) {
            http_response_code(404);
            echo 'Видео не найдено';
            return;
        }

        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $latestSchedules = $scheduleRepo->findLatestByGroupIds([(int)$id]);
        $schedule = $latestSchedules[$id] ?? null;
        $platform = $schedule['platform'] ?? 'youtube';
        $templateId = $group['template_id'] ?? null;

        $templates = $this->templateService->getUserTemplates($userId, true);
        $templateName = null;
        $templateData = null;
        if ($templateId) {
            foreach ($templates as $template) {
                if ((int)$template['id'] === (int)$templateId) {
                    $templateName = $template['name'];
                    $templateData = $template;
                    break;
                }
            }
        }

        // Получаем выбранные интеграции из settings группы и загружаем информацию о каналах
        $selectedIntegrations = [];
        $integrationAccounts = [];
        if (!empty($group['settings'])) {
            $settings = is_string($group['settings']) ? json_decode($group['settings'], true) : $group['settings'];
            if (isset($settings['integrations']) && is_array($settings['integrations'])) {
                $selectedIntegrations = $settings['integrations'];
                
                // Загружаем информацию о каналах
                $youtubeRepo = new \App\Repositories\YoutubeIntegrationRepository();
                $telegramRepo = new \App\Repositories\TelegramIntegrationRepository();
                $tiktokRepo = new \App\Repositories\TiktokIntegrationRepository();
                $instagramRepo = new \App\Repositories\InstagramIntegrationRepository();
                $pinterestRepo = new \App\Repositories\PinterestIntegrationRepository();
                
                foreach ($selectedIntegrations as $integration) {
                    $integrationPlatform = $integration['platform'] ?? '';
                    $integrationId = isset($integration['integration_id']) ? (int)$integration['integration_id'] : null;
                    
                    if (!$integrationPlatform || !$integrationId) {
                        continue;
                    }
                    
                    $account = null;
                    try {
                        switch ($integrationPlatform) {
                            case 'youtube':
                                $account = $youtubeRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'telegram':
                                $account = $telegramRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'tiktok':
                                $account = $tiktokRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'instagram':
                                $account = $instagramRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                            case 'pinterest':
                                $account = $pinterestRepo->findByIdAndUserId($integrationId, $userId);
                                break;
                        }
                        
                        if ($account) {
                            $integrationAccounts[] = [
                                'platform' => $integrationPlatform,
                                'integration_id' => $integrationId,
                                'account' => $account
                            ];
                        }
                    } catch (\Throwable $e) {
                        error_log("Error loading integration {$integrationPlatform} ID {$integrationId}: " . $e->getMessage());
                    }
                }
            }
        }

        $context = [
            'group_name' => $group['name'],
            'index' => $file['order_index'] ?? 0,
            'platform' => $platform,
        ];
        $preview = $this->templateService->applyTemplate($templateId, [
            'id' => $video['id'],
            'title' => $video['title'] ?? $video['file_name'] ?? '',
            'description' => $video['description'] ?? '',
            'tags' => $video['tags'] ?? '',
        ], $context);


        $canPublish = in_array($file['status'], ['new', 'queued', 'paused', 'error'], true);
        $csrfToken = (new \Core\Auth())->generateCsrfToken();

        include __DIR__ . '/../../../../views/content_groups/publish_now.php';
    }

    /**
     * Опубликовать файл сейчас
     */
    public function publishNow(int $id, int $fileId): void
    {
        if (!$this->validateCsrf()) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
                exit;
            }
            $_SESSION['error'] = 'Invalid CSRF token';
            header('Location: /content-groups/' . $id . '/files/' . $fileId . '/publish-now');
            exit;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Необходима авторизация']);
                exit;
            }
            $_SESSION['error'] = 'Необходима авторизация';
            header('Location: /login');
            exit;
        }

        // Инициализируем сессию, если не инициализирована
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        // Защита от повторных запросов: проверяем, не публикуется ли уже этот файл
        try {
            $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
            $file = $fileRepo->findById($fileId);
        
            if ($file && in_array($file['status'], ['queued', 'published'], true)) {
                // Проверяем, есть ли активные расписания
                $scheduleRepo = new \App\Repositories\ScheduleRepository();
                $db = \Core\Database::getInstance();
                $stmt = $db->prepare("
                    SELECT id 
                    FROM schedules 
                    WHERE video_id = ? 
                    AND status IN ('processing', 'pending')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    LIMIT 1
                ");
                $stmt->execute([(int)$file['video_id']]);
                if ($stmt->fetch()) {
                    $message = 'Этот файл уже публикуется, подождите';
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $message]);
                        exit;
                    }
                    $_SESSION['error'] = $message;
                    header('Location: /content-groups/' . $id . '/files/' . $fileId . '/publish-now');
                    exit;
                }
            }
        } catch (\Exception $e) {
            error_log("GroupController::publishNow: Error in duplicate check: " . $e->getMessage());
            // Продолжаем публикацию, если проверка не удалась
        }
        
        try {
            error_log("GroupController::publishNow: Starting publication for group {$id}, file {$fileId}, user {$userId}");
            $result = $this->groupService->publishGroupFileNow($id, $fileId, $userId);
            error_log("GroupController::publishNow: Publication result - success: " . ($result['success'] ? 'true' : 'false') . ", message: " . ($result['message'] ?? 'no message'));
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            }
            
            if ($result['success']) {
                $_SESSION['success'] = 'Видео опубликовано';
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Не удалось опубликовать видео';
            }
        } catch (\Exception $e) {
            error_log("GroupController::publishNow error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Ошибка публикации: ' . $e->getMessage()]);
                exit;
            }
            $_SESSION['error'] = 'Ошибка публикации: ' . $e->getMessage();
        }

        header('Location: /content-groups/' . $id . '/files/' . $fileId . '/publish-now');
        exit;
    }

    /**
     * Перегенерировать превью публикации по шаблону
     */
    public function regeneratePublishPreview(int $id, int $fileId): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        // Инициализируем сессию, если не инициализирована
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->error('Необходима авторизация', 401);
            return;
        }

        $group = $this->groupService->getGroupWithStats($id, $userId);
        if (!$group) {
            $this->error('Группа не найдена', 404);
            return;
        }

        $fileRepo = new \App\Modules\ContentGroups\Repositories\ContentGroupFileRepository();
        $file = $fileRepo->findById($fileId);
        if (!$file || (int)$file['group_id'] !== (int)$id) {
            $this->error('Файл не найден', 404);
            return;
        }

        $videoRepo = new \App\Repositories\VideoRepository();
        $video = $videoRepo->findById((int)$file['video_id']);
        if (!$video) {
            $this->error('Видео не найдено', 404);
            return;
        }

        $scheduleRepo = new \App\Repositories\ScheduleRepository();
        $latestSchedules = $scheduleRepo->findLatestByGroupIds([(int)$id]);
        $schedule = $latestSchedules[$id] ?? null;
        $platform = $schedule['platform'] ?? 'youtube';
        $templateId = $group['template_id'] ?? null;

        $context = [
            'group_name' => $group['name'],
            'index' => $file['order_index'] ?? 0,
            'platform' => $platform,
        ];
        $preview = $this->templateService->applyTemplate($templateId, [
            'id' => $video['id'],
            'title' => $video['title'] ?? $video['file_name'] ?? '',
            'description' => $video['description'] ?? '',
            'tags' => $video['tags'] ?? '',
        ], $context);

        // Сохраняем сгенерированное оформление в сессии для использования при публикации
        if (!isset($_SESSION['publish_previews'])) {
            $_SESSION['publish_previews'] = [];
        }
        $previewKey = "{$id}_{$fileId}";
        $_SESSION['publish_previews'][$previewKey] = $preview;

        $this->success(['preview' => $preview]);
    }

    /**
     * Опубликовать все неопубликованные видео в группе
     */
    public function publishAllUnpublished(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->error('Необходима авторизация', 401);
            return;
        }

        $result = $this->groupService->publishAllUnpublished($id, $userId);
        if ($result['success']) {
            $this->success($result['data'] ?? [], $result['message'] ?? 'Видео опубликованы');
        } else {
            $this->error($result['message'] ?? 'Не удалось опубликовать видео', 400);
        }
    }

    /**
     * Сбросить статус опубликованности для всех файлов в группе
     */
    public function clearAllFilesPublication(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->error('Invalid CSRF token', 403);
            return;
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            $this->error('Необходима авторизация', 401);
            return;
        }

        $result = $this->groupService->clearAllFilesPublication($id, $userId);
        if ($result['success']) {
            $this->success($result['data'] ?? [], $result['message'] ?? 'Статус опубликованности сброшен');
        } else {
            $this->error($result['message'] ?? 'Не удалось сбросить статус', 400);
        }
    }
}
