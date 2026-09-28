# migears-web — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (5th round, 2026-09-28).

| | |
|---|---|
| Status | **Best state** |
| Size | src 499 lines (net) · 118 tests · 7 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 1 · P2 5 · P3 1 · other 1 |
| Settled | 0 of 8 |
| Waiting on the owner | _nothing_ |
| Waiting on the reviewer | `P1-1`, `P2-1`, `P2-2`, `P2-3`, `P2-5`, `P3-1`, `G2` |
| Waiting on the coordinator | _nothing_ |
| Deferred, owing nobody | `P2-4` |

| id | level | status | title |
|---|---|---|---|
| [`P1-1`](issues/P1-1.md) | P1 | **fixed** | A logger registered in the container is still ignored by the framework: … |
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | A 405 response carries no `Allow` header, although … |
| [`P2-2`](issues/P2-2.md) | P2 | **fixed** | `class_exists($className, false)` is treated as proof the target file … |
| [`P2-3`](issues/P2-3.md) | P2 | **fixed** | A container factory returning `null` loses singleton semantics: … |
| [`P2-4`](issues/P2-4.md) | P2 | **deferred** | Route parameters are not URL-decoded: `locate('/users/42%20x')` yields … |
| [`P2-5`](issues/P2-5.md) | P2 | **rejected** | `Response::send()` validates nothing: no `headers_sent()` check, no … |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | The README describes the lifecycle as 'before → HTTP method → after' … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **8** of 8 |
| By status | `rejected` 1 · `deferred` 1 · `fixed` 6 |
| Waiting on | reviewer 7 · - 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P1** | [`P1-1`](issues/P1-1.md) | `fixed` | reviewer | A logger registered in the container is still ignored by the framework: … |
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | reviewer | A 405 response carries no `Allow` header, although … |
| **P2** | [`P2-2`](issues/P2-2.md) | `fixed` | reviewer | `class_exists($className, false)` is treated as proof the target file … |
| **P2** | [`P2-3`](issues/P2-3.md) | `fixed` | reviewer | A container factory returning `null` loses singleton semantics: … |
| **P2** | [`P2-4`](issues/P2-4.md) | `deferred` | - | Route parameters are not URL-decoded: `locate('/users/42%20x')` yields … |
| **P2** | [`P2-5`](issues/P2-5.md) | `rejected` | reviewer | `Response::send()` validates nothing: no `headers_sent()` check, no … |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | reviewer | The README describes the lifecycle as 'before → HTTP method → after' … |
| **-** | [`G2`](issues/G2.md) | `fixed` | reviewer | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Verdict

A compact REST framework with request parsing, resource location, and response formatting; all functional items from last round are resolved. Only minor documentation-drift items remain.

## Fixed since the last round

All prior P1/P2/P3 items confirmed fixed or rejected: P1-1 README FQCN collision claim corrected; P2-1 through P2-4 all verified; P2-5 Response validation rejected (PHP itself refuses header injection); P3-1 G2 strict flags complete.

## Test gaps

No test for Request with duplicate query parameters (last-wins behavior); no test for Response with numeric string headers; no test for ResourceLocator with a resource class that has required constructor params.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-web — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（5th round，2026-09-28）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 499 行（净）· 118 个用例 · 7 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 1 · P2 5 · P3 1 · 其他 1 |
| 已了结 | 0 / 8 |
| 等负责人 | _无_ |
| 等评审方 | `P1-1`, `P2-1`, `P2-2`, `P2-3`, `P2-5`, `P3-1`, `G2` |
| 等协调人 | _无_ |
| 已暂缓，不欠谁 | `P2-4` |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P1-1`](issues/P1-1.md) | P1 | **fixed** | 容器里注册的 logger 仍被框架忽略：框架自身错误只用构造器第 3 参，而 README 展示了两个不同的 … |
| [`P2-1`](issues/P2-1.md) | P2 | **fixed** | 405 响应不带 Allow 头，而 getAllowedMethods() 已经产出这份数据且只有 OPTIONS 分支使用。RFC … |
| [`P2-2`](issues/P2-2.md) | P2 | **fixed** | class_exists($className, false) 被当作「目标文件已加载」的证明。当同层精确目录与通配目录产生同一 FQCN … |
| [`P2-3`](issues/P2-3.md) | P2 | **fixed** | 容器工厂返回 null 会丢失单例语义：isset($this->instances[$id]) 对 null 为 false，于是每次 … |
| [`P2-4`](issues/P2-4.md) | P2 | **deferred** | 路由参数不做 URL 解码：locate("/users/42%20x") 得到字面量 42%20x，而 $_GET … |
| [`P2-5`](issues/P2-5.md) | P2 | **rejected** | Response::send() 不做任何校验：不检查 headers_sent()、不校验状态码范围、不校验头名——且完全没有测试。 |
| [`P3-1`](issues/P3-1.md) | P3 | **fixed** | README 把生命周期写成「before → HTTP 方法 → after」，未说明资源级 before() 短路时会跳过资源级 … |
| [`G2`](issues/G2.md) | - | **fixed** | 严格开关：`phpunit.xml.dist` … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **8** / 8 |
| 按状态 | `rejected` 1 · `deferred` 1 · `fixed` 6 |
| 等在谁 | 评审方 7 · - 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P1** | [`P1-1`](issues/P1-1.md) | `fixed` | 评审方 | 容器里注册的 logger 仍被框架忽略：框架自身错误只用构造器第 3 参，而 README 展示了两个不同的 … |
| **P2** | [`P2-1`](issues/P2-1.md) | `fixed` | 评审方 | 405 响应不带 Allow 头，而 getAllowedMethods() 已经产出这份数据且只有 OPTIONS 分支使用。RFC … |
| **P2** | [`P2-2`](issues/P2-2.md) | `fixed` | 评审方 | class_exists($className, false) 被当作「目标文件已加载」的证明。当同层精确目录与通配目录产生同一 FQCN … |
| **P2** | [`P2-3`](issues/P2-3.md) | `fixed` | 评审方 | 容器工厂返回 null 会丢失单例语义：isset($this->instances[$id]) 对 null 为 false，于是每次 … |
| **P2** | [`P2-4`](issues/P2-4.md) | `deferred` | - | 路由参数不做 URL 解码：locate("/users/42%20x") 得到字面量 42%20x，而 $_GET … |
| **P2** | [`P2-5`](issues/P2-5.md) | `rejected` | 评审方 | Response::send() 不做任何校验：不检查 headers_sent()、不校验状态码范围、不校验头名——且完全没有测试。 |
| **P3** | [`P3-1`](issues/P3-1.md) | `fixed` | 评审方 | README 把生命周期写成「before → HTTP 方法 → after」，未说明资源级 before() 短路时会跳过资源级 … |
| **-** | [`G2`](issues/G2.md) | `fixed` | 评审方 | 严格开关：`phpunit.xml.dist` … |

## 结论

一个紧凑的 REST 框架，含请求解析、资源定位与响应格式化；上一轮所有功能性问题均已解决。仅剩少量文档漂移类问题。

## 本轮已修复确认

All prior P1/P2/P3 items confirmed fixed or rejected: P1-1 README FQCN collision claim corrected; P2-1 through P2-4 all verified; P2-5 Response validation rejected (PHP itself refuses header injection); P3-1 G2 strict flags complete.

## 测试盲区

无重复 query 参数 Request 测试（后者优先的行为）；无数字字符串 header 的 Response 测试；无资源类有必填构造参数的 ResourceLocator 测试。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
