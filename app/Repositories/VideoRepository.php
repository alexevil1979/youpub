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

    /**
     * Поиск видео по запросу
     */
    public function search(int $userId, string $query, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = ? 
                AND (
                    title LIKE ? 
                    OR description LIKE ? 
                    OR tags LIKE ? 
                    OR file_name LIKE ?
                )
                ORDER BY created_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }
}
