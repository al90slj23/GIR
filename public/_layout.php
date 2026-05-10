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
<html lang="zh-CN" data-theme="dark" data-theme-choice="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title><?= h($title) ?> - <?= h($siteName) ?></title>
    <link rel="alternate" type="application/rss+xml" title="<?= h($siteName) ?> RSS" href="/rss.php">
    <script>
    (function () {
        var storageKey = 'gir-theme';
        var choice = 'dark';
        try {
            choice = localStorage.getItem(storageKey) || 'dark';
        } catch (error) {}
        if (['auto', 'dark', 'light'].indexOf(choice) === -1) {
            choice = 'dark';
        }
        var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var resolved = choice === 'auto' ? (prefersDark ? 'dark' : 'light') : choice;
        document.documentElement.setAttribute('data-theme-choice', choice);
        document.documentElement.setAttribute('data-theme', resolved);
    })();
    </script>
    <link rel="stylesheet" href="/assets/app.css?v=<?= h($assetVersion) ?>">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="/index.php"><?= h($siteName) ?></a>
        <nav class="nav">
            <a href="/admin/index.php"><?= h(app_setting('nav_admin_label', '后台')) ?></a>
            <a href="/rss.php">RSS</a>
            <div class="theme-switcher" data-theme-switcher>
                <button class="theme-toggle" type="button" data-theme-toggle aria-haspopup="true" aria-expanded="false" aria-label="切换颜色模式">
                    <span data-theme-label>深色</span>
                </button>
                <div class="theme-menu" data-theme-menu hidden>
                    <button type="button" data-theme-option="auto">自动</button>
                    <button type="button" data-theme-option="dark">深色</button>
                    <button type="button" data-theme-option="light">浅色</button>
                </div>
            </div>
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
<script>
(function () {
    var root = document.documentElement;
    var switcher = document.querySelector('[data-theme-switcher]');
    if (!switcher) {
        return;
    }

    var storageKey = 'gir-theme';
    var labels = {
        auto: '自动',
        dark: '深色',
        light: '浅色'
    };
    var toggle = switcher.querySelector('[data-theme-toggle]');
    var label = switcher.querySelector('[data-theme-label]');
    var menu = switcher.querySelector('[data-theme-menu]');
    var options = Array.prototype.slice.call(switcher.querySelectorAll('[data-theme-option]'));
    var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function normalize(choice) {
        return labels[choice] ? choice : 'dark';
    }

    function currentChoice() {
        try {
            return normalize(localStorage.getItem(storageKey) || root.getAttribute('data-theme-choice') || 'dark');
        } catch (error) {
            return normalize(root.getAttribute('data-theme-choice') || 'dark');
        }
    }

    function resolveTheme(choice) {
        if (choice === 'auto') {
            return media && media.matches ? 'dark' : 'light';
        }
        return choice;
    }

    function applyTheme(choice, persist) {
        choice = normalize(choice);
        if (persist) {
            try {
                localStorage.setItem(storageKey, choice);
            } catch (error) {}
        }
        root.setAttribute('data-theme-choice', choice);
        root.setAttribute('data-theme', resolveTheme(choice));
        if (label) {
            label.textContent = labels[choice];
        }
        options.forEach(function (option) {
            var active = option.getAttribute('data-theme-option') === choice;
            option.classList.toggle('active', active);
            option.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setOpen(open) {
        if (!menu || !toggle) {
            return;
        }
        menu.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    applyTheme(currentChoice(), false);

    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            setOpen(menu.hidden);
        });
    }
    options.forEach(function (option) {
        option.addEventListener('click', function () {
            applyTheme(option.getAttribute('data-theme-option'), true);
            setOpen(false);
        });
    });
    document.addEventListener('click', function (event) {
        if (!switcher.contains(event.target)) {
            setOpen(false);
        }
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setOpen(false);
        }
    });

    if (media) {
        var syncAuto = function () {
            if (currentChoice() === 'auto') {
                applyTheme('auto', false);
            }
        };
        if (media.addEventListener) {
            media.addEventListener('change', syncAuto);
        } else if (media.addListener) {
            media.addListener(syncAuto);
        }
    }
})();
</script>
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
    $summary = trim((string) ($row['analysis_summary_zh'] ?? ''));
    if ($summary === '') {
        $summary = trim((string) ($row['analysis_one_sentence'] ?? ''));
    }
    if ($summary === '') {
        $summary = trim((string) ($row['analysis_change_note'] ?? ''));
    }
    if ($summary === '') {
        $summary = trim((string) ($row['one_sentence'] ?? ''));
    }
    if ($summary === '') {
        $summary = trim((string) ($row['description'] ?? ''));
    }
    if ($summary === '') {
        $summary = '等待 GIR 解读，暂时先显示项目基础信息。';
    }
    $hasGirAnalysis = trim((string) ($row['analysis_one_sentence'] ?? '')) !== ''
        || trim((string) ($row['analysis_summary_zh'] ?? '')) !== ''
        || trim((string) ($row['analysis_change_note'] ?? '')) !== '';
    $projectType = trim((string) ($row['analysis_project_type'] ?? ''));
    if ($projectType === '') {
        $projectType = trim((string) ($row['project_type'] ?? ''));
    }
    ?>
<article class="project-card">
    <div class="project-top">
        <div>
            <div class="rank-label">#<?= (int) (($row['source_rank'] ?? 0) ?: $rank) ?> <?= h($platform) ?> · <?= h($tag) ?></div>
            <h2 class="project-title"><a href="/project.php?id=<?= (int) $row['project_id'] ?>"><?= h($row['full_name']) ?></a></h2>
            <div class="muted"><?= h($row['description'] ?: $row['one_sentence']) ?></div>
        </div>
        <span class="<?= $hasGirAnalysis ? 'badge green' : 'badge muted' ?>"><?= $hasGirAnalysis ? 'GIR 解读' : '待 GIR 解读' ?></span>
    </div>
    <div class="metrics">
        <span class="metric">Stars <?= (int) $row['stars'] ?></span>
        <span class="metric">Forks <?= (int) $row['forks'] ?></span>
        <span class="metric">最近推送 <?= h($row['pushed_at'] ?: '-') ?></span>
        <span class="metric"><?= h($row['language'] ?: '未知语言') ?></span>
        <span class="metric"><?= h($projectType ?: '未分类') ?></span>
    </div>
    <p class="desc"><?= h($summary) ?></p>
    <div class="muted">
        <a href="/project.php?id=<?= (int) $row['project_id'] ?>">查看 GIR 解读</a>
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
    if ($view === 'gir') {
        unset($params['view']);
    }
    return array_filter($params, 'strlen');
}

function ranking_count_label(int $current, int $full): string
{
    if ($full > 0 && $current !== $full) {
        return number_format($current) . ' / ' . number_format($full);
    }
    return number_format($current);
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
        $currentTotal = (int) ($platform['total'] ?? 0);
        $fullTotal = (int) ($platform['full_total'] ?? $currentTotal);
        $query = http_build_query(ranking_query_params($value, '', $activeView, $dateRange));
        $classes = trim(($activePlatform === $value ? 'active ' : '') . ($value === 'backfill' ? 'is-backfill ' : '') . ($fullTotal > 0 && $currentTotal !== $fullTotal ? 'is-filtered' : ''));
        ?>
        <a class="<?= h($classes) ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <span class="platform-tab-name"><?= h(ranking_platform_label($value)) ?></span>
            <span class="platform-tab-count">
                <strong><?= number_format($currentTotal) ?></strong>
                <?php if ($fullTotal > 0 && $currentTotal !== $fullTotal): ?>
                    <em>/ <?= number_format($fullTotal) ?></em>
                <?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</div>
<?php
}

function render_github_search_entry(string $defaultQuery = ''): void
{
    ?>
<section class="search-entry-panel">
    <div>
        <h2>GitHub 搜索</h2>
        <div class="muted">搜索入口已从主排行中拆出，用于按关键词查看项目库结果；后续抓取会进入独立搜索队列。</div>
    </div>
    <form class="search-entry-form" action="/search.php" method="get">
        <input type="search" name="q" value="<?= h($defaultQuery) ?>" placeholder="搜索项目、主题、语言或用途">
        <button class="button" type="submit">搜索</button>
    </form>
</section>
<?php
}

function render_tag_tabs(string $basePath, array $tags, string $activeTag, string $activePlatform, string $activeView, array $dateRange, int $platformRangeTotal, int $platformFullTotal): void
{
    if (!$tags) {
        return;
    }
    $allQuery = http_build_query(ranking_query_params($activePlatform, '', $activeView, $dateRange));
    ?>
<div class="tabs tag-tabs">
    <a class="<?= $activeTag === '' ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($allQuery) ?>">
        <span>全部</span>
        <strong><?= h(ranking_count_label($platformRangeTotal, $platformFullTotal)) ?></strong>
    </a>
    <?php foreach ($tags as $tag): ?>
        <?php
        $value = (string) $tag['source_tag'];
        $currentTotal = (int) ($tag['total'] ?? 0);
        $fullTotal = (int) ($tag['full_total'] ?? $currentTotal);
        $query = http_build_query(ranking_query_params($activePlatform, $value, $activeView, $dateRange));
        ?>
        <a class="<?= $activeTag === $value ? 'active' : '' ?>" href="<?= h($basePath) ?>?<?= h($query) ?>">
            <span><?= h(ranking_tag_label($value)) ?></span>
            <strong><?= h(ranking_count_label($currentTotal, $fullTotal)) ?></strong>
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
        <form class="date-custom-form <?= $dateRange['range'] === 'custom' ? 'active' : '' ?>" method="get" action="<?= h($basePath) ?>">
            <input type="hidden" name="platform" value="<?= h($activePlatform) ?>">
            <input type="hidden" name="tag" value="<?= h($activeTag) ?>">
            <input type="hidden" name="view" value="<?= h($activeView) ?>">
            <input type="hidden" name="range" value="custom">
            <span class="date-custom-title">自定义</span>
            <label>
                <span>自定义</span>
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
            <div class="progress-kicker">GIR 解读进度</div>
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
