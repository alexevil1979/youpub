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
    public function show(int $id): void
    {
        $userId = $_SESSION['user_id'];
        $group = $this->groupService->getGroupWithStats($id, $userId);

        if (!$group) {
            http_response_code(404);
            echo 'Group not found';
            return;
        }

        $files = $this->groupService->getGroupFiles($id, $userId);
        $templates = $this->templateService->getUserTemplates($userId, true);
        
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
}
