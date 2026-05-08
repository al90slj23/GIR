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
        if ($definition['type'] === 'checkbox') {
            $value = isset($settings[$key]) ? '1' : '0';
        } elseif ($definition['type'] === 'number') {
            $value = (string) max(0, (int) $value);
        }
        if (!update_app_setting($key, $value)) {
            $errors[] = $key;
        }
    }
    $saved = !$errors;
}

$rows = discover_settings();
$platforms = discover_platform_catalog();

render_header('采集设置');
?>
<div class="page-head">
    <div>
        <h1>采集设置</h1>
        <div class="muted">平台和分类固定在系统内，周期窗口和采集数量可配置，下一次 Actions 采集时自动生效。</div>
    </div>
    <a class="button secondary" href="/admin/index.php">返回后台</a>
</div>

<?php if ($saved): ?>
    <div class="notice success">已保存。</div>
<?php elseif ($errors): ?>
    <div class="notice error">保存失败：<?= h(implode(', ', $errors)) ?></div>
<?php endif; ?>

<section class="panel">
    <h2>固定平台与分类</h2>
    <table>
        <thead>
            <tr>
                <th>平台</th>
                <th>默认周期</th>
                <th>分类</th>
                <th>数量规则</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($platforms as $platform): ?>
                <tr>
                    <td>
                        <strong><?= h($platform['label']) ?></strong><br>
                        <span class="muted"><?= h($platform['key']) ?></span>
                    </td>
                    <td><?= h($platform['period']) ?></td>
                    <td><?= h(implode(', ', $platform['categories'])) ?></td>
                    <td><?= h($platform['limit']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>

<section class="panel">
    <h2>可配置参数</h2>
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
                    <?php elseif ($row['type'] === 'checkbox'): ?>
                        <input type="checkbox" name="settings[<?= h($row['key']) ?>]" value="1" <?= $row['value'] === '1' ? 'checked' : '' ?>>
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
    <div class="text-block">固定 GitHub Search 分类为：综合、新项目、ai、llm、agent、php。额外搜索语句每行一条，例如：language:PHP stars:&gt;20 pushed:&gt;{since}。{since} 会替换成当前日报或周榜的起始日期。自动触发时间由 GitHub Actions workflow 控制；后台关闭某个周期后，对应定时任务会跳过采集。</div>
</section>
<?php render_footer(); ?>
