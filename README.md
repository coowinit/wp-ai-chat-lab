# WP AI Chat Lab

> 从 WordPress 网站知识出发，研究并逐步构建一套可控、可靠、低成本、可扩展的 AI Chat 架构。

**当前稳定 Release：v0.3.1 · Structured Source Validation**  
**当前开发：v0.4.0 · Knowledge Store & Lifecycle（Design Ready）**  
**下一实现：v0.4.0 Stage 1 · Store Foundation**

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

`v0.4.0 — Knowledge Store & Lifecycle` 已完成第一版正式设计稿，当前仍未修改插件功能代码。

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

计划只引入 **1 张** Knowledge Store 自定义表，不提前创建 Chunk、Embedding、Log 或 Vector 表。第一轮实现严格限定为：

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

当前状态：**Design Ready — Awaiting Implementation**。

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

验证：

```text
Question
↓
Local Search
↓
Relevant Sources
```

这一阶段：

# 不调用 AI。

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
Conversation Limit
Visitor Limit
Site Limit
```

---

## v0.8.0

### Chat Integration

将 AI 接入真实前台 Chat。

---

## v0.9.0

### Human & Knowledge Loop

实现：

```text
AI
↓
Pending
↓
Human
↓
AI
```

以及：

```text
Unanswered Questions
Knowledge Suggestions
Lead Flow
Playground
```

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

# 四十九、当前网站与本项目的关系

生产环境继续：

```text
Tidio
```

承担：

```text
Live Chat
Human Support
成熟客服能力
```

WP AI Chat Lab：

```text
Development / Test
```

用于：

```text
Knowledge
Retrieval
Guard
DeepSeek
AI Architecture
```

至少在：

```text
v1.0.0
```

经过真实测试之前：

> **不以替换 Tidio 为目标。**

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
v0.3.1 Stable / v0.4.0 Design

Stage:
Knowledge Store & Lifecycle — Design Ready

Production:
Tidio

Development:
WP AI Chat Lab

Plugin Code:
v0.3.1 Stable Release（v0.4.0 尚未修改功能代码）

Primary Provider:
DeepSeek (from v0.2.0)

Knowledge Source:
Generic WordPress + Manual Supplement + Legacy / WEM Product structured enhancement

Structured Source:
Legacy Product Profile + WEM Field Registry Provider

Resolver Rule:
WEM non-empty value > Legacy fallback, resolved per knowledge_key

Content Normalizer:
Implemented — basic deterministic cleaning

Source Preview:
Implemented — no AI call, no persistence

Retrieval:
Local First — not implemented yet

Vector:
Not Used

Custom Database Tables:
0

Current Stable Release:
v0.3.1 Structured Source Validation

Development Status:
v0.4.0 Design Ready — Awaiting Implementation

Next:
v0.4.0 Stage 1 — Store Foundation
```

---

# 最后一句

这个项目暂时不追求：

> **做出功能最多的 AI Chatbot。**

而是希望通过长期迭代，逐步回答：

> **怎样才能让 WordPress 自己真正拥有一套简单、可靠、可维护、成本可控的 AI 能力。**

这也是 WP AI Chat Lab 从 v0.1.0 开始最重要的设计基线。