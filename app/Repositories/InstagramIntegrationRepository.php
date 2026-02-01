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
        try {
            if ($this->hasDefaultColumn()) {
                $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
            } else {
                $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? ORDER BY created_at DESC");
            }
            $stmt->execute([$userId]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            error_log("InstagramIntegrationRepository::findByUserId: Exception - " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$userId]);
                return $stmt->fetchAll();
            } catch (\Exception $e2) {
                error_log("InstagramIntegrationRepository::findByUserId: Fallback also failed - " . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * Найти аккаунт по умолчанию
     */
    public function findDefaultByUserId(int $userId): ?array
    {
        try {
            if ($this->hasDefaultColumn()) {
                $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? AND is_default = 1 AND status = 'connected' LIMIT 1");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                if ($result) {
                    return $result;
                }
            }
            
            // Если нет аккаунта с is_default = 1, берем первый подключенный
            $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? AND status = 'connected' ORDER BY created_at ASC LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetch() ?: null;
        } catch (\Exception $e) {
            error_log("InstagramIntegrationRepository::findDefaultByUserId: Exception - " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("SELECT * FROM `{$this->table}` WHERE user_id = ? AND status = 'connected' ORDER BY created_at ASC LIMIT 1");
                $stmt->execute([$userId]);
                return $stmt->fetch() ?: null;
            } catch (\Exception $e2) {
                error_log("InstagramIntegrationRepository::findDefaultByUserId: Fallback also failed - " . $e2->getMessage());
                return null;
            }
        }
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
        $stmt = $this->db->prepare("UPDATE `{$this->table}` SET is_default = 0 WHERE user_id = ?");
        $stmt->execute([$userId]);
        $stmt = $this->db->prepare("UPDATE `{$this->table}` SET is_default = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        return $stmt->rowCount() > 0;
    }

    private function hasDefaultColumn(): bool
    {
        $stmt = $this->db->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'is_default'");
        return $stmt !== false && (bool)$stmt->fetch();
    }
}
