<?php
declare(strict_types=1);

namespace TinyGears\Web;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * TinyRest — 极简 REST 框架。
 * 
 * 核心特色：目录即路由。不需要定义路由表，文件系统就是路由表。
 * 
 * 用法：
 *   $rest = new TinyRest(__DIR__ . '/resources', 'App\\Resources');
 *   $response = $rest->handle(Request::fromGlobals());
 *   $response->send();
 */
final class TinyRest
{
    private ResourceLocator $locator;
    private LoggerInterface $logger;

    /** @var list<callable(Request): ?Response> */
    private array $beforeHandlers = [];

    /** @var list<callable(Request, Response): Response> */
    private array $afterHandlers = [];

    /** @var ?callable(): Response */
    private $notFoundHandler = null;

    /** @var ?callable(\Throwable): Response */
    private $errorHandler = null;

    public function __construct(
        private readonly string $baseDir,
        private readonly string $namespace = '',
        ?LoggerInterface $logger = null,
    ) {
        $this->locator = new ResourceLocator($baseDir);
        $this->logger = $logger ?? new NullLogger();
    }

    /** 添加全局前置钩子，返回 Response 则短路 */
    public function before(callable $handler): self
    {
        $this->beforeHandlers[] = $handler;
        return $this;
    }

    /** 添加全局后置钩子，可以修改响应 */
    public function after(callable $handler): self
    {
        $this->afterHandlers[] = $handler;
        return $this;
    }

    /** 自定义 404 处理 */
    public function notFound(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    /** 自定义异常处理 */
    public function error(callable $handler): self
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /** 处理请求，返回响应 */
    public function handle(Request $request): Response
    {
        try {
            // 全局前置钩子
            foreach ($this->beforeHandlers as $handler) {
                if (null !== $response = $handler($request)) {
                    return $this->runAfter($request, $response);
                }
            }

            // 定位资源
            $result = $this->locator->locate($request->path);
            if ($result === null) {
                return $this->runAfter($request, $this->handleNotFound());
            }

            [$shortClassName, $filePath, $params, $remaining, $namespaceSegments] = $result;

            // 拼接完整类名并加载文件
            $className = $this->buildClassName($shortClassName, $namespaceSegments);
            if (!class_exists($className, false)) {
                require_once $filePath;
            }

            // 实例化资源
            $resource = $this->createResource($className);
            $resource->setParams($params);

            // 有剩余路径 → 子资源分发
            if (!empty($remaining)) {
                $response = $resource->handleSub($request, $remaining);
                return $this->runAfter($request, $response);
            }

            // 调用资源的 handle 方法（模板方法：before → method → after）
            $response = $resource->handle($request, $request->method);

            return $this->runAfter($request, $response);

        } catch (ResourceNotFoundException $e) {
            return $this->runAfter($request, $this->handleNotFound());
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return $this->runAfter($request, $this->handleError($e));
        }
    }

    /** 运行全局后置钩子 */
    private function runAfter(Request $request, Response $response): Response
    {
        foreach ($this->afterHandlers as $handler) {
            $response = $handler($request, $response);
        }
        return $response;
    }

    /** 处理 404 */
    private function handleNotFound(): Response
    {
        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)();
        }
        return Response::json(['error' => 'Not Found'], 404);
    }

    /** 处理异常 */
    private function handleError(\Throwable $e): Response
    {
        if ($this->errorHandler !== null) {
            return ($this->errorHandler)($e);
        }
        return Response::json(['error' => 'Internal Server Error'], 500);
    }

    /** 拼接完整类名 */
    private function buildClassName(string $shortName, array $namespaceSegments): string
    {
        $parts = [];
        if ($this->namespace !== '') {
            $parts[] = trim($this->namespace, '\\');
        }
        foreach ($namespaceSegments as $seg) {
            $parts[] = $seg;
        }
        $parts[] = $shortName;
        return implode('\\', $parts);
    }

    /** 创建资源实例 */
    private function createResource(string $className): AbstractResource
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Resource class not found: $className");
        }
        $resource = new $className();
        if (!$resource instanceof AbstractResource) {
            throw new \RuntimeException("Resource must extend AbstractResource: $className");
        }
        return $resource;
    }
}
