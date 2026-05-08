<?php
function render_header(string $title): void
{
    global $config;
    $siteName = app_setting('site_name', $config['app']['name']);
    $assetVersion = is_file(__DIR__ . '/assets/app.css') ? (string) filemtime(__DIR__ . '/assets/app.css') : '1';
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

function render_rank_tabs(string $basePath, string $activeView, string $date, string $platform = '', string $tag = ''): void
{
    $githubQuery = http_build_query(array_filter([
        'platform' => $platform,
        'tag' => $tag,
        'view' => 'github',
        'date' => $date,
    ], 'strlen'));
    $deepseekQuery = http_build_query(array_filter([
        'platform' => $platform,
        'tag' => $tag,
        'view' => 'deepseek',
        'date' => $date,
    ], 'strlen'));
    ?>
<div class="tabs">
    <a class="<?= $activeView === 'github' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($githubQuery) ?>">原版排行</a>
    <a class="<?= $activeView === 'deepseek' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($deepseekQuery) ?>">DeepSeek 中文解读</a>
</div>
<?php
}

function render_platform_tabs(string $basePath, array $platforms, string $activePlatform, string $activeView, string $date, string $tag): void
{
    if (!$platforms) {
        return;
    }
    ?>
<div class="tabs platform-tabs">
    <?php foreach ($platforms as $platform): ?>
        <?php
        $value = (string) $platform['source_platform'];
        $query = http_build_query(array_filter([
            'platform' => $value,
            'view' => $activeView,
            'tag' => $tag,
            'date' => $date,
        ], 'strlen'));
        ?>
        <a class="<?= $activePlatform === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <?= h(ranking_platform_label($value)) ?> · <?= (int) $platform['total'] ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
}

function render_tag_tabs(string $basePath, array $tags, string $activeTag, string $activePlatform, string $activeView, string $date): void
{
    if (!$tags) {
        return;
    }
    $allQuery = http_build_query(array_filter([
        'platform' => $activePlatform,
        'view' => $activeView,
        'date' => $date,
    ], 'strlen'));
    ?>
<div class="tabs tag-tabs">
    <a class="<?= $activeTag === '' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($allQuery) ?>">全部分类</a>
    <?php foreach ($tags as $tag): ?>
        <?php
        $value = (string) $tag['source_tag'];
        $query = http_build_query(array_filter([
            'platform' => $activePlatform,
            'view' => $activeView,
            'tag' => $value,
            'date' => $date,
        ], 'strlen'));
        ?>
        <a class="<?= $activeTag === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <?= h(ranking_tag_label($value)) ?> · <?= (int) $tag['total'] ?>
        </a>
    <?php endforeach; ?>
</div>
<?php
}
