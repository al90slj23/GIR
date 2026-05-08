<?php
declare(strict_types=1);

function latest_reports(string $periodType, int $limit = 20): array
{
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0
         ORDER BY r.report_date DESC, r.php_fit_score DESC, r.useful_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        [$periodType]
    );
}

function reports_by_date(string $periodType, string $date, int $limit = 30): array
{
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.report_date = ? AND p.is_hidden = 0
         ORDER BY r.php_fit_score DESC, r.useful_score DESC, r.play_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        [$periodType, $date]
    );
}

function recent_report_dates(string $periodType, int $limit = 14): array
{
    return db_all(
        'SELECT report_date, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE period_type = ? AND p.is_hidden = 0
         GROUP BY report_date
         ORDER BY report_date DESC
         LIMIT ' . (int) $limit,
        [$periodType]
    );
}

function project_with_reports(int $id): ?array
{
    $project = db_one('SELECT * FROM projects WHERE id = ?', [$id]);
    if (!$project) {
        return null;
    }
    $project['reports'] = db_all(
        'SELECT * FROM project_reports WHERE project_id = ? ORDER BY report_date DESC, id DESC',
        [$id]
    );
    return $project;
}

function recent_runs(int $limit = 20): array
{
    return db_all(
        'SELECT * FROM runs ORDER BY started_at DESC, id DESC LIMIT ' . (int) $limit
    );
}

function admin_project_statuses(): array
{
    return [
        'new' => '新发现',
        'saved' => '已收藏',
        'researching' => '研究中',
        'replicate' => '可复刻',
        'ignored' => '不感兴趣',
    ];
}

function normalize_admin_status(string $status): string
{
    $statuses = admin_project_statuses();
    return isset($statuses[$status]) ? $status : 'new';
}

function admin_projects(array $filters, int $limit = 80): array
{
    $where = [];
    $params = [];

    $status = isset($filters['status']) ? (string) $filters['status'] : '';
    if ($status !== '') {
        $where[] = 'p.admin_status = ?';
        $params[] = normalize_admin_status($status);
    }

    $visibility = isset($filters['visibility']) ? (string) $filters['visibility'] : '';
    if ($visibility === 'visible') {
        $where[] = 'p.is_hidden = 0';
    } elseif ($visibility === 'hidden') {
        $where[] = 'p.is_hidden = 1';
    }

    $language = isset($filters['language']) ? trim((string) $filters['language']) : '';
    if ($language !== '') {
        $where[] = 'p.language = ?';
        $params[] = truncate_text($language, 64);
    }

    $recommendation = isset($filters['recommendation']) ? trim((string) $filters['recommendation']) : '';
    if ($recommendation !== '') {
        $where[] = 'r.recommendation = ?';
        $params[] = truncate_text($recommendation, 32);
    }

    $keyword = isset($filters['q']) ? trim((string) $filters['q']) : '';
    if ($keyword !== '') {
        $where[] = '(p.full_name LIKE ? OR p.description LIKE ? OR r.summary_zh LIKE ?)';
        $like = '%' . $keyword . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return db_all(
        'SELECT p.*, r.id AS report_id, r.report_date, r.one_sentence, r.project_type, r.play_score,
                r.useful_score, r.php_fit_score, r.difficulty, r.recommendation, r.summary_zh
         FROM projects p
         LEFT JOIN project_reports r ON r.id = (
             SELECT rr.id FROM project_reports rr
             WHERE rr.project_id = p.id
             ORDER BY rr.report_date DESC, rr.id DESC
             LIMIT 1
         )
         ' . $sqlWhere . '
         ORDER BY p.is_hidden ASC, p.updated_at DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        $params
    );
}

function admin_project_filter_options(): array
{
    return [
        'languages' => db_all("SELECT language, COUNT(*) AS total FROM projects WHERE language <> '' GROUP BY language ORDER BY total DESC, language ASC LIMIT 50"),
        'recommendations' => db_all("SELECT recommendation, COUNT(*) AS total FROM project_reports WHERE recommendation <> '' GROUP BY recommendation ORDER BY total DESC, recommendation ASC LIMIT 20"),
    ];
}

function update_project_admin(int $projectId, string $status, bool $hidden, string $note): bool
{
    return db_exec(
        'UPDATE projects SET admin_status = ?, is_hidden = ?, admin_note = ?, updated_at = ? WHERE id = ?',
        [normalize_admin_status($status), $hidden ? 1 : 0, truncate_text($note, 5000), date('Y-m-d H:i:s'), $projectId]
    );
}

function all_app_settings(): array
{
    return db_all('SELECT * FROM app_settings ORDER BY setting_key ASC');
}

function update_app_setting(string $key, string $value): bool
{
    return db_exec(
        'UPDATE app_settings SET setting_value = ?, updated_at = ? WHERE setting_key = ?',
        [truncate_text($value, 5000), date('Y-m-d H:i:s'), truncate_text($key, 64)]
    );
}

function github_trigger_configured(): bool
{
    global $config;
    return $config['github']['owner'] !== ''
        && $config['github']['repo'] !== ''
        && $config['github']['token'] !== ''
        && $config['github']['workflow'] !== '';
}

function trigger_github_discover(string $runType): array
{
    global $config;

    if (!in_array($runType, ['daily', 'weekly', 'manual'], true)) {
        $runType = 'daily';
    }

    if (!github_trigger_configured()) {
        return ['ok' => false, 'error' => 'github_dispatch_not_configured'];
    }

    $body = json_encode([
        'ref' => 'main',
        'inputs' => [
            'run_type' => $runType,
        ],
    ], JSON_UNESCAPED_UNICODE);

    $url = 'https://api.github.com/repos/' . rawurlencode($config['github']['owner']) . '/' . rawurlencode($config['github']['repo'])
        . '/actions/workflows/' . rawurlencode($config['github']['workflow']) . '/dispatches';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_USERAGENT => 'GIR Admin Trigger',
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['github']['token'],
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
        return ['ok' => false, 'error' => 'curl_error: ' . $error];
    }
    if ($http < 200 || $http >= 300) {
        return ['ok' => false, 'error' => 'github_dispatch_failed: HTTP ' . $http . ' ' . truncate_text((string) $response, 300)];
    }

    return ['ok' => true];
}

function upsert_project(array $item): int
{
    $now = date('Y-m-d H:i:s');
    $fullName = truncate_text($item['full_name'] ?? '', 191);
    $existing = db_one('SELECT id FROM projects WHERE full_name = ?', [$fullName]);
    $topics = $item['topics'] ?? [];
    $topicsText = is_array($topics) ? implode(',', array_slice($topics, 0, 30)) : (string) $topics;
    $pushedAt = normalize_datetime($item['pushed_at'] ?? null);

    if ($existing) {
        db_exec(
            'UPDATE projects
             SET github_id = ?, name = ?, html_url = ?, description = ?, stars = ?, forks = ?, language = ?,
                 topics = ?, pushed_at = ?, updated_at = ?
             WHERE id = ?',
            [
                (int) ($item['github_id'] ?? $item['id'] ?? 0),
                truncate_text($item['name'] ?? '', 191),
                truncate_text($item['html_url'] ?? '', 255),
                truncate_text($item['description'] ?? '', 5000),
                (int) ($item['stars'] ?? $item['stargazers_count'] ?? 0),
                (int) ($item['forks'] ?? $item['forks_count'] ?? 0),
                truncate_text($item['language'] ?? '', 64),
                truncate_text($topicsText, 5000),
                $pushedAt,
                $now,
                (int) $existing['id'],
            ]
        );
        return (int) $existing['id'];
    }

    db_exec(
        'INSERT INTO projects
         (github_id, name, full_name, html_url, description, stars, forks, language, topics, pushed_at, discovered_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (int) ($item['github_id'] ?? $item['id'] ?? 0),
            truncate_text($item['name'] ?? '', 191),
            $fullName,
            truncate_text($item['html_url'] ?? '', 255),
            truncate_text($item['description'] ?? '', 5000),
            (int) ($item['stars'] ?? $item['stargazers_count'] ?? 0),
            (int) ($item['forks'] ?? $item['forks_count'] ?? 0),
            truncate_text($item['language'] ?? '', 64),
            truncate_text($topicsText, 5000),
            $pushedAt,
            $now,
            $now,
            $now,
        ]
    );
    return db_insert_id();
}

function upsert_report(int $projectId, ?int $runId, string $periodType, string $reportDate, array $analysis): void
{
    $now = date('Y-m-d H:i:s');
    $raw = json_encode($analysis, JSON_UNESCAPED_UNICODE);
    $existing = db_one(
        'SELECT id FROM project_reports WHERE project_id = ? AND period_type = ? AND report_date = ?',
        [$projectId, $periodType, $reportDate]
    );

    $params = [
        $runId ?: null,
        truncate_text($analysis['one_sentence'] ?? '', 255),
        truncate_text($analysis['project_type'] ?? '', 64),
        truncate_text($analysis['problem'] ?? $analysis['problem_text'] ?? '', 5000),
        list_to_text($analysis['tech_stack'] ?? ''),
        list_to_text($analysis['target_users'] ?? ''),
        clamp_score($analysis['play_score'] ?? 0),
        clamp_score($analysis['useful_score'] ?? 0),
        clamp_score($analysis['php_fit_score'] ?? 0),
        normalize_difficulty($analysis['difficulty'] ?? ''),
        !empty($analysis['is_suitable_for_this_host']) ? 1 : 0,
        list_to_text($analysis['ideas_to_reuse'] ?? ''),
        list_to_text($analysis['risks'] ?? ''),
        normalize_recommendation($analysis['recommendation'] ?? ''),
        truncate_text($analysis['summary_zh'] ?? '', 10000),
        $raw,
    ];

    if ($existing) {
        $params[] = (int) $existing['id'];
        db_exec(
            'UPDATE project_reports
             SET run_id = ?, one_sentence = ?, project_type = ?, problem_text = ?, tech_stack = ?, target_users = ?,
                 play_score = ?, useful_score = ?, php_fit_score = ?, difficulty = ?, is_suitable_for_this_host = ?,
                 ideas_to_reuse = ?, risks = ?, recommendation = ?, summary_zh = ?, raw_ai_json = ?
             WHERE id = ?',
            $params
        );
        return;
    }

    array_unshift($params, $projectId, $periodType, $reportDate);
    $params[] = $now;
    db_exec(
        'INSERT INTO project_reports
         (project_id, period_type, report_date, run_id, one_sentence, project_type, problem_text, tech_stack, target_users,
          play_score, useful_score, php_fit_score, difficulty, is_suitable_for_this_host, ideas_to_reuse, risks,
          recommendation, summary_zh, raw_ai_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $params
    );
}

function create_run(string $runType, string $source = ''): int
{
    $now = date('Y-m-d H:i:s');
    db_exec(
        'INSERT INTO runs (run_type, status, started_at, source, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$runType, 'started', $now, truncate_text($source, 64), $now, $now]
    );
    return db_insert_id();
}

function finish_run(int $runId, string $status, int $totalFound, int $totalAnalyzed, string $errorMessage = ''): void
{
    $now = date('Y-m-d H:i:s');
    db_exec(
        'UPDATE runs
         SET status = ?, finished_at = ?, total_found = ?, total_analyzed = ?, error_message = ?, updated_at = ?
         WHERE id = ?',
        [
            truncate_text($status, 16),
            $now,
            $totalFound,
            $totalAnalyzed,
            truncate_text($errorMessage, 5000),
            $now,
            $runId,
        ]
    );
}

function normalize_datetime($value): ?string
{
    if (!$value) {
        return null;
    }
    $time = strtotime((string) $value);
    return $time ? date('Y-m-d H:i:s', $time) : null;
}

function clamp_score($value): int
{
    $score = (int) $value;
    if ($score < 0) {
        return 0;
    }
    if ($score > 10) {
        return 10;
    }
    return $score;
}

function list_to_text($value): string
{
    if (is_array($value)) {
        return truncate_text(implode("\n", array_map('strval', $value)), 5000);
    }
    return truncate_text($value, 5000);
}

function normalize_difficulty($value): string
{
    $value = (string) $value;
    return in_array($value, ['低', '中', '高'], true) ? $value : '中';
}

function normalize_recommendation($value): string
{
    $value = (string) $value;
    return in_array($value, ['收藏', '研究', '可复刻', '暂不关注'], true) ? $value : '暂不关注';
}
