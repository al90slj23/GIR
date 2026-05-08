# 开发规范

## 项目定位

本项目是运行在传统 PHP 虚拟主机上的轻量 AI 项目发现站，不是常驻后台服务，也不是本地 AI 模型运行环境。

核心目标：

- 自动发现 GitHub 每日/每周热门项目
- 使用 DeepSeek API 生成中文结构化分析
- 将结果写入 MySQL
- 用 PHP 页面展示榜单、归档、详情和运行日志
- 支持后台按钮触发 GitHub Actions 手动更新

## 硬边界

虚拟主机上禁止依赖：

- Docker
- Node.js 常驻服务
- Python 常驻服务
- WebSocket 长连接
- 本地 AI 模型
- Redis、队列服务、后台 worker
- Composer 运行时强依赖，除非确认虚拟主机可用

虚拟主机只负责：

- PHP 页面渲染
- MySQL 读写
- 接收 GitHub Actions POST 的 JSON
- 调外部 AI API 的轻量请求
- 触发 GitHub Actions workflow_dispatch

抓取 GitHub、批量读取 README、批量调用 AI 分析，默认放在 GitHub Actions 里做。

## 技术栈

虚拟主机：

- PHP 7.2 兼容语法
- 原生 PHP + 少量 HTML/CSS/JS
- MySQLi 或 PDO MySQL
- MySQL 5.1 兼容 SQL

自动任务：

- GitHub Actions
- Node.js 脚本优先，用于抓 GitHub API 和调 DeepSeek
- 结果通过 HTTP POST 推送给 PHP 接口

AI：

- DeepSeek OpenAI-compatible API
- API Key 只放服务端或 GitHub Secrets
- 前端永远不出现 API Key

## 目录结构

第一版建议结构：

```text
config/
  config.php

lib/
  db.php
  http.php
  auth.php
  render.php

public/
  index.php
  weekly.php
  project.php
  assets/

admin/
  index.php
  runs.php
  trigger.php

api/
  receive_projects.php
  trigger_run.php

database/
  schema.sql
  migrations/

actions/
  fetch-projects.js
  deepseek-analyze.js
  payload-schema.json

docs/
  DEVELOPMENT.md
```

虚拟主机 FTP 的 `Web/` 目录应只放可公开访问的入口文件和必要资源。配置文件如果主机允许，应尽量放到 Web 根目录外；如果不允许，配置文件必须使用 `.php` 后缀并禁止直接输出。

## 密钥管理

本地真实密钥：

- `.env`
- `.netrc`
- 虚拟主机 FTP 根目录下的 `Data/.env`

这些文件必须被 `.gitignore` 忽略。

仓库里只能提交：

- `.env.example`
- 不含真实密码的配置模板

GitHub Actions 使用 GitHub Secrets：

- `DEEPSEEK_API_KEY`
- `APP_INGEST_URL`
- `APP_INGEST_TOKEN`
- `APP_TRIGGER_TOKEN`

PHP 接口认证：

- `receive_projects.php` 必须校验 token
- token 放在 PHP 后端配置中
- 不接受无 token 写入
- 写入接口只接受 `POST`
- JSON body 必须校验字段和长度

虚拟主机部署时配置读取优先级：

```text
../Data/.env
Web/.env
```

正式环境不要把 `.env` 放在 `Web/` 公开目录。

## 数据库兼容性

MySQL 当前为 `5.1.33-community`，必须按老版本 MySQL 写 SQL。

禁止使用：

- `JSON` 字段类型
- 窗口函数
- CTE / `WITH`
- `utf8mb4` 上过长唯一索引
- `GENERATED COLUMN`
- `ON DUPLICATE KEY UPDATE` 中依赖新版本特性

推荐：

- 字符集优先使用 `utf8`
- 文本大字段使用 `TEXT` 或 `MEDIUMTEXT`
- JSON 数据用 `TEXT` 保存
- 时间字段使用 `DATETIME`
- 主键使用 `INT UNSIGNED AUTO_INCREMENT`
- 常用查询字段加普通索引

核心表：

```text
projects
project_reports
runs
```

## GitHub 抓取规则

默认由 GitHub Actions 抓取，不由虚拟主机直接抓。

第一版抓取来源：

- GitHub Search API
- `stars:>100 pushed:>YYYY-MM-DD sort:stars`
- `created:>YYYY-MM-DD sort:stars`
- `topic:ai sort:stars`
- `topic:llm sort:stars`
- `topic:agent sort:stars`

抓取限制：

- 每次最多分析 10 到 20 个项目
- README 只截取必要长度
- 失败项目记录日志，不阻塞整批任务
- 同一个项目同一天不重复分析

## AI 输出模板

DeepSeek 必须输出可解析 JSON，并包含这些字段：

```text
name
one_sentence
project_type
problem
tech_stack
target_users
play_score
useful_score
php_fit_score
difficulty
is_suitable_for_this_host
ideas_to_reuse
risks
recommendation
summary_zh
```

评分范围统一为 1 到 10。`difficulty` 只允许：

```text
低
中
高
```

`recommendation` 只允许：

```text
收藏
研究
可复刻
暂不关注
```

## 页面边界

第一版只做实际可用页面：

- 今日榜
- 本周榜
- 项目详情
- 后台运行记录
- 手动触发按钮

不要做复杂营销页，不做登录系统大工程。后台第一版可以用固定 token 或简单密码保护。

## 手动触发规则

网站后台按钮不直接抓 GitHub。

流程：

```text
用户点击立即更新
PHP 校验后台权限
PHP 调 GitHub workflow_dispatch API
GitHub Actions 开始抓取和分析
Actions POST 结果回 PHP
PHP 写入 MySQL
页面展示最新结果
```

## 错误处理

所有入口必须：

- 不显示 PHP warning 给普通用户
- 记录可读错误到 `runs.error_message`
- 对外返回简短错误
- 不输出密钥、请求头、数据库密码

AI/API 失败：

- 单个项目失败只标记失败
- 整批任务继续处理其他项目
- 超时或限流写入运行日志

## 安全规则

- API Key 不进前端
- MySQL 密码不进前端
- GitHub Token 不进前端
- 探针文件测试完成后删除
- 所有写入接口必须有 token
- 所有输出 HTML 的用户数据必须转义
- 后台入口必须有访问控制

## 部署规则

FTP 上传目标目录：

```text
Web/
```

部署方式：

- 本地开发后用 FTP 上传
- 后续可用 GitHub Actions 自动 FTP 部署
- `.env`、`.netrc`、探针文件不得部署为公开长期文件

临时探针：

- `x.php`
- `mysql_probe.php`
- `github_scheme_probe.php`

这些只用于诊断，不属于正式业务代码。

## 验收标准

第一版完成标准：

- MySQL schema 可在 MySQL 5.1 执行
- `receive_projects.php` 可接收 Actions 推送并入库
- 首页能展示今日项目榜
- 项目详情能展示 AI 分析
- GitHub Actions 可定时运行
- 后台按钮可触发 GitHub Actions
- 失败任务能在后台看到日志
