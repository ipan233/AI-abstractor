# AIAbstractor

> 基于 OpenAI 标准接口的 Typecho 文章智能摘要插件

[![PHP](https://img.shields.io/badge/PHP-7.2%2B-blue)](https://www.php.net)
[![Typecho](https://img.shields.io/badge/Typecho-1.1%2B-orange)](https://typecho.org)
[![License](https://img.shields.io/github/license/ipan233/AIAbstractor)](./LICENSE)
[![Version](https://img.shields.io/badge/version-2.0.0-green)](https://github.com/ipan233/AIAbstractor/releases)

AIAbstractor 是一款为 **Typecho 博客系统** 开发的智能文章摘要插件。访客在文章页面点击「生成」按钮后，插件会自动提取正文内容，经服务端代理转发至 OpenAI 兼容接口，生成高质量中文摘要并渲染在页面顶部。摘要结果支持 LocalStorage 本地缓存（默认 7 天），重复访问无需再次请求。

感谢 [@zhheo](https://github.com/zhheo/Post-Abstract-AI) 提供的原始 UI 设计灵感。

---

## 目录

- [功能特性](#功能特性)
- [兼容性](#兼容性)
- [项目结构](#项目结构)
- [安装方法](#安装方法)
- [配置说明](#配置说明)
- [使用说明](#使用说明)
- [API 接口文档](#api-接口文档)
- [前端配置项](#前端配置项)
- [常见问题 FAQ](#常见问题-faq)
- [开发说明](#开发说明)
- [贡献指南](#贡献指南)
- [许可证](#许可证)

---

## 功能特性

| 特性 | 说明 |
|------|------|
| 智能摘要生成 | 调用 OpenAI 兼容接口，生成 30 字以内的中文摘要 |
| 服务端安全代理 | API Key 仅存储在服务端，不暴露给前端 |
| LocalStorage 缓存 | 摘要结果缓存 7 天，重复访问秒显 |
| 前端自动注入 | 自动在文章容器顶部插入摘要卡片 UI |
| 双端路由兼容 | 同时提供 `api.php` 直连模式与 Typecho Action 两套端点 |
| IP 频率限制 | 可配置每 IP 每分钟最大调用次数，防止滥用 |
| 模型白名单 | 前端可指定模型，但必须通过服务端白名单校验 |
| 亮色/暗色主题 | CSS 变量驱动，自动适配暗色模式，兼容 Butterfly 等主题 |
| 无侵入集成 | 不修改主题模板文件，全站无侵入 |
| 自定义选择器 | 可配置正文容器 CSS 选择器，适配任意主题 |

---

## 兼容性

| 环境 | 支持状态 |
|------|----------|
| Typecho 1.1 | ✅ |
| Typecho 1.2 / 1.2.1 | ✅ |
| PHP 7.2+ | ✅ |
| 宝塔 / open_basedir 限制 | ✅ |
| Nginx + PHP-FPM | ✅ |
| Apache / LiteSpeed | ✅ |
| 无 PATH_INFO 服务器 | ✅（使用 api.php 直连模式）|
| HTTPS / 反代 / CDN | ✅ |
| Butterfly 主题 | ✅（CSS 变量已适配 `--heo-*`）|

---

## 项目结构

```text
AIAbstractor/
├── Plugin.php                  # 插件主文件：生命周期管理、配置表单、前端资源注入、Action 处理
├── api.php                     # 独立后端代理：不依赖 PATH_INFO，直接加载 Typecho 核心转发请求
├── api.php.bak                 # api.php 备份文件
├── assets/
│   ├── css/
│   │   └── ai_abstractor.css   # 摘要卡片样式：CSS 变量驱动，支持亮色/暗色自动切换
│   └── js/
│       └── ai_abstractor.js    # 前端核心逻辑：UI 构建、文本提取、API 请求、本地缓存
├── LICENSE                     # 开源许可证
└── README.md                   # 项目文档（本文件）
```

### 各文件职责说明

#### `Plugin.php`

包含两个类：

- **`AIAbstractor_Plugin`**：实现 `Typecho_Plugin_Interface`，负责：
  - 插件激活/停用（注册 `header`、`footer` 钩子及 Action 路由）
  - 后台配置表单渲染（API Base、API Key、模型、温度、Tokens 等 9 项配置）
  - 前端 CSS/JS 资源注入（含防重复注入保护）
  - 将后台配置序列化为 `window.AIAbstractorConfigOverrides` 传递给前端
  - 跳过后台、Feed、RSS 等路径的注入

- **`AIAbstractor_Action`**：实现 `Widget_Interface_Do`，负责：
  - 接收前端 POST 请求，进行参数验证
  - IP 级内存频率桶限速（每分钟滑动窗口）
  - 模型白名单校验（`ALLOWED_MODELS` 常量）
  - cURL 封装调用 OpenAI `/chat/completions` 接口
  - 统一 JSON 错误响应

#### `api.php`

独立运行的 PHP 脚本，不依赖 Typecho Action 路由机制：

- 手动引导 Typecho 核心（`config.inc.php` + `Common.php`）
- 从插件配置读取 API 参数
- Session 级频率限制
- 模型白名单校验（白名单范围略宽于 `Plugin.php`，含 `gpt-5.2`、`gpt-5.3`）
- cURL 转发至 OpenAI 接口并返回结果

#### `assets/js/ai_abstractor.js`

单文件 IIFE，无外部依赖：

- 配置初始化：合并 `DEFAULT_CONFIG` 与后台注入的 `AIAbstractorConfigOverrides`
- LocalStorage 缓存读写（Key = `ai_abstractor_v2_{pathname}{hash}`，带时间戳过期校验）
- 文章文本提取：`innerText` 获取 + URL 过滤 + 字符数截断（`wordLimit`）
- UI 构建：动态创建摘要卡片（标题行 + 生成按钮 + 项目地址标签 + 内容区）
- Fetch API 请求：支持并发锁（`_fetching` 标志）、错误显示、加载动画
- 初始化重试机制：DOM 未就绪时最多重试 3 次（每次间隔 500ms）
- URL 匹配规则（可选）：支持通配符模式过滤生效页面

#### `assets/css/ai_abstractor.css`

- CSS 变量体系（`--aa-*`）驱动所有颜色、圆角、过渡
- 适配 `[data-theme=dark]` 暗色模式
- 兼容 Butterfly 主题的 `--heo-*` 变量
- 打字光标闪烁动画（`@keyframes aa-blink`）
- 响应式适配（`max-width: 768px`）

---

## 安装方法

### 1. 下载插件

```text
git clone https://github.com/ipan233/AIAbstractor.git
```

或直接下载 ZIP 并解压。

### 2. 上传到服务器

将文件夹重命名为 `AIAbstractor`（必须与此完全一致），上传到 Typecho 插件目录：

```text
/path/to/typecho/usr/plugins/AIAbstractor/
```

目录结构应为：

```text
usr/plugins/AIAbstractor/
├── Plugin.php
├── api.php
└── assets/
     ├── css/ai_abstractor.css
     └── js/ai_abstractor.js
```

### 3. 启用插件

登录 Typecho 后台 → **控制台** → **插件** → 找到 **AIAbstractor** → 点击**启用**。

### 4. 填写插件配置

点击「设置」，按实际情况填写各配置项（详见[配置说明](#配置说明)），保存后即可生效。

---

## 配置说明

| 配置项 | 默认值 | 说明 |
|--------|--------|------|
| **OpenAI API Base** | `https://api.openai.com/v1` | API 基础地址，可替换为企业网关或第三方代理（末尾不加 `/`）|
| **OpenAI API Key** | —— | 服务端使用，不会传递到前端 |
| **默认模型** | `gpt-5.3` | 后台默认模型，如 `gpt-4o-mini`、`gpt-4o`、`o4-mini` 等 |
| **Temperature** | `0.3` | 生成随机性，范围 `0–2`，越低越稳定 |
| **最大 Tokens** | `256` | 单次响应 token 上限，建议 `64–512` |
| **提取源文本长度上限** | `720` | 从页面采集的最大字符数，超出部分截断 |
| **文章容器选择器** | `#article-container` | 正文容器的 CSS 选择器，根据主题调整 |
| **频率限制（次/分钟）** | `5` | 每个 IP 每分钟最多调用次数，`0` 表示不限制 |
| **自动插入前端资源** | 开启 | 自动将 CSS 和 JS 注入页面，关闭后需手动引入 |

> **安全提示**：API Key 仅存储于 Typecho 数据库配置中，由服务端读取后代为请求，不会出现在任何前端输出中。

---

## 使用说明

启用插件并完成配置后，访客在文章页面会看到插入于正文顶部的摘要卡片。

**访客操作流程：**

1. 打开任意文章页面
2. 页面顶部自动出现「AI摘要」卡片，初始状态显示「加载中...」
3. 若本地有缓存（7 天内），直接展示缓存摘要
4. 若无缓存，自动触发生成，显示打字光标动画
5. 生成完成后摘要文字替换加载状态，并写入本地缓存
6. 点击「生成」按钮可随时手动重新生成

**无需：** 修改主题模板、手动插入代码片段、配置伪静态规则。

---

## API 接口文档

插件提供两套等价的后端接口，选用其一即可：

### 方式一：`api.php` 直连（推荐，兼容所有服务器）

```text
POST /usr/plugins/AIAbstractor/api.php
Content-Type: application/json
```

### 方式二：Typecho Action 路由

```text
POST /action/ai-abstractor
Content-Type: application/json
```

> 需服务器开启 PATH_INFO 或配置伪静态，否则请使用方式一。

### 请求体

```text
{
  "q": "需要生成摘要的文章文本内容",
  "model": "gpt-4o-mini"   // 可选，必须在服务端白名单内
}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `q` | string | 是 | 待摘要的文本，建议 720 字以内 |
| `model` | string | 否 | 指定模型，不在白名单则降级为后台配置的默认模型 |

### 响应体

**成功（HTTP 200）：**

```text
{
  "text": "AI 生成的摘要内容"
}
```

**失败示例：**

```text
// HTTP 400
{ "error": "Missing q" }

// HTTP 429
{ "error": "Too Many Requests" }

// HTTP 500
{ "error": "Bad response from AI provider (HTTP 401)" }
```

### 模型白名单

前端可通过 `model` 字段请求以下模型（超出白名单则使用后台默认值）：

```text
gpt-4o-mini
gpt-4o
o4-mini
gpt-3.5-turbo
```

---

## 前端配置项

前端通过 `window.AIAbstractorConfig` 对象控制行为。后台自动注入 `window.AIAbstractorConfigOverrides` 覆盖默认值，也可在主题模板中手动扩展：

```text
<script>
  window.AIAbstractorConfig = {
    appName:         'AI摘要',          // 卡片标题文字
    classNamePrefix: 'ai-abstractor',   // CSS 类名前缀
    apiEndpoint:     '/usr/plugins/AIAbstractor/api.php',  // 后端接口地址
    postSelector:    '#article-container',  // 文章容器 CSS 选择器
    wordLimit:       720,               // 提取文本最大字符数
    model:           'gpt-4o-mini',     // 请求使用的模型
    cacheMaxAge:     604800000,         // 缓存有效期（毫秒），默认 7 天
    postURL:         undefined,         // URL 匹配规则（支持通配符），undefined 表示全站生效
    debug:           false,             // 调试模式，开启后输出 console.log
  };
</script>
```

---

## 常见问题 FAQ

### 为什么不用 Typecho Action 路由？

许多服务器（尤其是宝塔默认配置）未开启 PATH_INFO 或伪静态，导致 `/action/ai-abstractor` 返回 404。`api.php` 直连模式绕过了路由机制，兼容所有服务器环境。两种方式均已实现，可按需选用。

### 文章容器选择器怎么填？

在浏览器开发者工具中检查文章正文区域的 `id` 或 `class`，常见值：

| 主题 | 选择器 |
|------|--------|
| Butterfly | `#article-container` |
| Handsome | `.post-body` |
| Single | `.post-content` |
| 其他主题 | 自行检查 DOM 结构 |

### 摘要字数太多/太少怎么调整？

- 字数控制由 Prompt 决定（当前为「30字以内」），如需修改请编辑 `api.php` 或 `Plugin.php` 中的 `system` / `user` Prompt。
- 同时适当调整后台「最大 Tokens」配置（建议 128–256）。

### 如何使用国内代理或第三方 OpenAI 兼容 API？

在「OpenAI API Base」中填入代理服务的 v1 端点地址即可，例如：

```text
https://your-proxy.example.com/v1
```

插件请求路径为 `{API Base}/chat/completions`，兼容任何遵循 OpenAI Chat Completions 格式的接口。

### 摘要卡片没有出现怎么办？

1. 检查浏览器控制台是否有报错
2. 确认「文章容器选择器」与当前主题的 DOM 结构匹配
3. 确认插件已启用且「自动插入前端资源」已开启
4. 检查是否存在 JS 冲突（可开启 `debug: true` 查看日志）

### 缓存如何清除？

摘要缓存存储在访客浏览器的 LocalStorage 中，Key 格式为 `ai_abstractor_v2_{pathname}`。可在浏览器开发者工具的「Application → Local Storage」中手动删除，或等待 7 天自动过期。

---

## 开发说明

### 本地调试

1. 在插件后台将「文章容器选择器」改为本地主题的正确选择器
2. 在主题模板或浏览器控制台中执行：
   ```text
   window.AIAbstractorConfig.debug = true;
   ```
3. 刷新页面，控制台将输出缓存命中、文本长度、请求状态等调试信息

### 修改 Prompt

摘要 Prompt 硬编码在 `api.php`（第 ~65 行）和 `Plugin.php`（`callOpenAI` 方法）中：

```text
$prompt = '生成30字以内的摘要供读者阅读，不要带开场白，文章内容：' . $q;
```

可按需修改摘要长度、语言风格或输出格式。

### 扩展模型白名单

在 `api.php` 中修改 `$allowedModels` 数组，在 `Plugin.php` 中修改 `ALLOWED_MODELS` 常量，两处保持同步。

---

## 贡献指南

欢迎提交 Issue 和 Pull Request。

- **Bug 反馈**：请附上 Typecho 版本、PHP 版本、服务器环境及浏览器控制台截图
- **功能建议**：请先开 Issue 讨论再提 PR
- **代码规范**：PHP 遵循 PSR-2，JS 保持单文件 IIFE 无依赖风格，CSS 遵循现有变量命名体系

---

## 许可证

本项目基于 [MIT License](./LICENSE) 开源。

---

*如果本插件对你有帮助，欢迎 Star 支持。*
