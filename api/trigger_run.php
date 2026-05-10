<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['trigger_token']);

$owner = $config['github']['owner'];
$repo = $config['github']['repo'];
$token = $config['github']['token'];
$workflow = $config['github']['workflow'];

if ($owner === '' || $repo === '' || $token === '') {
    json_response(['ok' => false, 'error' => 'github_dispatch_not_configured'], 400);
}

$runType = isset($_POST['run_type']) ? (string) $_POST['run_type'] : 'manual';
$inputs = [
    'run_type' => $runType,
];
if ($runType === 'backlog') {
    $inputs['backfill_existing'] = 'true';
    $inputs['backlog_pending_only'] = 'true';
}

$body = json_encode([
    'ref' => 'main',
    'inputs' => $inputs,
], JSON_UNESCAPED_UNICODE);

$url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
    . '/actions/workflows/' . rawurlencode($workflow) . '/dispatches';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_USERAGENT => 'AI Project Detective PHP Trigger',
    CURLOPT_HTTPHEADER => [
        'Accept: application/vnd.github+json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'X-GitHub-Api-Version: 2022-11-28',
    ],
    CURLOPT_TIMEOUT => 20,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
]);
$response = curl_exec($ch);
$errno = curl_errno($ch);
$error = curl_error($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($errno) {
    json_response(['ok' => false, 'error' => 'curl_error', 'detail' => $error], 502);
}

if ($http < 200 || $http >= 300) {
    json_response(['ok' => false, 'error' => 'github_dispatch_failed', 'http' => $http, 'response' => $response], 502);
}

json_response(['ok' => true, 'status' => 'dispatched']);
