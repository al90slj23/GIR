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
  PRIMARY KEY (id),
  UNIQUE KEY uniq_full_name (full_name),
  KEY idx_stars (stars),
  KEY idx_language (language),
  KEY idx_admin_status (admin_status),
  KEY idx_is_hidden (is_hidden),
  KEY idx_discovered_at (discovered_at)
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
  one_sentence VARCHAR(255) NOT NULL DEFAULT '',
  project_type VARCHAR(64) NOT NULL DEFAULT '',
  problem_text TEXT,
  tech_stack TEXT,
  target_users TEXT,
  play_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  useful_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  php_fit_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
  difficulty VARCHAR(16) NOT NULL DEFAULT '',
  is_suitable_for_this_host TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ideas_to_reuse TEXT,
  risks TEXT,
  recommendation VARCHAR(32) NOT NULL DEFAULT '',
  summary_zh TEXT,
  raw_ai_json MEDIUMTEXT,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_project_period_source (project_id, period_type, report_date, source_platform, source_tag),
  KEY idx_report_date (report_date),
  KEY idx_source (source_platform, source_tag),
  KEY idx_source_rank (source_platform, source_tag, source_rank),
  KEY idx_scores (php_fit_score, useful_score, play_score),
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
VALUES ('discover_max_projects', '3', '每次最多分析项目数', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_per_page', '20', '每条 GitHub 搜索请求拉取数量', NOW());

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
VALUES ('discover_topics', 'ai,llm,agent', '采集 topic，逗号或换行分隔', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_extra_queries', '', '额外 GitHub 搜索语句，每行一条，可使用 {since}', NOW());

INSERT IGNORE INTO app_settings (setting_key, setting_value, description, updated_at)
VALUES ('discover_platforms', 'github_trending,github_search,ossinsight,trendshift,reporank,gitrepotrend', '启用的排行平台，逗号或换行分隔', NOW());
