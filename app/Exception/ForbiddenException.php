<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/** Доступ запрещён (403 Forbidden). */
final class ForbiddenException extends AppException
{
    public function __construct(
        string $message = 'Forbidden',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 403);
    }
}
