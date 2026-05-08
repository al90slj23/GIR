<?php
declare(strict_types=1);

function latest_reports(string $periodType, int $limit = 20): array
{
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ?
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
         WHERE r.period_type = ? AND r.report_date = ?
         ORDER BY r.php_fit_score DESC, r.useful_score DESC, r.play_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        [$periodType, $date]
    );
}

function recent_report_dates(string $periodType, int $limit = 14): array
{
    return db_all(
        'SELECT report_date, COUNT(*) AS total
         FROM project_reports
         WHERE period_type = ?
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
