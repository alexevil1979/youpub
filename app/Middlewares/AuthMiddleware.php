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
        try {
            // Проверяем сессию
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $auth = new Auth();
            
            if (!$auth->check()) {
                error_log("AuthMiddleware: User not authenticated, redirecting to login");
                http_response_code(401);
                
                // Проверяем, это AJAX запрос?
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                
                if ($isAjax) {
                    // Для AJAX возвращаем JSON
                    header('Content-Type: application/json');
                    echo json_encode(['error' => 'Unauthorized', 'redirect' => '/login'], JSON_UNESCAPED_UNICODE);
                } else {
                    // Для обычных запросов - редирект
                    header('Location: /login');
                }
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("AuthMiddleware: Exception - " . $e->getMessage());
            http_response_code(500);
            return false;
        }
    }
}
