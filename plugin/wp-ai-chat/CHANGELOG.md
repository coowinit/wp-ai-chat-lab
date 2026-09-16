## [Unreleased]

暂无。

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
