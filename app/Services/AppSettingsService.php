<?php

namespace App\Services;

use App\Repositories\AppSettingsRepository;

/**
 * Сервис для доступа к глобальным настройкам приложения с простым кэшем в памяти.
 */
class AppSettingsService
{
    private static ?self $instance = null;

    private AppSettingsRepository $repo;
    /** @var array<string,string> */
    private array $cache = [];
    private bool $loaded = false;

    private function __construct()
    {
        $this->repo = new AppSettingsRepository();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Получить строковое значение настройки
     */
    public function get(string $key, ?string $default = null): ?string
    {
        if (!$this->loaded) {
            $this->cache = $this->repo->getAll();
            $this->loaded = true;
        }

        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        return $default;
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return (int)$value;
    }

    public function getBool(string $key, bool $default): bool
    {
        $value = $this->get($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Обновить несколько настроек и сбросить кэш
     *
     * @param array<string,string> $values
     */
    public function updateMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->repo->set($key, $value);
        }

        $this->loaded = false;
        $this->cache = [];
    }
}

