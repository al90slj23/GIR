<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'fetch';
$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 10;
$translateOnly = $mode === 'translate';

$projects = readme_pending_projects($limit, $translateOnly);

$items = [];
if ($translateOnly) {
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
} else {
    foreach ($projects as $row) {
        $items[] = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
        ];
    }
}

json_response([
    'ok' => true,
    'mode' => $translateOnly ? 'translate' : 'fetch',
    'count' => count($items),
    'projects' => $items,
]);
