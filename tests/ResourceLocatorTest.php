<?php
declare(strict_types=1);

namespace MiGears\Web\Tests;

use PHPUnit\Framework\TestCase;
use MiGears\Web\ResourceLocator;

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
        $this->assertSame('index', $result[0]); // short class name
        $this->assertStringEndsWith('index.php', $result[1]); // file path
        $this->assertSame([], $result[2]); // params
        $this->assertSame([], $result[3]); // remaining
        $this->assertSame([], $result[4]); // namespace segments
    }

    public function testRootIsAlsoAddressableByItsFileName(): void
    {
        // A file name is the path it answers, so index.php answers /index too.
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/index');
        $this->assertNotNull($result);
        $this->assertSame('index', $result[0]);
        $this->assertStringEndsWith('index.php', $result[1]);
    }

    public function testNamedResource(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users');
        $this->assertNotNull($result);
        $this->assertSame('users', $result[0]);
        $this->assertSame([], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame([], $result[4]);
    }

    public function testFilePathIsCorrect(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users');
        $this->assertNotNull($result);
        $this->assertFileExists($result[1]);
        $this->assertStringEndsWith('users.php', $result[1]);
    }

    public function testWildcardParam(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/123');
        $this->assertNotNull($result);
        $this->assertSame('___user_id___', $result[0]);
        $this->assertSame(['user_id' => '123'], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['users'], $result[4]);
    }

    public function testNestedResource(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/123/posts');
        $this->assertNotNull($result);
        $this->assertSame('posts', $result[0]);
        $this->assertSame(['user_id' => '123'], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['users', '___user_id___'], $result[4]);
    }

    public function testMultipleWildcardParams(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/regions/asia/tokyo');
        $this->assertNotNull($result);
        $this->assertSame('___location___', $result[0]);
        $this->assertSame(['region' => 'asia', 'location' => 'tokyo'], $result[2]);
        $this->assertSame([], $result[3]);
        $this->assertSame(['regions', '___region___'], $result[4]);
    }

    public function testSegmentMatchIsCaseInsensitive(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/Users');
        $this->assertNotNull($result);
        $this->assertSame('users', $result[0]);

        $deep = $locator->locate('/USERS/123');
        $this->assertNotNull($deep);
        $this->assertSame('___user_id___', $deep[0]);
        $this->assertSame(['user_id' => '123'], $deep[2]);
    }

    public function testWildcardValueKeepsRawEncoding(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/42%20x');
        $this->assertNotNull($result);
        // The value is handed over exactly as it arrived; decoding is the caller's job.
        $this->assertSame(['user_id' => '42%20x'], $result[2]);
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

    public function testCatchAll(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/catchall/anything/here');
        $this->assertNotNull($result);
        $this->assertSame('__other__', $result[0]);
        $this->assertSame(['anything', 'here'], $result[3]);
    }

    public function testCatchAllRoot(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/catchall');
        $this->assertNotNull($result);
        $this->assertSame('__other__', $result[0]);
        $this->assertSame([], $result[3]);
    }

    public function testTrailingSlash(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/users/');
        $this->assertNotNull($result);
        $this->assertSame('users', $result[0]);
    }

    public function testEmptyPath(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('');
        $this->assertNotNull($result);
        $this->assertSame('index', $result[0]);
    }

    public function testEmptyBaseDirThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ResourceLocator('');
    }

    public function testMissingBaseDirThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ResourceLocator('/nonexistent-resource-dir');
    }

    public function testDotSegmentDropped(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/./users');
        $this->assertNotNull($result);
        $this->assertSame('users', $result[0]);
        $this->assertSame([], $result[4]);
    }

    public function testTraversalSegmentCannotEscapeBaseDir(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        // '..' is dropped, so /../users resolves inside the base dir like /users
        $result = $locator->locate('/../users');
        $this->assertNotNull($result);
        $this->assertSame('users', $result[0]);
        $this->assertSame([], $result[4]);
        $this->assertStringStartsWith($this->baseDir, $result[1]);
    }

    public function testTraversalOnlyPathResolvesToRoot(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        // all dot segments dropped → empty path → root index
        $result = $locator->locate('/../..');
        $this->assertNotNull($result);
        $this->assertSame('index', $result[0]);
        $this->assertStringStartsWith($this->baseDir, $result[1]);
    }

    public function testBackslashSegmentIsRefused(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        // A backslash is a directory separator on Windows and can only be an
        // attempt to leave the base dir, so the path is not routed at all.
        $this->assertNull($locator->locate('/..\\..\\secret'));
        $this->assertNull($locator->locate('/users\\123'));
    }

    public function testNullByteSegmentIsRefused(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $this->assertNull($locator->locate("/users/abc\0def"));
    }
}
