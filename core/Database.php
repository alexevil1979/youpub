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
                // Используем 127.0.0.1 вместо localhost для TCP/IP соединения
                $host = self::$config['DB_HOST'];
                if ($host === 'localhost') {
                    $host = '127.0.0.1';
                }
                
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    $host,
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
                
                // Устанавливаем часовой пояс для MySQL
                $timezone = self::$config['TIMEZONE'] ?? 'Europe/Samara';
                try {
                    $dt = new \DateTime('now', new \DateTimeZone($timezone));
                    $offset = $dt->getOffset();
                    $hours = floor(abs($offset) / 3600);
                    $minutes = (abs($offset) % 3600) / 60;
                    $sign = $offset >= 0 ? '+' : '-';
                    $offsetStr = sprintf('%s%02d:%02d', $sign, $hours, $minutes);
                    self::$instance->exec("SET time_zone = '{$offsetStr}'");
                } catch (\Exception $e) {
                    error_log("Failed to set MySQL timezone: " . $e->getMessage());
                    // Используем UTC по умолчанию
                    self::$instance->exec("SET time_zone = '+00:00'");
                }
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
