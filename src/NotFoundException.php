<?php

declare(strict_types=1);

namespace MiGears\Web;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Thrown when an id is asked for that was never registered.
 *
 * PSR-11 requires a container to fail this way, and the class is also a
 * RuntimeException, so a caller may catch it by either name.
 *
 * This is not ResourceNotFoundException: that one means "no resource matched
 * this path" (a 404), this one means "the application was not wired correctly"
 * (a 500).
 */
class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
