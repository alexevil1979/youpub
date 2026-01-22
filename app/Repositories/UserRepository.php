<?php

namespace App\Repositories;

use Core\Repository;

/**
 * Репозиторий пользователей
 */
class UserRepository extends Repository
{
    public function __construct()
    {
        parent::__construct('users');
    }

    /**
     * Найти по email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }
}
