<?php

namespace App\Controllers;

use Core\Controller;
use Core\Auth;

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
        $email = $this->getParam('email');
        $password = $this->getParam('password');

        if (!$email || !$password) {
            $this->error('Email and password are required');
            return;
        }

        $result = $this->auth->login($email, $password);

        if ($result['success']) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success($result['user'], $result['message']);
            } else {
                header('Location: /dashboard');
            }
        } else {
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
        $email = $this->getParam('email');
        $password = $this->getParam('password');
        $name = $this->getParam('name');

        if (!$email || !$password) {
            $this->error('Email and password are required');
            return;
        }

        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters');
            return;
        }

        $result = $this->auth->register($email, $password, $name);

        if ($result['success']) {
            if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
                $this->success([], $result['message']);
            } else {
                $_SESSION['success'] = $result['message'];
                header('Location: /login');
            }
        } else {
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
