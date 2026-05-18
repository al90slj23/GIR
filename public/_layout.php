<?php
function render_header(string $title): void
{
    global $config;
    // Ensure CSRF cookie is sent before any HTML output.
    csrf_token();
    $siteName = app_setting('site_name', $config['app']['name']);
    $siteTagline = app_setting('site_tagline', app_setting('daily_subtitle', '每天发现值得研究和学习的 GitHub 灵感项目。'));
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
        <a class="brand" href="/index.php">
            <span class="brand-name"><?= h($siteName) ?></span>
            <span class="brand-tagline"><?= h($siteTagline) ?></span>
        </a>
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
<?php render_github_search_entry(isset($_GET['q']) ? (string) $_GET['q'] : ''); ?>
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
        <div class="muted">直接检索 GitHub 仓库；展示出来的项目会进入 GIR 项目库和解读队列。</div>
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


function render_progress_card_body(string $kind, array $data): void
{
    $progress = $data['progress'] ?? [];
    $estimate = $data['estimate'] ?? [];
    $currentRun = $data['current_run'] ?? [];
    $history = $data['history'] ?? [];
    $next = $data['next_schedule'] ?? [];
    $isCollection = $kind === 'collection';
    ?>
<div class="progress-section"><div class="progress-section-title">当前状态进度</div><div class="progress-stat-grid">
<?php if ($isCollection): ?>
    <span><strong><?= number_format((int) ($progress['with_readme'] ?? 0)) ?></strong><em>已有 README</em></span>
    <span><strong><?= number_format((int) ($progress['total'] ?? 0)) ?></strong><em>项目库总数</em></span>
    <span><strong><?= number_format((int) ($progress['pending_readme'] ?? 0)) ?></strong><em>缺 README</em></span>
    <span><strong><?= number_format((int) ($progress['with_zh'] ?? 0)) ?></strong><em>有中文可读</em></span>
    <span><strong><?= number_format((int) ($progress['pending_zh'] ?? 0)) ?></strong><em>缺中文</em></span>
<?php else: ?>
    <span><strong><?= number_format((int) ($progress['analyzed'] ?? 0)) ?></strong><em>已解读项目</em></span>
    <span><strong><?= number_format((int) ($progress['total'] ?? 0)) ?></strong><em>项目库总数</em></span>
    <span><strong><?= number_format((int) ($progress['refreshed'] ?? 0)) ?></strong><em>已刷新</em></span>
    <span><strong><?= number_format((int) ($progress['pending_refresh'] ?? 0)) ?></strong><em>待刷新</em></span>
    <span><strong><?= number_format((int) ($progress['pending_new'] ?? 0)) ?></strong><em>待解读（新）</em></span>
    <span><strong><?= number_format((int) ($progress['today_count'] ?? 0)) ?></strong><em>今日解读</em></span>
<?php endif; ?>
</div></div>
<div class="progress-section"><div class="progress-section-title">当前任务预计</div><div class="progress-stat-grid">
    <span><strong><?= h((string) ($estimate['rate_text'] ?? '计算中')) ?></strong><em>当前速度</em></span>
    <span><strong><?= h((string) ($estimate['eta_text'] ?? '计算中')) ?></strong><em>预计剩余</em></span>
    <span><strong><?= h((string) (($estimate['estimated_finish_at'] ?? '') ?: '-')) ?></strong><em>预计完成</em></span>
</div></div>
<div class="progress-section"><div class="progress-section-title">当前任务动态</div><div class="progress-stat-grid">
    <span><strong><?= h((string) ($currentRun['elapsed_text'] ?? '-')) ?></strong><em>本次持续</em></span>
    <?php if (!$isCollection): ?><span><strong><?= number_format((int) ($currentRun['today_count'] ?? 0)) ?></strong><em>本次解读数</em></span><?php endif; ?>
    <span><strong><?= h((string) ($currentRun['latest_at'] ?? '-')) ?></strong><em>最近活动</em></span>
</div>
<?php if (!$isCollection && !empty($currentRun['recent'])): ?>
<div class="progress-recent-list"><div class="progress-section-title" style="margin:0 0 6px">最近解读</div><?php foreach ($currentRun['recent'] as $item): ?><span class="progress-recent-item"><?= h($item['full_name'] ?? '') ?> <em><?= h($item['created_at'] ?? '') ?></em></span><?php endforeach; ?></div>
<?php endif; ?>
</div>
<div class="progress-section"><div class="progress-section-title"><?= h(($history['label'] ?? '') ?: '历史累计统计') ?></div><div class="progress-stat-grid">
<?php foreach (($history['stats'] ?? []) as $stat): ?>
    <span class="<?= !empty($stat['wide']) ? 'is-wide' : '' ?>"><strong><?= h($stat['value'] ?? '-') ?></strong><em><?= h($stat['label'] ?? '') ?></em></span>
<?php endforeach; ?>
</div></div>
<div class="progress-section"><div class="progress-section-title">下次周期倒计</div><div class="progress-next"><div><strong><?= h(($next['label'] ?? '') ?: '等待计划') ?></strong><span><?= h(($next['at'] ?? '') ?: '-') ?></span></div><div class="progress-countdown"><strong data-countdown-seconds="<?= (int) ($next['remaining_seconds'] ?? 0) ?>"><?= h(($next['remaining_text'] ?? '') ?: '计算中') ?></strong><span>倒计时</span></div></div></div>
<?php
}

function render_deepseek_progress_panel(): void
{
    $progress = public_progress_summary();
    $collection = $progress['collection'] ?? [];
    $gir = $progress['gir'] ?? ($progress['focus'] ?? []);
    $cPct = rtrim(rtrim(number_format((float) ($collection['percent'] ?? 0), 1, '.', ''), '0'), '.');
    $gPct = rtrim(rtrim(number_format((float) ($gir['percent'] ?? 0), 1, '.', ''), '0'), '.');
    $cMode = (string) ($collection['mode'] ?? 'idle');
    $gMode = (string) ($gir['mode'] ?? 'idle');
    ?>
<section class="progress-grid" data-progress-panel>
    <section class="progress-panel progress-card is-collection" data-progress-card="collection">
        <div class="progress-panel-top"><div><div class="progress-kicker">平台采集入库</div><h2 data-progress-title><?= h($collection['label'] ?? '平台采集入库') ?> · <?= h($cPct) ?>%</h2></div><span class="progress-status <?= $cMode === 'running' ? 'is-active' : 'is-idle' ?>" data-progress-status><?= h($collection['status_text'] ?? '-') ?></span></div>
        <div class="progress-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= h($cPct) ?>"><span data-progress-bar style="width:<?= h($cPct) ?>%"></span></div>
        <div data-progress-body><?php render_progress_card_body('collection', $collection); ?></div>
    </section>
    <section class="progress-panel progress-card is-gir" data-progress-card="gir">
        <div class="progress-panel-top"><div><div class="progress-kicker">GIR 解读</div><h2 data-progress-title><?= h($gir['label'] ?? 'GIR 解读') ?> · <?= h($gPct) ?>%</h2></div><span class="progress-status <?= $gMode === 'running' ? 'is-active' : ($gMode === 'pending' ? 'is-pending' : 'is-idle') ?>" data-progress-status><?= h($gir['status_text'] ?? '-') ?></span></div>
        <div class="progress-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= h($gPct) ?>"><span data-progress-bar style="width:<?= h($gPct) ?>%"></span></div>
        <div data-progress-body><?php render_progress_card_body('gir', $gir); ?></div>
    </section>
</section>
<script>
(function(){var panel=document.querySelector('[data-progress-panel]');if(!panel||panel.getAttribute('data-bound')==='1')return;panel.setAttribute('data-bound','1');function fN(v){return Number(v||0).toLocaleString('zh-CN')}function fP(v){return Math.max(0,Math.min(100,Number(v||0))).toLocaleString('zh-CN',{maximumFractionDigits:1})}function fD(v){var s=Math.max(0,Math.floor(Number(v||0))),d=Math.floor(s/86400),h=Math.floor((s%86400)/3600),m=Math.floor((s%3600)/60),sec=s%60,p=[];if(d)p.push(d+' 天');if(d||h)p.push(h+' 时');if(d||h||m)p.push(m+' 分');p.push(sec+' 秒');return p.join(' ')}function e(v){return String(v||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})}
function renderBody(kind,data){var p=data.progress||{},est=data.estimate||{},cr=data.current_run||{},h=data.history||{},n=data.next_schedule||{},isC=kind==='collection',html='<div class="progress-section"><div class="progress-section-title">当前状态进度</div><div class="progress-stat-grid">';if(isC){html+='<span><strong>'+fN(p.with_readme)+'</strong><em>已有 README</em></span><span><strong>'+fN(p.total)+'</strong><em>项目库总数</em></span><span><strong>'+fN(p.pending_readme)+'</strong><em>缺 README</em></span><span><strong>'+fN(p.with_zh)+'</strong><em>有中文可读</em></span><span><strong>'+fN(p.pending_zh)+'</strong><em>缺中文</em></span>'}else{html+='<span><strong>'+fN(p.analyzed)+'</strong><em>已解读项目</em></span><span><strong>'+fN(p.total)+'</strong><em>项目库总数</em></span><span><strong>'+fN(p.refreshed)+'</strong><em>已刷新</em></span><span><strong>'+fN(p.pending_refresh)+'</strong><em>待刷新</em></span><span><strong>'+fN(p.pending_new)+'</strong><em>待解读（新）</em></span><span><strong>'+fN(p.today_count)+'</strong><em>今日解读</em></span>'}html+='</div></div>';html+='<div class="progress-section"><div class="progress-section-title">当前任务预计</div><div class="progress-stat-grid"><span><strong>'+e(est.rate_text||'计算中')+'</strong><em>当前速度</em></span><span><strong>'+e(est.eta_text||'计算中')+'</strong><em>预计剩余</em></span><span><strong>'+e(est.estimated_finish_at||'-')+'</strong><em>预计完成</em></span></div></div>';html+='<div class="progress-section"><div class="progress-section-title">当前任务动态</div><div class="progress-stat-grid"><span><strong>'+e(cr.elapsed_text||'-')+'</strong><em>本次持续</em></span>';if(!isC)html+='<span><strong>'+fN(cr.today_count)+'</strong><em>本次解读数</em></span>';html+='<span><strong>'+e(cr.latest_at||'-')+'</strong><em>最近活动</em></span></div>';if(!isC&&cr.recent&&cr.recent.length){html+='<div class="progress-recent-list"><div class="progress-section-title" style="margin:0 0 6px">最近解读</div>';cr.recent.forEach(function(i){html+='<span class="progress-recent-item">'+e(i.full_name)+' <em>'+e(i.created_at)+'</em></span>'});html+='</div>'}html+='</div>';html+='<div class="progress-section"><div class="progress-section-title">'+e(h.label||'历史累计统计')+'</div><div class="progress-stat-grid">';(h.stats||[]).forEach(function(s){html+='<span class="'+(s.wide?'is-wide':'')+'"><strong>'+e(s.value||'-')+'</strong><em>'+e(s.label||'')+'</em></span>'});html+='</div></div>';var ns=Math.max(0,Math.floor(Number(n.remaining_seconds||0)));html+='<div class="progress-section"><div class="progress-section-title">下次周期倒计</div><div class="progress-next"><div><strong>'+e(n.label||'等待计划')+'</strong><span>'+e(n.at||'-')+'</span></div><div class="progress-countdown"><strong data-countdown-seconds="'+ns+'">'+fD(ns)+'</strong><span>倒计时</span></div></div></div>';return html}
function renderCard(kind,data){var card=panel.querySelector('[data-progress-card="'+kind+'"]');if(!card||!data)return;var pct=fP(data.percent);card.querySelector('[data-progress-title]').textContent=(data.label||'进度')+' · '+pct+'%';card.querySelector('[data-progress-bar]').style.width=pct+'%';var body=card.querySelector('[data-progress-body]');if(body)body.innerHTML=renderBody(kind,data);var st=card.querySelector('[data-progress-status]');if(st){st.textContent=data.status_text||'-';st.className='progress-status '+(data.mode==='running'?'is-active':data.mode==='pending'?'is-pending':'is-idle')}}
function renderProgress(payload){if(!payload||!payload.ok)return;renderCard('collection',payload.collection);renderCard('gir',payload.gir||payload.focus);updateCountdowns()}
function updateCountdowns(){panel.querySelectorAll('[data-countdown-seconds]').forEach(function(node){var s=Math.max(0,Math.floor(Number(node.getAttribute('data-countdown-seconds')||0)));node.textContent=fD(s);if(s>0)node.setAttribute('data-countdown-seconds',String(s-1))})}
function loadProgress(){fetch('/api/progress.php',{cache:'no-store'}).then(function(r){return r.json()}).then(renderProgress).catch(function(){})}
window.setTimeout(loadProgress,1200);window.setInterval(updateCountdowns,1000);window.setInterval(loadProgress,15000)})();
</script>
<?php
}
