# Changelog

本项目使用版本记录来保留从设计思想到正式产品的完整演进过程。

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
