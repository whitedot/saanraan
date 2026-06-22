<?php

$adminPageTitle = sr_t('member::ui.member.settings.6b4c84f7');
$memberSettingsHelpOpenLabel = sr_t('member::help.open');
$memberSettingsHelp = [
    'allow_registration' => [
        'id' => 'member-settings-help-allow-registration-modal',
        'title' => sr_t('member::help.settings.allow_registration.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.allow_registration.body.1',
            'member::help.settings.allow_registration.body.2',
        ]),
    ],
    'email_verification' => [
        'id' => 'member-settings-help-email-verification-modal',
        'title' => sr_t('member::help.settings.email_verification.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.email_verification.body.1',
            'member::help.settings.email_verification.body.2',
        ]),
    ],
    'login_identifier' => [
        'id' => 'member-settings-help-login-identifier-modal',
        'title' => sr_t('member::help.settings.login_identifier.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.login_identifier.body.1',
            'member::help.settings.login_identifier.body.2',
        ]),
    ],
    'member_skin' => [
        'id' => 'member-settings-help-member-skin-modal',
        'title' => sr_t('member::help.settings.member_skin.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.member_skin.body.1',
            'member::help.settings.member_skin.body.2',
        ]),
    ],
    'profile_field' => [
        'id' => 'member-settings-help-profile-field-modal',
        'title' => sr_t('member::help.settings.profile_field.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.profile_field.body.1',
            'member::help.settings.profile_field.body.2',
        ]),
    ],
    'throttle_window' => [
        'id' => 'member-settings-help-throttle-window-modal',
        'title' => sr_t('member::help.settings.throttle_window.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_window.body.1',
            'member::help.settings.throttle_window.body.2',
        ]),
    ],
    'throttle_account' => [
        'id' => 'member-settings-help-throttle-account-modal',
        'title' => sr_t('member::help.settings.throttle_account.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_account.body.1',
            'member::help.settings.throttle_account.body.2',
        ]),
    ],
    'throttle_ip' => [
        'id' => 'member-settings-help-throttle-ip-modal',
        'title' => sr_t('member::help.settings.throttle_ip.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.settings.throttle_ip.body.1',
            'member::help.settings.throttle_ip.body.2',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$memberSettingsSectionNavItems = [
    'member-settings-section-registration' => '가입/인증',
    'member-settings-section-skin' => '스킨',
    'member-settings-section-profile' => '프로필',
    'member-settings-section-login' => '로그인 제한',
    'member-settings-section-register-limit' => '가입 제한',
    'member-settings-section-password' => '비밀번호',
    'member-settings-section-email' => '이메일',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="회원 설정 섹션">
    <?php $memberSettingsSectionNavIndex = 0; ?>
    <?php foreach ($memberSettingsSectionNavItems as $memberSettingsSectionId => $memberSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $memberSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $memberSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $memberSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $memberSettingsSectionLabel); ?>
        </a>
        <?php $memberSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form method="post" action="<?php echo sr_e(sr_url('/admin/member-settings')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>

    <section id="member-settings-section-registration" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.text.564c3c84')); ?></h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.member.8df81cb2'), $memberSettingsHelp['allow_registration']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.member.8df81cb2')); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_allow_registration', 'allow_registration', '1', !empty($settings['allow_registration']), '사용'); ?>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.email.active.f166bfe8'), $memberSettingsHelp['email_verification']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.email.active.f166bfe8')); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_email_verification_enabled', 'email_verification_enabled', '1', !empty($settings['email_verification_enabled']), '사용'); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="modules_member_admin_settings_nickname_enabled"><?php echo sr_e(sr_t('member::ui.nickname')); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('modules_member_admin_settings_nickname_enabled', 'nickname_enabled', '1', !empty($settings['nickname_enabled']), '사용'); ?>
                    <small class="form-help"><?php echo sr_e(sr_t('member::settings.nickname.help')); ?></small>
                </div>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.login.ab1cc2ca'), $memberSettingsHelp['login_identifier']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.login.ab1cc2ca')); ?></span></span>
            <div class="form-field">
                <strong><?php echo sr_e((string) (sr_member_login_identifier_options()[(string) $settings['login_identifier']] ?? sr_t('member::ui.email.login.d1f22b60'))); ?></strong>
                <small class="form-help"><?php echo sr_e(sr_t('member::ui.email.login.login.login.44f3662f')); ?></small>
            </div>
        </div>
    </section>

    <section id="member-settings-section-skin" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.text.b5361f64')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_member_skin_key', sr_t('member::ui.member.3b335eb1'), $memberSettingsHelp['member_skin']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
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

    <section id="member-settings-section-profile" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.select.da5d4203')); ?></h2>
        <div class="form-grid">
        <?php foreach (sr_member_profile_field_definitions() as $definition) { ?>
            <?php
            $label = (string) $definition['label'];
            $enabledKey = (string) $definition['enabled_key'];
            $requiredKey = (string) $definition['required_key'];
            $enabledFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $enabledKey);
            $requiredFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $requiredKey);
            ?>
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html($label, $memberSettingsHelp['profile_field']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e($label); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html($enabledFieldId, $enabledKey, '1', !empty($settings[$enabledKey]), '사용', '', ' data-member-profile-visible data-member-profile-required-target="' . sr_e('#' . $requiredFieldId) . '"'); ?>
                    <?php echo sr_admin_switch_html($requiredFieldId, $requiredKey, '1', !empty($settings[$requiredKey]), '필수', '', ' data-member-profile-required data-member-profile-visible-target="' . sr_e('#' . $enabledFieldId) . '"'); ?>
                </div>
            </div>
        <?php } ?>
        </div>
    </section>

    <section id="member-settings-section-login" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.login.b726ae4b')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_login_throttle_window_seconds" type="number" name="login_throttle_window_seconds" value="<?php echo sr_e((string) $settings['login_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_login_throttle_account_limit" type="number" name="login_throttle_account_limit" value="<?php echo sr_e((string) $settings['login_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_login_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_login_throttle_ip_limit" type="number" name="login_throttle_ip_limit" value="<?php echo sr_e((string) $settings['login_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-register-limit" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.member.52394c42')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_register_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_register_throttle_window_seconds" type="number" name="register_throttle_window_seconds" value="<?php echo sr_e((string) $settings['register_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_register_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_register_throttle_ip_limit" type="number" name="register_throttle_ip_limit" value="<?php echo sr_e((string) $settings['register_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-password" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.password.settings.be683f9d')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_password_reset_throttle_window_seconds" type="number" name="password_reset_throttle_window_seconds" value="<?php echo sr_e((string) $settings['password_reset_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_password_reset_throttle_account_limit" type="number" name="password_reset_throttle_account_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_password_reset_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_password_reset_throttle_ip_limit" type="number" name="password_reset_throttle_ip_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section id="member-settings-section-email" class="card" data-admin-section-anchor>
        <h2><?php echo sr_e(sr_t('member::ui.email.2fbad242')); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_window_seconds', sr_t('member::ui.text.c7f70c10'), $memberSettingsHelp['throttle_window']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="member_admin_settings_email_verification_throttle_window_seconds" type="number" name="email_verification_throttle_window_seconds" value="<?php echo sr_e((string) $settings['email_verification_throttle_window_seconds']); ?>" required class="form-input" min="0" max="86400">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_account_limit', sr_t('member::ui.text.d78a4171'), $memberSettingsHelp['throttle_account']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_email_verification_throttle_account_limit" type="number" name="email_verification_throttle_account_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_account_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('member_admin_settings_email_verification_throttle_ip_limit', sr_t('member::ui.ip.62b8799d'), $memberSettingsHelp['throttle_ip']['id'], $memberSettingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="member_admin_settings_email_verification_throttle_ip_limit" type="number" name="email_verification_throttle_ip_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_ip_limit']); ?>" required class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php foreach ($memberSettingsHelp as $memberSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberSettingsHelpModal['id'], (string) $memberSettingsHelpModal['title'], (string) $memberSettingsHelpModal['body_html']); ?>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-member-profile-required]').forEach(function (requiredInput) {
        requiredInput.addEventListener('change', function () {
            if (!requiredInput.checked) {
                return;
            }

            var visibleTarget = requiredInput.getAttribute('data-member-profile-visible-target');
            var visibleInput = visibleTarget ? document.querySelector(visibleTarget) : null;
            if (visibleInput) {
                visibleInput.checked = true;
            }
        });
    });

    document.querySelectorAll('[data-member-profile-visible]').forEach(function (visibleInput) {
        visibleInput.addEventListener('change', function () {
            if (visibleInput.checked) {
                return;
            }

            var requiredTarget = visibleInput.getAttribute('data-member-profile-required-target');
            var requiredInput = requiredTarget ? document.querySelector(requiredTarget) : null;
            if (requiredInput) {
                requiredInput.checked = false;
            }
        });
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
