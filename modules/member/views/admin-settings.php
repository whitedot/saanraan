<?php

$adminPageTitle = '회원 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/member-settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="admin-card card">
        <h2>가입과 인증</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label">공개 회원가입 허용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_member_admin_settings_allow_registration">
                                            <input id="modules_member_admin_settings_allow_registration" type="checkbox" name="allow_registration" value="1" class="form-checkbox"<?php echo !empty($settings['allow_registration']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('공개 회원가입 허용'); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">이메일 인증 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_member_admin_settings_email_verification_enabled">
                                            <input id="modules_member_admin_settings_email_verification_enabled" type="checkbox" name="email_verification_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['email_verification_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('이메일 인증 사용'); ?>
                                        </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label">로그인 식별자</span>
            <div class="admin-form-field">
                <strong><?php echo sr_e((string) (sr_member_login_identifier_options()[(string) $settings['login_identifier']] ?? '이메일 + 로그인 아이디')); ?></strong>
                <small class="admin-form-help">로그인 정책은 최초 설치 때 정하는 값입니다. 운영 중 변경은 기존 계정 로그인 가능 여부에 영향을 줄 수 있어 이 화면에서 수정하지 않습니다.</small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>화면</h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_member_skin_key">회원 스킨</label>
            <div class="admin-form-field">
                <select id="member_admin_settings_member_skin_key" name="member_skin_key" class="form-select">
                                    <?php foreach (sr_member_skin_options() as $skinKey => $skinOption) { ?>
                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) $settings['member_skin_key'] === (string) $skinKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>선택 프로필 항목</h2>
        <div class="admin-form-grid">
        <?php foreach (sr_member_profile_field_definitions() as $definition) { ?>
            <?php
            $label = (string) $definition['label'];
            $enabledKey = (string) $definition['enabled_key'];
            $requiredKey = (string) $definition['required_key'];
            $enabledFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $enabledKey);
            $requiredFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $requiredKey);
            ?>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e($label); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="<?php echo sr_e($enabledFieldId); ?>">
                                            <input id="<?php echo sr_e($enabledFieldId); ?>" type="checkbox" name="<?php echo sr_e($enabledKey); ?>" class="form-checkbox" value="1"<?php echo !empty($settings[$enabledKey]) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html((string) $label . ' 보이기'); ?>
                                        </label>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($requiredFieldId); ?>">
                                            <input id="<?php echo sr_e($requiredFieldId); ?>" type="checkbox" name="<?php echo sr_e($requiredKey); ?>" class="form-checkbox" value="1"<?php echo !empty($settings[$requiredKey]) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html((string) $label . ' 필수입력'); ?>
                                        </label>
                </div>
            </div>
        <?php } ?>
        </div>
    </section>

    <section class="admin-card card">
        <h2>로그인 시도 제한</h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_window_seconds">제한 시간(초)</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_window_seconds" type="number" name="login_throttle_window_seconds" value="<?php echo sr_e((string) $settings['login_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_account_limit">계정 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_account_limit" type="number" name="login_throttle_account_limit" value="<?php echo sr_e((string) $settings['login_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_ip_limit">IP 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_ip_limit" type="number" name="login_throttle_ip_limit" value="<?php echo sr_e((string) $settings['login_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>회원가입 제한</h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_register_throttle_window_seconds">제한 시간(초)</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_register_throttle_window_seconds" type="number" name="register_throttle_window_seconds" value="<?php echo sr_e((string) $settings['register_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_register_throttle_ip_limit">IP 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_register_throttle_ip_limit" type="number" name="register_throttle_ip_limit" value="<?php echo sr_e((string) $settings['register_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>비밀번호 재설정 제한</h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_window_seconds">제한 시간(초)</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_window_seconds" type="number" name="password_reset_throttle_window_seconds" value="<?php echo sr_e((string) $settings['password_reset_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_account_limit">계정 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_account_limit" type="number" name="password_reset_throttle_account_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_ip_limit">IP 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_ip_limit" type="number" name="password_reset_throttle_ip_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>이메일 인증 제한</h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_window_seconds">제한 시간(초)</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_window_seconds" type="number" name="email_verification_throttle_window_seconds" value="<?php echo sr_e((string) $settings['email_verification_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_account_limit">계정 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_account_limit" type="number" name="email_verification_throttle_account_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_ip_limit">IP 기준 제한 횟수</label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_ip_limit" type="number" name="email_verification_throttle_ip_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
