<?php
require_once (is_file(__DIR__ . '/../lib/bootstrap.php') ? __DIR__ . '/../lib/bootstrap.php' : __DIR__ . '/lib/bootstrap.php');
require_once (is_file(__DIR__ . '/_layout.php') ? __DIR__ . '/_layout.php' : __DIR__ . '/public/_layout.php');

$query = isset($_GET['q']) ? trim(truncate_text((string) $_GET['q'], 120)) : '';
$search = $query !== '' ? github_search_repositories($query, 30) : ['ok' => true, 'items' => [], 'total_count' => 0, 'error' => ''];
$results = $search['items'] ?? [];
$localIngest = ['stored' => 0, 'project_ids' => []];
if ($query !== '' && !empty($search['ok']) && $results) {
    $localIngest = ingest_github_search_results_locally($query, $results);
}
$localIndex = projects_index_by_full_names(array_map(static function (array $row): string {
    return (string) ($row['full_name'] ?? '');
}, $results));

render_header('GitHub 搜索');
?>
<?php if ($query === ''): ?>
    <div class="empty">输入项目名、主题、语言或用途后，会直接搜索 GitHub 仓库。</div>
<?php elseif (empty($search['ok'])): ?>
    <div class="empty">GitHub 搜索失败：<?= h((string) ($search['error'] ?? 'unknown_error')) ?></div>
<?php elseif (!$results): ?>
    <div class="empty">GitHub 没有返回匹配 “<?= h($query) ?>” 的仓库。</div>
<?php else: ?>
    <div class="section-head compact">
        <div>
            <h2>GitHub 搜索结果</h2>
            <div class="muted">关键词 “<?= h($query) ?>” · GitHub 返回约 <?= number_format((int) ($search['total_count'] ?? count($results))) ?> 个仓库，当前展示前 <?= count($results) ?> 个 · 已即时入库 <?= number_format((int) ($localIngest['stored'] ?? 0)) ?> 个，将由后台 backlog 自动解读。</div>
        </div>
    </div>
    <div class="grid">
        <?php foreach ($results as $index => $row): ?>
            <?php
            $local = $localIndex[(string) ($row['full_name'] ?? '')] ?? [];
            $summary = trim((string) ($local['analysis_summary_zh'] ?? ''));
            if ($summary === '') {
                $summary = trim((string) ($local['analysis_one_sentence'] ?? ''));
            }
            if ($summary === '') {
                $summary = trim((string) ($local['analysis_change_note'] ?? ''));
            }
            if ($summary === '') {
                $summary = trim((string) ($row['description'] ?? ''));
            }
            $hasGirAnalysis = trim((string) ($local['analysis_one_sentence'] ?? '')) !== ''
                || trim((string) ($local['analysis_summary_zh'] ?? '')) !== ''
                || trim((string) ($local['analysis_change_note'] ?? '')) !== '';
            $projectId = (int) ($local['project_id'] ?? 0);
            $topics = is_array($row['topics'] ?? null) ? implode(' · ', array_slice($row['topics'], 0, 4)) : '';
            ?>
            <article class="project-card">
                <div class="project-top">
                    <div>
                        <div class="rank-label">GitHub 搜索 #<?= $index + 1 ?></div>
                        <h2 class="project-title">
                            <?php if ($projectId > 0): ?>
                                <a href="/project.php?id=<?= $projectId ?>"><?= h($row['full_name']) ?></a>
                            <?php else: ?>
                                <a href="<?= h($row['html_url']) ?>" target="_blank" rel="noreferrer"><?= h($row['full_name']) ?></a>
                            <?php endif; ?>
                        </h2>
                        <div class="muted"><?= h($row['description'] ?: '暂无项目简介') ?></div>
                    </div>
                    <span class="<?= $hasGirAnalysis ? 'badge green' : 'badge muted' ?>"><?= $hasGirAnalysis ? 'GIR 解读' : '待 GIR 解读' ?></span>
                </div>
                <div class="metrics">
                    <span class="metric">Stars <?= (int) $row['stars'] ?></span>
                    <span class="metric">Forks <?= (int) $row['forks'] ?></span>
                    <span class="metric"><?= h($row['language'] ?: '未知语言') ?></span>
                    <span class="metric">最近推送 <?= h($row['pushed_at'] ?: '-') ?></span>
                    <?php if ($topics !== ''): ?>
                        <span class="metric"><?= h($topics) ?></span>
                    <?php endif; ?>
                </div>
                <p class="desc"><?= h($summary ?: '已提交 GIR 入库与解读队列，暂时先显示 GitHub 项目简介。') ?></p>
                <div class="muted">
                    <?php if ($projectId > 0): ?>
                        <a href="/project.php?id=<?= $projectId ?>">查看 GIR 解读</a>
                        ·
                    <?php endif; ?>
                    <a href="<?= h($row['html_url']) ?>" target="_blank" rel="noreferrer">GitHub</a>
                    <?php if (!empty($local['analysis_report_date'])): ?>
                        · GIR 解读日期 <?= h((string) $local['analysis_report_date']) ?>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
