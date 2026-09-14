<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use TinyGears\Web\AbstractResource;
use TinyGears\Web\Request;
use TinyGears\Web\Response;

class AbstractResourceTest extends TestCase
{
    public function testDefaultGetReturns405(): void
    {
        $resource = $this->makeResource();
        $response = $resource->GET(new Request('GET', '/'));
        $this->assertSame(405, $response->status);
    }

    public function testDefaultPostReturns405(): void
    {
        $resource = $this->makeResource();
        $response = $resource->POST(new Request('POST', '/'));
        $this->assertSame(405, $response->status);
    }

    public function testDefaultPutReturns405(): void
    {
        $resource = $this->makeResource();
        $response = $resource->PUT(new Request('PUT', '/'));
        $this->assertSame(405, $response->status);
    }

    public function testDefaultDeleteReturns405(): void
    {
        $resource = $this->makeResource();
        $response = $resource->DELETE(new Request('DELETE', '/'));
        $this->assertSame(405, $response->status);
    }

    public function testDefaultPatchReturns405(): void
    {
        $resource = $this->makeResource();
        $response = $resource->PATCH(new Request('PATCH', '/'));
        $this->assertSame(405, $response->status);
    }

    public function testOptionsReturnsAllowHeader(): void
    {
        $resource = new class extends AbstractResource {
            public function GET(Request $request): Response
            {
                return Response::json(['ok' => true]);
            }
        };
        $response = $resource->OPTIONS(new Request('OPTIONS', '/'));
        $this->assertSame(204, $response->status);
        $this->assertStringContainsString('GET', $response->headers['Allow']);
        $this->assertStringContainsString('OPTIONS', $response->headers['Allow']);
        $this->assertStringContainsString('HEAD', $response->headers['Allow']);
    }

    public function testHeadUsesGetWithoutBody(): void
    {
        $resource = new class extends AbstractResource {
            public function GET(Request $request): Response
            {
                return Response::json(['data' => 'hello']);
            }
        };
        $response = $resource->HEAD(new Request('HEAD', '/'));
        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->body);
        $this->assertSame('application/json; charset=utf-8', $response->headers['Content-Type']);
    }

    public function testBeforeHookCanShortCircuit(): void
    {
        $resource = new class extends AbstractResource {
            protected function before(Request $request): ?Response
            {
                return Response::json(['blocked' => true], 403);
            }
            public function GET(Request $request): Response
            {
                return Response::json(['ok' => true]);
            }
        };
        $response = $this->callProtected($resource, 'before', [new Request('GET', '/')]);
        $this->assertNotNull($response);
        $this->assertSame(403, $response->status);
    }

    public function testBeforeHookReturnsNullByDefault(): void
    {
        $resource = $this->makeResource();
        $result = $this->callProtected($resource, 'before', [new Request('GET', '/')]);
        $this->assertNull($result);
    }

    public function testAfterHookReturnsResponseByDefault(): void
    {
        $resource = $this->makeResource();
        $response = new Response('hello', 200);
        $result = $this->callProtected($resource, 'after', [new Request('GET', '/'), $response]);
        $this->assertSame($response, $result);
    }

    public function testAfterHookCanModifyResponse(): void
    {
        $resource = new class extends AbstractResource {
            protected function after(Request $request, Response $response): Response
            {
                return new Response(
                    body: $response->body,
                    status: $response->status,
                    headers: array_merge($response->headers, ['X-Custom' => 'yes']),
                );
            }
            public function GET(Request $request): Response
            {
                return Response::json(['ok' => true]);
            }
        };
        $original = Response::json(['ok' => true]);
        $modified = $this->callProtected($resource, 'after', [new Request('GET', '/'), $original]);
        $this->assertSame('yes', $modified->headers['X-Custom']);
    }

    public function testSetParams(): void
    {
        $resource = $this->makeResource();
        $resource->setParams(['id' => '42']);
        $this->assertSame(['id' => '42'], $this->getProtectedProperty($resource, 'params'));
    }

    public function testHandleSubThrows404(): void
    {
        $resource = $this->makeResource();
        $this->expectException(\TinyGears\Web\ResourceNotFoundException::class);
        $resource->handleSub(new Request('GET', '/'), ['extra', 'path']);
    }

    public function testGetAllowedMethodsIncludesImplementedOnes(): void
    {
        $resource = new class extends AbstractResource {
            public function GET(Request $request): Response { return Response::json([]); }
            public function POST(Request $request): Response { return Response::json([]); }
        };
        $methods = $this->callProtected($resource, 'getAllowedMethods', []);
        $this->assertContains('GET', $methods);
        $this->assertContains('POST', $methods);
        $this->assertContains('OPTIONS', $methods);
        $this->assertContains('HEAD', $methods);
        $this->assertNotContains('PUT', $methods);
        $this->assertNotContains('DELETE', $methods);
    }

    private function makeResource(): AbstractResource
    {
        return new class extends AbstractResource {};
    }

    private function callProtected(object $object, string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($object, $args);
    }

    private function getProtectedProperty(object $object, string $property): mixed
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        return $ref->getValue($object);
    }
}
