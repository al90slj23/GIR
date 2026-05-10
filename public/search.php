<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$query = isset($_GET['q']) ? truncate_text((string) $_GET['q'], 120) : '';
$results = $query !== '' ? search_projects($query, 40) : [];

render_header('GitHub 搜索');
?>
<div class="page-head">
    <div>
        <h1>GitHub 搜索</h1>
        <div class="muted">这个入口独立于主排行，用来按关键词查找项目库；后续会接入动态 GitHub 搜索抓取和 GIR 解读队列。</div>
    </div>
</div>

<?php render_github_search_entry($query); ?>

<?php if ($query === ''): ?>
    <div class="empty">输入项目名、语言、主题或用途开始搜索。</div>
<?php elseif (!$results): ?>
    <div class="empty">项目库里还没有匹配 “<?= h($query) ?>” 的项目。</div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($results as $index => $row): ?>
            <?php
            $summary = trim((string) ($row['analysis_summary_zh'] ?? ''));
            if ($summary === '') {
                $summary = trim((string) ($row['analysis_one_sentence'] ?? ''));
            }
            if ($summary === '') {
                $summary = trim((string) ($row['analysis_change_note'] ?? ''));
            }
            if ($summary === '') {
                $summary = trim((string) ($row['description'] ?? ''));
            }
            $hasGirAnalysis = trim((string) ($row['analysis_one_sentence'] ?? '')) !== ''
                || trim((string) ($row['analysis_summary_zh'] ?? '')) !== ''
                || trim((string) ($row['analysis_change_note'] ?? '')) !== '';
            ?>
            <article class="project-card">
                <div class="project-top">
                    <div>
                        <div class="rank-label">搜索结果 #<?= $index + 1 ?></div>
                        <h2 class="project-title"><a href="/project.php?id=<?= (int) $row['project_id'] ?>"><?= h($row['full_name']) ?></a></h2>
                        <div class="muted"><?= h($row['description'] ?: '暂无项目简介') ?></div>
                    </div>
                    <span class="<?= $hasGirAnalysis ? 'badge green' : 'badge muted' ?>"><?= $hasGirAnalysis ? 'GIR 解读' : '待 GIR 解读' ?></span>
                </div>
                <div class="metrics">
                    <span class="metric">Stars <?= (int) $row['stars'] ?></span>
                    <span class="metric">Forks <?= (int) $row['forks'] ?></span>
                    <span class="metric"><?= h($row['language'] ?: '未知语言') ?></span>
                    <span class="metric">最近推送 <?= h($row['pushed_at'] ?: '-') ?></span>
                </div>
                <p class="desc"><?= h($summary ?: '等待 GIR 解读，暂时先显示项目基础信息。') ?></p>
                <div class="muted">
                    <a href="/project.php?id=<?= (int) $row['project_id'] ?>">查看 GIR 解读</a>
                    · <a href="<?= h($row['html_url']) ?>" target="_blank" rel="noreferrer">GitHub</a>
                    <?php if (!empty($row['analysis_report_date'])): ?>
                        · GIR 解读日期 <?= h($row['analysis_report_date']) ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
