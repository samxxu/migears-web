<?php

declare(strict_types=1);

namespace MiGears\Web\Tests;

use RuntimeException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use MiGears\Web\MiRest;
use MiGears\Web\NotFoundException;
use MiGears\Web\Request;

class ContainerTest extends TestCase
{
    private string $baseDir;
    private string $namespace;
    private MiRest $rest;

    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/Fixtures/resources';
        $this->namespace = 'MiGears\\Web\\Tests\\Fixtures\\Resources';
        $this->rest = new MiRest($this->baseDir, $this->namespace);
    }

    public function testItIsAPsr11Container(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->rest);
    }

    public function testHasReportsWhatIsRegistered(): void
    {
        $this->assertFalse($this->rest->has('pdo'));

        $this->rest->set('pdo', fn() => new \stdClass());

        $this->assertTrue($this->rest->has('pdo'));
    }

    public function testGetReturnsTheSameInstanceEveryTime(): void
    {
        $calls = 0;
        $this->rest->set('counter', function () use (&$calls) {
            $calls++;
            return new \stdClass();
        });

        $first = $this->rest->get('counter');
        $second = $this->rest->get('counter');

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    public function testSetReplacesTheFactoryAndDropsTheCachedInstance(): void
    {
        $this->rest->set('svc', fn() => 'first');
        $this->assertSame('first', $this->rest->get('svc'));

        $this->rest->set('svc', fn() => 'second');
        $this->assertSame('second', $this->rest->get('svc'));
    }

    public function testGetThrowsForAnUnregisteredId(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage("Nothing is registered under 'nonexistent'");

        $this->rest->get('nonexistent');
    }

    public function testTheThrownExceptionSatisfiesPsr11(): void
    {
        try {
            $this->rest->get('nonexistent');
            $this->fail('get() must throw for an unregistered id.');
        } catch (NotFoundExceptionInterface $e) {
            // PSR-11 requires exactly this interface — and being a
            // RuntimeException as well, either catch style works
            $this->assertInstanceOf(RuntimeException::class, $e);
        }
    }

    public function testAResourceResolvesAnEntryThroughTheContainer(): void
    {
        $this->rest->set('config', fn() => ['app_name' => 'miGears Demo']);

        $response = $this->rest->handle(new Request('GET', '/config'));

        $this->assertSame(200, $response->status);
        $this->assertSame('miGears Demo', json_decode($response->body, true)['app_name']);
    }

    public function testResolvingAnUnregisteredIdInsideAResourceSurfacesAsAnError(): void
    {
        // no 'config' registered: resolve() throws instead of handing back null,
        // so an assembly mistake becomes a 500 rather than an empty config
        $response = $this->rest->handle(new Request('GET', '/config'));

        $this->assertSame(500, $response->status);
    }
}
