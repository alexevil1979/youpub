<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий расписаний
 */
class ScheduleRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('schedules');
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
            $sql .= " ORDER BY publish_at ASC";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Найти расписания, готовые к публикации
     */
    public function findDueForPublishing(): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE status = 'pending'
            AND publish_at <= NOW()
            ORDER BY publish_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Найти активные расписания с группами
     */
    public function findActiveGroupSchedules(): array
    {
        $sql = "
            SELECT s.*, cg.name as group_name, cg.status as group_status
            FROM {$this->table} s
            JOIN content_groups cg ON cg.id = s.content_group_id
            WHERE s.status = 'pending'
            AND cg.status = 'active'
            AND s.content_group_id IS NOT NULL
            ORDER BY s.publish_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
