<?php

namespace App\Modules\ContentGroups\Repositories;

use Core\Repository;

/**
 * Репозиторий групп контента
 */
class ContentGroupRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('content_groups');
    }

    /**
     * Найти по пользователю
     */
    public function findByUserId(int $userId, array $orderBy = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                $order[] = "{$field} " . strtoupper($direction);
            }
            $sql .= " ORDER BY " . implode(", ", $order);
        } else {
            $sql .= " ORDER BY created_at DESC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Найти активные группы пользователя
     */
    public function findActiveByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Получить статистику группы
     */
    public function getGroupStats(int $groupId): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_files,
                SUM(CASE WHEN cgf.status = 'published' THEN 1 ELSE 0 END) as published_count,
                SUM(CASE WHEN cgf.status = 'queued' THEN 1 ELSE 0 END) as queued_count,
                SUM(CASE WHEN cgf.status = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN cgf.status = 'new' THEN 1 ELSE 0 END) as new_count
            FROM content_group_files cgf
            WHERE cgf.group_id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$groupId]);
        return $stmt->fetch() ?: [];
    }
}
