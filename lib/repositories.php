<?php
declare(strict_types=1);

function ranking_platform_labels(): array
{
    return [
        'github' => 'GitHub 搜索',
        'github_trending' => 'GitHub 官方趋势',
        'github_search' => 'GitHub 搜索榜',
        'ossinsight' => 'OSSInsight 趋势',
        'trendshift' => 'Trendshift 趋势',
        'reporank' => 'RepoRank 排行',
        'gitrepotrend' => 'GitRepoTrend 热度',
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
    ];
}

function ranking_tag_label(string $tag): string
{
    $labels = ranking_tag_labels();
    return $labels[$tag] ?? $tag;
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
        'yesterday' => '昨天',
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

function available_ranking_platforms(string $periodType, string $date = ''): array
{
    $dateFilter = report_date_filter_sql($periodType, $date);
    return db_all(
        'SELECT r.source_platform, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_platform
         ORDER BY total DESC, r.source_platform ASC',
        array_merge([$periodType], $dateFilter['params'])
    );
}

function available_ranking_platforms_by_range(string $periodType, array $dateRange): array
{
    $dateFilter = report_date_range_filter_sql($dateRange);
    return db_all(
        'SELECT r.source_platform, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_platform
         ORDER BY total DESC, r.source_platform ASC',
        array_merge([$periodType], $dateFilter['params'])
    );
}

function available_ranking_tags(string $periodType, string $platform, string $date = ''): array
{
    $dateFilter = report_date_filter_sql($periodType, $date);
    return db_all(
        'SELECT r.source_tag, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.source_platform = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_tag
         ORDER BY total DESC, r.source_tag ASC',
        array_merge([$periodType, $platform], $dateFilter['params'])
    );
}

function available_ranking_tags_by_range(string $periodType, string $platform, array $dateRange): array
{
    $dateFilter = report_date_range_filter_sql($dateRange);
    return db_all(
        'SELECT r.source_tag, COUNT(*) AS total
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
         WHERE r.period_type = ? AND r.source_platform = ? AND p.is_hidden = 0' . raw_rank_report_sql() . $dateFilter['sql'] . '
         GROUP BY r.source_tag
         ORDER BY total DESC, r.source_tag ASC',
        array_merge([$periodType, $platform], $dateFilter['params'])
    );
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
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
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
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
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
        'SELECT r.*, p.name, p.full_name, p.html_url, p.description, p.stars, p.forks, p.language, p.topics, p.pushed_at
         FROM project_reports r
         INNER JOIN projects p ON p.id = r.project_id
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
    $project = db_one('SELECT * FROM projects WHERE id = ?', [$id]);
    if (!$project) {
        return null;
    }
    $project['reports'] = db_all(
        "SELECT * FROM project_reports WHERE project_id = ? AND raw_rank_only = 0 AND one_sentence <> '' ORDER BY report_date DESC, id DESC",
        [$id]
    );
    return $project;
}

function recent_project_analyses(string $fullName, int $limit = 5): array
{
    return db_all(
        "SELECT r.report_date, r.source_platform, r.source_tag, r.one_sentence, r.summary_zh, r.change_note,
                r.recommendation, r.play_score, r.useful_score, r.maturity_score, r.difficulty, r.created_at
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
        '站长的部署环境是传统 PHP 7.2 + MySQL 5.1 虚拟主机，不能运行 Docker、Node/Python 常驻服务、本地模型或 WebSocket。',
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
    return '为这次榜单命中生成一条新的中文解说。即使历史里已经分析过同一个项目，也不要复用旧文案；请结合最近几次解说，判断这次是否有新功能、热度变化、定位变化或值得重新关注的原因。表达要说人话，避免空泛夸奖，重点说明这个项目解决什么问题、适合谁、是否值得收藏或研究。';
}

function discover_setting_definitions(): array
{
    return [
        'discover_daily_enabled' => ['label' => '启用日报自动采集', 'type' => 'checkbox', 'default' => '1', 'description' => '关闭后 daily 定时任务会跳过。'],
        'discover_weekly_enabled' => ['label' => '启用周榜自动采集', 'type' => 'checkbox', 'default' => '1', 'description' => '关闭后 weekly 定时任务会跳过。'],
        'discover_analyze_all' => ['label' => 'DeepSeek 处理全部候选', 'type' => 'checkbox', 'default' => '1', 'description' => '开启后本轮抓到的候选都会进入 DeepSeek 解读。'],
        'discover_max_projects' => ['label' => 'DeepSeek 分析上限', 'type' => 'number', 'default' => '3', 'description' => '仅在关闭“处理全部候选”时生效。'],
        'discover_per_page' => ['label' => '每个平台/分类候选数量', 'type' => 'number', 'default' => '20', 'description' => '每个固定来源、分类或搜索语句最多抓多少候选。'],
        'discover_recent_days_daily' => ['label' => '日报 GitHub 搜索窗口', 'type' => 'number', 'default' => '3', 'description' => 'GitHub Search 日榜向前搜索多少天。'],
        'discover_recent_days_weekly' => ['label' => '周榜 GitHub 搜索窗口', 'type' => 'number', 'default' => '14', 'description' => 'GitHub Search 周榜向前搜索多少天。'],
        'discover_min_stars_general' => ['label' => '通用最低 Stars', 'type' => 'number', 'default' => '100', 'description' => '综合搜索的最低 Stars。'],
        'discover_min_stars_created' => ['label' => '新项目最低 Stars', 'type' => 'number', 'default' => '20', 'description' => '新创建项目搜索的最低 Stars。'],
        'discover_min_stars_topic' => ['label' => 'Topic 最低 Stars', 'type' => 'number', 'default' => '50', 'description' => '普通 topic 搜索的最低 Stars。'],
        'discover_min_stars_agent' => ['label' => 'Agent 最低 Stars', 'type' => 'number', 'default' => '30', 'description' => 'agent topic 搜索的最低 Stars。'],
        'discover_extra_queries' => ['label' => '额外搜索语句', 'type' => 'textarea', 'default' => '', 'description' => '每行一条 GitHub Search 查询，可使用 {since}。'],
        'deepseek_system_prompt' => ['label' => 'DeepSeek 系统提示词', 'type' => 'textarea', 'default' => default_deepseek_system_prompt(), 'description' => '控制 DeepSeek 的角色、环境约束和评分标准。'],
        'deepseek_task_prompt' => ['label' => 'DeepSeek 解读任务提示词', 'type' => 'textarea', 'default' => default_deepseek_task_prompt(), 'description' => '控制每个项目解读的口吻、重点和判断方式；输出 JSON 字段结构由代码固定。'],
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
            'period' => 'daily / weekly',
            'limit' => '每周期最多 discover_per_page 个候选',
            'categories' => ['daily', 'weekly'],
            'source' => 'https://trendshift.io/',
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
            'label' => 'GitHub 搜索榜',
            'period' => '日报最近 N 天 / 周榜最近 N 天',
            'limit' => '每条搜索最多 discover_per_page 个候选',
            'categories' => ['综合', '新项目', 'ai', 'llm', 'agent', 'php', '额外搜索语句'],
            'source' => 'https://api.github.com/search/repositories',
        ],
    ];
}

function discover_fixed_platforms(): array
{
    return array_map(static function (array $platform): string {
        return (string) $platform['key'];
    }, discover_platform_catalog());
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
                 ideas_to_reuse = ?, risks = ?, change_note = ?, recommendation = ?, summary_zh = ?, raw_ai_json = ?, raw_rank_only = 1
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
          change_note, recommendation, summary_zh, raw_ai_json, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
