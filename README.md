# migears/web

![Version](https://img.shields.io/badge/version-2.0.0-blue)

A minimalist REST framework with directory-as-routing. Zero magic, zero global variables, core code under 600 lines.

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **Directory-as-routing** — The filesystem structure is your API, no routing table configuration needed
- **Lightweight Request/Response** — Custom objects, simpler and more intuitive than PSR-7
- **PSR-3 / PSR-4 / PSR-12** — Follows logging, autoloading, and coding standards
- **Minimal dependencies** — Only depends on `psr/log`
- **Under 600 lines** — Read the entire framework in one sitting
- **No global variables, no singletons** — Fully testable and injectable
- **Built-in DI container** — Register only what needs configuration (PDO, Redis, Logger); everything else is just `new`
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

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

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
use MiGears\Web\MiRest;
use MiGears\Web\Request;

$rest = new MiRest(
    baseDir: __DIR__ . '/resources',
    namespace: 'App\\Resources',
);

// Register only what needs configuration
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$response = $rest->handle(Request::fromGlobals());
$response->send();
```

## Dependency Injection (DI) Container

MiRest ships with a tiny built-in DI container for wiring **only the services that need configuration** (PDO, Redis, Logger, ...). Everything that doesn't need setup is just `new` — no container involved. This keeps the framework free of magic while staying fully injectable and testable.

### Design philosophy

- **Register only what needs configuration** — the container exists for objects with setup (DSNs, hosts, credentials). Pure value objects (Request, Response, Form, Str, ...) are simply `new`'d in place.
- **Closure-based, lazy factories** — each service is a factory closure. It is **not** executed until first requested, and its result is cached.
- **Singleton by default** — every service resolves to one shared instance for the lifetime of the `MiRest` object.
- **Opt-in, no auto-wiring** — there is no reflection and no magical resolution; a service must be registered explicitly to be used. Explicit beats magical: this keeps the whole container under ~30 lines and instantly readable.
- **No globals, no static** — the container lives on the `MiRest` instance and is handed to every resource.

### The whole API: three methods

```php
$rest->set(string $id, callable $factory): self   // Register a factory (lazy)
$rest->has(string $id): bool                       // Registered (factory pending or resolved)?
$rest->service(string $id): mixed                   // Resolve the instance (cached for reuse)
```

`$id` is either a class name (`PDO::class`) or any custom string (`'logger'`), so interfaces and friendly aliases work naturally.

**Lazy-singleton flow**:

```php
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$rest->has(PDO::class);      // true — factory registered (not yet resolved)
$rest->service(PDO::class);  // 1st call runs the factory, caches the result
$rest->service(PDO::class);  // 2nd call returns the SAME cached instance

$pdo = $rest->service('missing'); // null — not registered (no exception)
```

Re-registering with `set()` replaces the factory **and drops the cached instance**, so the next `service()` call builds a fresh object — handy for redeploy/rebuild or tests.

### Using services inside resources

Each resource receives the container (via its `MiRest` instance) and resolves services through `$this->service()`:

```php
class Users extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $pdo = $this->service(PDO::class);   // shared PDO from the container
        $dao = new UserDao($pdo);             // plain `new` — no container needed
        return Response::json($dao->getAll());
    }
}
```

Typical bootstrap wiring:

```php
$rest = new MiRest(baseDir: __DIR__ . '/resources', namespace: 'App\\Resources');

$rest->set(PDO::class,         fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
$rest->set('logger',           fn() => new Monolog\Logger('app'));
$rest->set(RedisCache::class, fn() => new RedisCache(new Redis(), 'localhost', 6379));
```

**Rule of thumb**: needs configuration → register via `set()`.
Needs nothing → just `new`. Either way your code never depends on container magic.

## Routing Rules

For a request path `/foo/bar/baz`, the locator descends level by level:

1. **Exact directory match** — If a `Foo/` directory exists, enter it and continue matching `bar/baz`
2. **Wildcard parameter** — If a `___*___/` directory exists, use the first one, capture the current segment as a parameter, and continue
3. **CatchAll fallback** — If the current directory has `CatchAllResource.php`, match all remaining paths
4. **404** — If none of the above match, return 404

After the path is fully traversed, resource files are looked up in the following order:
- `Index.php` — Index resource for the current directory
- `CatchAllResource.php` — Catch-all resource

Finally, the located resource serves the request through the template method `handle()` (`before` → HTTP method → `after`), so **hooks run consistently** whether or not extra path segments matched. A catch-all resource receives any unmatched remaining segments via `$this->remaining`.

URL segments are automatically converted to StudlyCase to match directory names (`/users` → `Users`, `/blog_posts` → `BlogPosts`). `.` / `..` segments are ignored, so the locator can never escape the resource root; `baseDir` must be an existing directory or an `InvalidArgumentException` is thrown.

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
$rest->set(string $id, callable $factory): self    // Register service
$rest->has(string $id): bool                       // Check service exists
$rest->service(string $id): mixed                   // Get service (singleton)
```

### Request

```php
$request->method      // HTTP method: GET, POST, PUT, DELETE...
$request->path        // Request path: /users/123
$request->query       // Query parameter array
$request->body        // Parsed body array (JSON/form)
$request->headers     // Request header array
$request->server      // $_SERVER array
$request->files       // Uploaded files array ($_FILES)

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
| `service(string $id): mixed` | Get a registered service (PDO, Logger, etc.) |
| `assertInt(mixed, string): int` | Validate as integer, throws 404 on failure |
| `$this->params` | All named parameters array |
| `$this->remaining` | Remaining path segments (only for catch-all resources) |

## License

MIT

---

# migears/web

![Version](https://img.shields.io/badge/version-2.0.0-blue)

极简 REST 框架，目录即路由。零魔法、零全局变量，核心代码不到 600 行。

> **背景**：miGears 源自自研 PHP 框架 **TinyGears**，因 TinyGears 这一名字
> 已被开源社区占用，故近期更名并开源发布。

## 特性

- **目录即路由** — 文件系统结构就是你的 API，无需配置路由表
- **轻量 Request/Response** — 自定义对象，比 PSR-7 更简洁直观
- **PSR-3 / PSR-4 / PSR-12** — 遵循日志、自动加载、编码规范
- **依赖极少** — 仅依赖 `psr/log`
- **不到 600 行** — 一口气读完整个框架
- **无全局变量、无单例** — 完全可测试、可注入
- **内置 DI 容器** — 只注册需要配置的部分（PDO、Redis、Logger），其他直接 `new`
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

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

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
use MiGears\Web\MiRest;
use MiGears\Web\Request;

$rest = new MiRest(
    baseDir: __DIR__ . '/resources',
    namespace: 'App\\Resources',
);

// 只注册需要配置的服务
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$response = $rest->handle(Request::fromGlobals());
$response->send();
```

## 依赖注入（DI）容器

MiRest 内置一个极简 DI 容器，只用于装配**需要配置的服务**（PDO、Redis、Logger 等）。不需要配置的，直接用 `new`，完全绕开容器。这样既保持框架零魔法，又做到完全可注入、可测试。

### 设计哲学

- **只注册需要配置的部分** — 容器存在的意义是有配置需求的对象（DSN、主机、账号密码）。纯粹的值对象（Request、Response、Form、Str 等）就地 `new` 即可。
- **闭包工厂、懒加载** — 每个服务都是一个工厂闭包，**首次被请求时才执行**，结果会被缓存。
- **默认单例** — 每个服务在 `MiRest` 对象生命周期内只解析出一个共享实例。
- **显式登记，不做自动装配** — 没有反射、没有魔法解析；想用某个服务必须显式 `set()`。显式优于魔法：这让整个容器保持在 30 行以内，可一口气读懂。
- **无全局、无静态** — 容器挂在 `MiRest` 实例上，随框架注入到每个资源。

### 全部 API 只有三个方法

```php
$rest->set(string $id, callable $factory): self   // 注册工厂（懒加载）
$rest->has(string $id): bool                       // 是否已注册（未解析工厂或已解析实例）
$rest->service(string $id): mixed                   // 解析实例（缓存复用）
```

`$id` 可以是类名（`PDO::class`），也可以是任意字符串别名（`'logger'`），因此接口、友好别名都能自然使用。

**懒加载单例的流程**：

```php
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$rest->has(PDO::class);      // true — 工厂已注册（尚未解析）
$rest->service(PDO::class);  // 第 1 次调用：执行工厂并缓存结果
$rest->service(PDO::class);  // 第 2 次调用：返回同一个缓存实例

$pdo = $rest->service('missing'); // null — 未注册（不抛异常）
```

重新 `set()` 会替换工厂**并丢弃已缓存的实例**，下次 `service()` 会构建全新对象——适合重新部署或测试场景。

### 在资源类内使用服务

每个资源都会拿到（其所属 `MiRest` 的）容器，通过 `$this->service()` 解析服务：

```php
class Users extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $pdo = $this->service(PDO::class);   // 容器中的共享 PDO
        $dao = new UserDao($pdo);             // 普通 `new` — 不需要容器
        return Response::json($dao->getAll());
    }
}
```

典型的启动装配：

```php
$rest = new MiRest(baseDir: __DIR__ . '/resources', namespace: 'App\\Resources');

$rest->set(PDO::class,          fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
$rest->set('logger',            fn() => new Monolog\Logger('app'));
$rest->set(RedisCache::class, fn() => new RedisCache(new Redis(), 'localhost', 6379));
```

**经验法则**：需要配置 → 用 `set()` 注册。不需要配置 → 直接 `new`。无论哪种方式，你的业务代码都不依赖容器的任何魔法。

## 路由规则

对于请求路径 `/foo/bar/baz`，定位器逐级下降：

1. **精确目录匹配** — 如果存在 `Foo/` 目录，进入并继续匹配 `bar/baz`
2. **通配符参数** — 如果存在 `___*___/` 目录，使用第一个，捕获当前段为参数，继续
3. **CatchAll 兜底** — 如果当前目录有 `CatchAllResource.php`，匹配所有剩余路径
4. **404** — 以上都不匹配，返回 404

路径走完后，按以下顺序查找资源文件：
- `Index.php` — 当前目录的索引资源
- `CatchAllResource.php` — 兜底资源

最终，定位到的资源统一通过模板方法 `handle()`（`before` → HTTP 方法 → `after`）处理请求，因此**无论是否有多余路径段，前置/后置钩子行为一致**。兜底资源可通过 `$this->remaining` 拿到未匹配的剩余路径段。

URL 段会自动转 StudlyCase 匹配目录名（`/users` → `Users`，`/blog_posts` → `BlogPosts`）。路径中的 `.` / `..` 段会被忽略，定位器永远不会逃出资源根目录；`baseDir` 必须是已存在的目录，否则抛出 `InvalidArgumentException`。

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
$rest->set(string $id, callable $factory): self    // 注册服务
$rest->has(string $id): bool                       // 检查服务是否存在
$rest->service(string $id): mixed                   // 获取服务（单例）
```

### Request

```php
$request->method      // HTTP 方法: GET, POST, PUT, DELETE...
$request->path        // 请求路径: /users/123
$request->query       // 查询参数数组
$request->body        // 解析后的 body 数组（JSON/表单）
$request->headers     // 请求头数组
$request->server      // $_SERVER 数组
$request->files       // 上传文件数组（$_FILES）

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
| `service(string $id): mixed` | 获取已注册服务（PDO、Logger 等） |
| `assertInt(mixed, string): int` | 验证整数，失败抛 404 |
| `$this->params` | 所有命名参数数组 |
| `$this->remaining` | 剩余路径段（仅 catch-all 资源有值） |

## License

MIT
