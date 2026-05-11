<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'fetch';
if (!in_array($mode, ['fetch', 'translate', 'recheck_zh', 'refresh_due'], true)) {
    $mode = 'fetch';
}
$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 10;

$projects = [];
$items = [];

if ($mode === 'translate') {
    $projects = readme_pending_projects($limit, true);
    foreach ($projects as $row) {
        $projectId = (int) $row['id'];
        $readmes = db_all(
            "SELECT id, readme_path, language_code, content_md
             FROM project_readmes
             WHERE project_id = ? AND is_translated = 0 AND language_code = 'en'
             ORDER BY id ASC
             LIMIT 1",
            [$projectId]
        );
        $readme = $readmes[0] ?? null;
        if (!$readme) {
            continue;
        }
        $items[] = [
            'id' => $projectId,
            'full_name' => (string) $row['full_name'],
            'source_readme' => [
                'readme_path' => (string) $readme['readme_path'],
                'language_code' => (string) $readme['language_code'],
                'content_md' => (string) $readme['content_md'],
            ],
        ];
    }
} elseif ($mode === 'recheck_zh') {
    $rows = db_all(
        "SELECT p.id, p.full_name
         FROM projects p
         WHERE p.is_hidden = 0
           AND EXISTS (SELECT 1 FROM project_readmes r WHERE r.project_id = p.id AND r.is_translated = 0 AND r.language_code = 'en')
           AND NOT EXISTS (SELECT 1 FROM project_readmes r WHERE r.project_id = p.id AND r.language_code LIKE 'zh%')
           AND (p.zh_readme_checked_at IS NULL OR p.zh_readme_checked_at < DATE_SUB(NOW(), INTERVAL 30 DAY))
         ORDER BY p.stars DESC, p.id ASC
         LIMIT " . (int) $limit
    );
    foreach ($rows as $row) {
        $items[] = ['id' => (int) $row['id'], 'full_name' => (string) $row['full_name']];
    }
} elseif ($mode === 'refresh_due') {
    $rows = db_all(
        "SELECT p.id, p.full_name, p.last_full_refresh_at
         FROM projects p
         WHERE p.is_hidden = 0
           AND (p.last_full_refresh_at IS NULL OR p.last_full_refresh_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
         ORDER BY CASE WHEN p.last_full_refresh_at IS NULL THEN 0 ELSE 1 END ASC,
                  p.last_full_refresh_at ASC,
                  p.stars DESC,
                  p.id ASC
         LIMIT " . (int) $limit
    );
    foreach ($rows as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'last_full_refresh_at' => (string) ($row['last_full_refresh_at'] ?? ''),
        ];
    }
} else {
    $projects = readme_pending_projects($limit, false);
    foreach ($projects as $row) {
        $items[] = ['id' => (int) $row['id'], 'full_name' => (string) $row['full_name']];
    }
}

json_response([
    'ok' => true,
    'mode' => $mode,
    'count' => count($items),
    'projects' => $items,
]);
