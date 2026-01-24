<?php

namespace Core;

use PDO;

/**
 * Базовый репозиторий
 */
abstract class Repository
{
    protected PDO $db;
    protected string $table;

    public function __construct(string $table)
    {
        $this->db = Database::getInstance();
        $this->table = $table;
    }

    /**
     * Найти по ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Найти все
     */
    public function findAll(array $conditions = [], array $orderBy = [], int $limit = null, int $offset = null): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                $where[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                $order[] = "{$field} " . strtoupper($direction);
            }
            $sql .= " ORDER BY " . implode(", ", $order);
        }

        if ($limit !== null) {
            $sql .= " LIMIT " . (int)$limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int)$offset;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Создать запись
     */
    public function create(array $data): int
    {
        // Фильтруем NULL значения для полей, которые могут быть NULL
        // Это позволяет базе данных использовать значения по умолчанию
        $filteredData = [];
        foreach ($data as $key => $value) {
            // Включаем поле, даже если значение NULL (для явного указания NULL)
            // Но можно исключить, если нужно использовать DEFAULT
            $filteredData[$key] = $value;
        }
        
        $fields = array_keys($filteredData);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($filteredData));
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Обновить запись
     */
    public function update(int $id, array $data): bool
    {
        if (array_key_exists('publish_at', $data) && $data['publish_at'] === null) {
            $data['publish_at'] = date('Y-m-d H:i:s');
        }

        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $params[] = $value;
        }
        
        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Удалить запись
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Получить PDO для прямых запросов
     */
    public function getDb(): PDO
    {
        return $this->db;
    }
}
