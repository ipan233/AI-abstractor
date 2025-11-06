# ✅ **AIAbstractor – 基于 OpenAI 的 Typecho 文章摘要插件**

感谢[@zhheo](https://github.com/zhheo/Post-Abstract-AI)的原始代码！很喜欢原版的这个AI摘要UI，所以想着移植到typecho博客上来。

AIAbstractor 是一款为 **Typecho 博客系统** 开发的智能文章摘要插件。
插件会在文章页面自动插入一个摘要按钮，通过调用 OpenAI API 生成高质量中文摘要，支持自定义模型、文本长度、自动注入脚本等功能。

插件已适配：

✅ Typecho 1.1
✅ Typecho 1.2 - 1.2.1
✅ 宝塔 / Nginx / open_basedir 限制环境
✅ 不依赖 PATH_INFO（兼容所有服务器）

---

# 📦 **功能特性**

* ✅ 一键生成文章摘要
* ✅ 前端自动插入「AI 摘要」按钮
* ✅ 自动提取文章内容（可自定义 CSS 选择器）
* ✅ 调用 OpenAI / 自建代理 API
* ✅ 可指定模型、温度（temperature）、最大 tokens
* ✅ 支持 JSON POST API
* ✅ 兼容所有服务器环境（不使用 Action，不需要 PATH_INFO）
* ✅ 全站无侵入、不修改主题文件
* ✅ 支持 HTTPS、跨域环境

---

# 📥 **安装方法**

1. 下载插件代码，将文件夹命名为：

```
AIAbstractor
```

路径结构应为：

```
/usr/plugins/AIAbstractor/
```

2. 插件目录应包含：

```
AIAbstractor/
 ├── Plugin.php
 ├── api.php
 └── assets/
      ├── css/ai_abstractor.css
      └── js/ai_abstractor.js
```

3. 登录 Typecho 后台 → 控制台 → 插件 → 启用 AIAbstractor

4. 打开插件设置界面，根据你的环境填写：

* ✅ OpenAI API Base
* ✅ API Key
* ✅ 默认模型（如 gpt-4o-mini）
* ✅ 温度 / 最大 tokens
* ✅ 文章内容选择器（默认：#article-container）
* ✅ 是否自动注入前端脚本

保存后即可使用。

---

# 🔧 **配置说明**

| 配置项           | 说明                                    |
| ------------- | ------------------------------------- |
| API Base      | 如：`https://api.openai.com/v1` 或你的代理地址 |
| API Key       | OpenAI 密钥，不会暴露给前端                     |
| 模型名           | gpt-4o-mini、gpt-4o、o4-mini 等          |
| Temperature   | 0–2，数值越低越稳定                           |
| Max Tokens    | AI 输出长度限制                             |
| Post Selector | 网页正文的 CSS 选择器                         |
| 自动注入          | 自动加载 CSS + JS                         |

---

# 🚀 **使用方法**

启用插件后，访客在阅读文章页面时，会看到一个「AI 摘要」按钮。

点击后：

* 插件前端会提取正文内容
* 通过 `api.php` POST 请求发送到你的服务器
* 你的服务器再转发到 OpenAI API
* 返回生成好的摘要并显示在页面中

无需额外配置主题，也不需要修改模板文件。

---

# 🔌 **API 说明（服务器端）**

插件提供以下后端接口：

```
POST /usr/plugins/AIAbstractor/api.php
```

请求体（JSON）：

```json
{
  "q": "需要摘要的文本",
  "model": "可选，覆盖默认模型"
}
```

返回格式：

```json
{
  "text": "AI 生成的摘要内容"
}
```

---

# ✅ **兼容性说明**

此插件已专门为复杂环境进行优化，支持：

* ✅ 宝塔 open_basedir 限制
* ✅ 无 PATH_INFO 的服务器
* ✅ Nginx + PHP-FPM
* ✅ Apache / LiteSpeed
* ✅ HTTPS、反代、CDN
* ✅ 主题选择器自适配

无需 Action 路由，不依赖 Typecho PATH_INFO。

---

# 🧱 **文件结构说明**

| 文件                           | 作用                       |
| ---------------------------- | ------------------------ |
| Plugin.php                   | Typecho 插件主文件            |
| api.php                      | 后端 OpenAI 代理（不依赖 Action） |
| assets/js/ai_abstractor.js   | 前端主逻辑                    |
| assets/css/ai_abstractor.css | 按钮样式                     |

---

# 🩺 **常见问题 FAQ**

### ❓ 为什么不用 Typecho Action？

许多服务器未开启 PATH_INFO 或伪静态，导致 Action 404。
为了兼容所有环境，因此本插件使用 `api.php` 方式。

### ❓ 为什么 API Key 不存放在前端？

前端存 Key 会泄露，本插件在服务器侧中转，确保安全。

### ❓ 如果摘要内容过长怎么办？

调整插件设置中的 maxTokens。

---

# 🤝 **贡献与支持**

欢迎你提交体验问题、改进建议或 Pull Request。
你也可以 Fork 本项目，并用于你自己的 Typecho 扩展工具中。

---

