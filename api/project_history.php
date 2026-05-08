<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$payload = request_json();
$fullName = isset($payload['full_name']) ? trim((string) $payload['full_name']) : '';
$limit = isset($payload['limit']) ? max(1, min(8, (int) $payload['limit'])) : 5;

if ($fullName === '') {
    json_response(['ok' => false, 'error' => 'missing_full_name'], 400);
}

json_response([
    'ok' => true,
    'full_name' => $fullName,
    'reports' => recent_project_analyses($fullName, $limit),
]);
