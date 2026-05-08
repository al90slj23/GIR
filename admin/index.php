<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

$runs = recent_runs(30);

render_header('后台');
?>
<div class="page-head">
    <div>
        <h1>后台</h1>
        <div class="muted">查看运行记录，后续可从这里触发 GitHub Actions。</div>
    </div>
</div>

<section class="panel">
    <h2>手动触发</h2>
    <p class="muted">接口已预留。正式启用前需要先加后台登录，避免把触发 token 暴露在页面里。</p>
    <button class="button" type="button" disabled>等待接入后台登录</button>
</section>

<section class="panel">
    <h2>运行记录</h2>
    <?php if (!$runs): ?>
        <div class="empty">暂无运行记录。</div>
    <?php else: ?>
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
            <?php foreach ($runs as $run): ?>
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
    <?php endif; ?>
</section>
<?php render_footer(); ?>
