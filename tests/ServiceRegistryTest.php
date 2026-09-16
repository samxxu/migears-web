<?php

declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\MiRest;
use MiGears\Web\Request;
use MiGears\Web\Response;
use MiGears\Web\AbstractResource;

class ServiceRegistryTest extends TestCase
{
    private string $baseDir;
    private string $namespace;

    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/Fixtures/resources';
        $this->namespace = 'MiGears\\Web\\Tests\\Fixtures\\Resources';
    }

    public function testSetAndHas(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $this->assertFalse($rest->has('pdo'));

        $rest->set('pdo', fn() => new \stdClass());

        $this->assertTrue($rest->has('pdo'));
    }

    public function testServiceReturnsSingleton(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $callCount = 0;
        $rest->set('counter', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $first = $rest->service('counter');
        $second = $rest->service('counter');

        $this->assertSame($first, $second);
        $this->assertSame(1, $callCount);
    }

    public function testServiceReturnsNullIfNotRegistered(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $this->assertNull($rest->service('nonexistent'));
    }

    public function testSetOverwritesPreviousFactory(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $rest->set('svc', fn() => 'first');
        $rest->set('svc', fn() => 'second');

        $this->assertSame('second', $rest->service('svc'));
    }

    public function testFactoryCalledOnceEvenIfServiceAccessedMultipleTimes(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $calls = 0;
        $rest->set('svc', function () use (&$calls) {
            $calls++;
            return ['call' => $calls];
        });

        $rest->service('svc');
        $rest->service('svc');
        $rest->service('svc');

        $this->assertSame(1, $calls);
    }

    public function testResourceCanAccessService(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $rest->set('config', fn() => ['app_name' => 'miGears Demo']);

        $response = $rest->handle(new Request('GET', '/config'));

        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertSame('miGears Demo', $data['app_name']);
    }

    public function testResourceServiceReturnsNullWhenNotRegistered(): void
    {
        $rest = new MiRest($this->baseDir, $this->namespace);

        $response = $rest->handle(new Request('GET', '/config'));

        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, true);
        $this->assertNull($data['app_name'] ?? null);
    }
}
