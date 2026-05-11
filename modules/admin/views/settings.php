<?php

$adminPageTitle = '사이트 설정';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo toy_e(toy_url('/admin/settings')); ?>">
    <?php echo toy_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <p>
        <label>사이트 이름<br>
            <input type="text" name="name" value="<?php echo toy_e($values['name']); ?>" maxlength="120" required>
        </label>
    </p>
    <p>
        <label>Base URL<br>
            <input type="url" name="base_url" value="<?php echo toy_e($values['base_url']); ?>" maxlength="255">
        </label>
    </p>
    <p>
        <label>Timezone<br>
            <input type="text" name="timezone" value="<?php echo toy_e($values['timezone']); ?>" maxlength="80" required>
        </label>
    </p>
    <p>
        <label>기본 locale<br>
            <input type="text" name="default_locale" value="<?php echo toy_e($values['default_locale']); ?>" maxlength="20" required>
        </label>
    </p>
    <p>
        <label>지원 locale 목록<br>
            <input type="text" name="supported_locales" value="<?php echo toy_e($values['supported_locales']); ?>" maxlength="255" required>
        </label>
        <span class="toy-install-help">쉼표 또는 공백으로 구분합니다. 예: ko,en,ja</span>
    </p>
    <p>
        <label>운영 상태<br>
            <select name="status">
                <option value="active"<?php echo $values['status'] === 'active' ? ' selected' : ''; ?>>운영</option>
                <option value="maintenance"<?php echo $values['status'] === 'maintenance' ? ' selected' : ''; ?>>점검</option>
            </select>
        </label>
    </p>
    <p>
        <label>공통 레이아웃<br>
            <select name="public_layout_key">
                <?php foreach (toy_public_layout_options() as $layoutKey => $layoutOption) { ?>
                    <option value="<?php echo toy_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                        <?php echo toy_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
    </p>
    <button type="submit">저장</button>
</form>

<section>
    <h2>추가 사이트 설정 항목</h2>
    <p>이 영역은 전용 화면이 없는 낮은 수준의 고급 설정입니다. 저장과 삭제는 owner만 실행할 수 있습니다.</p>
    <?php if ($canManageAdvancedSettings) { ?>
        <form method="post" action="<?php echo toy_e(toy_url('/admin/settings')); ?>">
            <?php echo toy_csrf_field(); ?>
            <input type="hidden" name="intent" value="site_setting">
            <p>
                <label>Key<br>
                    <input type="text" name="setting_key" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>Value<br>
                    <textarea name="setting_value" maxlength="5000"></textarea>
                </label>
            </p>
            <p>
                <label>Type<br>
                    <select name="value_type">
                        <?php foreach ($allowedSettingTypes as $type) { ?>
                            <option value="<?php echo toy_e($type); ?>"><?php echo toy_e($type); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>Owner 비밀번호<br>
                    <input type="password" name="owner_password" autocomplete="current-password">
                </label>
                <span class="toy-install-help">고위험 설정 저장 시 필요하며 bool 타입만 허용됩니다. 예: <code>admin.module_sources_enabled</code></span>
            </p>
            <button type="submit">항목 저장</button>
        </form>
    <?php } ?>

    <table>
        <thead>
            <tr>
                <th>Key</th>
                <th>Value</th>
                <th>Type</th>
                <th>Updated</th>
                <th>삭제</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($siteSettings === []) { ?>
                <tr>
                    <td colspan="5">설정 항목이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($siteSettings as $setting) { ?>
                <tr>
                    <td><?php echo toy_e((string) $setting['setting_key']); ?></td>
                    <td><?php echo toy_e(toy_admin_site_setting_display_value($setting)); ?></td>
                    <td><?php echo toy_e((string) $setting['value_type']); ?></td>
                    <td><?php echo toy_e((string) $setting['updated_at']); ?></td>
                    <td>
                        <?php if ($canManageAdvancedSettings) { ?>
                            <form method="post" action="<?php echo toy_e(toy_url('/admin/settings')); ?>">
                                <?php echo toy_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_site_setting">
                                <input type="hidden" name="setting_key" value="<?php echo toy_e((string) $setting['setting_key']); ?>">
                                <?php if (toy_admin_site_setting_requires_reauth((string) $setting['setting_key'])) { ?>
                                    <label>Owner 비밀번호<br>
                                        <input type="password" name="owner_password" autocomplete="current-password" required>
                                    </label>
                                <?php } ?>
                                <button type="submit">삭제</button>
                            </form>
                        <?php } else { ?>
                            owner 전용
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
