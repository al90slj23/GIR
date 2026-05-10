<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$range = isset($_GET['range']) ? (string) $_GET['range'] : 'today';
$startDate = isset($_GET['start_date']) ? (string) $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? (string) $_GET['end_date'] : '';
$dateRange = report_date_range($range, $startDate, $endDate);
$view = 'gir';
$platforms = available_ranking_platforms_by_range('daily', $dateRange);
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
$tags = available_ranking_tags_by_range('daily', $platform, $dateRange);
$tagValues = array_map(static function (array $row): string {
    return (string) ($row['source_tag'] ?? '');
}, $tags);
if ($tag !== '' && !in_array($tag, $tagValues, true)) {
    $tag = '';
}
$activePlatformRangeTotal = raw_rank_count_by_range('daily', $dateRange, $platform);
$activePlatformSelectedTotal = raw_rank_count_by_range('daily', $dateRange, $platform, $tag);
$activePlatformFullTotal = raw_rank_count_all('daily', $platform);
foreach ($platforms as $index => $row) {
    if ((string) ($row['source_platform'] ?? '') === $platform) {
        $platforms[$index]['total'] = $activePlatformSelectedTotal;
        $platforms[$index]['full_total'] = $activePlatformFullTotal;
        break;
    }
}
$reports = github_rank_reports_by_range('daily', $dateRange, 30, $platform, $tag);
$pageTitle = app_setting('daily_title', '今日 GitHub 灵感榜');

render_header($pageTitle);
?>
<div class="page-head">
    <div>
        <h1><?= h($pageTitle) ?></h1>
        <div class="muted"><?= h(app_setting('daily_subtitle', '每天发现值得研究和学习的 GitHub 灵感项目。')) ?></div>
    </div>
</div>

<?php render_deepseek_progress_panel(); ?>
<?php render_platform_tabs('/index.php', $platforms, $platform, $view, $dateRange, $tag); ?>
<?php render_github_search_entry(); ?>
<?php render_tag_tabs('/index.php', $tags, $tag, $platform, $view, $dateRange, $activePlatformRangeTotal, $activePlatformFullTotal); ?>
<?php render_date_range_filter('/index.php', $platform, $tag, $view, $dateRange); ?>

<?php if (!$reports): ?>
    <div class="empty"><?= h(app_setting('daily_empty_text', '还没有日报数据。完成 GitHub Actions 推送后，这里会显示灵感项目。')) ?></div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($reports as $index => $row): ?>
            <?php render_github_project_card($row, $index + 1); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
