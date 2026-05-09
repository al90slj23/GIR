<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'method_not_allowed'], 405);
}

require_token($config['app']['ingest_token']);

$projectTotals = db_one(
    'SELECT COUNT(*) AS total,
            SUM(CASE WHEN is_hidden = 0 THEN 1 ELSE 0 END) AS visible,
            SUM(CASE WHEN is_hidden = 1 THEN 1 ELSE 0 END) AS hidden
     FROM projects'
);

$reportTotals = db_one(
    'SELECT COUNT(*) AS total,
            SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
            SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank
     FROM project_reports'
);

$periods = db_all(
    'SELECT period_type,
            COUNT(*) AS total,
            SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
            SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank
     FROM project_reports
     GROUP BY period_type
     ORDER BY period_type ASC'
);

$platforms = db_all(
    'SELECT source_platform,
            COUNT(*) AS total,
            SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
            SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank
     FROM project_reports
     GROUP BY source_platform
     ORDER BY total DESC, source_platform ASC'
);

$dates = db_all(
    'SELECT report_date,
            COUNT(*) AS total,
            SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
            SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank
     FROM project_reports
     GROUP BY report_date
     ORDER BY report_date DESC
     LIMIT 10'
);

json_response([
    'ok' => true,
    'generated_at' => date('Y-m-d H:i:s'),
    'projects' => [
        'total' => (int) ($projectTotals['total'] ?? 0),
        'visible' => (int) ($projectTotals['visible'] ?? 0),
        'hidden' => (int) ($projectTotals['hidden'] ?? 0),
    ],
    'reports' => [
        'total' => (int) ($reportTotals['total'] ?? 0),
        'analyzed' => (int) ($reportTotals['analyzed'] ?? 0),
        'raw_rank' => (int) ($reportTotals['raw_rank'] ?? 0),
    ],
    'periods' => $periods,
    'platforms' => $platforms,
    'dates' => $dates,
    'runs' => recent_runs(5),
]);
