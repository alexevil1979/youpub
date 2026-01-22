<?php

namespace App\Modules\ContentGroups\Services;

use Core\Service;
use App\Modules\ContentGroups\Repositories\ContentGroupRepository;
use App\Modules\ContentGroups\Repositories\ContentGroupFileRepository;

/**
 * Сервис для работы с группами контента
 */
class GroupService extends Service
{
    private ContentGroupRepository $groupRepo;
    private ContentGroupFileRepository $fileRepo;

    public function __construct()
    {
        parent::__construct();
        $this->groupRepo = new ContentGroupRepository();
        $this->fileRepo = new ContentGroupFileRepository();
    }

    /**
     * Создать группу
     */
    public function createGroup(int $userId, array $data): array
    {
        $groupId = $this->groupRepo->create([
            'user_id' => $userId,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'settings' => isset($data['settings']) ? json_encode($data['settings']) : null,
        ]);

        return [
            'success' => true,
            'data' => ['id' => $groupId],
            'message' => 'Group created successfully'
        ];
    }

    /**
     * Обновить группу
     */
    public function updateGroup(int $groupId, int $userId, array $data): array
    {
        // Проверяем права доступа
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Group not found or access denied'
            ];
        }

        $updateData = [];
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['template_id'])) {
            $updateData['template_id'] = $data['template_id'];
        }
        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (empty($updateData)) {
            return [
                'success' => false,
                'message' => 'No data to update'
            ];
        }

        $this->groupRepo->update($groupId, $updateData);

        return [
            'success' => true,
            'message' => 'Group updated successfully'
        ];
    }

    /**
     * Добавить видео в группу
     */
    public function addVideoToGroup(int $groupId, int $videoId, int $userId): array
    {
        // Проверяем права доступа
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Group not found or access denied'];
        }

        $id = $this->fileRepo->addVideoToGroup($groupId, $videoId);
        
        return [
            'success' => $id > 0,
            'data' => ['id' => $id],
            'message' => $id > 0 ? 'Video added to group' : 'Video already in group'
        ];
    }

    /**
     * Массовое добавление видео в группу
     */
    public function addVideosToGroup(int $groupId, array $videoIds, int $userId): array
    {
        // Проверяем права доступа
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return ['success' => false, 'message' => 'Group not found or access denied'];
        }

        $added = $this->fileRepo->addVideosToGroup($groupId, $videoIds);
        
        return [
            'success' => true,
            'data' => ['added_count' => $added],
            'message' => "Added {$added} videos to group"
        ];
    }

    /**
     * Получить группу со статистикой
     */
    public function getGroupWithStats(int $groupId, int $userId): ?array
    {
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return null;
        }

        $stats = $this->groupRepo->getGroupStats($groupId);
        $group['stats'] = $stats;
        $group['settings'] = !empty($group['settings']) ? json_decode($group['settings'], true) : [];

        return $group;
    }

    /**
     * Получить список групп пользователя
     */
    public function getUserGroups(int $userId, bool $withStats = false): array
    {
        $groups = $this->groupRepo->findByUserId($userId);

        if ($withStats) {
            foreach ($groups as &$group) {
                $group['stats'] = $this->groupRepo->getGroupStats($group['id']);
                $group['settings'] = !empty($group['settings']) ? json_decode($group['settings'], true) : [];
            }
        }

        return $groups;
    }

    /**
     * Получить файлы группы
     */
    public function getGroupFiles(int $groupId, int $userId, ?string $status = null): array
    {
        // Проверяем права доступа
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return [];
        }

        if ($status) {
            return $this->fileRepo->findByGroupIdAndStatus($groupId, $status);
        }

        return $this->fileRepo->findByGroupId($groupId);
    }

    /**
     * Получить следующее видео для публикации из группы
     */
    public function getNextVideoForPublishing(int $groupId): ?array
    {
        return $this->fileRepo->findNextUnpublished($groupId);
    }
}
