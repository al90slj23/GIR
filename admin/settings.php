<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

require_admin();

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
    foreach ($settings as $key => $value) {
        $key = preg_replace('/[^a-z0-9_]/', '', (string) $key);
        if ($key === '') {
            continue;
        }
        if (!update_app_setting($key, (string) $value)) {
            $errors[] = $key;
        }
    }
    $saved = !$errors;
}

$rows = all_app_settings();

render_header('站点设置');
?>
<div class="page-head">
    <div>
        <h1>站点设置</h1>
        <div class="muted">编辑数据库中的运行期文案，保存后立即生效。</div>
    </div>
</div>

<?php if ($saved): ?>
    <div class="notice success">已保存。</div>
<?php elseif ($errors): ?>
    <div class="notice error">保存失败：<?= h(implode(', ', $errors)) ?></div>
<?php endif; ?>

<section class="panel">
    <?php if (!$rows): ?>
        <div class="empty">配置表还没有初始化，请先运行 install_schema.php。</div>
    <?php else: ?>
        <form method="post">
            <div class="settings-list">
                <?php foreach ($rows as $row): ?>
                    <label class="setting-row">
                        <span class="setting-label">
                            <strong><?= h($row['setting_key']) ?></strong>
                            <em><?= h($row['description']) ?></em>
                        </span>
                        <textarea name="settings[<?= h($row['setting_key']) ?>]" rows="2"><?= h($row['setting_value']) ?></textarea>
                    </label>
                <?php endforeach; ?>
            </div>
            <button class="button" type="submit">保存设置</button>
            <a class="button secondary" href="/admin/index.php">返回后台</a>
        </form>
    <?php endif; ?>
</section>
<?php render_footer(); ?>
