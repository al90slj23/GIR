<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

require_token($config['app']['trigger_token']);

$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) {
    json_response(['ok' => false, 'error' => 'schema_not_found'], 500);
}

$statements = array_filter(array_map('trim', explode(';', $schema)));
$errors = [];

foreach ($statements as $statement) {
    if ($statement === '') {
        continue;
    }
    if (!db()->query($statement)) {
        $errors[] = db()->error;
    }
}

$projectColumns = db_all('SHOW COLUMNS FROM projects');
$existingColumns = [];
foreach ($projectColumns as $column) {
    if (isset($column['Field'])) {
        $existingColumns[$column['Field']] = true;
    }
}

$migrations = [
    'is_hidden' => "ALTER TABLE projects ADD COLUMN is_hidden TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pushed_at",
    'admin_status' => "ALTER TABLE projects ADD COLUMN admin_status VARCHAR(32) NOT NULL DEFAULT 'new' AFTER is_hidden",
    'admin_note' => "ALTER TABLE projects ADD COLUMN admin_note TEXT AFTER admin_status",
    'last_full_refresh_at' => "ALTER TABLE projects ADD COLUMN last_full_refresh_at DATETIME DEFAULT NULL AFTER updated_at",
    'zh_readme_checked_at' => "ALTER TABLE projects ADD COLUMN zh_readme_checked_at DATETIME DEFAULT NULL AFTER last_full_refresh_at",
];

foreach ($migrations as $column => $statement) {
    if (!isset($existingColumns[$column]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

$indexes = [
    'idx_admin_status' => 'ALTER TABLE projects ADD INDEX idx_admin_status (admin_status)',
    'idx_is_hidden' => 'ALTER TABLE projects ADD INDEX idx_is_hidden (is_hidden)',
    'idx_last_full_refresh' => 'ALTER TABLE projects ADD INDEX idx_last_full_refresh (last_full_refresh_at)',
    'idx_zh_readme_checked' => 'ALTER TABLE projects ADD INDEX idx_zh_readme_checked (zh_readme_checked_at)',
];
$indexRows = db_all('SHOW INDEX FROM projects');
$existingIndexes = [];
foreach ($indexRows as $row) {
    if (isset($row['Key_name'])) {
        $existingIndexes[$row['Key_name']] = true;
    }
}
foreach ($indexes as $index => $statement) {
    if (!isset($existingIndexes[$index]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

$reportColumns = db_all('SHOW COLUMNS FROM project_reports');
$existingReportColumns = [];
foreach ($reportColumns as $column) {
    if (isset($column['Field'])) {
        $existingReportColumns[$column['Field']] = true;
    }
}

$reportMigrations = [
    'source_platform' => "ALTER TABLE project_reports ADD COLUMN source_platform VARCHAR(64) NOT NULL DEFAULT 'github' AFTER report_date",
    'source_tag' => "ALTER TABLE project_reports ADD COLUMN source_tag VARCHAR(64) NOT NULL DEFAULT '综合' AFTER source_platform",
    'source_rank' => "ALTER TABLE project_reports ADD COLUMN source_rank INT UNSIGNED NOT NULL DEFAULT 0 AFTER source_tag",
    'source_score' => "ALTER TABLE project_reports ADD COLUMN source_score DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER source_rank",
    'raw_rank_only' => "ALTER TABLE project_reports ADD COLUMN raw_rank_only TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER source_score",
    'maturity_score' => "ALTER TABLE project_reports ADD COLUMN maturity_score TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER useful_score",
    'change_note' => "ALTER TABLE project_reports ADD COLUMN change_note TEXT AFTER risks",
    'change_observation' => "ALTER TABLE project_reports ADD COLUMN change_observation MEDIUMTEXT AFTER change_note",
    'analysis_detail' => "ALTER TABLE project_reports ADD COLUMN analysis_detail MEDIUMTEXT AFTER change_observation",
    'previous_report_id' => "ALTER TABLE project_reports ADD COLUMN previous_report_id INT UNSIGNED DEFAULT NULL AFTER analysis_detail",
    'star_growth' => "ALTER TABLE project_reports ADD COLUMN star_growth INT DEFAULT NULL AFTER previous_report_id",
    'fork_growth' => "ALTER TABLE project_reports ADD COLUMN fork_growth INT DEFAULT NULL AFTER star_growth",
    'span_days' => "ALTER TABLE project_reports ADD COLUMN span_days INT DEFAULT NULL AFTER fork_growth",
];
foreach ($reportMigrations as $column => $statement) {
    if (!isset($existingReportColumns[$column]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}
if (!isset($existingReportColumns['raw_rank_only']) && !db()->query("UPDATE project_reports SET raw_rank_only = 1 WHERE one_sentence = ''")) {
    $errors[] = db()->error;
}

$reportIndexRows = db_all('SHOW INDEX FROM project_reports');
$existingReportIndexes = [];
$existingReportIndexColumns = [];
foreach ($reportIndexRows as $row) {
    if (isset($row['Key_name'])) {
        $existingReportIndexes[$row['Key_name']] = true;
        $indexName = (string) $row['Key_name'];
        $sequence = (int) ($row['Seq_in_index'] ?? 0);
        $existingReportIndexColumns[$indexName][$sequence] = (string) ($row['Column_name'] ?? '');
    }
}
if (isset($existingReportIndexes['uniq_project_period']) && !db()->query('ALTER TABLE project_reports DROP INDEX uniq_project_period')) {
    $errors[] = db()->error;
    unset($existingReportIndexes['uniq_project_period']);
}
if (isset($existingReportIndexes['uniq_project_period_source']) && !db()->query('ALTER TABLE project_reports DROP INDEX uniq_project_period_source')) {
    $errors[] = db()->error;
    unset($existingReportIndexes['uniq_project_period_source']);
}

$idxScoresColumns = $existingReportIndexColumns['idx_scores'] ?? [];
ksort($idxScoresColumns);
if (isset($existingReportIndexes['idx_scores']) && implode(',', $idxScoresColumns) !== 'useful_score,maturity_score,play_score') {
    if (!db()->query('ALTER TABLE project_reports DROP INDEX idx_scores')) {
        $errors[] = db()->error;
    } else {
        unset($existingReportIndexes['idx_scores']);
    }
}

$reportIndexes = [
    'idx_project_period_source' => 'ALTER TABLE project_reports ADD INDEX idx_project_period_source (project_id, period_type, report_date, source_platform, source_tag)',
    'idx_source' => 'ALTER TABLE project_reports ADD INDEX idx_source (source_platform, source_tag)',
    'idx_source_rank' => 'ALTER TABLE project_reports ADD INDEX idx_source_rank (source_platform, source_tag, source_rank)',
    'idx_raw_rank' => 'ALTER TABLE project_reports ADD INDEX idx_raw_rank (raw_rank_only, one_sentence)',
    'idx_scores' => 'ALTER TABLE project_reports ADD INDEX idx_scores (useful_score, maturity_score, play_score)',
];
foreach ($reportIndexes as $index => $statement) {
    if (!isset($existingReportIndexes[$index]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

// project_readmes migrations
$readmeTableExists = (bool) db_one("SHOW TABLES LIKE 'project_readmes'");
if ($readmeTableExists) {
    $readmeColumnsRows = db_all('SHOW COLUMNS FROM project_readmes');
    $existingReadmeColumns = [];
    foreach ($readmeColumnsRows as $column) {
        if (isset($column['Field'])) {
            $existingReadmeColumns[$column['Field']] = true;
        }
    }
    $readmeMigrations = [
        'content_md5' => "ALTER TABLE project_readmes ADD COLUMN content_md5 CHAR(32) NOT NULL DEFAULT '' AFTER content_md",
        'source_content_md5' => "ALTER TABLE project_readmes ADD COLUMN source_content_md5 CHAR(32) NOT NULL DEFAULT '' AFTER source_language_code",
    ];
    foreach ($readmeMigrations as $column => $statement) {
        if (!isset($existingReadmeColumns[$column]) && !db()->query($statement)) {
            $errors[] = db()->error;
        }
    }
}

json_response([
    'ok' => !$errors,
    'statements' => count($statements),
    'errors' => $errors,
], $errors ? 500 : 200);
