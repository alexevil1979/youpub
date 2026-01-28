<?php

namespace App\Repositories;

use Core\Database;
use PDO;

/**
 * Репозиторий глобальных настроек приложения (таблица app_settings)
 */
class AppSettingsRepository
{
    private PDO $db;
    private string $table = 'app_settings';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Получить все настройки в виде [key => value]
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT `key`, `value` FROM {$this->table}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }

    /**
     * Получить значение настройки
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = $this->db->prepare("SELECT `value` FROM {$this->table} WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? $value : $default;
    }

    /**
     * Установить значение настройки
     */
    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (`key`, `value`) 
            VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ");
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }
}

