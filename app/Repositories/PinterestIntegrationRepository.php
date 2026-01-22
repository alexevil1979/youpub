<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий интеграций Pinterest
 */
class PinterestIntegrationRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('pinterest_integrations');
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
