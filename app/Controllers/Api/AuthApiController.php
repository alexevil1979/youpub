<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Auth;
use Core\RateLimiter;

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
        $emailInput = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL) ?: '';

        if (!$email || !$password) {
            $this->error('Email and password are required', 400);
            return;
        }

        $rateLimiter = new RateLimiter();
        $ip = $this->auth->getClientIp();
        $rateKey = 'api_auth_login:' . strtolower($email) . ':' . $ip;
        $rate = $rateLimiter->check($rateKey, 10, 600);
        if (!$rate['allowed']) {
            $this->error('Too many attempts. Please try again later.', 429);
            return;
        }

        $result = $this->auth->login($email, $password);

        if ($result['success']) {
            $rateLimiter->clear($rateKey);
            try {
                $token = $this->auth->createJwt($result['user']);
            } catch (\Throwable $e) {
                error_log('JWT creation failed: ' . $e->getMessage());
                $this->error('Token creation failed', 500);
                return;
            }
            $this->success([
                'user' => $result['user'],
                'token' => $token
            ], $result['message']);
        } else {
            $rateLimiter->hit($rateKey, 10, 600);
            $this->error($result['message'], 401);
        }
    }

    /**
     * API регистрация
     */
    public function register(): void
    {
        $data = $this->getRequestData();
        $emailInput = trim((string)($data['email'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $name = trim((string)($data['name'] ?? ''));
        $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL) ?: '';

        if (!$email || !$password) {
            $this->error('Email and password are required', 400);
            return;
        }

        if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $this->error('Password must be at least 12 characters and contain uppercase, lowercase, and numbers', 400);
            return;
        }

        $rateLimiter = new RateLimiter();
        $ip = $this->auth->getClientIp();
        $rateKey = 'api_auth_register:' . strtolower($email) . ':' . $ip;
        $rate = $rateLimiter->check($rateKey, 5, 3600);
        if (!$rate['allowed']) {
            $this->error('Too many attempts. Please try again later.', 429);
            return;
        }

        $result = $this->auth->register($email, $password, $name);

        if ($result['success']) {
            $rateLimiter->clear($rateKey);
            $loginResult = $this->auth->login($email, $password);
            if (!$loginResult['success']) {
                $this->error('Registration succeeded but login failed', 500);
                return;
            }
            try {
                $token = $this->auth->createJwt($loginResult['user']);
            } catch (\Throwable $e) {
                error_log('JWT creation failed: ' . $e->getMessage());
                $this->error('Token creation failed', 500);
                return;
            }
            $this->success([
                'user' => $loginResult['user'] ?? null,
                'token' => $token
            ], $result['message']);
        } else {
            $rateLimiter->hit($rateKey, 5, 3600);
            $this->error($result['message'], 400);
        }
    }
}
