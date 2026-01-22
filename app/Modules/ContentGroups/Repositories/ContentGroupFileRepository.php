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
            $sql .= " ORDER BY cgf.order_index ASC, cgf.created_at ASC";
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
            AND cgf.status IN ('new', 'queued')
            AND v.status = 'uploaded'
            ORDER BY cgf.order_index ASC, cgf.created_at ASC
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$groupId]);
        return $stmt->fetch() ?: null;
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
            ORDER BY cgf.order_index ASC
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
        $max = $maxOrder->fetch()['max_order'] ?? 0;

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
        $data = ['status' => $status];
        if ($status === 'published') {
            $data['published_at'] = date('Y-m-d H:i:s');
        }
        if ($publicationId) {
            $data['publication_id'] = $publicationId;
        }
        return $this->update($id, $data);
    }
}
