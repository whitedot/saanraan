<?php

$adminPageTitle = '사이트 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <section class="admin-card card">
        <h2>사이트 기본값</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">사이트 이름</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">사이트 이름</span>
                <input type="text" name="name" value="<?php echo sr_e($values['name']); ?>" class="form-input" maxlength="120" required>
                </label>
            </div>
        </div>
        <p>
            <strong>외부 공개 URL</strong><br>
            <?php if ($values['base_url'] !== '') { ?>
                <code><?php echo sr_e($values['base_url']); ?></code>
            <?php } else { ?>
                <span>설정되지 않음</span>
            <?php } ?>
            <span class="admin-form-help">검색 결과, 공유 미리보기, 인증 메일 링크에 사용할 사이트 대표 주소입니다. 관리자 설정에서는 변경하지 않습니다.</span>
        </p>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">시간대</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">시간대</span>
                <input type="text" name="timezone" value="<?php echo sr_e($values['timezone']); ?>" class="form-input" maxlength="80" required>
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">기본 locale</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">기본 locale</span>
                <input type="text" name="default_locale" value="<?php echo sr_e($values['default_locale']); ?>" class="form-input" maxlength="20" required>
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">지원 locale 목록</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">지원 locale 목록</span>
                <input type="text" name="supported_locales" value="<?php echo sr_e($values['supported_locales']); ?>" class="form-input" maxlength="255" required>
                </label>
            <span class="admin-form-help">쉼표 또는 공백으로 구분합니다. 예: ko,en,ja</span>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">운영 상태</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">운영 상태</span>
                <select name="status" class="form-select">
                    <option value="active"<?php echo $values['status'] === 'active' ? ' selected' : ''; ?>>운영</option>
                    <option value="maintenance"<?php echo $values['status'] === 'maintenance' ? ' selected' : ''; ?>>점검</option>
                </select>
                </label>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2>화면</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">공통 레이아웃</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">공통 레이아웃</span>
                <select name="public_layout_key" class="form-select">
                    <?php foreach (sr_public_layout_options() as $layoutKey => $layoutOption) { ?>
                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">UI 색상 모드</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">UI 색상 모드</span>
                <select name="ui_color_scheme" class="form-select">
                    <?php foreach (sr_color_scheme_options() as $colorScheme => $colorSchemeLabel) { ?>
                        <option value="<?php echo sr_e((string) $colorScheme); ?>"<?php echo $values['ui_color_scheme'] === (string) $colorScheme ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $colorSchemeLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>관리자 화면</h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="admin_skin">
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">관리자 스킨</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">관리자 스킨</span>
                <select name="admin_skin_key" class="form-select">
                    <?php foreach ($adminSkinOptions as $skinKey => $skinOption) { ?>
                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $adminSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">관리자 화면 설정 저장</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">추가 사이트 설정 항목</h2>
    </div>
    <p>이 영역은 전용 화면이 없는 낮은 수준의 고급 설정입니다. 저장과 삭제는 소유자만 실행할 수 있습니다.</p>
    <?php if ($canManageAdvancedSettings) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="site_setting">
            <section class="admin-card card">
                <h2>설정 항목 추가</h2>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">키</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">키</span>
                        <input type="text" name="setting_key" maxlength="120" required class="form-input">
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">값</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">값</span>
                        <textarea name="setting_value" maxlength="5000" class="form-textarea"></textarea>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">유형</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">유형</span>
                        <select name="value_type" class="form-select">
                            <?php foreach ($allowedSettingTypes as $type) { ?>
                                <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'setting_type')); ?></option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="admin-form-label"><span class="form-label">소유자 비밀번호</span></div>
                    <div class="admin-form-field">
                        <label>
                            <span class="sr-only">소유자 비밀번호</span>
                        <input type="password" name="owner_password" autocomplete="current-password" class="form-input">
                        </label>
                    <span class="admin-form-help">고위험 설정 저장 시 필요하며 참/거짓 유형만 허용됩니다. 예: <code>admin.module_sources_enabled</code></span>
                    </div>
                </div>
            </section>
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
                <button type="submit" class="btn btn-solid-primary">항목 저장</button>
            </div>
        </form>
    <?php } ?>

    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>키</th>
                <th>값</th>
                <th>유형</th>
                <th>수정일</th>
                <th class="text-end">삭제</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($siteSettings === []) { ?>
                <tr>
                    <td colspan="5" class="admin-empty-state">설정 항목이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($siteSettings as $setting) { ?>
                <tr>
                    <td><?php echo sr_e((string) $setting['setting_key']); ?></td>
                    <td><?php echo sr_e(sr_admin_site_setting_display_value($setting)); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $setting['value_type'], 'setting_type')); ?></td>
                    <td><?php echo sr_e((string) $setting['updated_at']); ?></td>
                    <td class="admin-table-actions-cell">
                        <?php if ($canManageAdvancedSettings) { ?>
                            <div class="admin-row-actions admin-setting-manage">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_site_setting">
                                <input type="hidden" name="setting_key" value="<?php echo sr_e((string) $setting['setting_key']); ?>">
                                <?php if (sr_admin_site_setting_requires_reauth((string) $setting['setting_key'])) { ?>
                                    <label class="sr-only" for="delete_owner_password_<?php echo sr_e(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $setting['setting_key'])); ?>">소유자 비밀번호</label>
                                    <input type="password" name="owner_password" id="delete_owner_password_<?php echo sr_e(preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $setting['setting_key'])); ?>" class="form-input" autocomplete="current-password" placeholder="소유자 비밀번호" required>
                                <?php } ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                            </form>
                            </div>
                        <?php } else { ?>
                            소유자 전용
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
