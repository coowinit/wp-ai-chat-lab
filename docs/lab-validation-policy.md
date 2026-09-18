# WP AI Chat Lab — Lab / Playground 永久保留策略

> 状态：长期项目规则

## 1. 为什么长期保留

WP AI Chat Lab 的后台测试页面不是一次性开发工具，而是项目架构本身的可观察层。

长期保留有三个目的：

1. **理解架构**：把 Knowledge → Retrieval → Grounding → Usage → Provider → Public Chat Boundary 每层的行为可视化。
2. **诊断问题**：正式聊天异常时，可以绕开前端 UI，直接逐层验证问题出在哪一层。
3. **回归测试**：版本升级后可以重跑已经确认过的典型场景，避免新功能破坏旧边界。

## 2. 永久保留范围

当前及后续已经建立的 Lab / Playground / Validation 页面原则上长期保留，包括但不限于：

- Knowledge Sources / Knowledge Store
- Local Retrieval Playground
- Grounded AI Playground / Final Validation
- Usage Guard Playground / Historical Validation
- Chat Boundary Playground
- Chat UI Lab（前台 Widget 开关、状态映射与回归路线）
- 后续确有必要建立的独立诊断页面

## 3. 后续版本允许怎么改

允许：

- 优化中文说明与场景化测试步骤；
- 将已通过的历史测试默认折叠；
- 增加 Passed / Sealed 状态；
- 为新版本补充回归场景；
- 改善视觉层级与诊断信息；
- 在不破坏历史验收能力的前提下重构内部实现。

不建议：

- 因正式前端 Chat 上线而删除 Lab 页面；
- 为了界面精简移除关键诊断字段；
- 让 Lab 测试走一套与正式 Service 不同的平行逻辑；
- 将测试入口与生产业务逻辑强耦合。

## 4. 核心原则

```text
正式 UI = 用户入口
Lab 页面 = 架构观察与回归入口
核心 Service = 二者共同使用的唯一业务实现
```

因此：

> **Lab 可以退居后台，但不会退出项目。**

它们应始终尽量调用真实 Repository / Service / Boundary，而不是复制一套只用于演示的逻辑。
