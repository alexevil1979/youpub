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
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $secure = $this->isHttps();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $secure ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');

        $cookieParams = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $cookieParams['path'] ?? '/',
            'domain' => $cookieParams['domain'] ?? '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();

        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
        }
    }

    /**
     * Регистрация пользователя
     */
    public function register(string $email, string $password, string $name = null): array
    {
        $email = strtolower(trim($email));
        if (!$this->validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        $passwordValidation = $this->validatePassword($password);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => $passwordValidation['message']];
        }

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
        $email = strtolower(trim($email));
        if (!$this->validateEmail($email)) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        // Создание сессии
        $this->startSession();
        session_regenerate_id(true);
        $sessionId = bin2hex(random_bytes(32));
        $lifetime = (int)($this->config['SESSION_LIFETIME'] ?? 3600);
        $expiresAt = date('Y-m-d H:i:s', time() + $lifetime);
        $clientIp = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = $this->db->prepare(
            "INSERT INTO sessions (id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $sessionId,
            $user['id'],
            $clientIp,
            $userAgent,
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
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ? AND user_id = ? AND expires_at > NOW()");
        $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
        $session = $stmt->fetch();

        if (!$session) {
            return false;
        }

        $clientIp = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (!empty($session['ip_address']) && $session['ip_address'] !== $clientIp) {
            return false;
        }
        if (!empty($session['user_agent']) && $session['user_agent'] !== $userAgent) {
            return false;
        }

        return true;
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

    public function getUserById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, name, role, status FROM users WHERE id = ?");
        $stmt->execute([$id]);
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

    public function createJwt(array $user): string
    {
        $secret = (string)($this->config['JWT_SECRET'] ?? '');
        if ($secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured');
        }

        $now = time();
        $payload = [
            'sub' => (int)($user['id'] ?? 0),
            'email' => (string)($user['email'] ?? ''),
            'role' => (string)($user['role'] ?? 'user'),
            'iat' => $now,
            'exp' => $now + 86400,
        ];

        return $this->encodeJwt($payload, $secret);
    }

    public function validateJwt(string $token): ?array
    {
        $secret = (string)($this->config['JWT_SECRET'] ?? '');
        if ($secret === '') {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $signature = $this->base64UrlDecode($signatureB64);
        if ($signature === null) {
            return null;
        }

        $expected = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        $exp = (int)($payload['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            return null;
        }

        return $payload;
    }

    public function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        $trustedProxies = $this->config['TRUSTED_PROXIES'] ?? [];
        $trustedProxies = is_array($trustedProxies) ? $trustedProxies : [];

        if ($remoteAddr && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            $realIp = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            $candidates = array_filter(array_map('trim', explode(',', $forwarded)));
            if ($realIp !== '') {
                array_unshift($candidates, $realIp);
            }
            foreach ($candidates as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return $remoteAddr !== '' ? $remoteAddr : '0.0.0.0';
    }

    private function encodeJwt(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerB64 = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_UNICODE));
        $payloadB64 = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $signature = hash_hmac('sha256', $headerB64 . '.' . $payloadB64, $secret, true);
        $signatureB64 = $this->base64UrlEncode($signature);

        return $headerB64 . '.' . $payloadB64 . '.' . $signatureB64;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $data = strtr($data, '-_', '+/');
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($data, true);
        return $decoded === false ? null : $decoded;
    }

    private function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function validatePassword(string $password): array
    {
        if (strlen($password) < 12) {
            return ['valid' => false, 'message' => 'Password must be at least 12 characters'];
        }
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'message' => 'Password must contain uppercase, lowercase, and numbers'];
        }
        return ['valid' => true, 'message' => ''];
    }

    private function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        return !empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443;
    }
}
