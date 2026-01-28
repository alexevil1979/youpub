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
                    if (!$this->isValidIdentifier($field)) {
                        continue;
                    }
                    $dir = strtoupper((string)$direction);
                    $order[] = "{$field} " . ($dir === 'DESC' ? 'DESC' : 'ASC');
                }
                $sql .= " ORDER BY " . implode(", ", $order);
            } else {
                $sql .= " ORDER BY publish_at DESC";
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
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND status = ? ORDER BY publish_at DESC");
        $stmt->execute([$userId, $status]);
        return $stmt->fetchAll();
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public function countByUserIdAndStatus(int $userId, string $status): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND status = ?");
        $stmt->execute([$userId, $status]);
        return (int)$stmt->fetchColumn();
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
    public function findDueForPublishing(int $limit = 50, bool $excludeGroups = false): array
    {
        $limit = max(1, (int)$limit);
        $groupFilter = $excludeGroups ? "AND content_group_id IS NULL" : "";
        $sql = "
            SELECT * FROM {$this->table}
            WHERE status = 'pending'
            AND publish_at <= NOW()
            {$groupFilter}
            ORDER BY publish_at ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Найти активные расписания с группами
     * Включает расписания со статусом 'pending' и 'published' (если есть неопубликованные видео)
     * Ищет расписания, которые либо имеют content_group_id, либо используются группами через schedule_id
     */
    public function findActiveGroupSchedules(int $limit = 50): array
    {
        $limit = max(1, $limit);
        $sql = "
            SELECT DISTINCT s.*, 
                   COALESCE(cg1.name, cg2.name) as group_name, 
                   COALESCE(cg1.status, cg2.status) as group_status
            FROM {$this->table} s
            LEFT JOIN content_groups cg1 ON cg1.id = s.content_group_id AND cg1.status = 'active'
            LEFT JOIN content_groups cg2 ON cg2.schedule_id = s.id AND cg2.status = 'active'
            WHERE s.status IN ('pending', 'published')
            AND s.video_id IS NULL
            AND (
                -- Расписание с content_group_id (старая логика)
                (s.content_group_id IS NOT NULL AND cg1.id IS NOT NULL)
                OR
                -- Расписание используется группами через schedule_id
                (cg2.id IS NOT NULL)
            )
            AND (
                -- Для pending расписаний: 
                --   * для фиксированных (fixed) учитываем время publish_at
                --   * для интервальных/пакетных/случайных/волновых доверяем ScheduleEngineService::isScheduleReady
                (s.status = 'pending' AND (
                    s.schedule_type IN ('interval','batch','random','wave')
                    OR s.publish_at <= NOW()
                    OR s.publish_at IS NULL
                ))
                OR
                -- Для published расписаний: проверяем наличие неопубликованных видео
                (s.status = 'published' AND EXISTS (
                    SELECT 1 FROM content_group_files cgf
                    JOIN videos v ON v.id = cgf.video_id
                    WHERE (cgf.group_id = s.content_group_id OR cgf.group_id IN (
                        SELECT id FROM content_groups WHERE schedule_id = s.id
                    ))
                    AND cgf.status IN ('new', 'queued', 'paused')
                    AND v.status IN ('uploaded', 'ready')
                ))
            )
            ORDER BY s.publish_at ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Очистить зависшие расписания 'processing' (старше указанного времени)
     */
    public function cleanupStuckProcessing(int $minutes = 10): int
    {
        // Для расписаний, связанных с группами контента, мы не хотим
        // окончательно помечать их как 'failed', так как это "долгоживущие"
        // интервальные / пакетные / групповые расписания.
        // Вместо этого возвращаем их в 'pending', чтобы воркер мог
        // продолжить работу после таймаута.
        //
        // Для обычных одиночных расписаний (без content_group_id и без использования
        // через schedule_id в content_groups) оставляем прежнюю логику: 'failed'.

        // 1) Сначала помечаем как 'pending' все зависшие групповые расписания
        $sqlGroups = "
            UPDATE {$this->table}
            SET status = 'pending',
                error_message = CONCAT('Processing timeout (', ?, ' minutes)')
            WHERE status = 'processing'
              AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
              AND (
                    content_group_id IS NOT NULL
                    OR id IN (SELECT schedule_id FROM content_groups WHERE schedule_id IS NOT NULL)
              )
        ";
        $stmtGroups = $this->db->prepare($sqlGroups);
        $stmtGroups->execute([$minutes, $minutes]);
        $groupsUpdated = $stmtGroups->rowCount();

        // 2) Остальные зависшие расписания помечаем как 'failed'
        $sqlSingle = "
            UPDATE {$this->table}
            SET status = 'failed',
                error_message = CONCAT('Processing timeout (', ?, ' minutes)')
            WHERE status = 'processing'
              AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
              AND content_group_id IS NULL
              AND id NOT IN (SELECT schedule_id FROM content_groups WHERE schedule_id IS NOT NULL)
        ";
        $stmtSingle = $this->db->prepare($sqlSingle);
        $stmtSingle->execute([$minutes, $minutes]);
        $singleUpdated = $stmtSingle->rowCount();

        return $groupsUpdated + $singleUpdated;
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

    /**
     * Найти последнее расписание для списка групп
     */
    public function findLatestByGroupIds(array $groupIds): array
    {
        $ids = array_values(array_unique(array_filter($groupIds, static fn($id) => (int)$id > 0)));
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM {$this->table}
                WHERE content_group_id IN ({$placeholders})
                ORDER BY publish_at DESC, created_at DESC, id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $latest = [];
        foreach ($rows as $row) {
            $groupId = (int)($row['content_group_id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }
            if (!isset($latest[$groupId])) {
                $latest[$groupId] = $row;
            }
        }

        return $latest;
    }
}
