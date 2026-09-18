# Changelog

## v0.9.0 — Stage 1 Round 1 Lead Trigger Policy / Validation Passed / Sealed

> Real WordPress validation passed. Stage seal only; this is not a Release.

- Plugin Version advanced to `0.9.0`; DB Version remains `1.1`.
- Added administrator-controlled `wpaic_lead_commercial_keywords` Option.
- Initial installation/upgrade writes the default commercial keyword baseline only when the Option does not exist.
- An intentionally empty saved keyword list remains empty and disables `commercial_intent`; defaults are never silently restored.
- Added explicit administrator action to restore default commercial-intent keywords.
- Added `WPAIC_Lead_Trigger_Policy` as a deterministic, provider-independent policy boundary.
- Added trigger outputs for `manual`, `commercial_intent`, `no_answer`, and `usage_blocked`.
- Fixed priority contract: `manual → commercial_intent → no_answer → usage_blocked`.
- `clarify` and `error` are automatic-lead hard-stop states; manual user action remains allowed.
- Added Unicode keyword support and Latin case-insensitive phrase matching.
- ASCII matching uses token boundaries so a keyword such as `order` does not match text such as `border`.
- Added permanent `Lead Trigger Lab` with editable keywords, restore-default control, and a server-side Playground that calls the real policy class.
- Round 1 does not create `wp_wpaic_inquiries`, Public Inquiry REST, Inquiry Repository, personal-data capture, or Chat CTA integration.
- No AI Lead Scoring call was added.
- Fixed the missing Settings API sanitize callback that caused a WordPress Critical Error on the first keyword save; the admin save path now reuses the same keyword normalization contract as the runtime policy.
- Real WordPress validation passed for ordinary answer No Offer, default English commercial keyword, administrator-added Chinese keyword, deletion, empty-list disable semantics, restore defaults, `no_answer`, all three Usage Block variants, `clarify` / `error` hard stops, trigger priority, `manual` highest priority, and `order` vs `border` word-boundary protection.
- Stage 1 Round 1 is now formally **Validation Passed / Sealed**.
- Next: Stage 1 Round 2 — Inquiry Capture Foundation (database / repository / service / public REST / validation / honeypot / submission rate). Formal Chat inline form integration remains deferred until those boundaries pass validation.

## v0.9.0 — Lead Capture & Inquiry Management / Planning & Architecture Baseline

> Architecture baseline retained for reference. Stage 1 Round 1 has now passed real validation and is sealed; current stable release remains v0.8.0 until v0.9.0 Final Review.

- Defined v0.9.0 around two goals: natural Lead Capture from Chat and WordPress Inquiry Management.
- Defined Lead Trigger Policy as a separate boundary between Chat Response and Inquiry Capture.
- First trigger types: `manual`, `commercial_intent`, `no_answer`, `usage_blocked`.
- Confirmed deterministic trigger rules for the first version; no additional AI Lead Scoring call.
- Commercial Intent keywords are **administrator-configurable**, not hard-coded.
- Planned default keyword set is only an initial baseline; administrators can add, remove, edit, save an empty list to disable keyword triggering, or explicitly restore defaults.
- Planned keyword storage uses WordPress Options rather than a dedicated keyword table.
- Planned minimal Inline Inquiry Form: Name / Email / Message required; Company / Phone optional.
- Planned independent `POST /wp-json/wpaic/v1/inquiry` Public Boundary.
- Planned dedicated `wp_wpaic_inquiries` operational table with Service / Repository separation; exact DB Version change is deferred until Stage 1 implementation.
- Planned four Inquiry statuses: `new`, `contacted`, `closed`, `spam`.
- Planned minimal anti-abuse foundation: honeypot + Visitor submission rate + server-side validation; Turnstile deferred until real spam requires it.
- Explicitly excluded real-time Human Handoff, Agent Inbox, CRM Pipeline, AI Lead Scoring, full Conversation persistence, RAG, Vector Database, and Agent features from v0.9.0.
- Confirmed all future Lead / Inquiry Lab and Playground pages will remain permanently available for teaching, diagnostics, and regression testing.
- Added `docs/versions/v0.9.0.md` as the implementation contract before Stage 1 coding begins.

## v0.8.0 — Chat Integration / Final Review (Passed / Stable Release)

- Completed final release review after Stage 1 / Stage 2 / Stage 3 real WordPress validation.
- Confirmed Plugin Version `0.8.0`, DB Version `1.1`, and no new database tables.
- Confirmed Public REST input validation, server-owned Visitor / Site identity, UUID v4 Conversation validation, and stable `answer / clarify / no_answer / blocked / error` contract.
- Confirmed Public Response does not expose Provider, Model, Token, Retrieval Score, Usage Hash, or internal Reason Code.
- Confirmed Public Request Guard remains separate from exact Provider-call Usage Guard.
- Confirmed Usage Counter Public preflight requires InnoDB transactional storage and database failures remain fail-closed.
- Confirmed Provider Failure Lab controls are administrator-only and nonce-protected; no API key or Provider endpoint mutation is used for failure validation.
- Confirmed Chat Widget defaults to disabled and remains a thin client over the verified Public Chat Boundary.
- PHP / JavaScript syntax, package structure, secret scan, hard-coded test-domain scan, and temporary-file scan passed.
- Updated stale Stage 2 / Stage 3 Lab status copy so all permanent Lab pages reflect the sealed v0.8.0 state.
- All Lab / Playground / Validation pages remain permanently available for teaching, diagnostics, and regression testing.
- Production boundary remains explicit: WordPress transient Request Guard is best-effort; Cloudflare / WAF remains recommended for high-volume edge abuse protection; IP Rate stays disabled by default until `REMOTE_ADDR` is confirmed trustworthy.
- v0.8.0 is now formally **Final Review Passed / Stable Release**.


## v0.8.0 — Chat Integration / Stage 3 Public Hardening (Validation Passed / Sealed)

### Round 1 — Validation Passed

- Added `WPAIC_Public_Request_Guard` before the Grounded Answer pipeline and kept it separate from Provider-call Usage Guard.
- Real Widget validation passed for Visitor Daily Limit, Site Daily Limit, HTTP 429 Request Rate Limit, 60-second automatic recovery, and Request Rate / AI Usage isolation.
- Visitor Limit test: `Conversation=10 / Visitor=2 / Site=20`; first two Strong requests answered, third was blocked by Visitor Daily, with counters remaining `Visitor 2/2` and `Site 2/20`.
- Site Limit test: `Conversation=10 / Visitor=10 / Site=2`; first two Strong requests answered, third was blocked by Site Daily, with counters remaining `Visitor 2/10` and `Site 2/2`.
- Request Rate test: Visitor rate temporarily set to 3/minute; first three `dimension` requests returned `clarify`, fourth returned HTTP 429 / public `error`, and requests recovered automatically after the fixed window.
- Weak rate-limit test requests did not increase Provider Usage, confirming Request Guard / Usage Guard isolation.

### Round 2 — Provider Failure Boundary (Validation Passed)

- Added permanent `WPAIC_Provider_Failure_Lab` for admin-controlled, one-shot Provider Failure Boundary validation.
- Failure arm is scoped to the current hashed Visitor identity, expires after 180 seconds, and is never exposed as a public toggle.
- Added neutral `wpaic_ai_manager_pre_chat_result` short-circuit point in AI Manager; normal requests receive `null` and continue unchanged to the real provider.
- Provider Failure injection is attached only around a Public Chat request and is consumed only if Grounding + Usage Guard actually reach AI Manager. Weak / Medium / None / Usage BLOCK paths leave it armed.
- Simulated Transport Timeout does not modify the DeepSeek API key, endpoint, model, or provider transport implementation.
- Provider errors continue through the existing public fail-safe contract as HTTP 503 / `error`; the front-end Chat JS is unchanged.
- Public Hardening Lab now shows one-shot Failure status plus Visitor/Site Token counters, and includes arm / clear controls protected by `manage_options` + nonce.
- Documented conservative accounting semantics: a Provider-bound failure keeps the already-reserved Provider Call, while token counters increase only after a trusted successful Provider response.
- Added no new database tables; DB Version remains `1.1`.
- Real WordPress + formal Chat Widget validation passed for the complete Provider Failure Boundary.
- Armed Failure survived a Weak `dimension` request: public `clarify`, Failure remained Armed, and Provider Usage / Tokens stayed unchanged.
- Immediate Strong `CWC-610 dimension` consumed the one-shot Failure after Grounding + Usage Reservation and returned the existing public HTTP 503 / `error` path.
- Provider-bound failure incremented Visitor / Site Provider Call counters by 1 while both token counters remained 0.
- Failure automatically returned to Idle after one-shot consumption.
- A subsequent Strong request without re-arming recovered to the real Provider `answer`; Calls advanced again and tokens increased only on that successful response (580 tokens in the validation run).
- The 180-second TTL was also confirmed: an expired arm falls away safely and leaves the normal Provider path unchanged.
- Stage 3 Round 1 + Round 2 are now Validation Passed / Sealed. All Public Hardening Lab controls remain permanently available for diagnostics and regression testing.
- Next: v0.8.0 Final Review.


## v0.8.0 — Chat Integration / Stage 2 Simple Chat UI Integration

- Integrated the `simple-live-chat` visual baseline as the front-end Chat Widget.
- Removed the demo local `replies{}` engine; every real message now uses the verified Public Chat REST boundary.
- Added `public/` Widget renderer, isolated `cw-` CSS, Vanilla JS client, and front-end view.
- Added administrator `Chat UI Lab` as a permanent learning / diagnostics / regression page.
- Front-end Widget defaults to disabled and must be explicitly enabled from Chat UI Lab.
- Added `sessionStorage` Conversation continuity and local transcript restoration for the current browser tab.
- Visitor identity remains server-owned through the Stage 1 HttpOnly Cookie; Site identity remains server-only.
- Added Typing state and client-side duplicate-submit prevention while a request is in flight.
- Added user-facing rendering for `answer / clarify / no_answer / blocked / error`.
- Added a minimal New Conversation control that clears only Conversation/session UI state, not Visitor identity.
- Fixed Stage 2 front-end initialization timing: the Widget now binds after DOM readiness, so `wp_footer` output order cannot leave the visible launcher without click handlers.
- Preserved all existing Lab / Playground / Validation pages.
- No Conversation / Message table was added; DB Version remains `1.1`.
- Real front-end WordPress validation completed successfully.
- Verified Widget launcher / panel open-close behavior.
- Verified real `answer`, `clarify`, `no_answer`, and `blocked` rendering through the Stage 1 Public REST boundary.
- Verified New Conversation clears Conversation/session UI state while retaining Visitor identity.
- Verified same-tab refresh restores Conversation/session transcript and preserves Usage continuity.
- Verified duplicate-submit protection: rapid clicks / Enter presses produce only one active request and one Usage reservation.
- Verified offline failure displays a friendly error, exits the busy state, and allows a successful retry after network recovery.
- Verified slow-network pending behavior, duplicate-submit guard while pending, and successful completion.
- Verified mobile layout and core interactions without overflow or unusable controls.
- Stage 2 status: **Validation Passed / Sealed**.


## v0.8.0 — Chat Integration / Stage 1 Public Chat Boundary Foundation

- Plugin Version advanced to `0.8.0`; DB Version remains `1.1` with no new tables.
- Added public `POST /wp-json/wpaic/v1/chat` boundary.
- Added server-owned Conversation UUID, HttpOnly Visitor Cookie, and server-derived Site context.
- Added public contract: `answer / clarify / no_answer / blocked / error`.
- Public output hides retrieval/provider/model/token/hash/internal diagnostics.
- Added administrator `Chat Boundary` Playground calling the real public REST endpoint.
- Added InnoDB transaction/row-lock preflight and fail-closed handling before Public Provider calls.
- Tightened transaction-critical wpdb failures to rollback + `usage_guard_unavailable`.
- Reused `WPAIC_Grounded_Answer_Service`; no parallel Chat AI pipeline was added.
- `simple-live-chat` remains Stage 2 UI source; `wp-live-chat-inquiry` remains architecture reference only.
- No Chat history, Message table, Lead, Human Handoff, Agent, Streaming, Cost Guard, Embedding, Vector, or RAG.
- Real WordPress validation completed: all 7 Public Chat Boundary scenarios passed.
- Verified first anonymous context creation, conversation continuity, `clarify`, `no_answer`, Usage `blocked`, invalid Conversation HTTP 400, and public diagnostic hiding.
- Stage 1 status: **Validation Passed / Sealed**.
- Added a permanent Lab policy: existing Playground / Validation pages remain part of the project for learning, diagnostics, and regression testing even after production features are completed.


## v0.7.0 — Usage Guard (2026-09-17)

### Final Review — Passed / Stable Release

- Stage 1 — Usage Store & Policy Foundation：真实 WordPress 验收通过。
- Stage 2 — Provider Boundary Integration：真实 DeepSeek Provider Boundary 验收通过。
- Stage 3 — Limit Calibration & Operational Validation：Round 1、Round 2 与 Final Operational Validation 全部通过并封板。
- 最终真实链路确认：`Grounding → Usage Guard → Provider → Token Accounting`。
- Weak / Medium / None 均在 Grounding 层阻断，不消耗 Usage，不调用 Provider，Request Token = 0。
- Strong + quota available 才执行真实 Provider Call；Conversation 达限后稳定返回 `conversation_limit_reached`，Provider 不调用。
- Final Validation 两次成功调用累计 Prompt 1114 / Completion 46 / Total 1160，第三次被 Usage Guard 阻断后计数与累计 Token 均保持不变。
- Plugin Version / `WPAIC_VERSION`：`0.7.0`。
- DB Version：`1.1`；自定义表保持 2 张：Knowledge Store + Usage Counter。
- PHP 48 个文件语法检查通过；Admin JavaScript 语法检查通过。
- 完整仓库与 WordPress 安装包内容一致性检查通过；未发现打包泄露的 API Key / Bearer Secret。
- 管理后台 AJAX 继续受 `manage_options` + nonce 保护。
- v0.7.0 不包含 Chat UI、Lead、Human Handoff、Agent、Cost Guard、Embedding、Vector 或 RAG。
- Final Review 结论：**Passed / Stable Release**。
- 运维注意：Visitor / Site Daily Period 跟随 WordPress Timezone；正式前台接入前应确认站点时区符合业务预期。
- v0.8.0 前置硬化：在公共 Chat 流量接入前确认 Usage Counter 的事务 / 行锁能力，并进一步收紧底层数据库写入失败的 fail-closed 行为。

## v0.7.0 — Stage 3 Limit Calibration & Operational Validation (Validation Passed / Sealed)

- Kept DB Version at `1.1`; no schema change.
- Kept the existing three Usage Guard scopes only: Conversation, Visitor Daily, Site Daily.
- Added Stage 3 Operational Snapshot with WordPress timezone, local time, daily period key, and fixed scope precedence.
- Added administrator-only Manual Lab Reset for selected current-context counters.
- Reset is intentionally narrow: Conversation deletes only its lifetime row; Visitor / Site delete only the current WordPress-local-day row for the exact hashed test key.
- No table-wide truncate or global quota reset was added.
- Usage Guard diagnostics now show Provider Calls plus Prompt / Completion / Total Token counters for each scope.
- Added the Stage 3 calibration matrix directly to the Usage Guard Playground.
- Stage 3 Round 1 real WordPress validation passed: `2 / 3 / 5` boundary calibration, atomic block behavior, Conversation-only reset isolation, and WordPress Local Day diagnostics all matched expectations.
- Round 2 initially used A–E Context Presets; real usage showed that the labels were too abstract for a learning-oriented Lab.
- Refined Round 2 into six explicit Chinese test scenarios while keeping the same Context Keys and the same real `evaluate()` / `reserve()` backend paths.
- Added plain-language Scope explanations: Conversation = current chat, Visitor = same visitor today, Site = whole site today.
- Each scenario now auto-fills its test Keys and explains “what changed / what to do / expected result”.
- The fixed Round 2 sequence still reaches `C=2 / V=2 / S=5`, then verifies Conversation-over-Site, Visitor-over-Site, and Site-only blocking in the real reservation path.
- Recommended temporary validation limits remain `2 / 3 / 5`; production defaults are not changed before real calibration.
- Grounded AI Playground labels were advanced to Stage 3 Operational Validation while preserving the verified Stage 2 Provider Boundary pipeline.
- No Chat UI, Lead, Human Handoff, Agent, Cost Guard, Embedding, Vector, or RAG capability was added.
- Stage 3 Round 2 real WordPress validation passed: all six scenario-driven checks matched the expected counters and block reasons.
- Confirmed Conversation isolation, Visitor isolation, fixed `Conversation → Visitor → Site` precedence, and atomic no-increment behavior on blocked paths.
- Added a six-step Final Operational Validation guide directly to Grounded AI Playground.
- Added dedicated Final Validation Context Keys so the last regression pass does not reuse Round 1 / Round 2 counters.
- Added one-click Final Validation counter reset using the existing administrator-only narrow reset path; no global reset capability was added.
- Final sequence rechecks Strong real Provider calls, Weak / Medium clarify paths, None no-answer path, token accounting, and Strong-over-quota Provider blocking.
- Final scenario buttons only prefill the known test question and context; they never auto-call the Provider.
- Refined the Stage 3 validation UX after real review: the already-passed Round 2 tools are now explicitly marked `Passed` and collapsed as historical retest tools on the Usage Guard page.
- Reworked Final Operational Validation into six plain-language user scenarios instead of Strong / Weak / Medium / None-first labels.
- Each final scenario now explains `what happened / what to do / expected result`, while Candidate Limit, Top K, and Usage Context are moved into an advanced-details section.
- Final Validation logic, Provider Boundary, Usage Guard, database schema, and dedicated Final Validation Keys are unchanged.
- Final Operational Validation passed in the real WordPress + DeepSeek environment; all six plain-language regression scenarios matched expectations.
- Strong request #1: Usage ALLOW, AI Called Yes, calls `1 / 1 / 1`, Prompt 557 / Completion 23 / Total 580.
- Weak, Medium, and None paths all skipped Usage Guard, did not call the Provider, produced zero request tokens, and left counters unchanged.
- Strong request #2: Usage ALLOW, AI Called Yes, calls `2 / 2 / 2`, cumulative Prompt 1114 / Completion 46 / Total 1160.
- Strong request #3: `conversation_limit_reached`, AI Called No, request Token Usage 0, and all scope counters remained `2 / 2 / 2`.
- Confirmed the final boundary: Grounding block consumes no Usage; Usage block never crosses the Provider Boundary; only Strong + quota available triggers a real Provider call.
- Stage 3 is now formally `Validation Passed / Sealed`; no further Stage 3 features will be added.
- Next step: v0.7.0 Final Review. Stage seal is Commit-only; no Release is created at this point.

## v0.7.0 — Stage 2 Provider Boundary Integration (Validation Passed)

- Connected the verified Usage Guard to `WPAIC_Grounded_Answer_Service`.
- Added Usage Context to the real Grounded AI Playground.
- Usage Guard reservation now runs immediately before the AI Manager / Provider boundary.
- `clarify / no_answer` paths skip Usage Guard and remain zero-token.
- Usage-blocked `allow_answer` paths do not call the Provider and return deterministic `usage_block` output.
- Provider configuration is checked before reservation so a missing API key does not consume quota.
- Added real Provider token accounting after successful responses.
- Prompt / Completion / Total Tokens are accumulated across enabled Conversation / Visitor / Site scopes.
- Provider Call reservation remains consumed when an external Provider attempt fails; no token values are guessed on failure.
- Added Grounded AI Usage Guard diagnostics for Decision / Reason / Calls / Remaining / Token counters.
- Kept the standalone Usage Guard simulation playground for isolated policy testing.
- Stage 2 completed real WordPress validation and was formally sealed.
- Strong `CWC-610 dimension` request confirmed Usage ALLOW / reservation before Provider call, real DeepSeek answer, and real token accounting.
- Second identical request confirmed provider-call and token counters accumulate rather than overwrite.
- Third identical request confirmed `conversation_limit_reached`, `AI Called = No`, request Token Usage = 0, and Visitor / Site counters unchanged.
- `dimension` (Weak), `CWC-610 warranty` (Medium / Partial Evidence), and `ZXQ-99999-NOMATCH` (None) all confirmed Grounding blocks before Usage Guard reservation, with zero Usage consumption and zero Provider calls.
- Stage 2 status: Validation Passed; ready for Stage 3 Limit Calibration & Operational Validation.

## v0.7.0 — Stage 1 Usage Store & Policy Foundation (Validation Passed)

- Plugin development version advanced to `0.7.0`.
- DB schema advanced from `1.0` to `1.1`.
- Added `wp_wpaic_usage_counter` as the second custom table.
- Added `WPAIC_Usage_Context` with HMAC-SHA256 scope-key hashing.
- Added `WPAIC_Usage_Counter_Repository`.
- Added `WPAIC_Usage_Guard`.
- Added Conversation lifetime, Visitor daily, and Site daily Provider Call limits.
- Added `0 = unlimited/disabled` semantics per scope.
- Added fail-closed handling for missing enabled-scope context.
- Added atomic multi-scope Provider Call reservation simulation using a short DB transaction.
- Added Usage Guard admin settings and diagnostics playground.
- Added explicit test keys `lab-conversation-1`, `lab-visitor-1`, and `site`.
- Stage 1 simulation increments Usage counters but does **not** call DeepSeek and records zero tokens.
- Real Provider Boundary integration remains deferred to Stage 2.
- Stage 1 completed real WordPress validation and was formally sealed.
- Read-only `evaluate` confirmed that counters do not change.
- Successful reservation confirmed atomic +1 across Conversation / Visitor / Site enabled scopes.
- Conversation lifetime limit confirmed with `conversation_limit_reached`.
- Visitor daily limit confirmed with `visitor_daily_limit_reached` by keeping the same visitor key across new conversation keys.
- Site daily limit confirmed with `site_daily_limit_reached` by keeping the same site key across new conversation / visitor keys.
- Blocked reservations confirmed to leave all non-blocking scopes unchanged.
- Changing conversation key resets only Conversation scope while Visitor / Site counters persist.
- Missing enabled-scope context confirmed fail-closed with `missing_usage_context`.
- Saved limit values were confirmed to be read by Guard policy immediately after save/refresh.
- Scope-key hashes remained stable for the same raw key and differed for different keys.
- Stage 1 status: Validation Passed; ready for Stage 2 Provider Boundary Integration.

## v0.7.0 — Usage Guard (Design)

### Design

- 完成 `docs/versions/v0.7.0.md` 第一版设计合同。
- 明确核心原则：`Grounded ≠ Unlimited`，可靠 Evidence 只解决“能不能回答”，Usage Guard 再解决“有没有额度调用 AI”。
- 设计三层 Provider Call Scope：Conversation AI Call Limit、Visitor Daily AI Call Limit、Site Daily AI Call Limit。
- 默认设计基线：Conversation 10 / lifetime、Visitor 20 / day、Site 200 / day，`0` 表示该 Scope 不启用限制。
- 明确 Usage Guard 统计 Provider Call，不统计普通 Message / Retrieval / clarify / no_answer。
- 设计 `WPAIC_Usage_Context / WPAIC_Usage_Counter_Repository / WPAIC_Usage_Guard`。
- 设计新增 `{$wpdb->prefix}wpaic_usage_counter` 单表，DB Version 从 `1.0` 提升到 `1.1`。
- Usage Counter 记录 Provider Calls 与 Prompt / Completion / Total Tokens，但 v0.7.0 不做 Monthly Token / Cost Budget。
- Visitor / Conversation Scope Key 持久化前采用 Hash，不使用 IP 作为 Visitor ID。
- 启用 Scope 但缺少对应 Context 时 Fail Closed：`missing_usage_context`。
- 设计稳定 Reason Codes：`within_limits / conversation_limit_reached / visitor_daily_limit_reached / site_daily_limit_reached / missing_usage_context / usage_guard_unavailable`。
- 设计 Provider Boundary 前的原子 `reserve_provider_call()`；任意 Scope 超限则阻断 AI。
- Provider Call Attempt 一旦真正发生即占用 Call Counter；成功返回后再提交真实 Token Usage。
- Grounding Gate 先于 Usage Guard，Weak / Medium / None 不消耗 AI Quota。
- v0.7.0 仍不做 Conversation / Message Persistence、Front-end Chat、Lead、Human Handoff、Cost Guard、Chunk / Embedding / Vector / RAG。
- 开发拆分为 Stage 1 Usage Store & Policy Foundation、Stage 2 Provider Boundary Integration、Stage 3 Limit Calibration & Operational Validation。
- Design Milestone 发布时插件功能代码仍保持 v0.6.0 Stable。

---

## v0.6.0 — Grounded AI (2026-09-17)

### Final Review

- Stage 1 Grounding Gate Foundation、Stage 2 Evidence Pack & Prompt Builder、Stage 3 Grounded Answer 均已通过真实 WordPress 环境验证。
- Strong / Medium / Weak / None 四类 Retrieval 状态与 allow_answer / clarify / no_answer 应用层决策完成闭环验证。
- 单来源 `CWC-610 dimension` 与多来源 `minimum order quantity` 均完成真实 DeepSeek Grounded Answer 验证。
- Weak / Medium / None 路径继续保持 `AI Called = No / Token Usage = 0`。
- Source Trace、Provider / Model、Token Usage 与 Elapsed Diagnostics 验证通过。
- v0.6.0 Final Review 通过并作为 Stable Release 发布。

### Stage 3 — Grounded Answer

- 新增 `WPAIC_Grounded_Answer_Service`，统一编排 Retrieval → Gate → Evidence → Prompt → AI Manager → Grounded Answer。
- `clarify / no_answer` 在 Service 内直接返回 deterministic result，不构建 Provider Request，不产生 Token。
- 只有 `allow_answer / allow_ai=true` 才允许越过 Provider Boundary。
- Grounded Answer 继续只通过 `WPAIC_AI_Manager` 调用当前 Provider，不直接依赖 `WPAIC_DeepSeek_Provider`，不自行读取 API Key。
- 默认 AI 参数：`max_tokens = 640 / temperature = 0.1`，提供 `wpaic_grounded_answer_ai_options` Filter。
- 新增统一 Grounded Answer Result：`answer / source_trace / provider / model / usage / finish_reason / provider_elapsed_ms / elapsed_ms / ai_called`。
- Source Trace 由应用层根据 Evidence Pack 生成，模型不能决定真实 Source ID / Title / URL。
- Grounded AI Playground 升级到 Stage 3：`allow_answer` 会产生真实 Provider 调用；`clarify / no_answer` 继续保持 `AI Called = No / Token Usage = 0`。
- Playground 新增 Grounded Answer、Source Trace、Provider / Model、Prompt / Completion / Total Tokens、Provider / Pipeline Elapsed 诊断。
- Prompt Preview 与 Evidence Pack 继续保留，方便逐层核对实际发送给 Provider 的输入。
- Provider 未配置 API Key 时，allow_answer 路径明确返回配置错误，不会静默退化为自由回答。
- 不新增数据库表，不新增 Answer / Prompt / Usage Log；仍限定 Single-turn / Single-intent QA。
- 不做 Conversation / Front-end Chat / Lead / Human Handoff / Chunk / Embedding / Vector / RAG。
- PHP / JavaScript 静态语法检查通过。
- Stage 3 已完成真实 WordPress 环境验证并正式封板。
- `CWC-610 dimension`：Gate = `allow_answer`，真实调用 DeepSeek / `deepseek-flash`；Prompt 557 Tokens、Completion 23、Total 580；答案正确返回 `610*9mm [S1]`，Source Trace 指向 `wordpress_post_2413`。
- `dimension`：Weak → `clarify`，返回 deterministic clarification；`AI Called = No / Total Tokens = 0`。
- `CWC-610 warranty`：Medium Partial Evidence → `clarify`；`AI Called = No / Total Tokens = 0`，未因产品型号强匹配而猜测 warranty。
- `ZXQ-99999-NOMATCH`：None → `no_answer`；`AI Called = No / Total Tokens = 0`。
- `minimum order quantity`：S1 + S2 多来源 Evidence 真实调用通过；DeepSeek 正确说明 Evidence 未提供具体 MOQ 数值，并引用 `[S1][S2]`；Prompt 301、Completion 68、Total 369。
- Source Trace 在单来源与多来源真实回答路径均正确由应用层输出。
- Stage 3 当前状态：Validation Passed / Ready for v0.6.0 Final Review。

### Stage 2 — Evidence Pack & Prompt Builder

- 新增 `WPAIC_Evidence_Pack_Builder`：只在 Grounding Gate 返回 `allow_answer / allow_ai=true` 后构建 Evidence。
- Evidence Pack 默认最多 3 个 Source；每 Source 最多 2400 字符；总 Evidence 最多 6000 字符。
- Rank #1 固定作为 Primary Evidence。
- Supporting Evidence 默认要求 Score ≥ 18 且 Coverage ≥ 75%，避免普通 Recall Candidate 污染 Prompt。
- Evidence 内容优先级：Structured Data → Excerpt → Relevant Content Snippet。
- Evidence Source 使用稳定 `S1 / S2 / S3` ID，并保留应用层 Source ID / Title / URL / Hash。
- 新增 `WPAIC_Grounded_Prompt_Builder`，生成 provider-agnostic System Prompt 与 User Prompt Preview。
- Prompt 明确规定 Evidence 仅作为不可信 Data，Evidence 内部指令文本不能覆盖 System Rule。
- Prompt 要求只基于 Evidence 回答；Evidence 不支持的事实必须明确说明不足，不允许补充猜测。
- Source Trace 由应用层提供，模型不得自行生成来源 ID 或 URL。
- Grounded AI Playground 新增 Evidence Pack、Evidence Budget、Prompt Preview 诊断。
- Gate 为 `clarify / no_answer` 时 Evidence / Prompt 明确 `Skipped`。
- Stage 2 继续强制 `AI Called = false / Token Usage = 0`，不调用 DeepSeek。
- 不新增数据库表，不引入 Chunk / Embedding / Vector / RAG。
- PHP / JavaScript 静态语法检查通过。
- Stage 2 已完成真实 WordPress 环境验证并正式封板。
- `CWC-610 dimension`：Gate = `allow_answer`，Evidence Pack 仅保留正确 Product `wordpress_post_2413` 为 `S1`；Score 14 / Coverage 50% 的普通 Product 被正确过滤。
- `dimension`：Gate = `clarify`，Evidence Pack / Prompt Preview 均 `Skipped`。
- `CWC-610 warranty`：Medium Partial Evidence → `clarify`，Evidence Pack / Prompt Preview 均 `Skipped`。
- `ZXQ-99999-NOMATCH`：`no_answer`，Evidence Pack / Prompt Preview 均 `Skipped`。
- `minimum order quantity` 多来源验证通过：`manual_3100`（Score 39 / Coverage 100%）作为 `S1`，`manual_3104`（Score 21 / Coverage 100%）作为 `S2`；Prompt Preview 正确输出 `Evidence IDs: S1, S2`。
- Supporting Evidence 门槛 `Score >= 18 / Coverage >= 75%` 真实环境验证通过，弱相关 Score 9 / 4 候选不会污染 Evidence Pack。
- Stage 2 全程保持 `AI Called = false / Token Usage = 0`。
- 当前状态：Stage 2 Validation Passed；下一阶段进入 Stage 3 — Grounded Answer。

### Stage 1 — Grounding Gate Foundation

- 插件开发版本提升到 `0.6.0`。
- 新增 `WPAIC_Grounding_Gate`，消费 v0.5.0 Local Retrieval Result，不直接依赖 AI Provider。
- 新增 Gate Decision Contract：`decision / allow_ai / reason_code / reason_message / retrieval_strength / reliable_match / top_score / score_gap / top_coverage`。
- 第一版保守 Gate：Strong + Reliable Yes → `allow_answer`；Medium / Weak → `clarify`；None → `no_answer`。
- 新增 Reason Codes：`strong_reliable_match / medium_needs_clarification / weak_ambiguous_match / no_local_evidence`。
- 新增后台 `Grounded AI` Playground，独立于 v0.5.0 “本地检索”页面。
- Playground 先执行 Local Retrieval，再展示 Grounding Gate Decision 与 Retrieval Diagnostics。
- 新增 `Allow AI (Policy)` 与实际 `AI Called` 的职责区分。
- Stage 1 强制 `AI Called = false`、`Token Usage = 0`；即使 Gate 返回 `allow_answer`，也不会调用 `WPAIC_AI_Manager` / DeepSeek。
- Stage 1 不构建 Evidence Pack、不构建 Prompt、不生成 AI Answer。
- 不新增数据库表，不引入 Chunk / Embedding / Vector / RAG。
- PHP syntax check / JavaScript syntax check 已通过。
- Stage 1 已完成真实 WordPress 环境验证并正式封板。
- `CWC-610 dimension`：Strong / Reliable Yes → `allow_answer`；`Allow AI (Policy) = Yes`，但 `AI Called = No / Token Usage = 0`。
- `CWC-610 warranty`：Medium / Coverage 50% → `clarify`；验证 Partial Evidence 不会因为型号强匹配而放行缺乏属性证据的问题。
- `dimension`：Weak / Gap 0 → `clarify`；验证高频模糊 Query 不允许 AI 猜测。
- `ZXQ-99999-NOMATCH`：None → `no_answer`；验证无本地证据时明确阻断 AI。
- 四种证据状态均确认 Stage 1 不调用 `WPAIC_AI_Manager` / DeepSeek，`Token Usage = 0`。
- 当前状态：Stage 1 Validation Passed；下一阶段进入 Stage 2 — Evidence Pack & Prompt Builder。

### Design

- 完成 `docs/versions/v0.6.0.md` 第一版设计合同。
- 明确 v0.6.0 的核心原则：`Application Gate > Prompt`。
- 明确硬边界：`No Reliable Knowledge → No AI Call`。
- 设计 `Local Retrieval → Grounding Gate → Evidence Pack → Grounded Prompt → AI Manager → Grounded Answer` 主链路。
- 设计 `WPAIC_Grounding_Gate`，第一版采用保守决策：Strong + Reliable Yes → `allow_answer`；Medium / Weak → `clarify`；None → `no_answer`。
- Gate Decision 设计为可复用 Contract，并包含 reason code / retrieval strength / score / coverage / gap。
- 设计 `WPAIC_Evidence_Pack_Builder`，Retrieval 同时作为 Relevance Filter 与 Token Firewall。
- Evidence 第一版最多 3 个 Source，优先 Structured Data，并设计每 Source / 总字符预算。
- 设计稳定 Source Ref：`S1 / S2 / S3`；Source URL 与 Source Trace 由应用层生成，不信任模型自行生成来源。
- 设计 `WPAIC_Grounded_Prompt_Builder`，明确 Evidence-only Answer、资料不足时不补全、Evidence 内容只能视为数据不能覆盖 System Rule。
- 设计 `WPAIC_Grounded_Answer_Service`，只通过现有 `WPAIC_AI_Manager` 调用 Provider，不把 Grounding 业务逻辑塞进 DeepSeek Provider。
- 设计 deterministic `clarify / no_answer`：非 `allow_answer` 状态不调用 AI，也不产生 Token Cost。
- v0.6.0 第一版限定 Single-turn / Single-intent Grounded QA，不做完整 Chat。
- v0.6.0 不新增数据库表，不做 Conversation Log / Prompt Log / Answer Log。
- 开发拆分为 Stage 1 Grounding Gate Foundation、Stage 2 Evidence Pack & Prompt Builder、Stage 3 Grounded Answer。
- Stage 1 与 Stage 2 明确不调用 DeepSeek；Stage 3 只有 Gate 允许时才调用 AI。
- Design Milestone `v0.6.0-design.1` 发布时插件代码仍保持 v0.5.0；当前已进入 v0.6.0 Stage 1 功能实现。

---

## v0.5.0 — Local Retrieval (2026-09-17)

### Final Review

- Stage 1 — Query & Candidate Foundation：Passed。
- Stage 2 — Weighted Scoring：Passed。
- Stage 3 — Retrieval Quality Calibration：Passed。
- `CWC-610 dimension`：Strong / Reliable Yes，36 vs 14，Gap 22，Coverage 100%。
- `minimum order quantity`：Strong / Reliable Yes，39 vs 9，Gap 30，Coverage 100%。
- `dimension`：Weak / Reliable No，19 vs 19，Gap 0，验证高频模糊 Query 不会被误判为可靠匹配。
- `CWC-610`：单候选 Strong / Reliable Yes，Score 28，Coverage 100%。
- `ZXQ-99999-NOMATCH`：None / Reliable No。
- Top-K 3 / 5 / 10 与 Stable Ranking 已通过真实环境验证。
- Local Retrieval 后台页完成 100% 可用宽度优化，便于查看 Score Breakdown / Snippet。
- PHP syntax check：通过。
- JavaScript syntax check：通过。
- Plugin Version / `WPAIC_VERSION`：`0.5.0`。
- Knowledge Store 自定义表：仍为 1 张；Retrieval 不新增数据表。
- Retrieval 不调用 DeepSeek；不执行 Chunk / Embedding / Vector / RAG。
- v0.5.0 Final Review：通过，作为 Stable Release。


### v0.5.0 Stage 3 — Retrieval Quality Calibration（Validation Passed）

- 新增 `WPAIC_Retrieval_Strength_Evaluator`。
- 新增诊断级 `strong / medium / weak / none`。
- Strength 同时观察 Top Score、Top Coverage 与 Top/Second Score Gap。
- 新增 Minimum Score Floor、Medium / Strong Score Threshold。
- 新增 Medium / Strong Coverage 与 Score Gap 校准基线。
- 新增 `Reliable Match: Yes / No` 诊断信号。
- `weak / none` 显示 `No Reliable Local Match`，但保留候选供校准。
- 新增 `wpaic_retrieval_strength_thresholds` Filter。
- Playground 显示 Top Score / Second Score / Score Gap / Top Coverage。
- Stage 3 仍不调用 DeepSeek，也不是 Grounding Gate。
- 不新增 Retrieval 数据表，不引入 Chunk / Embedding / Vector / RAG。

- Stage 3 真实环境校准验证通过。
- `CWC-610 dimension`：36 / 14 / Gap 22 / Coverage 100% → Strong / Reliable Yes。
- `minimum order quantity`：39 / 9 / Gap 30 / Coverage 100% → Strong / Reliable Yes。
- `dimension`：19 / 19 / Gap 0 / Coverage 100% → Weak / Reliable No，验证高频模糊 Query 不会被误判为可靠匹配。
- `CWC-610`：Candidate Count 1 / Score 28 / Coverage 100% → Strong / Reliable Yes。
- `ZXQ-99999-NOMATCH`：Candidate Count 0 → None / Reliable No。
- 当前校准基线暂不调整：Minimum 12 / Medium 18 / Strong 25 / Coverage 50%/75% / Gap 3/6。
- Stage 3 正式封板，下一步进入 v0.5.0 Final Review。


### Design

- 完成 `docs/versions/v0.5.0.md` 第一版正式设计合同。
- 明确 Retrieval 与 AI Answer 分离：v0.5.0 只负责从 Knowledge Store 找证据，不调用 DeepSeek 生成答案。
- 继续坚持 Local Retrieval First，不提前引入 Chunk、Embedding、Vector Database 或 RAG。
- Retrieval 数据源固定为 v0.4.0 Knowledge Store，并且只查询 `store_status = active` 的正式 Knowledge。
- 设计 `Query Normalizer → Candidate Recall → Weighted Scoring → Top-K → Score Breakdown` 主链路。
- Candidate Recall 第一版采用 SQL `LIKE` + Candidate Limit，不建立 FULLTEXT Index。
- Ranking 第一版采用 PHP 可解释评分，字段权重方向为 Structured Data > Title > Taxonomies > Excerpt > Content。
- 设计 Exact Match Boost、Phrase Match Boost 与 Query Coverage Bonus。
- 明确不默认加入 Recency Boost，也不默认给 Product / FAQ / Manual 等 Source Type 隐藏优先级。
- Retrieval Result 必须包含 matched terms / fields / score breakdown，保证可解释和可调试。
- 设计后台“本地检索” Playground，只提供 Question、Top K 与 Retrieval Diagnostics，不加入 Chat UI。
- 设计 No Match 为合法结果，为后续 v0.6.0 Grounding Gate 提供基础。
- v0.5.0 不新增自定义数据库表，不记录 Query Log。
- 开发分为 Stage 1 Query & Candidate Foundation、Stage 2 Weighted Scoring、Stage 3 Retrieval Quality Calibration。

### Stage 1 — Query & Candidate Foundation

- 插件版本提升到 `0.5.0`。
- 新增 `WPAIC_Retrieval_Query_Normalizer`：Unicode lowercase、HTML entity decode、标点标准化、空白合并，并保留 `- / _` 等型号字符。
- 第一版 Stop Words 仅过滤少量明显低价值英语功能词，并提供 `wpaic_retrieval_stop_words` Filter。
- 新增 `wpaic_retrieval_query_terms` Filter，用于后续按真实业务需要显式扩展少量 Query Alias。
- 新增 `WPAIC_Retrieval_Candidate_Searcher`：只查询 `store_status = active` 的 Knowledge Store Row。
- Candidate Recall 第一版对 `title / excerpt / taxonomies / structured_data / content` 使用 SQL `LIKE`。
- Candidate Limit 默认 100，并通过 `wpaic_retrieval_candidate_limit` Filter 限制在 20~200。
- 新增 `WPAIC_Local_Retriever` Stage 1 编排层，返回 Normalized Query、Terms、Candidate Count、Elapsed 与 Candidate Diagnostics。
- 新增后台“本地检索” Playground，支持 Question + Candidate Limit。
- Candidate Result 显示 Source ID、Post Type、Matched Fields、Matched Terms、Snippet 与来源 URL。
- Stage 1 明确不输出最终 Score / Ranking；当前候选顺序仅用于 Recall Diagnostics。
- Retrieval Playground 为只读操作，不修改 Knowledge Store，不触发 Full Sync / Incremental Sync。
- 不调用 DeepSeek，不新增 Retrieval Log / Chunk / Embedding / Vector 表。

### Stage 1 Status

- Code Implemented。
- Real-world Validation Passed。
- `CWC-610`：Candidate Count = 1，正确 Product 命中 `title / structured_data`。
- `CWC-610 dimension`：正确 Product 稳定进入 Candidate Set；高频 `dimension` 产生较多候选符合 Stage 1 OR Recall 设计。
- `minimum order quantity`：正确 Manual Knowledge 被召回，并同时命中 `minimum / order / quantity`。
- inactive 特有关键词：Candidate Count = 0，验证 Active-only Search。
- `ZXQ-99999-NOMATCH`：Candidate Count = 0，正确输出 No Candidate Match。
- Query Normalize、Stop Words、连字符型号、Candidate Limit、Product / Manual Knowledge 通用召回均符合预期。
- Stage 1 正式封板，下一阶段进入 Stage 2 — Weighted Scoring。

### Stage 2 — Weighted Scoring

- 新增 `WPAIC_Retrieval_Scorer`，在 PHP 层完成可解释 Weighted Scoring。
- 初始字段权重：Structured Data 8 / Title 6 / Taxonomies 4 / Excerpt 3 / Content 1。
- 新增 Exact Identifier Boost：仅对含数字、连字符或下划线的 identifier-like Term 生效，避免普通高频词获得隐藏优先级。
- 新增 Phrase Match Boost。
- 新增 Query Coverage Bonus。
- 新增稳定 Ranking：Score → Coverage → Matched Terms → Matched Fields → source_id。
- 新增 Top-K，默认 5，可通过 `wpaic_retrieval_top_k` Filter 调整，并在 Playground 选择 3 / 5 / 10。
- Retrieval Result 新增 Rank / Score / Score Breakdown / source_hash。
- Score Breakdown 显示 Field Scores / Exact Match / Phrase Match / Coverage。
- Scorer 内部消费完整 Candidate Snapshot，但 Stage 2 返回 UI 时不再默认传输完整 content / structured_data。
- Playground 新增 Scored Count 与 Top K。
- Stage 2 继续只读 Knowledge Store，不调用 DeepSeek，不新增 Retrieval / Chunk / Embedding / Vector 表。
- Retrieval Strength / Minimum Score Threshold 留到 Stage 3 真实质量校准。
- 优化“本地检索”后台页面布局：仅 Retrieval 页面使用 WordPress 后台可用宽度的 100%，主结果区自适应扩展，右侧 Stage 边界保持紧凑宽度，减少 Ranking 表格横向隐藏。
- 当前状态：Real-world Validation Passed。

### Stage 2 Status

- `CWC-610 dimension`：正确 Product `wordpress_post_2413` 稳定 Rank #1，Score = 36；普通只命中 `dimension` 的 Product 为 Score = 14，分差清晰。
- 正确 Product Score Breakdown 验证：`structured_data +16 / title +6 / Exact cwc-610 +4 / Coverage 2/2 +10`。
- `minimum order quantity`：正确 Manual Knowledge `manual_3091` 稳定 Rank #1，Score = 39；第 2 名 Score = 9，其余弱相关结果明显更低。
- Top-K = 3 / 5 / 10 均通过：Candidate Count 与 Scored Count 保持不变，只改变最终返回结果数量。
- 同一 Query 连续检索两次，Top 5 排名顺序保持一致，验证 Stable Ranking / Tie-breaker。
- Candidate Count = 100 / Scored Count = 100 时 Top-K 正常截断，Candidate Pool 与最终结果集职责分离。
- Full-width Retrieval Playground 调整后，Score Breakdown 与 Snippet 可完整横向查看，调试体验符合预期。
- Stage 2 正式封板，下一阶段进入 Stage 3 — Retrieval Quality Calibration。

### Boundaries

- 当前稳定 Release 仍为 v0.4.0；v0.5.0 开发分支插件代码版本已进入 0.5.0。
- 不做 DeepSeek Answer / Prompt Builder / Grounding Gate / RAG。
- 不做 Chunk / Embedding / Vector / Hybrid Search。
- 不做前台 Public Retrieval API、Conversation、Live Chat、Lead 或 Human Handoff。

---

## v0.4.0 — Knowledge Store & Lifecycle (2026-09-17)

### Fixed

- 修复 Knowledge Store 分页链接中查询参数分隔符被错误编码为 `#038;`，导致点击页码无法切换的问题。
- 后台 JS / CSS 资源版本加入文件内容哈希，避免同一 `v0.4.0` 多阶段开发时浏览器继续使用旧缓存。

### Design


- 完成 `docs/versions/v0.4.0.md` 第一版正式设计合同。
- 明确 WordPress 继续作为唯一 Source of Truth，Knowledge Store 只是可重建的 AI Read Model / Snapshot。
- 计划首次引入 1 张 `{$wpdb->prefix}wpaic_knowledge_store` 自定义表；暂不建立 Chunk、Embedding、Vector 或 Log 表。
- 明确 Source Hash 只判断 AI-visible 内容变化，Eligibility 单独判断 publish / enabled / exists 生命周期。
- 定义最小 Lifecycle Action：`created / updated / unchanged / reactivated / deactivated / error`。
- 确认第一版采用 soft deactivate，不直接物理删除 Store Row。
- 设计 `WPAIC_Knowledge_Store_Repository` 与 `WPAIC_Knowledge_Lifecycle_Manager` 职责边界。
- 设计覆盖升级可执行的 DB Version / `dbDelta()` 机制，避免依赖“停用再激活”。
- Initial / Full Sync 采用可见 AJAX Batch；日常维护采用 Incremental Sync，不引入 Cron / Queue。
- v0.4.0 第一轮实现限定为 Store Foundation：DB Installer、Table、Repository、Single-source Sync、Minimal Diagnostics。

#### Stage 1 — Store Foundation (Code Implemented)

- 插件版本提升到 `0.4.0`，新增 `WPAIC_DB_VERSION = 1.0` 与 `wpaic_db_version`。
- 新增 `WPAIC_DB_Installer`，Activation 与覆盖升级共用幂等 `dbDelta()` 安装流程。
- 新增 `{$wpdb->prefix}wpaic_knowledge_store`，仅建立 1 张 Knowledge Store 派生表。
- 新增 `WPAIC_Knowledge_Store_Repository`，负责 Store CRUD、状态统计与诊断列表。
- 新增 `WPAIC_Knowledge_Lifecycle_Manager` Stage 1 实现，仅处理 eligible Source 的 `created / unchanged / updated`。
- 新增“知识存储”后台页，提供 Active / Inactive / Total Summary、Single-source Sync 与最近 Store Rows。
- Single-source Sync 可显示 action、old/new hash、store status 以及已持久化的 Snapshot。
- Manual Knowledge 在 publish 状态下可直接进行单条 Store Sync；普通 Post Type 必须已在“知识来源”启用。
- `unchanged` 只更新检查时间与状态元数据，不重写 AI-visible Snapshot。
- Source Hash 变化时更新完整 Snapshot。

#### Stage 1 Boundaries

- Draft / Trash / Deleted / Disabled 的 soft deactivate 与 Reactivate 留到 Stage 2。
- Full AJAX Batch / Reconciliation 留到 Stage 3。
- WordPress lifecycle incremental hooks 留到 Stage 4。
- 不做 Retrieval、Fulltext Ranking、Chunk、Embedding、Vector、RAG、Grounding、AI Answer 或 Chat。
- Knowledge Store 不提供业务内容编辑器，所有知识仍从 WordPress Source 重建。
- Stage 1 真实环境验证通过：覆盖升级建表、Product / Post / Manual Knowledge 持久化、`created / unchanged / updated`、Hash Compare / Restore、`source_id` 唯一性均符合预期。

#### Stage 2 — Lifecycle (Code Implemented)

- `WPAIC_Knowledge_Lifecycle_Manager` 增加 Eligibility Lifecycle：内容变化与可用状态分离判断。
- `publish → draft / private / trash`：已有 Store Row 软停用为 `inactive`，`inactive_reason = not_published`。
- Knowledge Source 被禁用：单条 Sync 将已有 Row 标记为 `inactive / source_disabled`。
- Source 对应 CPT 不再可发现：已有 Row 标记为 `inactive / source_missing`。
- Source 被永久删除：通过 `object_id` 找回历史 Store Row，并标记为 `inactive / source_deleted`。
- Inactive Row 不删除最后有效 AI-visible Snapshot，也不因状态变化重算 Source Hash。
- Inactive Source 再次满足 publish + enabled 条件时，重新生成当前 Source，完整更新 Snapshot / Hash，并记为 `reactivated`。
- 新增 `find_by_object_id()`，用于已删除 Source 的历史 Store 定位。
- Store Diagnostics 增加 Inactive Reason，并展示 Stage 2 Lifecycle 边界。
- Stage 2 继续只使用 Single-source Sync；Batch / Reconcile 仍留到 Stage 3，自动保存 Hooks 仍留到 Stage 4。

#### Stage 2 Validation Passed

- `publish → draft → inactive / not_published`：通过。
- `draft → publish → reactivated`：通过。
- `publish → trash → inactive / not_published`：通过。
- 永久删除 Source → `source_deleted`：通过。
- 禁用 Knowledge Source Type → `source_disabled`：通过。
- 重新启用 Source Type → `reactivated`：通过。
- Lifecycle 状态变化但 AI-visible 内容未变化时 Source Hash 保持稳定：通过。
- Inactive Store Row 保留最后有效 Snapshot 与 Hash：通过。

#### Stage 3 — Batch Sync & Reconciliation (Code Implemented)

- 新增 `WPAIC_Knowledge_Batch_Sync`。
- 新增可见 AJAX Full Sync，不引入 Cron / Queue。
- Full Sync 在开始时冻结当前 eligible WordPress ID 集合，并按默认每批 20 条执行现有 Lifecycle Sync。
- 新增 `wpaic_full_sync_batch_size` Filter，可在 5~50 之间调整批量大小。
- Full Sync 支持中断后在短时锁有效期内继续当前任务，避免重复并发任务。
- 新增进度与统计：Processed / Created / Updated / Unchanged / Reactivated / Deactivated / Errors。
- 所有 Source Batch 完成后进入 Reconciliation。
- Reconciliation 会检查 Store 中仍为 active、但已不属于当前 eligible Source 集合的 Row，并通过现有 Lifecycle Manager 软停用。
- 可处理 Draft / Trash / Deleted / Source Disabled / Source Missing 等 stale active Row。
- 新增 Last Full Sync 持久化摘要。
- Knowledge Store 页面新增 Full Sync UI、进度条、最终 Summary 与 Last Full Sync。
- 首次真实 Full Sync 已验证 138 条正式 Knowledge、Errors 0。
- Knowledge Store Rows 从“最近 10 条”升级为完整分页浏览：默认每页 20 条，支持上一页 / 页码 / 下一页。
- 列表继续按 `updated_at DESC, id DESC` 展示，分页只解决 Store 可见性，不引入搜索、筛选或批量管理。
- Single-source Sync 继续保留作为诊断工具。
- Stage 3 仍不加入自动保存 Hooks；Incremental Sync 留到 Stage 4。

#### Stage 3 Validation Passed

- 首次真实 Full Sync：Processed 138 / Errors 0。
- 连续重复 Full Sync：Created 0 / Updated 0 / Unchanged 138，Store Total 保持 139，不产生重复 Row。
- 禁用 Product 后，81 条 Product 由 Reconciliation 批量变为 `inactive / source_disabled`；Active 138 → 57，Inactive 1 → 82，Total 仍为 139。
- 重新启用 Product 后：Reactivated 81 / Unchanged 57 / Errors 0。
- Knowledge Store Rows 分页在 139 条真实 Store Row 上验证通过。
- Stage 3 — Batch Sync & Reconciliation：Passed。

#### Stage 4 — Incremental Sync (Code Implemented)

- 新增 `WPAIC_Knowledge_Incremental_Sync`。
- 监听 `wp_after_insert_post`，在 WordPress 正常保存流程完成后只同步当前 Source。
- 监听 `deleted_post`，永久删除 Source 后自动将已有 Snapshot 标记为 `source_deleted`。
- 新增 Source 在 `publish + enabled` 条件下可通过日常保存直接 `created`，无需等待下一次 Full Sync。
- 已有 Store Row 保存时继续复用 Lifecycle Manager，可自动产生 `unchanged / updated / deactivated / reactivated`。
- Draft / Trash / Republish 等状态变化只更新当前 Source，不触发整站扫描。
- Legacy / WEM Structured Data 保存后继续通过同一 Extractor / Resolver / Hash / Store 链路更新当前 Row。
- 自动忽略 revision、autosave 与 auto-draft；未启用且从未进入 Store 的普通内容不会产生无意义同步。
- 新增轻量 `wpaic_last_incremental_sync` Option，仅保存最近一次 Incremental Sync 诊断结果，不增加日志表。
- Knowledge Store 页面新增最近 Incremental Sync 信息，并更新为 Stage 4 边界说明。
- Full Sync 继续保留用于 Initial Sync 与 Reconciliation；Incremental Sync 不调用 Full Sync。
- Stage 4 仍不做 Retrieval / Fulltext / Chunk / Embedding / Vector / RAG / AI Answer。

#### Stage 4 Validation Passed

- 修改已有 Product 后自动 `updated`：通过。
- AI-visible Knowledge 未变化再次保存 → `unchanged`：通过。
- Product `publish → draft` → `inactive / deactivated / not_published`：通过。
- Draft 重新发布 → `active / reactivated`：通过。
- Legacy / WEM Structured Data 变化 → `updated` 且 Source Hash 改变：通过。
- 新建并发布 Manual Knowledge → 自动 `created`：通过。
- Manual Knowledge 移入回收站 → `deactivated / not_published`：通过。
- Manual Knowledge 永久删除 → 自动 `source_deleted`：通过。
- Incremental Sync 不触发 Full Sync，Last Full Sync 保持不变：通过。

#### Final Review

- Stage 1 — Store Foundation：Passed。
- Stage 2 — Lifecycle：Passed。
- Stage 3 — Batch Sync & Reconciliation：Passed。
- Stage 4 — Incremental Sync：Passed。
- PHP syntax check：通过。
- JavaScript syntax check：通过。
- Plugin Version / `WPAIC_VERSION`：`0.4.0`。
- Knowledge Store 自定义表：1 张。
- Chunk / Embedding / Vector 表：0。
- Preview / Store / Sync 不调用 DeepSeek。
- v0.4.0 Final Review：通过。

---

## v0.3.1 — Structured Source Validation (2026-09-16)

#### Design

- 将原计划 `WEM Structured Source Validation` 扩展为 `Structured Source Validation`。
- 确认大量旧站使用 `functions.php + Post Meta`，不能把 WEM Field Group 作为结构化知识的架构前提。
- 确立 `Structured Field Resolver → Provider → AI Allowlist → structured_data` 的最小结构。
- 引入稳定 `knowledge_key` 概念，使旧 Meta Key 与未来 WEM Meta Key 可以映射到同一业务语义。
- 继续坚持 Explicit Allowlist，不自动读取所有 Post Meta，也不自动授权所有结构化字段。

#### Added

- 新增 `WPAIC_Legacy_Profile_Provider`，以显式 Profile 兼容旧站 functions.php Post Meta。
- 新增 `WPAIC_Structured_Field_Resolver`，统一解析结构化字段定义，并为后续 WEM Provider 预留轻量合并路径。
- 新增 `WPAIC_Structured_Value_Normalizer`。
- 新增 `WPAIC_Structured_Source_Enhancer`，继续复用 `wpaic_knowledge_source` 扩展现有 Unified Source。
- 新增 Legacy Product 最小 Allowlist：`product_number → product_model`、`product_size → product_dimension`。
- 新增 `wpaic_legacy_structured_profiles`、`wpaic_structured_field_definitions`、`wpaic_structured_data` 扩展点。

#### Boundaries

- 当前代码轮次验证 Legacy Product + WEM Product 两种结构化字段来源；只允许已明确映射的 Product 字段。
- 不扫描任意 Post Meta，不解析 functions.php，不加入图片、VR、Video 或 PDF IDs。
- 不新增 Field Mapping UI、数据库表、Knowledge Store、Retrieval、RAG 或 AI 调用。

#### Stage 1 Validation Passed

- Legacy Product `product_number → product_model`：通过。
- Legacy Product `product_size → product_dimension`：通过。
- Legacy Meta 不存在时安全返回空 `structured_data`：通过。
- Structured Data 变化后 Source Hash 改变：通过。
- 恢复原值后 Source Hash 恢复：通过。
- 连续 Preview 相同 Knowledge 时 Source Hash 稳定：通过。

#### Stage 2 Added

- 新增 `WPAIC_WEM_Field_Registry_Provider`。
- 从 WEM `wem_cf_field_groups` 与 `wem_cf_runtime_field_groups` 获取 Field Definitions。
- 新增 WEM Product 最小 AI Allowlist：`product_model`、`product_dimension`。
- 新增 `wpaic_wem_structured_allowlist` 扩展点。
- Structured Field Resolver 改为保留同一 `knowledge_key` 的 Provider Candidate 顺序。
- 实现逐字段 `WEM non-empty > Legacy fallback` 行为。
- WEM Provider 仅在 WEM Content Fields 激活时启用；不会仅凭残留 Option 误启用。
- 知识来源页版本文案改为动态读取 `WPAIC_VERSION`。

#### Stage 2 Validation Passed

- WEM 两个 Product 字段均非空时，Structured Data 正确使用 WEM 值：通过。
- WEM 单字段为空时，按 `knowledge_key` 独立回退 Legacy：通过。
- WEM 字段全部为空时，Legacy Product Profile 完整接管：通过。
- 停用 WEM Content Fields 后，Legacy Structured Data 仍正常：通过。
- WEM Structured Data 变化后 Source Hash 改变：通过。
- 恢复相同 AI-visible Structured Data 后 Source Hash 恢复：通过。
- 连续 Preview 相同 Knowledge 时 Source Hash 保持稳定：通过。
- 普通 Post 等非 Product 来源未被 Structured Provider 污染：通过。
- Preview 继续保持不调用 DeepSeek、不持久化 Source：通过。

#### Final Review

- PHP syntax check：通过。
- JavaScript syntax check：通过。
- Plugin Version / `WPAIC_VERSION`：`0.3.1`。
- 知识来源页版本信息改为动态读取 `WPAIC_VERSION`：通过。
- DeepSeek API URL 仍只存在于 DeepSeek Provider。
- 自定义数据库建表语句：0。
- v0.3.1 Final Review：通过。

---

## v0.3.0 — Knowledge Source Foundation (2026-09-16)

### Added

- 新增 Post Type Discovery，只发现 `public + show_ui` 的业务内容类型，并排除常见 WordPress / Builder 内部类型。
- 新增“知识来源”后台页面，管理员可以显式启用 / 禁用允许进入 AI Knowledge Source 管道的 Post Type；默认全部关闭。
- 新增 `WPAIC_Generic_Extractor`，将普通 WordPress 内容按需转换为统一 Knowledge Source。
- 新增 `WPAIC_Content_Normalizer`，处理基础 HTML、script/style、HTML / Gutenberg 注释、Shortcode、HTML Entity 与多余空白噪音。
- 新增 `WPAIC_Knowledge_Source`，统一输出 `source_id`、`source_type`、`post_type`、`knowledge_type`、title、excerpt、URL、taxonomy、structured data、normalized content、SHA-256 source hash、updated_at 与 status。
- 新增 `wpaic_knowledge` CPT，后台名称为“AI 补充知识”，用于保存样品政策、报价流程、MOQ 通用说明等原有业务内容无法自然表达的知识。
- 新增 `wpaic_knowledge_category` 分类法，用于 AI 补充知识的后台组织与筛选。
- 新增 Minimal Source Preview，可按 WordPress 内容 ID 查看 AI 后续实际可见的数据。
- Source Preview 支持最近内容快捷预览，不调用 DeepSeek，也不持久化 Knowledge Source。
- 新增轻量扩展 Filter：`wpaic_discoverable_post_types`、`wpaic_allowed_meta_keys_for_post_type`、`wpaic_normalized_content`、`wpaic_knowledge_source`。
- 新增 `wpaic_enabled_sources` Option，作为知识来源 Opt-in 配置，并保持非自动加载。

### Changed

- 插件版本正式提升到 `0.3.0`。
- 后台菜单扩展为“AI 设置 / 知识来源 / AI 补充知识”。
- v0.2.0 AI Provider Foundation 完整保留，知识来源模块与 DeepSeek Provider 解耦。
- README 与 `docs/versions/v0.3.0.md` 更新为 Final Review Passed / Stable Release 状态。

### Security / Data Boundaries

- Generic Extractor 默认不读取任何 Post Meta；只有开发者显式白名单 Filter 才能读取指定 Key。
- Public Taxonomy 以独立结构输出，不直接混入正文。
- 正式 Knowledge 只接受 `publish` 内容；Preview 可以用于管理员调试非发布内容，但明确标记为不可进入正式 Knowledge。
- `wpaic_knowledge` 设置为非公开、不可前台查询、排除站内搜索。
- v0.3.0 不创建任何自定义数据库表。

### Intentionally Not Included

v0.3.0 仍不包含：

- 正式 Adapter Interface / Registry
- WEM / WooCommerce / ACF / Meta Box Extractor
- Field Mapping UI
- Knowledge Store / Lifecycle / Auto Sync
- Chunk / Retrieval / Search Ranking
- Embedding / Vector Database
- RAG / Grounding / AI Answer
- Conversation / Live Chat / Lead / Human Handoff

### Validation Status

- PHP syntax check：通过。
- JavaScript syntax check：通过。
- v0.2.0 DeepSeek Provider 回归测试：通过。
- Post Type Discovery / Enable / Disable / 默认 Opt-in：通过。
- FAQ Knowledge Source Preview：通过。
- Product Knowledge Source Preview：通过。
- Post Knowledge Source Preview：通过。
- Manual Knowledge Preview：通过。
- Product / Blog Content Normalization：通过。
- Public Taxonomy 独立结构化输出：通过。
- Generic Extractor 默认 Meta 隔离：通过。
- Draft 可预览但不属于正式 Knowledge：通过。
- Source Hash 在仅状态变化时保持稳定：通过。
- Source Hash 在标题 / 正文变化时改变：通过。
- Preview 不调用 DeepSeek：通过。
- DeepSeek API URL 仍只存在于 DeepSeek Provider。
- 自定义数据库建表语句：0。
- Final Review：通过。

---

## v0.2.0 — AI Provider Foundation

### Added

- 建立第一个可安装的 WordPress 插件骨架 `plugin/wp-ai-chat/`。
- 新增 `WPAIC_AI_Provider_Interface`，定义统一 AI Provider Contract。
- 新增 `WPAIC_DeepSeek_Provider`，集中处理 DeepSeek API 通信、响应标准化与错误映射。
- 新增 `WPAIC_AI_Manager`，业务层通过统一入口调用当前 Provider。
- 新增 WordPress 后台 AI 设置页。
- 支持后台保存 DeepSeek API Key，并支持 `wp-config.php` 常量 `WPAIC_DEEPSEEK_API_KEY` 优先。
- 默认模型使用 `deepseek-flash`。
- DeepSeek 测试请求显式关闭 Thinking Mode，保持测试链路简单、快速、低成本。
- 新增最小连接测试与最小 AI 对话测试。
- 测试结果显示 Provider、Request/Response Model、Token Usage、Elapsed Time、Finish Reason 与 HTTP Status。
- 新增 AJAX Nonce、Capability Check、重复点击保护和测试问题长度限制。
- 建立稳定的 `WP_Error` 错误分类，包括缺少 Key、认证失败、限流、超时、Provider 故障、无效响应等。
- 新增 `docs/versions/v0.2.0.md`，记录本版本目标、边界、架构、测试与验收标准。

### Fixed

- 修复保存 AI 设置后“AI 设置已保存。”成功通知重复显示的问题。
- 将成功通知从设置清理回调移到保存完成后的页面渲染阶段，避免 WordPress 重复执行 sanitize callback 时重复注册 Notice。

### Changed

- README 当前版本更新至 v0.2.0。
- Roadmap 中 v0.2.0 从“DeepSeek Client”明确为“AI Provider Foundation”。

### Intentionally Not Included

v0.2.0 不包含：

- Knowledge Source
- Product / Solution / FAQ Adapter
- Content Scanner / Normalizer
- Chunk / Knowledge Store
- Retrieval / RAG
- Grounding Gate
- Usage Limit
- Conversation / Live Chat
- Lead / Human Handoff
- Embedding / Vector Database
- Streaming
- Multiple Providers

---

## v0.1.0 — Concept & Architecture Baseline

- 建立项目设计思想与长期目标。
- 完成 Tidio / Lyro、Rapls、Zorachat、ChatBudgie、MxChat、AxiaChat 等方案的对照研究。
- 确立 WordPress Native Knowledge、Local Retrieval、Grounding、Usage Guard、Human Handoff 等核心原则。
- 明确短期不以替代 Tidio 为目标。
- 规划 v0.1.0 → v1.0.0 的阶段路线。
