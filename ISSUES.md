# migears-web — Known Issues

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> From the miGears Full-Module Code Review Report (6th round, 2026-10-01).

| | |
|---|---|
| Status | **Best state** |
| Size | src 491 lines (net) · 120 tests · 7 src files |

Legend — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs

## At a glance

| | |
|---|---|
| Unsettled | P0 0 · P1 0 · P2 2 · P3 1 · other 0 |
| Settled | 7 of 10 |
| Waiting on the owner | _nothing_ |
| Waiting on the coordinator | _nothing_ |
| Waiting on the reviewer | `P2-5`, `P3-2` |
| Deferred, owing nobody | `P2-4` |

| id | level | status | title |
|---|---|---|---|
| [`P1-1`](issues/P1-1.md) | P1 | **verified** | A logger registered in the container is still ignored by the framework: … |
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | A 405 response carries no `Allow` header, although … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | `class_exists($className, false)` is treated as proof the target file … |
| [`P2-3`](issues/P2-3.md) | P2 | **verified** | A container factory returning `null` loses singleton semantics: … |
| [`P2-4`](issues/P2-4.md) | P2 | **deferred** | Route parameters are not URL-decoded: `locate('/users/42%20x')` yields … |
| [`P2-5`](issues/P2-5.md) | P2 | **rejected** | `Response::send()` validates nothing: no `headers_sent()` check, no … |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | The README describes the lifecycle as 'before → HTTP method → after' … |
| [`P3-2`](issues/P3-2.md) | P3 | **rejected** | README code-size claim is slightly off — states '~700 lines of source' … |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | MiRest::createResource() relies on autoload to find resource classes — … |
| [`G2`](issues/G2.md) | - | **verified** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Unclosed

What is left to do here: every item whose `status` is not `verified` or `closed`,
highest severity first. `waiting on` is the party who acts next, read from that status.

| | |
|---|---|
| Unclosed | **3** of 10 |
| By status | `rejected` 2 · `deferred` 1 |
| Waiting on | reviewer 2 · - 1 |

| level | item | status | waiting on | title |
|---|---|---|---|---|
| **P2** | [`P2-4`](issues/P2-4.md) | `deferred` | - | Route parameters are not URL-decoded: `locate('/users/42%20x')` yields … |
| **P2** | [`P2-5`](issues/P2-5.md) | `rejected` | reviewer | `Response::send()` validates nothing: no `headers_sent()` check, no … |
| **P3** | [`P3-2`](issues/P3-2.md) | `rejected` | reviewer | README code-size claim is slightly off — states '~700 lines of source' … |

## Verdict

The autoloader leak is closed and load-bearing, and the module is otherwise clean; the one open item is the deliberately deferred raw-encoding trade-off.

## Fixed since the last round

P3-3 verified by mutation: a located file must now declare the class itself and createResource() asks the autoloader nothing, so a class whose file cannot be loaded fails where the class was named instead of resolving silently. The other eight items are consistent.

## Test gaps

No significant gap; the raw-encoding versus %2e%2e trade-off is a recorded deferral rather than an untested path.

## Verification protocol

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.


---

# migears-web — 已知问题

> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> 出自 miGears 全模块代码评审报告（6th round，2026-10-01）。

| | |
|---|---|
| 状态 | **状态最好** |
| 体量 | src 491 行（净）· 120 个用例 · 7 个源文件 |

级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## 状态一览

| | |
|---|---|
| 未了结 | P0 0 · P1 0 · P2 2 · P3 1 · 其他 0 |
| 已了结 | 7 / 10 |
| 等模块主 | _无_ |
| 等协调人 | _无_ |
| 等评审方 | `P2-5`, `P3-2` |
| 已暂缓，不欠谁 | `P2-4` |

| id | 级别 | 状态 | 标题 |
|---|---|---|---|
| [`P1-1`](issues/P1-1.md) | P1 | **verified** | 容器里注册的 logger 仍被框架忽略：框架自身错误只用构造器第 3 参，而 README 展示了两个不同的 … |
| [`P2-1`](issues/P2-1.md) | P2 | **verified** | 405 响应不带 Allow 头，而 getAllowedMethods() 已经产出这份数据且只有 OPTIONS 分支使用。RFC … |
| [`P2-2`](issues/P2-2.md) | P2 | **verified** | class_exists($className, false) 被当作「目标文件已加载」的证明。当同层精确目录与通配目录产生同一 FQCN … |
| [`P2-3`](issues/P2-3.md) | P2 | **verified** | 容器工厂返回 null 会丢失单例语义：isset($this->instances[$id]) 对 null 为 false，于是每次 … |
| [`P2-4`](issues/P2-4.md) | P2 | **deferred** | 路由参数不做 URL 解码：locate("/users/42%20x") 得到字面量 42%20x，而 $_GET … |
| [`P2-5`](issues/P2-5.md) | P2 | **rejected** | Response::send() 不做任何校验：不检查 headers_sent()、不校验状态码范围、不校验头名——且完全没有测试。 |
| [`P3-1`](issues/P3-1.md) | P3 | **verified** | README 把生命周期写成「before → HTTP 方法 → after」，未说明资源级 before() 短路时会跳过资源级 … |
| [`P3-2`](issues/P3-2.md) | P3 | **rejected** | README 代码量声称略有偏差——称「约 700 行源码」，但实测 7 个文件净代码约 499 行。轻微漂移。 |
| [`P3-3`](issues/P3-3.md) | P3 | **verified** | MiRest::createResource() 依赖自动加载来找到资源类——如果类名匹配的文件尚未加载且自动加载静默失败，行为将不可预测。 |
| [`G2`](issues/G2.md) | - | **verified** | 严格开关：`phpunit.xml.dist` … |

## 未关闭

本模块还剩什么要做：所有 `status` 不是 `verified` 或 `closed` 的条目，按严重度从高到低。
`waiting on` 是下一步该动手的一方，由其状态读出。

| | |
|---|---|
| 未关闭 | **3** / 10 |
| 按状态 | `rejected` 2 · `deferred` 1 |
| 等在谁 | 评审方 2 · - 1 |

| 级别 | 条目 | 状态 | 等在谁 | 标题 |
|---|---|---|---|---|
| **P2** | [`P2-4`](issues/P2-4.md) | `deferred` | - | 路由参数不做 URL 解码：locate("/users/42%20x") 得到字面量 42%20x，而 $_GET … |
| **P2** | [`P2-5`](issues/P2-5.md) | `rejected` | 评审方 | Response::send() 不做任何校验：不检查 headers_sent()、不校验状态码范围、不校验头名——且完全没有测试。 |
| **P3** | [`P3-2`](issues/P3-2.md) | `rejected` | 评审方 | README 代码量声称略有偏差——称「约 700 行源码」，但实测 7 个文件净代码约 499 行。轻微漂移。 |

## 结论

自动加载泄漏已封住且承重，模块整体干净；唯一的未决项是被刻意暂缓的「保留原始编码」取舍。

## 本轮已修复确认

P3-3 verified by mutation: a located file must now declare the class itself and createResource() asks the autoloader nothing, so a class whose file cannot be loaded fails where the class was named instead of resolving silently. The other eight items are consistent.

## 测试盲区

无显著盲区；「保留原始编码 vs %2e%2e 防护」是有记录的暂缓，而非未测路径。

## 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- `phpunit.xml.dist` 中的 warning/notice/deprecation/risky 开关：四个全开
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
