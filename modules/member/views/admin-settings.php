<?php

$adminPageTitle = sr_t('member::ui.member.settings.df7b9920');
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
        <h2><?php echo sr_e(sr_t('member::ui.text.564c3c84')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('member::ui.member.8df81cb2')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_member_admin_settings_allow_registration">
                                            <input id="modules_member_admin_settings_allow_registration" type="checkbox" name="allow_registration" value="1" class="form-checkbox"<?php echo !empty($settings['allow_registration']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('member::ui.member.8df81cb2')); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('member::ui.email.active.f166bfe8')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_member_admin_settings_email_verification_enabled">
                                            <input id="modules_member_admin_settings_email_verification_enabled" type="checkbox" name="email_verification_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['email_verification_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('member::ui.email.active.f166bfe8')); ?>
                                        </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label"><?php echo sr_e(sr_t('member::ui.login.ab1cc2ca')); ?></span>
            <div class="admin-form-field">
                <strong><?php echo sr_e((string) (sr_member_login_identifier_options()[(string) $settings['login_identifier']] ?? sr_t('member::ui.email.login.d1f22b60'))); ?></strong>
                <small class="admin-form-help"><?php echo sr_e(sr_t('member::ui.email.login.login.login.44f3662f')); ?></small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('member::ui.text.b5361f64')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_member_skin_key"><?php echo sr_e(sr_t('member::ui.member.3b335eb1')); ?></label>
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
        <h2><?php echo sr_e(sr_t('member::ui.select.da5d4203')); ?></h2>
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
                                            <?php echo sr_admin_choice_label_html((string) $label . sr_t('member::ui.text.dc690320')); ?>
                                        </label>
                                        <label class="admin-form-check form-label" for="<?php echo sr_e($requiredFieldId); ?>">
                                            <input id="<?php echo sr_e($requiredFieldId); ?>" type="checkbox" name="<?php echo sr_e($requiredKey); ?>" class="form-checkbox" value="1"<?php echo !empty($settings[$requiredKey]) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html((string) $label . sr_t('member::ui.required.800c5ae5')); ?>
                                        </label>
                </div>
            </div>
        <?php } ?>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('member::ui.login.b726ae4b')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_window_seconds"><?php echo sr_e(sr_t('member::ui.text.c7f70c10')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_window_seconds" type="number" name="login_throttle_window_seconds" value="<?php echo sr_e((string) $settings['login_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_account_limit"><?php echo sr_e(sr_t('member::ui.text.d78a4171')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_account_limit" type="number" name="login_throttle_account_limit" value="<?php echo sr_e((string) $settings['login_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_login_throttle_ip_limit"><?php echo sr_e(sr_t('member::ui.ip.62b8799d')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_login_throttle_ip_limit" type="number" name="login_throttle_ip_limit" value="<?php echo sr_e((string) $settings['login_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('member::ui.member.52394c42')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_register_throttle_window_seconds"><?php echo sr_e(sr_t('member::ui.text.c7f70c10')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_register_throttle_window_seconds" type="number" name="register_throttle_window_seconds" value="<?php echo sr_e((string) $settings['register_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_register_throttle_ip_limit"><?php echo sr_e(sr_t('member::ui.ip.62b8799d')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_register_throttle_ip_limit" type="number" name="register_throttle_ip_limit" value="<?php echo sr_e((string) $settings['register_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('member::ui.password.settings.be683f9d')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_window_seconds"><?php echo sr_e(sr_t('member::ui.text.c7f70c10')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_window_seconds" type="number" name="password_reset_throttle_window_seconds" value="<?php echo sr_e((string) $settings['password_reset_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_account_limit"><?php echo sr_e(sr_t('member::ui.text.d78a4171')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_account_limit" type="number" name="password_reset_throttle_account_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_password_reset_throttle_ip_limit"><?php echo sr_e(sr_t('member::ui.ip.62b8799d')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_password_reset_throttle_ip_limit" type="number" name="password_reset_throttle_ip_limit" value="<?php echo sr_e((string) $settings['password_reset_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('member::ui.email.2fbad242')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_window_seconds"><?php echo sr_e(sr_t('member::ui.text.c7f70c10')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_window_seconds" type="number" name="email_verification_throttle_window_seconds" value="<?php echo sr_e((string) $settings['email_verification_throttle_window_seconds']); ?>" class="form-input" min="0" max="86400">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_account_limit"><?php echo sr_e(sr_t('member::ui.text.d78a4171')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_account_limit" type="number" name="email_verification_throttle_account_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_account_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="member_admin_settings_email_verification_throttle_ip_limit"><?php echo sr_e(sr_t('member::ui.ip.62b8799d')); ?></label>
            <div class="admin-form-field">
                <input id="member_admin_settings_email_verification_throttle_ip_limit" type="number" name="email_verification_throttle_ip_limit" value="<?php echo sr_e((string) $settings['email_verification_throttle_ip_limit']); ?>" class="form-input" min="0" max="1000">
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
