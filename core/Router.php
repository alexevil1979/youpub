<?php

namespace Core;

/**
 * Маршрутизатор
 */
class Router
{
    private array $routes = [];
    private array $middlewares = [];

    /**
     * Добавить маршрут
     */
    public function add(string $method, string $path, $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->normalizePath($path),
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * GET маршрут
     */
    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    /**
     * POST маршрут
     */
    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    /**
     * PUT маршрут
     */
    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    /**
     * DELETE маршрут
     */
    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Обработка запроса
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath(parse_url($uri, PHP_URL_PATH));

        foreach ($this->routes as $route) {
            $params = [];
            if ($route['method'] === $method && $this->matchPath($route['path'], $path, $params)) {
                // Выполнить middleware
                foreach ($route['middlewares'] as $middleware) {
                    if (is_string($middleware)) {
                        $middleware = new $middleware();
                    }
                    if (!$middleware->handle()) {
                        return;
                    }
                }

                if ($this->isStateChangingMethod($method) && !$this->isApiRequest($path)) {
                    if (!$this->validateCsrfToken()) {
                        $this->sendCsrfError();
                        return;
                    }
                }

                // Выполнить handler
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // 404 - проверяем, это AJAX запрос или обычный?
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        
        http_response_code(404);
        
        if ($isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            // AJAX запрос - возвращаем JSON
            echo json_encode(['error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
        } else {
            // Обычный запрос - показываем HTML страницу 404
            $title = 'Страница не найдена';
            ob_start();
            ?>
            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <title>404 - Страница не найдена</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 2rem; text-align: center; }
                    .error { background: #f8f9fa; border: 1px solid #dee2e6; padding: 2rem; border-radius: 8px; max-width: 600px; margin: 0 auto; }
                </style>
            </head>
            <body>
                <div class="error">
                    <h1>404 - Страница не найдена</h1>
                    <p>Запрашиваемая страница не существует.</p>
                    <p><a href="/dashboard">Вернуться на главную</a></p>
                </div>
            </body>
            </html>
            <?php
            echo ob_get_clean();
        }
    }

    /**
     * Нормализация пути
     */
    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }

    /**
     * Проверка соответствия пути
     */
    private function matchPath(string $routePath, string $requestPath, array &$params): bool
    {
        $params = [];
        $routeParts = explode('/', trim($routePath, '/'));
        $requestParts = explode('/', trim($requestPath, '/'));

        if (count($routeParts) !== count($requestParts)) {
            return false;
        }

        foreach ($routeParts as $index => $routePart) {
            if (preg_match('/^{(\w+)}$/', $routePart, $matches)) {
                $params[$matches[1]] = $requestParts[$index];
            } elseif ($routePart !== $requestParts[$index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Привести параметры к типам согласно сигнатуре метода
     */
    private function convertParamsToTypes(array $params, \ReflectionMethod $reflection): array
    {
        $typedParams = [];
        $paramIndex = 0;
        $paramValues = array_values($params);
        
        foreach ($reflection->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType();
            
            // Получаем значение параметра (сначала по имени, потом по индексу)
            $value = $params[$paramName] ?? ($paramValues[$paramIndex] ?? null);
            
            // Приводим тип, если указан и значение не null
            if ($paramType && !$paramType->allowsNull() && $value !== null) {
                $typeName = $paramType->getName();
                if ($typeName === 'int') {
                    $value = (int)$value;
                } elseif ($typeName === 'float') {
                    $value = (float)$value;
                } elseif ($typeName === 'bool') {
                    $value = (bool)$value;
                }
            }
            
            $typedParams[] = $value;
            $paramIndex++;
        }
        
        return $typedParams;
    }

    /**
     * Вызов handler
     */
    private function callHandler($handler, array $params): void
    {
        // Обработка строки вида "Controller@method"
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controller, $method] = explode('@', $handler);
            $controllerClass = "App\\Controllers\\{$controller}";
            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                if (method_exists($controllerInstance, $method)) {
                    call_user_func_array([$controllerInstance, $method], array_values($params));
                    return;
                }
            }
        }

        // Обработка массива [ClassName::class, 'method']
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class) && class_exists($class)) {
                $instance = new $class();
                if (method_exists($instance, $method)) {
                    // Приводим типы параметров согласно сигнатуре метода
                    try {
                        $reflection = new \ReflectionMethod($instance, $method);
                        $typedParams = $this->convertParamsToTypes($params, $reflection);
                        call_user_func_array([$instance, $method], $typedParams);
                    } catch (\ReflectionException $e) {
                        // Если не удалось получить reflection, используем параметры как есть
                        call_user_func_array([$instance, $method], array_values($params));
                    }
                    return;
                }
            }
        }

        // Обработка callable (функции, замыкания)
        if (is_callable($handler)) {
            try {
                if (empty($params)) {
                    $result = call_user_func($handler);
                } else {
                    $result = call_user_func_array($handler, array_values($params));
                }
                // Если функция ничего не вернула и не сделала редирект, это нормально
                return;
            } catch (\Throwable $e) {
                error_log('Handler error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Handler execution failed', 'message' => $e->getMessage()]);
                return;
            }
        }

        http_response_code(500);
        echo json_encode(['error' => 'Invalid handler', 'handler_type' => gettype($handler)]);
    }

    private function isStateChangingMethod(string $method): bool
    {
        return in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true);
    }

    private function isApiRequest(string $path): bool
    {
        return strpos($path, '/api') === 0;
    }

    private function validateCsrfToken(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        $sessionToken = $_SESSION['csrf_token'] ?? null;
        if (!$token || !$sessionToken) {
            return false;
        }
        return hash_equals($sessionToken, $token);
    }

    private function sendCsrfError(): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        http_response_code(403);
        if ($isAjax || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
            return;
        }
        $_SESSION['error'] = 'Сессия устарела. Обновите страницу и попробуйте снова.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/dashboard'));
    }
}
