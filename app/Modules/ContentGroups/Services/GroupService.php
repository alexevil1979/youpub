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
     * Копировать группу
     */
    public function duplicateGroup(int $groupId, int $userId): array
    {
        // Проверяем права доступа
        $group = $this->groupRepo->findById($groupId);
        if (!$group || $group['user_id'] !== $userId) {
            return [
                'success' => false,
                'message' => 'Group not found or access denied'
            ];
        }

        // Создаем копию группы
        $newGroupId = $this->groupRepo->create([
            'user_id' => $userId,
            'name' => $group['name'] . ' (копия)',
            'description' => $group['description'],
            'template_id' => $group['template_id'],
            'status' => 'paused', // Копия создается на паузе
            'settings' => $group['settings'],
        ]);

        // Копируем файлы из группы
        $files = $this->fileRepo->findByGroupId($groupId);
        foreach ($files as $file) {
            $this->fileRepo->create([
                'group_id' => $newGroupId,
                'video_id' => $file['video_id'],
                'status' => 'new', // Новые файлы в статусе "new"
                'order_index' => $file['order_index'],
            ]);
        }

        return [
            'success' => true,
            'data' => ['id' => $newGroupId],
            'message' => 'Группа успешно скопирована'
        ];
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

    /**
     * Переключить статус файла в группе
     */
    public function toggleFileStatus(int $groupId, int $fileId, int $userId, string $newStatus): array
    {
        try {
            error_log("GroupService::toggleFileStatus: groupId={$groupId}, fileId={$fileId}, userId={$userId}, newStatus={$newStatus}");
            
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                error_log("GroupService::toggleFileStatus: Group not found - ID {$groupId}");
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            
            if ($group['user_id'] !== $userId) {
                error_log("GroupService::toggleFileStatus: Unauthorized - group userId={$group['user_id']}, request userId={$userId}");
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }

            $file = $this->fileRepo->findById($fileId);
            if (!$file) {
                error_log("GroupService::toggleFileStatus: File not found - ID {$fileId}");
                return ['success' => false, 'message' => 'Файл не найден'];
            }
            
            if ($file['group_id'] !== $groupId) {
                error_log("GroupService::toggleFileStatus: File belongs to different group - file groupId={$file['group_id']}, request groupId={$groupId}");
                return ['success' => false, 'message' => 'Файл не принадлежит этой группе'];
            }

            // Валидация статуса
            $allowedStatuses = ['new', 'queued', 'published', 'error', 'paused'];
            if (!in_array($newStatus, $allowedStatuses)) {
                error_log("GroupService::toggleFileStatus: Invalid status - {$newStatus}");
                return ['success' => false, 'message' => 'Недопустимый статус: ' . $newStatus];
            }

            $updated = $this->fileRepo->updateFileStatus($fileId, $newStatus);
            if (!$updated) {
                error_log("GroupService::toggleFileStatus: Failed to update file status in database");
                return ['success' => false, 'message' => 'Не удалось обновить статус в базе данных'];
            }

            error_log("GroupService::toggleFileStatus: Success - file {$fileId} status changed to {$newStatus}");
            return [
                'success' => true,
                'message' => 'Статус файла изменен',
                'data' => ['status' => $newStatus]
            ];
        } catch (\Exception $e) {
            error_log("GroupService::toggleFileStatus: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [
                'success' => false,
                'message' => 'Произошла ошибка: ' . $e->getMessage()
            ];
        }
    }
}
