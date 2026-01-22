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

                // Выполнить handler
                $this->callHandler($route['handler'], $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
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
                    call_user_func_array([$instance, $method], array_values($params));
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
}
