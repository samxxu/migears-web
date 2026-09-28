# migears-web — Known Issues / 已知问题

> Summary of this module's issues. The items themselves are in [`issues/`](issues/README.md), one file
> per item: a front-matter header and a thread. This file is generated from them and can be rewritten at
> any time; edit an item, never this file.
>
> 本模块问题的概览。条目本体在 [`issues/`](issues/README.md)，一条目一文件：前置字段加讨论串。
> 本文件由条目生成，随时可以整段重写；请改条目，不要改本文件。
>
> From the miGears Full-Module Code Review Report (4th round, 2026-09-27).

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Size / 体量 | src 872 lines (467 net) · 109 tests · 7 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## At a glance / 状态一览

| | |
|---|---|
| Items / 条目 | P0 0 · P1 1 · P2 5 · P3 1 · other 1 |
| Answered / 已回复 | 2 of 8 |
| Waiting / 等待回复 | `P1-1`, `P2-1`, `P2-2`, `P2-3`, `P2-4`, `P3-1` |

| id | level | status | title |
|---|---|---|---|
| [`P1-1`](issues/P1-1.md) | P1 | **open** | A logger registered in the container is still ignored by the framework: … |
| [`P2-1`](issues/P2-1.md) | P2 | **open** | A 405 response carries no `Allow` header, although … |
| [`P2-2`](issues/P2-2.md) | P2 | **open** | `class_exists($className, false)` is treated as proof the target file … |
| [`P2-3`](issues/P2-3.md) | P2 | **open** | A container factory returning `null` loses singleton semantics: … |
| [`P2-4`](issues/P2-4.md) | P2 | **open** | Route parameters are not URL-decoded: `locate('/users/42%20x')` yields … |
| [`P2-5`](issues/P2-5.md) | P2 | **rejected** | `Response::send()` validates nothing: no `headers_sent()` check, no … |
| [`P3-1`](issues/P3-1.md) | P3 | **open** | The README describes the lifecycle as 'before → HTTP method → after' … |
| [`G2`](issues/G2.md) | - | **fixed** | Strict flags: `phpunit.xml.dist` currently sets none of the five. The … |

## Verdict / 结论

Both P0s are fixed cleanly. The rest of the list is untouched, and this round reproduced all four P2s with probes — including one that instantiates the wrong class file when an exact and a wildcard directory produce the same FQCN.

两个 P0 都修得干净。清单其余部分未动，本轮用探针把四条 P2 全部实证——其中一条会在精确目录与通配目录产生同一 FQCN 时实例化错误的类文件。

## Fixed since the last round / 本轮已修复确认

上一轮两个 P0 均已修复且有测试固化：新增 HTTP_METHODS 白名单并在 handle() 内做白名单 + method_exists 双检（HANDLE/RESOLVE/PARAM/BEFORE 等一律 405）；dispatch 与 runAfter 拆成两个独立 try，after 只运行一次、异常被包成 500。P1-1 已修：README 资源示例补上 namespace 并解释通配目录贡献的命名空间段。 

## Test gaps / 测试盲区

No test for `Allow` on 405, `Response::send()` at all, URL-encoded paths, a null-returning container factory, or the exact-vs-wildcard FQCN collision; the container-registered logger path is never exercised.

无「405 是否带 Allow」用例、Response::send() 完全无用例、无 URL 编码路径用例、无「容器工厂返回 null」用例、无「精确目录与通配目录 FQCN 冲突」用例；容器注册的 logger 通路从未被覆盖。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: all four on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。
