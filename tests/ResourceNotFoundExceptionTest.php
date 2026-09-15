<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\ResourceNotFoundException;

class ResourceNotFoundExceptionTest extends TestCase
{
    public function testDefaultMessage(): void
    {
        $e = new ResourceNotFoundException();
        $this->assertSame('Resource not found', $e->getMessage());
    }

    public function testCustomMessage(): void
    {
        $e = new ResourceNotFoundException('Custom 404');
        $this->assertSame('Custom 404', $e->getMessage());
    }

    public function testIsRuntimeException(): void
    {
        $e = new ResourceNotFoundException();
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }

    public function testPreviousException(): void
    {
        $prev = new \RuntimeException('previous');
        $e = new ResourceNotFoundException('not found', 0, $prev);
        $this->assertSame($prev, $e->getPrevious());
    }
}
