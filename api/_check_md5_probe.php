<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_token($config['app']['ingest_token']);
$rows = db_all(
    "SELECT r.project_id, p.full_name, r.created_at, r.raw_ai_json
     FROM project_reports r INNER JOIN projects p ON p.id=r.project_id
     WHERE r.raw_rank_only = 0 AND r.one_sentence <> ''
     ORDER BY r.id DESC LIMIT 5"
);
$out = [];
foreach ($rows as $row) {
    $payload = json_decode((string) $row['raw_ai_json'], true);
    $snap = is_array($payload) ? ($payload['repo_snapshot'] ?? null) : null;
    $out[] = [
        'project_id' => (int) $row['project_id'],
        'full_name' => $row['full_name'],
        'created_at' => $row['created_at'],
        'has_readme_md5' => $snap && isset($snap['readme_md5']),
        'snap' => $snap,
    ];
}
json_response(['ok' => true, 'recent_reports' => $out]);
