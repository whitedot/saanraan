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
                <select name="ui_color_scheme" class="form-select" data-admin-color-scheme-select>
                    <?php foreach (sr_color_scheme_options() as $colorScheme => $colorSchemeLabel) { ?>
                        <option value="<?php echo sr_e((string) $colorScheme); ?>"<?php echo $values['ui_color_scheme'] === (string) $colorScheme ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $colorSchemeLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
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
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
