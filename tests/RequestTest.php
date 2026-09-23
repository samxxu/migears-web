<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\Request;

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

    // --- parseBody ---

    public function testParseBodyPrefersPostForm(): void
    {
        $result = Request::parseBody(['name' => 'Alice'], '{"name":"Bob"}');
        $this->assertSame(['name' => 'Alice'], $result);
    }

    public function testParseBodyParsesJsonWhenPostEmpty(): void
    {
        $result = Request::parseBody([], '{"key":"value"}');
        $this->assertSame(['key' => 'value'], $result);
    }

    public function testParseBodyReturnsEmptyWhenNothing(): void
    {
        $this->assertSame([], Request::parseBody([], ''));
        $this->assertSame([], Request::parseBody([], 'not-json'));
    }

    // --- fromGlobals ---

    public function testFromGlobalsCollectsContentTypeAndLengthHeaders(): void
    {
        $this->withGlobals(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users', 'CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => '15'],
            [],
            [],
            function () {
                $request = Request::fromGlobals();
                $this->assertSame('application/json', $request->header('Content-Type'));
                $this->assertSame('15', $request->header('Content-Length'));
                $this->assertSame('POST', $request->method);
                $this->assertSame('/users', $request->path);
            },
        );
    }

    public function testFromGlobalsDefaults(): void
    {
        $this->withGlobals([], [], [], function () {
            $request = Request::fromGlobals();
            $this->assertSame('GET', $request->method);
            $this->assertSame('/', $request->path);
        });
    }

    private function withGlobals(array $server, array $post, array $get, callable $fn): void
    {
        $prevServer = $_SERVER;
        $prevPost = $_POST;
        $prevGet = $_GET;
        $prevFiles = $_FILES;
        $_SERVER = $server;
        $_POST = $post;
        $_GET = $get;
        $_FILES = [];
        try {
            $fn();
        } finally {
            $_SERVER = $prevServer;
            $_POST = $prevPost;
            $_GET = $prevGet;
            $_FILES = $prevFiles;
        }
    }
}
