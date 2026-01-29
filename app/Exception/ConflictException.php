<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/** Конфликт (409 Conflict), например дубликат. */
final class ConflictException extends AppException
{
    public function __construct(
        string $message = 'Conflict',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 409);
    }
}
