<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$date = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['date']) : '';
$dates = recent_report_dates('weekly', 14);
$reports = $date ? reports_by_date('weekly', $date, 40) : latest_reports('weekly', 40);
$pageTitle = app_setting('weekly_title', '本周 GitHub 灵感榜');

render_header($pageTitle);
?>
<div class="page-head">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="muted"><?= h(app_setting('weekly_subtitle', '按周聚合更值得研究、学习和复刻的 GitHub 灵感项目。')) ?></div>
    </div>
</div>

<?php if ($dates): ?>
<div class="dates">
    <?php foreach ($dates as $item): ?>
        <a href="/weekly.php?date=<?= h($item['report_date']) ?>"><?= h($item['report_date']) ?> · <?= (int) $item['total'] ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$reports): ?>
    <div class="empty"><?= h(app_setting('weekly_empty_text', '还没有周榜数据。')) ?></div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $row): ?>
            <?php render_project_card($row); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
