<?php
declare(strict_types=1);

namespace TinyGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use TinyGears\Web\ResourceLocator;

class ResourceLocatorTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = __DIR__ . '/Fixtures/resources';
    }

    public function testRootIndex(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]); // short class name
        $this->assertStringEndsWith('Index.php', $result[1]); // file path
        $this->assertSame([], $result[2]); // params
        $this->assertSame([], $result[3]); // remaining
        $this->assertSame([], $result[4]); // namespace segments
    }

    public function testNamedResource(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]);
        $this->assertSame([], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['Users'], $result[4]);
    }

    public function testWildcardParam(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/123');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]);
        $this->assertSame(['user_id' => '123'], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['Users', 'UserId'], $result[4]);
    }

    public function testNestedResource(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/123/posts');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]);
        $this->assertSame(['user_id' => '123'], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['Users', 'UserId', 'Posts'], $result[4]);
    }

    public function testNotFound(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/nonexistent');
        $this->assertNull($result);
    }

    public function testDeepNotFound(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/123/posts/456/comments');
        $this->assertNull($result);
    }

    public function testCatchAllResource(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/catchall/anything/here');
        $this->assertNotNull($result);
        $this->assertSame('CatchAllResource', $result[0]);
        $this->assertSame(['anything', 'here'], $result[3]);
    }

    public function testCatchAllRoot(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/catchall');
        $this->assertNotNull($result);
        $this->assertSame('CatchAllResource', $result[0]);
        $this->assertSame([], $result[3]);
    }

    public function testTrailingSlash(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]);
    }

    public function testEmptyPath(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('');
        $this->assertNotNull($result);
        $this->assertSame('Index', $result[0]);
    }

    public function testFilePathIsCorrect(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users');
        $this->assertNotNull($result);
        $this->assertFileExists($result[1]);
        $this->assertStringEndsWith('Users' . DIRECTORY_SEPARATOR . 'Index.php', $result[1]);
    }
}
