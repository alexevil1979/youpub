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
        $orderBy = $this->sanitizeOrderBy($orderBy, ['created_at', 'title', 'id']);
        return $this->findAll(['user_id' => $userId], $orderBy, $limit);
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

    public function countByUserId(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    private function sanitizeOrderBy(array $orderBy, array $allowedFields): array
    {
        $sanitized = [];
        foreach ($orderBy as $field => $direction) {
            if (!in_array($field, $allowedFields, true)) {
                continue;
            }
            $dir = strtoupper((string)$direction);
            $sanitized[$field] = $dir === 'DESC' ? 'DESC' : 'ASC';
        }
        return $sanitized;
    }
}
