# GIR · GitHub Idea Radar / 灵猎雷达 项目上下文

## 项目名称

正式名称：

```text
GIR · GitHub Idea Radar
灵猎雷达
```

副标题：

```text
每天发现值得研究和学习的 GitHub 灵感项目
```

一句话介绍：

```text
GIR 灵猎雷达会自动追踪 GitHub 最新热门项目，并用 AI 生成中文结构化分析，帮用户快速发现值得研究和学习的开源灵感。
```

## 项目缘起

这个项目是为了给一台闲置的终身虚拟主机安排一个长期可运行的实际用途，免得 PHP 和 MySQL 资源浪费。虚拟主机能力有限，但适合承载轻量页面、数据展示和接口接收；重抓取、批量分析和外部 API 调用则交给 GitHub Actions，形成一个适合传统 PHP 空间的低维护项目。

建议仓库名：

```text
github-idea-radar
```

## 当前本地路径

当前 Codex workspace：

```text
/Volumes/RuiRui4TB/CloudBackup/Mac/code/projects /sanfeng/30G
```

如果迁移到新目录，建议复制整个目录，但不要提交 `.env` 和 `.netrc`。

## 线上环境

站点：

```text
http://gir.likeheng.com/
http://github.likeheng.com/
```

虚拟主机：

```text
FTP 账号：ftp6398841
FTP 地址：171.80.8.109（共享 IP）
空间状态：4K/100M
共享 IP：171.80.8.109
Web 服务器：Microsoft-IIS/8.5
系统：Windows NT WIN-6BM95UV21-9 6.3 build 9600（Windows Server 2012 R2 Datacenter Edition）AMD64
PHP：7.2.5
PHP SAPI：cgi-fcgi
PHP 内存限制：128M
PHP POST 限制：20M
PHP 上传限制：20M
PHP 最长执行时间：300 秒
脚本路径：D:\localuser\ftp6398841\Web\x.php
数据库名称：sfydb_6398841
数据库地址：127.0.0.1
数据库账号：sfydb_6398841
MySQL server：5.1.33-community
MySQL 空间：100M
MySQLi client：mysqlnd 5.0.12-dev
站点协议：当前用 HTTP
```

可用 PHP 扩展：

```text
mysqli
pdo_mysql
curl
openssl
gd
zip
mbstring
fileinfo
sockets
SimpleXML
json
PDO
dom
xml
xmlreader
xmlwriter
soap
xmlrpc
gettext
Phar
```

不可依赖：

```text
Docker
Node.js 常驻服务
Python 常驻服务
WebSocket
本地 AI 模型
Redis
Swoole
SQLite3
现代 MySQL 特性
```

## 密钥与隐私文件

本地真实密钥文件：

```text
.env
.netrc
```

权限已设为：

```text
600
```

`.gitignore` 已忽略：

```text
.netrc
.ftp*
.env
.env.*
!.env.example
.DS_Store
```

虚拟主机正式环境优先读取：

```text
FTP 根目录/Data/.env
```

其次才读取：

```text
Web/.env
```

正式环境不要把 `.env` 放在 `Web/` 公开目录。

## 已知凭据位置

不要在公开仓库里保存真实值。

当前真实值已放在：

```text
.env
.netrc
FTP /Data/.env
```

`.env.example` 只放占位模板。

## GitHub 连通性结论

虚拟主机访问 GitHub 的测试结果：

```text
http://github.com/        -> 301 到 https
http://api.github.com/    -> 301 到 https
http://raw.githubusercontent.com/... -> 301 到 https
http://codeload.github.com/...       -> 301 到 https
```

HTTPS 严格证书校验失败：

```text
curl error 60: SSL certificate problem: unable to get local issuer certificate
```

关闭证书校验后，部分 HTTPS 可访问：

```text
github.com          HTTP 206
api.github.com      HTTP 206
codeload.github.com HTTP 200
raw.githubusercontent.com 本次测试为 404，说明网络能到，但测试 URL/Range 结果不稳定
```

结论：

```text
不是只能 HTTP。
GitHub 基本强制 HTTPS。
问题是虚拟主机 PHP cURL 缺 CA 根证书，直接抓 GitHub 不稳定。
```

因此架构定为：

```text
GitHub Actions 抓 GitHub + 调 DeepSeek
PHP 虚拟主机接收结果 + MySQL 入库 + 页面展示
```

## 项目架构

```text
GitHub Actions
  定时抓 GitHub 热门项目
  读取 README
  调 DeepSeek API 生成中文结构化 JSON
  POST 到 PHP 接收接口

PHP 虚拟主机
  接收 JSON
  写入 MySQL
  展示今日榜、本周榜、项目详情、运行日志

MySQL
  保存项目、AI 分析报告、运行记录
```

## 当前已完成

### 本地文档

```text
README.md
PROJECT_CONTEXT.md
docs/DEVELOPMENT.md
docs/RESEARCH.md
.env.example
```

### PHP 站点

```text
config/config.php
lib/bootstrap.php
lib/helpers.php
lib/db.php
lib/auth.php
lib/repositories.php
public/index.php
public/weekly.php
public/project.php
public/_layout.php
public/assets/app.css
admin/index.php
api/receive_projects.php
api/trigger_run.php
api/install_schema.php
database/schema.sql
```

### GitHub Actions

```text
actions/discover.js
actions/package.json
.github/workflows/discover.yml
.github/workflows/deploy.yml
```

`deploy.yml`：

```text
push 到 main 或手动 workflow_dispatch 时，把 PHP 站点业务文件上传到 FTP Web/ 目录。
不自动上传 .env、.netrc、x.php、mysql_probe.php。
```

### 数据库

已通过 `api/install_schema.php` 在线创建 3 张表：

```text
projects
project_reports
runs
```

MySQL 当前已有一条测试数据：

```text
example/demo-ai-tool
```

它是用于验证接收、入库、展示链路的测试项目，接入真实 Actions 后可删除。

## 已部署线上路径

FTP 目录结构：

```text
Web/
  old/
    PicUploader/

Data/
  old/
    1.1-17.10.30-release.zip
    1.sql
    P.rar
    Writing.zip

Log/
  UnZipLog.txt
  W3SVC437/
```

说明：

```text
新 FTP 空间原有 Web 内容已移动到 Web/old/。
新 FTP 空间原有 Data 文件已移动到 Data/old/。
根目录和 Log 目录受虚拟主机权限限制，不适合作为项目部署目录。
```

计划部署结构：

```text
Web/
  index.php
  weekly.php
  project.php
  assets/app.css
  public/_layout.php
  admin/index.php
  api/install_schema.php
  api/receive_projects.php
  api/trigger_run.php
  config/config.php
  lib/*.php
  database/schema.sql

Data/
  .env
```

线上可访问页面：

```text
http://gir.likeheng.com/index.php
http://gir.likeheng.com/weekly.php
http://gir.likeheng.com/project.php?id=1
http://gir.likeheng.com/admin/index.php
```

## 已验证

本地：

```text
PHP 语法检查通过
node --check actions/discover.js 通过
```

线上：

```text
MySQL 建表成功
receive_projects.php 模拟 POST 成功
首页能显示测试项目
详情页能显示测试报告
后台能显示运行记录区域
```

## 安全注意

后台页面曾短暂把 trigger token 放进表单 URL，已修复并轮换 token。

当前状态：

```text
admin/index.php 不再输出 trigger token
手动触发按钮暂时禁用
```

启用手动触发前必须先加后台登录或其它访问控制。

`api/trigger_run.php` 已实现 workflow_dispatch 调用，但需要：

```text
GITHUB_OWNER
GITHUB_REPO
GITHUB_TOKEN
GITHUB_WORKFLOW
APP_TRIGGER_TOKEN
```

且不应直接把 token 暴露在前端。

## GitHub Actions Secrets

接入 GitHub Actions 时需要配置：

```text
DEEPSEEK_API_KEY
DEEPSEEK_BASE_URL
DEEPSEEK_MODEL
APP_INGEST_URL
APP_INGEST_TOKEN
```

推荐：

```text
DEEPSEEK_BASE_URL=https://api.deepseek.com
DEEPSEEK_MODEL=deepseek-chat
APP_INGEST_URL=http://gir.likeheng.com/api/receive_projects.php
```

`APP_INGEST_TOKEN` 从本地 `.env` 读取，不要写入公开文件。

## DeepSeek 分析模板字段

Actions 脚本要求 AI 输出 JSON，核心字段：

```text
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

枚举：

```text
difficulty: 低 / 中 / 高
recommendation: 收藏 / 研究 / 可复刻 / 暂不关注
```

## 参考项目

调研记录在：

```text
docs/RESEARCH.md
```

主要借鉴：

```text
github-trending-repos
agents-radar
GitFeed
HubLens
OSSInsight
```

取舍：

```text
不做实时大数据。
不做常驻爬虫服务。
不做复杂登录系统。
第一版只做 GitHub Actions + DeepSeek + PHP 接收展示。
```

## 下一步任务

优先级建议：

1. 把站点品牌从“AI 项目侦探站”改成：

```text
GIR · GitHub Idea Radar
灵猎雷达
每天发现值得研究和学习的 GitHub 灵感项目
```

2. 增加 RSS：

```text
http://gir.likeheng.com/rss.php
```

RSS 内容：

```text
最新日报项目
每个 item 指向 project.php?id=...
description 使用 one_sentence + summary_zh
```

3. 清理或隐藏临时探针：

```text
x.php
x_zh.php
mysql_probe.php
github_scheme_probe.php
github_probe.php
server_probe.php
```

保留探针会暴露主机环境信息，正式上线前建议删除。

4. 创建 GitHub 仓库：

```text
github-idea-radar
```

5. 配置 GitHub Secrets 并跑一次真实 Actions。

6. 加后台登录，再启用手动触发按钮。

7. 删除测试数据 `example/demo-ai-tool`。

## 常用验证命令

PHP 语法：

```bash
find config lib public admin api -name '*.php' -print0 | xargs -0 -n1 php -l
```

Actions 脚本：

```bash
node --check actions/discover.js
```

FTP 上传单文件示例：

```bash
curl --ftp-pasv --netrc-file .netrc -T public/assets/app.css ftp://171.80.8.109/Web/assets/app.css
```

调用建表接口：

```bash
curl "http://gir.likeheng.com/api/install_schema.php?token=APP_TRIGGER_TOKEN"
```

接收接口：

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer APP_INGEST_TOKEN" \
  --data-binary @payload.json \
  "http://gir.likeheng.com/api/receive_projects.php"
```
