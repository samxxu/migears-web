<?php
declare(strict_types=1);

namespace MiGears\Web;

/**
 * Path-based route locator.
 * Walks the resource tree one URL segment at a time to find the resource class file.
 *
 * A segment is answered by a file when it ends the path, and by a directory when
 * more path follows — which is why `/users` and `/users/42` sit side by side
 * without an index file in between.
 *
 * File naming conventions (lowercase, used verbatim — the path is the name):
 *   index.php        → the root resource, `/`
 *   <segment>.php    → exact match for /<segment>
 *   <segment>/       → container for a deeper path
 *   ___name___.php   → wildcard for one segment, its value passed as `name`
 *   ___name___/      → wildcard container
 *   __other__.php    → catch-all for this level and everything below it
 *
 * The class name is the file name, so `users/___user_id___.php` declares the
 * class `namespace\users\___user_id___`. Because a file name becomes a PHP class
 * name, a segment that is a PHP keyword (`/new`, `/list`, `/match`) cannot be
 * used — `class new {}` is a parse error. MiRest reports that when it loads the
 * file.
 *
 * Matching lowercases the URL segment, so `/Users` and `/users` reach the same
 * file; the wildcard value keeps the segment exactly as it arrived, encoded.
 */
class ResourceLocator
{
    private const ROOT_FILE = 'index.php';
    private const CATCH_ALL_FILE = '__other__.php';
    private const WILDCARD_FILE_PATTERN = '___*___.php';
    private const WILDCARD_DIR_PATTERN = '___*___';

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
        if ($segments === null) {
            return null;
        }

        $dir = rtrim($this->baseDir, '/');
        $params = [];
        $namespaceSegments = [];

        // "/" is the one path with no segment of its own: its resource is index.php.
        if ($segments === []) {
            $rootFile = $dir . '/' . self::ROOT_FILE;
            if ($this->fileExists($rootFile)) {
                return [$this->classNameOf(self::ROOT_FILE), $rootFile, $params, [], $namespaceSegments];
            }

            return $this->catchAll($dir, $params, $namespaceSegments, []);
        }

        $lastIndex = count($segments) - 1;

        foreach ($segments as $i => $segment) {
            $name = strtolower($segment);
            $isLast = $i === $lastIndex;

            // 1. The resource for the final segment: <segment>.php
            $exactFile = $dir . '/' . $name . '.php';
            if ($isLast && $this->fileExists($exactFile)) {
                return [$name, $exactFile, $params, [], $namespaceSegments];
            }

            // 2. A container for what follows: <segment>/
            $exactDir = $dir . '/' . $name;
            if (is_dir($exactDir)) {
                $namespaceSegments[] = $name;
                $dir = $exactDir;
                continue;
            }

            // 3. Wildcard: a file ends the path, a directory carries it further
            $wildcard = $isLast
                ? $this->firstMatch($dir . '/' . self::WILDCARD_FILE_PATTERN)
                : $this->firstMatch($dir . '/' . self::WILDCARD_DIR_PATTERN, GLOB_ONLYDIR);

            if ($wildcard !== null) {
                $wildcardName = $isLast ? $this->classNameOf(basename($wildcard)) : basename($wildcard);
                $params[trim($wildcardName, '_')] = $segment;

                if ($isLast) {
                    return [$wildcardName, $wildcard, $params, [], $namespaceSegments];
                }

                $namespaceSegments[] = $wildcardName;
                $dir = $wildcard;
                continue;
            }

            // 4. Catch-all declared at this level
            return $this->catchAll($dir, $params, $namespaceSegments, array_slice($segments, $i));
        }

        // Every segment was consumed by a container: only a catch-all can answer.
        return $this->catchAll($dir, $params, $namespaceSegments, []);
    }

    /**
     * The catch-all declared in $dir, with whatever path is left over.
     *
     * @param array<string, string> $params
     * @param list<string> $namespaceSegments
     * @param list<string> $remaining
     * @return array{0: string, 1: string, 2: array<string, string>, 3: list<string>, 4: list<string>}|null
     */
    private function catchAll(string $dir, array $params, array $namespaceSegments, array $remaining): ?array
    {
        $file = $dir . '/' . self::CATCH_ALL_FILE;
        if (!$this->fileExists($file)) {
            return null;
        }

        return [$this->classNameOf(self::CATCH_ALL_FILE), $file, $params, $remaining, $namespaceSegments];
    }

    /**
     * File base name without the .php suffix — the name doubles as the class name.
     */
    private function classNameOf(string $fileName): string
    {
        return basename($fileName, '.php');
    }

    /**
     * First glob match, or null. Glob sorts, so the result is deterministic.
     */
    private function firstMatch(string $pattern, int $flags = 0): ?string
    {
        $matches = glob($pattern, $flags);
        if ($matches === false || $matches === []) {
            return null;
        }

        return $matches[0];
    }

    /**
     * Parse a path into a segment array, or null when the path must not be routed.
     *
     * Dot segments ('.', '..') are dropped, so the locator can never escape the
     * resource base directory. A segment carrying a backslash or a NUL byte could
     * only be an attempt at the same, so it is refused outright instead.
     *
     * @return list<string>|null
     */
    private function parsePath(string $path): ?array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return [];
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if (str_contains($segment, '\\') || str_contains($segment, "\0")) {
                return null;
            }
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
