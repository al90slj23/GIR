<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

require_admin();

$stats = admin_stats();

function stat_number($value): string
{
    return number_format((int) $value);
}

render_header('数据统计');
?>
<div class="page-head">
    <div>
        <h1>数据统计</h1>
        <div class="muted">查看项目库规模、GIR 解读量、各来源平台和最近采集运行情况。</div>
    </div>
    <a class="button secondary" href="/admin/index.php">返回后台</a>
</div>

<section class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">项目总数</div>
        <div class="stat-value"><?= stat_number($stats['projects']['total']) ?></div>
        <div class="muted">可见 <?= stat_number($stats['projects']['visible']) ?> · 隐藏 <?= stat_number($stats['projects']['hidden']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">总报告记录</div>
        <div class="stat-value"><?= stat_number($stats['reports']['total']) ?></div>
        <div class="muted">原始排行 + GIR 解读</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">GIR 解读</div>
        <div class="stat-value"><?= stat_number($stats['reports']['analyzed']) ?></div>
        <div class="muted">可在前台 GIR 解读中展示</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">原始排行</div>
        <div class="stat-value"><?= stat_number($stats['reports']['raw_rank']) ?></div>
        <div class="muted">各平台原榜候选记录</div>
    </div>
</section>

<section class="panel">
    <h2>平台规模</h2>
    <table>
        <thead>
        <tr>
            <th>平台</th>
            <th>项目数</th>
            <th>总记录</th>
            <th>GIR 解读</th>
            <th>原始排行</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['platforms'] as $row): ?>
            <tr>
                <td><?= h(ranking_platform_label((string) $row['source_platform'])) ?></td>
                <td><?= stat_number($row['projects']) ?></td>
                <td><?= stat_number($row['total']) ?></td>
                <td><?= stat_number($row['analyzed']) ?></td>
                <td><?= stat_number($row['raw_rank']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>最近日期</h2>
    <table>
        <thead>
        <tr>
            <th>日期</th>
            <th>项目数</th>
            <th>总记录</th>
            <th>GIR 解读</th>
            <th>原始排行</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['dates'] as $row): ?>
            <tr>
                <td><?= h($row['report_date']) ?></td>
                <td><?= stat_number($row['projects']) ?></td>
                <td><?= stat_number($row['total']) ?></td>
                <td><?= stat_number($row['analyzed']) ?></td>
                <td><?= stat_number($row['raw_rank']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>热门分类</h2>
    <table>
        <thead>
        <tr>
            <th>平台</th>
            <th>分类</th>
            <th>总记录</th>
            <th>GIR 解读</th>
            <th>原始排行</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['tags'] as $row): ?>
            <tr>
                <td><?= h(ranking_platform_label((string) $row['source_platform'])) ?></td>
                <td><?= h(ranking_tag_label((string) $row['source_tag'])) ?></td>
                <td><?= stat_number($row['total']) ?></td>
                <td><?= stat_number($row['analyzed']) ?></td>
                <td><?= stat_number($row['raw_rank']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>最近运行</h2>
    <table>
        <thead>
        <tr>
            <th>ID</th>
            <th>类型</th>
            <th>状态</th>
            <th>开始</th>
            <th>结束</th>
            <th>发现/分析</th>
            <th>错误</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($stats['runs'] as $run): ?>
            <tr>
                <td><?= (int) $run['id'] ?></td>
                <td><?= h($run['run_type']) ?></td>
                <td><?= h($run['status']) ?></td>
                <td><?= h($run['started_at']) ?></td>
                <td><?= h($run['finished_at'] ?: '-') ?></td>
                <td><?= (int) $run['total_found'] ?> / <?= (int) $run['total_analyzed'] ?></td>
                <td><?= h($run['error_message']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer(); ?>
