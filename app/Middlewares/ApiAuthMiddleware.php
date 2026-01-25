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
            $payload = $auth->validateJwt($token);
            if ($payload && !empty($payload['sub'])) {
                $user = $auth->getUserById((int)$payload['sub']);
                if ($user) {
                    $auth->startSession();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['user_name'] = $user['name'];
                    return true;
                }
            }
        }
        
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        return false;
    }
}
