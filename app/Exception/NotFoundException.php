<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/** Ресурс не найден (404 Not Found). */
final class NotFoundException extends AppException
{
    public function __construct(
        string $message = 'Not found',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 404);
    }
}
