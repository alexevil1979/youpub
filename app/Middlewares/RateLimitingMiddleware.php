<?php

namespace App\Middlewares;

use Core\RateLimiter;
use Core\Auth;

/**
 * Middleware для ограничения частоты запросов (Rate Limiting)
 * Защищает от DDoS атак и злоупотреблений
 */
class RateLimitingMiddleware
{
    private RateLimiter $rateLimiter;
    private Auth $auth;
    private int $limit;
    private int $windowSeconds;
    private string $keyPrefix;

    /**
     * @param int $limit Количество запросов
     * @param int $windowSeconds Окно времени в секундах
     * @param string $keyPrefix Префикс для ключа rate limit
     */
    public function __construct(int $limit = 100, int $windowSeconds = 3600, string $keyPrefix = 'rate_limit')
    {
        $this->rateLimiter = new RateLimiter();
        $this->auth = new Auth();
        $this->limit = $limit;
        $this->windowSeconds = $windowSeconds;
        $this->keyPrefix = $keyPrefix;
    }

    /**
     * Обработка middleware
     * @return bool true если запрос разрешен, false если превышен лимит
     */
    public function handle(): bool
    {
        $key = $this->generateKey();
        $result = $this->rateLimiter->hit($key, $this->limit, $this->windowSeconds);

        if (!$result['allowed']) {
            $this->sendRateLimitError($result);
            return false;
        }

        // Добавляем заголовки с информацией о rate limit
        $this->addRateLimitHeaders($result);
        return true;
    }

    /**
     * Генерация ключа для rate limiting
     * Использует IP адрес и, если пользователь авторизован, его ID
     */
    private function generateKey(): string
    {
        $ip = $this->auth->getClientIp();
        $userId = $_SESSION['user_id'] ?? null;
        
        // Если пользователь авторизован, используем его ID для более точного лимитирования
        if ($userId) {
            return $this->keyPrefix . ':user:' . $userId . ':' . $ip;
        }
        
        // Для неавторизованных пользователей используем только IP
        return $this->keyPrefix . ':ip:' . $ip;
    }

    /**
     * Отправка ошибки при превышении лимита
     */
    private function sendRateLimitError(array $result): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0 ||
                 (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        http_response_code(429); // Too Many Requests

        $resetAt = $result['reset_at'] ?? time() + $this->windowSeconds;
        $retryAfter = max(1, $resetAt - time());

        // Добавляем заголовки
        header('X-RateLimit-Limit: ' . $this->limit);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $resetAt);
        header('Retry-After: ' . $retryAfter);

        if ($isAjax || $isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Превышен лимит запросов. Попробуйте позже.',
                'retry_after' => $retryAfter,
                'reset_at' => $resetAt
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // Для обычных запросов показываем HTML страницу
            $this->showRateLimitPage($retryAfter);
        }
    }

    /**
     * Добавление заголовков с информацией о rate limit
     */
    private function addRateLimitHeaders(array $result): void
    {
        $resetAt = $result['reset_at'] ?? time() + $this->windowSeconds;
        header('X-RateLimit-Limit: ' . $this->limit);
        header('X-RateLimit-Remaining: ' . ($result['remaining'] ?? 0));
        header('X-RateLimit-Reset: ' . $resetAt);
    }

    /**
     * Показать HTML страницу с ошибкой rate limit
     */
    private function showRateLimitPage(int $retryAfter): void
    {
        $minutes = ceil($retryAfter / 60);
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Слишком много запросов</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 2rem;
                    border-radius: 12px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    text-align: center;
                    max-width: 500px;
                }
                h1 {
                    color: #e74c3c;
                    margin-top: 0;
                }
                .retry-time {
                    font-size: 1.2rem;
                    color: #666;
                    margin: 1rem 0;
                }
                .btn {
                    display: inline-block;
                    padding: 0.75rem 1.5rem;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    margin-top: 1rem;
                }
                .btn:hover {
                    background: #5568d3;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>⚠️ Слишком много запросов</h1>
                <p>Вы превысили лимит запросов. Пожалуйста, подождите перед повторной попыткой.</p>
                <div class="retry-time">
                    Попробуйте снова через: <strong><?= $minutes ?> <?= $minutes === 1 ? 'минуту' : ($minutes < 5 ? 'минуты' : 'минут') ?></strong>
                </div>
                <a href="/" class="btn">Вернуться на главную</a>
            </div>
        </body>
        </html>
        <?php
    }
}
