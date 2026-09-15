# migears/web

A minimalist REST framework with directory-as-routing. Zero magic, zero global variables, core code under 600 lines.

## Features

- **Directory-as-routing** — The filesystem structure is your API, no routing table configuration needed
- **Lightweight Request/Response** — Custom objects, simpler and more intuitive than PSR-7
- **PSR-3 / PSR-4 / PSR-12** — Follows logging, autoloading, and coding standards
- **Minimal dependencies** — Only depends on `psr/log`
- **Under 600 lines** — Read the entire framework in one sitting
- **No global variables, no singletons** — Fully testable and injectable
- **`before()` / `after()` hooks** — Lightweight middleware alternative
- **`___param___` wildcard directories** — Capture URL segments as named parameters

## Installation

```bash
composer require migears/web
```

## Quick Start

### 1. Create Resource Directory

```
resources/
  Index.php              # GET /
  Users/
    Index.php            # GET /users, POST /users
    ___user_id___/
      Index.php          # GET /users/{user_id}, PUT /users/{user_id}, DELETE /users/{user_id}
      Posts/
        Index.php        # GET /users/{user_id}/posts
  Catchall/
    CatchAllResource.php # Fallback match for /catchall/*
```

### 2. Write Resource Class

```php
<?php
// resources/Users/___user_id___/Index.php

use miGears\Web\AbstractResource;
use miGears\Web\Request;
use miGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $userId = (int) $this->param('user_id');
        return Response::json(['id' => $userId, 'name' => 'User ' . $userId]);
    }

    public function PUT(Request $request): Response
    {
        $userId = (int) $this->param('user_id');
        $body = $request->body;
        return Response::json(['id' => $userId, 'updated' => $body], 200);
    }

    protected function before(Request $request): ?Response
    {
        // Returning a Response short-circuits dispatch
        if ($request->header('X-Auth') === '') {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        return null;
    }

    protected function after(Request $request, Response $response): Response
    {
        // Modify the response and return it
        return $response->withHeader('X-Powered-By', 'migears');
    }
}
```

### 3. Bootstrap

```php
<?php
use miGears\Web\MiRest;
use miGears\Web\Request;

$rest = new MiRest(
    baseDir: __DIR__ . '/resources',
    namespace: 'App\\Resources',
);

$response = $rest->handle(Request::fromGlobals());
$response->send();
```

## Routing Rules

For a request path `/foo/bar/baz`, the locator descends level by level:

1. **Exact directory match** — If a `Foo/` directory exists, enter it and continue matching `bar/baz`
2. **Wildcard parameter** — If a `___*___/` directory exists, use the first one, capture the current segment as a parameter, and continue
3. **CatchAll fallback** — If the current directory has `CatchAllResource.php`, match all remaining paths
4. **404** — If none of the above match, return 404

After the path is fully traversed, resource files are looked up in the following order:
- `Index.php` — Index resource for the current directory
- `CatchAllResource.php` — Catch-all resource

URL segments are automatically converted to StudlyCase to match directory names (`/users` → `Users`, `/blog_posts` → `BlogPosts`).

## API Reference

### MiRest

```php
public function handle(Request $request): Response
```

Main entry point, dispatches the request to the corresponding resource and returns a response.

```php
$rest->before(callable $handler): self      // Global before hook
$rest->after(callable $handler): self       // Global after hook
$rest->notFound(callable $handler): self    // Custom 404 handler
$rest->error(callable $handler): self       // Custom exception handler
```

### Request

```php
$request->method      // HTTP method: GET, POST, PUT, DELETE...
$request->path        // Request path: /users/123
$request->query       // Query parameter array
$request->body        // Parsed body array (JSON/form)
$request->headers     // Request header array
$request->server      // $_SERVER array

$request->header(string $name, mixed $default = null): mixed
Request::fromGlobals(): self
```

### Response

```php
Response::json(mixed $data, int $status = 200): self
Response::html(string $html, int $status = 200): self
Response::redirect(string $url, int $status = 302): self
Response::empty(int $status = 204): self

$response->withHeader(string $name, string $value): self
$response->withStatus(int $status): self
$response->send(): void
```

### AbstractResource

| Method | Description |
|--------|-------------|
| `GET() / POST() / PUT() / DELETE()` | HTTP method handlers (override in subclasses) |
| `before(Request): ?Response` | Before hook, short-circuits if a response is returned |
| `after(Request, Response): Response` | After hook, modifies and returns the response |
| `param(string $name, mixed $default = null): mixed` | Get a named route parameter |
| `assertInt(mixed, string): int` | Validate as integer, throws 404 on failure |
| `$this->params` | All named parameters array |

## License

MIT

---

# migears/web

极简 REST 框架，目录即路由。零魔法、零全局变量，核心代码不到 600 行。

## 特性

- **目录即路由** — 文件系统结构就是你的 API，无需配置路由表
- **轻量 Request/Response** — 自定义对象，比 PSR-7 更简洁直观
- **PSR-3 / PSR-4 / PSR-12** — 遵循日志、自动加载、编码规范
- **依赖极少** — 仅依赖 `psr/log`
- **不到 600 行** — 一口气读完整个框架
- **无全局变量、无单例** — 完全可测试、可注入
- **`before()` / `after()` 钩子** — 轻量级中间件替代方案
- **`___param___` 通配符目录** — 捕获 URL 段作为命名参数

## 安装

```bash
composer require migears/web
```

## 快速开始

### 1. 创建资源目录

```
resources/
  Index.php              # GET /
  Users/
    Index.php            # GET /users, POST /users
    ___user_id___/
      Index.php          # GET /users/{user_id}, PUT /users/{user_id}, DELETE /users/{user_id}
      Posts/
        Index.php        # GET /users/{user_id}/posts
  Catchall/
    CatchAllResource.php # 兜底匹配 /catchall/*
```

### 2. 编写资源类

```php
<?php
// resources/Users/___user_id___/Index.php

use miGears\Web\AbstractResource;
use miGears\Web\Request;
use miGears\Web\Response;

class Index extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $userId = (int) $this->param('user_id');
        return Response::json(['id' => $userId, 'name' => 'User ' . $userId]);
    }

    public function PUT(Request $request): Response
    {
        $userId = (int) $this->param('user_id');
        $body = $request->body;
        return Response::json(['id' => $userId, 'updated' => $body], 200);
    }

    protected function before(Request $request): ?Response
    {
        // 返回 Response 则短路分发
        if ($request->header('X-Auth') === '') {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        return null;
    }

    protected function after(Request $request, Response $response): Response
    {
        // 修改响应后返回
        return $response->withHeader('X-Powered-By', 'migears');
    }
}
```

### 3. 启动

```php
<?php
use miGears\Web\MiRest;
use miGears\Web\Request;

$rest = new MiRest(
    baseDir: __DIR__ . '/resources',
    namespace: 'App\\Resources',
);

$response = $rest->handle(Request::fromGlobals());
$response->send();
```

## 路由规则

对于请求路径 `/foo/bar/baz`，定位器逐级下降：

1. **精确目录匹配** — 如果存在 `Foo/` 目录，进入并继续匹配 `bar/baz`
2. **通配符参数** — 如果存在 `___*___/` 目录，使用第一个，捕获当前段为参数，继续
3. **CatchAll 兜底** — 如果当前目录有 `CatchAllResource.php`，匹配所有剩余路径
4. **404** — 以上都不匹配，返回 404

路径走完后，按以下顺序查找资源文件：
- `Index.php` — 当前目录的索引资源
- `CatchAllResource.php` — 兜底资源

URL 段会自动转 StudlyCase 匹配目录名（`/users` → `Users`，`/blog_posts` → `BlogPosts`）。

## API 参考

### MiRest

```php
public function handle(Request $request): Response
```

主入口，将请求分发到对应的资源并返回响应。

```php
$rest->before(callable $handler): self      // 全局前置钩子
$rest->after(callable $handler): self       // 全局后置钩子
$rest->notFound(callable $handler): self    // 自定义 404
$rest->error(callable $handler): self       // 自定义异常处理
```

### Request

```php
$request->method      // HTTP 方法: GET, POST, PUT, DELETE...
$request->path        // 请求路径: /users/123
$request->query       // 查询参数数组
$request->body        // 解析后的 body 数组（JSON/表单）
$request->headers     // 请求头数组
$request->server      // $_SERVER 数组

$request->header(string $name, mixed $default = null): mixed
Request::fromGlobals(): self
```

### Response

```php
Response::json(mixed $data, int $status = 200): self
Response::html(string $html, int $status = 200): self
Response::redirect(string $url, int $status = 302): self
Response::empty(int $status = 204): self

$response->withHeader(string $name, string $value): self
$response->withStatus(int $status): self
$response->send(): void
```

### AbstractResource

| 方法 | 说明 |
|------|------|
| `GET() / POST() / PUT() / DELETE()` | HTTP 方法处理器（子类重写） |
| `before(Request): ?Response` | 前置钩子，返回响应则短路 |
| `after(Request, Response): Response` | 后置钩子，修改并返回响应 |
| `param(string $name, mixed $default = null): mixed` | 获取命名路由参数 |
| `assertInt(mixed, string): int` | 验证整数，失败抛 404 |
| `$this->params` | 所有命名参数数组 |

## License

MIT
