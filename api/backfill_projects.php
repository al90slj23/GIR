<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 100;
$pendingOnly = isset($_GET['pending_only']) && in_array(strtolower((string) $_GET['pending_only']), ['1', 'true', 'yes', 'on'], true);
$total = backfill_project_count($pendingOnly);
$projects = backfill_projects($offset, $limit, $pendingOnly);

json_response([
    'ok' => true,
    'total' => $total,
    'offset' => $offset,
    'limit' => $limit,
    'pending_only' => $pendingOnly,
    'count' => count($projects),
    'projects' => $projects,
]);
