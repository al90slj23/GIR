<?php
declare(strict_types=1);

require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');

function sitemap_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sitemap_absolute_url(string $path, string $baseUrl): string
{
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

$baseUrl = app_setting('site_base_url', $config['app']['base_url']);

$staticPaths = ['/index.php', '/weekly.php', '/rss.php'];
$projects = db_all(
    "SELECT p.id, p.updated_at, r.report_date
     FROM projects p
     LEFT JOIN project_reports r ON r.id = (
         SELECT rr.id FROM project_reports rr
         WHERE rr.project_id = p.id AND rr.raw_rank_only = 0 AND rr.one_sentence <> ''
         ORDER BY rr.report_date DESC, rr.id DESC
         LIMIT 1
     )
     WHERE p.is_hidden = 0
     ORDER BY p.updated_at DESC, p.id DESC
     LIMIT 1000"
);

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticPaths as $path): ?>
    <url>
        <loc><?= sitemap_escape(sitemap_absolute_url($path, $baseUrl)) ?></loc>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
<?php endforeach; ?>
<?php foreach ($projects as $row):
    $link = sitemap_absolute_url('/project.php?id=' . (int) $row['id'], $baseUrl);
    $lastmod = (string) ($row['updated_at'] ?? '');
    $lastmodTime = $lastmod !== '' ? strtotime($lastmod) : time();
    if ($lastmodTime === false) {
        $lastmodTime = time();
    }
?>
    <url>
        <loc><?= sitemap_escape($link) ?></loc>
        <lastmod><?= sitemap_escape(date('c', $lastmodTime)) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
<?php endforeach; ?>
</urlset>
