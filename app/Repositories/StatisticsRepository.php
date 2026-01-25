<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий статистики
 */
class StatisticsRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('statistics');
    }

    /**
     * Найти по публикации и дате
     */
    public function findByPublicationAndDate(int $publicationId, string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE publication_id = ? 
             AND DATE(collected_at) = ? 
             ORDER BY collected_at DESC LIMIT 1"
        );
        $stmt->execute([$publicationId, $date]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Найти по публикации
     */
    public function findByPublicationId(int $publicationId, array $orderBy = []): array
    {
        $orderBy = $this->sanitizeOrderBy($orderBy, ['collected_at', 'id']);
        if (empty($orderBy)) {
            $orderBy = ['collected_at' => 'DESC'];
        }
        return $this->findAll(['publication_id' => $publicationId], $orderBy);
    }

    public function findLatestByPublicationIds(array $publicationIds): array
    {
        $publicationIds = array_values(array_filter(array_map('intval', $publicationIds), fn($id) => $id > 0));
        if (empty($publicationIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($publicationIds), '?'));
        $sql = "SELECT * FROM {$this->table} WHERE publication_id IN ({$placeholders}) ORDER BY collected_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($publicationIds);
        $rows = $stmt->fetchAll();

        $latest = [];
        foreach ($rows as $row) {
            $pubId = (int)($row['publication_id'] ?? 0);
            if ($pubId <= 0) {
                continue;
            }
            if (!isset($latest[$pubId])) {
                $latest[$pubId] = $row;
            }
        }

        return $latest;
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
