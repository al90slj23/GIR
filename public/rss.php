<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/bootstrap.php';

function rss_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rss_absolute_url(string $path, string $baseUrl): string
{
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

$siteName = app_setting('site_name', $config['app']['name']);
$baseUrl = app_setting('site_base_url', $config['app']['base_url']);
$reports = latest_reports('daily', 80);
$items = [];
$seen = [];

foreach ($reports as $report) {
    $projectId = (int) ($report['project_id'] ?? 0);
    if ($projectId <= 0 || isset($seen[$projectId])) {
        continue;
    }
    $seen[$projectId] = true;
    $items[] = $report;
    if (count($items) >= 30) {
        break;
    }
}

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title><?= rss_escape($siteName) ?></title>
    <link><?= rss_escape($baseUrl) ?></link>
    <atom:link href="<?= rss_escape(rss_absolute_url('/rss.php', $baseUrl)) ?>" rel="self" type="application/rss+xml" />
    <description><?= rss_escape('GitHub 项目中文解读与趋势发现') ?></description>
    <language>zh-CN</language>
    <lastBuildDate><?= rss_escape(date(DATE_RSS)) ?></lastBuildDate>
<?php foreach ($items as $item): ?>
<?php
    $projectId = (int) ($item['project_id'] ?? 0);
    $link = rss_absolute_url('/project.php?id=' . $projectId, $baseUrl);
    $title = trim((string) ($item['full_name'] ?? $item['name'] ?? ''));
    $oneSentence = trim((string) ($item['one_sentence'] ?? ''));
    $summary = trim((string) ($item['summary_zh'] ?? ''));
    $descriptionParts = [];
    if ($oneSentence !== '') {
        $descriptionParts[] = $oneSentence;
    }
    if ($summary !== '') {
        $descriptionParts[] = $summary;
    }
    $description = implode("\n\n", $descriptionParts);
    $publishedAt = (string) ($item['created_at'] ?? '');
    $publishedTime = strtotime($publishedAt);
    if ($publishedTime === false) {
        $publishedTime = strtotime((string) ($item['report_date'] ?? ''));
    }
    if ($publishedTime === false) {
        $publishedTime = time();
    }
?>
    <item>
        <title><?= rss_escape($title) ?></title>
        <link><?= rss_escape($link) ?></link>
        <guid isPermaLink="false"><?= rss_escape('gir-report-' . (string) ($item['id'] ?? $projectId)) ?></guid>
        <pubDate><?= rss_escape(date(DATE_RSS, $publishedTime)) ?></pubDate>
        <description><?= rss_escape($description) ?></description>
<?php if (!empty($item['source_platform'])): ?>
        <category><?= rss_escape(ranking_platform_label((string) $item['source_platform'])) ?></category>
<?php endif; ?>
<?php if (!empty($item['source_tag'])): ?>
        <category><?= rss_escape(ranking_tag_label((string) $item['source_tag'])) ?></category>
<?php endif; ?>
<?php if (!empty($item['language'])): ?>
        <category><?= rss_escape((string) $item['language']) ?></category>
<?php endif; ?>
    </item>
<?php endforeach; ?>
</channel>
</rss>
