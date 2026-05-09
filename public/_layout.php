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

function render_deepseek_progress_panel(): void
{
    $progress = public_progress_summary();
    $focus = $progress['focus'];
    $percent = (float) ($focus['percent'] ?? 0);
    $percentText = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');
    $active = !empty($progress['active']);
    $platforms = $progress['platforms'];
    $timing = $focus['timing'] ?? [];
    ?>
<section class="progress-panel" data-progress-panel>
    <div class="progress-panel-top">
        <div>
            <div class="progress-kicker">DeepSeek 解读进度</div>
            <h2 data-progress-title><?= h($focus['label']) ?> · <?= h($percentText) ?>%</h2>
        </div>
        <span class="progress-status <?= $active ? 'is-active' : 'is-idle' ?>" data-progress-status><?= $active ? '正在更新' : '最近更新' ?></span>
    </div>
    <div class="progress-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= h($percentText) ?>">
        <span data-progress-bar style="width: <?= h($percentText) ?>%"></span>
    </div>
    <div class="progress-meta">
        <span><strong data-progress-percent><?= h($percentText) ?>%</strong> 完成</span>
        <span>已解读 <strong data-progress-analyzed><?= (int) ($focus['analyzed'] ?? 0) ?></strong></span>
        <span>原始候选 <strong data-progress-raw><?= (int) ($focus['raw_rank'] ?? 0) ?></strong></span>
        <span data-progress-date><?= h((string) ($progress['report_date'] ?: '-')) ?></span>
    </div>
    <div class="progress-timing">
        <span><strong data-progress-elapsed><?= h((string) ($timing['elapsed_text'] ?? '计算中')) ?></strong><em>任务已运行</em></span>
        <span><strong data-progress-rate><?= h((string) ($timing['rate_text'] ?? '计算中')) ?></strong><em>动态平均速度</em></span>
        <span><strong data-progress-eta><?= h((string) ($timing['eta_text'] ?? '计算中')) ?></strong><em>预计剩余时长</em></span>
        <span><strong data-progress-total><?= h((string) ($timing['estimated_total_text'] ?? '计算中')) ?></strong><em>预计总耗时</em></span>
        <span><strong data-progress-finish><?= h((string) (($timing['estimated_finish_at'] ?? '') ?: '计算中')) ?></strong><em>预计完成时间</em></span>
    </div>
    <div class="progress-platforms" data-progress-platforms>
        <?php foreach ($platforms as $platform): ?>
            <span><?= h($platform['label']) ?> <?= (int) $platform['analyzed'] ?>/<?= (int) $platform['raw_rank'] ?></span>
        <?php endforeach; ?>
    </div>
</section>
<script>
(function () {
  var panel = document.querySelector('[data-progress-panel]');
  if (!panel || panel.getAttribute('data-bound') === '1') {
    return;
  }
  panel.setAttribute('data-bound', '1');

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('zh-CN');
  }

  function formatPercent(value) {
    var percent = Math.max(0, Math.min(100, Number(value || 0)));
    return percent.toLocaleString('zh-CN', { maximumFractionDigits: 1 });
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

  function renderPlatforms(platforms) {
    var target = panel.querySelector('[data-progress-platforms]');
    if (!target) {
      return;
    }
    target.innerHTML = (platforms || []).map(function (item) {
      return '<span>' + escapeHtml(item.label) + ' ' + formatNumber(item.analyzed) + '/' + formatNumber(item.raw_rank) + '</span>';
    }).join('');
  }

  function setText(selector, value) {
    var target = panel.querySelector(selector);
    if (target) {
      target.textContent = value || '计算中';
    }
  }

  function renderProgress(payload) {
    if (!payload || !payload.ok || !payload.focus) {
      return;
    }
    var focus = payload.focus;
    var percent = Math.max(0, Math.min(100, Number(focus.percent || 0)));
    var percentText = formatPercent(percent);
    var timing = focus.timing || {};
    var status = panel.querySelector('[data-progress-status]');
    var meter = panel.querySelector('[role="progressbar"]');

    panel.querySelector('[data-progress-title]').textContent = (focus.label || '等待采集') + ' · ' + percentText + '%';
    panel.querySelector('[data-progress-bar]').style.width = percentText + '%';
    panel.querySelector('[data-progress-percent]').textContent = percentText + '%';
    panel.querySelector('[data-progress-analyzed]').textContent = formatNumber(focus.analyzed);
    panel.querySelector('[data-progress-raw]').textContent = formatNumber(focus.raw_rank);
    panel.querySelector('[data-progress-date]').textContent = payload.report_date || '-';
    setText('[data-progress-elapsed]', timing.elapsed_text);
    setText('[data-progress-rate]', timing.rate_text);
    setText('[data-progress-eta]', timing.eta_text);
    setText('[data-progress-total]', timing.estimated_total_text);
    setText('[data-progress-finish]', timing.estimated_finish_at);

    if (meter) {
      meter.setAttribute('aria-valuenow', String(percent));
    }
    if (status) {
      status.textContent = payload.active ? '正在更新' : '最近更新';
      status.className = 'progress-status ' + (payload.active ? 'is-active' : 'is-idle');
    }
    renderPlatforms(payload.platforms);
  }

  function loadProgress() {
    fetch('/api/progress.php', { cache: 'no-store' })
      .then(function (response) { return response.json(); })
      .then(renderProgress)
      .catch(function () {});
  }

  window.setTimeout(loadProgress, 1200);
  window.setInterval(loadProgress, 15000);
})();
</script>
<?php
}
