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

    public function testJsonGracefullyHandlesEncodeFailure(): void
    {
        // NAN cannot be JSON-encoded — json_encode returns false.
        // Response::json degrades to 500 with a JSON error body.
        $response = Response::json(['value' => NAN]);
        $this->assertSame(500, $response->status);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
        $data = json_decode($response->body, true);
        $this->assertIsArray($data);
        $this->assertSame('json_encode failed', $data['error']);
        $this->assertArrayHasKey('code', $data);
        $this->assertArrayHasKey('message', $data);
    }

    public function testJsonHandlesResourceWithoutTypeError(): void
    {
        $fh = fopen('php://memory', 'r');
        try {
            $response = Response::json(['fh' => $fh]);
            $data = json_decode($response->body, true);
            $this->assertSame('json_encode failed', $data['error']);
        } finally {
            fclose($fh);
        }
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

    public function testPropertiesAreMutable(): void
    {
        $response = new Response();
        $response->status = 201;
        $response->body = 'hello';
        $response->headers = ['X-Custom' => 'yes'];
        $this->assertSame(201, $response->status);
        $this->assertSame('hello', $response->body);
        $this->assertSame('yes', $response->headers['X-Custom']);
    }

    public function testWithHeaderSetsHeaderAndReturnsSelf(): void
    {
        $response = Response::json(['ok' => true]);
        $result = $response->withHeader('X-Powered-By', 'migears');
        $this->assertSame($response, $result);
        $this->assertSame('migears', $response->headers['X-Powered-By']);
    }

    public function testWithHeaderOverwritesExistingHeader(): void
    {
        $response = Response::json(['ok' => true]);
        $response->withHeader('Content-Type', 'text/plain');
        $this->assertSame('text/plain', $response->headers['Content-Type']);
    }

    public function testWithStatusSetsStatusAndReturnsSelf(): void
    {
        $response = Response::json(['ok' => true]);
        $result = $response->withStatus(418);
        $this->assertSame($response, $result);
        $this->assertSame(418, $response->status);
    }

    public function testFluentChaining(): void
    {
        $response = Response::json(['ok' => true])
            ->withStatus(201)
            ->withHeader('X-Request-Id', 'abc123')
            ->withHeader('X-Custom', 'value');
        $this->assertSame(201, $response->status);
        $this->assertSame('abc123', $response->headers['X-Request-Id']);
        $this->assertSame('value', $response->headers['X-Custom']);
        $this->assertSame('{"ok":true}', $response->body);
    }
}
