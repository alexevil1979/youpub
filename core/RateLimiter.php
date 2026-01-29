<?php

declare(strict_types=1);

namespace Core;

/**
 * Простой файловый rate limiter.
 */
class RateLimiter
{
    private string $storagePath;

    public function __construct(?string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../storage/rate_limits';
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0775, true);
        }
    }

    public function check(string $key, int $limit, int $windowSeconds, bool $increment = false): array
    {
        $data = $this->load($key);
        $now = time();

        if ($data['reset_at'] <= $now) {
            $data = ['count' => 0, 'reset_at' => $now + $windowSeconds];
        }

        $allowed = $data['count'] < $limit;
        if ($increment && $allowed) {
            $data['count']++;
            $this->save($key, $data);
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $limit - $data['count']),
            'reset_at' => $data['reset_at'],
        ];
    }

    public function clear(string $key): void
    {
        $path = $this->getPath($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function hit(string $key, int $limit, int $windowSeconds): array
    {
        return $this->check($key, $limit, $windowSeconds, true);
    }

    private function getPath(string $key): string
    {
        return $this->storagePath . '/' . hash('sha256', $key) . '.json';
    }

    private function load(string $key): array
    {
        $path = $this->getPath($key);
        if (!is_file($path)) {
            return ['count' => 0, 'reset_at' => 0];
        }

        $contents = @file_get_contents($path);
        $data = $contents ? json_decode($contents, true) : null;
        if (!is_array($data)) {
            return ['count' => 0, 'reset_at' => 0];
        }

        return [
            'count' => (int)($data['count'] ?? 0),
            'reset_at' => (int)($data['reset_at'] ?? 0),
        ];
    }

    private function save(string $key, array $data): void
    {
        $path = $this->getPath($key);
        $payload = json_encode([
            'count' => (int)($data['count'] ?? 0),
            'reset_at' => (int)($data['reset_at'] ?? 0),
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return;
        }

        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $path);
        }
    }
}
