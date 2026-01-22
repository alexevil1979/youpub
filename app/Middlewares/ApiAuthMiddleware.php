<?php

namespace App\Middlewares;

use Core\Auth;

/**
 * Middleware для API авторизации
 */
class ApiAuthMiddleware
{
    public function handle(): bool
    {
        $auth = new Auth();
        
        // Проверка через сессию
        if ($auth->check()) {
            return true;
        }
        
        // Проверка через JWT токен в заголовке
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if ($token && strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
            // TODO: Реализовать проверку JWT токена
            // Пока используем сессии
        }
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        return false;
    }
}
