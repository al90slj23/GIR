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
  UNIQUE KEY uniq_project_period (project_id, period_type, report_date),
  KEY idx_report_date (report_date),
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
