<?php

declare(strict_types=1);

namespace App\Exception;

use Throwable;

/** Ошибка валидации ввода (400 Bad Request). */
final class ValidationException extends AppException
{
    /** @var array<string, string[]> Ошибки по полям */
    private array $errors = [];

    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous, 400);
        $this->errors = $errors;
    }

    /** @return array<string, string[]> */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
