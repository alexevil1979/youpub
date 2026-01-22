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
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Найти по пользователю и статусу
     */
    public function findByUserIdAndStatus(int $userId, string $status): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND status = ?");
        $stmt->execute([$userId, $status]);
        return $stmt->fetchAll();
    }

    /**
     * Найти предстоящие расписания
     */
    public function findUpcoming(int $userId, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = ? AND status = 'pending' AND publish_at > NOW() 
             ORDER BY publish_at ASC LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Найти расписания для публикации
     */
    public function findDueForPublishing(): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = 'pending' AND publish_at <= NOW()"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
