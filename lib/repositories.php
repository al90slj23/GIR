<?php
declare(strict_types=1);

function ranking_platform_labels(): array
{
    return [
        'github' => 'GitHub 搜索',
        'github_trending' => 'GitHub 官方趋势',
        'github_search' => 'GitHub 搜索',
        'ossinsight' => 'OSSInsight 趋势',
        'trendshift' => 'Trendshift 趋势',
        'reporank' => 'RepoRank 排行',
        'gitrepotrend' => 'GitRepoTrend 热度',
        'backfill' => '全部项目库',
    ];
}

function ranking_platform_label(string $platform): string
{
    $labels = ranking_platform_labels();
    return $labels[$platform] ?? $platform;
}

function ranking_tag_labels(): array
{
    return [
        'daily' => '日榜',
        'weekly' => '周榜',
        'momentum' => '动量榜',
        'attention' => '关注榜',
        'ai-ml' => 'AI / 机器学习',
        'llm' => '大模型 / 生成式 AI',
        'webdev' => 'Web 开发',
        'devtools' => '开发工具',
        'devops' => 'DevOps / 云服务',
        'finance' => '金融 / 量化',
        'crypto' => '加密 / Web3',
        'security' => '安全',
        'datascience' => '数据科学',
        'mobile' => '移动开发',
        'rust' => 'Rust',
        'python' => 'Python',
        'go' => 'Go',
        '综合' => '综合',
        '新项目' => '新项目',
        'ai' => 'AI',
        'agent' => 'Agent',
        'php' => 'PHP',
        '额外搜索语句' => '额外搜索',
        'all_projects' => '全部项目',
    ];
}

function ranking_tag_label(string $tag): string
{
    $labels = ranking_tag_labels();
    return $labels[$tag] ?? $tag;
}

function progress_timestamp(string $value): int
{
    if ($value === '') {
        return 0;
    }
    $timestamp = strtotime($value);
    return $timestamp === false ? 0 : (int) $timestamp;
}

function progress_duration_text(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '计算中';
    }

    if ($seconds < 60) {
        return $seconds . ' 秒';
    }

    if ($seconds < 3600) {
        $minutes = (int) floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return $remainingSeconds > 0 ? $minutes . ' 分 ' . $remainingSeconds . ' 秒' : $minutes . ' 分';
    }

    if ($seconds < 86400) {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);
        return $minutes > 0 ? $hours . ' 时 ' . $minutes . ' 分' : $hours . ' 时';
    }

    $days = (int) floor($seconds / 86400);
    $hours = (int) floor(($seconds % 86400) / 3600);
    return $hours > 0 ? $days . ' 天 ' . $hours . ' 时' : $days . ' 天';
}

function progress_duration_precise_text(?int $seconds): string
{
    if ($seconds === null || $seconds < 0) {
        return '计算中';
    }

    $days = (int) floor($seconds / 86400);
    $hours = (int) floor(($seconds % 86400) / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;
    $parts = [];

    if ($days > 0) {
        $parts[] = $days . ' 天';
    }
    if ($days > 0 || $hours > 0) {
        $parts[] = $hours . ' 时';
    }
    if ($days > 0 || $hours > 0 || $minutes > 0) {
        $parts[] = $minutes . ' 分';
    }
    $parts[] = $remainingSeconds . ' 秒';

    return implode(' ', $parts);
}

function progress_rate_text(float $ratePerHour): string
{
    if ($ratePerHour <= 0) {
        return '计算中';
    }

    if ($ratePerHour >= 10) {
        return number_format($ratePerHour, 0) . ' 条/小时';
    }

    return number_format($ratePerHour, 1) . ' 条/小时';
}

function progress_timing(array $row, int $rawRank, int $analyzed): array
{
    $now = time();
    $startedAt = (string) ($row['started_at'] ?? '');
    $firstAnalysisAt = (string) ($row['first_analysis_at'] ?? '');
    $latestAnalysisAt = (string) ($row['latest_analysis_at'] ?? '');
    $startedTimestamp = progress_timestamp($startedAt);
    $firstAnalysisTimestamp = progress_timestamp($firstAnalysisAt);
    $elapsedSeconds = $startedTimestamp > 0 ? max(0, $now - $startedTimestamp) : null;
    $analysisElapsedSeconds = ($firstAnalysisTimestamp > 0 && $analyzed > 0) ? max(1, $now - $firstAnalysisTimestamp) : null;
    $ratePerSecond = ($analysisElapsedSeconds !== null && $analysisElapsedSeconds > 0) ? $analyzed / $analysisElapsedSeconds : 0.0;
    $ratePerHour = $ratePerSecond > 0 ? $ratePerSecond * 3600 : 0.0;
    $remaining = max(0, $rawRank - $analyzed);
    $etaSeconds = $ratePerSecond > 0 ? (int) ceil($remaining / $ratePerSecond) : null;
    $estimatedTotalSeconds = ($elapsedSeconds !== null && $etaSeconds !== null) ? $elapsedSeconds + $etaSeconds : null;
    $estimatedFinishAt = $etaSeconds !== null ? date('Y-m-d H:i:s', $now + $etaSeconds) : '';

    return [
        'started_at' => $startedAt,
        'first_analysis_at' => $firstAnalysisAt,
        'latest_analysis_at' => $latestAnalysisAt,
        'elapsed_seconds' => $elapsedSeconds,
        'elapsed_text' => progress_duration_text($elapsedSeconds),
        'analysis_elapsed_seconds' => $analysisElapsedSeconds,
        'analysis_elapsed_text' => progress_duration_text($analysisElapsedSeconds),
        'remaining' => $remaining,
        'rate_per_hour' => round($ratePerHour, 1),
        'rate_text' => progress_rate_text($ratePerHour),
        'eta_seconds' => $etaSeconds,
        'eta_text' => progress_duration_text($etaSeconds),
        'estimated_total_seconds' => $estimatedTotalSeconds,
        'estimated_total_text' => progress_duration_text($estimatedTotalSeconds),
        'estimated_finish_at' => $estimatedFinishAt,
    ];
}

function report_date_filter_sql(string $periodType, string $date = ''): array
{
    if ($date !== '') {
        return ['sql' => ' AND r.report_date = ?', 'params' => [$date]];
    }
    return [
        'sql' => ' AND r.report_date = (SELECT MAX(report_date) FROM project_reports WHERE period_type = ?)',
        'params' => [$periodType],
    ];
}

function report_date_range_options(): array
{
    return [
        'today' => '今天',
        '3d' => '最近三天',
        '7d' => '最近一周',
        '30d' => '最近一月',
        '365d' => '最近一年',
        'custom' => '自定义时间',
    ];
}

function sanitize_report_date(string $value): string
{
    $date = preg_replace('/[^0-9\-]/', '', $value);
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '';
    }
    return $date;
}

function report_date_range(string $range, string $startDate = '', string $endDate = ''): array
{
    $range = array_key_exists($range, report_date_range_options()) ? $range : 'today';
    $today = date('Y-m-d');

    if ($range === 'custom') {
        $start = sanitize_report_date($startDate);
        $end = sanitize_report_date($endDate);
        if ($start === '' && $end === '') {
            $start = $today;
            $end = $today;
        } elseif ($start === '') {
            $start = $end;
        } elseif ($end === '') {
            $end = $start;
        }
        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }
        return ['range' => $range, 'start' => $start, 'end' => $end, 'label' => $start . ' 至 ' . $end];
    }

    $days = [
        'today' => 1,
        'yesterday' => 1,
        '3d' => 3,
        '7d' => 7,
        '30d' => 30,
        '365d' => 365,
    ];
    if ($range === 'yesterday') {
        $date = date('Y-m-d', strtotime('-1 day'));
        return ['range' => $range, 'start' => $date, 'end' => $date, 'label' => report_date_range_options()[$range]];
    }

    $span = $days[$range] ?? 1;
    $start = date('Y-m-d', strtotime('-' . ($span - 1) . ' days'));
    return ['range' => $range, 'start' => $start, 'end' => $today, 'label' => report_date_range_options()[$range]];
}

function report_date_range_filter_sql(array $dateRange): array
{
    return [
        'sql' => ' AND r.report_date BETWEEN ? AND ?',
        'params' => [(string) $dateRange['start'], (string) $dateRange['end']],
    ];
}

function analyzed_report_sql(): string
{
    return " AND r.raw_rank_only = 0 AND r.one_sentence <> ''";
}

function raw_rank_report_sql(): string
{
    return " AND r.raw_rank_only = 1";
}

function ranking_platform_order_sql(string $column = 'source_platform'): string
{
    return 'CASE ' . $column . "
        WHEN 'github_trending' THEN 10
        WHEN 'github' THEN 20
        WHEN 'github_search' THEN 21
        WHEN 'gitrepotrend' THEN 30
        WHEN 'ossinsight' THEN 40
        WHEN 'trendshift' THEN 50
        WHEN 'reporank' THEN 60
        WHEN 'backfill' THEN 999
        ELSE 500
    END";
}

function merge_full_totals(array $rows, array $fullRows, string $key = 'source_platform'): array
{
    $currentRows = [];
    foreach ($rows as $row) {
        $currentRows[(string) ($row[$key] ?? '')] = $row;
    }

    $merged = [];
    foreach ($fullRows as $fullRow) {
        $value = (string) ($fullRow[$key] ?? '');
        $row = $currentRows[$value] ?? $fullRow;
        $row['total'] = isset($currentRows[$value]) ? (int) ($currentRows[$value]['total'] ?? 0) : 0;
        $row['full_total'] = (int) ($fullRow['total'] ?? 0);
        $merged[] = $row;
        unset($currentRows[$value]);
    }

    foreach ($currentRows as $row) {
        $value = (string) ($row[$key] ?? '');
        if ($value === '') {
            continue;
        }
        $row['full_total'] = (int) ($row['total'] ?? 0);
        $merged[] = $row;
    }

    return $merged;
}

function raw_rank_analysis_select_sql(): string
{
    return ', ar.one_sentence AS analysis_one_sentence,
              ar.summary_zh AS analysis_summary_zh,
              ar.change_note AS analysis_change_note,
              ar.project_type AS analysis_project_type';
}

function raw_rank_analysis_join_sql(): string
{
    return ' LEFT JOIN project_reports ar ON ar.id = (
                 SELECT rr.id
                 FROM project_reports rr
                 WHERE rr.project_id = r.project_id
                   AND rr.raw_rank_only = 0
                   AND rr.one_sentence <> ""
                 ORDER BY rr.report_date DESC, rr.id DESC
                 LIMIT 1
             )';
}

function ranking_primary_platform_filter_sql(string $alias = 'r'): string
{
    $prefix = $alias === '' ? '' : $alias . '.';
    return ' AND ' . $prefix . "source_platform NOT IN ('github', 'github_search')";
}

function available_ranking_platforms(string $periodType, string $date = ''): array
{
    $dateFilter = report_date_filter_sql($periodType, $date);
    $rows = db_all(
        'SELECT r.source_platform, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . ranking_primary_platform_filter_sql('r') . $dateFilter['sql'] . '
         GROUP BY r.source_platform
         ORDER BY ' . ranking_platform_order_sql('source_platform') . ', total DESC, r.source_platform ASC',
        array_merge([$periodType], $dateFilter['params'])
    );
    return merge_full_totals($rows, all_ranking_platform_totals($periodType), 'source_platform');
}

function available_ranking_platforms_by_range(string $periodType, array $dateRange): array
{
    $dateFilter = report_date_range_filter_sql($dateRange);
    $rows = db_all(
        'SELECT r.source_platform, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . ranking_primary_platform_filter_sql('r') . $dateFilter['sql'] . '
         GROUP BY r.source_platform
         ORDER BY ' . ranking_platform_order_sql('source_platform') . ', total DESC, r.source_platform ASC',
        array_merge([$periodType], $dateFilter['params'])
    );
    return merge_full_totals($rows, all_ranking_platform_totals($periodType), 'source_platform');
}

function all_ranking_platform_totals(string $periodType): array
{
    return db_all(
        'SELECT r.source_platform, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . ranking_primary_platform_filter_sql('r') . '
         GROUP BY r.source_platform
         ORDER BY ' . ranking_platform_order_sql('source_platform') . ', total DESC, r.source_platform ASC',
        [$periodType]
    );
}

function available_ranking_tags(string $periodType, string $platform, string $date = ''): array
{
    $dateFilter = report_date_filter_sql($periodType, $date);
    $rows = db_all(
        'SELECT r.source_tag, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.source_platform = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_tag
         ORDER BY total DESC, r.source_tag ASC',
        array_merge([$periodType, $platform], $dateFilter['params'])
    );
    return merge_full_totals($rows, all_ranking_tag_totals($periodType, $platform), 'source_tag');
}

function available_ranking_tags_by_range(string $periodType, string $platform, array $dateRange): array
{
    $dateFilter = report_date_range_filter_sql($dateRange);
    $rows = db_all(
        'SELECT r.source_tag, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.source_platform = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_tag
         ORDER BY total DESC, r.source_tag ASC',
        array_merge([$periodType, $platform], $dateFilter['params'])
    );
    return merge_full_totals($rows, all_ranking_tag_totals($periodType, $platform), 'source_tag');
}

function all_ranking_tag_totals(string $periodType, string $platform): array
{
    return db_all(
        'SELECT r.source_tag, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.source_platform = ? AND p.is_hidden = 0' . raw_rank_report_sql() . '
         GROUP BY r.source_tag
         ORDER BY total DESC, r.source_tag ASC',
        [$periodType, $platform]
    );
}

function raw_rank_count_by_range(string $periodType, array $dateRange, string $platform = '', string $tag = ''): int
{
    $filters = report_source_filter($platform, $tag);
    $dateFilter = report_date_range_filter_sql($dateRange);
    $row = db_one(
        'SELECT COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . $filters['sql'],
        array_merge([$periodType], $dateFilter['params'], $filters['params'])
    );
    return (int) ($row['total'] ?? 0);
}

function raw_rank_count_all(string $periodType, string $platform = '', string $tag = ''): int
{
    $filters = report_source_filter($platform, $tag);
    $row = db_one(
        'SELECT COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $filters['sql'],
        array_merge([$periodType], $filters['params'])
    );
    return (int) ($row['total'] ?? 0);
}

function latest_reports(string $periodType, int $limit = 20, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . analyzed_report_sql() . $filters['sql'] . '
         ORDER BY r.report_date DESC, r.useful_score DESC, r.maturity_score DESC, r.play_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType], $filters['params'])
    );
}

function github_rank_reports(string $periodType, int $limit = 20, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at' . raw_rank_analysis_select_sql() . '
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         ' . raw_rank_analysis_join_sql() . '
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $filters['sql'] . '
         ORDER BY r.report_date DESC, r.source_rank ASC, r.source_score DESC, p.stars DESC, p.forks DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType], $filters['params'])
    );
}

function reports_by_date(string $periodType, string $date, int $limit = 30, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.report_date = ? AND p.is_hidden = 0' . analyzed_report_sql() . $filters['sql'] . '
         ORDER BY r.useful_score DESC, r.maturity_score DESC, r.play_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType, $date], $filters['params'])
    );
}

function reports_by_range(string $periodType, array $dateRange, int $limit = 30, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    $dateFilter = report_date_range_filter_sql($dateRange);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . analyzed_report_sql() . $dateFilter['sql'] . $filters['sql'] . '
         ORDER BY r.report_date DESC, r.useful_score DESC, r.maturity_score DESC, r.play_score DESC, p.stars DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType], $dateFilter['params'], $filters['params'])
    );
}

function github_rank_reports_by_date(string $periodType, string $date, int $limit = 30, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at' . raw_rank_analysis_select_sql() . '
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         ' . raw_rank_analysis_join_sql() . '
         WHERE r.period_type = ? AND r.report_date = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $filters['sql'] . '
         ORDER BY r.source_rank ASC, r.source_score DESC, p.stars DESC, p.forks DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType, $date], $filters['params'])
    );
}

function github_rank_reports_by_range(string $periodType, array $dateRange, int $limit = 30, string $platform = '', string $tag = ''): array
{
    $filters = report_source_filter($platform, $tag);
    $dateFilter = report_date_range_filter_sql($dateRange);
    return db_all(
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at' . raw_rank_analysis_select_sql() . '
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         ' . raw_rank_analysis_join_sql() . '
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . $filters['sql'] . '
         ORDER BY r.report_date DESC, r.source_rank ASC, r.source_score DESC, p.stars DESC, p.forks DESC
         LIMIT ' . (int) $limit,
        array_merge([$periodType], $dateFilter['params'], $filters['params'])
    );
}

function report_source_filter(string $platform, string $tag): array
{
    $sql = '';
    $params = [];
    if ($platform !== '') {
        $sql .= ' AND r.source_platform = ?';
        $params[] = truncate_text($platform, 64);
    }
    if ($tag !== '') {
        $sql .= ' AND r.source_tag = ?';
        $params[] = truncate_text($tag, 64);
    }
    return ['sql' => $sql, 'params' => $params];
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
    $project = db_one(
        'SELECT id, github_id, name, full_name, html_url, description, stars, forks, language, topics,
                pushed_at, is_hidden, admin_status, admin_note, discovered_at, created_at, updated_at
         FROM projects
         WHERE id = ?',
        [$id]
    );
    if (!$project) {
        return null;
    }
    $project['reports'] = db_all(
        "SELECT id, project_id, run_id, period_type, report_date, source_platform, source_tag, source_rank, source_score,
                raw_rank_only, one_sentence, project_type, problem_text, tech_stack, target_users,
                play_score, useful_score, maturity_score, php_fit_score, difficulty, is_suitable_for_this_host,
                ideas_to_reuse, risks, change_note, change_observation, analysis_detail,
                previous_report_id, star_growth, fork_growth, span_days,
                recommendation, summary_zh, created_at
         FROM project_reports
         WHERE project_id = ? AND raw_rank_only = 0 AND one_sentence <> ''
         ORDER BY report_date DESC, id DESC",
        [$id]
    );
    return $project;
}

function search_projects(string $query, int $limit = 30): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $like = '%' . $query . '%';
    return db_all(
        'SELECT p.id AS project_id, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at,
                ar.one_sentence AS analysis_one_sentence,
                ar.summary_zh AS analysis_summary_zh,
                ar.change_note AS analysis_change_note,
                ar.project_type AS analysis_project_type,
                ar.report_date AS analysis_report_date,
                ar.source_platform AS analysis_source_platform,
                ar.source_tag AS analysis_source_tag
         FROM projects p
         LEFT JOIN project_reports ar ON ar.id = (
             SELECT rr.id
             FROM project_reports rr
             WHERE rr.project_id = p.id
               AND rr.raw_rank_only = 0
               AND rr.one_sentence <> ""
             ORDER BY rr.report_date DESC, rr.id DESC
             LIMIT 1
         )
         WHERE p.is_hidden = 0
           AND (p.full_name LIKE ? OR p.name LIKE ? OR p.description LIKE ? OR p.language LIKE ? OR p.topics LIKE ?)
         ORDER BY
           CASE WHEN p.full_name LIKE ? THEN 0 ELSE 1 END,
           p.stars DESC,
           p.forks DESC,
           p.updated_at DESC
         LIMIT ' . (int) $limit,
        [$like, $like, $like, $like, $like, $like]
    );
}

function projects_index_by_full_names(array $fullNames): array
{
    $clean = [];
    foreach ($fullNames as $fullName) {
        $fullName = trim((string) $fullName);
        if ($fullName !== '') {
            $clean[] = $fullName;
        }
    }
    $clean = array_values(array_unique($clean));
    if (!$clean) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($clean), '?'));
    $rows = db_all(
        'SELECT p.id AS project_id, p.full_name,
                ar.id AS analysis_id,
                ar.one_sentence AS analysis_one_sentence,
                ar.summary_zh AS analysis_summary_zh,
                ar.change_note AS analysis_change_note,
                ar.report_date AS analysis_report_date
         FROM projects p
         LEFT JOIN project_reports ar ON ar.id = (
             SELECT rr.id
             FROM project_reports rr
             WHERE rr.project_id = p.id
               AND rr.raw_rank_only = 0
               AND rr.one_sentence <> ""
             ORDER BY rr.report_date DESC, rr.id DESC
             LIMIT 1
         )
         WHERE p.full_name IN (' . $placeholders . ')',
        $clean
    );

    $index = [];
    foreach ($rows as $row) {
        $index[(string) $row['full_name']] = $row;
    }
    return $index;
}

function github_search_repositories(string $query, int $limit = 20): array
{
    global $config;

    $query = trim($query);
    if ($query === '') {
        return ['ok' => true, 'items' => [], 'total_count' => 0, 'error' => ''];
    }

    $limit = max(1, min(50, $limit));
    $url = 'https://api.github.com/search/repositories?' . http_build_query([
        'q' => $query,
        'sort' => 'stars',
        'order' => 'desc',
        'per_page' => $limit,
    ]);

    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if (!empty($config['github']['token'])) {
        $headers[] = 'Authorization: Bearer ' . $config['github']['token'];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'GIR GitHub Search',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
    ], http_ssl_options()));

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'items' => [], 'total_count' => 0, 'error' => 'curl_error: ' . $error, 'http' => $http];
    }

    $data = json_decode((string) $response, true);
    if ($http < 200 || $http >= 300 || !is_array($data)) {
        $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : truncate_text((string) $response, 300);
        return ['ok' => false, 'items' => [], 'total_count' => 0, 'error' => 'github_search_failed: HTTP ' . $http . ' ' . $message, 'http' => $http];
    }

    $items = [];
    foreach (($data['items'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $fullName = trim((string) ($item['full_name'] ?? ''));
        if ($fullName === '') {
            continue;
        }
        $items[] = [
            'github_id' => (int) ($item['id'] ?? 0),
            'name' => (string) ($item['name'] ?? ''),
            'full_name' => $fullName,
            'html_url' => (string) ($item['html_url'] ?? ('https://github.com/' . $fullName)),
            'description' => (string) ($item['description'] ?? ''),
            'stars' => (int) ($item['stargazers_count'] ?? 0),
            'forks' => (int) ($item['forks_count'] ?? 0),
            'language' => (string) ($item['language'] ?? ''),
            'topics' => is_array($item['topics'] ?? null) ? $item['topics'] : [],
            'pushed_at' => normalize_datetime($item['pushed_at'] ?? null),
            'updated_at' => normalize_datetime($item['updated_at'] ?? null),
            'is_fork' => !empty($item['fork']),
            'is_archived' => !empty($item['archived']),
            'open_issues' => (int) ($item['open_issues_count'] ?? 0),
        ];
    }

    return [
        'ok' => true,
        'items' => $items,
        'total_count' => (int) ($data['total_count'] ?? count($items)),
        'error' => '',
        'http' => $http,
    ];
}

function github_discover_active_run(): ?array
{
    global $config;

    static $memo = false;
    if ($memo !== false) {
        return is_array($memo) ? $memo : null;
    }

    $owner = trim((string) ($config['github']['owner'] ?? ''));
    $repo = trim((string) ($config['github']['repo'] ?? ''));
    $token = trim((string) ($config['github']['token'] ?? ''));
    $workflow = trim((string) ($config['github']['workflow'] ?? 'discover.yml'));
    if ($owner === '' || $repo === '' || $token === '' || !function_exists('curl_init')) {
        $memo = null;
        return null;
    }

    $cacheKey = md5($owner . '/' . $repo . '/' . $workflow);
    $cachePath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'gir_discover_run_' . $cacheKey . '.json';
    $cacheTtl = 12;

    if (is_file($cachePath)) {
        $raw = @file_get_contents($cachePath);
        $cached = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($cached) && (int) ($cached['cached_at'] ?? 0) + $cacheTtl >= time()) {
            $memo = is_array($cached['data'] ?? null) ? $cached['data'] : null;
            return $memo;
        }
    }

    $url = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo)
        . '/actions/workflows/' . rawurlencode($workflow) . '/runs?' . http_build_query([
            'per_page' => 6,
            'exclude_pull_requests' => 'true',
        ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'GIR Progress Monitor',
        CURLOPT_HTTPHEADER => [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $token,
            'X-GitHub-Api-Version: 2022-11-28',
        ],
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ], http_ssl_options()));

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = null;
    if (!$errno && $http >= 200 && $http < 300) {
        $payload = json_decode((string) $response, true);
        $runs = is_array($payload['workflow_runs'] ?? null) ? $payload['workflow_runs'] : [];
        foreach ($runs as $run) {
            if (!is_array($run)) {
                continue;
            }
            $status = (string) ($run['status'] ?? '');
            if ($status === 'completed') {
                continue;
            }
            $result = [
                'run_id' => (int) ($run['id'] ?? 0),
                'status' => $status,
                'event' => (string) ($run['event'] ?? ''),
                'title' => (string) ($run['display_title'] ?? ''),
                'html_url' => (string) ($run['html_url'] ?? ''),
                'created_at' => normalize_datetime($run['created_at'] ?? null) ?: '',
                'started_at' => normalize_datetime($run['run_started_at'] ?? null) ?: '',
                'updated_at' => normalize_datetime($run['updated_at'] ?? null) ?: '',
            ];
            break;
        }
    }

    @file_put_contents($cachePath, json_encode([
        'cached_at' => time(),
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE));

    $memo = $result;
    return $result;
}

function github_search_query_hash(string $query): string
{
    return sha1(trim(strtolower($query)));
}

function github_search_recent_dispatch(string $query, int $cooldownMinutes = 30): ?array
{
    $hash = github_search_query_hash($query);
    $row = db_one('SELECT * FROM github_search_requests WHERE query_hash = ?', [$hash]);
    if (!$row) {
        return null;
    }
    $lastDispatchedAt = (string) ($row['last_dispatched_at'] ?? '');
    $timestamp = $lastDispatchedAt !== '' ? strtotime($lastDispatchedAt) : 0;
    if ($timestamp && time() - (int) $timestamp < $cooldownMinutes * 60) {
        return $row;
    }
    return null;
}

function record_github_search_request(string $query, string $status, string $error = ''): void
{
    $now = date('Y-m-d H:i:s');
    db_exec(
        'INSERT INTO github_search_requests
         (query_hash, query_text, status, last_error, last_dispatched_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
           query_text = VALUES(query_text),
           status = VALUES(status),
           last_error = VALUES(last_error),
           last_dispatched_at = VALUES(last_dispatched_at),
           updated_at = VALUES(updated_at)',
        [
            github_search_query_hash($query),
            truncate_text($query, 500),
            truncate_text($status, 24),
            truncate_text($error, 1000),
            $now,
            $now,
            $now,
        ]
    );
}

function trigger_github_search_ingest(string $query): array
{
    $query = trim($query);
    if ($query === '') {
        return ['ok' => false, 'error' => 'empty_query'];
    }

    $recent = github_search_recent_dispatch($query);
    if ($recent) {
        return [
            'ok' => true,
            'skipped' => true,
            'status' => (string) ($recent['status'] ?? 'dispatched'),
            'last_dispatched_at' => (string) ($recent['last_dispatched_at'] ?? ''),
        ];
    }

    $result = trigger_github_workflow([
        'run_type' => 'manual',
        'platforms' => 'github_search',
        'extra_query' => $query,
    ]);
    record_github_search_request($query, $result['ok'] ? 'dispatched' : 'failed', (string) ($result['error'] ?? ''));
    return $result + ['skipped' => false];
}

function ingest_github_search_results_locally(string $query, array $items): array
{
    $stored = 0;
    $projectIds = [];
    $reportDate = date('Y-m-d');
    $sourceTag = '搜索:' . truncate_text($query, 52);

    foreach ($items as $index => $item) {
        if (!is_array($item) || trim((string) ($item['full_name'] ?? '')) === '') {
            continue;
        }
        $projectId = upsert_project([
            'github_id' => (int) ($item['github_id'] ?? 0),
            'name' => (string) ($item['name'] ?? ''),
            'full_name' => (string) ($item['full_name'] ?? ''),
            'html_url' => (string) ($item['html_url'] ?? ''),
            'description' => (string) ($item['description'] ?? ''),
            'stars' => (int) ($item['stars'] ?? 0),
            'forks' => (int) ($item['forks'] ?? 0),
            'language' => (string) ($item['language'] ?? ''),
            'topics' => $item['topics'] ?? [],
            'pushed_at' => (string) ($item['pushed_at'] ?? ''),
        ]);
        if ($projectId <= 0) {
            continue;
        }
        upsert_report(
            $projectId,
            null,
            'manual',
            $reportDate,
            ['raw_rank_only' => true],
            [
                'platform' => 'github_search',
                'tag' => $sourceTag,
                'rank' => $index + 1,
                'score' => (float) ($item['stars'] ?? 0),
            ]
        );
        $projectIds[(string) ($item['full_name'] ?? '')] = $projectId;
        $stored++;
    }

    return ['stored' => $stored, 'project_ids' => $projectIds];
}

function recent_project_analyses(string $fullName, int $limit = 5): array
{
    return db_all(
        "SELECT r.report_date, r.source_platform, r.source_tag, r.one_sentence, r.summary_zh, r.change_note,
                r.recommendation, r.play_score, r.useful_score, r.maturity_score, r.difficulty, r.created_at,
                r.star_growth, r.fork_growth, r.span_days, r.raw_ai_json
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE p.full_name = ? AND r.raw_rank_only = 0 AND r.one_sentence <> ''
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT " . (int) $limit,
        [$fullName]
    );
}

function recent_runs(int $limit = 20): array
{
    return db_all(
        'SELECT * FROM runs ORDER BY started_at DESC, id DESC LIMIT ' . (int) $limit
    );
}

function admin_stats(): array
{
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

    return [
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
        'platforms' => db_all(
            'SELECT source_platform,
                    COUNT(*) AS total,
                    SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
                    SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank,
                    COUNT(DISTINCT project_id) AS projects
             FROM project_reports
             GROUP BY source_platform
             ORDER BY total DESC, source_platform ASC'
        ),
        'tags' => db_all(
            'SELECT source_platform, source_tag,
                    COUNT(*) AS total,
                    SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
                    SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank
             FROM project_reports
             GROUP BY source_platform, source_tag
             ORDER BY total DESC, source_platform ASC, source_tag ASC
             LIMIT 80'
        ),
        'dates' => db_all(
            'SELECT report_date,
                    COUNT(*) AS total,
                    SUM(CASE WHEN raw_rank_only = 0 AND one_sentence <> "" THEN 1 ELSE 0 END) AS analyzed,
                    SUM(CASE WHEN raw_rank_only = 1 THEN 1 ELSE 0 END) AS raw_rank,
                    COUNT(DISTINCT project_id) AS projects
             FROM project_reports
             GROUP BY report_date
             ORDER BY report_date DESC
             LIMIT 30'
        ),
        'runs' => recent_runs(20),
    ];
}

function latest_run_summary(?array $latestRunRow): ?array
{
    if (!$latestRunRow) {
        return null;
    }

    return [
        'status' => (string) ($latestRunRow['status'] ?? ''),
        'run_type' => (string) ($latestRunRow['run_type'] ?? ''),
        'started_at' => (string) ($latestRunRow['started_at'] ?? ''),
        'finished_at' => (string) ($latestRunRow['finished_at'] ?? ''),
        'updated_at' => (string) ($latestRunRow['updated_at'] ?? ''),
        'total_found' => (int) ($latestRunRow['total_found'] ?? 0),
        'total_analyzed' => (int) ($latestRunRow['total_analyzed'] ?? 0),
        'source' => (string) ($latestRunRow['source'] ?? ''),
    ];
}

function run_recent_seconds(?array $latestRunRow): int
{
    if (!$latestRunRow) {
        return 999999;
    }
    $latestRunTimestamp = 0;
    foreach (['updated_at', 'finished_at', 'started_at'] as $timeKey) {
        $timestamp = isset($latestRunRow[$timeKey]) ? (int) strtotime((string) $latestRunRow[$timeKey]) : 0;
        if ($timestamp > $latestRunTimestamp) {
            $latestRunTimestamp = $timestamp;
        }
    }
    return $latestRunTimestamp > 0 ? time() - $latestRunTimestamp : 999999;
}

function progress_next_schedule(string $kind, bool $preferBacklog = false): array
{
    $now = time();
    if ($kind === 'gir' && $preferBacklog && discover_bool_setting('discover_backlog_enabled', true)) {
        $minute = (int) date('i', $now);
        $nextMinute = ((int) floor($minute / 30) + 1) * 30;
        $timestamp = $nextMinute >= 60
            ? strtotime(date('Y-m-d H:00:00', strtotime('+1 hour', $now)))
            : strtotime(date('Y-m-d H:', $now) . str_pad((string) $nextMinute, 2, '0', STR_PAD_LEFT) . ':00');
        $timestamp = $timestamp ?: strtotime('+30 minutes', $now);
        $seconds = max(0, $timestamp - $now);
        return [
            'label' => '每 30 分钟自动补跑',
            'at' => date('Y-m-d H:i:s', $timestamp),
            'remaining_seconds' => $seconds,
            'remaining_text' => progress_duration_precise_text($seconds),
        ];
    }

    $today = date('Y-m-d');
    $daily = strtotime($today . ' 09:00:00');
    if ($daily !== false && $daily <= $now) {
        $daily = strtotime('+1 day', $daily);
    }

    $weekly = strtotime('monday this week 09:30:00');
    if ($weekly !== false && $weekly <= $now) {
        $weekly = strtotime('next monday 09:30:00');
    }

    $daily = $daily ?: strtotime('+1 day 09:00:00');
    $weekly = $weekly ?: strtotime('next monday 09:30:00');
    $useWeekly = $weekly !== false && $weekly < $daily;
    $timestamp = $useWeekly ? (int) $weekly : (int) $daily;
    $baseLabel = $useWeekly ? '每周自动采集' : '每日自动采集';
    $label = $kind === 'gir' ? ($useWeekly ? '随每周采集触发' : '随每日采集触发') : $baseLabel;
    $seconds = max(0, $timestamp - $now);

    return [
        'label' => $label,
        'at' => date('Y-m-d H:i:s', $timestamp),
        'remaining_seconds' => $seconds,
        'remaining_text' => progress_duration_precise_text($seconds),
    ];
}

function progress_span_text(?string $start, ?string $end): string
{
    $startTime = $start ? strtotime($start) : 0;
    $endTime = $end ? strtotime($end) : 0;
    if (!$startTime || !$endTime || $endTime < $startTime) {
        return '-';
    }
    return progress_duration_text($endTime - $startTime);
}

function progress_run_history_stats(string $kind): array
{
    if ($kind === 'gir') {
        $runStats = db_one(
            'SELECT COUNT(*) AS total_runs,
                    SUM(CASE WHEN run_type IN ("daily", "weekly", "backlog") THEN 1 ELSE 0 END) AS auto_runs,
                    SUM(CASE WHEN run_type = "manual" THEN 1 ELSE 0 END) AS manual_runs,
                    SUM(total_analyzed) AS total_analyzed,
                    SUM(CASE WHEN finished_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, started_at, finished_at) ELSE 0 END) AS total_seconds,
                    MAX(COALESCE(finished_at, updated_at, started_at)) AS latest_finished_at
             FROM runs
             WHERE total_analyzed > 0'
        );
        $reportStats = db_one(
            'SELECT COUNT(*) AS total_reports,
                    COUNT(DISTINCT project_id) AS projects,
                    MIN(created_at) AS first_at,
                    MAX(created_at) AS latest_at
             FROM project_reports
             WHERE raw_rank_only = 0 AND one_sentence <> ""'
        );

        return [
            'label' => '累计解读统计',
            'stats' => [
                ['label' => '累计解读次数', 'value' => number_format((int) ($reportStats['total_reports'] ?? 0))],
                ['label' => '已解读项目', 'value' => number_format((int) ($reportStats['projects'] ?? 0))],
                ['label' => '解读批次数', 'value' => number_format((int) ($runStats['total_runs'] ?? 0))],
                ['label' => '自动周期批次', 'value' => number_format((int) ($runStats['auto_runs'] ?? 0))],
                ['label' => '手动/搜索批次', 'value' => number_format((int) ($runStats['manual_runs'] ?? 0))],
                ['label' => '解读时间跨度', 'value' => progress_span_text($reportStats['first_at'] ?? null, $reportStats['latest_at'] ?? null)],
                ['label' => '最近解读', 'value' => (string) (($reportStats['latest_at'] ?? '') ?: '-'), 'wide' => true],
            ],
        ];
    }

    $runStats = db_one(
        'SELECT COUNT(*) AS total_runs,
                SUM(CASE WHEN run_type IN ("daily", "weekly") THEN 1 ELSE 0 END) AS auto_runs,
                SUM(CASE WHEN run_type = "manual" THEN 1 ELSE 0 END) AS manual_runs,
                SUM(total_found) AS total_found,
                SUM(CASE WHEN finished_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, started_at, finished_at) ELSE 0 END) AS total_seconds,
                MAX(COALESCE(finished_at, updated_at, started_at)) AS latest_finished_at
         FROM runs
         WHERE total_found > 0 AND total_analyzed = 0'
    );
    $reportStats = db_one(
        'SELECT COUNT(*) AS raw_reports,
                COUNT(DISTINCT project_id) AS projects,
                MIN(created_at) AS first_at,
                MAX(created_at) AS latest_at
         FROM project_reports
         WHERE raw_rank_only = 1'
    );

    return [
        'label' => '累计采集统计',
        'stats' => [
            ['label' => '累计候选记录', 'value' => number_format((int) ($reportStats['raw_reports'] ?? 0))],
            ['label' => '去重项目数', 'value' => number_format((int) ($reportStats['projects'] ?? 0))],
            ['label' => '采集批次数', 'value' => number_format((int) ($runStats['total_runs'] ?? 0))],
            ['label' => '自动周期批次', 'value' => number_format((int) ($runStats['auto_runs'] ?? 0))],
            ['label' => '手动/搜索批次', 'value' => number_format((int) ($runStats['manual_runs'] ?? 0))],
            ['label' => '采集时间跨度', 'value' => progress_span_text($reportStats['first_at'] ?? null, $reportStats['latest_at'] ?? null)],
            ['label' => '最近入库', 'value' => (string) (($reportStats['latest_at'] ?? '') ?: '-'), 'wide' => true],
        ],
    ];
}

function public_collection_progress_summary(?array $latestRunRow): array
{
    $completion = collection_completion_stats();
    $total = $completion['total'];
    $withReadme = $completion['with_readme_raw'];
    $pendingReadme = $completion['pending_readme'];
    $withZh = $completion['with_readme_zh'];
    $pendingZh = max(0, $withReadme - $withZh);
    $percent = $total > 0 ? round(min(100, ($withReadme / $total) * 100), 1) : 0;

    $latestAt = '';
    $reportStats = db_one(
        "SELECT MIN(created_at) AS first_at, MAX(created_at) AS latest_at
         FROM project_readmes WHERE is_translated = 0"
    );
    $latestAt = (string) ($reportStats['latest_at'] ?? '');
    $firstAt = (string) ($reportStats['first_at'] ?? '');

    $active = ($latestRunRow && (string) ($latestRunRow['status'] ?? '') === 'started')
        || ($pendingReadme > 0 && $latestAt !== '' && time() - (int) strtotime($latestAt) <= 20 * 60);
    $mode = $active ? 'running' : 'idle';

    // Timing: rate based on recent README ingestion speed.
    $recentRate = 0.0;
    if ($latestAt !== '' && $firstAt !== '') {
        $span = max(1, (int) strtotime($latestAt) - (int) strtotime($firstAt));
        $recentRate = $withReadme > 1 ? ($withReadme / ($span / 3600.0)) : 0;
    }
    $etaSeconds = ($recentRate > 0 && $pendingReadme > 0) ? (int) ceil($pendingReadme / ($recentRate / 3600)) : null;

    // History stats (raw_rank collection runs).
    $runStats = db_one(
        'SELECT COUNT(*) AS total_runs,
                SUM(CASE WHEN run_type IN ("daily", "weekly") THEN 1 ELSE 0 END) AS auto_runs,
                SUM(CASE WHEN run_type = "manual" THEN 1 ELSE 0 END) AS manual_runs,
                SUM(total_found) AS total_found
         FROM runs
         WHERE total_found > 0 AND total_analyzed = 0'
    );
    $rawReportStats = db_one(
        'SELECT COUNT(*) AS raw_reports,
                COUNT(DISTINCT project_id) AS projects,
                MIN(created_at) AS first_at,
                MAX(created_at) AS latest_at
         FROM project_reports
         WHERE raw_rank_only = 1'
    );

    return [
        'label' => '平台采集入库',
        'active' => $active,
        'mode' => $mode,
        'status_text' => $active ? '正在采集' : ($pendingReadme > 0 ? '队列待处理' : '已完成'),
        'percent' => $percent,

        'progress' => [
            'total' => $total,
            'with_readme' => $withReadme,
            'pending_readme' => $pendingReadme,
            'with_zh' => $withZh,
            'pending_zh' => $pendingZh,
            'percent' => $percent,
        ],

        'estimate' => [
            'rate_per_hour' => round($recentRate, 1),
            'rate_text' => $recentRate > 0 ? (number_format($recentRate, 0) . ' 条/小时') : '计算中',
            'eta_seconds' => $etaSeconds,
            'eta_text' => progress_duration_text($etaSeconds),
            'estimated_finish_at' => $etaSeconds !== null ? date('Y-m-d H:i:s', time() + $etaSeconds) : '',
        ],

        'current_run' => [
            'status' => $mode,
            'started_at' => (string) ($latestRunRow['started_at'] ?? ''),
            'latest_at' => $latestAt,
            'elapsed_text' => $latestRunRow ? progress_duration_text(max(0, time() - (int) strtotime((string) $latestRunRow['started_at']))) : '-',
        ],

        'history' => [
            'label' => '历史累计统计',
            'stats' => [
                ['label' => '累计候选记录', 'value' => number_format((int) ($rawReportStats['raw_reports'] ?? 0))],
                ['label' => '去重项目数', 'value' => number_format((int) ($rawReportStats['projects'] ?? 0))],
                ['label' => '采集批次数', 'value' => number_format((int) ($runStats['total_runs'] ?? 0))],
                ['label' => '自动周期批次', 'value' => number_format((int) ($runStats['auto_runs'] ?? 0))],
                ['label' => '手动/搜索批次', 'value' => number_format((int) ($runStats['manual_runs'] ?? 0))],
                ['label' => '采集时间跨度', 'value' => progress_span_text($rawReportStats['first_at'] ?? null, $rawReportStats['latest_at'] ?? null)],
                ['label' => '最近入库', 'value' => (string) (($rawReportStats['latest_at'] ?? '') ?: '-'), 'wide' => true],
            ],
        ],

        'next_schedule' => progress_next_schedule('collection'),
    ];
}

function collection_completion_stats(): array
{
    $total = (int) (db_one("SELECT COUNT(*) AS n FROM projects WHERE is_hidden = 0")['n'] ?? 0);
    $withReadmeRaw = (int) (db_one(
        "SELECT COUNT(DISTINCT r.project_id) AS n
         FROM project_readmes r INNER JOIN projects p ON p.id = r.project_id
         WHERE p.is_hidden = 0 AND r.is_translated = 0"
    )['n'] ?? 0);
    $withReadmeZh = (int) (db_one(
        "SELECT COUNT(DISTINCT r.project_id) AS n
         FROM project_readmes r INNER JOIN projects p ON p.id = r.project_id
         WHERE p.is_hidden = 0 AND r.language_code LIKE 'zh%'"
    )['n'] ?? 0);
    $withGir = (int) (db_one(
        "SELECT COUNT(DISTINCT r.project_id) AS n
         FROM project_reports r INNER JOIN projects p ON p.id = r.project_id
         WHERE p.is_hidden = 0 AND r.raw_rank_only = 0 AND r.one_sentence <> ''"
    )['n'] ?? 0);
    $fully = (int) (db_one(
        "SELECT COUNT(*) AS n FROM projects p
         WHERE p.is_hidden = 0
           AND EXISTS (SELECT 1 FROM project_readmes r WHERE r.project_id = p.id AND r.is_translated = 0)
           AND EXISTS (SELECT 1 FROM project_readmes r WHERE r.project_id = p.id AND r.language_code LIKE 'zh%')
           AND EXISTS (SELECT 1 FROM project_reports r WHERE r.project_id = p.id AND r.raw_rank_only = 0 AND r.one_sentence <> '')"
    )['n'] ?? 0);

    return [
        'total' => $total,
        'with_readme_raw' => $withReadmeRaw,
        'with_readme_zh' => $withReadmeZh,
        'with_gir' => $withGir,
        'fully_ingested' => $fully,
        'pending_readme' => max(0, $total - $withReadmeRaw),
        'pending_translation' => max(0, $withReadmeRaw - $withReadmeZh),
        'pending_gir' => max(0, $total - $withGir),
    ];
}

function public_gir_progress_summary(?array $latestRunRow, ?array $activeWorkflowRun = null): array
{
    $total = (int) (db_one("SELECT COUNT(*) AS total FROM projects WHERE is_hidden = 0")['total'] ?? 0);
    $analysisTotals = db_one(
        'SELECT COUNT(DISTINCT p.id) AS analyzed,
                MIN(r.created_at) AS first_analysis_at,
                MAX(r.created_at) AS latest_analysis_at
         FROM projects p
         INNER JOIN project_reports r ON r.project_id = p.id
         WHERE p.is_hidden = 0 AND r.raw_rank_only = 0 AND r.one_sentence <> ""'
    );
    $analyzed = (int) ($analysisTotals['analyzed'] ?? 0);
    $pendingNew = max(0, $total - $analyzed);
    $firstAnalysisAt = (string) ($analysisTotals['first_analysis_at'] ?? '');
    $latestAnalysisAt = (string) ($analysisTotals['latest_analysis_at'] ?? '');

    // "Refreshed" = projects that have been re-analyzed with the current schema
    // (indicated by last_full_refresh_at being set).
    $refreshed = (int) (db_one(
        "SELECT COUNT(*) AS n FROM projects WHERE is_hidden = 0 AND last_full_refresh_at IS NOT NULL"
    )['n'] ?? 0);
    $pendingRefresh = max(0, $total - $refreshed);

    // Percent: if there are un-refreshed projects, show refresh progress.
    // Otherwise show first-analysis coverage.
    $showRefreshProgress = $pendingRefresh > 0 || $refreshed > 0;
    if ($showRefreshProgress && $refreshed < $total) {
        $percent = $total > 0 ? round(min(100, ($refreshed / $total) * 100), 1) : 0;
    } else {
        $percent = $total > 0 ? round(min(100, ($analyzed / $total) * 100), 1) : 0;
    }

    // Today's activity.
    $todayCount = (int) (db_one(
        "SELECT COUNT(*) AS n FROM project_reports
         WHERE raw_rank_only = 0 AND one_sentence <> '' AND created_at >= CURDATE()"
    )['n'] ?? 0);

    // Pending strong-signal count (cached, expensive query).
    $pendingSignal = 0;
    $signalCachePath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'gir_pending_signal.json';
    $signalCacheTtl = 120;
    if (is_file($signalCachePath)) {
        $raw = @file_get_contents($signalCachePath);
        $cached = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($cached) && (int) ($cached['t'] ?? 0) + $signalCacheTtl >= time()) {
            $pendingSignal = (int) ($cached['n'] ?? 0);
        }
    }

    $active = ($latestRunRow && (string) ($latestRunRow['status'] ?? '') === 'started')
        || ($latestAnalysisAt !== '' && time() - (int) strtotime($latestAnalysisAt) <= 20 * 60);
    $pending = null;
    if (!$active && ($pendingNew > 0 || $pendingRefresh > 0) && $activeWorkflowRun) {
        $workflowStartedAt = (string) (($activeWorkflowRun['started_at'] ?? '') ?: ($activeWorkflowRun['created_at'] ?? ''));
        $workflowTs = $workflowStartedAt !== '' ? (int) strtotime($workflowStartedAt) : 0;
        $latestKnownTs = $latestAnalysisAt !== '' ? (int) strtotime($latestAnalysisAt) : 0;
        if ($workflowTs > 0 && $workflowTs > $latestKnownTs) {
            $pending = [
                'label' => 'GitHub 工作流已启动',
                'summary' => '等待首批结果回写',
                'started_at' => $workflowStartedAt,
            ];
        }
    }
    $mode = $active ? 'running' : ($pending ? 'pending' : 'idle');

    // Rate.
    $recentRate = 0.0;
    if ($firstAnalysisAt !== '' && $latestAnalysisAt !== '' && $analyzed > 1) {
        $span = max(1, (int) strtotime($latestAnalysisAt) - (int) strtotime($firstAnalysisAt));
        $recentRate = $analyzed / ($span / 3600.0);
    }
    $totalPending = $pendingNew + $pendingRefresh;
    $etaSeconds = ($recentRate > 0 && $totalPending > 0) ? (int) ceil($totalPending / ($recentRate / 3600)) : null;

    // Recent analyses (last 5).
    $recentList = db_all(
        "SELECT r.project_id, p.full_name, r.created_at,
                SUBSTRING(r.change_observation, 1, 200) AS obs_preview
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.raw_rank_only = 0 AND r.one_sentence <> ''
         ORDER BY r.created_at DESC, r.id DESC
         LIMIT 5"
    );

    // History.
    $runStats = db_one(
        'SELECT COUNT(*) AS total_runs,
                SUM(CASE WHEN run_type IN ("daily", "weekly", "backlog") THEN 1 ELSE 0 END) AS auto_runs,
                SUM(CASE WHEN run_type = "manual" THEN 1 ELSE 0 END) AS manual_runs,
                SUM(total_analyzed) AS total_analyzed
         FROM runs
         WHERE total_analyzed > 0'
    );
    $reportStats = db_one(
        'SELECT COUNT(*) AS total_reports,
                COUNT(DISTINCT project_id) AS projects,
                MIN(created_at) AS first_at,
                MAX(created_at) AS latest_at
         FROM project_reports
         WHERE raw_rank_only = 0 AND one_sentence <> ""'
    );

    return [
        'label' => 'GIR 解读',
        'active' => $active,
        'mode' => $mode,
        'status_text' => $active ? '正在解读' : ($pending ? '等待首批回写' : ($totalPending > 0 ? '队列待处理' : '空闲')),
        'percent' => $percent,
        'pending' => $pending,

        'progress' => [
            'total' => $total,
            'analyzed' => $analyzed,
            'refreshed' => $refreshed,
            'pending_new' => $pendingNew,
            'pending_refresh' => $pendingRefresh,
            'pending_signal' => $pendingSignal,
            'today_count' => $todayCount,
            'percent' => $percent,
        ],

        'estimate' => [
            'rate_per_hour' => round($recentRate, 1),
            'rate_text' => $recentRate > 0 ? (number_format($recentRate, 0) . ' 条/小时') : '计算中',
            'eta_seconds' => $etaSeconds,
            'eta_text' => progress_duration_text($etaSeconds),
            'estimated_finish_at' => $etaSeconds !== null ? date('Y-m-d H:i:s', time() + $etaSeconds) : '',
        ],

        'current_run' => [
            'status' => $mode,
            'started_at' => (string) ($latestRunRow['started_at'] ?? ''),
            'latest_at' => $latestAnalysisAt,
            'elapsed_text' => $active && $latestRunRow ? progress_duration_text(max(0, time() - (int) strtotime((string) $latestRunRow['started_at']))) : '-',
            'today_count' => $todayCount,
            'recent' => array_map(static function (array $row): array {
                return [
                    'full_name' => (string) $row['full_name'],
                    'created_at' => (string) $row['created_at'],
                ];
            }, $recentList),
        ],

        'history' => [
            'label' => '历史累计统计',
            'stats' => [
                ['label' => '累计解读次数', 'value' => number_format((int) ($reportStats['total_reports'] ?? 0))],
                ['label' => '已解读项目', 'value' => number_format((int) ($reportStats['projects'] ?? 0))],
                ['label' => '已刷新项目', 'value' => number_format($refreshed)],
                ['label' => '解读批次数', 'value' => number_format((int) ($runStats['total_runs'] ?? 0))],
                ['label' => '自动周期批次', 'value' => number_format((int) ($runStats['auto_runs'] ?? 0))],
                ['label' => '手动/搜索批次', 'value' => number_format((int) ($runStats['manual_runs'] ?? 0))],
                ['label' => '解读时间跨度', 'value' => progress_span_text($reportStats['first_at'] ?? null, $reportStats['latest_at'] ?? null)],
                ['label' => '最近解读', 'value' => (string) (($reportStats['latest_at'] ?? '') ?: '-'), 'wide' => true],
            ],
        ],

        'next_schedule' => progress_next_schedule('gir', $totalPending > 0),
    ];
}

function public_progress_summary(): array
{
    $cachePath = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'gir_progress_summary.json';
    $cacheTtl = 8;
    if (is_file($cachePath)) {
        $raw = @file_get_contents($cachePath);
        $cached = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($cached) && (int) ($cached['cached_at'] ?? 0) + $cacheTtl >= time() && is_array($cached['data'] ?? null)) {
            return $cached['data'];
        }
    }
    $data = public_progress_summary_uncached();
    @file_put_contents($cachePath, json_encode([
        'cached_at' => time(),
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE));
    return $data;
}

function public_progress_summary_uncached(): array
{
    $latestCollectionRunRow = db_one(
        'SELECT * FROM runs
         WHERE total_found > 0 AND total_analyzed = 0
         ORDER BY started_at DESC, id DESC
         LIMIT 1'
    );
    $latestGirRunRow = db_one(
        'SELECT * FROM runs
         WHERE total_analyzed > 0
         ORDER BY started_at DESC, id DESC
         LIMIT 1'
    );
    $activeWorkflowRun = github_discover_active_run();
    $collection = public_collection_progress_summary($latestCollectionRunRow);
    $gir = public_gir_progress_summary($latestGirRunRow, $activeWorkflowRun);
    $active = !empty($collection['active']) || !empty($gir['active']) || (string) ($gir['mode'] ?? '') === 'pending';

    return [
        'generated_at' => date('Y-m-d H:i:s'),
        'report_date' => (string) ($collection['report_date'] ?? ''),
        'active' => $active,
        'focus' => $gir,
        'platforms' => $gir['platforms'],
        'collection' => $collection,
        'gir' => $gir,
        'latest_run' => latest_run_summary($latestGirRunRow ?: $latestCollectionRunRow),
    ];
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
                r.useful_score, r.maturity_score, r.php_fit_score, r.difficulty, r.recommendation, r.summary_zh
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

function backfill_project_count(bool $pendingOnly = false): int
{
    $sql = 'SELECT COUNT(*) AS total
            FROM projects p
            WHERE p.is_hidden = 0';
    if ($pendingOnly) {
        $sql .= " AND NOT EXISTS (
            SELECT 1
            FROM project_reports r
            WHERE r.project_id = p.id
              AND r.raw_rank_only = 0
              AND r.one_sentence <> ''
        )";
    }
    $row = db_one($sql);
    return (int) ($row['total'] ?? 0);
}

function backfill_projects(int $offset, int $limit, bool $pendingOnly = false): array
{
    $offset = max(0, $offset);
    $limit = max(1, min(200, $limit));
    $sql = 'SELECT p.*
            FROM projects p
            WHERE p.is_hidden = 0';
    if ($pendingOnly) {
        $sql .= " AND NOT EXISTS (
            SELECT 1
            FROM project_reports r
            WHERE r.project_id = p.id
              AND r.raw_rank_only = 0
              AND r.one_sentence <> ''
        )";
    }
    $sql .= '
         ORDER BY p.stars DESC, p.forks DESC, p.id ASC
         LIMIT ' . $offset . ', ' . $limit;
    $rows = db_all($sql);

    $projects = [];
    foreach ($rows as $index => $row) {
        $topics = array_values(array_filter(array_map('trim', explode(',', (string) ($row['topics'] ?? '')))));
        $projects[] = [
            'id' => (int) ($row['github_id'] ?? 0),
            'github_id' => (int) ($row['github_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'full_name' => (string) ($row['full_name'] ?? ''),
            'html_url' => (string) ($row['html_url'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'stargazers_count' => (int) ($row['stars'] ?? 0),
            'forks_count' => (int) ($row['forks'] ?? 0),
            'language' => (string) ($row['language'] ?? ''),
            'topics' => $topics,
            'pushed_at' => (string) ($row['pushed_at'] ?? ''),
            'source_platform' => 'backfill',
            'source_tag' => 'all_projects',
            'source_rank' => $offset + $index + 1,
            'source_score' => (int) ($row['stars'] ?? 0) + (int) ($row['forks'] ?? 0) * 2,
        ];
    }

    return $projects;
}

function reset_project_analyses(): array
{
    db_exec('DELETE FROM project_reports WHERE raw_rank_only = 0');
    $deletedAnalyses = db()->affected_rows;
    db_exec("DELETE FROM project_reports WHERE raw_rank_only = 1 AND source_platform = 'backfill'");
    $deletedBackfillRaw = db()->affected_rows;

    return [
        'deleted_analyses' => max(0, (int) $deletedAnalyses),
        'deleted_backfill_raw' => max(0, (int) $deletedBackfillRaw),
    ];
}

function all_app_settings(): array
{
    return db_all('SELECT * FROM app_settings ORDER BY setting_key ASC');
}

function update_app_setting(string $key, string $value): bool
{
    return db_exec(
        'INSERT INTO app_settings (setting_key, setting_value, description, updated_at)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
        [truncate_text($key, 64), truncate_text($value, 20000), '', date('Y-m-d H:i:s')]
    );
}

function default_deepseek_system_prompt(): string
{
    return implode("\n", [
        '你是一个帮助站长发现 GitHub 新项目的技术分析员。',
        '你的目标是把项目讲成人能快速理解的中文：它做什么、为什么值得关注、适合谁、怎么用、有什么可借鉴点。',
        '不要把本站运行环境或某个特定技术栈当作通用评价标准；除非输入明确要求，否则不要把部署条件作为主要结论。',
        '你必须用中文输出严格 JSON，不要 Markdown，不要解释。',
        '评分为 1 到 10 的整数。',
        'play_score 衡量项目是否有趣、是否值得点开体验、是否能带来灵感。',
        'useful_score 衡量项目是否解决真实问题、是否有明确使用价值。',
        'maturity_score 衡量项目成熟度，综合 Stars、Forks、最近更新、文档完整度和社区活跃度。',
        'difficulty 衡量理解、部署、改造或复刻成本，只能输出 低、中、高。',
    ]);
}

function default_deepseek_task_prompt(): string
{
    return '为这次榜单命中生成一条新的中文解说。即使历史里已经分析过同一个项目，也不要复用旧文案；请结合最近几次解说，判断这次是否有新功能、热度变化、定位变化或值得重新关注的原因。表达要说人话，避免空泛夸奖，重点说明：项目一句话用途、解决的真实问题、为什么上榜或变热、适合谁用、上手方式或可借鉴点、主要风险。不要默认围绕部署条件评价项目，也不要因为项目依赖较多或运行门槛较高就直接给出“暂不关注”；只有当部署门槛会明显影响目标用户采用时，才在风险里简短说明。';
}

function discover_setting_definitions(): array
{
    return [
        'discover_daily_enabled' => ['label' => '启用日报自动采集', 'type' => 'checkbox', 'default' => '1', 'description' => '关闭后 daily 定时任务会跳过。'],
        'discover_weekly_enabled' => ['label' => '启用周榜自动采集', 'type' => 'checkbox', 'default' => '1', 'description' => '关闭后 weekly 定时任务会跳过。'],
        'discover_backlog_enabled' => ['label' => '启用积压项目自动补跑', 'type' => 'checkbox', 'default' => '1', 'description' => '每 30 分钟检查一次未解读项目，直到 backlog 清空。'],
        'discover_backlog_batch_size' => ['label' => '每轮 backlog 解读数量', 'type' => 'number', 'default' => '40', 'description' => '每次自动补跑最多处理多少个未解读项目。'],
        'discover_analyze_all' => ['label' => 'GIR 解读全部候选', 'type' => 'checkbox', 'default' => '1', 'description' => '开启后本轮抓到的候选都会进入 GIR 解读。'],
        'discover_max_projects' => ['label' => 'GIR 解读上限', 'type' => 'number', 'default' => '3', 'description' => '仅在关闭“解读全部候选”时生效。'],
        'discover_per_page' => ['label' => '每个平台/分类候选数量', 'type' => 'number', 'default' => '20', 'description' => '每个固定来源、分类或搜索语句最多抓多少候选。'],
        'discover_recent_days_daily' => ['label' => '日报 GitHub 搜索窗口', 'type' => 'number', 'default' => '3', 'description' => 'GitHub Search 日榜向前搜索多少天。'],
        'discover_recent_days_weekly' => ['label' => '周榜 GitHub 搜索窗口', 'type' => 'number', 'default' => '14', 'description' => 'GitHub Search 周榜向前搜索多少天。'],
        'discover_min_stars_general' => ['label' => '通用最低 Stars', 'type' => 'number', 'default' => '100', 'description' => '综合搜索的最低 Stars。'],
        'discover_min_stars_created' => ['label' => '新项目最低 Stars', 'type' => 'number', 'default' => '20', 'description' => '新创建项目搜索的最低 Stars。'],
        'discover_min_stars_topic' => ['label' => 'Topic 最低 Stars', 'type' => 'number', 'default' => '50', 'description' => '普通 topic 搜索的最低 Stars。'],
        'discover_min_stars_agent' => ['label' => 'Agent 最低 Stars', 'type' => 'number', 'default' => '30', 'description' => 'agent topic 搜索的最低 Stars。'],
        'discover_extra_queries' => ['label' => '额外搜索语句', 'type' => 'textarea', 'default' => '', 'description' => '每行一条 GitHub Search 查询，可使用 {since}。'],
        'readme_fetch_enabled' => ['label' => '启用 README 抓取', 'type' => 'checkbox', 'default' => '1', 'description' => '每轮 backlog 会持续抓取，直到队列清空或达到时间预算。'],
        'readme_per_run' => ['label' => 'README 抓取单批大小', 'type' => 'number', 'default' => '20', 'description' => '每个 fetch 批次抓多少个项目；抓完继续下一批，直到队列清空。'],
        'analyze_concurrency' => ['label' => 'DeepSeek 解读并发数', 'type' => 'number', 'default' => '10', 'description' => '同时并行调用 DeepSeek 的线程数。根据 API 渠道限制设置。'],
        'deepseek_system_prompt' => ['label' => 'GIR 解读系统提示词', 'type' => 'textarea', 'default' => default_deepseek_system_prompt(), 'description' => '控制 GIR 解读的角色、边界和评分标准。'],
        'deepseek_task_prompt' => ['label' => 'GIR 解读任务提示词', 'type' => 'textarea', 'default' => default_deepseek_task_prompt(), 'description' => '控制每个项目解读的口吻、重点和判断方式；输出 JSON 字段结构由代码固定。'],
        'deepseek_api_key' => ['label' => 'DeepSeek API Key', 'type' => 'textarea', 'default' => '', 'description' => '填写后优先使用此 Key（覆盖 GitHub Secrets）。留空则使用 Secrets 里的值。'],
        'deepseek_base_url' => ['label' => 'DeepSeek API 地址', 'type' => 'textarea', 'default' => '', 'description' => '例如 https://api.deepseek.com 或第三方兼容地址。留空则使用 Secrets 里的值。'],
        'deepseek_model' => ['label' => 'DeepSeek 模型名', 'type' => 'textarea', 'default' => '', 'description' => '例如 deepseek-chat。留空则使用 Secrets 里的值。'],
    ];
}

function discover_platform_catalog(): array
{
    return [
        [
            'key' => 'github_trending',
            'label' => 'GitHub 官方趋势',
            'period' => 'daily / weekly',
            'limit' => '每周期最多 discover_per_page 个候选',
            'categories' => ['daily', 'weekly'],
            'source' => 'https://github.com/trending',
        ],
        [
            'key' => 'ossinsight',
            'label' => 'OSSInsight 趋势',
            'period' => 'past_24_hours / past_7_days',
            'limit' => '每周期最多 discover_per_page 个候选',
            'categories' => ['daily', 'weekly'],
            'source' => 'https://api.ossinsight.io/v1/trends/repos/',
        ],
        [
            'key' => 'trendshift',
            'label' => 'Trendshift 趋势',
            'period' => 'daily / weekly / topic',
            'limit' => '主榜、GitHub Trending、Repository engagements、每个 topic 最多 discover_per_page 个候选',
            'categories' => ['daily', 'weekly', 'github-trending', 'repository-engagements', 'topics/*'],
            'source' => 'https://trendshift.io/ + /topics/*',
        ],
        [
            'key' => 'reporank',
            'label' => 'RepoRank 排行',
            'period' => 'momentum',
            'limit' => '最多 discover_per_page 个候选',
            'categories' => ['momentum'],
            'source' => 'https://reporank.co/',
        ],
        [
            'key' => 'gitrepotrend',
            'label' => 'GitRepoTrend 热度',
            'period' => 'attention',
            'limit' => '每分类最多 discover_per_page 个候选',
            'categories' => ['ai-ml', 'llm', 'webdev', 'devtools', 'devops', 'finance', 'crypto', 'security', 'datascience', 'mobile', 'rust', 'python', 'go'],
            'source' => 'https://gitrepotrend.com/api/init',
        ],
        [
            'key' => 'github_search',
            'label' => 'GitHub 搜索',
            'period' => '手动 / 动态搜索',
            'limit' => '每条搜索最多 discover_per_page 个候选',
            'categories' => ['综合', '新项目', 'ai', 'llm', 'agent', 'php', '额外搜索语句'],
            'source' => 'https://api.github.com/search/repositories',
            'default_enabled' => false,
        ],
    ];
}

function discover_fixed_platforms(): array
{
    $platforms = array_filter(discover_platform_catalog(), static function (array $platform): bool {
        return !isset($platform['default_enabled']) || $platform['default_enabled'] !== false;
    });
    return array_map(static function (array $platform): string {
        return (string) $platform['key'];
    }, $platforms);
}

function discover_fixed_topics(): array
{
    return ['ai', 'llm', 'agent', 'php'];
}

function discover_settings(): array
{
    $definitions = discover_setting_definitions();
    $keys = array_keys($definitions);
    $quoted = implode(',', array_fill(0, count($keys), '?'));
    $rows = db_all('SELECT setting_key, setting_value, description FROM app_settings WHERE setting_key IN (' . $quoted . ') ORDER BY setting_key ASC', $keys);
    $settings = [];
    foreach ($definitions as $key => $definition) {
        $settings[$key] = [
            'key' => $key,
            'label' => $definition['label'],
            'type' => $definition['type'],
            'value' => $definition['default'],
            'description' => isset($definition['description']) ? (string) $definition['description'] : '',
        ];
    }
    foreach ($rows as $row) {
        $key = (string) $row['setting_key'];
        if (isset($settings[$key])) {
            $settings[$key]['value'] = (string) $row['setting_value'];
            if ((string) $row['description'] !== '') {
                $settings[$key]['description'] = (string) $row['description'];
            }
        }
    }
    return $settings;
}

function discover_int_setting(string $key, int $default, int $min, int $max): int
{
    $value = (int) app_setting($key, (string) $default);
    return max($min, min($max, $value));
}

function discover_bool_setting(string $key, bool $default): bool
{
    $value = app_setting($key, $default ? '1' : '0');
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function discover_list_setting(string $key): array
{
    $raw = app_setting($key, '');
    $items = preg_split('/[\r\n,]+/', $raw);
    $clean = [];
    foreach ($items ?: [] as $item) {
        $item = trim((string) $item);
        if ($item !== '') {
            $clean[] = $item;
        }
    }
    return array_values(array_unique($clean));
}

function discover_public_config(): array
{
    return [
        'daily_enabled' => discover_bool_setting('discover_daily_enabled', true),
        'weekly_enabled' => discover_bool_setting('discover_weekly_enabled', true),
        'backlog_enabled' => discover_bool_setting('discover_backlog_enabled', true),
        'backlog_batch_size' => discover_int_setting('discover_backlog_batch_size', 40, 1, 200),
        'analyze_all' => discover_bool_setting('discover_analyze_all', true),
        'max_projects' => discover_int_setting('discover_max_projects', 3, 1, 50),
        'per_page' => discover_int_setting('discover_per_page', 20, 1, 100),
        'recent_days_daily' => discover_int_setting('discover_recent_days_daily', 3, 1, 90),
        'recent_days_weekly' => discover_int_setting('discover_recent_days_weekly', 14, 1, 180),
        'min_stars_general' => discover_int_setting('discover_min_stars_general', 100, 0, 1000000),
        'min_stars_created' => discover_int_setting('discover_min_stars_created', 20, 0, 1000000),
        'min_stars_topic' => discover_int_setting('discover_min_stars_topic', 50, 0, 1000000),
        'min_stars_agent' => discover_int_setting('discover_min_stars_agent', 30, 0, 1000000),
        'topics' => discover_fixed_topics(),
        'extra_queries' => discover_list_setting('discover_extra_queries'),
        'platforms' => discover_fixed_platforms(),
        'deepseek_system_prompt' => app_setting('deepseek_system_prompt', default_deepseek_system_prompt()),
        'deepseek_task_prompt' => app_setting('deepseek_task_prompt', default_deepseek_task_prompt()),
        'deepseek_api_key' => app_setting('deepseek_api_key', ''),
        'deepseek_base_url' => app_setting('deepseek_base_url', ''),
        'deepseek_model' => app_setting('deepseek_model', ''),
        'readme_fetch_enabled' => discover_bool_setting('readme_fetch_enabled', true),
        'readme_per_run' => discover_int_setting('readme_per_run', 10, 0, 200),
        'analyze_concurrency' => discover_int_setting('analyze_concurrency', 10, 1, 200),
    ];
}

function github_trigger_configured(): bool
{
    global $config;
    return $config['github']['owner'] !== ''
        && $config['github']['repo'] !== ''
        && $config['github']['token'] !== ''
        && $config['github']['workflow'] !== '';
}

function trigger_github_workflow(array $inputs): array
{
    global $config;

    if (!github_trigger_configured()) {
        return ['ok' => false, 'error' => 'github_dispatch_not_configured'];
    }

    $cleanInputs = [];
    foreach ($inputs as $key => $value) {
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $key);
        if ($key === '') {
            continue;
        }
        $cleanInputs[$key] = truncate_text((string) $value, 500);
    }
    if (!isset($cleanInputs['run_type']) || !in_array($cleanInputs['run_type'], ['daily', 'weekly', 'manual', 'backlog'], true)) {
        $cleanInputs['run_type'] = 'daily';
    }

    $body = json_encode([
        'ref' => 'main',
        'inputs' => $cleanInputs,
    ], JSON_UNESCAPED_UNICODE);

    $url = 'https://api.github.com/repos/' . rawurlencode($config['github']['owner']) . '/' . rawurlencode($config['github']['repo'])
        . '/actions/workflows/' . rawurlencode($config['github']['workflow']) . '/dispatches';

    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace([
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
    ], http_ssl_options()));

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

function trigger_github_discover(string $runType): array
{
    $inputs = ['run_type' => $runType];
    if ($runType === 'backlog') {
        $inputs['backfill_existing'] = 'true';
        $inputs['backlog_pending_only'] = 'true';
    }
    return trigger_github_workflow($inputs);
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

function upsert_report(int $projectId, ?int $runId, string $periodType, string $reportDate, array $analysis, array $source = []): void
{
    $now = date('Y-m-d H:i:s');
    $raw = json_encode($analysis, JSON_UNESCAPED_UNICODE);
    $sourcePlatform = truncate_text($source['platform'] ?? 'github', 64);
    $sourceTag = truncate_text($source['tag'] ?? '综合', 64);
    $sourceRank = max(0, (int) ($source['rank'] ?? 0));
    $sourceScore = (float) ($source['score'] ?? 0);
    $rawRankOnly = !empty($analysis['raw_rank_only']);
    $existing = $rawRankOnly ? db_one(
        'SELECT id FROM project_reports WHERE project_id = ? AND period_type = ? AND report_date = ? AND source_platform = ? AND source_tag = ? AND raw_rank_only = 1',
        [$projectId, $periodType, $reportDate, $sourcePlatform, $sourceTag]
    ) : null;

    $previousReportId = null;
    $starGrowth = null;
    $forkGrowth = null;
    $spanDays = null;
    if (!$rawRankOnly) {
        $prev = db_one(
            "SELECT id, created_at FROM project_reports
             WHERE project_id = ? AND raw_rank_only = 0 AND one_sentence <> ''
             ORDER BY report_date DESC, id DESC LIMIT 1",
            [$projectId]
        );
        if ($prev) {
            $previousReportId = (int) $prev['id'];
            // stars/forks growth需要基线，这里用 projects 当前值 - 上一版 snapshot。
            // Snapshot 以 raw_ai_json 里 repo 数据为准，兜底为 projects.stars。
            $prevRow = db_one("SELECT raw_ai_json FROM project_reports WHERE id = ?", [$previousReportId]);
            $prevStars = null;
            $prevForks = null;
            if ($prevRow && !empty($prevRow['raw_ai_json'])) {
                $prevPayload = json_decode((string) $prevRow['raw_ai_json'], true);
                if (is_array($prevPayload)) {
                    if (isset($prevPayload['repo_snapshot']['stars'])) {
                        $prevStars = (int) $prevPayload['repo_snapshot']['stars'];
                    }
                    if (isset($prevPayload['repo_snapshot']['forks'])) {
                        $prevForks = (int) $prevPayload['repo_snapshot']['forks'];
                    }
                }
            }
            $currentRow = db_one("SELECT stars, forks FROM projects WHERE id = ?", [$projectId]);
            if ($currentRow) {
                if ($prevStars !== null) {
                    $starGrowth = (int) $currentRow['stars'] - $prevStars;
                }
                if ($prevForks !== null) {
                    $forkGrowth = (int) $currentRow['forks'] - $prevForks;
                }
            }
            $prevTime = strtotime((string) $prev['created_at']);
            if ($prevTime) {
                $spanDays = max(0, (int) floor((time() - $prevTime) / 86400));
            }
        }
    }

    $changeObservation = isset($analysis['change_observation']) && is_array($analysis['change_observation'])
        ? json_encode($analysis['change_observation'], JSON_UNESCAPED_UNICODE)
        : '';
    $analysisDetail = isset($analysis['project_profile']) && is_array($analysis['project_profile'])
        ? json_encode($analysis['project_profile'], JSON_UNESCAPED_UNICODE)
        : '';

    $params = [
        $runId ?: null,
        $sourceRank,
        $sourceScore,
        truncate_text($analysis['one_sentence'] ?? '', 255),
        truncate_text($analysis['project_type'] ?? '', 64),
        truncate_text($analysis['problem'] ?? $analysis['problem_text'] ?? '', 5000),
        list_to_text($analysis['tech_stack'] ?? ''),
        list_to_text($analysis['target_users'] ?? ''),
        clamp_score($analysis['play_score'] ?? 0),
        clamp_score($analysis['useful_score'] ?? 0),
        clamp_score($analysis['maturity_score'] ?? 0),
        clamp_score($analysis['php_fit_score'] ?? 0),
        normalize_difficulty($analysis['difficulty'] ?? ''),
        !empty($analysis['is_suitable_for_this_host']) ? 1 : 0,
        list_to_text($analysis['ideas_to_reuse'] ?? ''),
        list_to_text($analysis['risks'] ?? ''),
        truncate_text($analysis['change_note'] ?? '', 5000),
        $changeObservation,
        $analysisDetail,
        $previousReportId,
        $starGrowth,
        $forkGrowth,
        $spanDays,
        normalize_recommendation($analysis['recommendation'] ?? ''),
        truncate_text($analysis['summary_zh'] ?? '', 10000),
        $raw,
    ];

    if ($rawRankOnly && $existing) {
        $params[] = (int) $existing['id'];
        db_exec(
            'UPDATE project_reports
             SET run_id = ?, source_rank = ?, source_score = ?, one_sentence = ?, project_type = ?, problem_text = ?, tech_stack = ?, target_users = ?,
                 play_score = ?, useful_score = ?, maturity_score = ?, php_fit_score = ?, difficulty = ?, is_suitable_for_this_host = ?,
                 ideas_to_reuse = ?, risks = ?, change_note = ?, change_observation = ?, analysis_detail = ?,
                 previous_report_id = ?, star_growth = ?, fork_growth = ?, span_days = ?,
                 recommendation = ?, summary_zh = ?, raw_ai_json = ?, raw_rank_only = 1
             WHERE id = ?',
            $params
        );
        return;
    }

    array_unshift($params, $projectId, $periodType, $reportDate, $sourcePlatform, $sourceTag);
    $params[] = $now;
    $rawRankFlag = $rawRankOnly ? 1 : 0;
    array_splice($params, 8, 0, [$rawRankFlag]);
    db_exec(
        'INSERT INTO project_reports
         (project_id, period_type, report_date, source_platform, source_tag, run_id, source_rank, source_score,
          raw_rank_only, one_sentence, project_type, problem_text, tech_stack, target_users,
          play_score, useful_score, maturity_score, php_fit_score, difficulty, is_suitable_for_this_host, ideas_to_reuse, risks,
          change_note, change_observation, analysis_detail, previous_report_id, star_growth, fork_growth, span_days,
          recommendation, summary_zh, raw_ai_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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

function upsert_project_readme(int $projectId, array $row): int
{
    $readmePath = truncate_text($row['readme_path'] ?? '', 255);
    $languageCode = truncate_text($row['language_code'] ?? 'en', 32);
    $isTranslated = !empty($row['is_translated']) ? 1 : 0;
    $sourceLanguageCode = truncate_text($row['source_language_code'] ?? '', 32);
    $sourceContentMd5 = truncate_text($row['source_content_md5'] ?? '', 32);
    $sourceUrl = truncate_text($row['source_url'] ?? '', 500);
    $content = (string) ($row['content_md'] ?? '');
    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > 200000) {
        $content = mb_substr($content, 0, 200000, 'UTF-8');
    } elseif (strlen($content) > 400000) {
        $content = substr($content, 0, 400000);
    }
    $content = strip_mysql_utf8_unsupported($content);
    $contentMd5 = $content !== '' ? md5($content) : '';
    $fetchedAt = normalize_datetime($row['fetched_at'] ?? null) ?: date('Y-m-d H:i:s');
    $now = date('Y-m-d H:i:s');

    if ($content !== '') {
        readme_cache_write($projectId, $languageCode, (bool) $isTranslated, $content);
        readme_cache_maybe_evict();
    }

    $existing = db_one(
        'SELECT id, content_md5 FROM project_readmes
         WHERE project_id = ? AND readme_path = ? AND language_code = ? AND is_translated = ?',
        [$projectId, $readmePath, $languageCode, $isTranslated]
    );

    $emptyContentForDb = '';

    if ($existing) {
        db_exec(
            'UPDATE project_readmes
             SET source_url = ?, source_language_code = ?, source_content_md5 = ?,
                 content_md = ?, content_md5 = ?, fetched_at = ?, updated_at = ?
             WHERE id = ?',
            [$sourceUrl, $sourceLanguageCode, $sourceContentMd5, $emptyContentForDb, $contentMd5, $fetchedAt, $now, (int) $existing['id']]
        );
        return (int) $existing['id'];
    }

    db_exec(
        'INSERT INTO project_readmes
         (project_id, readme_path, language_code, source_url, is_translated, source_language_code,
          source_content_md5, content_md, content_md5, fetched_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $projectId,
            $readmePath,
            $languageCode,
            $sourceUrl,
            $isTranslated,
            $sourceLanguageCode,
            $sourceContentMd5,
            $emptyContentForDb,
            $contentMd5,
            $fetchedAt,
            $now,
            $now,
        ]
    );
    return db_insert_id();
}

function list_project_readmes(int $projectId): array
{
    return db_all(
        "SELECT id, project_id, readme_path, language_code, is_translated, source_language_code,
                source_content_md5, source_url, content_md5, fetched_at, created_at, updated_at
         FROM project_readmes
         WHERE project_id = ?
         ORDER BY is_translated ASC,
                  CASE language_code WHEN 'zh-CN' THEN 0 WHEN 'zh' THEN 1 WHEN 'en' THEN 2 ELSE 9 END ASC,
                  id ASC",
        [$projectId]
    );
}

function classify_project_readmes(array $readmes): array
{
    $native_zh = null;
    $native_en = null;
    $machine_zh = null;
    $machine_zh_valid = false;
    foreach ($readmes as $row) {
        $isTranslated = (int) ($row['is_translated'] ?? 0) === 1;
        $lang = strtolower((string) ($row['language_code'] ?? ''));
        $isZh = $lang === 'zh' || $lang === 'zh-cn' || $lang === 'zh_cn' || $lang === 'zh-tw' || strpos($lang, 'zh') === 0;
        if ($isTranslated && $isZh && !$machine_zh) {
            $machine_zh = $row;
        } elseif (!$isTranslated && $isZh && !$native_zh) {
            $native_zh = $row;
        } elseif (!$isTranslated && !$isZh && !$native_en) {
            $native_en = $row;
        }
    }
    // Check if machine translation is still valid (source MD5 matches current English README MD5).
    if ($machine_zh && $native_en) {
        $sourceMd5 = (string) ($machine_zh['source_content_md5'] ?? '');
        $currentMd5 = (string) ($native_en['content_md5'] ?? '');
        $machine_zh_valid = ($sourceMd5 !== '' && $currentMd5 !== '' && $sourceMd5 === $currentMd5);
    } elseif ($machine_zh && !$native_en) {
        // No English README to compare against; treat translation as valid.
        $machine_zh_valid = true;
    }
    return [
        'native_zh' => $native_zh,
        'native_en' => $native_en,
        'machine_zh' => $machine_zh,
        'machine_zh_valid' => $machine_zh_valid,
    ];
}

function readme_ingest_payload(int $projectId, array $payload): int
{
    $rows = is_array($payload['readmes'] ?? null) ? $payload['readmes'] : [];
    $stored = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (upsert_project_readme($projectId, $row) > 0) {
            $stored++;
        }
    }
    return $stored;
}

function readme_pending_projects(int $limit, bool $translateOnly = false): array
{
    $limit = max(1, min(200, $limit));
    if ($translateOnly) {
        $sql = "SELECT p.id, p.full_name
                FROM projects p
                WHERE p.is_hidden = 0
                  AND EXISTS (
                      SELECT 1 FROM project_readmes rr
                      WHERE rr.project_id = p.id AND rr.is_translated = 0 AND rr.language_code = 'en'
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM project_readmes rr
                      WHERE rr.project_id = p.id AND rr.language_code LIKE 'zh%'
                  )
                ORDER BY p.stars DESC, p.id ASC
                LIMIT " . $limit;
    } else {
        $sql = 'SELECT p.id, p.full_name
                FROM projects p
                WHERE p.is_hidden = 0
                  AND NOT EXISTS (
                      SELECT 1 FROM project_readmes rr WHERE rr.project_id = p.id
                  )
                ORDER BY p.stars DESC, p.id ASC
                LIMIT ' . $limit;
    }
    return db_all($sql);
}


/**
 * Resolve the rendered markdown content for a README row. Tries the file
 * cache first; if missing or expired, fetches from raw.githubusercontent.com
 * (only for non-translated rows; translated rows must come from cache).
 *
 * Returns the markdown string. Persists newly fetched content into both
 * the file cache and (optionally) updates the DB metadata.
 */
function readme_resolve_content(array $project, array $row, string $view = ''): string
{
    $projectId = (int) ($row['project_id'] ?? $project['id'] ?? 0);
    $languageCode = (string) ($row['language_code'] ?? 'en');
    $isTranslated = !empty($row['is_translated']);
    $cached = readme_cache_read($projectId, $languageCode, $isTranslated);
    $ttl = readme_cache_ttl_seconds();
    $age = readme_cache_age_seconds($projectId, $languageCode, $isTranslated);
    $cacheFresh = $cached !== null && ($age === null || $age <= $ttl);

    if ($cacheFresh) {
        return $cached;
    }

    // Translated content only ever comes from on-demand translation; we
    // cannot rebuild it from raw.githubusercontent.com. Return whatever
    // (possibly stale) we have in cache; otherwise empty.
    if ($isTranslated) {
        return $cached !== null ? $cached : '';
    }

    // Fetch from raw.githubusercontent.com using the stored readme_path.
    $fullName = (string) ($project['full_name'] ?? '');
    $readmePath = (string) ($row['readme_path'] ?? '');
    if ($fullName === '' || $readmePath === '') {
        return $cached !== null ? $cached : '';
    }
    $fresh = readme_fetch_from_github($fullName, $readmePath);
    if ($fresh !== null && $fresh !== '') {
        readme_cache_write($projectId, $languageCode, false, $fresh);
        readme_cache_maybe_evict();
        // Update fetched_at + md5 in DB so future stats reflect the refresh.
        $md5 = md5($fresh);
        $now = date('Y-m-d H:i:s');
        db_exec(
            'UPDATE project_readmes SET content_md5 = ?, fetched_at = ?, updated_at = ? WHERE id = ?',
            [$md5, $now, $now, (int) ($row['id'] ?? 0)]
        );
        return $fresh;
    }
    return $cached !== null ? $cached : '';
}

function readme_fetch_from_github(string $fullName, string $readmePath): ?string
{
    if ($fullName === '' || $readmePath === '') return null;
    $url = 'https://raw.githubusercontent.com/' . $fullName . '/HEAD/' . str_replace(' ', '%20', $readmePath);
    $ch = curl_init($url);
    curl_setopt_array($ch, array_replace([
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT => 'GIR README Fetcher',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ], http_ssl_options()));
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno = curl_errno($ch);
    curl_close($ch);
    if ($errno || $http < 200 || $http >= 300 || !is_string($body)) {
        return null;
    }
    return $body;
}
