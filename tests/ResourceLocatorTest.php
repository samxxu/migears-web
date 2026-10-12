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

    public function testCatchRemaining(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/docs/anything/here');
        $this->assertNotNull($result);
        $this->assertSame('__catch_remaining__', $result[0]);
        $this->assertSame(['anything', 'here'], $result[3]);
    }

    public function testCatchRemainingAtItsOwnDepth(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/docs');
        $this->assertNotNull($result);
        $this->assertSame('__catch_remaining__', $result[0]);
        $this->assertSame([], $result[3]);
    }

    public function testCatchRemainingUnderAWildcardIsNotReachedAtTheWildcardsDepth(): void
    {
        // A file answers a segment that ends the path, a directory carries it further.
        // An exact directory is still entered when it ends the path — that is how
        // /docs reaches the file inside docs/ — but a wildcard directory is not, so
        // /api/v1 never reaches the catch-remaining file inside ___version___/.
        $dir = sys_get_temp_dir() . '/migears-wildcatch-' . uniqid();
        mkdir($dir . '/api/___version___', 0777, true);
        file_put_contents($dir . '/api/___version___/__catch_remaining__.php', "<?php\n");

        try {
            $locator = new ResourceLocator($dir);

            $tail = $locator->locate('/api/v1/foo');
            $this->assertNotNull($tail);
            $this->assertSame(['foo'], $tail[3]);
            $this->assertSame(['version' => 'v1'], $tail[2]);

            $this->assertNull($locator->locate('/api/v1'));
        } finally {
            unlink($dir . '/api/___version___/__catch_remaining__.php');
            rmdir($dir . '/api/___version___');
            rmdir($dir . '/api');
            rmdir($dir);
        }
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

    // --- kebab / snake tolerance (default nameFor() behaviour) ---

    public function testKebabUrlResolvesToSnakeFile(): void
    {
        // Recommended kebab URL → snake file name (PHP class names cannot contain `-`)
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/blog-posts');
        $this->assertNotNull($result);
        $this->assertSame('blog_posts', $result[0]);
        $this->assertStringEndsWith('blog_posts.php', $result[1]);
    }

    public function testSnakeUrlAlsoResolvesToSnakeFile(): void
    {
        // Legacy snake URLs still work — segments without `-` come through unchanged after lowercasing
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/blog_posts');
        $this->assertNotNull($result);
        $this->assertSame('blog_posts', $result[0]);
    }

    public function testKebabWithUppercaseAlsoResolves(): void
    {
        $locator = new ResourceLocator($this->baseDir);
        $result = $locator->locate('/Blog-Posts');
        $this->assertNotNull($result);
        $this->assertSame('blog_posts', $result[0]);
    }

    public function testWildcardCaptureKeepsUnderscoreSegmentUntouched(): void
    {
        // A user-data segment (`john_X_99`) falls through to the wildcard, which
        // captures the raw URL value — nameFor() only affects exact file/directory
        // lookups, not what the wildcard hands over.
        $dir = sys_get_temp_dir() . '/migears-wild-kebab-' . uniqid();
        mkdir($dir . '/users', 0777, true);
        file_put_contents($dir . '/users/___user_id___.php', "<?php\n");

        try {
            $locator = new ResourceLocator($dir);

            $snake = $locator->locate('/users/john_X_99');
            $this->assertNotNull($snake);
            $this->assertSame(['user_id' => 'john_X_99'], $snake[2]);

            $kebab = $locator->locate('/users/john-X-99');
            $this->assertNotNull($kebab);
            $this->assertSame(['user_id' => 'john-X-99'], $kebab[2]);
        } finally {
            unlink($dir . '/users/___user_id___.php');
            rmdir($dir . '/users');
            rmdir($dir);
        }
    }
}
