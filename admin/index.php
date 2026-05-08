<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

require_admin();

$triggerMessage = '';
$triggerError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_discover'])) {
    $runType = isset($_POST['run_type']) ? (string) $_POST['run_type'] : 'daily';
    $result = trigger_github_discover($runType);
    if (!empty($result['ok'])) {
        $triggerMessage = '已提交 GitHub Actions 更新任务。';
    } else {
        $triggerError = isset($result['error']) ? (string) $result['error'] : '触发失败';
    }
}

$runs = recent_runs(30);
$canTrigger = github_trigger_configured();

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
    <p class="muted">从后台触发 GitHub Actions，抓取并分析最新项目。</p>
    <?php if ($triggerMessage): ?>
        <div class="notice success"><?= h($triggerMessage) ?></div>
    <?php elseif ($triggerError): ?>
        <div class="notice error"><?= h($triggerError) ?></div>
    <?php endif; ?>
    <?php if ($canTrigger): ?>
        <form method="post" class="inline-form">
            <select name="run_type">
                <option value="daily">今日榜</option>
                <option value="weekly">本周榜</option>
                <option value="manual">手动测试</option>
            </select>
            <button class="button" type="submit" name="trigger_discover" value="1">立即更新</button>
        </form>
    <?php else: ?>
        <div class="empty">GitHub 触发配置未完成。需要在虚拟主机 Data/.env 配置 GITHUB_OWNER、GITHUB_REPO、GITHUB_TOKEN、GITHUB_WORKFLOW。</div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>虚拟主机管理</h2>
    <p class="muted">一键打开三丰云虚拟主机控制台，管理当前站点空间、FTP 和数据库。</p>
    <a class="button" href="https://www.sanfengyun.com/control/#/vhost/6398841" target="_blank" rel="noreferrer">打开虚拟主机管理</a>
</section>

<section class="panel">
    <h2>项目管理</h2>
    <p class="muted">筛选已入库项目，设置收藏、研究状态、隐藏和管理员备注。</p>
    <a class="button" href="/admin/projects.php">管理项目</a>
</section>

<section class="panel">
    <h2>采集设置</h2>
    <p class="muted">调整关键词、topic、stars 门槛和每次分析数量，下一次 Actions 自动生效。</p>
    <a class="button" href="/admin/discovery.php">编辑采集设置</a>
</section>

<section class="panel">
    <h2>站点设置</h2>
    <p class="muted">站点名称、导航文字和页面文案现在从数据库读取。</p>
    <a class="button" href="/admin/settings.php">编辑站点设置</a>
</section>

<section class="panel">
    <h2>服务器探针</h2>
    <p class="muted">X Prober 已移入后台路径，访问时需要输入后台 token。</p>
    <a class="button" href="/admin/x.php">打开 X Prober</a>
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
