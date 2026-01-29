<?php

declare(strict_types=1);

namespace Core;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Создаёт экземпляр логгера приложения (Monolog) с записью в storage/logs.
 * Уровни — целые числа PSR-3 (100 = debug, 200 = info, …) для совместимости Monolog 2 и 3.
 */
final class LoggerFactory
{
    private const DEFAULT_CHANNEL = 'app';
    private const DEFAULT_LOG_FILE = 'app.log';

    /** Уровни PSR-3 (RFC 5424) */
    private const LEVEL_DEBUG = 100;
    private const LEVEL_INFO = 200;
    private const LEVEL_NOTICE = 250;
    private const LEVEL_WARNING = 300;
    private const LEVEL_ERROR = 400;
    private const LEVEL_CRITICAL = 500;
    private const LEVEL_ALERT = 550;
    private const LEVEL_EMERGENCY = 600;

    public static function create(array $config): LoggerInterface
    {
        $logDir = $config['LOG_DIR'] ?? __DIR__ . '/../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/' . self::DEFAULT_LOG_FILE;
        $level = self::resolveLevel($config['LOG_LEVEL'] ?? 'debug');
        $logger = new Logger(self::DEFAULT_CHANNEL);
        $handler = new StreamHandler($logFile, $level, true);
        $logger->pushHandler($handler);

        return $logger;
    }

    private static function resolveLevel(string $level): int
    {
        return match (strtolower($level)) {
            'debug' => self::LEVEL_DEBUG,
            'info' => self::LEVEL_INFO,
            'notice' => self::LEVEL_NOTICE,
            'warning' => self::LEVEL_WARNING,
            'error' => self::LEVEL_ERROR,
            'critical' => self::LEVEL_CRITICAL,
            'alert' => self::LEVEL_ALERT,
            'emergency' => self::LEVEL_EMERGENCY,
            default => self::LEVEL_DEBUG,
        };
    }
}
