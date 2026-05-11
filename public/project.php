<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$project = $id > 0 ? project_with_reports($id) : null;

if (!$project) {
    http_response_code(404);
    render_header('项目不存在');
    echo '<div class="empty">项目不存在。</div>';
    render_footer();
    exit;
}

$tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'gir';
if (!in_array($tab, ['gir', 'readme'], true)) {
    $tab = 'gir';
}

$readmes = list_project_readmes((int) $project['id']);
$readmeBuckets = classify_project_readmes($readmes);

$readmeViewRequested = isset($_GET['readme']) ? (string) $_GET['readme'] : '';
$availableReadmeViews = [];
if ($readmeBuckets['native_zh']) {
    $availableReadmeViews['native_zh'] = '原版中文 README';
}
if ($readmeBuckets['native_en']) {
    $availableReadmeViews['native_en'] = '原版英文 README';
}
if ($readmeBuckets['machine_zh']) {
    $availableReadmeViews['machine_zh'] = '强翻中文 README';
}

$readmeView = $readmeViewRequested;
if (!isset($availableReadmeViews[$readmeView])) {
    if ($readmeBuckets['native_zh']) {
        $readmeView = 'native_zh';
    } elseif ($readmeBuckets['machine_zh']) {
        $readmeView = 'machine_zh';
    } elseif ($readmeBuckets['native_en']) {
        $readmeView = 'native_en';
    } else {
        $readmeView = '';
    }
}

render_header($project['full_name']);
?>
<div class="page-head">
    <div>
        <h1><?= h($project['full_name']) ?></h1>
        <div class="muted"><?= h($project['description']) ?></div>
    </div>
    <a class="button" href="<?= h($project['html_url']) ?>" target="_blank" rel="noreferrer">打开 GitHub</a>
</div>

<section class="panel">
    <div class="details">
        <div class="detail"><div class="detail-label">Stars</div><div class="detail-value"><?= (int) $project['stars'] ?></div></div>
        <div class="detail"><div class="detail-label">Forks</div><div class="detail-value"><?= (int) $project['forks'] ?></div></div>
        <div class="detail"><div class="detail-label">语言</div><div class="detail-value"><?= h($project['language'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">最近推送</div><div class="detail-value"><?= h($project['pushed_at'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">研究状态</div><div class="detail-value"><?= h(admin_project_statuses()[$project['admin_status']] ?? $project['admin_status']) ?></div></div>
        <div class="detail"><div class="detail-label">解读次数</div><div class="detail-value"><?= count($project['reports']) ?></div></div>
    </div>
</section>

<div class="tabs project-tabs">
    <a class="<?= $tab === 'gir' ? 'active' : '' ?>" href="?id=<?= (int) $project['id'] ?>&amp;tab=gir">GIR 解读</a>
    <a class="<?= $tab === 'readme' ? 'active' : '' ?>" href="?id=<?= (int) $project['id'] ?>&amp;tab=readme">完整 README<?= $readmes ? ' · ' . count($readmes) : '' ?></a>
</div>

<?php if ($tab === 'readme'): ?>
    <?php
    $currentReadme = null;
    if ($readmeView !== '') {
        $currentReadme = $readmeBuckets[$readmeView] ?? null;
    }
    ?>
    <?php if (count($availableReadmeViews) > 1): ?>
        <div class="tabs tag-tabs">
            <?php foreach ($availableReadmeViews as $key => $label): ?>
                <a class="<?= $readmeView === $key ? 'active' : '' ?>" href="?id=<?= (int) $project['id'] ?>&amp;tab=readme&amp;readme=<?= h($key) ?>">
                    <span><?= h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$currentReadme): ?>
        <div class="empty">这个项目的 README 还没被抓取到。后台 backlog 会在下一轮补跑时自动抓取；如果是英文 README，还会自动生成「强翻中文」版本。</div>
    <?php else: ?>
        <?php
        $languageLabel = '';
        switch ($readmeView) {
            case 'native_zh':
                $languageLabel = '项目提供的中文 README';
                break;
            case 'native_en':
                $languageLabel = '项目提供的原版 README';
                break;
            case 'machine_zh':
                $languageLabel = 'DeepSeek 一比一翻译的中文 README';
                break;
        }
        $sourceUrl = (string) ($currentReadme['source_url'] ?? '');
        $fetchedAt = (string) ($currentReadme['fetched_at'] ?? '');
        $html = render_markdown_html((string) $currentReadme['content_md'], (string) $project['full_name']);
        ?>
        <section class="panel readme-panel">
            <div class="readme-meta muted">
                <?= h($languageLabel) ?>
                <?php if ($sourceUrl !== ''): ?>
                    · <a href="<?= h($sourceUrl) ?>" target="_blank" rel="noreferrer">查看源文件</a>
                <?php endif; ?>
                <?php if ($fetchedAt !== ''): ?>
                    · 抓取于 <?= h($fetchedAt) ?>
                <?php endif; ?>
            </div>
            <div class="readme-body markdown-body">
                <?= $html ?>
            </div>
        </section>
    <?php endif; ?>
<?php else: ?>
    <?php
    $reports = $project['reports'];
    $latestReport = $reports ? $reports[0] : null;
    ?>

    <?php if ($latestReport): ?>
    <section class="panel">
        <div class="section-head">
            <div>
                <h2>GIR 解读</h2>
                <div class="muted">
                    <?= h(ranking_platform_label((string) $latestReport['source_platform'])) ?>
                    · <?= h(ranking_tag_label((string) $latestReport['source_tag'])) ?>
                    · <?= h($latestReport['report_date']) ?>
                </div>
            </div>
            <span class="<?= h(badge_class($latestReport['recommendation'])) ?>"><?= h($latestReport['recommendation']) ?></span>
        </div>
        <p class="desc"><?= h($latestReport['one_sentence']) ?></p>
        <?php if (!empty($latestReport['change_note'])): ?>
            <div class="text-block"><?= h($latestReport['change_note']) ?></div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!empty($project['admin_note'])): ?>
    <section class="panel">
        <h2>管理员备注</h2>
        <div class="text-block"><?= h($project['admin_note']) ?></div>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="section-head">
            <div>
                <h2>解读历史</h2>
                <div class="muted">同一个项目每次命中榜单都会保留一条新的 GIR 解读。</div>
            </div>
        </div>
        <?php if (!$reports): ?>
            <div class="empty">这个项目还没有 GIR 解读，暂时先看项目官方简介。</div>
        <?php else: ?>
            <div class="timeline">
                <?php foreach ($reports as $index => $report): ?>
                    <article class="timeline-item">
                        <div class="timeline-marker"><?= count($reports) - $index ?></div>
                        <div class="timeline-body">
                            <div class="section-head compact">
                                <div>
                                    <h2><?= h($report['period_type']) ?> · <?= h($report['report_date']) ?></h2>
                                    <div class="muted">
                                        <?= h(ranking_platform_label((string) $report['source_platform'])) ?>
                                        · <?= h(ranking_tag_label((string) $report['source_tag'])) ?>
                                        · #<?= (int) $report['source_rank'] ?>
                                        · <?= h($report['created_at']) ?>
                                    </div>
                                </div>
                                <span class="<?= h(badge_class($report['recommendation'])) ?>"><?= h($report['recommendation']) ?></span>
                            </div>
                            <div class="scores">
                                <span class="score">可玩 <?= (int) $report['play_score'] ?>/10</span>
                                <span class="score">实用 <?= (int) $report['useful_score'] ?>/10</span>
                                <span class="score">成熟 <?= display_maturity_score(array_merge($project, $report)) ?>/10</span>
                                <span class="score">难度 <?= h($report['difficulty']) ?></span>
                            </div>
                            <p class="desc"><?= h($report['one_sentence']) ?></p>
                            <?php if (!empty($report['change_note'])): ?>
                                <h2>变化观察</h2>
                                <div class="text-block"><?= h($report['change_note']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($report['summary_zh'])): ?>
                                <h2>中文总结</h2>
                                <div class="text-block"><?= h($report['summary_zh']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($report['ideas_to_reuse'])): ?>
                                <h2>可借鉴点</h2>
                                <div class="text-block"><?= h($report['ideas_to_reuse']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($report['risks'])): ?>
                                <h2>风险点</h2>
                                <div class="text-block"><?= h($report['risks']) ?></div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
