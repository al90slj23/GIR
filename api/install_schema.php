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

json_response([
    'ok' => !$errors,
    'statements' => count($statements),
    'errors' => $errors,
], $errors ? 500 : 200);
