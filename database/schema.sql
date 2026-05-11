CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  github_id INT UNSIGNED NOT NULL DEFAULT 0,
  name VARCHAR(191) NOT NULL,
  full_name VARCHAR(191) NOT NULL,
  html_url VARCHAR(255) NOT NULL,
  description TEXT,
  stars INT UNSIGNED NOT NULL DEFAULT 0,
  forks INT UNSIGNED NOT NULL DEFAULT 0,
  language VARCHAR(64) NOT NULL DEFAULT '',
  topics TEXT,
  pushed_at DATETIME DEFAULT NULL,
  is_hidden TINYINT UNSIGNED NOT NULL DEFAULT 0,
  admin_status VARCHAR(32) NOT NULL DEFAULT 'new',
  admin_note TEXT,
  discovered_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_full_refresh_at DATETIME DEFAULT NULL,
  zh_readme_checked_at DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_full_name (full_name),
  KEY idx_stars (stars),
  KEY idx_language (language),
  KEY idx_admin_status (admin_status),
  KEY idx_is_hidden (is_hidden),
  KEY idx_discovered_at (discovered_at),
  KEY idx_last_full_refresh (last_full_refresh_at),
  KEY idx_zh_readme_checked (zh_readme_checked_at)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS project_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  run_id INT UNSIGNED DEFAULT NULL,
  period_type VARCHAR(16) NOT NULL DEFAULT 'daily',
  report_date DATE NOT NULL,
  source_platform VARCHAR(64) NOT NULL DEFAULT 'github',
  source_tag VARCHAR(64) NOT NULL DEFAULT '综合',
  source_rank INT UNSIGNED NOT NULL DEFAULT 0,
  source_score DECIMAL(12,2) NOT NULL DEFAULT 0,
  raw_rank_only TINYINT UNSIGNED NOT NULL DEFAULT 0,
  one_sentence VARCHAR(255) NOT NULL DEFAULT '',
  project_type VARCHAR(64) NOT NULL DEFAULT '',
  problem_text TEXT,
  tech_stack TEXT,
  target_users TEXT,
  play_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  useful_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  maturity_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  php_fit_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  difficulty VARCHAR(16) NOT NULL DEFAULT '',
  is_suitable_for_this_host TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ideas_to_reuse TEXT,
  risks TEXT,
  change_note TEXT,
  recommendation VARCHAR(32) NOT NULL DEFAULT '',
  summary_zh TEXT,
  raw_ai_json MEDIUMTEXT,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_project_period_source (project_id, period_type, report_date, source_platform, source_tag),
  KEY idx_report_date (report_date),
  KEY idx_source (source_platform, source_tag),
  KEY idx_source_rank (source_platform, source_tag, source_rank),
  KEY idx_raw_rank (raw_rank_only, one_sentence),
  KEY idx_scores (useful_score, maturity_score, play_score),
  KEY idx_recommendation (recommendation)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS runs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_type VARCHAR(16) NOT NULL DEFAULT 'daily',
  status VARCHAR(16) NOT NULL DEFAULT 'started',
  started_at DATETIME NOT NULL,
  finished_at DATETIME DEFAULT NULL,
  total_found INT UNSIGNED NOT NULL DEFAULT 0,
  total_analyzed INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT,
  source VARCHAR(64) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_run_type (run_type),
  KEY idx_status (status),
  KEY idx_started_at (started_at)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS project_readmes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  readme_path VARCHAR(255) NOT NULL DEFAULT '',
  language_code VARCHAR(32) NOT NULL DEFAULT '',
  source_url VARCHAR(500) NOT NULL DEFAULT '',
  is_translated TINYINT UNSIGNED NOT NULL DEFAULT 0,
  source_language_code VARCHAR(32) NOT NULL DEFAULT '',
  content_md MEDIUMTEXT,
  fetched_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_project_readme (project_id, readme_path, language_code, is_translated),
  KEY idx_project_readmes_project (project_id),
  KEY idx_project_readmes_language (language_code, is_translated)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS gir_analysis_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id INT UNSIGNED NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'pending',
  reason VARCHAR(64) NOT NULL DEFAULT 'missing_analysis',
  priority INT UNSIGNED NOT NULL DEFAULT 100,
  last_analysis_at DATETIME DEFAULT NULL,
  next_run_at DATETIME DEFAULT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_gir_analysis_project (project_id),
  KEY idx_gir_analysis_queue_status (status, priority, next_run_at),
  KEY idx_gir_analysis_queue_project (project_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS platform_fetch_state (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  platform VARCHAR(64) NOT NULL,
  source_tag VARCHAR(128) NOT NULL DEFAULT '',
  period_type VARCHAR(16) NOT NULL DEFAULT 'daily',
  interval_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
  last_fetched_at DATETIME DEFAULT NULL,
  next_fetch_at DATETIME DEFAULT NULL,
  last_status VARCHAR(24) NOT NULL DEFAULT '',
  last_error TEXT,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_platform_fetch_state (platform, source_tag, period_type),
  KEY idx_platform_fetch_due (next_fetch_at, platform)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS github_search_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  query_hash CHAR(40) NOT NULL,
  query_text VARCHAR(500) NOT NULL,
  status VARCHAR(24) NOT NULL DEFAULT 'dispatched',
  last_error TEXT,
  last_dispatched_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_github_search_query (query_hash),
  KEY idx_github_search_dispatched (last_dispatched_at)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(64) NOT NULL,
  setting_value TEXT,
  description VARCHAR(255) NOT NULL DEFAULT '',
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('site_name', 'GIR · 灵猎雷达', '站点名称', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('nav_today_label', '今日榜', '今日榜导航文字', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('nav_weekly_label', '本周榜', '本周榜导航文字', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('nav_admin_label', '后台', '后台导航文字', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('daily_title', '今日 GitHub 灵感榜', '日报页面标题', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('daily_subtitle', '每天发现值得研究和学习的 GitHub 灵感项目。', '日报页面副标题', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('daily_empty_text', '还没有日报数据。完成 GitHub Actions 推送后，这里会显示灵感项目。', '日报空状态文案', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('weekly_title', '本周 GitHub 灵感榜', '周榜页面标题', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('weekly_subtitle', '按周聚合更值得研究、学习和复刻的 GitHub 灵感项目。', '周榜页面副标题', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('weekly_empty_text', '还没有周榜数据。', '周榜空状态文案', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_daily_enabled', '1', '是否启用日报自动采集', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_weekly_enabled', '1', '是否启用周榜自动采集', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_analyze_all', '1', '是否让 GIR 解读处理全部候选项目', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_max_projects', '3', '每次最多分析项目数', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_per_page', '20', '每个平台或分类最多拉取多少候选项目', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_recent_days_daily', '3', '日报搜索最近多少天活跃或创建的项目', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_recent_days_weekly', '14', '周榜搜索最近多少天活跃或创建的项目', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_min_stars_general', '100', '通用活跃项目最低 stars', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_min_stars_created', '20', '新创建项目最低 stars', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_min_stars_topic', '50', 'topic 项目最低 stars', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_min_stars_agent', '30', 'agent topic 项目最低 stars', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_topics', 'ai,llm,agent,php', '旧配置项：采集 topic 已固定在代码中', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_extra_queries', '', '额外 GitHub 搜索语句，每行一条，可使用 {since}', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('deepseek_system_prompt', '你是一个帮助站长发现 GitHub 新项目的技术分析员。\n你的目标是把项目讲成人能快速理解的中文：它做什么、为什么值得关注、适合谁、怎么用、有什么可借鉴点。\n不要把本站运行环境或某个特定技术栈当作通用评价标准；除非输入明确要求，否则不要把部署条件作为主要结论。\n你必须用中文输出严格 JSON，不要 Markdown，不要解释。\n评分为 1 到 10 的整数。\nplay_score 衡量项目是否有趣、是否值得点开体验、是否能带来灵感。\nuseful_score 衡量项目是否解决真实问题、是否有明确使用价值。\nmaturity_score 衡量项目成熟度，综合 Stars、Forks、最近更新、文档完整度和社区活跃度。\ndifficulty 衡量理解、部署、改造或复刻成本，只能输出 低、中、高。', 'GIR 解读系统提示词', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('deepseek_task_prompt', '为这次榜单命中生成一条新的中文解说。即使历史里已经分析过同一个项目，也不要复用旧文案；请结合最近几次解说，判断这次是否有新功能、热度变化、定位变化或值得重新关注的原因。表达要说人话，避免空泛夸奖，重点说明：项目一句话用途、解决的真实问题、为什么上榜或变热、适合谁用、上手方式或可借鉴点、主要风险。不要默认围绕部署条件评价项目，也不要因为项目依赖较多或运行门槛较高就直接给出“暂不关注”；只有当部署门槛会明显影响目标用户采用时，才在风险里简短说明。', 'GIR 解读任务提示词', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_platforms', 'github_trending,github_search,ossinsight,trendshift,reporank,gitrepotrend', '旧配置项：排行平台已固定在代码中', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('readme_fetch_enabled', '1', '是否自动抓取项目 README', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('readme_translate_enabled', '1', '是否对英文 README 自动翻译成中文', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('readme_per_run', '20', '每轮 backlog 每批抓取多少个项目的 README（会持续抓到清空或时间预算到）', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('readme_translate_per_run', '5', '每轮 backlog 每批翻译多少个英文 README（会持续翻译到清空或时间预算到）', NOW());
