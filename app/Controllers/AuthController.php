<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;
use Core\RateLimiter;

/**
 * Контроллер авторизации
 */
class AuthController extends Controller
{
    private Auth $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
    }

    /**
     * Показать форму входа
     */
    public function showLogin(): void
    {
        if ($this->auth->check()) {
            header('Location: /dashboard');
            exit;
        }
        
        $csrfToken = $this->auth->generateCsrfToken();
        include __DIR__ . '/../../views/auth/login.php';
    }

    /**
     * Вход пользователя
     */
    public function login(): void
    {
        if (!$this->validateCsrf()) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->error('Invalid CSRF token', 403);
            } else {
                $_SESSION['error'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
                header('Location: /login');
            }
            return;
        }

        $emailInput = trim((string)$this->getParam('email', ''));
        $password = (string)$this->getParam('password', '');
        $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL) ?: '';

        if (!$email || !$password) {
            $this->error('Email and password are required');
            return;
        }

        $rateLimiter = new RateLimiter();
        $ip = $this->auth->getClientIp();
        $rateKey = 'auth_login:' . strtolower($email) . ':' . $ip;
        $rate = $rateLimiter->check($rateKey, 5, 600);
        if (!$rate['allowed']) {
            $this->error('Too many attempts. Please try again later.', 429);
            return;
        }

        $result = $this->auth->login($email, $password);

        if ($result['success']) {
            $rateLimiter->clear($rateKey);
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success($result['user'], $result['message']);
            } else {
                header('Location: /dashboard');
            }
        } else {
            $rateLimiter->hit($rateKey, 5, 600);
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->error($result['message'], 401);
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: /login');
            }
        }
    }

    /**
     * Показать форму регистрации
     */
    public function showRegister(): void
    {
        if ($this->auth->check()) {
            header('Location: /dashboard');
            exit;
        }
        
        $csrfToken = $this->auth->generateCsrfToken();
        include __DIR__ . '/../../views/auth/register.php';
    }

    /**
     * Регистрация пользователя
     */
    public function register(): void
    {
        if (!$this->validateCsrf()) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->error('Invalid CSRF token', 403);
            } else {
                $_SESSION['error'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
                header('Location: /register');
            }
            return;
        }

        $emailInput = trim((string)$this->getParam('email', ''));
        $password = (string)$this->getParam('password', '');
        $name = trim((string)$this->getParam('name', ''));
        $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL) ?: '';

        if (!$email || !$password) {
            $this->error('Email and password are required');
            return;
        }

        if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $this->error('Password must be at least 12 characters and contain uppercase, lowercase, and numbers');
            return;
        }

        $rateLimiter = new RateLimiter();
        $ip = $this->auth->getClientIp();
        $rateKey = 'auth_register:' . strtolower($email) . ':' . $ip;
        $rate = $rateLimiter->check($rateKey, 3, 3600);
        if (!$rate['allowed']) {
            $this->error('Too many attempts. Please try again later.', 429);
            return;
        }

        $result = $this->auth->register($email, $password, $name);

        if ($result['success']) {
            $rateLimiter->clear($rateKey);
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success([], $result['message']);
            } else {
                $_SESSION['success'] = $result['message'];
                header('Location: /login');
            }
        } else {
            $rateLimiter->hit($rateKey, 3, 3600);
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->error($result['message'], 400);
            } else {
                $_SESSION['error'] = $result['message'];
                header('Location: /register');
            }
        }
    }

    /**
     * Выход пользователя
     */
    public function logout(): void
    {
        $this->auth->logout();
        header('Location: /login');
        exit;
    }
}
