<?php

declare(strict_types=1);

namespace Core;

use PDO;

/**
 * Базовый сервис
 */
abstract class Service
{
    protected PDO $db;
    protected array $config = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config/env.php';
    }
}
