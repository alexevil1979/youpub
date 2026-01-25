<?php

namespace App\Modules\ContentGroups\Repositories;

use Core\Repository;

/**
 * Репозиторий связи групп и файлов
 */
class ContentGroupFileRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('content_group_files');
    }

    /**
     * Найти файлы группы
     */
    public function findByGroupId(int $groupId, array $orderBy = []): array
    {
        $sql = "
            SELECT cgf.*, v.title, v.file_name, v.file_size, v.status as video_status
            FROM {$this->table} cgf
            JOIN videos v ON v.id = cgf.video_id
            WHERE cgf.group_id = ?
        ";
        $params = [$groupId];

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                $order[] = "cgf.{$field} " . strtoupper($direction);
            }
            $sql .= " ORDER BY " . implode(", ", $order);
        } else {
            $sql .= " ORDER BY cgf.created_at DESC, cgf.id DESC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Найти следующее неопубликованное видео в группе
     */
    public function findNextUnpublished(int $groupId): ?array
    {
        $sql = "
            SELECT cgf.*, v.*
            FROM {$this->table} cgf
            JOIN videos v ON v.id = cgf.video_id
            WHERE cgf.group_id = ? 
            AND cgf.status IN ('new', 'queued', 'paused')
            AND v.status IN ('uploaded', 'ready')
            ORDER BY cgf.order_index ASC, cgf.created_at ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$groupId]);
        $result = $stmt->fetch() ?: null;
        
        if ($result) {
            $videoStatus = isset($result['video_status']) ? $result['video_status'] : 'unknown';
            error_log("ContentGroupFileRepository::findNextUnpublished: Found file. Group ID: {$groupId}, File ID: {$result['id']}, Video ID: {$result['video_id']}, File status: {$result['status']}, Video status: {$videoStatus}");
        } else {
            error_log("ContentGroupFileRepository::findNextUnpublished: No unpublished file found. Group ID: {$groupId}");
            
            // Логируем все файлы в группе для диагностики
            $allFiles = $this->findByGroupId($groupId);
            error_log("ContentGroupFileRepository::findNextUnpublished: Total files in group: " . count($allFiles));
            foreach ($allFiles as $file) {
                $fileVideoStatus = isset($file['video_status']) ? $file['video_status'] : 'unknown';
                error_log("ContentGroupFileRepository::findNextUnpublished: File ID: {$file['id']}, Video ID: {$file['video_id']}, File status: {$file['status']}, Video status: {$fileVideoStatus}");
            }
        }
        
        return $result;
    }

    /**
     * Найти по группе и статусу
     */
    public function findByGroupIdAndStatus(int $groupId, string $status): array
    {
        $stmt = $this->db->prepare("
            SELECT cgf.*, v.title, v.file_name
            FROM {$this->table} cgf
            JOIN videos v ON v.id = cgf.video_id
            WHERE cgf.group_id = ? AND cgf.status = ?
            ORDER BY cgf.created_at DESC, cgf.id DESC
        ");
        $stmt->execute([$groupId, $status]);
        return $stmt->fetchAll();
    }

    /**
     * Добавить видео в группу
     */
    public function addVideoToGroup(int $groupId, int $videoId, int $orderIndex = 0): int
    {
        // Проверяем, не добавлено ли уже
        $existing = $this->db->prepare("SELECT id FROM {$this->table} WHERE group_id = ? AND video_id = ?");
        $existing->execute([$groupId, $videoId]);
        if ($existing->fetch()) {
            return 0; // Уже добавлено
        }

        // Получаем максимальный order_index
        $maxOrder = $this->db->prepare("SELECT MAX(order_index) as max_order FROM {$this->table} WHERE group_id = ?");
        $maxOrder->execute([$groupId]);
        $maxRow = $maxOrder->fetch();
        $max = isset($maxRow['max_order']) ? (int)$maxRow['max_order'] : 0;

        return $this->create([
            'group_id' => $groupId,
            'video_id' => $videoId,
            'status' => 'new',
            'order_index' => $orderIndex > 0 ? $orderIndex : ($max + 1),
        ]);
    }

    /**
     * Массовое добавление видео в группу
     */
    public function addVideosToGroup(int $groupId, array $videoIds): int
    {
        $added = 0;
        foreach ($videoIds as $videoId) {
            if ($this->addVideoToGroup($groupId, $videoId)) {
                $added++;
            }
        }
        return $added;
    }

    /**
     * Обновить статус файла в группе
     */
    public function updateFileStatus(int $id, string $status, ?int $publicationId = null): bool
    {
        try {
            $publicationIdStr = $publicationId !== null ? (string)$publicationId : 'null';
            error_log("ContentGroupFileRepository::updateFileStatus: id={$id}, status={$status}, publicationId=" . $publicationIdStr);
            
            $data = ['status' => $status];
            if ($status === 'published') {
                $data['published_at'] = date('Y-m-d H:i:s');
            }
            if ($publicationId) {
                $data['publication_id'] = $publicationId;
            }
            
            $result = $this->update($id, $data);
            
            if ($result) {
                error_log("ContentGroupFileRepository::updateFileStatus: Success - file {$id} status updated to {$status}");
            } else {
                error_log("ContentGroupFileRepository::updateFileStatus: Failed - update returned false for file {$id}");
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("ContentGroupFileRepository::updateFileStatus: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return false;
        }
    }

    /**
     * Очистить статус опубликованности для одного файла
     */
    public function clearPublicationStatus(int $id): bool
    {
        return $this->update($id, [
            'status' => 'new',
            'published_at' => null,
            'publication_id' => null,
        ]);
    }

    /**
     * Очистить статус опубликованности для списка файлов группы
     */
    public function clearPublicationStatusByIds(int $groupId, array $fileIds): int
    {
        $ids = array_values(array_unique(array_filter($fileIds, static fn($id) => (int)$id > 0)));
        if (empty($ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE {$this->table} 
                SET status = 'new', published_at = NULL, publication_id = NULL
                WHERE group_id = ? AND id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$groupId], $ids));
        return $stmt->rowCount();
    }

    /**
     * Найти группы для видео
     */
    public function findGroupsByVideoId(int $videoId): array
    {
        $sql = "SELECT cgf.*, cg.name as group_name, cg.status as group_status
                FROM {$this->table} cgf
                JOIN content_groups cg ON cgf.group_id = cg.id
                WHERE cgf.video_id = ?
                ORDER BY cg.name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$videoId]);
        return $stmt->fetchAll();
    }
}
