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
        $userId = $_SESSION['user_id'];
        $groups = $this->groupService->getUserGroups($userId, true);
        
        include __DIR__ . '/../../../../views/content_groups/index.php';
    }

    /**
     * Показать форму создания группы
     */
    public function showCreate(): void
    {
        $userId = $_SESSION['user_id'];
        $templates = $this->templateService->getUserTemplates($userId, true);
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        include __DIR__ . '/../../../../views/content_groups/create.php';
    }

    /**
     * Создать группу
     */
    public function create(): void
    {
        $userId = $_SESSION['user_id'];
        $data = [
            'name' => $this->getParam('name', ''),
            'description' => $this->getParam('description', ''),
            'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
            'status' => $this->getParam('status', 'active'),
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
        $filePublications = [];
        foreach ($files as $file) {
            $publications = $publicationRepo->findSuccessfulByVideoId($file['video_id']);
            if (!empty($publications)) {
                // Берем первую (последнюю по дате) успешную публикацию
                $filePublications[$file['video_id']] = $publications[0];
            }
        }
        
        // Получаем следующую дату публикации для каждого файла (только если группа активна)
        $nextPublishDates = [];
        $nextPublishInfo = []; // Дополнительная информация о следующей публикации
        if ($group['status'] === 'active' && !empty($files)) {
            $scheduleRepo = new \App\Repositories\ScheduleRepository();
            
            // Получаем все активные расписания для этой группы
            $schedules = $scheduleRepo->findByGroupId($id);
            
            // Для каждого файла находим следующее расписание
            // Для групп контента расписание обычно для всей группы, а не для конкретного видео
            if (!empty($schedules)) {
                // Берем ближайшее расписание для группы
                $nextSchedule = $schedules[0];
                $nextPublishDate = $nextSchedule['publish_at'];
                $platform = $nextSchedule['platform'] ?? 'youtube';
                
                // Присваиваем эту дату всем файлам, которые еще не опубликованы или в очереди
                foreach ($files as $file) {
                    if (in_array($file['status'], ['new', 'queued', 'paused'])) {
                        $nextPublishDates[$file['id']] = $nextPublishDate;
                        $nextPublishInfo[$file['id']] = [
                            'date' => $nextPublishDate,
                            'platform' => $platform,
                            'schedule_id' => $nextSchedule['id'] ?? null
                        ];
                    }
                }
            }
        }
        
        $templates = $this->templateService->getUserTemplates($userId, true);
        
        // Применяем шаблон для превью каждого файла в группе
        $filePreviews = [];
        if ($group['status'] === 'active' && !empty($files)) {
            // Определяем шаблон (из расписания или группы)
            $templateId = null;
            if (!empty($schedules) && isset($schedules[0]['template_id'])) {
                $templateId = $schedules[0]['template_id'];
            } elseif ($group['template_id']) {
                $templateId = $group['template_id'];
            }
            
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
                    // Определяем шаблон (из расписания или группы)
                    $templateId = null;
                    if (!empty($schedules) && isset($schedules[0]['template_id'])) {
                        $templateId = $schedules[0]['template_id'];
                    } elseif ($group['template_id']) {
                        $templateId = $group['template_id'];
                    }
                    
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
        $userId = $_SESSION['user_id'];
        $videoIds = $this->getParam('video_ids', []);

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
        $csrfToken = (new \Core\Auth())->generateCsrfToken();
        
        if (!isset($templates)) {
            $templates = [];
        }
        
        include __DIR__ . '/../../../../views/content_groups/edit.php';
    }

    /**
     * Обновить группу
     */
    public function update(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            $_SESSION['error'] = 'Group not found';
            header('Location: /content-groups');
            exit;
        }

        $data = [
            'name' => $this->getParam('name', ''),
            'description' => $this->getParam('description', ''),
            'template_id' => $this->getParam('template_id') ? (int)$this->getParam('template_id') : null,
            'status' => $this->getParam('status', 'active'),
        ];

        $result = $this->groupService->updateGroup($id, $userId, $data);

        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            header('Location: /content-groups/' . $id);
        } else {
            $_SESSION['error'] = $result['message'];
            header('Location: /content-groups/' . $id . '/edit');
        }
        exit;
    }

    /**
     * Переключить статус группы (включить/выключить)
     */
    public function toggleStatus(int $id): void
    {
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
            $this->error('Произошла ошибка при изменении статуса: ' . $e->getMessage(), 500);
        }
    }
}
