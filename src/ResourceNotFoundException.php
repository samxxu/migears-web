<?php

declare(strict_types=1);

namespace MiGears\Web;

/** 404 Resource Not Found exception. */
final class ResourceNotFoundException extends \RuntimeException
{
    public function __construct(string $message = 'Resource not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
