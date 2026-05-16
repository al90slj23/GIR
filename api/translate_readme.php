<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}
require_csrf();

$projectId = isset($_POST['project_id']) ? (int) $_POST['project_id'] : 0;
if ($projectId <= 0) {
    json_response(['ok' => false, 'error' => 'missing_project_id'], 400);
}

if (!tmt_configured()) {
    json_response(['ok' => false, 'error' => 'tencent_not_configured'], 503);
}

// Find an English README we can translate.
$row = db_one(
    "SELECT id, project_id, readme_path, content_md5, language_code
     FROM project_readmes
     WHERE project_id = ? AND is_translated = 0 AND language_code = 'en'
     ORDER BY id ASC LIMIT 1",
    [$projectId]
);
if (!$row) {
    json_response(['ok' => false, 'error' => 'no_source_readme'], 404);
}

$projectRow = db_one('SELECT id, full_name FROM projects WHERE id = ?', [$projectId]);
if (!$projectRow) {
    json_response(['ok' => false, 'error' => 'project_not_found'], 404);
}

// Resolve source markdown from cache, falling back to a live fetch.
$source = readme_resolve_content(
    ['id' => $projectId, 'full_name' => (string) $projectRow['full_name']],
    [
        'project_id' => $projectId,
        'id' => (int) $row['id'],
        'language_code' => (string) $row['language_code'],
        'is_translated' => 0,
        'readme_path' => (string) $row['readme_path'],
    ]
);
if (trim($source) === '') {
    json_response(['ok' => false, 'error' => 'source_unavailable'], 404);
}

// Rate-limit by IP: max 30 requests / 5 minutes.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ipKey = preg_replace('/[^A-Za-z0-9]/', '_', $ip);
$rateDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'gir_tmt_rate';
if (!is_dir($rateDir)) {
    @mkdir($rateDir, 0700, true);
}
$rateFile = $rateDir . DIRECTORY_SEPARATOR . $ipKey . '.json';
$now = time();
$rate = [];
if (is_file($rateFile)) {
    $raw = @file_get_contents($rateFile);
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $rate = $decoded;
    }
}
$rate = array_values(array_filter($rate, static function ($t) use ($now) {
    return is_int($t) && $t > $now - 300;
}));
if (count($rate) >= 30) {
    json_response(['ok' => false, 'error' => 'rate_limit'], 429);
}
$rate[] = $now;
@file_put_contents($rateFile, json_encode($rate));

// Run translation.
$result = tmt_translate_long($source, 'en', 'zh');
if (!$result['ok']) {
    app_log('tmt', 'translate_failed', ['project_id' => $projectId, 'error' => $result['error']]);
    json_response(['ok' => false, 'error' => $result['error']], 502);
}

// Persist as a translated readme row so subsequent visits use the cached version.
// Record the source README's MD5 so we can detect when the translation becomes stale.
$sourceReadmeMd5 = (string) ($row['content_md5'] ?? '');
if ($sourceReadmeMd5 === '') {
    $sourceReadmeMd5 = md5($source);
}
upsert_project_readme($projectId, [
    'readme_path' => (string) $row['readme_path'],
    'language_code' => 'zh',
    'is_translated' => 1,
    'source_language_code' => 'en',
    'source_content_md5' => $sourceReadmeMd5,
    'source_url' => 'https://github.com/' . (string) $projectRow['full_name'] . '/blob/HEAD/' . (string) $row['readme_path'],
    'content_md' => $result['text'],
    'fetched_at' => date('Y-m-d H:i:s'),
]);

json_response([
    'ok' => true,
    'project_id' => $projectId,
    'language_code' => 'zh',
    'is_translated' => true,
    'html' => render_markdown_html($result['text'], (string) $projectRow['full_name']),
]);
