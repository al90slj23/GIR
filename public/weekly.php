<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$range = isset($_GET['range']) ? (string) $_GET['range'] : '7d';
$startDate = isset($_GET['start_date']) ? (string) $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? (string) $_GET['end_date'] : '';
$dateRange = report_date_range($range, $startDate, $endDate);
$view = isset($_GET['view']) && $_GET['view'] === 'deepseek' ? 'deepseek' : 'github';
$platforms = available_ranking_platforms_by_range('weekly', $dateRange);
$platform = isset($_GET['platform']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['platform']) : '';
if ($platform === '' && $platforms) {
    $platform = (string) $platforms[0]['source_platform'];
}
if ($platform === '') {
    $platform = 'github';
}
$tag = isset($_GET['tag']) ? truncate_text((string) $_GET['tag'], 64) : '';
$tags = available_ranking_tags_by_range('weekly', $platform, $dateRange);
$reports = $view === 'github'
    ? github_rank_reports_by_range('weekly', $dateRange, 40, $platform, $tag)
    : reports_by_range('weekly', $dateRange, 40, $platform, $tag);
$pageTitle = app_setting('weekly_title', '本周 GitHub 灵感榜');

render_header($pageTitle);
?>
<div class="page-head">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="muted"><?= h(app_setting('weekly_subtitle', '按周聚合更值得研究、学习和复刻的 GitHub 灵感项目。')) ?></div>
    </div>
</div>

<?php render_platform_tabs('/weekly.php', $platforms, $platform, $view, $dateRange, $tag); ?>
<?php render_tag_tabs('/weekly.php', $tags, $tag, $platform, $view, $dateRange); ?>
<?php render_rank_tabs('/weekly.php', $view, $dateRange, $platform, $tag); ?>
<?php render_date_range_filter('/weekly.php', $platform, $tag, $view, $dateRange); ?>

<?php if (!$reports): ?>
    <div class="empty"><?= h(app_setting('weekly_empty_text', '还没有周榜数据。')) ?></div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $index => $row): ?>
            <?php $view === 'github' ? render_github_project_card($row, $index + 1) : render_project_card($row); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
