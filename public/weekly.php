<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$range = isset($_GET['range']) ? (string) $_GET['range'] : '7d';
$startDate = isset($_GET['start_date']) ? (string) $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? (string) $_GET['end_date'] : '';
$dateRange = report_date_range($range, $startDate, $endDate);
$view = 'gir';
$platforms = available_ranking_platforms_by_range('weekly', $dateRange);
$platform = isset($_GET['platform']) ? preg_replace('/[^a-z0-9_\-]/i', '', (string) $_GET['platform']) : '';
if ($platform === '' && $platforms) {
    $platform = (string) $platforms[0]['source_platform'];
}
if ($platform === '') {
    $platform = 'github_trending';
}
$platformValues = array_map(static function (array $row): string {
    return (string) ($row['source_platform'] ?? '');
}, $platforms);
if ($platforms && !in_array($platform, $platformValues, true)) {
    $platform = (string) $platforms[0]['source_platform'];
}
$tag = isset($_GET['tag']) ? truncate_text((string) $_GET['tag'], 64) : '';
$tags = available_ranking_tags_by_range('weekly', $platform, $dateRange);
$tagValues = array_map(static function (array $row): string {
    return (string) ($row['source_tag'] ?? '');
}, $tags);
if ($tag !== '' && !in_array($tag, $tagValues, true)) {
    $tag = '';
}
$activePlatformRangeTotal = raw_rank_count_by_range('weekly', $dateRange, $platform);
$activePlatformSelectedTotal = raw_rank_count_by_range('weekly', $dateRange, $platform, $tag);
$activePlatformFullTotal = raw_rank_count_all('weekly', $platform);
foreach ($platforms as $index => $row) {
    if ((string) ($row['source_platform'] ?? '') === $platform) {
        $platforms[$index]['total'] = $activePlatformSelectedTotal;
        $platforms[$index]['full_total'] = $activePlatformFullTotal;
        break;
    }
}
$reports = github_rank_reports_by_range('weekly', $dateRange, 40, $platform, $tag);
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
<?php render_github_search_entry(); ?>
<?php render_tag_tabs('/weekly.php', $tags, $tag, $platform, $view, $dateRange, $activePlatformRangeTotal, $activePlatformFullTotal); ?>
<?php render_date_range_filter('/weekly.php', $platform, $tag, $view, $dateRange); ?>

<?php if (!$reports): ?>
    <div class="empty"><?= h(app_setting('weekly_empty_text', '还没有周榜数据。')) ?></div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $index => $row): ?>
            <?php render_github_project_card($row, $index + 1); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
