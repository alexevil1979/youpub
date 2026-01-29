<?php

declare(strict_types=1);

namespace Core;

use App\Exception\AppException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Централизованная обработка необработанных исключений и фатальных ошибок PHP.
 * Логирует через PSR-3 логгер и отдаёт ответ в формате JSON (API) или HTML (web).
 */
final class ErrorHandler
{
    private LoggerInterface $logger;
    private bool $debug;
    private string $environment;

    public function __construct(LoggerInterface $logger, bool $debug = false, string $environment = 'production')
    {
        $this->logger = $logger;
        $this->debug = $debug;
        $this->environment = $environment;
    }

    /**
     * Регистрирует обработчик исключений и ошибок PHP.
     */
    public function register(): void
    {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError'], E_ALL);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    /**
     * Обработчик необработанных исключений.
     */
    public function handleException(Throwable $e): void
    {
        $this->logThrowable($e);

        $statusCode = $e instanceof AppException
            ? $e->getHttpStatusCode()
            : 500;

        $this->sendResponse($e, $statusCode);
    }

    /**
     * Обработчик ошибок PHP (конвертирует в ErrorException).
     */
    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $e = new \ErrorException($message, 0, $severity, $file, $line);
        $this->handleException($e);
        return true;
    }

    /**
     * Shutdown: перехват фатальных ошибок.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            $e = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );
            $this->logThrowable($e);
            $this->sendResponse($e, 500);
        }
    }

    private function logThrowable(Throwable $e): void
    {
        $context = [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];

        if ($e instanceof AppException && $e->getHttpStatusCode() < 500) {
            $this->logger->warning($e->getMessage(), $context);
        } else {
            $this->logger->error($e->getMessage(), array_merge($context, [
                'trace' => $e->getTraceAsString(),
            ]));
        }
    }

    private function sendResponse(Throwable $e, int $statusCode): void
    {
        if (headers_sent()) {
            return;
        }

        $isApi = $this->isApiRequest();

        if ($isApi) {
            $this->sendJsonResponse($e, $statusCode);
        } else {
            $this->sendHtmlResponse($e, $statusCode);
        }

        exit(1);
    }

    private function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api') === 0) {
            return true;
        }
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        $ajax = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($ajax) === 'xmlhttprequest';
    }

    private function sendJsonResponse(Throwable $e, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $payload = [
            'success' => false,
            'message' => $statusCode >= 500 ? 'Internal Server Error' : $e->getMessage(),
        ];

        if ($e instanceof App\Exception\ValidationException) {
            $payload['errors'] = $e->getErrors();
        }

        if ($this->debug && $this->environment !== 'production') {
            $payload['debug'] = [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function sendHtmlResponse(Throwable $e, int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=utf-8');

        $showDetails = $this->debug && $this->environment !== 'production';
        $message = $showDetails ? $e->getMessage() : 'Произошла внутренняя ошибка сервера. Попробуйте позже.';
        $file = $e->getFile();
        $line = $e->getLine();

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="ru">
        <head>
            <meta charset="UTF-8">
            <title>Ошибка</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 2rem; }
                .error { background: #f8f9fa; border: 1px solid #dee2e6; padding: 1.5rem; border-radius: 8px; max-width: 600px; margin: 0 auto; }
                .error h1 { margin-top: 0; color: #333; }
                .error .details { margin-top: 1rem; font-size: 0.9rem; color: #666; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>Ошибка сервера</h1>
                <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($showDetails): ?>
                    <div class="details">
                        <p><strong>Файл:</strong> <?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></p>
                        <p><strong>Строка:</strong> <?= (int) $line ?></p>
                    </div>
                <?php endif; ?>
                <p><a href="/dashboard">Вернуться на главную</a></p>
            </div>
        </body>
        </html>
        <?php
        echo ob_get_clean();
    }
}
