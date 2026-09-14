<?php
declare(strict_types=1);

namespace TinyGears\Web;

/**
 * REST 资源基类。
 * 
 * 资源类继承本类，实现对应的 HTTP 方法（GET/POST/PUT/DELETE 等，大写）。
 * 方法接收 Request，返回 Response。
 */
abstract class AbstractResource
{
    /** 资源路径中的命名参数（从 URL 通配符解析而来） */
    protected array $params = [];

    /** 设置命名参数（由 TinyRest 调用） */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * 处理请求：before → HTTP方法 → after
     * 这是模板方法，子类一般不需要覆盖。
     */
    public function handle(Request $request, string $method): Response
    {
        $method = strtoupper($method);

        if (null !== $response = $this->before($request)) {
            return $response;
        }

        if (!method_exists($this, $method)) {
            return Response::json(['error' => 'Method Not Allowed'], 405);
        }

        $response = $this->$method($request);

        return $this->after($request, $response);
    }

    /**
     * 前置钩子。
     * 返回 Response 则短路（直接作为响应返回，不再调用方法）。
     * 返回 null 则继续执行。
     */
    protected function before(Request $request): ?Response
    {
        return null;
    }

    /**
     * 后置钩子。
     * 可以修改或替换响应对象。
     */
    protected function after(Request $request, Response $response): Response
    {
        return $response;
    }

    /**
     * 子资源分发。
     * 当 URL 路径还有剩余段时调用，子类可以覆盖此方法来分发子资源。
     * 默认返回 404。
     * 
     * @param list<string> $remaining 剩余路径段
     */
    public function handleSub(Request $request, array $remaining): Response
    {
        throw new ResourceNotFoundException();
    }

    /** 默认 GET 方法：405 Method Not Allowed */
    public function GET(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /** 默认 POST 方法：405 */
    public function POST(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /** 默认 PUT 方法：405 */
    public function PUT(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /** 默认 DELETE 方法：405 */
    public function DELETE(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /** 默认 PATCH 方法：405 */
    public function PATCH(Request $request): Response
    {
        return Response::json(['error' => 'Method Not Allowed'], 405);
    }

    /** OPTIONS 方法：默认返回 Allow 头 */
    public function OPTIONS(Request $request): Response
    {
        $methods = $this->getAllowedMethods();
        return new Response(
            status: 204,
            headers: ['Allow' => implode(', ', $methods)],
        );
    }

    /** HEAD 方法：默认和 GET 一样但没有 body */
    public function HEAD(Request $request): Response
    {
        $response = $this->GET($request);
        return new Response(
            body: '',
            status: $response->status,
            headers: $response->headers,
        );
    }

    /** 获取资源支持的 HTTP 方法（用于 Allow 头） */
    protected function getAllowedMethods(): array
    {
        $methods = ['OPTIONS', 'HEAD'];
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $reflection = new \ReflectionMethod($this, $method);
            if ($reflection->getDeclaringClass()->getName() !== self::class) {
                $methods[] = $method;
            }
        }
        return $methods;
    }
}
