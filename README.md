# WeChatSync

同步 Typecho 博客文章到微信公众号草稿箱的插件。

## 功能特性

- 一键同步文章到微信公众号草稿箱
- 自动上传封面图片（支持 WebP 格式自动转换）
- 自动处理文章内容图片并上传到微信素材库
- Markdown 内容自动转换为微信兼容格式
- 代码高亮支持
- 支持配置文章作者、摘要字段
- 支持选择是否添加原文链接
- 美观的同步进度动画

## 安装使用

### 1. 安装

将插件目录重命名为 `WeChatSync`，上传到 Typecho 的 `usr/plugins/` 目录，然后在后台启用插件。

### 2. 配置

在插件设置页面填写以下信息：

- **公众号 AppID**：微信公众平台的 AppID
- **公众号 Secret**：微信公众平台的 Secret
- **公众号文章作者**：默认使用的作者名称
- **文章摘要字段**：自定义字段名称，默认为 `abstract`
- **添加原文链接**：选择是否在文章中添加指向原文的链接

### 3. 使用

在文章管理页面或写文章页面，点击「发布公众号」按钮即可同步文章到微信草稿箱。

## 目录结构

```
WeChatSync/
├── Plugin.php              # 插件主类
├── Action.php             # 动作入口
├── Library/
│   ├── WeChatClient.php   # 微信 API 封装
│   ├── ContentProcessor.php # 内容处理
│   └── SyncRenderer.php   # 同步流程
├── Assets/
│   ├── admin.css          # 后台样式
│   └── admin.js           # 后台脚本
├── parsedown/              # Markdown 解析
└── geshi/                 # 代码高亮
```

## 注意事项

- 封面图片为 WebP 格式时会自动转换为 JPG 再上传
- 文章需要已发布状态才能同步
- 受密码保护的文章无法同步
- 文章内容过短（小于 100 字符）无法同步

## 作者

[TeohZY](https://blog.teohzy.com)

## 版本

1.0.1
