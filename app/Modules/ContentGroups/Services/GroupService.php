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
        try {
            // Проверяем права доступа
            $group = $this->groupRepo->findById($groupId);
            if (!$group || $group['user_id'] !== $userId) {
                return [
                    'success' => false,
                    'message' => 'Group not found or access denied'
                ];
            }

            // Проверяем существование колонки schedule_id
            $hasScheduleIdColumn = false;
            try {
                $db = \Core\Database::getInstance();
                $stmt = $db->prepare("SHOW COLUMNS FROM `content_groups` LIKE 'schedule_id'");
                $stmt->execute();
                $hasScheduleIdColumn = (bool)$stmt->fetch();
            } catch (\Exception $e) {
                error_log("GroupService::updateGroup: Error checking schedule_id column: " . $e->getMessage());
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
            if (isset($data['use_auto_generation'])) {
                $updateData['use_auto_generation'] = $data['use_auto_generation'];
            }
            // Обрабатываем schedule_id - всегда добавляем в updateData если передан, даже если null
            // Используем array_key_exists чтобы различать отсутствие ключа и null значение
            if (array_key_exists('schedule_id', $data)) {
                if ($hasScheduleIdColumn) {
                    // schedule_id может быть null (если не выбрано расписание)
                    $scheduleId = $data['schedule_id'];
                    if ($scheduleId === '' || $scheduleId === null || $scheduleId === 0) {
                        $updateData['schedule_id'] = null;
                    } else {
                        $updateData['schedule_id'] = (int)$scheduleId;
                    }
                    error_log("GroupService::updateGroup: schedule_id will be updated to: " . var_export($updateData['schedule_id'], true) . " (hasScheduleIdColumn: " . ($hasScheduleIdColumn ? 'true' : 'false') . ")");
                } else {
                    error_log("GroupService::updateGroup: schedule_id column does not exist in database, skipping");
                }
            } else {
                error_log("GroupService::updateGroup: schedule_id key not found in data array");
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (isset($data['settings'])) {
                $updateData['settings'] = is_array($data['settings']) ? json_encode($data['settings']) : $data['settings'];
            }

            // Проверяем, есть ли данные для обновления
            // empty() вернет true если массив пустой, но также вернет true если все значения null
            // Поэтому проверяем количество элементов
            if (count($updateData) === 0) {
                return [
                    'success' => false,
                    'message' => 'No data to update'
                ];
            }
            
            error_log("GroupService::updateGroup: updateData keys: " . implode(', ', array_keys($updateData)));
            error_log("GroupService::updateGroup: updateData values: " . json_encode($updateData));

            $this->groupRepo->update($groupId, $updateData);

            return [
                'success' => true,
                'message' => 'Group updated successfully'
            ];
        } catch (\Exception $e) {
            error_log("GroupService::updateGroup error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [
                'success' => false,
                'message' => 'Ошибка при обновлении группы: ' . $e->getMessage()
            ];
        }
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
            $allowedStatuses = ['new', 'queued', 'published', 'error', 'paused', 'skipped'];
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

    /**
     * Очистить статус опубликованности для одного файла
     */
    public function clearFilePublication(int $groupId, int $fileId, int $userId): array
    {
        try {
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            if ($group['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }

            $file = $this->fileRepo->findById($fileId);
            if (!$file) {
                return ['success' => false, 'message' => 'Файл не найден'];
            }
            if ((int)$file['group_id'] !== (int)$groupId) {
                return ['success' => false, 'message' => 'Файл не принадлежит этой группе'];
            }

            $updated = $this->fileRepo->clearPublicationStatus($fileId);
            if (!$updated) {
                return ['success' => false, 'message' => 'Не удалось очистить статус'];
            }

            return [
                'success' => true,
                'message' => 'Статус опубликованности очищен',
                'data' => ['status' => 'new']
            ];
        } catch (\Exception $e) {
            error_log("GroupService::clearFilePublication: " . $e->getMessage());
            return ['success' => false, 'message' => 'Произошла ошибка при очистке статуса'];
        }
    }

    /**
     * Очистить статус опубликованности для нескольких файлов
     */
    public function clearFilesPublication(int $groupId, array $fileIds, int $userId): array
    {
        try {
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            if ($group['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }

            $ids = array_values(array_unique(array_filter($fileIds, static fn($id) => (int)$id > 0)));
            if (empty($ids)) {
                return ['success' => false, 'message' => 'Список файлов пуст'];
            }

            $updatedCount = $this->fileRepo->clearPublicationStatusByIds($groupId, $ids);
            if ($updatedCount <= 0) {
                return ['success' => false, 'message' => 'Не удалось очистить статус'];
            }

            return [
                'success' => true,
                'message' => 'Статус опубликованности очищен',
                'data' => ['updated' => $updatedCount]
            ];
        } catch (\Exception $e) {
            error_log("GroupService::clearFilesPublication: " . $e->getMessage());
            return ['success' => false, 'message' => 'Произошла ошибка при очистке статуса'];
        }
    }

    /**
     * Опубликовать файл группы прямо сейчас
     */
    public function publishGroupFileNow(int $groupId, int $fileId, int $userId): array
    {
        $smartQueue = new \App\Modules\ContentGroups\Services\SmartQueueService();
        return $smartQueue->publishGroupFileNow($groupId, $fileId, $userId);
    }

    /**
     * Опубликовать все неопубликованные видео в группе
     */
    public function publishAllUnpublished(int $groupId, int $userId): array
    {
        try {
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            if ($group['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }

            // Получаем все неопубликованные файлы
            $unpublishedFiles = $this->fileRepo->findByGroupIdAndStatus($groupId, 'new');
            $unpublishedFiles = array_merge($unpublishedFiles, $this->fileRepo->findByGroupIdAndStatus($groupId, 'paused'));
            $unpublishedFiles = array_merge($unpublishedFiles, $this->fileRepo->findByGroupIdAndStatus($groupId, 'error'));

            if (empty($unpublishedFiles)) {
                return ['success' => false, 'message' => 'Нет неопубликованных видео в группе'];
            }

            $smartQueue = new \App\Modules\ContentGroups\Services\SmartQueueService();
            $results = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($unpublishedFiles as $file) {
                $result = $smartQueue->publishGroupFileNow($groupId, $file['id'], $userId);
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $results[] = [
                        'file_id' => $file['id'],
                        'video_id' => $file['video_id'],
                        'error' => $result['message'] ?? 'Ошибка публикации'
                    ];
                }
            }

            return [
                'success' => $successCount > 0,
                'message' => "Опубликовано: {$successCount}, Ошибок: {$errorCount}",
                'data' => [
                    'total' => count($unpublishedFiles),
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'error_details' => $results
                ]
            ];
        } catch (\Exception $e) {
            error_log("GroupService::publishAllUnpublished: " . $e->getMessage());
            return ['success' => false, 'message' => 'Произошла ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * Сбросить статус опубликованности для всех файлов в группе
     */
    public function clearAllFilesPublication(int $groupId, int $userId): array
    {
        try {
            $group = $this->groupRepo->findById($groupId);
            if (!$group) {
                return ['success' => false, 'message' => 'Группа не найдена'];
            }
            if ($group['user_id'] !== $userId) {
                return ['success' => false, 'message' => 'Нет доступа к этой группе'];
            }

            // Получаем все файлы группы
            $files = $this->fileRepo->findByGroupId($groupId);
            if (empty($files)) {
                return ['success' => false, 'message' => 'В группе нет файлов'];
            }

            $fileIds = array_map(static fn($file) => (int)$file['id'], $files);
            $updatedCount = $this->fileRepo->clearPublicationStatusByIds($groupId, $fileIds);

            return [
                'success' => true,
                'message' => "Статус опубликованности сброшен для {$updatedCount} файлов",
                'data' => ['updated' => $updatedCount]
            ];
        } catch (\Exception $e) {
            error_log("GroupService::clearAllFilesPublication: " . $e->getMessage());
            return ['success' => false, 'message' => 'Произошла ошибка: ' . $e->getMessage()];
        }
    }
}
