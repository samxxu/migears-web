<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\MiRest;
use MiGears\Web\NotFoundException;
use MiGears\Web\Request;
use MiGears\Web\Response;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MiRestTest extends TestCase
{
    private string $baseDir;
    private string $namespace;

    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/Fixtures/resources';
        $this->namespace = 'MiGears\\Web\\Tests\\Fixtures\\Resources';
    }

    /**
     * A MiRest with a logger registered, as every real bootstrap must do.
     *
     * The framework resolves its logger from the container and refuses to
     * invent a silent default, so a missing registration is a loud failure.
     * Tests that are not about that failure register NullLogger here.
     */
    private function rest(?string $baseDir = null, ?string $namespace = null): MiRest
    {
        $rest = new MiRest($baseDir ?? $this->baseDir, $namespace ?? $this->namespace);
        $rest->set(LoggerInterface::class, static fn() => new NullLogger());
        return $rest;
    }

    // --- Basic routing tests ---

    public function testGetRoot(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Hello from root', $data['message']);
    }

    public function testEmptyBaseDirThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MiRest('');
    }

    public function testNoNamespaceWorksWithSingleResource(): void
    {
        // A single resource file with no namespace works fine.
        // With multiple resources, class names collide (documented in README).
        $dir = sys_get_temp_dir() . '/migears-test-nons-' . uniqid();
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/Index.php', '<?php use MiGears\Web\AbstractResource; use MiGears\Web\Request; use MiGears\Web\Response; class Index extends AbstractResource { public function GET(Request $r): Response { return Response::json(["ok"=>true]); } }');
        try {
            $rest = $this->rest($dir, '');
            $response = $rest->handle(new Request('GET', '/'));
            $this->assertSame(200, $response->status);
            $data = json_decode($response->body, true);
            $this->assertTrue($data['ok']);
        } finally {
            unlink($dir . '/Index.php');
            rmdir($dir);
        }
    }

    public function testTraversalRequestIsNotFound(): void
    {
        $rest = $this->rest();
        // '..' is dropped, 'secret' has no resource inside the base dir → 404
        $response = $rest->handle(new Request('GET', '/../secret'));
        $this->assertSame(404, $response->status);
    }

    public function testTraversalRequestResolvesInsideBaseDir(): void
    {
        $rest = $this->rest();
        // '..' is dropped, so the request hits Users/Index inside the base dir
        $response = $rest->handle(new Request('GET', '/../users'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertArrayHasKey('users', $data);
    }

    public function testPostRoot(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('POST', '/', body: ['key' => 'value']));
        $this->assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(['key' => 'value'], $data['received']);
    }

    public function testGetUsersList(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/users', query: ['page' => '2']));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(2, $data['page']);
        $this->assertCount(2, $data['users']);
    }

    public function testPostUsers(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('POST', '/users', body: ['name' => 'Alice']));
        $this->assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame(42, $data['id']);
        $this->assertSame('Alice', $data['name']);
    }

    /**
     * A JSON POST body must reach the resource (previously $_POST was empty
     * for application/json, so the body was silently dropped).
     */
    public function testPostJsonBodyReachesResource(): void
    {
        $body = ['name' => 'Bob'];
        $request = new Request('POST', '/users', body: Request::parseBody([], json_encode($body)));
        $rest = $this->rest();
        $response = $rest->handle($request);
        $this->assertSame(201, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Bob', $data['name']);
    }

    public function testGetSingleUser(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('42', $data['id']);
        $this->assertSame('User 42', $data['name']);
    }

    public function testPutUser(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('PUT', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['updated']);
    }

    public function testDeleteUser(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('DELETE', '/users/42'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['deleted']);
    }

    public function testNestedResource(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/users/42/posts'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('42', $data['user_id']);
        $this->assertCount(2, $data['posts']);
    }

    public function testMultipleWildcardsThroughFramework(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/regions/asia/tokyo'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('asia', $data['region']);
        $this->assertSame('tokyo', $data['location']);
    }

    public function testRouteParametersKeepTheirRawUrlEncoding(): void
    {
        // %20 is not decoded: the locator captures the segment as written. $_GET
        // is decoded separately, so decoding the route value too would let a raw
        // value such as '..' reach resource code, where it is often concatenated
        // into a path (see README, "Route parameters keep their raw URL encoding").
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/users/42%20x'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('42%20x', $data['id']);
    }

    public function testExactAndWildcardDirectoriesCannotSilentlyShareAClass(): void
    {
        // Users/UserId/ and Users/___user_id___/ both map to the same FQCN
        // (...\Users\UserId\Index). Only one of the two files can ever be loaded,
        // so once the exact one is in memory the wildcard request must not answer
        // with the exact file's implementation as if nothing were wrong.
        $suffix = uniqid('collision');
        $ns = 'MiGears\\Web\\Tests\\' . $suffix;
        $dir = sys_get_temp_dir() . '/migears-collision-' . $suffix;
        mkdir($dir . '/Users/UserId', 0777, true);
        mkdir($dir . '/Users/___user_id___', 0777, true);

        $template = <<<'PHP'
<?php
namespace %s\Users\UserId;
use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;
class Index extends AbstractResource {
    public function GET(Request $r): Response { return Response::json(['from' => '%s']); }
}
PHP;
        file_put_contents($dir . '/Users/UserId/Index.php', sprintf($template, $ns, 'exact'));
        file_put_contents($dir . '/Users/___user_id___/Index.php', sprintf($template, $ns, 'wildcard'));

        try {
            $rest = $this->rest($dir, $ns);

            // Exact directory: /users/user_id → Users/UserId (loads the class)
            $exact = $rest->handle(new Request('GET', '/users/user_id'));
            $this->assertSame(200, $exact->status);
            $this->assertSame('exact', json_decode($exact->body, true)['from']);

            // Wildcard directory: /users/999 → Users/___user_id___ (same FQCN).
            // The ambiguous setup is reported, not resolved in silence.
            $wildcard = $rest->handle(new Request('GET', '/users/999'));
            $this->assertSame(500, $wildcard->status);
            $this->assertNotSame('exact', json_decode($wildcard->body, true)['from'] ?? null);
        } finally {
            unlink($dir . '/Users/UserId/Index.php');
            unlink($dir . '/Users/___user_id___/Index.php');
            rmdir($dir . '/Users/UserId');
            rmdir($dir . '/Users/___user_id___');
            rmdir($dir . '/Users');
            rmdir($dir);
        }
    }

    // --- Error handling tests ---

    public function testNotFound(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Not Found', $data['error']);
    }

    public function testCustomNotFoundHandler(): void
    {
        $rest = $this->rest();
        $rest->notFound(fn() => Response::html('<h1>404</h1>', 404));
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
        $this->assertSame('<h1>404</h1>', $response->body);
    }

    public function testMethodNotAllowed(): void
    {
        $rest = $this->rest();
        // users/Index does not implement patch
        $response = $rest->handle(new Request('PATCH', '/users'));
        $this->assertSame(405, $response->status);
    }

    public function testOptionsViaFrameworkReturnsAllowHeader(): void
    {
        $rest = $this->rest();
        // /users Index implements GET + POST
        $response = $rest->handle(new Request('OPTIONS', '/users'));
        $this->assertSame(204, $response->status);
        $this->assertStringContainsString('GET', $response->headers['Allow']);
        $this->assertStringContainsString('POST', $response->headers['Allow']);
    }

    public function testHeadViaFrameworkReturnsBodyless(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('HEAD', '/'));
        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->body);
    }

    public function testErrorHandler(): void
    {
        $rest = $this->rest();
        $rest->before(function () { throw new \RuntimeException('boom'); });
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(500, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('Internal Server Error', $data['error']);
    }

    public function testCustomErrorHandler(): void
    {
        $rest = $this->rest();
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
        $rest = $this->rest();
        $rest->before(fn(Request $r) => Response::json(['blocked' => true], 401));
        $response = $rest->handle(new Request('GET', '/'));
        $this->assertSame(401, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['blocked']);
    }

    public function testBeforeHookPassesThrough(): void
    {
        $rest = $this->rest();
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
        $rest = $this->rest();
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

    public function testThrowingAfterHookRunsOnceAndBecomesA500(): void
    {
        $rest = $this->rest();
        $calls = 0;
        $rest->after(function (Request $r, Response $resp) use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('after boom');
            }
            return $resp;
        });

        $response = $rest->handle(new Request('GET', '/'));

        $this->assertSame(1, $calls);
        $this->assertSame(500, $response->status);
    }

    public function testThrowingAfterHookDoesNotEscapeHandle(): void
    {
        $rest = $this->rest();
        $rest->after(function (Request $r, Response $resp): Response {
            throw new \RuntimeException('after boom');
        });

        $response = $rest->handle(new Request('GET', '/'));

        $this->assertSame(500, $response->status);
    }

    public function testAfterHooksRunOnceOnTheNotFoundPath(): void
    {
        $rest = $this->rest();
        $calls = 0;
        $rest->after(function (Request $r, Response $resp) use (&$calls): Response {
            $calls++;
            return $resp;
        });

        $response = $rest->handle(new Request('GET', '/no/such/resource'));

        $this->assertSame(404, $response->status);
        $this->assertSame(1, $calls);
    }

    public function testMultipleBeforeHooks(): void
    {
        $rest = $this->rest();
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
        $rest = $this->rest();
        $rest->set(LoggerInterface::class, fn() => $logger);
        $rest->before(function () { throw new \RuntimeException('log test'); });
        $rest->handle(new Request('GET', '/'));
        $this->assertGreaterThan(0, $logger->count());
        $log = $logger->first();
        $this->assertSame('error', $log['level']);
        $this->assertSame('log test', $log['message']);
    }

    public function testUnregisteredLoggerFailsLoudly(): void
    {
        // The framework will not invent a silent default: with nothing
        // registered, the very first request fails instead of logging nowhere.
        $rest = new MiRest($this->baseDir, $this->namespace);

        $this->expectException(NotFoundException::class);

        $rest->handle(new Request('GET', '/'));
    }

    public function testARegisteredNonLoggerEntryFailsLoudly(): void
    {
        // Registering the wrong object under LoggerInterface::class is an
        // assembly mistake too, caught by the same early check.
        $rest = new MiRest($this->baseDir, $this->namespace);
        $rest->set(LoggerInterface::class, fn() => new \stdClass());

        $this->expectException(\RuntimeException::class);

        $rest->handle(new Request('GET', '/'));
    }

    public function testNullLoggerSilencesFrameworkErrors(): void
    {
        // Silence is an explicit choice: register NullLogger.
        $rest = new MiRest($this->baseDir, $this->namespace);
        $rest->set(LoggerInterface::class, fn() => new NullLogger());
        $rest->before(function () { throw new \RuntimeException('silent'); });

        $response = $rest->handle(new Request('GET', '/'));

        $this->assertSame(500, $response->status);
    }

    // --- CatchAll tests ---

    public function testCatchAllResource(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/catchall/any/path'));
        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertTrue($data['caught']);
        $this->assertSame(['any', 'path'], $data['remaining']);
        // with remaining path, the before/after hooks must still run
        $this->assertSame('yes', $response->headers['X-Hooked']);
    }

    public function testCatchAllRootRunsHooks(): void
    {
        $rest = $this->rest();
        $response = $rest->handle(new Request('GET', '/catchall'));
        $this->assertSame(200, $response->status);
        // without remaining path, hooks must also run (consistency)
        $this->assertSame('yes', $response->headers['X-Hooked']);
    }

    // --- No namespace tests ---

    public function testWithoutNamespace(): void
    {
        // Without namespace, it should still work if the class exists (just testing no crash)
        // Since our Fixtures are all under namespace, this test verifies passing no namespace doesn't cause a fatal error
        $rest = $this->rest($this->baseDir, '');
        $response = $rest->handle(new Request('GET', '/nonexistent'));
        $this->assertSame(404, $response->status);
    }
}
