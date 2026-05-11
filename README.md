# GIR · 灵猎雷达

面向传统 PHP 虚拟主机的 AI 项目发现与分析站点。

目标是每天/每周自动发现 GitHub 热门项目，使用 DeepSeek OpenAI-compatible API 生成中文分析报告，并通过 PHP + MySQL 展示榜单、归档和项目详情。

## 项目缘起

这个项目是为一台闲置的终身虚拟主机安排的实际用途。主机环境偏传统，但仍然可以稳定运行 PHP 和 MySQL；与其让资源长期空置，不如把它做成一个轻量、可维护、持续产出的 GitHub 项目发现站，顺便把 PHP 的价值用起来。

## 当前环境

- 虚拟主机：Windows Server + IIS，PHP 7.2（`cgi-fcgi`）
- FTP：地址由 `.env` 提供，共享 IP
- 域名：`gir.likeheng.com`、`github.likeheng.com`
- MySQL：`5.1.33-community`，库名与账号由 `.env` 提供，`127.0.0.1`，100M
- PHP 限制：内存 `128M`，POST `20M`，上传 `20M`，最长执行 `300s`
- 可用扩展：`mysqli`、`pdo_mysql`、`curl`、`openssl`、`gd`、`zip`、`mbstring`、`fileinfo`、`sockets`、`SimpleXML`
- 站点协议：当前使用 HTTP
- GitHub 访问：HTTP 会跳转 HTTPS；PHP cURL 严格证书校验失败，原因是主机 CA 证书链问题

## 推荐架构

```text
GitHub Actions
  定时抓 GitHub 项目
  调 DeepSeek 分析
  POST JSON 到 PHP 接口

PHP 虚拟主机
  接收分析结果
  写入 MySQL
  展示今日榜 / 本周榜 / 项目详情 / 运行日志

MySQL
  保存项目元信息、AI 报告、任务运行记录
```

## 开发规范

项目边界、目录结构、数据库兼容性、密钥规则和部署规则见：

```text
docs/DEVELOPMENT.md
```

同类项目调研见：

```text
docs/RESEARCH.md
```

## 密钥文件

真实密钥只放本地 `.env` 和 `.netrc`，不要提交到 Git。

模板文件：

```text
.env.example
```

## 自动部署

代码推送到 GitHub `main` 分支后，`Deploy to FTP` workflow 会把 PHP 站点文件上传到虚拟主机 `Web/` 目录。

需要在 GitHub Secrets 中配置：

```text
FTP_HOST
FTP_USER
FTP_PASSWORD
FTP_WEB_DIR
APP_INGEST_URL
APP_INGEST_TOKEN
APP_TRIGGER_TOKEN
DEEPSEEK_API_KEY
DEEPSEEK_BASE_URL
DEEPSEEK_MODEL
GIR_GITHUB_SEARCH_TOKEN
```

`GIR_GITHUB_SEARCH_TOKEN` 用于 GitHub Search 补跑，建议使用只授权本仓库的 fine-grained PAT，避免 Actions 内置安装 token 被搜索接口限流。

## 自动补跑与搜索策略

`discover.yml` 每 30 分钟会跑一次 backlog，只处理未解读项目，直到清空。每日采集 09:00 北京时间、每周采集周一 09:30 北京时间。

前台 `/search.php` 搜索只做两件事：同步 GitHub 搜索结果 + 把结果入库成 raw 行，不再即时触发 GitHub Actions；新入库项目会在下一次 backlog 时自动被 GIR 解读，避免恶意搜索关键词打爆 Actions 和 DeepSeek 额度。

## cURL 证书校验

如果主机缺 CA 根证书，可以把一份 `cacert.pem` 放在以下任一位置并重启 PHP：

```text
Web/config/cacert.pem
Data/cacert.pem
```

或者在 `.env` 里设置 `CA_BUNDLE_PATH` 指向绝对路径。命中任一路径后，PHP cURL 会走严格证书校验，否则回退到关闭校验。

## 运行日志

`api/receive_projects.php` 和 `api/trigger_run.php` 的异常会写到 `Data/logs/app.log`（不存在会尝试创建）。

探针文件 `x.php` 不参与自动部署，只在需要诊断主机环境时手动上传；需要时可在本地自己写一份带自定义 token 的探针，诊断后删除，不要提交到仓库。
