<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий интеграций Instagram
 */
class InstagramIntegrationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('instagram_integrations');
    }

    /**
     * Найти по пользователю
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }
}
