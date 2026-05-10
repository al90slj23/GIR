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

render_header($project['full_name']);
?>
<div class="page-head">
    <div>
        <h1><?= h($project['full_name']) ?></h1>
        <div class="muted"><?= h($project['description']) ?></div>
    </div>
    <a class="button" href="<?= h($project['html_url']) ?>" target="_blank" rel="noreferrer">打开 GitHub</a>
</div>

<?php
$reports = $project['reports'];
$latestReport = $reports ? $reports[0] : null;
?>

<section class="panel">
    <div class="details">
        <div class="detail"><div class="detail-label">Stars</div><div class="detail-value"><?= (int) $project['stars'] ?></div></div>
        <div class="detail"><div class="detail-label">Forks</div><div class="detail-value"><?= (int) $project['forks'] ?></div></div>
        <div class="detail"><div class="detail-label">语言</div><div class="detail-value"><?= h($project['language'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">最近推送</div><div class="detail-value"><?= h($project['pushed_at'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">研究状态</div><div class="detail-value"><?= h(admin_project_statuses()[$project['admin_status']] ?? $project['admin_status']) ?></div></div>
        <div class="detail"><div class="detail-label">解读次数</div><div class="detail-value"><?= count($reports) ?></div></div>
    </div>
</section>

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
<?php render_footer(); ?>
