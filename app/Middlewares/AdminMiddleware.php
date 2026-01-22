<?php

namespace App\Middlewares;

use Core\Auth;

/**
 * Middleware для проверки прав администратора
 */
class AdminMiddleware
{
    public function handle(): bool
    {
        $auth = new Auth();
        
        if (!$auth->check()) {
            http_response_code(401);
            header('Location: /login');
            return false;
        }
        
        $user = $auth->user();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo 'Forbidden';
            return false;
        }
        
        return true;
    }
}
