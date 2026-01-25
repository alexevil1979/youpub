<?php

namespace Core;

/**
 * Базовый контроллер
 */
abstract class Controller
{
    protected array $config = [];

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/env.php';
    }

    /**
     * Отправить JSON ответ
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Отправить успешный ответ
     */
    protected function success(array $data = [], string $message = 'Success'): void
    {
        $this->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Отправить ошибку
     */
    protected function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $this->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $statusCode);
    }

    /**
     * Получить данные запроса
     */
    protected function getRequestData(): array
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return $data ?: [];
    }

    /**
     * Получить параметр из GET/POST
     */
    protected function getParam(string $key, $default = null)
    {
        return $_GET[$key] ?? $_POST[$key] ?? $default;
    }

    /**
     * Валидация CSRF токена
     */
    protected function validateCsrf(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        $sessionToken = $_SESSION['csrf_token'] ?? null;

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            return false;
        }

        return true;
    }

    /**
     * Проверка авторизации
     */
    protected function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
            if (strpos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
                $auth = new \Core\Auth();
                $payload = $auth->validateJwt($token);
                if ($payload && !empty($payload['sub'])) {
                    $user = $auth->getUserById((int)$payload['sub']);
                    if ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'];
                        return;
                    }
                }
            }
            $this->error('Unauthorized', 401);
            exit;
        }
    }

    /**
     * Проверка роли администратора
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        $auth = new \Core\Auth();
        $user = $auth->user();
        if (!$user || $user['role'] !== 'admin') {
            $this->error('Forbidden', 403);
            exit;
        }
    }
}
