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
        $sql = "SELECT * FROM {$this->table} WHERE publication_id = ?";
        $params = [$publicationId];

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
}
