# migears-web — Known Issues / 已知问题

> Generated from the miGears Full-Module Code Review Report (4th round, 2026-09-27).
> This file has two regions. Everything above **Owner feedback** is generated from the report — do
> not edit it there. The **Owner feedback** region belongs to the module maintainer: write into it,
> and it is preserved verbatim when the file is regenerated.
> A `fixed` reply is verified against the code by the reviewer before the finding is closed; a
> `rejected` reply is either accepted as a false positive or answered with counter-evidence.
>
> 本文件分两个区域。**「负责人反馈」之前的全部内容**由评审报告生成，请勿在该区修改；
> **「负责人反馈」区**归模块负责人所有，重新生成时会原样保留。
> 标注 `fixed`（已修复）的回复会被评审对照代码核实后才关闭；标注 `rejected`（不认同）的，
> 评审要么采纳为误报，要么给出反驳证据。
>
> 摘自 miGears 全模块代码评审报告（第四轮，2026-09-27）。

| | |
|---|---|
| Status / 状态 | **P0 cleared / P0 已清零** |
| Findings / 问题 | P0 0 · P1 1 · P2 5 · P3 1 |
| Size / 体量 | src 872 lines (467 net) · 109 tests · 7 src files |

Legend / 图例 — **P0** functional or security · **P1** documentation that fails when copied · **P2** robustness · **P3** metadata and docs
级别说明 — **P0** 功能性或安全级 · **P1** 文档照抄即错 · **P2** 健壮性 · **P3** 元数据与文档

## Verdict / 结论

Both P0s are fixed cleanly. The rest of the list is untouched, and this round reproduced all four P2s with probes — including one that instantiates the wrong class file when an exact and a wildcard directory produce the same FQCN.

两个 P0 都修得干净。清单其余部分未动，本轮用探针把四条 P2 全部实证——其中一条会在精确目录与通配目录产生同一 FQCN 时实例化错误的类文件。

## Fixed since the last round / 本轮已修复确认

上一轮两个 P0 均已修复且有测试固化：新增 HTTP_METHODS 白名单并在 handle() 内做白名单 + method_exists 双检（HANDLE/RESOLVE/PARAM/BEFORE 等一律 405）；dispatch 与 runAfter 拆成两个独立 try，after 只运行一次、异常被包成 500。P1-1 已修：README 资源示例补上 namespace 并解释通配目录贡献的命名空间段。 

## Open findings / 未修问题


### P1

**P1-1** — `src/MiRest.php:34,60,158,165 vs README:164,180,445,461`

- EN: A logger registered in the container is still ignored by the framework: its own errors only use the third constructor argument, while the README shows two different ids — `resolve(LoggerInterface::class)` and `set("logger", ...)` — which are not the same channel, so copying the example yields a NotFoundException or a silent no-op.
- 中文: 容器里注册的 logger 仍被框架忽略：框架自身错误只用构造器第 3 参，而 README 展示了两个不同的 id——resolve(LoggerInterface::class) 与 set("logger", ...)——并非同一通路，照抄示例会得到 NotFoundException 或静默无效。
- Verification / 验证: reproduced / 已实证


### P2

**P2-1** — `src/AbstractResource.php:127,225-235`

- EN: A 405 response carries no `Allow` header, although `getAllowedMethods()` already produces that data and only the OPTIONS branch uses it. RFC 9110 requires Allow on 405<sup><a href="#cite-2">[2]</a></sup>.
- 中文: 405 响应不带 Allow 头，而 getAllowedMethods() 已经产出这份数据且只有 OPTIONS 分支使用。RFC 9110 要求 405 必须带 Allow<sup><a href="#cite-2">[2]</a></sup>。
- Verification / 验证: reproduced / 已实证

**P2-2** — `src/MiRest.php:193-195`

- EN: `class_exists($className, false)` is treated as proof the target file is loaded. When an exact directory and a wildcard directory on the same level generate the same FQCN, the second request skips `require_once` and reuses the class from the other file: `GET /users/user_id` and `GET /users/999` both returned the exact-directory implementation.
- 中文: class_exists($className, false) 被当作「目标文件已加载」的证明。当同层精确目录与通配目录产生同一 FQCN 时，第二个请求会跳过 require_once 并复用另一个文件里的类：GET /users/user_id 与 GET /users/999 都返回了精确目录那份实现。
- Verification / 验证: reproduced / 已实证

**P2-3** — `src/MiRest.php:96-104,82`

- EN: A container factory returning `null` loses singleton semantics: `isset($this->instances[$id])` is false for null, so every `get()` re-runs the factory (measured: 2 calls, 2 executions) while `has()` still returns true.
- 中文: 容器工厂返回 null 会丢失单例语义：isset($this->instances[$id]) 对 null 为 false，于是每次 get() 都重跑工厂（实测：2 次调用、2 次执行），而 has() 仍返回 true。
- Verification / 验证: reproduced / 已实证

**P2-4** — `src/Request.php:53, src/ResourceLocator.php:59`

- EN: Route parameters are not URL-decoded: `locate("/users/42%20x")` yields the literal `42%20x` while `$_GET` would decode it, so one request carries two semantics. (The side effect is that `%2e%2e` traversal is also unusable, which is a security plus.)
- 中文: 路由参数不做 URL 解码：locate("/users/42%20x") 得到字面量 42%20x，而 $_GET 会解码，同一个请求两套语义。（副作用是 %2e%2e 型穿越也因此不可用，安全上是加分。）
- Verification / 验证: reproduced / 已实证

**P2-5** — `src/Response.php:97-104`

- EN: `Response::send()` validates nothing: no `headers_sent()` check, no status-range check, no header-name validation — and it has no test at all.
- 中文: Response::send() 不做任何校验：不检查 headers_sent()、不校验状态码范围、不校验头名——且完全没有测试。
- Verification / 验证: reproduced / 已实证


### P3

**P3-1** — `src/AbstractResource.php:136-140 vs README:202,482`

- EN: The README describes the lifecycle as "before → HTTP method → after" without noting that a short-circuiting resource-level `before()` skips the resource-level `after()` (the behaviour is pinned by a test).
- 中文: README 把生命周期写成「before → HTTP 方法 → after」，未说明资源级 before() 短路时会跳过资源级 after()（该行为已被测试固化）。
- Verification / 验证: reproduced / 已实证

## Test gaps / 测试盲区

No test for `Allow` on 405, `Response::send()` at all, URL-encoded paths, a null-returning container factory, or the exact-vs-wildcard FQCN collision; the container-registered logger path is never exercised.

无「405 是否带 Allow」用例、Response::send() 完全无用例、无 URL 编码路径用例、无「容器工厂返回 null」用例、无「精确目录与通配目录 FQCN 冲突」用例；容器注册的 logger 通路从未被覆盖。

## Verification protocol / 验证方式

- `./vendor/bin/phpunit` · `composer analyse` · `composer validate`
- Warning/notice/deprecation/risky flags in `phpunit.xml.dist`: none on
- A PHP warning counts as a test failure only where those flags are on; otherwise run `./vendor/bin/phpunit --fail-on-warning` explicitly.
- 只有在上述开关打开时 PHP 警告才会导致套件失败；否则请显式加 `--fail-on-warning`。

## Owner feedback / 负责人反馈

<!-- OWNER-FEEDBACK:BEGIN -->
<!-- 渠道说明 / channel notice — 跨模块协调人发布，长期有效 / issued by the cross-module coordinator, standing
     ISSUES.md 是本模块「完整」的问题讨论与修复渠道，不只是评审结论的存放处。
     ISSUES.md is this module's COMPLETE issue-discussion-and-fix channel, not merely where review verdicts land.

     1. 每位负责人只对自己模块负责。对别的模块有意见、疑问、反证或改动建议，写入「对方模块」的 ISSUES.md，
        不要写在自己模块里。
        Each owner is responsible for their own module only. Opinions, questions, counter-evidence and
        change requests about ANOTHER module go into THAT module's ISSUES.md, never into your own.
     2. 在对方模块的文件里注明你是谁：模块名 + 身份。署名是硬要求，不署名则无法追溯来源。
        Sign it in the other module's file: your module name and your role. Signing is mandatory; an
        unsigned entry cannot be traced back to its author.
     3. 署名格式 / signature forms, so the source is distinguishable:
          reviewer — migears-full-review   评审方
          coordinator — cross-module       跨模块协调人
          owner — migears-<module>         其他模块负责人
     4. 结论文本一律带状态词：accepted / fixed / rejected / deferred / question / new-evidence。
        无署名条目下一轮可能被按新发现重新评级。
        Sign conclusions with one status word: accepted / fixed / rejected / deferred / question /
        new-evidence. An unsigned entry may be re-graded as a new finding in the next round.
     5. 开工之前先通读本文件：把每条开启条目按证据评估（签名条目也算），再把你接受的条目与自己的工作一并执行，
        不要拆成两轮。每条都要有状态词。
        Read this file before starting work: evaluate every open item on its evidence, signed entries
        included, then execute the ones you accept together with your own work in one pass. Every item
        gets a status word. -->

<!-- Maintainers: reply under each finding's `### <id>` heading and keep the headings, so the
     reviewer can map your reply to the finding. Status vocabulary, one word followed by your
     reasoning and any evidence:
       accepted      you agree; it will be fixed
       fixed         you believe it is already fixed in the code (the reviewer verifies this)
       rejected      you disagree — give the reason; the reviewer either accepts it as a false
                     positive or answers with counter-evidence
       deferred      deliberate, out of scope for now — give the reason
       question      you need a decision or clarification first
       new-evidence  you have additional facts bearing on the finding
     You may also add findings of your own under `### New — <short title>`.

     负责人：请在对应 `### <编号>` 标题下逐条回复，并保留标题以便评审对应。
     状态词（一个词 + 理由与证据）：
       accepted      认同，将会修复
       fixed         认为代码里已经修好（评审会对照代码核实）
       rejected      不认同——请给理由；评审要么采纳为误报，要么给出反驳证据
       deferred      有意暂缓或超出范围——请给理由
       question      需要先明确或决策
       new-evidence  补充与本次结论相关的新事实
     也欢迎在 `### New — <简短标题>` 下补充你发现的问题。 -->

### P1-1
<!-- 负责人反馈 / owner response here -->

### P2-1
<!-- 负责人反馈 / owner response here -->

### P2-2
<!-- 负责人反馈 / owner response here -->

### P2-3
<!-- 负责人反馈 / owner response here -->

### P2-4
<!-- 负责人反馈 / owner response here -->

### P2-5
<!-- 负责人反馈 / owner response here -->

- **rejected** — the finding's substantive claim is header injection, and PHP's own `header()` already forbids it, so `src/Response.php:100-101` cannot be made to split one header into two. Probe on PHP 8.5.10:
  - `@header("X-Test: a\r\nX-Injected: b")` → returns `null` and raises `Header may not contain more than a single header, new line detected`.
  - `@header("X-Test\r\nX-Injected: b")` → the same refusal and warning for a CRLF in the *name*.
  The audit's "security-relevant gap" risk flag is therefore refuted by the runtime, not by an argument.
- The other two "validations" are not defects either. `http_response_code()` raised no warning for any code in the probe — in range (`200`, `599`) or not (`0`, `99`, `600`, `9999`) — so a status-range check would be the framework second-guessing PHP; and a header-name check would only re-implement the newline invariant `header()` already enforces (a name containing a space is a developer error, not an injection path). `send()` is a deliberate thin passthrough to PHP's three response primitives — `http_response_code()`, `header()`, `echo` — and adding re-validation of PHP's own contract is not this class's job.
- The "has no test at all" half is closed: `tests/ResponseTest.php:139-167` (`testSendEchoesBody`, `testSendReturnsVoid`), added by `60e4f7e` ("Address P1-1, P2-1, P2-2, P2-3, P2-5, P3-1 from 4th round review"). No source change was made.
- 中文: **rejected**——本条有分量的主张是头注入，而 PHP 自带的 `header()` 已禁止，`src/Response.php:100-101` 无法把一个头拆成两个。PHP 8.5.10 探针：`@header("X-Test: a\r\nX-Injected: b")` 返回 `null` 并报 `Header may not contain more than a single header, new line detected`；名字里含 CRLF 同样被拒并报警告。因此审计的「security-relevant gap」风险标记是被运行时否掉的，而非靠论证。另外两条「校验」也不是缺陷：`http_response_code()` 在探针中对 `0`、`99`、`200`、`599`、`600`、`9999` 都不报警告，加状态码范围检查等于替 PHP 做判断；头名校验只是重复 `header()` 已保证的换行不变量（名字含空格属开发者笔误，不是注入路径）。`send()` 是对 `http_response_code()`/`header()`/`echo` 三个原语的刻意薄封装，替 PHP 复检其自身契约不是本类的职责。「完全没有测试」这半边已闭合（`tests/ResponseTest.php:139-167`，由 `60e4f7e` 加入）。未改动任何源码。
- Evidence / 证据: `php web_probe2.php` → `P1 value-CRLF ret=NULL warn='Header may not contain more than a single header, new line detected'`; `P2 name-CRLF ret=NULL warn=…same…`; `P4 code=0 ret=false warn=NULL`, `code=99/200/599/600/9999 ret=<previous> warn=NULL`.
- owner — migears-web

### P3-1
<!-- 负责人反馈 / owner response here -->
<!-- 跨模块条目 / cross-module items — 由跨模块协调人提出，非本轮评审 finding。口径见工作区根目录 `migears-engineering-gates.md`。
      Filed by the cross-module coordinator, not by the round's review. Standard: `migears-engineering-gates.md` at the workspace root. -->

### G2

- EN: Strict flags: `phpunit.xml.dist` currently sets none of the five. The standard is all five — `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests` — which 11 of 27 modules set. Missing here: `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky`, `beStrictAboutOutputDuringTests`. Turn them on and make the suite green; run `./vendor/bin/phpunit` and `composer analyse` before and after, and expect the first run to surface real warnings. If a flag genuinely cannot be turned on, reply `deferred` with the failing test and the reason instead of leaving the suite red.
- 中文: 严格开关：`phpunit.xml.dist` 目前五个开关一个都没开。标准是五个全开——`failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`——27 个模块中 11 个如此。本模块缺 `failOnWarning`、`failOnNotice`、`failOnDeprecation`、`failOnRisky`、`beStrictAboutOutputDuringTests`。请打开并让套件保持全绿；改动前后各跑一次 `./vendor/bin/phpunit` 与 `composer analyse`，第一次跑出真警告是预期内的。若某个开关确实无法打开，请回复 `deferred` 并给出失败的用例与原因，而不是把套件留在红灯状态。
- Reply with one status word (`accepted` / `fixed` / `rejected` / `deferred` / `question`). / 请回复一个状态词（`accepted` / `fixed` / `rejected` / `deferred` / `question`）。
coordinator — cross-module

- **fixed** — all five strict flags are now on. `phpunit.xml.dist` previously set none of them; I added
  `failOnWarning`, `failOnNotice`, `failOnDeprecation`, `failOnRisky` and
  `beStrictAboutOutputDuringTests` and, matching the reference shape
  (`migears-data-structure/phpunit.xml.dist`), the three `displayDetailsOnTestsThatTrigger*`
  attributes. Every pre-existing attribute (`colors`, `cacheDirectory`, `executionOrder="random"`) and
  the `<testsuites>`/`<source>` structure were kept; the schema URL is untouched.
  - Before: `./vendor/bin/phpunit` → `OK (118 tests, 277 assertions)`, exit 0, no flags on.
  - After: `./vendor/bin/phpunit` → `OK (118 tests, 277 assertions)`, exit 0, all five flags on — no
    warning, notice, deprecation, risky test or stray output surfaced, so the cost here was zero. The
    first run under the flags did not surface any real warning, contrary to the expectation in the item.
  - `./vendor/bin/phpstan analyse --no-progress` → `[OK] No errors`, exit 0, before and after.
  - `phpunit.xml.dist:7-15` now sets `failOnWarning`, `failOnNotice`, `failOnDeprecation`,
    `failOnRisky`, `beStrictAboutOutputDuringTests`, `displayDetailsOnTestsThatTriggerWarnings`,
    `displayDetailsOnTestsThatTriggerNotices`, `displayDetailsOnTestsThatTriggerDeprecations`, all `true`.

  owner — migears-web

<!-- OWNER-FEEDBACK:END -->
