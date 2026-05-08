<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

require_admin();

$statuses = admin_project_statuses();
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_id'])) {
    $projectId = (int) $_POST['project_id'];
    $status = isset($_POST['admin_status']) ? (string) $_POST['admin_status'] : 'new';
    $hidden = isset($_POST['is_hidden']) && (string) $_POST['is_hidden'] === '1';
    $note = isset($_POST['admin_note']) ? (string) $_POST['admin_note'] : '';
    if ($projectId > 0 && update_project_admin($projectId, $status, $hidden, $note)) {
        $saved = true;
    } else {
        $error = '保存失败。';
    }
}

$filters = [
    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
    'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
    'visibility' => isset($_GET['visibility']) ? trim((string) $_GET['visibility']) : 'visible',
    'language' => isset($_GET['language']) ? trim((string) $_GET['language']) : '',
    'recommendation' => isset($_GET['recommendation']) ? trim((string) $_GET['recommendation']) : '',
];
$projects = admin_projects($filters, 100);
$options = admin_project_filter_options();

render_header('项目管理');
?>
<div class="page-head">
    <div>
        <h1>项目管理</h1>
        <div class="muted">筛选、隐藏和标记已发现的 GitHub 项目。</div>
    </div>
    <a class="button secondary" href="/admin/index.php">返回后台</a>
</div>

<?php if ($saved): ?>
    <div class="notice success">已保存。</div>
<?php elseif ($error): ?>
    <div class="notice error"><?= h($error) ?></div>
<?php endif; ?>

<section class="panel">
    <form method="get" class="filter-form">
        <input type="search" name="q" value="<?= h($filters['q']) ?>" placeholder="搜索项目、描述、总结">
        <select name="visibility">
            <option value=""<?= $filters['visibility'] === '' ? ' selected' : '' ?>>全部显示状态</option>
            <option value="visible"<?= $filters['visibility'] === 'visible' ? ' selected' : '' ?>>前台可见</option>
            <option value="hidden"<?= $filters['visibility'] === 'hidden' ? ' selected' : '' ?>>已隐藏</option>
        </select>
        <select name="status">
            <option value="">全部研究状态</option>
            <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= h($key) ?>"<?= $filters['status'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="language">
            <option value="">全部语言</option>
            <?php foreach ($options['languages'] as $row): ?>
                <option value="<?= h($row['language']) ?>"<?= $filters['language'] === $row['language'] ? ' selected' : '' ?>>
                    <?= h($row['language']) ?> · <?= (int) $row['total'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="recommendation">
            <option value="">全部推荐</option>
            <?php foreach ($options['recommendations'] as $row): ?>
                <option value="<?= h($row['recommendation']) ?>"<?= $filters['recommendation'] === $row['recommendation'] ? ' selected' : '' ?>>
                    <?= h($row['recommendation']) ?> · <?= (int) $row['total'] ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="button" type="submit">筛选</button>
        <a class="button secondary" href="/admin/projects.php">重置</a>
    </form>
</section>

<?php if (!$projects): ?>
    <div class="empty">没有符合条件的项目。</div>
<?php else: ?>
    <div class="admin-projects">
        <?php foreach ($projects as $project): ?>
            <article class="panel admin-project<?= (int) $project['is_hidden'] ? ' is-hidden' : '' ?>">
                <div class="project-top">
                    <div>
                        <h2 class="project-title">
                            <a href="/project.php?id=<?= (int) $project['id'] ?>"><?= h($project['full_name']) ?></a>
                        </h2>
                        <div class="muted"><?= h($project['one_sentence'] ?: $project['description']) ?></div>
                    </div>
                    <span class="<?= h(badge_class($project['recommendation'])) ?>"><?= h($project['recommendation'] ?: '未分析') ?></span>
                </div>

                <div class="metrics">
                    <span class="metric">Stars <?= (int) $project['stars'] ?></span>
                    <span class="metric">Forks <?= (int) $project['forks'] ?></span>
                    <span class="metric"><?= h($project['language'] ?: '未知语言') ?></span>
                    <span class="metric"><?= h($project['project_type'] ?: '未分类') ?></span>
                    <span class="metric"><?= (int) $project['is_hidden'] ? '已隐藏' : '前台可见' ?></span>
                    <span class="metric"><?= h($statuses[$project['admin_status']] ?? $project['admin_status']) ?></span>
                </div>

                <div class="scores">
                    <span class="score">可玩 <?= (int) $project['play_score'] ?>/10</span>
                    <span class="score">实用 <?= (int) $project['useful_score'] ?>/10</span>
                    <span class="score">成熟 <?= display_maturity_score($project) ?>/10</span>
                    <span class="score">难度 <?= h($project['difficulty'] ?: '-') ?></span>
                </div>

                <form method="post" class="project-admin-form">
                    <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
                    <label>
                        <span>研究状态</span>
                        <select name="admin_status">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?= h($key) ?>"<?= $project['admin_status'] === $key ? ' selected' : '' ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>展示状态</span>
                        <select name="is_hidden">
                            <option value="0"<?= (int) $project['is_hidden'] ? '' : ' selected' ?>>前台可见</option>
                            <option value="1"<?= (int) $project['is_hidden'] ? ' selected' : '' ?>>隐藏</option>
                        </select>
                    </label>
                    <label class="wide">
                        <span>管理员备注</span>
                        <textarea name="admin_note" rows="2"><?= h($project['admin_note']) ?></textarea>
                    </label>
                    <div class="project-actions">
                        <a href="<?= h($project['html_url']) ?>" target="_blank" rel="noreferrer">GitHub</a>
                        <button class="button" type="submit">保存</button>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_footer(); ?>
