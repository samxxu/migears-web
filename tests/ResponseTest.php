<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\Response;

class ResponseTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $response = new Response();
        $this->assertSame('', $response->body);
        $this->assertSame(200, $response->status);
        $this->assertSame([], $response->headers);
    }

    public function testJsonResponse(): void
    {
        $response = Response::json(['key' => 'value']);
        $this->assertSame('{"key":"value"}', $response->body);
        $this->assertSame(200, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testJsonWithCustomStatus(): void
    {
        $response = Response::json(['error' => 'not found'], 404);
        $this->assertSame(404, $response->status);
    }

    public function testHtmlResponse(): void
    {
        $response = Response::html('<p>Hello</p>');
        $this->assertSame('<p>Hello</p>', $response->body);
        $this->assertSame('text/html; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testRedirectResponse(): void
    {
        $response = Response::redirect('/login');
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
        $this->assertSame('', $response->body);
    }

    public function testRedirectWithCustomStatus(): void
    {
        $response = Response::redirect('/new-url', 301);
        $this->assertSame(301, $response->status);
    }

    public function testEmptyResponse(): void
    {
        $response = Response::empty();
        $this->assertSame(204, $response->status);
        $this->assertSame('', $response->body);
    }

    public function testEmptyWithCustomStatus(): void
    {
        $response = Response::empty(201);
        $this->assertSame(201, $response->status);
    }

    public function testReadonlyProperties(): void
    {
        $response = new Response();
        $this->expectException(\Error::class);
        $response->status = 500;
    }
}
