<?php

namespace Core;

use PDO;
use PDOException;

/**
 * Класс для работы с базой данных
 */
class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    /**
     * Инициализация подключения
     */
    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * Получить экземпляр PDO (Singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::$config['DB_HOST'],
                    self::$config['DB_NAME'],
                    self::$config['DB_CHARSET']
                );

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$instance = new PDO(
                    $dsn,
                    self::$config['DB_USER'],
                    self::$config['DB_PASS'],
                    $options
                );
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Database connection failed');
            }
        }

        return self::$instance;
    }

    /**
     * Закрыть соединение
     */
    public static function close(): void
    {
        self::$instance = null;
    }
}
