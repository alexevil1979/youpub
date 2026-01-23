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
        try {
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
            if (!$stmt) {
                error_log("ScheduleRepository::findByUserId: Failed to prepare statement. SQL: {$sql}");
                return [];
            }
            
            $result = $stmt->execute($params);
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("ScheduleRepository::findByUserId: Execute failed: " . print_r($errorInfo, true));
                return [];
            }
            
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!is_array($data)) {
                error_log("ScheduleRepository::findByUserId: fetchAll returned non-array: " . gettype($data));
                return [];
            }
            
            return $data;
        } catch (\Exception $e) {
            error_log("ScheduleRepository::findByUserId: Exception - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [];
        }
    }

    /**
     * Найти по пользователю и статусу
     */
    public function findByUserIdAndStatus(int $userId, string $status): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND status = ? ORDER BY publish_at ASC");
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
     * Найти расписания, готовые к публикации
     */
    public function findDueForPublishing(): array
    {
        $sql = "
            SELECT * FROM {$this->table}
            WHERE status = 'pending'
            AND status <> 'paused'
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
            AND s.status <> 'paused'
            AND cg.status = 'active'
            AND s.content_group_id IS NOT NULL
            ORDER BY s.publish_at ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Поиск расписаний по запросу
     */
    public function search(int $userId, string $query, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';
        $sql = "SELECT s.* FROM {$this->table} s
                LEFT JOIN videos v ON s.video_id = v.id
                WHERE s.user_id = ? 
                AND (
                    s.platform LIKE ? 
                    OR s.status LIKE ? 
                    OR v.title LIKE ? 
                    OR v.description LIKE ?
                )
                ORDER BY s.created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Найти расписания для группы контента
     */
    public function findByGroupId(int $groupId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE content_group_id = ? 
            AND status = 'pending'
            AND publish_at >= NOW()
            ORDER BY publish_at ASC
        ");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
}
