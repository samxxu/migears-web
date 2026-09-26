# migears/web

![Version](https://img.shields.io/badge/version-2.0.1-blue)

A minimalist REST framework with directory-as-routing. Zero magic, zero global variables, core code under 500 lines.

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **Directory-as-routing** — The filesystem structure is your API, no routing table configuration needed
- **Lightweight Request/Response** — Custom objects, simpler and more intuitive than PSR-7
- **PSR-3 / PSR-4 / PSR-11 / PSR-12** — Follows logging, autoloading, container, and coding standards
- **Minimal dependencies** — Only `psr/container` and `psr/log`
- **Under 500 lines of code** — Comments and blank lines excluded, so it stays true as the documentation grows; read the entire framework in one sitting
- **No global variables, no singletons** — Fully testable and injectable
- **Built-in container, PSR-11** — Register only what needs configuration (PDO, Redis, Logger, DAOs, Managers); everything else is just `new`
- **`before()` / `after()` hooks** — Lightweight middleware alternative
- **`___param___` wildcard directories** — Capture URL segments as named parameters

## Installation

```bash
composer require migears/web
```

Requires: PHP 8.1+, `psr/container`, `psr/log`.

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

## Container

MiRest **is** the container: register only what needs configuration (PDO, Redis, Logger, a DAO, a Manager); everything that merely needs `new` is constructed in place. It is a **PSR-11** container — `Psr\Container\ContainerInterface` — so anything that speaks PSR-11 reaches it without a bespoke interface, and this package does not have to depend on the package that consumes it.

### Design philosophy

- **Register only what needs configuration** — the container exists for objects with setup (DSNs, hosts, credentials). Pure value objects (Request, Response, Domains, ...) are simply `new`'d in place.
- **Closure-based, lazy factories** — each entry is a factory closure. It is **not** executed until first requested, and its result is cached.
- **One instance per entry** — every entry resolves to one shared instance for the lifetime of the `MiRest` object.
- **Opt-in, no auto-wiring** — there is no reflection and no magical resolution; an entry must be registered explicitly to be used. Explicit beats magical: this keeps the whole container under ~40 lines and instantly readable.
- **No globals, no static** — the container lives on the `MiRest` instance and is handed to every resource.

### The whole API: three methods

```php
$rest->set(string $id, callable $factory): self   // Register a factory (lazy)
$rest->has(string $id): bool                       // Registered (factory pending or resolved)?
$rest->get(string $id): mixed                      // Resolve the instance (cached for reuse)
```

`$id` is either a class name (`PDO::class`) or any custom string (`'config'`), so interfaces and friendly aliases work naturally. `has()` and `get()` are PSR-11; `set()` is MiRest's own registration side.

**Lazy-singleton flow**:

```php
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$rest->has(PDO::class);   // true — factory registered (not yet resolved)
$rest->get(PDO::class);   // 1st call runs the factory, caches the result
$rest->get(PDO::class);   // 2nd call returns the SAME cached instance

$rest->get('missing');    // throws NotFoundException — an unregistered id is an assembly mistake
```

`get()` throwing is PSR-11's requirement rather than a style choice: a typo or a forgotten registration must fail where it is asked for, instead of surfacing later as a `null` that "is not an object". Probing for something optional is what `has()` is for. `NotFoundException` is also a `RuntimeException`, and it is not `ResourceNotFoundException` — that one means "no resource matched this path" (a 404), this one means "the application was not wired correctly" (a 500).

Re-registering with `set()` replaces the factory **and drops the cached instance**, so the next `get()` builds a fresh object — handy for redeploy/rebuild or tests.

### Using the container inside resources

Each resource receives the container (its `MiRest` instance) and resolves entries through `$this->resolve()`:

```php
class Users extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $pdo    = $this->resolve(PDO::class);              // shared PDO from the container
        $logger = $this->resolve(LoggerInterface::class);  // and the shared logger
        $dao    = new UserDao($pdo, $logger);              // plain `new` — no container needed

        return Response::json($dao->getAll());
    }
}
```

The accessor is named `resolve()` rather than `get()` on purpose: PHP method names are case-insensitive, so a `get()` here would collide with the HTTP `GET()` verb.

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

Once located, the HTTP verb decides what runs: the handler the resource declares, or `405 Method Not Allowed` when it declares none. The default `OPTIONS()` reports what the resource supports through an `Allow` header.

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
$rest->set(string $id, callable $factory): self    // Register an entry (lazy)
$rest->has(string $id): bool                       // Is it registered?
$rest->get(string $id): mixed                      // Resolve the instance (cached)
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
| `GET() / POST() / PUT() / DELETE() / PATCH()` | HTTP method handlers (override in subclasses). A method you do not override answers `405 Method Not Allowed` |
| `OPTIONS()` | Answers `204` with an `Allow` header listing what the resource supports (always `OPTIONS` and `HEAD`, plus whichever of the five above the subclass declares — detected by reflection, so simply declaring `POST()` is enough) |
| `HEAD()` | `GET()` without a body |
| `before(Request): ?Response` | Before hook, short-circuits if a response is returned |
| `after(Request, Response): Response` | After hook, modifies and returns the response |
| `param(string $name, mixed $default = null): mixed` | Get a named route parameter |
| `resolve(string $id): mixed` | Resolve a registered entry (PDO, Logger, a Manager, ...) |
| `assertInt(mixed, string): int` | Validate as integer, throws 404 on failure |
| `$this->params` | All named parameters array |
| `$this->remaining` | Remaining path segments (only for catch-all resources) |

## License

MIT

---

# migears/web

![Version](https://img.shields.io/badge/version-2.0.1-blue)

极简 REST 框架，目录即路由。零魔法、零全局变量，核心代码不到 500 行。

> **背景**：miGears 源自自研 PHP 框架 **TinyGears**，因 TinyGears 这一名字
> 已被开源社区占用，故近期更名并开源发布。

## 特性

- **目录即路由** — 文件系统结构就是你的 API，无需配置路由表
- **轻量 Request/Response** — 自定义对象，比 PSR-7 更简洁直观
- **PSR-3 / PSR-4 / PSR-11 / PSR-12** — 遵循日志、自动加载、容器、编码规范
- **依赖极少** — 仅 `psr/container` 与 `psr/log`
- **代码不到 500 行** — 不计注释与空行，因此文档怎么长都不会让这句话失真；一口气读完整个框架
- **无全局变量、无单例** — 完全可测试、可注入
- **内置容器，PSR-11** — 只注册需要配置的部分（PDO、Redis、Logger、DAO、Manager），其他直接 `new`
- **`before()` / `after()` 钩子** — 轻量级中间件替代方案
- **`___param___` 通配符目录** — 捕获 URL 段作为命名参数

## 安装

```bash
composer require migears/web
```

要求：PHP 8.1+，`psr/container`，`psr/log`。

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

## 容器

MiRest **本身就是**容器：只注册需要配置的东西（PDO、Redis、Logger、DAO、Manager）；只需要 `new` 的东西就地构造。
它是一个 **PSR-11** 容器 —— `Psr\Container\ContainerInterface` —— 所以任何会说 PSR-11 的代码都能取用它，
不必为它另立接口，本包也无需依赖使用它的那个包。

### 设计哲学

- **只注册需要配置的部分** — 容器存在的意义是有配置需求的对象（DSN、主机、账号密码）。纯粹的值对象（Request、Response、Domain 等）就地 `new` 即可。
- **闭包工厂、懒加载** — 每个条目都是一个工厂闭包，**首次被请求时才执行**，结果会被缓存。
- **一个条目一个实例** — 每个条目在 `MiRest` 对象生命周期内只解析出一个共享实例。
- **显式登记，不做自动装配** — 没有反射、没有魔法解析；想用某个条目必须显式 `set()`。显式优于魔法：这让整个容器保持在 40 行以内，可一口气读懂。
- **无全局、无静态** — 容器挂在 `MiRest` 实例上，随框架注入到每个资源。

### 全部 API 只有三个方法

```php
$rest->set(string $id, callable $factory): self   // 注册工厂（懒加载）
$rest->has(string $id): bool                       // 是否已注册（工厂待执行或已解析）
$rest->get(string $id): mixed                      // 解析实例（缓存复用）
```

`$id` 可以是类名（`PDO::class`），也可以是任意字符串别名（`'config'`），因此接口、友好别名都能自然使用。
`has()` 与 `get()` 是 PSR-11 的；`set()` 是 MiRest 自己的注册面。

**懒加载单例的流程**：

```php
$rest->set(PDO::class, fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));

$rest->has(PDO::class);   // true — 工厂已注册（尚未解析）
$rest->get(PDO::class);   // 第 1 次调用：执行工厂并缓存结果
$rest->get(PDO::class);   // 第 2 次调用：返回同一个缓存实例

$rest->get('missing');    // 抛 NotFoundException —— 未注册的 id 属于装配错误
```

`get()` 会抛不是风格选择，而是 PSR-11 的要求：拼错或漏注册必须在要它的地方失败，
而不是过一阵子以「某个 `null` 不是对象」的形式冒出来。要探测可有可无的东西，用 `has()`。
`NotFoundException` 同时也是 `RuntimeException`；它不是 `ResourceNotFoundException` ——
后者是「没有资源匹配这个路径」（404），前者是「应用没装配对」（500）。

重新 `set()` 会替换工厂**并丢弃已缓存的实例**，下次 `get()` 会构建全新对象 —— 适合重新部署或测试场景。

### 在资源类内使用容器

每个资源都会拿到容器（其所属 `MiRest` 实例），通过 `$this->resolve()` 取条目：

```php
class Users extends AbstractResource
{
    public function GET(Request $request): Response
    {
        $pdo    = $this->resolve(PDO::class);              // 容器中的共享 PDO
        $logger = $this->resolve(LoggerInterface::class);  // 以及共享 logger
        $dao    = new UserDao($pdo, $logger);              // 普通 `new` —— 不需要容器

        return Response::json($dao->getAll());
    }
}
```

访问器叫 `resolve()` 而不是 `get()`，是刻意的：PHP 方法名不区分大小写，叫 `get()` 会与 HTTP 的 `GET()` 冲突。

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

定位到资源之后，由 HTTP 动词决定执行什么：资源声明了就执行对应处理器，没声明则返回 `405 Method Not Allowed`；默认的 `OPTIONS()` 会通过 `Allow` 头报告该资源支持哪些方法。

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
$rest->set(string $id, callable $factory): self    // 注册条目（懒加载）
$rest->has(string $id): bool                       // 是否已注册
$rest->get(string $id): mixed                      // 解析实例（缓存）
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
| `GET() / POST() / PUT() / DELETE() / PATCH()` | HTTP 方法处理器（子类重写）。未重写的方法一律返回 `405 Method Not Allowed` |
| `OPTIONS()` | 返回 `204` 并带 `Allow` 头，列出该资源支持的方法（始终含 `OPTIONS` 与 `HEAD`，再加上子类声明的那几个 —— 通过反射探测，所以只要声明 `POST()` 就会出现） |
| `HEAD()` | 与 `GET()` 相同但不返回响应体 |
| `before(Request): ?Response` | 前置钩子，返回响应则短路 |
| `after(Request, Response): Response` | 后置钩子，修改并返回响应 |
| `param(string $name, mixed $default = null): mixed` | 获取命名路由参数 |
| `resolve(string $id): mixed` | 解析已注册的条目（PDO、Logger、Manager 等） |
| `assertInt(mixed, string): int` | 验证整数，失败抛 404 |
| `$this->params` | 所有命名参数数组 |
| `$this->remaining` | 剩余路径段（仅 catch-all 资源有值） |

## License

MIT
