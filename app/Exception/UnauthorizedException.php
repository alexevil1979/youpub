<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/** Не авторизован (401 Unauthorized). */
final class UnauthorizedException extends AppException
{
    public function __construct(
        string $message = 'Unauthorized',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 401);
    }
}
