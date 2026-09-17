## v0.5.0 — Local Retrieval (Stage 1 Validation Passed)

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
