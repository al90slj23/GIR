<?php
require_once dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/public/_layout.php';

require_admin();

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : [];
    foreach (discover_setting_definitions() as $key => $definition) {
        $value = isset($settings[$key]) ? (string) $settings[$key] : (string) $definition['default'];
        if ($definition['type'] === 'number') {
            $value = (string) max(0, (int) $value);
        }
        if (!update_app_setting($key, $value)) {
            $errors[] = $key;
        }
    }
    $saved = !$errors;
}

$rows = discover_settings();

render_header('采集设置');
?>
<div class="page-head">
    <div>
        <h1>采集设置</h1>
        <div class="muted">调整 GitHub 搜索策略，下一次 Actions 采集时自动生效。</div>
    </div>
    <a class="button secondary" href="/admin/index.php">返回后台</a>
</div>

<?php if ($saved): ?>
    <div class="notice success">已保存。</div>
<?php elseif ($errors): ?>
    <div class="notice error">保存失败：<?= h(implode(', ', $errors)) ?></div>
<?php endif; ?>

<section class="panel">
    <form method="post">
        <div class="settings-list">
            <?php foreach ($rows as $row): ?>
                <label class="setting-row">
                    <span class="setting-label">
                        <strong><?= h($row['label']) ?></strong>
                        <em><?= h($row['description'] ?: $row['key']) ?></em>
                    </span>
                    <?php if ($row['type'] === 'textarea'): ?>
                        <textarea name="settings[<?= h($row['key']) ?>]" rows="4"><?= h($row['value']) ?></textarea>
                    <?php else: ?>
                        <input type="number" min="0" name="settings[<?= h($row['key']) ?>]" value="<?= h($row['value']) ?>">
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="button" type="submit">保存采集设置</button>
        <a class="button secondary" href="/admin/index.php">返回后台</a>
    </form>
</section>

<section class="panel">
    <h2>搜索语句说明</h2>
    <div class="text-block">Topics 支持逗号或换行分隔。额外搜索语句每行一条，例如：language:PHP stars:&gt;20 pushed:&gt;{since}。{since} 会替换成当前日报或周榜的起始日期。</div>
</section>
<?php render_footer(); ?>
