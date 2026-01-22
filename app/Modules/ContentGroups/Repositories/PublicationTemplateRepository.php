<?php

namespace App\Modules\ContentGroups\Repositories;

use Core\Repository;

/**
 * Репозиторий шаблонов публикаций
 */
class PublicationTemplateRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('publication_templates');
    }

    /**
     * Найти по пользователю
     */
    public function findByUserId(int $userId, bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = ?";
        $params = [$userId];

        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Найти активные шаблоны пользователя
     */
    public function findActiveByUserId(int $userId): array
    {
        return $this->findByUserId($userId, true);
    }
}
