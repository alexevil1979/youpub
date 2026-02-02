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
        if (empty($orderBy)) {
            $orderBy = ['published_at' => 'DESC'];
        }
        return $this->findAll(['user_id' => $userId], $orderBy);
    }

    /**
     * Найти публикации пользователя с названием, описанием видео и каналом (для страницы статистики)
     */
    public function findByUserIdWithVideoInfo(int $userId, array $orderBy = []): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy, ['published_at', 'created_at', 'id']);
        if (empty($orderBy)) {
            $orderBy = ['published_at' => 'DESC'];
        }
        $orderStr = implode(', ', array_map(fn($f, $d) => "p.{$f} " . ($d === 'DESC' ? 'DESC' : 'ASC'), array_keys($orderBy), $orderBy));

        $channelJoin = $this->buildChannelJoin();
        $sql = "SELECT p.*, v.title AS video_title, v.description AS video_description, v.file_name AS video_file_name, {$channelJoin['select']}
                FROM {$this->table} p 
                LEFT JOIN videos v ON p.video_id = v.id 
                {$channelJoin['join']}
                WHERE p.user_id = ? 
                ORDER BY {$orderStr}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Сборка JOIN и SELECT для названия канала (YouTube, Telegram и т.д.)
     */
    private function buildChannelJoin(): array
    {
        $hasIntegrationId = false;
        try {
            $stmt = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'integration_id'");
            $hasIntegrationId = $stmt !== false && (bool)$stmt->fetch();
        } catch (\Throwable $e) {
            // колонки нет
        }

        if ($hasIntegrationId) {
            $join = "LEFT JOIN youtube_integrations yi ON yi.user_id = p.user_id AND p.platform = 'youtube' AND (yi.id = p.integration_id OR (p.integration_id IS NULL AND yi.is_default = 1))
                     LEFT JOIN telegram_integrations ti ON ti.user_id = p.user_id AND p.platform = 'telegram' AND (ti.id = p.integration_id OR (p.integration_id IS NULL AND ti.is_default = 1))";
            $select = "COALESCE(yi.channel_name, yi.account_name, ti.channel_username, ti.account_name) AS channel_name";
        } else {
            $join = "LEFT JOIN youtube_integrations yi ON yi.user_id = p.user_id AND p.platform = 'youtube' AND yi.is_default = 1
                     LEFT JOIN telegram_integrations ti ON ti.user_id = p.user_id AND p.platform = 'telegram' AND ti.is_default = 1";
            $select = "COALESCE(yi.channel_name, yi.account_name, ti.channel_username, ti.account_name) AS channel_name";
        }

        return ['join' => $join, 'select' => $select];
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
     * Последние успешные публикации пользователя (для дашборда)
     */
    public function findRecentSuccessfulByUserId(int $userId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE user_id = ? AND status = 'success' ORDER BY published_at DESC LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
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

    /**
     * Найти публикацию по schedule_id
     */
    public function findByScheduleId(int $scheduleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE schedule_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$scheduleId]);
        $result = $stmt->fetch();
        return $result ?: null;
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
