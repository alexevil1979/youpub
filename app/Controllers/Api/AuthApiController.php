<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Auth;

/**
 * API контроллер авторизации
 */
class AuthApiController extends Controller
{
    private Auth $auth;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
    }

    /**
     * API вход
     */
    public function login(): void
    {
        $data = $this->getRequestData();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            $this->error('Email and password are required', 400);
            return;
        }

        $result = $this->auth->login($email, $password);

        if ($result['success']) {
            $this->success($result['user'], $result['message']);
        } else {
            $this->error($result['message'], 401);
        }
    }

    /**
     * API регистрация
     */
    public function register(): void
    {
        $data = $this->getRequestData();
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? null;

        if (!$email || !$password) {
            $this->error('Email and password are required', 400);
            return;
        }

        if (strlen($password) < 6) {
            $this->error('Password must be at least 6 characters', 400);
            return;
        }

        $result = $this->auth->register($email, $password, $name);

        if ($result['success']) {
            $this->success([], $result['message']);
        } else {
            $this->error($result['message'], 400);
        }
    }
}
