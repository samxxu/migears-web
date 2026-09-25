<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * Directory-based route locator.
 * Traverses directories level by level from the URL path to find the corresponding resource class file.
 *
 * Directory naming conventions:
 *   Index.php             → exact match for the current directory
 *   ___name___            → wildcard parameter, value passed to resource as a named parameter
 *   CatchAllResource.php  → catch-all resource (matches all unmatched remaining paths)
 *
 * URL to directory name conversion: URL segments are automatically converted to StudlyCase before matching.
 * For example /users → Users directory, /blog_posts → BlogPosts directory.
 */
class ResourceLocator
{
    public function __construct(
        private readonly string $baseDir,
    ) {
        if ($baseDir === '' || !is_dir($baseDir)) {
            throw new \InvalidArgumentException("Resource base directory must be an existing directory: $baseDir");
        }
    }

    /**
     * Locate a resource.
     * Returns [shortClassName, fullFilePath, namedParams, remainingPathSegments, namespaceSegmentsArray]
     * Returns null if not found.
     *
     * @return array{0: string, 1: string, 2: array<string, string>, 3: list<string>, 4: list<string>}|null
     */
    public function locate(string $path): ?array
    {
        $segments = $this->parsePath($path);
        $dir = rtrim($this->baseDir, '/');
        $params = [];
        $namespaceSegments = [];

        foreach ($segments as $i => $segment) {
            $studly = $this->toStudlyCase($segment);

            // 1. Exact match by directory name (StudlyCase)
            $exactDir = $dir . '/' . $studly;
            if (is_dir($exactDir)) {
                $namespaceSegments[] = $studly;
                $dir = $exactDir;
                continue;
            }

            // 2. Wildcard directory ___name___
            $wildcardDirs = glob($dir . '/___*___', GLOB_ONLYDIR);
            if ($wildcardDirs !== false && !empty($wildcardDirs)) {
                $wildcardDir = $wildcardDirs[0];
                $dirName = basename($wildcardDir);
                $paramName = trim($dirName, '_');
                $params[$paramName] = $segment;
                $namespaceSegments[] = $this->toStudlyCase($paramName);
                $dir = $wildcardDir;
                continue;
            }

            // 3. No match found, check if current directory has CatchAllResource
            $catchAllFile = $dir . '/CatchAllResource.php';
            if ($this->fileExists($catchAllFile)) {
                $remaining = array_slice($segments, $i);
                return ['CatchAllResource', $catchAllFile, $params, $remaining, $namespaceSegments];
            }

            return null;
        }

        // Path fully traversed, look for Index.php
        $indexFile = $dir . '/Index.php';
        if ($this->fileExists($indexFile)) {
            return ['Index', $indexFile, $params, [], $namespaceSegments];
        }

        // Or look for CatchAllResource
        $catchAllFile = $dir . '/CatchAllResource.php';
        if ($this->fileExists($catchAllFile)) {
            return ['CatchAllResource', $catchAllFile, $params, [], $namespaceSegments];
        }

        return null;
    }

    /**
     * Convert a string to StudlyCase: user_id → UserId, users → Users
     */
    private function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Parse a path into a segment array.
     * Dot segments ('.', '..') are dropped so the locator can never
     * escape the resource base directory.
     */
    private function parsePath(string $path): array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return [];
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if (trim($segment, " .") !== '') {
                $segments[] = $segment;
            }
        }
        return $segments;
    }

    /**
     * Unit-test friendly: overridable file_exists.
     */
    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }
}
