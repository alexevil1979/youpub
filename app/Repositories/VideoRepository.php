<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий видео
 */
class VideoRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('videos');
    }

    /**
     * Найти по пользователю
     */
    public function findByUserId(int $userId, array $orderBy = [], int $limit = null): array
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

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
