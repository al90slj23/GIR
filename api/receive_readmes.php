<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$payload = request_json();
$items = isset($payload['projects']) && is_array($payload['projects']) ? $payload['projects'] : [];
$markZhChecked = !empty($payload['mark_zh_checked']);
$markRefreshed = !empty($payload['mark_refreshed']);

if (!$items) {
    json_response(['ok' => false, 'error' => 'empty_projects'], 400);
}

$processed = 0;
$stored = 0;
$errors = [];

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        $errors[] = "project {$index}: invalid";
        continue;
    }
    $fullName = trim((string) ($item['full_name'] ?? ''));
    if ($fullName === '') {
        $errors[] = "project {$index}: missing full_name";
        continue;
    }
    $row = db_one('SELECT id FROM projects WHERE full_name = ?', [$fullName]);
    if (!$row) {
        $errors[] = $fullName . ': project not found';
        continue;
    }
    $projectId = (int) $row['id'];
    $stored += readme_ingest_payload($projectId, $item);
    if ($markZhChecked) {
        db_exec('UPDATE projects SET zh_readme_checked_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $projectId]);
    }
    if ($markRefreshed) {
        db_exec('UPDATE projects SET last_full_refresh_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $projectId]);
    }
    $processed++;
}

if ($errors) {
    app_log('readme', 'ingest_errors', ['count' => count($errors), 'first' => $errors[0] ?? '']);
}

json_response([
    'ok' => true,
    'processed' => $processed,
    'stored' => $stored,
    'errors' => $errors,
]);
