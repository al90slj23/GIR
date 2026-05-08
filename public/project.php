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

<section class="panel">
    <div class="details">
        <div class="detail"><div class="detail-label">Stars</div><div class="detail-value"><?= (int) $project['stars'] ?></div></div>
        <div class="detail"><div class="detail-label">Forks</div><div class="detail-value"><?= (int) $project['forks'] ?></div></div>
        <div class="detail"><div class="detail-label">语言</div><div class="detail-value"><?= h($project['language'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">最近推送</div><div class="detail-value"><?= h($project['pushed_at'] ?: '-') ?></div></div>
        <div class="detail"><div class="detail-label">研究状态</div><div class="detail-value"><?= h(admin_project_statuses()[$project['admin_status']] ?? $project['admin_status']) ?></div></div>
    </div>
</section>

<?php if (!empty($project['admin_note'])): ?>
<section class="panel">
    <h2>管理员备注</h2>
    <div class="text-block"><?= h($project['admin_note']) ?></div>
</section>
<?php endif; ?>

<?php foreach ($project['reports'] as $report): ?>
<section class="panel">
    <h2><?= h($report['period_type']) ?> · <?= h($report['report_date']) ?> · <?= h($report['recommendation']) ?></h2>
    <div class="scores">
        <span class="score">可玩 <?= (int) $report['play_score'] ?>/10</span>
        <span class="score">实用 <?= (int) $report['useful_score'] ?>/10</span>
        <span class="score">难度 <?= h($report['difficulty']) ?></span>
    </div>
    <p class="desc"><?= h($report['one_sentence']) ?></p>
    <h2>中文总结</h2>
    <div class="text-block"><?= h($report['summary_zh']) ?></div>
    <h2>可借鉴点</h2>
    <div class="text-block"><?= h($report['ideas_to_reuse']) ?></div>
    <h2>风险点</h2>
    <div class="text-block"><?= h($report['risks']) ?></div>
</section>
<?php endforeach; ?>
<?php render_footer(); ?>
