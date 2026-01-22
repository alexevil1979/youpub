<?php

namespace App\Middlewares;

use Core\Auth;

/**
 * Middleware для проверки авторизации
 */
class AuthMiddleware
{
    public function handle(): bool
    {
        $auth = new Auth();
        
        if (!$auth->check()) {
            http_response_code(401);
            header('Location: /login');
            return false;
        }
        
        return true;
    }
}
