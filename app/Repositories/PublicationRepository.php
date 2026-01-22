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
     * Найти по video_id
     */
    public function findByVideoId(int $videoId, array $orderBy = []): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE video_id = ?";
        $params = [$videoId];

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
     * Найти успешные публикации по video_id
     */
    public function findSuccessfulByVideoId(int $videoId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE video_id = ? AND status = 'success' ORDER BY published_at DESC");
        $stmt->execute([$videoId]);
        return $stmt->fetchAll();
    }
}
