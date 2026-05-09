<?php
function render_header(string $title): void
{
    global $config;
    $siteName = app_setting('site_name', $config['app']['name']);
    $assetPath = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\') . '/assets/app.css';
    if ($assetPath === '/assets/app.css' || !is_file($assetPath)) {
        $assetPath = __DIR__ . '/assets/app.css';
    }
    $assetVersion = is_file($assetPath) ? (string) filemtime($assetPath) : '1';
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> - <?= h($siteName) ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=<?= h($assetVersion) ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/index.php"><?= h($siteName) ?></a>
        <nav class="nav">
            <a href="/index.php"><?= h(app_setting('nav_today_label', '今日榜')) ?></a>
            <a href="/weekly.php"><?= h(app_setting('nav_weekly_label', '本周榜')) ?></a>
            <a href="/admin/index.php"><?= h(app_setting('nav_admin_label', '后台')) ?></a>
        </nav>
    </div>
</header>
<main class="wrap">
<?php
}

function render_footer(): void
{
    ?>
</main>
</body>
</html>
<?php
}

function render_project_card(array $row): void
{
    ?>
<article class="project-card">
    <div class="project-top">
        <div>
            <h2 class="project-title"><a href="/project.php?id=<?= (int) $row['project_id'] ?>"><?= h($row['full_name']) ?></a></h2>
            <div class="muted"><?= h($row['one_sentence']) ?></div>
        </div>
        <span class="<?= h(badge_class($row['recommendation'])) ?>"><?= h($row['recommendation']) ?></span>
    </div>
    <p class="desc"><?= h($row['summary_zh'] ?: $row['description']) ?></p>
    <div class="scores">
        <span class="score">可玩 <?= (int) $row['play_score'] ?>/10</span>
        <span class="score">实用 <?= (int) $row['useful_score'] ?>/10</span>
        <span class="score">成熟 <?= display_maturity_score($row) ?>/10</span>
        <span class="score">难度 <?= h($row['difficulty']) ?></span>
    </div>
    <div class="metrics">
        <span class="metric">Stars <?= (int) $row['stars'] ?></span>
        <span class="metric">Forks <?= (int) $row['forks'] ?></span>
        <span class="metric"><?= h($row['language'] ?: '未知语言') ?></span>
        <span class="metric"><?= h($row['project_type'] ?: '未分类') ?></span>
    </div>
    <div class="muted">
        <a href="<?= h($row['html_url']) ?>" target="_blank" rel="noreferrer">GitHub</a>
        · 报告日期 <?= h($row['report_date']) ?>
    </div>
</article>
<?php
}

function render_github_project_card(array $row, int $rank): void
{
    $platform = ranking_platform_label((string) ($row['source_platform'] ?? 'github'));
    $tag = ranking_tag_label((string) ($row['source_tag'] ?? '综合'));
    ?>
<article class="project-card">
    <div class="project-top">
        <div>
            <div class="rank-label">#<?= (int) (($row['source_rank'] ?? 0) ?: $rank) ?> <?= h($platform) ?> · <?= h($tag) ?></div>
            <h2 class="project-title"><a href="/project.php?id=<?= (int) $row['project_id'] ?>"><?= h($row['full_name']) ?></a></h2>
            <div class="muted"><?= h($row['description'] ?: $row['one_sentence']) ?></div>
        </div>
        <span class="badge muted"><?= h($row['language'] ?: '未知语言') ?></span>
    </div>
    <div class="metrics">
        <span class="metric">Stars <?= (int) $row['stars'] ?></span>
        <span class="metric">Forks <?= (int) $row['forks'] ?></span>
        <span class="metric">最近推送 <?= h($row['pushed_at'] ?: '-') ?></span>
        <span class="metric"><?= h($row['project_type'] ?: '未分类') ?></span>
    </div>
    <p class="desc"><?= h($row['one_sentence'] ?: '已完成 DeepSeek 中文解读，点进项目查看。') ?></p>
    <div class="muted">
        <a href="/project.php?id=<?= (int) $row['project_id'] ?>">查看中文解读</a>
        · <a href="<?= h($row['html_url']) ?>" target="_blank" rel="noreferrer">GitHub</a>
        · 报告日期 <?= h($row['report_date']) ?>
    </div>
</article>
<?php
}

function ranking_query_params(string $platform, string $tag, string $view, array $dateRange): array
{
    $params = [
        'platform' => $platform,
        'tag' => $tag,
        'view' => $view,
        'range' => (string) ($dateRange['range'] ?? 'today'),
    ];
    if (($dateRange['range'] ?? '') === 'custom') {
        $params['start_date'] = (string) ($dateRange['start'] ?? '');
        $params['end_date'] = (string) ($dateRange['end'] ?? '');
    }
    return array_filter($params, 'strlen');
}

function render_rank_tabs(string $basePath, string $activeView, array $dateRange, string $platform = '', string $tag = ''): void
{
    $githubQuery = http_build_query(ranking_query_params($platform, $tag, 'github', $dateRange));
    $deepseekQuery = http_build_query(ranking_query_params($platform, $tag, 'deepseek', $dateRange));
    ?>
<div class="tabs">
    <a class="<?= $activeView === 'github' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($githubQuery) ?>">原版排行</a>
    <a class="<?= $activeView === 'deepseek' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($deepseekQuery) ?>">DeepSeek 中文解读</a>
</div>
<?php
}

function render_platform_tabs(string $basePath, array $platforms, string $activePlatform, string $activeView, array $dateRange, string $tag): void
{
    if (!$platforms) {
        return;
    }
    ?>
<div class="tabs platform-tabs">
    <?php foreach ($platforms as $platform): ?>
        <?php
        $value = (string) $platform['source_platform'];
        $query = http_build_query(ranking_query_params($value, $tag, $activeView, $dateRange));
        ?>
        <a class="<?= $activePlatform === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <?= h(ranking_platform_label($value)) ?> · <?= (int) $platform['total'] ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
}

function render_tag_tabs(string $basePath, array $tags, string $activeTag, string $activePlatform, string $activeView, array $dateRange): void
{
    if (!$tags) {
        return;
    }
    $allQuery = http_build_query(ranking_query_params($activePlatform, '', $activeView, $dateRange));
    ?>
<div class="tabs tag-tabs">
    <a class="<?= $activeTag === '' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($allQuery) ?>">全部分类</a>
    <?php foreach ($tags as $tag): ?>
        <?php
        $value = (string) $tag['source_tag'];
        $query = http_build_query(ranking_query_params($activePlatform, $value, $activeView, $dateRange));
        ?>
        <a class="<?= $activeTag === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <?= h(ranking_tag_label($value)) ?> · <?= (int) $tag['total'] ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
}

function render_date_range_filter(string $basePath, string $activePlatform, string $activeTag, string $activeView, array $dateRange): void
{
    $options = report_date_range_options();
    ?>
<div class="date-filter">
    <div class="tabs date-tabs">
        <?php foreach ($options as $value => $label): ?>
            <?php if ($value === 'custom') continue; ?>
            <?php $query = http_build_query(ranking_query_params($activePlatform, $activeTag, $activeView, report_date_range($value))); ?>
            <a class="<?= $dateRange['range'] === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form class="date-custom-form" method="get" action="<?= h($basePath) ?>">
        <input type="hidden" name="platform" value="<?= h($activePlatform) ?>">
        <input type="hidden" name="tag" value="<?= h($activeTag) ?>">
        <input type="hidden" name="view" value="<?= h($activeView) ?>">
        <input type="hidden" name="range" value="custom">
        <label>
            <span>自定义时间</span>
            <input type="date" name="start_date" value="<?= h((string) $dateRange['start']) ?>">
        </label>
        <span class="date-separator">至</span>
        <label>
            <span>结束</span>
            <input type="date" name="end_date" value="<?= h((string) $dateRange['end']) ?>">
        </label>
        <button class="button secondary" type="submit">筛选</button>
    </form>
</div>
<?php
}
