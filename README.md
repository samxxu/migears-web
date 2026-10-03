# migears/web

![Version](https://img.shields.io/badge/version-2.2.0-blue)

A minimalist REST framework with directory-as-routing. Zero magic, zero global variables, core code under 600 lines.

> **Upgrading from 2.1?** This release changes the resource layout and the class
> naming rule: one file per path (`users.php`, `users/___user_id___.php`) instead
> of a directory holding an `Index.php`, and a resource file's name is now also
> its class name. Every existing `resources/` tree has to be renamed — see
> [Routing Rules](#routing-rules).

> **Background**: miGears is the open-source successor of **TinyGears**, a
> self-developed PHP framework. It was renamed and open-sourced recently because
> the name *TinyGears* is already taken in the open-source community.

## Features

- **Directory-as-routing** — The filesystem structure is your API, no routing table configuration needed
- **Lightweight Request/Response** — Custom objects, simpler and more intuitive than PSR-7
- **PSR-3 / PSR-4 / PSR-11 / PSR-12** — Follows logging, autoloading, container, and coding standards
- **Minimal dependencies** — Only `psr/container` and `psr/log`
- **Under 600 lines of code** — Comments and blank lines excluded, so it stays true as the documentation grows; read the entire framework in one sitting
- **No global variables, no process-wide static singletons** — Fully testable and injectable; the container is per-instance, and an entry is shared only for the lifetime of that one `MiRest` (the lazy singleton described below)
- **Built-in container, PSR-11** — Register only what needs configuration (PDO, Redis, Logger, DAOs, Managers); everything else is just `new`
- **`before()` / `after()` hooks** — Lightweight middleware alternative
- **`___param___` wildcard segments** — Capture URL segments as named parameters

## Boundaries

**In scope**

- Directory-as-routing and dispatch: `ResourceLocator` walks the filesystem (`<segment>.php` / `___param___` / `__other__.php`), and `MiRest::handle()` wires global before/after hooks, the 404 and error handlers, and the template method `before` → HTTP verb → `after`.
- The lightweight `Request` / `Response` objects (`Request::fromGlobals()`, `Response::json` / `html` / `redirect` / `empty`, `send()`) and `AbstractResource`'s HTTP verb handlers, including the `OPTIONS` / `HEAD` defaults and the `405` response with an `Allow` header.
- The PSR-11 container that `MiRest` itself is (`set()` / `has()` / `get()`, lazy-singleton factories) plus `$this->resolve()` inside resources — it is the composition root that wires the sibling modules together.

**Not in scope (by design)**

- Persistence / data access — no database connection, SQL builder or DAO; `PDO` is only ever registered as an opaque container entry. Owned by `migears/dao` / `migears/sql` / `migears/mitable`.
- Business logic and orchestration — managers, the event bus and domain models come from `migears/manager` / `migears/domain`; this package only calls the resource handler it dispatches to.
- Rendering / templating — `Response::html()` merely carries an HTML string; view compilation and layout rendering belong to `migears/pages` / `migears/template`.
- Logging, caching, auth, mail, image and i18n — each is a sibling module (`migears/log` / `migears/cache` / `migears/security` / `migears/mail` / `migears/image` / `migears/i18n`); this package only resolves a PSR-3 `LoggerInterface` from the container and implements none of them.

## Installation

```bash
composer require migears/web
```

Requires: PHP 8.1+, `psr/container`, `psr/log`.

## Quick Start

### 1. Create Resource Directory

```
resources/
  index.php                  # GET /
  users.php                  # GET /users, POST /users
  users/
    ___user_id___.php        # GET /users/{user_id}, PUT /users/{user_id}, DELETE /users/{user_id}
    ___user_id___/
      posts.php              # GET /users/{user_id}/posts
  catchall/
    __other__.php            # Fallback match for /catchall/*
```

### 2. Write Resource Class

The class name is the file name. The file below lives at
`resources/users/___user_id___.php` and the bootstrap uses `namespace: 'App\\Resources'`,
so the class is `App\Resources\users\___user_id___` — the directories contribute
their names verbatim, with no case conversion.

```php
<?php
// resources/users/___user_id___.php

namespace App\Resources\users;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___user_id___ extends AbstractResource
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
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new \Psr\Log\NullLogger()); // required; NullLogger for silence
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
- **Opt-in, no auto-wiring** — the container does no reflection-based auto-wiring and no magical resolution; an entry must be registered explicitly to be used. Explicit beats magical: this keeps the whole container under ~40 lines and instantly readable. (The package's only use of reflection is elsewhere: detecting which HTTP verbs a resource declares, feeding the `OPTIONS` default and the `405` `Allow` header.)
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

`get()` throwing is PSR-11's requirement rather than a style choice: a typo or a forgotten registration must fail where it is asked for, instead of surfacing later as a `null` that "is not an object". Probing for something optional is what `has()` is for. `NotFoundException` is also a `RuntimeException`, and it is not `ResourceNotFoundException` — that one means "no resource matched this path" (a 404), this one means "the application was not wired correctly", and since the container entries are resolved before dispatch, such a failure is thrown out of `handle()` rather than answered with a 500 response.

Re-registering with `set()` replaces the factory **and drops the cached instance**, so the next `get()` builds a fresh object — handy for redeploy/rebuild or tests.

### The framework's own logger

When the framework reports an internal error — a resource or a global `after` hook that throws — it writes through a `LoggerInterface` resolved from the container. There is no built-in default: it is resolved at the start of every `handle()` call, so an unregistered (or wrong-typed) entry fails loudly on the first request instead of being hidden behind a silent fallback. Registering `NullLogger` is how you ask for silence:

```php
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new \Psr\Log\NullLogger());
```

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

$rest->set(PDO::class,                    fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new Monolog\Logger('app'));
$rest->set(RedisCache::class,            fn() => new RedisCache(new Redis()));
```

**Rule of thumb**: needs configuration → register via `set()`.
Needs nothing → just `new`. Either way your code never depends on container magic.

> **Note on the `namespace` parameter**: Each resource file declares a class whose name is the file name (`users.php` → `class users`). If `namespace` is empty, all resource classes live in the global scope and will collide as soon as you have more than one resource — you will get a *"Cannot redeclare class"* fatal error. Always set a namespace for any project with more than one resource file.

## Routing Rules

A resource file is named after the path it answers: `/users` is `users.php`, and
`/users/42` is `users/___user_id___.php`. Nothing is case-converted — the file
name is the path, and the class name is the file name.

For a request path `/foo/bar/baz`, the locator descends level by level. At each
level, with `foo` as the current segment:

1. **`foo.php`** — when `foo` ends the path, this file answers it
2. **`foo/`** — when more path follows, the directory is entered and matching continues with `bar/baz`
3. **`___name___.php`** — a wildcard file, when the segment ends the path: any segment matches and is captured as `name`
4. **`___name___/`** — a wildcard directory, when more path follows
5. **`__other__.php`** — a catch-all declared at this level answers the current segment and everything after it; its tail arrives in `$this->remaining`
6. **404** — if none of the above match, return 404

A file and a directory of the same name coexist happily: `users.php` is the
collection, `users/` holds its members.

Finally, the located resource serves the request through the template method `handle()` (`before` → HTTP method → `after`), so **hooks run consistently** whether or not extra path segments matched.

> **Note on short-circuiting**: If the resource-level `before()` returns a `Response`, the request short-circuits — **neither the HTTP method nor the resource-level `after()` runs**. The global `after` hooks registered on `MiRest` still execute, because they wrap the entire dispatch.

Once located, the HTTP verb decides what runs: the handler the resource declares, or `405 Method Not Allowed` when it declares none — the 405 response always carries an `Allow` header listing the supported methods (RFC 9110). A request whose verb is not an HTTP method gets the same 405 and never calls a handler method, so the resource's own helpers can never be invoked as handlers; note though that the resource is still instantiated and its `before()` runs before the 405 is decided. The default `OPTIONS()` reports what the resource supports through an `Allow` header.

Matching lowercases the URL segment, so `/Users` and `/users` reach the same file — nothing else is rewritten, so `-` and `_` are *not* interchangeable and the URL has to match the file name (`/blog_posts` only reaches `blog_posts.php`). `.` / `..` segments are dropped, and a segment carrying a backslash or a NUL byte is refused outright, so the locator can never escape the resource root; `baseDir` must be an existing directory or an `InvalidArgumentException` is thrown.

**Wildcard vs exact on the same level**: an exact `foo.php` / `foo/` always takes priority over a wildcard. If both exist at the same depth, the exact match wins — the wildcard is never reached. This is intentional: explicit routes should not be shadowed by parameter captures.

**Route parameters keep their raw URL encoding**: values captured from a `___param___` file or directory are passed through as-is (e.g. `hello%20world` stays `hello%20world`). Use `urldecode($this->param('name'))` when you need the decoded value. This is intentional — keeping raw encoding provides an extra layer of protection against path-traversal variants like `%2e%2e`.

> **A file name is a class name**, so a segment that is a PHP keyword cannot be a resource: `/new` would need `class new {}`, which the parser rejects. Use the plural (`/news`, `/lists`, `/classes`) or another wording — the framework reports that parse error with the file name and the reason.

> **Keep `resources/` outside the document root.** The root resource is literally called `index.php`, which is also the web server's default document name; if the directory sits under the docroot, a request for it can be executed directly, bypassing the framework.

## Policy Hooks

Two hooks can refuse a request before a handler runs, and they see different things:

| | `MiRest::before()` | a resource's `before()` |
|---|---|---|
| When | before the locator runs, on every request | inside the matched resource, after its parameters are set and before the verb is checked |
| What it has | the `Request` | `$request`, `$this->params` and `static::class` |
| Endpoint identity | none — the literal path is all it gets | `static::class` names the endpoint: one key per endpoint, stable across parameter values |
| A path that matches nothing | still passes through it | never reaches it |

So `MiRest::before()` is the place for policy that does not depend on which endpoint was matched: a maintenance switch, a coarse "everything needs a session unless it is on this list", a bot filter, a shared security header. A decision that *is* per endpoint belongs in a resource-level `before()`, because that is the only one of the two that knows which endpoint it is — keying such a rule on `$request->path` means re-deriving the route the locator already resolved, and a prefix rule then grants the endpoint's children along with it.

```php
abstract class RestrictedResource extends AbstractResource
{
    protected function before(Request $request): ?Response
    {
        // static::class names the endpoint — ...\users\___user_id___ answers
        // /users/{id} while ...\users answers /users — and $this->params carries
        // the captured values. The literal path is on $request.
        $policy = $this->resolve('policy');

        if (!$policy->allows(static::class, $request->method, $this->params)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return null;
    }
}
```

Returning a `Response` short-circuits, and this hook runs before the verb is checked — a refusal therefore does not tell an unauthorised caller which methods the endpoint supports.

One caution: a base class is fail-open. A resource that forgets to extend it is silently unprotected, which is the reason to keep the coarse, endpoint-blind default in `MiRest::before()` and treat the per-endpoint layer as an addition rather than as the only line of defence.

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

![Version](https://img.shields.io/badge/version-2.2.0-blue)

极简 REST 框架，目录即路由。零魔法、零全局变量，核心代码不到 600 行。

> **从 2.1 升级？** 本次发布改了资源布局与类名规则：一个路径一个文件
> （`users.php`、`users/___user_id___.php`），不再是一个目录里放一个 `Index.php`；
> 资源文件的文件名现在就是它的类名。所有既有的 `resources/` 树都必须改名——
> 见[路由规则](#路由规则)。

> **背景**：miGears 源自自研 PHP 框架 **TinyGears**，因 TinyGears 这一名字
> 已被开源社区占用，故近期更名并开源发布。

## 特性

- **目录即路由** — 文件系统结构就是你的 API，无需配置路由表
- **轻量 Request/Response** — 自定义对象，比 PSR-7 更简洁直观
- **PSR-3 / PSR-4 / PSR-11 / PSR-12** — 遵循日志、自动加载、容器、编码规范
- **依赖极少** — 仅 `psr/container` 与 `psr/log`
- **代码不到 600 行** — 不计注释与空行，因此文档怎么长都不会让这句话失真；一口气读完整个框架
- **无全局变量、无进程级静态单例** — 完全可测试、可注入；容器是每实例的，条目只在那一个 `MiRest` 的生命周期内共享（即下文的懒加载单例）
- **内置容器，PSR-11** — 只注册需要配置的部分（PDO、Redis、Logger、DAO、Manager），其他直接 `new`
- **`before()` / `after()` 钩子** — 轻量级中间件替代方案
- **`___param___` 通配符段** — 捕获 URL 段作为命名参数

## 边界

**范围内**

- 目录即路由与分发：`ResourceLocator` 逐级遍历文件系统（`<segment>.php` / `___param___` / `__other__.php`），`MiRest::handle()` 负责接线全局 before/after 钩子、404 与异常处理器，以及 `before` → HTTP 动词 → `after` 模板方法。
- 轻量 `Request` / `Response` 对象（`Request::fromGlobals()`、`Response::json` / `html` / `redirect` / `empty`、`send()`），以及 `AbstractResource` 的 HTTP 动词处理器，包括默认 `OPTIONS` / `HEAD` 与带 `Allow` 头的 `405` 响应。
- `MiRest` 本身就是的 PSR-11 容器（`set()` / `has()` / `get()`，懒加载单例工厂），以及资源内的 `$this->resolve()` —— 它是把各兄弟模块接线到一起的组合根。

**范围外（刻意不做）**

- 持久化 / 数据访问 —— 不含数据库连接、SQL 构造或 DAO；`PDO` 只是被当作不透明的容器条目注册。由 `migears/dao` / `migears/sql` / `migears/mitable` 负责。
- 业务逻辑与编排 —— Manager、事件总线与领域模型来自 `migears/manager` / `migears/domain`；本包只调用它所分发的资源处理器。
- 渲染 / 模板 —— `Response::html()` 只承载一段 HTML 字符串；视图编译与布局渲染属于 `migears/pages` / `migears/template`。
- 日志、缓存、鉴权、邮件、图像与国际化 —— 各自都是兄弟模块（`migears/log` / `migears/cache` / `migears/security` / `migears/mail` / `migears/image` / `migears/i18n`）；本包只从容器解析 PSR-3 `LoggerInterface`，绝不实现其中任何一项。

## 安装

```bash
composer require migears/web
```

要求：PHP 8.1+，`psr/container`，`psr/log`。

## 快速开始

### 1. 创建资源目录

```
resources/
  index.php                  # GET /
  users.php                  # GET /users, POST /users
  users/
    ___user_id___.php        # GET /users/{user_id}, PUT /users/{user_id}, DELETE /users/{user_id}
    ___user_id___/
      posts.php              # GET /users/{user_id}/posts
  catchall/
    __other__.php            # 兜底匹配 /catchall/*
```

### 2. 编写资源类

类名就是文件名。下面的文件在 `resources/users/___user_id___.php`，
bootstrap 里用的是 `namespace: 'App\\Resources'`，所以类名是
`App\Resources\users\___user_id___` —— 各级目录名原样作为命名空间段，不做任何大小写转换。

```php
<?php
// resources/users/___user_id___.php

namespace App\Resources\users;

use MiGears\Web\AbstractResource;
use MiGears\Web\Request;
use MiGears\Web\Response;

class ___user_id___ extends AbstractResource
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
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new \Psr\Log\NullLogger()); // 必需；要静默就用 NullLogger
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
- **显式登记，不做自动装配** — 容器不做基于反射的自动装配、没有魔法解析；想用某个条目必须显式 `set()`。显式优于魔法：这让整个容器保持在 40 行以内，可一口气读懂。（本包唯一的反射用途在别处：探测资源声明了哪些 HTTP 动词，供 `OPTIONS` 默认实现与 `405` 的 `Allow` 头使用。）
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
后者是「没有资源匹配这个路径」（404），前者是「应用没装配对」，而且由于容器条目在分发之前解析，
这种失败会从 `handle()` 抛出，而不是被回应成一个 500 响应。

重新 `set()` 会替换工厂**并丢弃已缓存的实例**，下次 `get()` 会构建全新对象 —— 适合重新部署或测试场景。

### 框架自身的 logger

框架报告内部错误时——资源或全局 `after` 钩子抛异常——会通过从容器解析的 `LoggerInterface` 落日志。这里没有内置默认值：它在每次 `handle()` 开始时解析，因此未注册（或类型不对）的条目会在第一个请求上大声失败，而不是被隐式兜底吞掉。想要静默，就显式注册 `NullLogger`：

```php
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new \Psr\Log\NullLogger());
```

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

$rest->set(PDO::class,                    fn() => new PDO('mysql:host=localhost;dbname=app', 'user', 'pass'));
$rest->set(\Psr\Log\LoggerInterface::class, fn() => new Monolog\Logger('app'));
$rest->set(RedisCache::class,            fn() => new RedisCache(new Redis()));
```

**经验法则**：需要配置 → 用 `set()` 注册。不需要配置 → 直接 `new`。无论哪种方式，你的业务代码都不依赖容器的任何魔法。

> **关于 `namespace` 参数**：每个资源文件声明的类名就是文件名（`users.php` → `class users`）。如果 `namespace` 为空，所有资源类都在全局作用域，一旦有两个以上资源就会类名冲突，报 *"Cannot redeclare class"* 致命错误。任何有多个资源文件的项目都应设置 namespace。

## 路由规则

资源文件以它所响应的路径命名：`/users` 是 `users.php`，`/users/42` 是
`users/___user_id___.php`。全程不做大小写转换——文件名就是路径，类名就是文件名。

对于请求路径 `/foo/bar/baz`，定位器逐级下降。每一级以 `foo` 为当前段：

1. **`foo.php`** — 当 `foo` 是最后一段时，由该文件响应
2. **`foo/`** — 后面还有路径时，进入该目录，继续匹配 `bar/baz`
3. **`___name___.php`** — 通配文件，用于最后一段：任意段都匹配，并捕获为 `name`
4. **`___name___/`** — 通配目录，用于后面还有路径时
5. **`__other__.php`** — 声明在本层的兜底资源，响应当前段及其后的全部路径；其尾巴通过 `$this->remaining` 传入
6. **404** — 以上都不匹配，返回 404

同名的文件与目录可以并存：`users.php` 是集合本身，`users/` 装它的下级。

最终，定位到的资源统一通过模板方法 `handle()`（`before` → HTTP 方法 → `after`）处理请求，因此**无论是否有多余路径段，前置/后置钩子行为一致**。

> **短路说明**：资源级 `before()` 返回 `Response` 时请求短路——**HTTP 方法和资源级 `after()` 都不会执行**。但注册在 `MiRest` 上的全局 `after` 钩子仍会执行（它们包裹整个分发过程）。

定位到资源之后，由 HTTP 动词决定执行什么：资源声明了就执行对应处理器，没声明则返回 `405 Method Not Allowed`——405 响应始终携带列出支持方法的 `Allow` 头（RFC 9110）。不是 HTTP 动词的请求同样得到 405，且不会调用任何处理器方法，因此资源自己的辅助方法不可能被当成处理器调用；但要注意资源本身仍会被实例化、其 `before()` 仍会先运行，之后才判定为 405。默认的 `OPTIONS()` 会通过 `Allow` 头报告该资源支持哪些方法。

匹配时只把 URL 段转小写，因此 `/Users` 与 `/users` 会命中同一个文件——除此之外不做任何改写，所以 `-` 与 `_` **不**等价，URL 必须与文件名一致（`/blog_posts` 只能命中 `blog_posts.php`）。路径中的 `.` / `..` 段会被丢弃，含反斜杠或 NUL 字节的段则直接拒绝路由，定位器永远不会逃出资源根目录；`baseDir` 必须是已存在的目录，否则抛出 `InvalidArgumentException`。

**同级精确与通配的优先级**：精确的 `foo.php` / `foo/` 始终优先于通配。若同一深度两者都存在，精确匹配胜出——通配永远不会被命中。这是有意设计的：显式路由不应被参数捕获所遮蔽。

**路由参数保留原始 URL 编码**：从 `___param___` 文件或目录捕获的值按原样传入（例如 `hello%20world` 保持 `hello%20world`）。需要解码时用 `urldecode($this->param('name'))` 即可。这是有意设计的——保留原始编码为 `%2e%2e` 这类路径穿越变体提供了额外的防护层。

> **文件名就是类名**，所以 PHP 关键字不能做资源：`/new` 会需要 `class new {}`，解析器直接拒绝。改用复数（`/news`、`/lists`、`/classes`）或其他措辞——框架会把这类解析错误连同文件名和原因一起报出来。

> **`resources/` 要放在文档根之外。** 根资源就叫 `index.php`，它同时也是 Web 服务器的默认文档名；若该目录位于 docroot 之下，直接请求它就可能被执行，从而绕过框架。

## 策略钩子

有两个钩子能在处理器运行之前拒掉请求，而它们能看到的东西不同：

| | `MiRest::before()` | 资源级 `before()` |
|---|---|---|
| 时机 | 定位器之前，每个请求都会经过 | 已定位的资源内，参数已就位、动词尚未判定 |
| 手里有什么 | 只有 `Request` | `$request`、`$this->params` 与 `static::class` |
| 端点身份 | 没有——只有一个字面路径 | `static::class` 就是端点：一个端点一个键，不随参数取值变化 |
| 什么都没匹配上的路径 | 照样经过它 | 根本到不了 |

所以 `MiRest::before()` 适合与「命中哪个端点」无关的策略：维护开关、粗粒度的「除白名单外一律要求会话」、bot 拦截、统一安全响应头。**按端点**做的判定属于资源级 `before()`，因为两者中只有它知道自己是哪个端点——拿 `$request->path` 去写这种规则，等于把定位器已经解出的路由再推一遍，而前缀规则还会把该端点的子路径一并放行。

```php
abstract class RestrictedResource extends AbstractResource
{
    protected function before(Request $request): ?Response
    {
        // static::class 就是端点——...\users\___user_id___ 响应 /users/{id}，
        // ...\users 响应 /users；$this->params 里是捕获到的取值，
        // 字面路径在 $request 上。
        $policy = $this->resolve('policy');

        if (!$policy->allows(static::class, $request->method, $this->params)) {
            return Response::json(['error' => 'Forbidden'], 403);
        }

        return null;
    }
}
```

返回 `Response` 即短路，且该钩子在动词判定之前运行——因此拒绝不会告诉未授权者这个端点支持哪些方法。

一点提醒：基类是 fail-open 的。忘记继承的资源会静默失守，这正是要把粗粒度、看不见端点的默认策略留在 `MiRest::before()`、而把按端点的判定当作叠加层而非唯一防线的原因。

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
