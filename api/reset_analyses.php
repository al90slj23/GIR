<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$confirm = isset($_POST['confirm']) ? (string) $_POST['confirm'] : '';
if ($confirm !== 'clear_analyses') {
    json_response(['ok' => false, 'error' => 'missing_confirm'], 400);
}

$result = reset_project_analyses();
json_response(array_merge(['ok' => true], $result));
