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
     * Найти по пользователю (все аккаунты)
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Найти аккаунт по умолчанию
     */
    public function findDefaultByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE user_id = ? AND is_default = 1 AND status = 'connected' LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Найти по ID интеграции
     */
    public function findByIdAndUserId(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Установить аккаунт по умолчанию
     */
    public function setDefault(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stmt = $this->db->prepare("UPDATE {$this->table} SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }
}
