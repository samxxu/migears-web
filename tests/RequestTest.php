<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use TinyGears\Web\Request;

class RequestTest extends TestCase
{
    public function testConstructorWithDefaults(): void
    {
        $request = new Request('GET', '/test');
        $this->assertSame('GET', $request->method);
        $this->assertSame('/test', $request->path);
        $this->assertSame([], $request->query);
        $this->assertSame([], $request->body);
        $this->assertSame([], $request->headers);
        $this->assertSame([], $request->server);
    }

    public function testConstructorWithAllParams(): void
    {
        $request = new Request(
            method: 'POST',
            path: '/api/users',
            query: ['page' => '1'],
            body: ['name' => 'Alice'],
            headers: ['content-type' => 'application/json'],
            server: ['HTTP_HOST' => 'localhost'],
        );
        $this->assertSame('POST', $request->method);
        $this->assertSame('/api/users', $request->path);
        $this->assertSame(['page' => '1'], $request->query);
        $this->assertSame(['name' => 'Alice'], $request->body);
        $this->assertSame(['content-type' => 'application/json'], $request->headers);
    }

    public function testHeaderReturnsValue(): void
    {
        $request = new Request('GET', '/', headers: ['x-token' => 'abc123']);
        $this->assertSame('abc123', $request->header('X-Token'));
    }

    public function testHeaderCaseInsensitive(): void
    {
        $request = new Request('GET', '/', headers: ['content-type' => 'text/html']);
        $this->assertSame('text/html', $request->header('Content-Type'));
        $this->assertSame('text/html', $request->header('CONTENT-TYPE'));
    }

    public function testHeaderReturnsDefault(): void
    {
        $request = new Request('GET', '/');
        $this->assertNull($request->header('X-Missing'));
        $this->assertSame('fallback', $request->header('X-Missing', 'fallback'));
    }

    public function testReadonlyProperties(): void
    {
        $request = new Request('GET', '/');
        $this->expectException(\Error::class);
        $request->method = 'POST';
    }
}
