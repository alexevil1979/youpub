<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий публикаций
 */
class PublicationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('publications');
    }

    /**
     * Найти по пользователю
     */
    public function findByUserId(int $userId, array $orderBy = []): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy, ['published_at', 'created_at', 'id']);
        return $this->findAll(['user_id' => $userId], $orderBy);
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
     * Найти по video_id
     */
    public function findByVideoId(int $videoId, array $orderBy = []): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy, ['created_at', 'published_at', 'id']);
        if (empty($orderBy)) {
            $orderBy = ['created_at' => 'DESC'];
        }
        return $this->findAll(['video_id' => $videoId], $orderBy);
    }

    /**
     * Найти успешные публикации по video_id
     */
    public function findSuccessfulByVideoId(int $videoId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE video_id = ? AND status = 'success' ORDER BY published_at DESC");
        $stmt->execute([$videoId]);
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

    public function findByUserIdSince(int $userId, string $since, array $orderBy = []): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy, ['published_at', 'created_at', 'id']);
        if (empty($orderBy)) {
            $orderBy = ['published_at' => 'DESC'];
        }

        $sql = "SELECT * FROM {$this->table} WHERE user_id = ? AND published_at >= ? ORDER BY " .
            implode(', ', array_map(fn($field, $dir) => "{$field} {$dir}", array_keys($orderBy), $orderBy));
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $since]);
        return $stmt->fetchAll();
    }

    /**
     * Поиск публикаций по запросу
     */
    public function search(int $userId, string $query, int $limit = 10): array
    {
        $searchTerm = '%' . $query . '%';
        $sql = "SELECT p.* FROM {$this->table} p
                LEFT JOIN videos v ON p.video_id = v.id
                WHERE p.user_id = ? 
                AND (
                    p.platform LIKE ? 
                    OR p.status LIKE ? 
                    OR v.title LIKE ? 
                    OR v.description LIKE ?
                )
                ORDER BY p.published_at DESC 
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
        return $stmt->fetchAll();
    }

    public function findLatestSuccessfulByVideoIds(array $videoIds): array
    {
        $videoIds = array_values(array_filter(array_map('intval', $videoIds), fn($id) => $id > 0));
        if (empty($videoIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($videoIds), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE video_id IN ({$placeholders}) AND status = 'success' ORDER BY published_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($videoIds);
        $rows = $stmt->fetchAll();

        $latestByVideo = [];
        foreach ($rows as $row) {
            $videoId = (int)($row['video_id'] ?? 0);
            if ($videoId <= 0) {
                continue;
            }
            if (!isset($latestByVideo[$videoId])) {
                $latestByVideo[$videoId] = $row;
            }
        }

        return $latestByVideo;
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
