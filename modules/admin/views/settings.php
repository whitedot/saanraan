<?php

$adminPageTitle = '사이트 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (!$currentHomepageAvailable) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon">!</span>
        <div class="admin-notice-copy">
            <strong>현재 저장된 초기화면을 사용할 수 없습니다.</strong>
            <p>방문자는 기본 홈페이지를 보게 됩니다. 사용할 수 있는 후보를 다시 선택해 저장하세요.</p>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="site">
    <section class="admin-card card">
        <h2>사이트 기본값</h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_name">사이트 이름</label>
            <div class="admin-form-field">
                <input id="admin_settings_name" type="text" name="name" value="<?php echo sr_e($values['name']); ?>" class="form-input" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">외부 공개 URL</span>
            <div class="admin-form-field">
                <?php if ($values['base_url'] !== '') { ?>
                    <code><?php echo sr_e($values['base_url']); ?></code>
                <?php } else { ?>
                    <span>설정되지 않음</span>
                <?php } ?>
                <p class="admin-form-help">검색 결과, 공유 미리보기, 인증 메일 링크에 사용할 사이트 대표 주소입니다. 관리자 설정에서는 변경하지 않습니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_timezone">시간대</label>
            <div class="admin-form-field">
                <select id="admin_settings_timezone" name="timezone" class="form-select" required>
                    <?php foreach ($timezoneOptions as $timezoneOption) { ?>
                        <option value="<?php echo sr_e($timezoneOption); ?>"<?php echo $values['timezone'] === $timezoneOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($timezoneOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_default_locale">기본 locale</label>
            <div class="admin-form-field">
                <select id="admin_settings_default_locale" name="default_locale" class="form-select" required>
                    <?php foreach ($localeOptions as $localeOption) { ?>
                        <option value="<?php echo sr_e($localeOption); ?>"<?php echo $values['default_locale'] === $localeOption ? ' selected' : ''; ?>>
                            <?php echo sr_e($localeOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_supported_locales">지원 locale 목록</label>
            <div class="admin-form-field">
                <?php $selectedSupportedLocales = sr_supported_locales($values); ?>
                <select id="admin_settings_supported_locales" name="supported_locales[]" class="form-select form-control-full" multiple required>
                    <?php foreach ($localeOptions as $localeOption) { ?>
                        <option value="<?php echo sr_e($localeOption); ?>"<?php echo in_array($localeOption, $selectedSupportedLocales, true) ? ' selected' : ''; ?>>
                            <?php echo sr_e($localeOption); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_status">운영 상태</label>
            <div class="admin-form-field">
                <select id="admin_settings_status" name="status" class="form-select">
                                    <option value="active"<?php echo $values['status'] === 'active' ? ' selected' : ''; ?>>운영</option>
                                    <option value="maintenance"<?php echo $values['status'] === 'maintenance' ? ' selected' : ''; ?>>점검</option>
                                </select>
            </div>
        </div>
    </section>
    <section class="admin-card card">
        <h2>화면</h2>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_public_layout_key">공통 레이아웃</label>
            <div class="admin-form-field">
                <select id="admin_settings_public_layout_key" name="public_layout_key" class="form-select">
                                    <?php foreach (sr_public_layout_options($pdo) as $layoutKey => $layoutOption) { ?>
                                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo $values['public_layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                        </option>
                                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_home_path">초기화면</label>
            <div class="admin-form-field">
                <select id="admin_settings_home_path" name="home_path" class="form-select form-control-full">
                    <?php foreach ($homepageCandidates as $candidate) { ?>
                        <?php $candidatePath = (string) ($candidate['path'] ?? ''); ?>
                        <?php $candidateSelected = (string) ($values['home_path'] ?? '/') === $candidatePath; ?>
                        <option value="<?php echo sr_e($candidatePath); ?>"<?php echo $candidateSelected ? ' selected' : ''; ?><?php echo empty($candidate['available']) && !$candidateSelected ? ' disabled' : ''; ?>>
                            <?php echo sr_e((string) ($candidate['label'] ?? $candidatePath)); ?>
                            <?php echo $candidatePath !== '/' ? ' - ' . sr_e($candidatePath) : ''; ?>
                            <?php echo empty($candidate['available']) ? ' (사용 불가)' : ''; ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">페이지 모듈의 공개 페이지와 커뮤니티 홈은 활성 상태일 때 초기화면 후보로 사용할 수 있습니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_ui_color_scheme">UI 색상 모드</label>
            <div class="admin-form-field">
                <select id="admin_settings_ui_color_scheme" name="ui_color_scheme" class="form-select" data-admin-color-scheme-select>
                                    <?php foreach (sr_color_scheme_options() as $colorScheme => $colorSchemeLabel) { ?>
                                        <option value="<?php echo sr_e((string) $colorScheme); ?>"<?php echo $values['ui_color_scheme'] === (string) $colorScheme ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) $colorSchemeLabel); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="admin_settings_admin_skin_key">관리자 스킨</label>
            <div class="admin-form-field">
                <select id="admin_settings_admin_skin_key" name="admin_skin_key" class="form-select">
                                    <?php foreach ($adminSkinOptions as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $adminSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <a href="<?php echo sr_e(sr_url('/')); ?>" class="btn btn-solid-light" target="_blank" rel="noopener noreferrer">홈 보기</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
