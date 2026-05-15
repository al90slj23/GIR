<?php
/**
 * One-shot migration: move project_readmes.content_md (DB) into the
 * file cache, then NULL out the DB column to free space.
 *
 * Idempotent: rows whose cache file already exists are skipped from
 * re-writing but their DB content is still cleared.
 *
 * Usage: GET /api/migrate_readme_to_cache.php?token=APP_TRIGGER_TOKEN&limit=500
 */
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_token($config['app']['trigger_token']);

$limit = isset($_GET['limit']) ? max(1, min(2000, (int) $_GET['limit'])) : 500;
$dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

$rows = db_all(
    "SELECT id, project_id, language_code, is_translated, content_md
     FROM project_readmes
     WHERE content_md IS NOT NULL AND content_md <> ''
     ORDER BY id ASC
     LIMIT " . (int) $limit
);

$migrated = 0;
$alreadyCached = 0;
$cleared = 0;
$failed = 0;
$bytesMoved = 0;

foreach ($rows as $row) {
    $pid = (int) $row['project_id'];
    $lang = (string) $row['language_code'];
    $tr = (bool) ((int) $row['is_translated']);
    $content = (string) $row['content_md'];
    if ($content === '') continue;
    $cachePath = readme_cache_path($pid, $lang, $tr);
    if ($cachePath === '') {
        $failed++;
        continue;
    }
    if (is_file($cachePath)) {
        $alreadyCached++;
    } else {
        if ($dryRun) {
            $migrated++;
            $bytesMoved += strlen($content);
            continue;
        }
        if (!readme_cache_write($pid, $lang, $tr, $content)) {
            $failed++;
            continue;
        }
        $migrated++;
        $bytesMoved += strlen($content);
    }
    if (!$dryRun) {
        db_exec(
            "UPDATE project_readmes SET content_md = '' WHERE id = ?",
            [(int) $row['id']]
        );
        $cleared++;
    }
}

json_response([
    'ok' => true,
    'dry_run' => $dryRun,
    'rows_seen' => count($rows),
    'migrated_to_cache' => $migrated,
    'already_cached' => $alreadyCached,
    'cleared_in_db' => $cleared,
    'failed' => $failed,
    'bytes_moved' => $bytesMoved,
    'remaining_with_content' => (int) (db_one("SELECT COUNT(*) AS n FROM project_readmes WHERE content_md IS NOT NULL AND content_md <> ''")['n'] ?? 0),
]);
