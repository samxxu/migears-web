<?php
declare(strict_types=1);

namespace TinyGears\Web;

/**
 * 目录路由定位器。
 * 从 URL 路径逐级下降目录，找到对应的资源类文件。
 * 
 * 目录命名规则：
 *   Index.php             → 精确匹配当前目录
 *   ___name___            → 通配符参数，值作为命名参数传给资源
 *   CatchAllResource.php  → 兜底资源（匹配所有未匹配的剩余路径）
 * 
 * URL 到目录名的转换：URL 段会自动转成 StudlyCase 再匹配目录。
 * 例如 /users → Users 目录，/blog_posts → BlogPosts 目录。
 */
final class ResourceLocator
{
    public function __construct(
        private readonly string $baseDir,
    ) {}

    /**
     * 定位资源。
     * 返回 [短类名, 文件完整路径, 命名参数, 剩余路径段, 命名空间段数组]
     * 找不到返回 null。
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

            // 1. 精确匹配目录名（StudlyCase）
            $exactDir = $dir . '/' . $studly;
            if (is_dir($exactDir)) {
                $namespaceSegments[] = $studly;
                $dir = $exactDir;
                continue;
            }

            // 2. 通配符目录 ___name___
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

            // 3. 都没匹配到，检查当前目录是否有 CatchAllResource
            $catchAllFile = $dir . '/CatchAllResource.php';
            if ($this->fileExists($catchAllFile)) {
                $remaining = array_slice($segments, $i);
                return ['CatchAllResource', $catchAllFile, $params, $remaining, $namespaceSegments];
            }

            return null;
        }

        // 路径走完了，找 Index.php
        $indexFile = $dir . '/Index.php';
        if ($this->fileExists($indexFile)) {
            return ['Index', $indexFile, $params, [], $namespaceSegments];
        }

        // 或者找 CatchAllResource
        $catchAllFile = $dir . '/CatchAllResource.php';
        if ($this->fileExists($catchAllFile)) {
            return ['CatchAllResource', $catchAllFile, $params, [], $namespaceSegments];
        }

        return null;
    }

    /** 字符串转 StudlyCase：user_id → UserId, users → Users */
    private function toStudlyCase(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /** 解析路径为段数组 */
    private function parsePath(string $path): array
    {
        $path = trim($path, '/');
        return $path === '' ? [] : explode('/', $path);
    }

    /** 单元测试友好：可覆盖的 file_exists */
    protected function fileExists(string $path): bool
    {
        return file_exists($path);
    }
}
