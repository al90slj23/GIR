<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$date = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['date']) : '';
$dates = recent_report_dates('daily', 14);
$reports = $date ? reports_by_date('daily', $date, 30) : latest_reports('daily', 30);

render_header('今日 GitHub 灵感榜');
?>
<div class="page-head">
    <div>
        <h1>今日 GitHub 灵感榜</h1>
        <div class="muted">每天发现值得研究和学习的 GitHub 灵感项目。</div>
    </div>
</div>

<?php if ($dates): ?>
<div class="dates">
    <?php foreach ($dates as $item): ?>
        <a href="/index.php?date=<?= h($item['report_date']) ?>"><?= h($item['report_date']) ?> · <?= (int) $item['total'] ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$reports): ?>
    <div class="empty">还没有日报数据。完成 GitHub Actions 推送后，这里会显示灵感项目。</div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $row): ?>
            <?php render_project_card($row); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
