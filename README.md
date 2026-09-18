# WP AI Chat Lab

> 从 WordPress 网站知识出发，研究并逐步构建一套可控、可靠、低成本、可扩展的 AI Chat 架构。

**当前稳定 Release：v0.9.0 · Lead Capture & Inquiry Management**  
**状态：Final Review Passed / Stable Release**

> **测试与上线提醒**：v0.9.0 的真实开发/验收使用 COODEC 官网（https://www.coodecglobal.com/）公开内容及对应 WordPress 站点数据作为真实站点样本。README 中的型号、查询词、评分、Token、限额与计数均属于当时测试快照，不能直接当作其他站点或正式生产环境的固定值。正式上线前请务必阅读下文 **「v0.9.0 测试数据与正式上线注意事项」**。

## v0.5.0 稳定版本状态

`v0.5.0 — Local Retrieval` 已完成 Stage 1 Query & Candidate Foundation、Stage 2 Weighted Scoring 与 Stage 3 Retrieval Quality Calibration 的真实环境验收。

当前链路：

```text
Question
→ Query Normalize
→ Candidate Recall
→ Weighted Scoring
→ Stable Ranking
→ Strength Evaluation
→ Top-K
→ Score Breakdown
```

Stage 2 第一轮评分基线：

```text
Structured Data  8
Title            6
Taxonomies       4
Excerpt          3
Content          1

Exact Identifier Boost  +4
Phrase Match Boost      +8
Coverage Bonus Max     +10
```

Playground 现在支持 Candidate Limit + Top K，并显示 Rank / Score / Score Breakdown。Stage 3 已在此基础上加入诊断级 Retrieval Strength 与 Threshold Calibration。

Stage 3 第一轮诊断基线：

```text
Minimum Score  12
Medium Score   18
Strong Score   25
Medium Coverage 50%
Strong Coverage 75%
Medium Gap      3
Strong Gap      6
```

Strength 同时观察 Top Score、Top Coverage 与 Top/Second Score Gap；弱匹配仍保留候选供调试，不会隐藏结果。当前阈值通过 `wpaic_retrieval_strength_thresholds` Filter 可调整。


完整设计、实现与验收记录见：

```text
docs/versions/v0.5.0.md
```

当前状态：**Final Review Passed / Stable Release**。Stage 1、Stage 2、Stage 3 均已完成并通过真实环境验证；诊断级 Strong / Medium / Weak / None、Minimum Score Floor、Top Coverage 与 Score Gap 已建立，其中真实 Query 已覆盖 Strong / Weak / None 与单候选 Strong 等关键路径。它仍不是 v0.6.0 Grounding Gate。


## v0.6.0 开发状态

`v0.6.0 — Grounded AI` 已完成设计、Stage 1 Grounding Gate、Stage 2 Evidence Pack / Prompt Builder、Stage 3 Grounded Answer 与 Final Review，并已作为正式 Stable Release 发布。

当前已经建立：

```text
Question
→ Local Retrieval
→ Grounding Gate
→ allow_answer / clarify / no_answer
```

最重要的规则仍然是：

```text
Application Gate > Prompt
No Reliable Knowledge → No AI Call
```

Stage 1 第一版保守 Gate：

```text
Strong + Reliable Yes → allow_answer
Medium / Weak          → clarify
None                   → no_answer
```

当前新增：

```text
WPAIC_Grounding_Gate
Grounding Gate Decision Contract
Reason Codes
Grounded AI 后台 Playground
AI Called = No
Token Usage = 0
```

Stage 1 即使得到 `allow_answer`，也只表示**策略上允许进入后续 AI Pipeline**；当前实现不会调用 `WPAIC_AI_Manager` 或 DeepSeek。

开发仍拆分为三阶段：

```text
Stage 1 — Grounding Gate Foundation      ← Validation Passed
Stage 2 — Evidence Pack & Prompt Builder ← Validation Passed
Stage 3 — Grounded Answer                ← Validation Passed
```

Stage 1 已在真实 WordPress 环境完成四类证据状态验证：

```text
CWC-610 dimension
→ Strong / Reliable Yes
→ allow_answer

CWC-610 warranty
→ Medium / Partial Evidence
→ clarify

dimension
→ Weak / Ambiguous
→ clarify

ZXQ-99999-NOMATCH
→ None / No Evidence
→ no_answer
```

四种情况均确认：

```text
AI Called = No
Token Usage = 0
```

其中 `CWC-610 warranty` 专门验证了 Partial Evidence：系统可以确认产品本身，但当问题中的 `warranty` 缺少完整本地证据时，Gate 不会因为产品型号匹配很强就放行 AI。Stage 1 正式封板，下一阶段进入 **Stage 2 — Evidence Pack & Prompt Builder**。

Stage 2 也已完成真实环境验收：`CWC-610 dimension` 只把正确 Product 作为 `S1` 放入 Evidence Pack，Score 14 / Coverage 50% 的普通候选被正确过滤；`dimension`、`CWC-610 warranty` 与 `ZXQ-99999-NOMATCH` 均验证非 `allow_answer` 状态会跳过 Evidence Pack 与 Prompt Preview。随后通过 `minimum order quantity` 构造并验证 `S1 + S2` 多来源 Evidence：`manual_3100`（Score 39 / Coverage 100%）作为 Primary Evidence，`manual_3104`（Score 21 / Coverage 100%）作为 Supporting Evidence，Prompt Preview 正确输出 `Evidence IDs: S1, S2`，弱相关候选仍被排除。Stage 2 正式封板，下一阶段进入 **Stage 3 — Grounded Answer**。

Stage 3 第一轮已经新增 `WPAIC_Grounded_Answer_Service`，将单轮流程正式串联为：

```text
Question
→ Local Retrieval
→ Grounding Gate
→ allow_answer only
→ Evidence Pack
→ Grounded Prompt
→ WPAIC_AI_Manager
→ DeepSeek Provider
→ Grounded Answer + Source Trace + Usage
```

关键边界：`clarify / no_answer` 在 Service 中直接返回确定性结果，绝不会越过 Provider Boundary；只有 `allow_answer` 才能调用 AI Manager。真实 AI 路径会返回 Provider、Model、Prompt / Completion / Total Tokens、Provider Elapsed、Pipeline Elapsed 与应用层 Source Trace。当前默认 `max_tokens = 640 / temperature = 0.1`，可通过 `wpaic_grounded_answer_ai_options` Filter 调整。

Stage 3 已完成真实 WordPress 环境验证：

```text
CWC-610 dimension
→ allow_answer
→ AI Called = Yes
→ DeepSeek / deepseek-flash
→ 580 Total Tokens
→ 正确回答 610*9mm [S1]
→ Source Trace = wordpress_post_2413

dimension
→ clarify
→ deterministic answer
→ AI Called = No
→ Total Tokens = 0

CWC-610 warranty
→ Medium / clarify
→ AI Called = No
→ Total Tokens = 0

ZXQ-99999-NOMATCH
→ no_answer
→ AI Called = No
→ Total Tokens = 0

minimum order quantity
→ allow_answer
→ AI Called = Yes
→ 369 Total Tokens
→ 回答明确说明没有具体 MOQ 数值证据
→ 正确引用 [S1][S2]
→ Source Trace 保留 S1 / S2
```

这证明 `allow_answer` 才会越过 Provider Boundary，而 `clarify / no_answer` 继续保持零 Token；多来源 Evidence 也能在真实 Provider Answer 中被正确约束和追踪。Stage 3 与 Final Review 均已通过，v0.6.0 已正式发布。

完整设计与阶段记录见：

```text
docs/versions/v0.6.0.md
```

## v0.7.0 开发状态

`v0.7.0 — Usage Guard` 的 **Stage 1 — Usage Store & Policy Foundation**、**Stage 2 — Provider Boundary Integration** 与 **Stage 3 — Limit Calibration & Operational Validation** 均已通过真实 WordPress 验收。Stage 3 已完成低额度校准、Scope 组合验证、WordPress Local Day 诊断、可重复 Lab Reset 与最终真实链路回归，并正式封板；下一步进入 **v0.7.0 Final Review**。

这一版开始解决：

```text
即使问题有可靠 Evidence，
也不能让公共网站无限调用 AI。
```

核心链路计划扩展为：

```text
Question
→ Local Retrieval
→ Grounding Gate
→ Evidence / Prompt
→ Usage Guard
→ AI Provider
```

第一版只做三个 Scope：

```text
Conversation AI Call Limit
Visitor Daily AI Call Limit
Site Daily AI Call Limit
```

设计默认基线：

```text
Conversation  10 / lifetime
Visitor       20 / day
Site         200 / day
```

Usage Guard 控制的是 **Provider Call**，不是用户消息数；`clarify / no_answer` 不增加 Usage。Stage 1 已新增 `wpaic_usage_counter` 表并把 DB Version 从 `1.0` 提升到 `1.1`；Stage 2 现已在真实 Provider Boundary 前执行 Reservation，并在成功 Provider Response 后记录真实 Token Usage。

Stage 1 真实环境已验证：

```text
只读检查不会增加 Counter                  ✅
Reservation 三个启用 Scope 原子 +1         ✅
Conversation lifetime limit                ✅
Visitor daily limit                        ✅
Site daily limit                           ✅
更换 Conversation 后 Visitor / Site 保持累计 ✅
Missing Context Fail Closed                ✅
任一 Scope BLOCK 后其他 Scope 不增加        ✅
后台 Limit 修改后实时 Policy 生效           ✅
Hashed Scope Key 稳定且不同 Key 相互隔离     ✅
```

最终 Visitor 专项测试使用 `Conversation = 10 / Visitor = 3 / Site = 20`：同一个 `visitor-limit-test` 在三个不同 Conversation 中成功 Reserve 到 `3/3`，第四个新 Conversation 正确返回 `visitor_daily_limit_reached`；被阻断时新 Conversation 与 Site 均不再增加。Stage 1 因此正式封板。Stage 2 也已完成真实 Provider Boundary 验证并封板。Stage 3 Round 1、Round 2 与 Final Operational Validation 均已完成真实 WordPress 验收，Stage 3 现已正式封板。



### v0.7.0 Stage 2 — Provider Boundary Integration

当前真实链路已扩展为：

```text
Question
→ Local Retrieval
→ Grounding Gate
→ Evidence Pack
→ Prompt Builder
→ Usage Guard Reserve
→ WPAIC_AI_Manager
→ AI Provider
→ Token Accounting
```

Stage 2 关键规则：

```text
clarify / no_answer
→ Usage Guard Skipped
→ AI Called = No
→ Token Usage = 0

allow_answer + Usage BLOCK
→ Evidence / Prompt 可以已构建
→ Provider Call 不发生
→ AI Called = No
→ 当前请求 Token Usage = 0

allow_answer + Usage ALLOW
→ 原子 Reserve Provider Call +1
→ 调用 AI Provider
→ 成功响应后记录真实 Token Usage
```

Grounded AI Playground 已新增 Conversation / Visitor / Site 测试 Key，并显示 Usage Guard Decision、Reason Code、Call Counter 与累计 Token。Stage 2 已完成真实 Provider Boundary 验证：`CWC-610 dimension` 两次成功调用后第三次被 Conversation Limit 阻断；Weak / Medium / None 路径均保持 Usage Guard Skipped、AI Called = No、Token = 0。Stage 2 当前状态：**Validation Passed / Sealed**。

最重要的边界：

```text
Grounded First
Usage Guard Second
Provider Last
```

完整设计见：

```text
docs/versions/v0.7.0.md
```

### v0.7.0 Stage 3 — Limit Calibration & Operational Validation

Stage 3 Round 1 与 Round 2 均已通过真实 WordPress 验收；继续保持 `DB Version = 1.1`，没有新增数据库字段。

新增的只是验收能力：

```text
Operational Snapshot
→ WordPress Timezone / Local Time / Daily Period Key
→ Scope Precedence: Conversation → Visitor → Site

Manual Lab Reset
→ 精确按当前测试 Key + Scope + Period 删除
→ Conversation = lifetime
→ Visitor / Site = current WordPress local day
→ 不提供整表清空
```

Usage Guard Playground 现在同时显示 Calls 与 Prompt / Completion / Total Token 累计，便于确认模拟 Reservation 只增加 Call、真实 Provider 成功响应才增加 Token。

Stage 3 继续使用低额度：

```text
Conversation = 2
Visitor Daily = 3
Site Daily = 5
```

Round 1 已真实验证通过：Boundary / Atomic BLOCK / Conversation-only Reset / WordPress Local Day。

Round 2 第一版曾使用 A–E Context Preset。实际试用后，为避免“记编号而不理解 Scope”，测试入口已优化成 1–6 个中文场景；后端 Guard、Repository、Provider Boundary 与测试 Key 关系都没有改变。

```text
场景 1：建立基准计数             → 2 / 2 / 2
场景 2：同一访客，新会话         → 0 / 2 / 2 → 1 / 3 / 3
场景 3：新访客，同一网站         → 0 / 0 / 3 → 2 / 2 / 5
场景 4：Conversation + Site 同时满 → Conversation BLOCK
场景 5：Visitor + Site 同时满      → Visitor BLOCK
场景 6：只有 Site 已满             → Site BLOCK
```

页面会先解释 `Conversation = 当前聊天 / Visitor = 当天同一访客 / Site = 当天整个网站`，每个场景点击后自动填写 Key，并直接显示“发生了什么 / 现在怎么做 / 预期结果”。选择场景本身不会修改 Counter。

Round 2 真实 WordPress 验收结果：6 个场景全部符合预期，新 Conversation 不重置 Visitor / Site，新 Visitor 不重置 Site，多 Scope 同时达到上限时稳定按 `Conversation → Visitor → Site` 返回，所有 BLOCK 路径继续保持 Atomic，未命中的 Scope 不增加。

当前状态：**v0.7.0 Final Review Passed / Stable Release**。Stage 3 已正式封板；Usage Guard 页面保留 Round 2 历史验收工具，Grounded AI 页面保留 Final Operational Validation 回归工具，供后续排查或版本回归使用。v0.7.0 不再增加功能，下一阶段仅进入 v0.8.0 Chat Integration 的设计与前置硬化。

最终测试入口改为“人话场景”，每一步都直接显示“发生了什么 / 现在怎么做 / 预期结果”：

```text
1. 有明确知识 → AI 正常回答
   Strong + 有额度 → AI Called = Yes → C=1/2、V=1/3、S=1/5

2. 问题太模糊 → 不调用 AI
   Weak → clarify → Usage Guard Skipped → Counter 不变

3. 资料不够明确 → 不调用 AI
   Medium → clarify → Usage Guard Skipped → Counter 不变

4. 完全没有资料 → 不调用 AI
   None → no_answer → Usage Guard Skipped → Counter 不变

5. 第二次正常回答 → 达到会话上限
   Strong + 有额度 → AI Called = Yes → C=2/2、V=2/3、S=2/5

6. 再问一次 → 被额度阻止
   Strong → conversation_limit_reached → AI Called = No → 本次 Token = 0
```

场景 6 后计数仍应保持 `C=2/2、V=2/3、S=2/5`。Candidate Limit、Top K 与 Usage Context 被收进“高级测试参数”，正常验收无需修改。Final Validation 仍不做 Chat UI、Lead、Human Handoff、Agent、Cost Guard、Embedding、Vector 或 RAG。

Final Operational Validation 已在真实 WordPress + DeepSeek 环境完成，6 个场景全部符合预期：

```text
场景 1：Strong → Usage ALLOW → AI Called = Yes
         Calls = 1 / 1 / 1
         Prompt 557 / Completion 23 / Total 580

场景 2：Weak → clarify → Usage Guard Skipped
         AI Called = No / Token = 0 / Counter 不变

场景 3：Medium → clarify → Usage Guard Skipped
         AI Called = No / Token = 0 / Counter 不变

场景 4：None → no_answer → Usage Guard Skipped
         AI Called = No / Token = 0 / Counter 不变

场景 5：Strong → Usage ALLOW → AI Called = Yes
         Calls = 2 / 2 / 2
         累计 Prompt 1114 / Completion 46 / Total 1160

场景 6：Strong → Usage BLOCK
         Reason = conversation_limit_reached
         AI Called = No / 本次 Token = 0
         Calls 仍保持 2 / 2 / 2
```

这次最终回归再次确认：**Grounding 阻断不消耗 Usage；Usage BLOCK 不越过 Provider Boundary；只有 Strong + quota available 才会真实调用 Provider 并记录 Token。**


## v0.3.1 开发状态

`v0.3.1` 在 v0.3.0 封版后的真实旧站兼容性审查中，从原计划的 **WEM Structured Source Validation** 调整为：

```text
v0.3.1 — Structured Source Validation
```

原因是大量现有站点的结构化字段直接定义在主题 `functions.php` 中，并不存在 WEM Field Group。新版本因此不把 WEM 作为架构前提，而是统一验证：

```text
Legacy Profile
+
WEM Field Definitions
→ Normalized Field Definitions
→ AI Allowlist
→ structured_data
→ Unified Knowledge Source
```

第一轮仍坚持 **Product First**；旧站无需迁移字段系统，新站后续继续利用 WEM Schema。

当前已经完成两阶段代码实现；Stage 1 Legacy Product 已在真实旧站验证通过：

```text
product_number
→ product_model

product_size
→ product_dimension
```

链路为：

```text
Legacy Product Profile
→ Structured Field Resolver
→ Structured Value Normalizer
→ Structured Source Enhancer
→ structured_data
→ Source Preview / Source Hash
```

Stage 2 已加入 **WEM Field Registry Provider**。当前 Provider 顺序为：

```text
Legacy Profile
→ fallback

WEM Field Registry
→ non-empty value overrides Legacy
```

因此同一个 `knowledge_key` 会按字段独立解析：WEM 非空值优先；WEM 空值保留 Legacy fallback。Stage 2 已在真实旧站 + WEM Content Fields 并存环境完成验证，并确认 Structured Data 与 Source Hash 行为符合预期。完整设计、实现与验收记录见：

```text
docs/versions/v0.3.1.md
```

真实环境最终确认：Legacy-only 站点可直接生成 Structured Data；WEM 与 Legacy 并存时，WEM 非空字段按 `knowledge_key` 覆盖 Legacy，WEM 空字段自动回退 Legacy；停用 WEM 后 Legacy 仍可独立工作。Structured Data 的变化会稳定反映到 Source Hash，恢复相同 AI-visible Knowledge 后 Hash 也恢复一致。

当前状态是 **Final Review Passed / Stable Release**。

## v0.4.0 设计状态

`v0.4.0 — Knowledge Store & Lifecycle` 已完成设计、四阶段实现、真实环境验证与 Final Review，现作为当前稳定 Release。

这一阶段第一次把已经标准化的 Unified Knowledge Source 持久化为可重建的 AI Read Model：

```text
WordPress Source of Truth
→ Unified Knowledge Source
→ Source Hash
→ Knowledge Store
→ Lifecycle
```

核心设计已经冻结为：

```text
WordPress = Source of Truth
Knowledge Store = Derived Snapshot
Hash = Content Change
Eligibility = Lifecycle State
Inactive ≠ Delete
Initial Sync = Batch
Daily Maintenance = Incremental
```

当前只引入 **1 张** Knowledge Store 自定义表，不提前创建 Chunk、Embedding、Log 或 Vector 表。Stage 1 第一轮代码严格限定为：

```text
DB Installer
Knowledge Store Table
Repository
Single-source Sync
Minimal Store Diagnostics
```

完整设计合同见：

```text
docs/versions/v0.4.0.md
```

Stage 1 已完成并通过真实环境验证：

```text
DB Installer / DB Version
Knowledge Store Table
Repository
Single-source Create / Unchanged / Update
Minimal Store Diagnostics
```

覆盖升级不依赖重新激活：插件启动时会通过 DB Version 检查执行幂等 `dbDelta()`。真实环境已经验证 Product / Post / Manual Knowledge 可以持久化，`created → unchanged → updated → Hash Restore` 正常，且 `source_id` 不重复。

Stage 2 Eligibility Lifecycle 已通过真实环境验证：Draft / Trash / Disabled / Deleted 均能把已有 Store Row 软停用，重新满足条件后 `reactivated`；Inactive Row 保留最后有效 AI-visible Snapshot 与 Hash。

Stage 3 已通过真实环境验证：首次 Full Sync 成功处理 138 条正式 Knowledge、Errors 0；连续重复 Full Sync 均得到 Created 0 / Updated 0 / Unchanged 138，Store Total 保持 139；禁用 Product 后 81 条 Product 由 Reconciliation 批量变为 inactive / source_disabled，重新启用后一次 Full Sync 得到 Reactivated 81 / Unchanged 57 / Errors 0。Knowledge Store Rows 同时完成每页 20 条的轻量分页。

Stage 4 Incremental Sync 已通过真实环境验证：相关 WordPress Source 保存后通过 `wp_after_insert_post` 只同步当前 Source；永久删除通过 `deleted_post` 自动标记 `source_deleted`。已验证 `updated / unchanged / deactivated / reactivated / created / source_deleted`，Legacy / WEM Structured Data 变化能正确进入 Hash 与 Store；日常单条保存不会改变 Last Full Sync，也不会触发整站 Batch。当前状态：**v0.4.0 Final Review Passed / Stable Release**。

## v0.2.0 实测状态

真实 WordPress 环境已经完成本版本核心与边界测试，包括：

- 正确 / 错误 / 空 API Key；
- `wp-config.php` 常量优先；
- DeepSeek 连接与中文最小对话；
- Usage、模型、耗时、Finish Reason 与 HTTP 状态；
- 断网、慢网与重复点击保护。

测试未发现功能问题。Final Review 中仅发现保存设置成功通知重复显示，现已修复，版本号继续保持 `v0.2.0`。

## v0.3.0 实测状态

`v0.3.0 — Knowledge Source Foundation` 已完成正式功能实现、真实 WordPress 环境测试与 Final Review，范围严格保持在设计合同确认的六项能力：

```text
1. Post Type Discovery
2. Enable / Disable Sources
3. Generic Extractor
4. Content Normalizer
5. Manual Knowledge CPT
6. Minimal Source Preview
```

本轮实现仍保持以下边界：

- Generic Extractor 默认不读取任何 Post Meta；
- Knowledge Source 按需生成，不建立自定义 Knowledge 表；
- Source Preview 不调用 DeepSeek；
- `wpaic_knowledge` 仅用于 AI 补充知识；
- WEM / WooCommerce / ACF Extractor 尚未加入；
- Chunk / Retrieval / RAG / Embedding / Vector 尚未加入。

真实环境已经验证 FAQ、Product、Post 与 Manual Knowledge 四类来源；复杂 Product 内容与普通 Blog 内容均能经过 Generic Extractor + Content Normalizer 输出可读 Knowledge Source。Draft 内容可预览但不会被标记为正式 Knowledge；Source Hash 在仅改变发布状态时保持稳定，在标题 / 正文等 AI-visible 内容变化时会改变。

当前状态是 **Final Review Passed / Stable Release**。正式开发定义、实现与验收记录见：

```text
docs/versions/v0.3.0.md
```

架构决策草案与外部评审问题保留在：

```text
docs/research/v0.3.0-knowledge-source-architecture-review.md
```

---

# 一、项目是什么

WP AI Chat Lab 是一个用于研究、设计和逐步实现 **WordPress AI 聊天系统** 的长期实验项目。

它目前不是一款完成的 WordPress 插件，也不急于立即成为正式产品。

这个仓库更重要的作用，是完整记录一个 AI Chat 项目从：

```text
问题出现
↓
设计思考
↓
竞品研究
↓
架构决策
↓
最小实验
↓
版本迭代
↓
真实网站测试
↓
稳定产品
```

的整个演进过程。

项目希望最终回答一个问题：

> **如何让 WordPress 网站已有的数据，安全、可靠、低成本地成为 AI 可以使用的知识，并进一步服务于网站访客、客户咨询和业务转化？**

---

# 二、项目最初从哪里开始

最初的问题非常简单：

> 能不能在现有 WordPress 在线客服插件中接入 DeepSeek，让 AI 自动回复客户？

最简单的实现似乎只是：

```text
Visitor
   ↓
Chat Window
   ↓
DeepSeek API
   ↓
AI Reply
```

但继续思考以后，很快发现：

**真正困难的并不是“调用 DeepSeek API”。**

真正需要解决的是：

- AI 应该回答什么？
- AI 不应该回答什么？
- AI 的知识从哪里来？
- 能不能直接读取 WordPress 产品数据？
- 是否需要为 AI 再维护一套产品数据库？
- 网站内容变化以后，AI 如何更新？
- 如何防止访客把网站变成免费的 ChatGPT？
- 如何避免无限消耗 Token？
- 没有网站知识时是否还应该调用 AI？
- AI 回答不出来时怎么办？
- 什么情况下应该转人工？
- 人工接管以后 AI 是否必须停止？
- 如何记录客户真正关心但网站没有回答的问题？
- AI 的最终目的到底是聊天，还是业务服务？

于是项目逐渐从：

> **给聊天插件增加一个 AI 接口**

发展为：

> **研究一套完整的 WordPress AI Knowledge + Retrieval + Guard + Chat 架构。**

---

# 三、为什么已经使用 Tidio，还要开发这个项目

这是本项目必须首先回答的问题。

目前真实网站已经在使用成熟的 **Tidio 在线客服系统**。

Tidio 本身已经拥有非常成熟的能力，包括：

```text
Live Chat
AI Agent
Human Handoff
Knowledge Base
Lead Capture
Automation
Visitor Tracking
Multi-channel
Mobile App
Analytics
```

其 AI Agent —— Lyro，也已经实现许多与本项目思考高度一致的能力：

- 从网站 URL 建立知识；
- 手工 Q&A；
- PDF / CSV 等知识源；
- WooCommerce 产品知识；
- Current Page Context；
- AI 无法回答时转人工；
- 未回答问题 Suggestions；
- 从人工聊天中发现新的知识；
- 人工审核后再加入知识库；
- Playground 测试；
- Guidance 行为规则；
- AI Actions。

因此：

> **如果目标只是让当前网站拥有 AI 客服，就没有必要重新开发一套 Tidio。**

直接使用 Tidio / Lyro 会更加成熟、省事和可靠。

---

# 四、所以本项目不以替代 Tidio 为短期目标

WP AI Chat Lab 与 Tidio 的定位不同。

可以简单理解为：

```text
Tidio
=
成熟商业客服 SaaS
```

而：

```text
WP AI Chat Lab
=
WordPress Native AI Architecture Lab
```

Tidio 的优势在于：

```text
成熟 Live Chat
多客服
移动 App
多渠道
通知
Ticket
Automation
客服基础设施
商业 SaaS 稳定性
```

这些能力本项目没有必要重复开发。

---

# 五、我们真正想研究的是 Tidio 不负责解决的问题

本项目关注的是：

```text
WordPress Data
        ↓
Website Knowledge
        ↓
Local Retrieval
        ↓
AI Guard
        ↓
AI Provider
        ↓
Conversation
```

尤其包括：

### WordPress 原生数据

直接理解：

```text
Product
Solution
FAQ
Custom Post Type
Custom Fields
```

而不是只看到最终网页 HTML。

---

### 自定义内容模型

例如网站自己的：

```text
Product Model
Dimensions
Material
Length
Color
Application
Solution
FAQ
```

这些字段本身已经具有明确语义。

AI 不应该再从网页正文中猜：

> 哪一个数字是尺寸？

而应该直接知道：

```text
Dimensions = 138 × 23 mm
```

---

### 本地 Knowledge

Knowledge、Conversation、Lead 等核心数据尽量保留在 WordPress 中。

---

### BYOK

项目本身不经营 AI SaaS。

用户提供自己的：

```text
DeepSeek API Key
```

WordPress 直接调用 Provider。

---

### 完整可控的 AI Gate

由 WordPress 自己决定：

```text
什么时候允许调用 AI

什么时候拒绝

什么时候转人工

什么时候停止

每天最多调用多少次
```

而不是完全交给第三方 SaaS。

---

### 完整源码与长期可扩展性

项目不仅要得到一个可用插件，更希望通过开发理解：

```text
Conversation
REST
Knowledge
RAG
Retrieval
Grounding
Prompt
Token
Guard
Human Handoff
AI Provider
Knowledge Lifecycle
```

从而形成真正的：

> **WordPress + AI 系统开发能力。**

---

# 六、Tidio 给本项目带来的重要启发

Tidio 不应该只是一个“竞争产品”。

它更适合作为一个成熟的设计参考。

---

## 1. Knowledge Sources

AI 的回答应该建立在明确的数据源上，而不是无限使用模型自己的知识。

---

## 2. Unanswered Questions

AI 不知道的问题不是垃圾数据。

它们代表：

> **Knowledge Gap。**

例如：

```text
客户不断询问：

MOQ是多少？
是否支持巴西市场？
质保是多少年？
是否提供样品？
```

这些问题应该被记录。

---

## 3. Knowledge Suggestions

形成：

```text
Customer Question
        ↓
AI Cannot Answer
        ↓
Knowledge Suggestion
        ↓
Human Review
        ↓
Approve / Reject
        ↓
Knowledge Base
```

---

## 4. Human Review

AI 自动发现的知识不应该立即成为正式知识。

应该：

```text
Discover
↓
Suggestion
↓
Review
↓
Enable
```

---

## 5. Current Page Context

如果访客正在：

```text
/product/product-a/
```

然后询问：

> What colors are available?

系统应该知道：

> 用户正在问 Product A。

---

## 6. Human Handoff

AI 不应该承担所有问题。

复杂、高价值或无法确认的问题应该进入人工流程。

---

## 7. Playground

正式上线前必须能够：

```text
输入问题
↓
查看检索结果
↓
查看 AI 回答
↓
发现问题
↓
调整 Knowledge
↓
重新测试
```

---

# 七、项目长期目标

长期希望逐渐形成：

# WordPress AI Chat Framework

架构可以演进为：

```text
WordPress Content
        ↓
Knowledge Layer
        ↓
Retrieval Layer
        ↓
Guard Layer
        ↓
AI Provider
        ↓
Conversation Layer
        ↓
Human / Lead / Action
```

但始终坚持：

> **先解决真实问题，再增加功能。**

---

# 八、核心设计原则

## 1. AI 不是 Knowledge Source

DeepSeek、OpenAI、Gemini、Claude 等模型主要负责：

```text
理解问题
理解上下文
组织语言
生成自然回答
```

而不是默认：

> 什么都知道，什么都回答。

核心原则：

# 有依据才回答。

---

# 九、WordPress 数据才是主要事实来源

WordPress 已经存在的数据不应该重复录入。

例如：

```text
Product
Solution
FAQ
Page
Post
Custom Post Type
Custom Fields
```

都可以成为：

```text
Knowledge Source
```

---

# 十、Single Source of Truth

系统坚持：

> **一份数据，多处使用。**

错误方式：

```text
WordPress Product
        +
AI Product Database
```

这会产生数据同步问题。

正确方式：

```text
WordPress Product
        ↓
Knowledge Builder
        ↓
AI Knowledge
```

产品修改以后：

```text
Knowledge
```

自动重新建立。

---

# 十一、自动知识为主，人工知识为辅

Knowledge 分为三个层次：

```text
Knowledge
│
├── Structured Content
│
├── Page Content
└── Manual Knowledge
```

---

## Structured Content

例如：

```text
Product Name
Model
Dimensions
Length
Material
Color
Category
```

可信度最高。

---

## Page Content

例如：

```text
Overview
Features
Applications
Installation
Maintenance
```

补充解释型内容。

---

## Manual Knowledge

网站正文不适合保存，但客服需要知道的信息：

```text
MOQ
Sample Policy
Quotation Rules
Shipping Rules
Dealer Policy
Sales Process
Special Instructions
```

---

# 十二、Knowledge Source Adapter

不同内容类型拥有自己的 Adapter。

```text
Knowledge Source
│
├── Product Adapter
├── Solution Adapter
├── FAQ Adapter
└── Manual Knowledge Adapter
```

Adapter 决定：

> 哪些字段真正属于 AI Knowledge。

---

# 十三、字段必须使用白名单

不能：

```text
get_post_meta()
↓
全部进入 AI
```

否则容易包含：

```text
SEO Plugin Meta
Elementor Settings
Theme Options
Cache
Revision Data
Internal Data
```

正确方式：

```text
Product
│
├── Title            ✓
├── Category         ✓
├── Model            ✓
├── Dimensions       ✓
├── Material         ✓
├── Length           ✓
├── Colors           ✓
├── Content          ✓
│
├── SEO Meta         ✕
├── Builder Settings ✕
├── Internal Cache   ✕
└── Revision Data    ✕
```

---

# 十四、Knowledge Lifecycle

内容创建：

```text
Create
↓
Build Knowledge
```

内容修改：

```text
Update
↓
Check Hash
↓
Rebuild if Changed
```

内容删除：

```text
Delete
↓
Remove Knowledge
```

---

# 十五、使用 Content Hash

生成：

```text
Knowledge Payload
        ↓
Normalize
        ↓
SHA-256
```

如果：

```text
New Hash == Old Hash
```

则：

```text
Skip
```

如果：

```text
New Hash != Old Hash
```

才：

```text
Rebuild Knowledge
```

避免无意义重复处理。

---

# 十六、第一阶段 Knowledge Source

计划首先支持：

```text
Product
Solution
FAQ
Manual Knowledge
```

暂时不自动开启：

```text
Posts
Pages
All CPT
PDF
External Website
```

原因是：

> 网站上存在某段内容，不等于公司销售或承诺其中描述的东西。

例如 Blog 中讨论竞争产品：

```text
Wood
PVC
Stone
Composite
```

并不意味着公司出售所有这些产品。

---

# 十七、Knowledge Priority

不同知识来源具有不同可信度。

例如：

```text
Structured Product Data
        ↓
Manual Verified Knowledge
        ↓
FAQ
        ↓
Solution
        ↓
Page
        ↓
Blog
```

当不同来源存在冲突：

> 高可信 Source 优先。

必要时：

> 不回答，转人工确认。

---

# 十八、第一阶段 Retrieval 不使用 Vector Database

暂时不计划引入：

```text
Embedding
Pinecone
Qdrant
HNSW
Vector Database
```

而首先验证：

# Local Retrieval

---

## 初始检索信号

```text
Current Page
Product Title
FAQ Question
Category
Structured Fields
Heading
Chunk Content
```

给予不同权重：

```text
Current Page        High

Product Title       High

FAQ Question        High

Structured Fields   High

Heading             Medium

Content             Normal
```

---

# 十九、为什么第一阶段不使用 Embedding

原因不是 Vector 不好。

而是现阶段：

```text
几十个产品
几十个 FAQ
几个 Solution
```

规模并不大。

Local Retrieval：

- 更简单；
- 更透明；
- 更容易调试；
- 不产生 Embedding API 成本；
- 更容易判断为什么找到某个结果。

原则：

> **简单方法能够解决问题时，不提前引入复杂技术。**

---

# 二十、Grounding Gate

这是系统最核心的机制之一。

```text
User Question
        ↓
Local Retrieval
        ↓
Enough Knowledge?
```

如果：

```text
YES
```

进入：

```text
AI
```

如果：

```text
NO
```

则：

# 不调用 AI。

---

## Grounding Gate 同时解决两个问题

### 防止 Hallucination

没有知识：

> AI 不自行发挥。

---

### Token Firewall

没有知识：

> 不产生 DeepSeek Chat API 调用。

因此：

```text
Retrieval
=
RAG
+
Token Firewall
```

---

# 二十一、不能只依赖 Prompt

不能只告诉 AI：

```text
“请不要回答无关问题。”
```

因为 Prompt 本身不能作为安全边界。

应该：

```text
Application Rules
        ↓
Retrieval
        ↓
Grounding
        ↓
Usage
        ↓
AI
```

原则：

# Application Gate > Prompt

---

# 二十二、AI 不能成为通用聊天机器人

公共网站不能变成：

> 网站管理员出钱提供的免费 ChatGPT。

例如用户询问：

```text
怎么学习 Python？
```

流程应该：

```text
Question
↓
Local Retrieval
↓
No Business Knowledge
↓
Fixed Reply
↓
AI API = 0
```

而不是：

```text
DeepSeek
↓
开始教授 Python
```

---

# 二十三、Usage Guard

即使问题属于业务范围，也不能无限调用 AI。

第一阶段计划：

```text
Conversation AI Limit
Visitor Daily Limit
Site Daily Limit
```

例如：

```text
单会话：
10 次 AI Reply

单访客：
20 次 / Day

全站：
200 次 / Day
```

超过：

```text
AI Stop
```

但：

```text
Live Chat
Lead Capture
Human Chat
```

仍然正常工作。

---

# 二十四、未来可以增加 Cost Guard

例如：

```text
Monthly Token Budget
Monthly Cost Budget
```

达到预算：

```text
AI Disabled
```

这样最坏成本仍然可预测。

但第一阶段优先：

```text
Request Count
```

保持简单。

---

# 二十五、Conversation Context

不会每次都把完整聊天历史发送给 AI。

第一阶段：

```text
System Prompt
+
Knowledge Context
+
Recent Messages
+
Current Question
```

只保留最近几轮。

后期可以增加：

```text
Conversation Summary
```

将长聊天压缩为：

```text
Customer interested in:

Composite Decking
Grey
138 × 23 mm
500㎡
California
```

减少 Token。

---

# 二十六、回答长度也应该受控制

AI 客服不是写作机器人。

默认应该：

```text
简洁
准确
业务导向
```

例如：

```text
2–5 个短段落
```

或少量列表。

避免：

```text
长篇百科内容
主动扩展无关知识
无休止继续聊天
```

---

# 二十七、FAQ First

部分高频问题甚至不需要 AI。

例如：

```text
联系方式
报价流程
样品流程
MOQ规则
工作时间
```

可以：

```text
Question
↓
FAQ Match
↓
Local Answer
```

AI API：

```text
0
```

---

# 二十八、问题类型

长期可以将问题大致分成：

| 类型 | 示例 | 处理 |
|---|---|---|
| FAQ | 如何获取样品？ | 本地回答 |
| Knowledge | 产品尺寸是多少？ | Knowledge + AI |
| Lead | 我要1000㎡报价 | Lead Flow |
| Off-topic | 教我写Python | 本地拒绝 |
| Sensitive | 能保证使用25年吗？ | Knowledge / Human |

---

# 二十九、AI 的角色

系统中 AI 更接近：

# Language Renderer

而不是：

# Knowledge Source

也就是说：

```text
Website Knowledge
        ↓
AI理解
        ↓
Natural Answer
```

而不是：

```text
AI知道什么
        ↓
就回答什么
```

---

# 三十、Unanswered Questions

Knowledge 不足时：

```text
Question
↓
No Answer
↓
Record
```

后台形成：

# Knowledge Gap

例如：

| Question | Count |
|---|---:|
| MOQ是多少？ | 12 |
| 支持巴西市场吗？ | 8 |
| 有25年质保吗？ | 5 |

---

# 三十一、Knowledge Gap Workflow

```text
Customer Question
        ↓
Cannot Answer
        ↓
Knowledge Gap
        ↓
Human Review
        ↓
Add Knowledge
        ↓
AI Can Answer Next Time
```

这样系统会随着真实客户问题逐渐变得更好。

---

# 三十二、知识不能自动无审核学习

从：

```text
Human Conversation
```

或者：

```text
Unanswered Question
```

生成的 Knowledge，只能作为：

```text
Suggestion
```

默认：

```text
Disabled
```

管理员确认以后：

```text
Enable
```

原则：

> **AI 可以发现知识缺口，但正式知识必须有人负责。**

---

# 三十三、AI Playground

后台应该提供独立测试环境。

输入：

```text
What is your MOQ?
```

系统显示：

```text
Retrieved Sources
Score
Source Type
Matched Chunks
```

然后：

```text
Grounding Result
```

最后：

```text
AI Answer
```

这样可以区分：

```text
Retrieval Problem

还是

Prompt Problem

还是

Model Problem
```

---

# 三十四、Conversation 与 Handler 必须分离

Conversation Status：

```text
open
closed
spam
```

Handler Mode：

```text
ai
pending_handoff
human
```

两者表示不同概念。

---

# 三十五、Human Handoff

工作流：

```text
AI
↓
Cannot Reliably Answer

或

High-value Lead

或

Customer Requests Human
↓
pending_handoff
↓
Human Takeover
↓
human
```

进入：

```text
human
```

以后：

```text
Visitor Message
↓
Save Message
↓
Stop
```

不会继续：

```text
Retrieval
AI
Token
```

---

# 三十六、Human 完成后

管理员可以：

```text
Resume AI
```

状态：

```text
human
↓
ai
```

AI重新接管后续普通问题。

---

# 三十七、Lead First

这个项目不是为了最大化：

> Chat Duration。

而是：

```text
Useful Answer
        ↓
Purchase Intent
        ↓
Contact Information
        ↓
Human Follow-up
```

出现：

```text
Quote
MOQ
Sample
Shipping
Dealer
Distributor
Custom Product
Large Quantity
```

应该逐渐引导：

```text
Name
Email
WhatsApp
Country
Product
Quantity
Destination
```

---

# 三十八、第一阶段 AI Provider

只实现：

# DeepSeek

原因：

- 已有实际调用经验；
- OpenAI Compatible；
- 成本较低；
- 足够验证完整架构。

采用：

```text
BYOK
```

架构：

```text
WordPress
↓
DeepSeek API
```

不经过项目自己的 SaaS Server。

---

# 三十九、Provider Architecture

虽然第一版只有 DeepSeek，但长期预留：

```text
AI Provider Interface
│
├── DeepSeek
├── OpenAI
├── Gemini
└── Claude
```

业务层不直接依赖具体 Provider。

---

# 四十、现有项目提供的基础

## WP Live Chat Inquiry

已经验证：

```text
Conversation
Messages
Visitor Token
REST API
Polling
Lead
Contact
Admin Reply
Conversation Status
Spam
Rate Limit
Import / Export
```

因此不需要重新发明聊天系统的所有基础能力。

---

## SEO Article Studio

已经验证：

```text
Content Scanner
Content Normalizer
Content Hash
AI Client
Knowledge Store
Batch Processing
Versioning
Status
```

这些设计经验可以复用。

但：

> 不产生代码级强依赖。

---

# 四十一、参考插件研究

目前主要研究过：

```text
AxiaChat
MxChat
Rapls
Zorachat
ChatBudgie
Tidio / Lyro
```

---

## Rapls

重点吸收：

```text
Grounding Gate
Usage Gate
Guest Hash
Unanswered Questions
Field Allowlist
Cost Guard
```

---

## Zorachat

重点吸收：

```text
AI / Human State Machine
Takeover
Structured Product Payload
Source Hash
```

---

## ChatBudgie

重点吸收：

```text
Knowledge Lifecycle
Product Attributes
Incremental Indexing
Source Grouping
```

---

## Tidio

重点吸收：

```text
Knowledge Sources
Knowledge Suggestions
Human Review
Current Page Context
Human Handoff
Guidance
Playground
AI Actions Concept
```

但不试图复制：

```text
Multi-channel
Mobile App
Ticket System
Full SaaS Customer Service Platform
```

---

# 四十二、当前核心架构

```text
                   WordPress
                       │
                Website Content
                       │
        ┌──────────────┼──────────────┐
        │              │              │
     Product        Solution         FAQ
        │              │              │
        └──────────────┼──────────────┘
                       │
                Source Adapter
                       │
                       ▼
                Knowledge Builder
                       │
                       ▼
                  Source Hash
                       │
                       ▼
                     Chunk
                       │
                       ▼
                Knowledge Store
                       │
                       ▼
                 Local Retrieval
                       │
Visitor ───────────────┤
                       ▼
                Conversation Gate
                       │
                       ▼
                 Grounding Gate
                 ┌─────┴─────┐
                 │           │
               Match       No Match
                 │           │
                 ▼           ▼
             Usage Gate   Local Reply
                 │           │
                 ▼           ▼
              DeepSeek   Knowledge Gap
                 │
                 ▼
              AI Reply
                 │
          ┌──────┴──────┐
          │             │
      Continue AI   Lead / Human
                         │
                         ▼
                    Human Takeover
```

---

# 四十三、建议代码架构

长期可以演化为：

```text
wp-ai-chat/
│
├── chat/
│
├── knowledge/
│   ├── adapters/
│   ├── builder/
│   ├── store/
│   └── lifecycle/
│
├── retrieval/
│
├── guard/
│
├── ai/
│   ├── providers/
│   ├── prompt/
│   └── usage/
│
├── lead/
│
├── human/
│
└── admin/
```

重点是：

> **Knowledge 与 Chat 解耦。**

---

# 四十四、为什么 Knowledge 应该独立

长期可能出现：

```text
WordPress Knowledge Core
        │
        ├── AI Chat
        ├── Admin Assistant
        ├── SEO Assistant
        └── AI Search
```

因此真正有长期价值的可能不只是：

> 聊天窗口。

而是：

# WordPress Knowledge Layer

---

# 四十五、第一阶段明确不做

暂时不开发：

```text
Embedding
Vector Database
Pinecone
Qdrant
PDF
Voice
Image Recognition
Web Search
MCP
Appointment
CRM
WhatsApp API
Complex Workflow
Multiple Bots
AI Content Generator
WooCommerce Ordering
Mobile App
Ticket System
Multi-channel
WebSocket
```

这些不是永远不做。

而是：

> **没有真实需求之前不做。**

---

# 四十六、版本路线

## v0.1.0

### Concept & Architecture Baseline

完成：

```text
设计思想
项目定位
Tidio 对照
竞品研究
架构设计
核心原则
功能边界
Roadmap
```

本版本：

> 不以代码为主要目标。

---

## v0.2.0

### AI Provider Foundation

已实现：

```text
WordPress Admin
      ↓
AI Manager
      ↓
AI Provider Interface
      ↓
DeepSeek Provider
      ↓
DeepSeek API
      ↓
Unified Response
```

本版本正式加入可安装的 WordPress 插件骨架、DeepSeek API Key 双入口、连接测试、最小 AI 测试、统一响应与错误模型。

仍然**不包含** Knowledge、Retrieval、Grounding、Conversation、Lead 或 Human Handoff。详细设计与测试清单见：

```text
docs/versions/v0.2.0.md
```

---

## v0.3.0

### Knowledge Source Foundation

v0.3.0 已完成实现、真实环境测试与 Final Review，稳定范围严格限定为：

```text
Post Type Discovery
Enable / Disable Sources
Generic Extractor
Content Normalizer
Manual Knowledge CPT
Minimal Source Preview
```

本版本不包含 WEM、Chunk、Retrieval、RAG、Embedding、Vector 或 Knowledge Store。

详细定义：

```text
docs/versions/v0.3.0.md
```

---

## v0.3.1

### Structured Source Validation

当前采用两阶段真实验证：

```text
第一阶段：Legacy Product Profile
第二阶段：WEM Field Definitions
```

本版本已经完成两阶段真实验证：Legacy Product 最小链路将 `product_number` 与 `product_size` 映射为稳定 `knowledge_key`；随后加入 WEM Field Registry Provider，并验证 WEM 非空值优先、WEM 空值逐字段回退 Legacy。两条路径最终都进入相同 `structured_data` 与 Source Hash。

---

## v0.4.0

### Knowledge Store & Lifecycle

当前设计已完成，核心目标是把 Unified Knowledge Source 保存为可重建的 AI Read Model，并把“内容变化”与“生命周期状态”分开处理：

```text
Unified Knowledge Source
→ Knowledge Store
→ Source Hash Comparison
→ Create / Update / Unchanged
→ Deactivate / Reactivate
→ Batch Initial Sync
→ Incremental Sync
```

本版本计划首次引入 **1 张 Knowledge Store 自定义表**。WordPress 原始内容继续是唯一 Source of Truth；Store 只是派生快照，不允许成为第二套业务内容后台。

详细设计：

```text
docs/versions/v0.4.0.md
```

---

## v0.5.0

### Local Retrieval

设计已经完成，目标是在 **不调用 AI、不使用 Embedding / Vector Database** 的前提下，从 v0.4.0 Knowledge Store 中为真实问题找出最相关的 Top-K Knowledge Source：

```text
Question
↓
Query Normalizer
↓
Candidate Recall
↓
Weighted Local Scoring
↓
Top-K Relevant Sources
↓
Score Breakdown
```

第一版继续坚持 **Local Retrieval First**：SQL 只负责 active Knowledge Candidate Recall，PHP 负责可解释的字段加权评分。初始权重方向为 Structured Data > Title > Taxonomies > Excerpt > Content，并加入 Exact Match、Phrase Match 与 Query Coverage。

本版本只负责 **找证据**，不负责生成答案；不加入 DeepSeek Answer、Prompt Builder、RAG、Chunk、Embedding 或 Vector。

开发计划分为：

```text
Stage 1 — Query & Candidate Foundation
Stage 2 — Weighted Scoring
Stage 3 — Retrieval Quality Calibration
```

完整设计合同见：

```text
docs/versions/v0.5.0.md
```

Stage 1 第一轮代码已经实现：

```text
Query Normalizer
→ Term Extraction
→ Active-only Candidate Search
→ Candidate Limit
→ Local Retrieval Playground
```

当前只验证 **Question → Candidate Recall**：SQL `LIKE` 仅从 `store_status = active` 的 Knowledge Store Row 中召回候选；后台“本地检索”页面显示 Normalized Query、Terms、Candidate Count、Matched Fields / Terms 与 Snippet。当前顺序不是最终 Ranking，Weighted Scoring / Top-K 留到 Stage 2。Stage 1 不调用 DeepSeek、不修改 Knowledge Store、不新增 Retrieval 数据表。

Stage 1 已在真实 WordPress 环境验证通过：

```text
CWC-610 → Candidate Count 1，正确 Product 命中 title + structured_data
CWC-610 dimension → 正确 Product 稳定进入 Candidate Set
minimum order quantity → 正确 Manual Knowledge 被召回
inactive 特有词 → Candidate Count 0
ZXQ-99999-NOMATCH → Candidate Count 0 / No Candidate Match
```

同时确认自然英文问句归一化、Stop Words、连字符型号、Active-only Search、Manual Knowledge 通用召回与 No Match 边界均符合预期。Stage 1 正式封板，下一阶段进入 **Stage 2 — Weighted Scoring**。

---

## v0.6.0

### Grounded AI

实现：

```text
Retrieval
↓
Grounding Gate
↓
DeepSeek
↓
Answer
```

---

## v0.7.0

### Usage Guard

实现：

```text
Conversation AI Call Limit
Visitor Daily AI Call Limit
Site Daily AI Call Limit
```

并建立：

```text
Usage Context
Usage Counter Store
Provider Boundary Reserve
Token Usage Diagnostics
```

---

## v0.8.0

### Chat Integration

将 AI 接入真实前台 Chat。

---

## v0.9.0

### Lead Capture & Inquiry Management

当前状态：**Stage 1 / Stage 2 / Stage 3 全部 Validation Passed / Sealed；v0.9.0 Final Review Passed / Stable Release。**

Stage 2 后台管理采用更简单的运营模型：

```text
wp_wpaic_inquiries
↓
未读 / 已读
↓
查看详情自动已读
↓
全部 / 未读 / 已读 / 回收站筛选
↓
软删除 / 恢复 / 永久删除
```

Inquiry 业务状态只保留 `unread / read`；回收站使用独立 `trashed_at` 生命周期，不再把 `contacted / closed / spam` 混入状态。列表默认每页 10 条，使用 WordPress 原生 list-table 风格和紧凑分页。Plugin Version 保持 `0.9.0`，DB Version 升至 `1.3`。

目标：在 v0.8.0 已稳定的 Chat Integration 之上建立自然、可解释的询盘转化路径：

```text
Chat Response
↓
Lead Trigger Policy
↓
CTA
↓
Inquiry Form
↓
Inquiry Database
↓
后台 Inquiry Management
```

第一版 Lead Trigger 采用确定性规则，不额外调用 AI 做 Lead Scoring。

重点 Trigger：

```text
manual
commercial_intent
no_answer
usage_blocked
```

其中 **commercial_intent 关键词必须后台完全可配置**：插件提供默认关键词，但管理员可以增删改、保存空列表以关闭关键词触发，并可主动恢复默认值。

Stage 1 Round 1 已实现并通过真实 WordPress 验收：

```text
Commercial Keywords Settings
↓
Deterministic Lead Trigger Policy
↓
Lead Trigger Lab
```

当前 Plugin Version 为 `0.9.0`。Stage 1 Round 2 将 DB Version 升至 `1.2`，新增独立 `wp_wpaic_inquiries`，并建立 `POST /wp-json/wpaic/v1/inquiry`、Inquiry Service / Repository、服务器校验、Honeypot 与独立 Visitor Submission Rate；Stage 1 Round 3 将同一个 Lead Trigger Policy 与同一个 Public Inquiry REST 接入正式 Chat Widget。Stage 2 Round 2 为独立回收站生命周期新增 `trashed_at` 并将 DB Version 升至 `1.3`；Stage 2 Round 3 保持 DB Version `1.3`，不再新增表或字段。

Round 1 已验证：普通 Answer 不触发、默认/自定义商业关键词、中文关键词增删、空列表关闭、恢复默认、`no_answer`、Visitor/Site/Conversation Usage Block、`clarify` / `error` Hard Stop、`manual` 最高优先，以及 `order` 不误命中 `border`。关键词首次保存时发现并修复了 Settings API sanitize callback 缺失导致的 Critical Error；修复后保存与运行时共用同一套标准化逻辑。

Round 2 已真实验证：正常 HTTP 201 写入、必填字段、非法 Email、非法 Conversation UUID、Trigger allowlist、Honeypot 不写库且不消耗 Rate、同 Visitor 前 5 次成功 / 第 6 次 HTTP 429 + Retry-After，以及失败请求 Rows 不增加。数据库仅保存 Visitor Hash，不保存原始 Visitor UUID / IP / 完整 Conversation。

Round 3 已真实验证：Manual Contact Sales、commercial_intent / no_answer / usage_blocked CTA、clarify 后商业意图连续性、Inline Form 真提交、失败保留与重试、Inquiry Rate 429、Cancel / × 草稿丢弃、刷新隐私边界、Form 打开时 Chat Composer 禁用与手机端布局。

详细设计与 Round 1 / Round 2 / Round 3 验收清单见：`docs/versions/v0.9.0.md`。

## v0.9.0 测试数据与正式上线注意事项

### 1. 测试数据来源

本项目 v0.3.0～v0.9.0 的大量真实环境测试，使用 **COODEC 官网（https://www.coodecglobal.com/）公开内容及对应 WordPress 站点数据**作为真实企业站样本，包括产品、文章、FAQ、结构化字段、Knowledge、Retrieval、Grounding、Chat 与 Inquiry 场景。

因此 README / Lab 中出现的以下内容都应理解为**测试快照**，而不是插件固定业务规则：

```text
产品型号与产品名称
测试问题（例如 dimension / warranty / quote 等）
Retrieval Score / Coverage / Gap
Grounding Strong / Medium / Weak / None 的具体样例
Provider Token 数
Usage Counter 数值
Inquiry 测试数据
商业需求关键词
```

后续继续测试时应注意：

- COODEC 网站内容发生变化后，同一句 Query 的候选、Score、Grounding 结果与 Token 都可能变化；不要机械要求与历史截图完全一致。
- 如果换到其他 WordPress 网站，应使用**该站自己的真实产品、FAQ、文章和业务语言**重新建立测试样例，不要继续依赖 COODEC 的产品型号。
- 内容模型或字段结构变化后，先执行 Knowledge Preview / Sync，再重新验证 Retrieval → Grounding → Chat。
- 建议长期保留一组稳定回归问题：**明确命中、部分证据、模糊问题、完全无匹配、商业意图**各至少 1 条。
- 测试数据只使用公开或专门构造的数据；不要把客户真实隐私信息、API Key、内部报价或其他敏感内容写入 GitHub 仓库。
- Lab 中产生的测试 Inquiry 应使用明显的测试姓名 / 邮箱 / Message，测试结束后清理，避免与真实客户询盘混淆。

### 2. 测试设置与正式上线设置

开发期间为了快速触发边界，很多 Limit 会被**临时调低**。这些测试值绝不能不检查就直接带到生产环境。

| 项目 | 测试阶段常见做法 | 正式上线建议 |
| --- | --- | --- |
| DeepSeek API Key | 测试 Key / 后台临时配置 | 优先放在 `wp-config.php` 或安全配置中；禁止提交到仓库 |
| Conversation AI Calls | 常临时设为 `1～2` 验证 BLOCK | 默认基线可从 `10 / conversation` 起，根据成本与业务调整 |
| Visitor Daily AI Calls | 常临时设为 `1～3` | 默认基线可从 `20 / day` 起，根据实际访问量调整 |
| Site Daily AI Calls | 常临时设为 `2～20` | 默认基线可从 `200 / day` 起，并结合预算观察 |
| Public Request Guard | 测试时可降到 `3 / minute` | 建议恢复到合理值；当前默认 Visitor `20 / minute` |
| IP Request Guard | 通常保持 `0` | 未确认真实客户端 IP 前继续保持关闭；高流量站交给 Cloudflare / WAF |
| Inquiry Rate | 测试时频繁 Reset | 当前默认同 Visitor `5 / hour`；上线后按垃圾询盘情况调整 |
| Commercial Keywords | 默认词 + 临时中文测试词 | 按真实产品、询盘语言、市场与销售术语重新检查；空列表代表主动关闭 |
| Provider Failure Lab | 测试时可能 Armed | 正式上线必须保持 `Idle`，不要带着故障注入状态上线 |
| Lab Reset | 可频繁重置测试 Counter | 正式环境只在明确知道影响范围时使用，不要把运营数据当测试数据清除 |
| Chat Widget | 测试环境反复开关 | 上线前完成桌面端 / 手机端 / 无痕窗口端到端测试后再启用 |

另外必须检查：

1. **WordPress Timezone**：Visitor / Site Daily Limit 使用 WordPress Local Day。站点若仍是 `+00:00`，每天额度会按 UTC 切换。生产站应改成真实业务时区。
2. **数据库引擎**：Usage Counter 的 Public Provider Boundary 依赖事务与行锁；生产环境必须保持支持事务的 InnoDB 等引擎。
3. **DB Version**：v0.9.0 为 `1.3`。升级前建议备份 WordPress 数据库与插件文件。
4. **测试 Counter**：上线前建议清理专用测试 Counter / Rate 状态，并确认 Provider Failure Lab 为 `Idle`。
5. **测试 Inquiry**：上线前删除或清空明确的测试询盘，避免后台未读数量与真实运营数据混在一起。
6. **商业关键词**：默认词只是通用起点。B2B 站应补充自身常用的 quote / sample / MOQ / distributor / project 等语言及对应中文或目标市场语言。

### 3. 正式上线前检查清单

建议每个生产站至少执行一次：

```text
[ ] 备份数据库与插件文件
[ ] 确认 Plugin Version = 0.9.0 / DB Version = 1.3
[ ] 确认 WordPress Timezone
[ ] 确认 DeepSeek API Key 未写入仓库且连接测试正常
[ ] 执行 Knowledge Preview / Full Sync，确认 Errors = 0
[ ] 使用本站真实内容重新跑 Retrieval / Grounding 基础回归
[ ] 恢复正式 Usage Guard / Request Guard / Inquiry Rate 设置
[ ] 确认 Provider Failure Lab = Idle
[ ] 确认商业需求关键词符合本站产品和目标市场语言
[ ] 清理测试 Counter、测试 Inquiry 与临时测试数据
[ ] 检查 Chat Widget 桌面端与手机端
[ ] 验证 answer / clarify / no_answer / blocked / error
[ ] 验证 Request a Quote / Leave a Message / Contact Sales
[ ] 验证真实 Inquiry 能进入后台且显示为未读
[ ] 验证询盘详情、搜索、筛选、回收站正常
[ ] 检查 Privacy Policy / Cookie / 联系表单隐私说明是否覆盖询盘数据
[ ] HTTPS 正常；如有高流量或攻击风险，在 Cloudflare / WAF 增加边缘保护
```

### 4. 其他运维注意事项

- **v0.9.0 不提供邮件通知。** 新 Inquiry 会进入 WordPress 后台“询盘管理”，运营人员需要主动查看；不要误以为提交后一定会自动发邮件给销售。
- **v0.9.0 不保存服务器端完整聊天记录。** Conversation ID / 当前标签页 Chat transcript 的连续性与 Inquiry 数据是两套概念；不要把 Inquiry 当成完整 Conversation Archive。
- **未提交的个人信息不持久化。** Name / Email / Company / Phone / Inquiry Message 草稿不会因为刷新而保存在 `sessionStorage`。
- **Inquiry 中包含个人信息。** 生产站应限制后台管理员权限，建立合理的数据保留 / 删除规则，并根据所在地法规完善 Privacy Policy。
- **Public Request Guard 属于 WordPress 应用层 best-effort 保护。** 高流量、Bot 或攻击场景优先在 Cloudflare / WAF 等边缘层限流。
- **IP Rate 默认关闭是有意设计。** 只有在确认代理链路下 `REMOTE_ADDR` 代表真实访客时才启用，避免共享代理出口误伤。
- **Lab / Playground 是长期诊断工具。** 可以用于测试与回归，但不要把测试按钮当成日常运营操作；测试后应恢复生产设置。
- **Source URL 只接受本站来源。** 不应把访客传入的外站 URL 当成可信来源链接。
- **Read / Unread 只是轻量运营状态。** 当前版本没有 CRM Pipeline、Sales Owner、Lead Tag、Human Handoff 或销售跟进阶段。
- **与现有客服系统可以并存。** v0.9.0 已能完成 AI Chat + Inquiry 闭环，但不包含实时人工客服；如果站点仍需要 Human Live Chat，可继续与 Tidio 等成熟客服系统并行。
- **Future Hardening**：Dedicated DB `1.2 → 1.3` Migration Validation Lab 与 Inquiry Repository DB Failure Injection Lab 已明确延后，不阻塞 v0.9.0，但适合作为后续 `v0.9.1 / v1.0.0` 的回归扩展。

---

## v1.0.0

### First Stable Release

目标：

> 第一款可以部署到真实 WordPress 企业网站进行长期使用和验证的版本。

---

---

# v0.2.0 已实现内容

仓库从纯设计阶段正式进入代码实验阶段。

当前新增：

```text
plugin/wp-ai-chat/
```

核心模块：

```text
AI Manager
AI Provider Interface
DeepSeek Provider
Admin Settings
Connection Test
Minimal AI Test
```

DeepSeek API Key 支持：

```text
wp-config.php
        ↓
WordPress Option
```

当前 Provider 测试采用：

```text
Model: deepseek-flash
Streaming: false
Thinking: disabled
```

并统一返回：

```text
content
provider
request_model
response_model
usage
finish_reason
elapsed_ms
status_code
```

完整版本设计、测试清单和 Acceptance Criteria：

```text
docs/versions/v0.2.0.md
```

版本变化记录：

```text
CHANGELOG.md
```

# 四十七、与 WP Live Chat Inquiry 的关系

目前：

# 保持独立。

原因：

- AI 架构仍然需要实验；
- 不影响已有稳定项目；
- 方便记录完整演进历史；
- Knowledge Core 可能具有独立价值；
- 最终产品结构现在还不应该提前锁死。

---

# 四十八、未来有三种可能

## 方案 A

保持两个插件：

```text
WP Live Chat
+
WP AI Chat
```

---

## 方案 B

最终合并：

```text
WP Live Chat
↓
WP Live Chat AI
```

---

## 方案 C

AI 项目成为：

```text
Reusable AI Core
```

然后：

```text
WP Live Chat
```

只是其中一个 Adapter / Integration。

现在：

> **不提前决定。**

让真实开发和使用结果给出答案。

---

# 四十九、生产部署与现有客服系统的关系

项目早期以 `Tidio` 承担正式 Live Chat / Human Support，WP AI Chat Lab 主要用于 Knowledge、Retrieval、Guard、DeepSeek 与 AI Architecture 实验。

v0.9.0 已经形成独立的：

```text
AI Chat
↓
Lead Trigger
↓
Inquiry Form
↓
Inquiry Admin Management
```

因此它已经可以在真实 WordPress 企业站进行受控部署与持续验证。

但当前版本**仍不包含实时 Human Live Chat / Human Handoff**。如果网站需要人工在线客服，可以继续与 Tidio 或其他成熟客服系统并行，不需要为了使用 WP AI Chat Lab 而强行替换现有客服能力。

---

# 五十、项目记录方式

仓库不仅保存代码，还需要保存：

```text
Why
Decision
Experiment
Result
Change
```

也就是说不仅记录：

> 做了什么。

还记录：

> 为什么这样做。

---

# 五十一、建议仓库结构

```text
wp-ai-chat-lab/
│
├── README.md
├── CHANGELOG.md
├── LICENSE
│
├── docs/
│   ├── architecture.md
│   ├── decisions.md
│   ├── competitors.md
│   └── roadmap.md
│
├── experiments/
│
└── plugin/
```

---

# 五十二、README 的角色

README：

> 当前完整认知。

---

# 五十三、CHANGELOG 的角色

记录：

```text
每个版本到底改变了什么
```

---

# 五十四、decisions.md 的角色

记录关键决策。

例如：

```text
为什么第一版不用 Vector？

为什么 Product 使用字段白名单？

为什么无 Knowledge 时不调用 DeepSeek？

为什么生产环境继续保留 Tidio？

为什么 Knowledge 与 Chat 解耦？
```

---

# 五十五、competitors.md

长期记录：

```text
Tidio
Rapls
Zorachat
ChatBudgie
MxChat
AxiaChat
其他 AI Chat Plugin
```

关注：

> 可以学习什么。

而不是：

> 谁的功能最多。

---

# 五十六、核心开发哲学

## 网站已有的数据，不重复录入。

## 能够本地完成的事情，不调用 AI。

## 没有可靠知识依据，不让 AI 自由发挥。

## 能够自动同步的数据，不依赖人工重复维护。

## AI 可以发现知识缺口，但正式知识必须经过人工确认。

## 人工接管以后，AI 必须停止。

## AI 的目标不是无限聊天，而是解决真实问题。

## 简单方案验证成功之前，不提前引入复杂技术。

## 成熟产品已经做好的事情，不盲目重复开发。

## 功能增加不能以牺牲长期维护性为代价。

---

# 五十七、项目最终可能形成什么

如果长期迭代成功：

```text
WordPress Content
        ↓
Website Knowledge
        ↓
Local Retrieval
        ↓
AI Guard
        ↓
AI Provider
        ↓
Customer Interaction
        ↓
Lead / Support / Action
```

它最终可能不仅是一款：

> AI Chat Plugin。

而是一层：

# WordPress AI Knowledge Infrastructure

让 WordPress 中已经存在的数据真正成为：

> **AI 能够安全、准确、可控制使用的网站知识。**

---

# 当前状态

```text
Version:
v0.9.0

Release:
Lead Capture & Inquiry Management — Stable Release

Plugin Code:
v0.9.0 Final Review Passed

DB Version:
1.3

Primary Provider:
DeepSeek

Knowledge Source:
Generic WordPress + Manual Supplement + Legacy / WEM Product structured enhancement

Retrieval:
Local First — validated

Grounding:
Strong / Medium / Weak / None + deterministic Gate — validated

Usage Guard:
Conversation / Visitor / Site Provider Call limits — validated

Public Chat:
REST + Simple Chat UI + Request Guard + Provider Failure Boundary — validated

Lead Capture:
manual / commercial_intent / no_answer / usage_blocked — validated

Inquiry:
Public REST + Validation + Honeypot + Rate Guard + wp_wpaic_inquiries — validated

Inquiry Admin:
Unread / Read + Search + Source Filter + Bulk Read/Unread + Trash Lifecycle — validated

Custom Database Tables:
3（Knowledge Store + Usage Counter + Inquiries）

Vector:
Not Used

Server-side Conversation History:
Not Used

Human Handoff / CRM:
Not Included

Next Direction:
Production observation → Future Hardening → v1.0.0
```

---

# 最后一句

这个项目暂时不追求：

> **做出功能最多的 AI Chatbot。**

而是希望通过长期迭代，逐步回答：

> **怎样才能让 WordPress 自己真正拥有一套简单、可靠、可维护、成本可控的 AI 能力。**

这也是 WP AI Chat Lab 从 v0.1.0 开始最重要的设计基线。

### v0.7.0 当前状态

```text
Stage 1 — Usage Store & Policy Foundation      Validation Passed
Stage 2 — Provider Boundary Integration        Validation Passed
Stage 3 — Calibration & Operational Validation Passed / Sealed

DB Version 1.1                                 Implemented
wpaic_usage_counter                            Implemented
Usage Context / Hashed Scope Keys              Implemented
Conversation / Visitor / Site Limits           Implemented
Fail Closed                                    Implemented
Atomic Reservation                             Implemented
Real Provider Boundary Integration             Implemented
Real Token Accounting                          Implemented
Operational Snapshot                            Implemented
Manual Lab Reset (selected exact context)       Implemented
Stage 3 Round 1 Real Validation                 Passed
Stage 3 Round 2 Real Validation                 Passed
Stage 3 Final Operational Validation            Passed
Stage 3 Seal                                    Completed
```

Stage 3 Round 1 已验证低额度边界、Local Day、Atomic BLOCK 与精确 Lab Reset。Round 2 继续使用 `Conversation 2 / Visitor 3 / Site 5`，测试入口已从抽象的 A–E Preset 优化为 1–6 个中文场景，用来验证跨 Conversation / Visitor 隔离和固定 Scope Precedence。

当前：**v0.7.0 Final Review Passed / Stable Release**。Stage 1、Stage 2、Stage 3 均已完成真实环境验收；本版本正式停止扩展，下一步进入 v0.8.0 的设计阶段。

#### v0.7.0 发布后的两个运维注意点

1. **Daily Limit 以 WordPress Timezone 为准。** Visitor / Site 的 `period_key` 使用 WordPress 本地日期。如果站点时区配置为 `+00:00`，每日额度就会按 UTC 日期切换；正式接入前应确认站点时区是否符合业务预期。
2. **v0.8.0 前台 Chat Integration 前需要做一次数据库并发硬化。** 当前真实环境已经验证 Atomic Reservation 的正常路径，但公共流量接入前应明确确认 Usage Counter 表使用支持事务 / 行锁的存储引擎，并进一步把底层数据库写入失败收紧为显式 fail-closed。这属于 v0.8.0 的前置硬化，不回改已封板的 v0.7.0 Stage 3。



---

# v0.8.0 Stage 1 — Public Chat Boundary Foundation

v0.8.0 开始把稳定的 AI Pipeline 接到匿名访客入口，但仍坚持：**Chat 只是入口，不重新实现 AI 逻辑。**

```text
Public REST → Chat Context → WPAIC_Grounded_Answer_Service
            → Retrieval → Grounding → Usage Guard → Provider
```

Stage 1 已加入：公开 `/wp-json/wpaic/v1/chat`、服务器 Conversation UUID、HttpOnly Visitor Cookie、Server-only Site Context、统一 Public Contract 与 Chat Boundary Playground；同时完成 Public Provider 前的 InnoDB / transaction / row-lock fail-closed 硬化。真实 WordPress 环境 7 个 Public Boundary 场景均已验证通过，Stage 1 正式封板。

Stage 1 没有新增数据库表，DB Version 仍为 `1.1`；也没有加入正式 Chat UI、聊天记录、Lead、Human Handoff、Agent、Streaming、Cost Guard、Embedding、Vector 或 RAG。详见 `docs/versions/v0.8.0.md`。

## v0.8.0 Stage 2 — Simple Chat UI Integration

Stage 2 已把 `simple-live-chat` 的视觉外壳迁入插件，但保持 **Chat UI 只是薄客户端**：

```text
simple-live-chat UI
        ↓
Public REST /wpaic/v1/chat
        ↓
Chat Context
        ↓
Grounded Answer Service
        ↓
Grounding → Usage Guard → Provider
```

原 Demo `replies{}` 已删除；Quick Questions 与输入框全部走真实 Public REST。Conversation 使用 `sessionStorage` 保持同一标签页连续性；Visitor 继续由服务器 HttpOnly Cookie 管理。请求中显示 Typing 并禁用重复发送；`answer / clarify / no_answer / blocked / error` 映射成前台消息气泡。

Stage 2 首轮前台预览发现并修复了一个初始化时序问题：WordPress 页脚脚本可能先于 Widget HTML 执行，导致 Launcher 可见但没有绑定点击事件。现在 `chat.js` 会等待 DOM Ready 后再查找并初始化 Widget，同时使用一次性初始化标记避免重复绑定。

正式 Widget 默认关闭，需要在永久保留的 **Chat UI Lab** 中显式启用。Stage 2 不新增 Conversation / Message 表，DB Version 仍为 `1.1`。

Stage 2 已完成真实前台验收并正式封板：Widget 打开/关闭、真实 `answer`、`clarify`、`no_answer`、`blocked`、新 Conversation、Visitor 跨会话保持、刷新后的 Conversation / Usage 连续性、重复发送保护、断网错误恢复、恢复网络后重试、慢网 Pending 状态以及手机端布局均符合预期。Stage 2 状态：**Validation Passed / Sealed**。

### Stage 3 — Public Hardening & Remaining Operational Boundaries

由于 Stage 2 已经在真实前台覆盖了原计划的一部分 Session / Resilience 场景，Stage 3 不再重复慢网、断网、重复发送和手机端验收，而只聚焦尚未完成的生产边界。

Stage 3 Round 1 已实现：

- 新增独立 `WPAIC_Public_Request_Guard`，位于 Public REST 与 Grounded Answer 之间。
- Request Guard 只限制公开请求频率，不改变 Usage Guard 的 Provider Call 语义。
- 固定 60 秒窗口，使用短期 Transient；只保存 Visitor / IP 哈希。
- 默认 Visitor = 20 请求/分钟；IP 默认关闭，避免反向代理 / Cloudflare 环境共享出口误伤。
- Rate Limit 返回 HTTP 429 + Public `error`，并带 `Retry-After`；前端不会永久锁死当前 Conversation。
- 新增永久 **Public Hardening Lab**，用于配置、观察和重置 Visitor / Site / Request Rate 测试状态。
- DB Version 仍为 `1.1`，新增数据表 = 0。

Round 1 已完成真实 Chat Widget 验收并通过：

- `Conversation=10 / Visitor=2 / Site=20` 时，前两次 Strong 正常 answer，第 3 次准确触发 Visitor Daily Limit；BLOCK 后 Visitor / Site 保持 `2 / 2` 与 `2 / 20`。
- `Conversation=10 / Visitor=10 / Site=2` 时，前两次 Strong 正常 answer，第 3 次准确触发 Site Daily Limit；BLOCK 后保持 Visitor `2 / 10`、Site `2 / 2`。
- Visitor Rate 临时设为 `3 / minute` 后，前三次 `dimension` 正常 `clarify`，第 4 次返回 HTTP 429 / `error`；约 60 秒后自动恢复。
- Rate Limit 测试中的 Weak 请求只增加 Public Request Rate，不增加 Provider Usage，证明 Request Guard 与 Usage Guard 相互隔离。

**Round 1 结论：Validation Passed。**

Round 2 已新增永久 **Provider Failure Lab**，不修改真实 API Key 或 DeepSeek Endpoint，而是为当前 Visitor 武装一个 180 秒、一次性的模拟 Transport Timeout。失败注入只在请求真正通过 Grounding 与 Usage Reservation、进入 AI Manager 时消费；Weak / Medium / None / Usage BLOCK 不会消费该测试状态。

Round 2 将验证：

1. 武装 Failure 后先发 `dimension`，仍正常 `clarify`，Failure 保持 Armed。
2. 再发 `CWC-610 dimension`，Usage Reservation 成功后在 Provider Boundary 模拟 timeout，Public Chat 返回 HTTP 503 / `error`，Widget 可恢复。
3. 失败尝试计入 Provider Call，但 Token 不增加；这是对真实 timeout 不确定性的保守计数语义。
4. Failure 为 one-shot，下一次 Strong 在不重新武装时应恢复真实 Provider `answer`，并只在成功响应后累计 Token。

Round 2 已完成真实 WordPress + 正式 Chat Widget 验收并通过：

- 武装 Provider Timeout 后先发送 `dimension`，正常返回 `clarify`；Failure 仍保持 `Armed`，Visitor / Site Usage 与 Token 均不增加。✅
- 在 Failure 仍处于有效期内立即发送 `CWC-610 dimension`，Strong 请求通过 Grounding 与 Usage Reservation 后命中模拟 Transport Timeout；Widget 正确显示临时不可用。✅
- Provider-bound 失败保守计入 1 次 Provider Call，但 Visitor / Site Token 均保持 `0`。✅
- Failure 为 one-shot，命中后自动回到 `Idle`；不重新武装再次发送同一 Strong，真实 Provider 恢复正常 `answer`。✅
- 恢复后的成功请求使 Visitor / Site Calls 继续累计到 2，Token 只在成功响应后增加（本次真实验证为 580）。✅
- Failure Arm 的 180 秒 TTL 行为也得到确认：若人工测试超过有效期，状态自动过期，后续请求走正常 Provider，不污染正式配置。✅

**Round 2 结论：Validation Passed。**

**Stage 3 结论：Round 1 + Round 2 全部通过，Validation Passed / Sealed。** Public Hardening Lab、Request Guard 设置、安全 Reset 与 Provider Failure Lab 全部长期保留，继续用于教学、诊断和回归测试。

## v0.8.0 Final Review — Passed / Stable Release

v0.8.0 已完成发布前最终复核，结论为 **Passed / Stable Release**：

- Stage 1 Public Chat Boundary、Stage 2 Simple Chat UI、Stage 3 Public Hardening 全部完成真实 WordPress / 正式 Widget 验收。
- Plugin Version = `0.8.0`；DB Version = `1.1`；新增数据表 = 0。
- Public REST 输入校验、Conversation UUID、HttpOnly Visitor Cookie、Server-only Site Context 与 Public Contract 均保持封板设计。
- Public Response 不暴露 Provider、Model、Token、Retrieval Score、Usage Hash 或内部 Reason Code。
- Public Request Guard 与 Provider Usage Guard 保持职责分离；Usage Counter 在 Public Provider Boundary 前强制检查 InnoDB / transaction prerequisite 并 fail closed。
- Provider Failure Lab 的 Arm / Clear 仅管理员可操作，并由 nonce 保护；正式 Provider 配置不会被测试工具改写。
- Chat Widget 默认关闭；启用后继续使用已经验证的 Public REST，不拥有独立 AI 逻辑。
- PHP / JavaScript 语法、仓库包 / 插件包结构、敏感信息与临时文件扫描均通过。
- `Chat Boundary / Chat UI / Public Hardening / Grounded AI / Usage Guard / Retrieval / Knowledge` 等 Lab 页面继续永久保留。

已知边界保持明确：Public Request Guard 是 WordPress 应用层 best-effort 保护，高流量生产站仍建议使用 Cloudflare / WAF；IP Rate 默认关闭，只有确认 `REMOTE_ADDR` 代表真实访客 IP 时再启用；Visitor / Site Daily Limit 按 WordPress Local Day 计算；v0.8.0 不保存服务器端聊天历史，也不包含 Lead / Human Handoff / Agent / RAG / Vector / Cost Guard。

**v0.8.0 正式封板，不再追加功能。**

## Lab / Playground 永久保留原则

WP AI Chat Lab 中已经建立的后台实验与验收页面将长期保留，包括 Knowledge、Retrieval、Grounded AI、Usage Guard、Chat Boundary 等 Playground / Validation 工具。

它们不是开发完成后删除的临时页面，而是项目的一部分，长期承担：

1. **教学与理解**：可以直接观察每一层输入、决策和边界。
2. **诊断与排错**：正式 Chat 出现异常时，可逐层定位 Retrieval、Grounding、Usage、Provider 或 Public Boundary。
3. **版本回归**：后续 v0.8.x / v0.9.x / v1.x 升级后可直接重跑关键场景，确认旧能力没有回归。

因此后续版本的原则是：**可以整理、折叠、优化这些 Lab 页面，但不因正式功能上线而删除其核心测试能力。** 详细约定见 `docs/lab-validation-policy.md`。

# v0.9.0 — Lead Capture & Inquiry Management

`v0.9.0 — Lead Capture & Inquiry Management` 已完成 Stage 1 Lead Capture、Stage 2 Inquiry Admin Management 与 Stage 3 Operational Validation，并通过 Final Review，现作为正式 Stable Release。当前 Plugin Version 为 `0.9.0`，DB Version 为 `1.3`。

已封板的产品原则与能力：

- Chat 先解决问题，Lead CTA 只在自然业务节点出现；
- `commercial_intent` 关键词全部由后台管理员控制，默认词只是起点；
- 关键词支持增删改、Unicode / 中文、空列表关闭触发、手动恢复默认；
- 第一版 Trigger 使用确定性规则，不调用 AI Lead Scoring；
- 固定优先级：`manual → commercial_intent → no_answer → usage_blocked`；
- `clarify` / `error` 是自动 Lead Hard Stop，但用户主动 `manual` 永远允许；
- Conversation Limit 只提供 secondary Lead CTA；Visitor / Site Daily Limit 使用 primary fallback；
- Latin 词边界已验证：`order` 不会误命中 `border`；
- Lead Trigger Lab 永久保留，用于教学、诊断和回归测试。

Round 1 仍不创建 `wp_wpaic_inquiries`，不新增 Public Inquiry REST，不保存个人信息，也不修改正式 Chat Widget。

当前状态：**Stage 1 / Stage 2 / Stage 3 全部 Validation Passed / Sealed；v0.9.0 Final Review Passed / Stable Release。** Stage 2 始终直接读取 `wp_wpaic_inquiries`，不复制为 CPT，也不扩展成 CRM。完整架构与验收记录见 `docs/versions/v0.9.0.md`.


- Stage 2 Round 1 UX2：询盘管理页取消 1180px 最大宽度，使用后台可用宽度；列表切换为 WordPress 原生 `wp-list-table / widefat / striped / table-view-list` 样式与原生 `tablenav` 分页外观；默认每页 10 条，保留详情页 20px 卡片间距。

- Stage 2 Round 2：Inquiry 状态简化为未读/已读；打开详情自动标记已读；新增全部/未读/已读/回收站筛选、软删除、恢复、永久删除与紧凑分页；DB Version 升至 1.3。
- Stage 2 Round 2 状态：**Validation Passed / Sealed**。已真实验证旧状态迁移、查看详情自动已读、未读/已读筛选、回收站、恢复、永久删除与分页；下一步进入 Round 3。
- Stage 2 Round 3：新增批量标记已读/未读、来源筛选、Name / Email / Company / Message 搜索与清空全部回收站；筛选/搜索可与分页组合，DB Version 保持 1.3。
- Stage 2 Round 3 状态：**Validation Passed / Sealed**。真实后台验收确认批量已读/未读、来源筛选、搜索、组合筛选/分页、回收站清空均符合预期。
- Stage 2 结论：**Round 1 + Round 2 + Round 3 已完成并封板**；Inquiry Admin 功能到此停止扩展。
- Stage 3 Round 1：后台导航与永久 Lab 间距统一完成并封板。
- Stage 3 Round 2：Read-State Nonce、非法 ID、Stored XSS、Source URL 伪造、Unicode / 特殊字符搜索、组合筛选分页、Bulk 边界与 Trash/Restore/Permanent Delete 重复操作均通过真实 WordPress 验收并封板。
- Final Review：Plugin `0.9.0` / DB `1.3`、PHP / JS、ZIP 结构、敏感信息、永久 Lab 状态与正式仓库/插件包一致性全部通过。
- Future Hardening：Dedicated DB 1.2 → 1.3 Migration Validation Lab 与 Inquiry Repository DB Failure Injection Lab 延后，不阻塞 v0.9.0 Release。
