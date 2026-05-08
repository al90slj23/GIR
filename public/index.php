<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$date = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['date']) : '';
$view = isset($_GET['view']) && $_GET['view'] === 'deepseek' ? 'deepseek' : 'github';
$dates = recent_report_dates('daily', 14);
$reports = $date
    ? ($view === 'github' ? github_rank_reports_by_date('daily', $date, 30) : reports_by_date('daily', $date, 30))
    : ($view === 'github' ? github_rank_reports('daily', 30) : latest_reports('daily', 30));
$pageTitle = app_setting('daily_title', '今日 GitHub 灵感榜');

render_header($pageTitle);
?>
<div class="page-head">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="muted"><?= h(app_setting('daily_subtitle', '每天发现值得研究和学习的 GitHub 灵感项目。')) ?></div>
    </div>
</div>

<?php render_rank_tabs('/index.php', $view, $date); ?>

<?php if ($dates): ?>
<div class="dates">
    <?php foreach ($dates as $item): ?>
        <a href="/index.php?view=<?= h($view) ?>&date=<?= h($item['report_date']) ?>"><?= h($item['report_date']) ?> · <?= (int) $item['total'] ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$reports): ?>
    <div class="empty"><?= h(app_setting('daily_empty_text', '还没有日报数据。完成 GitHub Actions 推送后，这里会显示灵感项目。')) ?></div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $index => $row): ?>
            <?php $view === 'github' ? render_github_project_card($row, $index + 1) : render_project_card($row); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
