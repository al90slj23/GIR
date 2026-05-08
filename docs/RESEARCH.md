# 同类项目调研

## 借鉴对象

### github-trending-repos

项目地址：https://github.com/vitalets/github-trending-repos

可借鉴点：

- 每日/每周固定时间抓取 GitHub Trending
- 按语言拆分订阅主题
- 用 GitHub 原生通知完成分发

落地到本项目：

- 保留 daily/weekly 两种周期
- 第一版先按 AI/LLM/Agent 等主题抓取
- 展示层改为 PHP + MySQL 网站，而不是 GitHub Issue 通知

### agents-radar

项目地址：https://github.com/duanyytop/agents-radar

可借鉴点：

- 使用 GitHub Actions 每日定时运行
- 聚合多源信号
- 生成中文/英文 digest
- 保存历史报告并提供 Web UI
- 支持手动触发和定期 rollup

落地到本项目：

- 抓取和 AI 分析全部放 GitHub Actions
- PHP 虚拟主机只接收和展示
- 报告保留 daily/weekly/manual 三种类型

### GitFeed

站点：https://www.gitfeed.dev/

可借鉴点：

- 用 GitHub API 数据发现趋势项目
- 用 AI 生成摘要
- 按 star acceleration 和时间衰减排序
- 项目详情页强调“这个项目做什么、适合谁”

落地到本项目：

- 第一版先用 stars/forks/pushed_at 的简化评分
- 后续增加 star acceleration 字段
- AI 模板固定输出“适合谁、可借鉴点、PHP 适配度”

### HubLens

站点：https://www.hublens.dev/

可借鉴点：

- 每日榜单
- GitHub + Hacker News 信号
- AI 双语摘要
- 标签分类和 24h movers

落地到本项目：

- 第一版只做 GitHub
- 后续可加入 HN/Product Hunt/Hugging Face
- 页面保留分类和评分字段，为后续扩展做准备

### OSSInsight

站点：https://ossinsight.io/

可借鉴点：

- GitHub 开源数据分析
- Trending Repos 按综合分排序
- 提供语言筛选、星标、Fork、活跃度等维度

落地到本项目：

- MySQL 中保留 stars、forks、language、pushed_at
- 后续可增加趋势分和语言筛选

## 本项目取舍

不做：

- 实时 GitHub 事件流
- 大规模数据仓库
- 自建爬虫服务
- 复杂登录系统
- 本地模型分析

第一版只做：

- GitHub Actions 定时抓取
- DeepSeek 分析
- PHP 接收 JSON
- MySQL 入库
- 今日榜、本周榜、详情页、运行日志
