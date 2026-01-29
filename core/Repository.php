<?php

declare(strict_types=1);

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
        if (!$this->isValidIdentifier($table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }
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
                if (!$this->isValidIdentifier($key)) {
                    throw new \InvalidArgumentException('Invalid column name');
                }
                $where[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        if (!empty($orderBy)) {
            $order = [];
            foreach ($orderBy as $field => $direction) {
                if (!$this->isValidIdentifier($field)) {
                    throw new \InvalidArgumentException('Invalid order field');
                }
                $dir = strtoupper((string)$direction);
                if (!in_array($dir, ['ASC', 'DESC'], true)) {
                    $dir = 'ASC';
                }
                $order[] = "{$field} {$dir}";
            }
            $sql .= " ORDER BY " . implode(", ", $order);
        }

        if ($limit !== null) {
            $limit = max(0, (int)$limit);
            $sql .= " LIMIT " . $limit;
            if ($offset !== null) {
                $offset = max(0, (int)$offset);
                $sql .= " OFFSET " . $offset;
            }
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countAll(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $key => $value) {
                if (!$this->isValidIdentifier($key)) {
                    throw new \InvalidArgumentException('Invalid column name');
                }
                $where[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
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
            if (!$this->isValidIdentifier($key)) {
                throw new \InvalidArgumentException('Invalid column name');
            }
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
        $fields = [];
        $params = [];
        
        foreach ($data as $key => $value) {
            if (!$this->isValidIdentifier($key)) {
                throw new \InvalidArgumentException('Invalid column name');
            }
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

    protected function isValidIdentifier(string $name): bool
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }
}
