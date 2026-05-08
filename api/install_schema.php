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
];

foreach ($migrations as $column => $statement) {
    if (!isset($existingColumns[$column]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

$indexes = [
    'idx_admin_status' => 'ALTER TABLE projects ADD INDEX idx_admin_status (admin_status)',
    'idx_is_hidden' => 'ALTER TABLE projects ADD INDEX idx_is_hidden (is_hidden)',
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
];
foreach ($reportMigrations as $column => $statement) {
    if (!isset($existingReportColumns[$column]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

$reportIndexRows = db_all('SHOW INDEX FROM project_reports');
$existingReportIndexes = [];
foreach ($reportIndexRows as $row) {
    if (isset($row['Key_name'])) {
        $existingReportIndexes[$row['Key_name']] = true;
    }
}
if (isset($existingReportIndexes['uniq_project_period']) && !db()->query('ALTER TABLE project_reports DROP INDEX uniq_project_period')) {
    $errors[] = db()->error;
    unset($existingReportIndexes['uniq_project_period']);
}

$reportIndexes = [
    'uniq_project_period_source' => 'ALTER TABLE project_reports ADD UNIQUE KEY uniq_project_period_source (project_id, period_type, report_date, source_platform, source_tag)',
    'idx_source' => 'ALTER TABLE project_reports ADD INDEX idx_source (source_platform, source_tag)',
    'idx_source_rank' => 'ALTER TABLE project_reports ADD INDEX idx_source_rank (source_platform, source_tag, source_rank)',
];
foreach ($reportIndexes as $index => $statement) {
    if (!isset($existingReportIndexes[$index]) && !db()->query($statement)) {
        $errors[] = db()->error;
    }
}

json_response([
    'ok' => !$errors,
    'statements' => count($statements),
    'errors' => $errors,
], $errors ? 500 : 200);
