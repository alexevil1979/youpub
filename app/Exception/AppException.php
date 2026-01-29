<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/**
 * Базовое исключение приложения.
 * Все доменные/прикладные исключения наследуются от него.
 */
class AppException extends \Exception
{
    /** @var int HTTP-код ответа по умолчанию */
    protected int $httpStatusCode = 500;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        int $httpStatusCode = 500
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }
}
