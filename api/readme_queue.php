<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'fetch';
if (!in_array($mode, ['fetch', 'translate', 'recheck_zh', 'refresh_due', 'refresh_all'], true)) {
    $mode = 'fetch';
}
$limit = isset($_GET['limit']) ? max(1, min(200, (int) $_GET['limit'])) : 10;
$cursor = isset($_GET['cursor']) ? max(0, (int) $_GET['cursor']) : 0;

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
} elseif ($mode === 'refresh_all') {
    // First-pass walk: re-analyze every project so that all rows have the
    // new repo_snapshot schema (stars, forks, readme_md5). Ordered by id ASC
    // so cursor-based pagination is stable.
    $rows = db_all(
        "SELECT p.id, p.full_name, p.stars, p.forks
         FROM projects p
         WHERE p.is_hidden = 0 AND p.id > ?
         ORDER BY p.id ASC
         LIMIT " . (int) $limit,
        [$cursor]
    );
    $maxNextCursor = $cursor;
    foreach ($rows as $row) {
        $maxNextCursor = max($maxNextCursor, (int) $row['id']);
        $items[] = [
            'id' => (int) $row['id'],
            'full_name' => (string) $row['full_name'],
            'reasons' => ['initial_full_pass'],
            'previous_report_id' => null,
            'current_stars' => (int) $row['stars'],
            'current_forks' => (int) $row['forks'],
        ];
    }
    json_response([
        'ok' => true,
        'mode' => $mode,
        'count' => count($items),
        'cursor' => $cursor,
        'next_cursor' => count($rows) < $limit ? null : $maxNextCursor,
        'projects' => $items,
    ]);
} elseif ($mode === 'refresh_due') {
    // Walk the project library by id ascending; for each project decide if a
    // re-analysis is justified by strong signals:
    //   1. never analyzed (no GIR row)         -> always
    //   2. README MD5 changed since last GIR   -> always
    //   3. stars +10% or +500 since last GIR   -> always
    //   4. forks +10% or +100 since last GIR   -> always
    //   5. otherwise                            -> skip
    $scanLimit = max(1, min(500, (int) ($_GET['scan'] ?? 200)));
    $maxNextCursor = $cursor;
    $rows = db_all(
        "SELECT p.id, p.full_name, p.stars, p.forks
         FROM projects p
         WHERE p.is_hidden = 0 AND p.id > ?
         ORDER BY p.id ASC
         LIMIT " . (int) $scanLimit,
        [$cursor]
    );
    $skipped = 0;
    foreach ($rows as $row) {
        $maxNextCursor = max($maxNextCursor, (int) $row['id']);
        if (count($items) >= $limit) {
            break;
        }
        $pid = (int) $row['id'];
        $currentStars = (int) $row['stars'];
        $currentForks = (int) $row['forks'];

        $lastReport = db_one(
            "SELECT id, created_at, raw_ai_json
             FROM project_reports
             WHERE project_id = ? AND raw_rank_only = 0 AND one_sentence <> ''
             ORDER BY created_at DESC, id DESC LIMIT 1",
            [$pid]
        );
        $reasons = [];
        if (!$lastReport) {
            $reasons[] = 'never_analyzed';
        } else {
            $prevStars = null;
            $prevForks = null;
            $prevReadmeMd5 = null;
            if (!empty($lastReport['raw_ai_json'])) {
                $payload = json_decode((string) $lastReport['raw_ai_json'], true);
                if (is_array($payload)) {
                    if (isset($payload['repo_snapshot']['stars'])) {
                        $prevStars = (int) $payload['repo_snapshot']['stars'];
                    }
                    if (isset($payload['repo_snapshot']['forks'])) {
                        $prevForks = (int) $payload['repo_snapshot']['forks'];
                    }
                    if (isset($payload['repo_snapshot']['readme_md5'])) {
                        $prevReadmeMd5 = (string) $payload['repo_snapshot']['readme_md5'];
                    }
                }
            }
            $currentReadmeRow = db_one(
                "SELECT content_md5 FROM project_readmes
                 WHERE project_id = ? AND is_translated = 0
                 ORDER BY (language_code LIKE 'zh%') DESC, id ASC LIMIT 1",
                [$pid]
            );
            $currentReadmeMd5 = $currentReadmeRow ? (string) $currentReadmeRow['content_md5'] : '';

            if ($prevStars !== null) {
                $delta = $currentStars - $prevStars;
                if ($delta >= 500 || ($prevStars > 0 && $delta >= max(1, $prevStars * 0.1))) {
                    $reasons[] = 'stars_growth:' . $delta . '/' . $prevStars;
                }
            }
            if ($prevForks !== null) {
                $delta = $currentForks - $prevForks;
                if ($delta >= 100 || ($prevForks > 0 && $delta >= max(1, $prevForks * 0.1))) {
                    $reasons[] = 'forks_growth:' . $delta . '/' . $prevForks;
                }
            }
            if ($currentReadmeMd5 !== '' && $prevReadmeMd5 !== null && $prevReadmeMd5 !== '' && $currentReadmeMd5 !== $prevReadmeMd5) {
                $reasons[] = 'readme_changed';
            }
        }

        if ($reasons) {
            $items[] = [
                'id' => $pid,
                'full_name' => (string) $row['full_name'],
                'reasons' => $reasons,
                'previous_report_id' => $lastReport ? (int) $lastReport['id'] : null,
                'current_stars' => $currentStars,
                'current_forks' => $currentForks,
            ];
        } else {
            $skipped++;
        }
    }
    $exhausted = count($rows) < $scanLimit;
    json_response([
        'ok' => true,
        'mode' => $mode,
        'count' => count($items),
        'scanned' => count($rows),
        'skipped' => $skipped,
        'cursor' => $cursor,
        'next_cursor' => $exhausted ? null : $maxNextCursor,
        'projects' => $items,
    ]);
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
