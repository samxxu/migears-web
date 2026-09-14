<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use TinyGears\Web\TinyRest;
use TinyGears\Web\Request;
use TinyGears\Web\Response;

class TinyRestTest extends TestCase
{
    private string $baseDir;
    private string $namespace;

    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/Fixtures/resources';
        $this->namespace = 'TinyGears\\Web\\Tests\\Fixtures\\Resources';
    }

    // --- Basic routing tests ---

    public function testGetRoot(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Hello from root', $data['message']);
    }

    public function testPostRoot(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('POST', '/', body: ['key' => 'value']));
        $this->assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(['key' => 'value'], $data['received']);
    }

    public function testGetUsersList(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/users', query: ['page' => '2']));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(2, $data['page']);
        $this->assertCount(2, $data['users']);
    }

    public function testPostUsers(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('POST', '/users', body: ['name' => 'Alice']));
        $this->assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(42, $data['id']);
        $this->assertSame('Alice', $data['name']);
    }

    public function testGetSingleUser(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('42', $data['id']);
        $this->assertSame('User 42', $data['name']);
    }

    public function testPutUser(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('PUT', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['updated']);
    }

    public function testDeleteUser(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('DELETE', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['deleted']);
    }

    public function testNestedResource(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/users/42/posts'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('42', $data['user_id']);
        $this->assertCount(2, $data['posts']);
    }

    // --- Error handling tests ---

    public function testNotFound(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Not Found', $data['error']);
    }

    public function testCustomNotFoundHandler(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $rest->notFound(fn() => Response::html('<h1>404</h1>', 404));
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
        $this->assertSame('<h1>404</h1>', $response->body);
    }

    public function testMethodNotAllowed(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        // users/Index does not implement patch
        $response = $rest->handle(new Request('PATCH', '/users'));
        $this->assertSame(405, $response->status);
    }

    public function testErrorHandler(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $rest->before(function () { throw new \RuntimeException('boom'); });
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(500, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Internal Server Error', $data['error']);
    }

    public function testCustomErrorHandler(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $rest->error(fn(\Throwable $e) => Response::json(['msg' => $e->getMessage()], 503));
        $rest->before(function () { throw new \RuntimeException('custom error'); });
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(503, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('custom error', $data['msg']);
    }

    // --- Global hook tests ---

    public function testBeforeHookShortCircuit(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $rest->before(fn(Request $r) => Response::json(['blocked' => true], 401));
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(401, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['blocked']);
    }

    public function testBeforeHookPassesThrough(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $called = false;
        $rest->before(function (Request $r) use (&$called) {
            $called = true;
            return null;
        });
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertTrue($called);
        $this->assertSame(200, $response->status);
    }

    public function testAfterHookModifiesResponse(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $rest->after(function (Request $r, Response $resp) {
            return new Response(
                body: $resp->body,
                status: $resp->status,
                headers: array_merge($resp->headers, ['X-Test' => 'yes']),
            );
        });
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame('yes', $response->headers['X-Test']);
    }

    public function testMultipleBeforeHooks(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $order = [];
        $rest->before(function () use (&$order) { $order[] = 1; return null; });
        $rest->before(function () use (&$order) { $order[] = 2; return null; });
        $rest->handle(new Request('GET', '/'));
        $this->assertSame([1, 2], $order);
    }

    // --- Logging tests ---

    public function testErrorLogging(): void
    {
        $logger = new ArrayLogger();
        $rest = new TinyRest($this->baseDir, $this->namespace, $logger);
        $rest->before(function () { throw new \RuntimeException('log test'); });
        $rest->handle(new Request('GET', '/'));
        $this->assertGreaterThan(0, $logger->count());
        $log = $logger->first();
        $this->assertSame('error', $log['level']);
        $this->assertSame('log test', $log['message']);
    }

    // --- CatchAll tests ---

    public function testCatchAllResource(): void
    {
        $rest = new TinyRest($this->baseDir, $this->namespace);
        $response = $rest->handle(new Request('GET', '/catchall/any/path'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['caught']);
    }

    // --- No namespace tests ---

    public function testWithoutNamespace(): void
    {
        // Without namespace, it should still work if the class exists (just testing no crash)
        // Since our Fixtures are all under namespace, this test verifies passing no namespace doesn't cause a fatal error
        $rest = new TinyRest($this->baseDir);
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
    }
}
