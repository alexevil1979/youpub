<?php

namespace Core;

use PDO;

/**
 * Класс для работы с авторизацией
 */
class Auth
{
    private PDO $db;
    private array $config;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = require __DIR__ . '/../config/env.php';
    }

    /**
     * Начать сессию
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Регистрация пользователя
     */
    public function register(string $email, string $password, string $name = null): array
    {
        // Проверка существования email
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already exists'];
        }

        // Создание пользователя
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            "INSERT INTO users (email, password_hash, name, role, status) VALUES (?, ?, ?, 'user', 'active')"
        );
        $stmt->execute([$email, $passwordHash, $name]);

        return ['success' => true, 'message' => 'User registered successfully'];
    }

    /**
     * Вход пользователя
     */
    public function login(string $email, string $password): array
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Создание сессии
        $this->startSession();
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['SESSION_LIFETIME']);

        $stmt = $this->db->prepare(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $sessionId,
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $expiresAt
        ]);

        $_SESSION['session_id'] = $sessionId;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
            ]
        ];
    }

    /**
     * Выход пользователя
     */
    public function logout(): void
    {
        $this->startSession();
        
        if (isset($_SESSION['session_id'])) {
            try {
                $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
                $stmt->execute([$_SESSION['session_id']]);
            } catch (\Exception $e) {
                error_log('Logout error: ' . $e->getMessage());
            }
        }

        // Очищаем все данные сессии
        $_SESSION = [];
        
        // Удаляем cookie сессии
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }

    /**
     * Проверка авторизации
     */
    public function check(): bool
    {
        $this->startSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }

        // Проверка сессии в БД
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ? AND expires_at > NOW()");
        $stmt->execute([$_SESSION['session_id']]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Получить текущего пользователя
     */
    public function user(): ?array
    {
        if (!$this->check()) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, email, name, role, status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Генерация CSRF токена
     */
    public function generateCsrfToken(): string
    {
        $this->startSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}
