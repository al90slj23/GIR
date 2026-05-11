<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$payload = request_json();
$runType = isset($payload['run_type']) ? (string) $payload['run_type'] : 'daily';
$periodType = isset($payload['period_type']) ? (string) $payload['period_type'] : $runType;
$reportDate = isset($payload['report_date']) ? (string) $payload['report_date'] : date('Y-m-d');
$projects = isset($payload['projects']) && is_array($payload['projects']) ? $payload['projects'] : [];

if (!in_array($periodType, ['daily', 'weekly', 'manual'], true)) {
    json_response(['ok' => false, 'error' => 'invalid_period_type'], 400);
}

if (!$projects) {
    json_response(['ok' => false, 'error' => 'empty_projects'], 400);
}

$runId = create_run($runType, 'github_actions');
$found = count($projects);
$analyzed = 0;
$stored = 0;
$errors = [];

foreach ($projects as $index => $item) {
    if (!is_array($item)) {
        $errors[] = "project {$index}: invalid item";
        continue;
    }
    $fullName = isset($item['full_name']) ? trim((string) $item['full_name']) : '';
    $analysis = isset($item['analysis']) && is_array($item['analysis']) ? $item['analysis'] : [];
    if ($fullName === '') {
        $errors[] = "project {$index}: missing full_name";
        continue;
    }
    $rawRankOnly = !empty($analysis['raw_rank_only']);

    $projectId = upsert_project($item);
    if ($projectId <= 0) {
        $errors[] = "project {$index}: project upsert failed";
        continue;
    }
    $source = [
        'platform' => isset($item['source_platform']) ? (string) $item['source_platform'] : 'github',
        'tag' => isset($item['source_tag']) ? (string) $item['source_tag'] : '综合',
        'rank' => isset($item['source_rank']) ? (int) $item['source_rank'] : 0,
        'score' => isset($item['source_score']) ? (float) $item['source_score'] : 0,
    ];
    upsert_report($projectId, $runId, $periodType, $reportDate, $analysis, $source);
    $stored++;
    if (!$rawRankOnly && $analysis) {
        $analyzed++;
    }
}

$status = $errors ? ($stored > 0 ? 'partial' : 'failed') : 'success';
finish_run($runId, $status, $found, $analyzed, implode("\n", $errors));

if ($errors) {
    app_log('ingest', 'partial/failed', [
        'run_id' => $runId,
        'status' => $status,
        'found' => $found,
        'stored' => $stored,
        'analyzed' => $analyzed,
        'error_count' => count($errors),
        'first_error' => $errors[0] ?? '',
    ]);
}

json_response([
    'ok' => $stored > 0,
    'run_id' => $runId,
    'status' => $status,
    'total_found' => $found,
    'total_stored' => $stored,
    'total_analyzed' => $analyzed,
    'errors' => $errors,
]);
