<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$date = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', (string) $_GET['date']) : '';
$view = isset($_GET['view']) && $_GET['view'] === 'deepseek' ? 'deepseek' : 'github';
$platforms = available_ranking_platforms('daily', $date);
$platform = isset($_GET['platform']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['platform']) : '';
if ($platform === '' && $platforms) {
    $platform = (string) $platforms[0]['source_platform'];
}
if ($platform === '') {
    $platform = 'github';
}
$tag = isset($_GET['tag']) ? truncate_text((string) $_GET['tag'], 64) : '';
$tags = available_ranking_tags('daily', $platform, $date);
$dates = recent_report_dates('daily', 14);
$reports = $date
    ? ($view === 'github' ? github_rank_reports_by_date('daily', $date, 30, $platform, $tag) : reports_by_date('daily', $date, 30, $platform, $tag))
    : ($view === 'github' ? github_rank_reports('daily', 30, $platform, $tag) : latest_reports('daily', 30, $platform, $tag));
$pageTitle = app_setting('daily_title', '今日 GitHub 灵感榜');

render_header($pageTitle);
?>
<div class="page-head">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="muted"><?= h(app_setting('daily_subtitle', '每天发现值得研究和学习的 GitHub 灵感项目。')) ?></div>
    </div>
</div>

<?php render_platform_tabs('/index.php', $platforms, $platform, $view, $date, $tag); ?>
<?php render_tag_tabs('/index.php', $tags, $tag, $platform, $view, $date); ?>
<?php render_rank_tabs('/index.php', $view, $date, $platform, $tag); ?>

<?php if ($dates): ?>
<div class="dates">
    <?php foreach ($dates as $item): ?>
        <a href="/index.php?platform=<?= h($platform) ?>&tag=<?= h($tag) ?>&view=<?= h($view) ?>&date=<?= h($item['report_date']) ?>"><?= h($item['report_date']) ?> · <?= (int) $item['total'] ?></a>
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
