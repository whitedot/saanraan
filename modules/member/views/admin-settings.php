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

    <section id="member-settings-section-profile" class="card admin-list-card admin-list-form" data-admin-section-anchor data-member-profile-extra-fields-builder>
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.select.da5d4203')); ?></h2>
            <div class="admin-row-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="member-profile-extra-field-modal" data-overlay="#member-profile-extra-field-modal" data-member-profile-extra-field-add>항목 추가</button>
            </div>
        </div>
        <?php foreach (sr_member_profile_field_definitions() as $definition) { ?>
        <?php
        $label = (string) $definition['label'];
        $enabledKey = (string) $definition['enabled_key'];
        $requiredKey = (string) $definition['required_key'];
        $enabledFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $enabledKey);
        $requiredFieldId = 'member_profile_' . preg_replace('/[^a-zA-Z0-9_]+/', '_', $requiredKey);
        ?>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html($label, $memberSettingsHelp['profile_field']['id'], $memberSettingsHelpOpenLabel); ?><span><?php echo sr_e($label); ?></span></span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html($enabledFieldId, $enabledKey, '1', !empty($settings[$enabledKey]), '사용', '', ' data-member-profile-visible data-member-profile-required-target="' . sr_e('#' . $requiredFieldId) . '"'); ?>
                    <?php echo sr_admin_switch_html($requiredFieldId, $requiredKey, '1', !empty($settings[$requiredKey]), '필수', '', ' data-member-profile-required data-member-profile-visible-target="' . sr_e('#' . $enabledFieldId) . '"'); ?>
                </div>
            </div>
        </div>
        <?php } ?>
        <div class="table-wrapper" data-member-profile-extra-field-table-wrap hidden>
            <table class="table table-list" data-member-profile-extra-field-table>
                <caption class="sr-only">추가 프로필 항목 목록</caption>
                <thead>
                    <tr>
                        <th class="member-profile-extra-field-order-cell">순서</th>
                        <th>라벨</th>
                        <th>유형</th>
                        <th>표시</th>
                        <th>개인정보</th>
                        <th class="text-end">작업</th>
                    </tr>
                </thead>
                <tbody data-member-profile-extra-field-list></tbody>
            </table>
        </div>
        <p class="admin-empty-state" data-member-profile-extra-field-empty hidden>추가 프로필 항목이 없습니다.</p>
        <textarea id="member_admin_settings_profile_fields_json" name="profile_fields_json" hidden data-member-profile-extra-fields-json><?php echo sr_e((string) ($settings['profile_fields_json'] ?? '[]')); ?></textarea>
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

<div id="member-profile-extra-field-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="member-profile-extra-field-modal-label" aria-hidden="true" inert data-member-profile-extra-field-modal>
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="member-profile-extra-field-modal-label" class="modal-title" data-member-profile-extra-field-modal-title>추가 프로필 항목</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#member-profile-extra-field-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <input type="hidden" value="" data-member-profile-extra-field-index>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_key">Key <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_key" type="text" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input data-member-profile-extra-field-input="key" data-overlay-focus class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_label">라벨 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_label" type="text" maxlength="120" required data-member-profile-extra-field-input="label" class="form-input form-control-full">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_type">유형 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_type" required data-member-profile-extra-field-input="type" class="form-select">
                            <option value="text">텍스트</option>
                            <option value="textarea">긴 텍스트</option>
                            <option value="select">선택</option>
                            <option value="checkbox">체크박스</option>
                        </select>
                    </div>
                </div>
                <div class="form-row" data-member-profile-extra-field-options-row>
                    <label class="form-label" for="member_profile_extra_field_options">선택지 <span class="sr-required-label" data-member-profile-extra-field-options-required hidden>(필수)</span></label>
                    <div class="form-field">
                        <textarea id="member_profile_extra_field_options" rows="4" maxlength="6000" data-member-profile-extra-field-input="options" class="form-textarea form-control-full"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">필수 여부</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="member_profile_extra_field_required">
                            <input id="member_profile_extra_field_required" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="required">
                            <?php echo sr_admin_choice_label_html('필수 입력'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_visibility">공개 범위</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_visibility" data-member-profile-extra-field-input="visibility" class="form-select">
                            <option value="public">공개</option>
                            <option value="admin">관리자 전용</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">표시 위치</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="member_profile_extra_field_show_on_profile">
                            <input id="member_profile_extra_field_show_on_profile" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="show_on_profile">
                            <?php echo sr_admin_choice_label_html('프로필 화면 표시'); ?>
                        </label>
                        <label class="form-check form-label" for="member_profile_extra_field_show_in_admin">
                            <input id="member_profile_extra_field_show_in_admin" type="checkbox" value="1" class="form-switch form-switch-light" data-member-profile-extra-field-input="show_in_admin">
                            <?php echo sr_admin_choice_label_html('관리자 목록 표시'); ?>
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_privacy_purpose">개인정보 목적</label>
                    <div class="form-field">
                        <input id="member_profile_extra_field_privacy_purpose" type="text" maxlength="255" data-member-profile-extra-field-input="privacy_purpose" class="form-input form-control-full">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_export_policy">Export 정책</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_export_policy" data-member-profile-extra-field-input="export_policy" class="form-select">
                            <option value="include">포함</option>
                            <option value="exclude">제외</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_profile_extra_field_cleanup_policy">Cleanup 정책</label>
                    <div class="form-field">
                        <select id="member_profile_extra_field_cleanup_policy" data-member-profile-extra-field-input="cleanup_policy" class="form-select">
                            <option value="anonymize">익명화</option>
                            <option value="retain">보관</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#member-profile-extra-field-modal">닫기</button>
                <button type="button" class="btn btn-solid-primary modal-action" data-member-profile-extra-field-save>적용</button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($memberSettingsHelp as $memberSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberSettingsHelpModal['id'], (string) $memberSettingsHelpModal['title'], (string) $memberSettingsHelpModal['body_html']); ?>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function memberProfileExtraFieldAllowedType(value) {
        return ['text', 'textarea', 'select', 'checkbox'].indexOf(value) !== -1 ? value : 'text';
    }

    function memberProfileExtraFieldNormalize(raw) {
        var definitions = [];
        var seen = {};
        if (!Array.isArray(raw)) {
            return definitions;
        }
        raw.forEach(function (item) {
            if (!item || typeof item !== 'object' || definitions.length >= 20) {
                return;
            }
            var key = String(item.key || '').trim().toLowerCase();
            if (!/^[a-z][a-z0-9_]{1,59}$/.test(key) || seen[key]) {
                return;
            }
            var label = String(item.label || '').replace(/\s+/g, ' ').trim().slice(0, 120);
            if (label === '') {
                return;
            }
            var type = memberProfileExtraFieldAllowedType(String(item.type || 'text'));
            var options = [];
            if (type === 'select') {
                if (Array.isArray(item.options)) {
                    item.options.forEach(function (option) {
                        var value = String(option || '').replace(/\s+/g, ' ').trim().slice(0, 120);
                        if (value !== '' && options.indexOf(value) === -1 && options.length < 50) {
                            options.push(value);
                        }
                    });
                }
                if (options.length === 0) {
                    return;
                }
            }
            definitions.push({
                key: key,
                label: label,
                type: type,
                required: !!item.required,
                options: options,
                visibility: String(item.visibility || 'public') === 'admin' ? 'admin' : 'public',
                show_on_profile: Object.prototype.hasOwnProperty.call(item, 'show_on_profile') ? !!item.show_on_profile : true,
                show_in_admin: !!item.show_in_admin,
                privacy_purpose: String(item.privacy_purpose || '').trim(),
                export_policy: String(item.export_policy || 'include') === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: String(item.cleanup_policy || 'anonymize') === 'retain' ? 'retain' : 'anonymize'
            });
            seen[key] = true;
        });
        return definitions;
    }

    function memberProfileExtraFieldParse(textarea) {
        if (!textarea) {
            return [];
        }
        try {
            return memberProfileExtraFieldNormalize(JSON.parse(textarea.value || '[]'));
        } catch (error) {
            return [];
        }
    }

    function memberProfileExtraFieldTypeLabel(type) {
        var labels = {
            text: '텍스트',
            textarea: '긴 텍스트',
            select: '선택',
            checkbox: '체크박스'
        };
        return labels[type] || labels.text;
    }

    function memberProfileExtraFieldPolicyLabel(field) {
        var exportLabel = field.export_policy === 'exclude' ? 'Export 제외' : 'Export 포함';
        var cleanupLabel = field.cleanup_policy === 'retain' ? '보관' : '익명화';
        return exportLabel + ' / ' + cleanupLabel;
    }

    function memberProfileExtraFieldRandomKey(definitions) {
        var existing = {};
        memberProfileExtraFieldNormalize(definitions).forEach(function (field) {
            existing[field.key] = true;
        });
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var randomPart = function () {
            var value = '';
            var bytes = null;
            if (window.crypto && typeof window.crypto.getRandomValues === 'function') {
                bytes = new Uint8Array(10);
                window.crypto.getRandomValues(bytes);
            }
            for (var i = 0; i < 10; i++) {
                var index = bytes ? bytes[i] % chars.length : Math.floor(Math.random() * chars.length);
                value += chars.charAt(index);
            }
            return value;
        };
        for (var attempt = 0; attempt < 20; attempt++) {
            var key = 'field_' + randomPart();
            if (!existing[key]) {
                return key;
            }
        }

        return 'field_' + String(Date.now()).slice(-10);
    }

    function memberProfileExtraFieldWrite(root, definitions) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        if (!textarea) {
            return;
        }
        textarea.value = JSON.stringify(memberProfileExtraFieldNormalize(definitions), null, 2);
        textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function memberProfileExtraFieldRender(root) {
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        var list = root ? root.querySelector('[data-member-profile-extra-field-list]') : null;
        var empty = root ? root.querySelector('[data-member-profile-extra-field-empty]') : null;
        var tableWrap = root ? root.querySelector('[data-member-profile-extra-field-table-wrap]') : null;
        if (!textarea || !list) {
            return;
        }
        var definitions = memberProfileExtraFieldParse(textarea);
        list.innerHTML = '';
        if (empty) {
            empty.hidden = definitions.length > 0;
        }
        if (tableWrap) {
            tableWrap.hidden = definitions.length === 0;
        }
        definitions.forEach(function (field, index) {
            var row = document.createElement('tr');

            var orderCell = document.createElement('td');
            orderCell.className = 'member-profile-extra-field-order-cell';
            var orderGroup = document.createElement('div');
            orderGroup.className = 'admin-row-actions';
            [
                ['up', '위로', 'arrow_upward'],
                ['down', '아래로', 'arrow_downward']
            ].forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-member-profile-extra-field-action', action[0]);
                button.setAttribute('data-member-profile-extra-field-index-value', String(index));
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                orderGroup.appendChild(button);
            });
            orderCell.appendChild(orderGroup);
            row.appendChild(orderCell);

            var labelCell = document.createElement('td');
            labelCell.textContent = field.label + (field.required ? ' (필수)' : '');
            row.appendChild(labelCell);

            var typeCell = document.createElement('td');
            typeCell.textContent = memberProfileExtraFieldTypeLabel(field.type);
            row.appendChild(typeCell);

            var displayCell = document.createElement('td');
            var display = [];
            display.push(field.visibility === 'admin' ? '관리자 전용' : '공개');
            if (field.show_on_profile) {
                display.push('프로필');
            }
            if (field.show_in_admin) {
                display.push('관리자 목록');
            }
            displayCell.textContent = display.join(' / ');
            row.appendChild(displayCell);

            var privacyCell = document.createElement('td');
            privacyCell.textContent = (field.privacy_purpose || '목적 없음') + ' / ' + memberProfileExtraFieldPolicyLabel(field);
            row.appendChild(privacyCell);

            var actionCell = document.createElement('td');
            actionCell.className = 'admin-table-actions-cell';
            var actionGroup = document.createElement('div');
            actionGroup.className = 'admin-row-actions';
            [
                ['edit', '수정', 'edit'],
                ['remove', '제거', 'delete']
            ].forEach(function (action) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = action[0] === 'remove' ? 'btn btn-sm btn-icon btn-outline-danger' : 'btn btn-sm btn-icon btn-solid-light';
                button.setAttribute('aria-label', action[1]);
                button.setAttribute('title', action[1]);
                button.setAttribute('data-member-profile-extra-field-action', action[0]);
                button.setAttribute('data-member-profile-extra-field-index-value', String(index));
                button.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">' + action[2] + '</span>';
                actionGroup.appendChild(button);
            });
            actionCell.appendChild(actionGroup);
            row.appendChild(actionCell);

            list.appendChild(row);
        });
    }

    function memberProfileExtraFieldInput(modal, name) {
        return modal ? modal.querySelector('[data-member-profile-extra-field-input="' + name + '"]') : null;
    }

    function memberProfileExtraFieldSetModal(modal, field, index) {
        if (!modal) {
            return;
        }
        field = field || {
            key: '',
            label: '',
            type: 'text',
            required: false,
            options: [],
            visibility: 'public',
            show_on_profile: true,
            show_in_admin: false,
            privacy_purpose: '',
            export_policy: 'include',
            cleanup_policy: 'anonymize'
        };
        var indexInput = modal.querySelector('[data-member-profile-extra-field-index]');
        var title = modal.querySelector('[data-member-profile-extra-field-modal-title]');
        if (indexInput) {
            indexInput.value = typeof index === 'number' && index >= 0 ? String(index) : '';
        }
        if (title) {
            title.textContent = typeof index === 'number' && index >= 0 ? '추가 프로필 항목 수정' : '추가 프로필 항목 추가';
        }
        memberProfileExtraFieldInput(modal, 'key').value = field.key || '';
        memberProfileExtraFieldInput(modal, 'label').value = field.label || '';
        memberProfileExtraFieldInput(modal, 'type').value = memberProfileExtraFieldAllowedType(field.type || 'text');
        memberProfileExtraFieldInput(modal, 'options').value = Array.isArray(field.options) ? field.options.join("\n") : '';
        memberProfileExtraFieldInput(modal, 'required').checked = !!field.required;
        memberProfileExtraFieldInput(modal, 'visibility').value = field.visibility === 'admin' ? 'admin' : 'public';
        memberProfileExtraFieldInput(modal, 'show_on_profile').checked = Object.prototype.hasOwnProperty.call(field, 'show_on_profile') ? !!field.show_on_profile : true;
        memberProfileExtraFieldInput(modal, 'show_in_admin').checked = !!field.show_in_admin;
        memberProfileExtraFieldInput(modal, 'privacy_purpose').value = field.privacy_purpose || '';
        memberProfileExtraFieldInput(modal, 'export_policy').value = field.export_policy === 'exclude' ? 'exclude' : 'include';
        memberProfileExtraFieldInput(modal, 'cleanup_policy').value = field.cleanup_policy === 'retain' ? 'retain' : 'anonymize';
        memberProfileExtraFieldSyncOptions(modal);
    }

    function memberProfileExtraFieldSyncOptions(modal) {
        var type = memberProfileExtraFieldInput(modal, 'type');
        var options = memberProfileExtraFieldInput(modal, 'options');
        var requiredLabel = modal ? modal.querySelector('[data-member-profile-extra-field-options-required]') : null;
        var isSelect = type && type.value === 'select';
        if (options) {
            options.required = !!isSelect;
            options.closest('.form-row').hidden = !isSelect;
        }
        if (requiredLabel) {
            requiredLabel.hidden = !isSelect;
        }
    }

    function memberProfileExtraFieldCollect(modal, definitions) {
        var keyInput = memberProfileExtraFieldInput(modal, 'key');
        var labelInput = memberProfileExtraFieldInput(modal, 'label');
        var typeInput = memberProfileExtraFieldInput(modal, 'type');
        var optionsInput = memberProfileExtraFieldInput(modal, 'options');
        var indexInput = modal ? modal.querySelector('[data-member-profile-extra-field-index]') : null;
        var index = indexInput && indexInput.value !== '' ? parseInt(indexInput.value, 10) : -1;
        [keyInput, labelInput, typeInput, optionsInput].forEach(function (input) {
            if (input && typeof input.setCustomValidity === 'function') {
                input.setCustomValidity('');
            }
        });
        if (!keyInput || !labelInput || !typeInput || !optionsInput) {
            return null;
        }
        keyInput.value = keyInput.value.trim().toLowerCase();
        labelInput.value = labelInput.value.replace(/\s+/g, ' ').trim();
        var duplicate = definitions.some(function (field, fieldIndex) {
            return field.key === keyInput.value && fieldIndex !== index;
        });
        if (duplicate) {
            keyInput.setCustomValidity('이미 사용 중인 Key입니다.');
        }
        var type = memberProfileExtraFieldAllowedType(typeInput.value);
        var options = optionsInput.value.split(/\r?\n/).map(function (value) {
            return value.replace(/\s+/g, ' ').trim().slice(0, 120);
        }).filter(function (value, optionIndex, values) {
            return value !== '' && values.indexOf(value) === optionIndex;
        }).slice(0, 50);
        if (type === 'select' && options.length === 0) {
            optionsInput.setCustomValidity('선택지는 하나 이상 입력해 주세요.');
        }
        var controls = [keyInput, labelInput, typeInput, optionsInput];
        for (var i = 0; i < controls.length; i++) {
            if (controls[i] && typeof controls[i].checkValidity === 'function' && !controls[i].checkValidity()) {
                controls[i].reportValidity();
                return null;
            }
        }
        return {
            index: index,
            field: {
                key: keyInput.value,
                label: labelInput.value,
                type: type,
                required: !!memberProfileExtraFieldInput(modal, 'required').checked,
                options: type === 'select' ? options : [],
                visibility: memberProfileExtraFieldInput(modal, 'visibility').value === 'admin' ? 'admin' : 'public',
                show_on_profile: !!memberProfileExtraFieldInput(modal, 'show_on_profile').checked,
                show_in_admin: !!memberProfileExtraFieldInput(modal, 'show_in_admin').checked,
                privacy_purpose: memberProfileExtraFieldInput(modal, 'privacy_purpose').value.trim(),
                export_policy: memberProfileExtraFieldInput(modal, 'export_policy').value === 'exclude' ? 'exclude' : 'include',
                cleanup_policy: memberProfileExtraFieldInput(modal, 'cleanup_policy').value === 'retain' ? 'retain' : 'anonymize'
            }
        };
    }

    function memberProfileExtraFieldInit() {
        var root = document.querySelector('[data-member-profile-extra-fields-builder]');
        var modal = document.querySelector('[data-member-profile-extra-field-modal]');
        var textarea = root ? root.querySelector('[data-member-profile-extra-fields-json]') : null;
        if (!root || !modal || !textarea) {
            return;
        }
        memberProfileExtraFieldRender(root);
        textarea.addEventListener('change', function () {
            memberProfileExtraFieldRender(root);
        });
        var type = memberProfileExtraFieldInput(modal, 'type');
        if (type) {
            type.addEventListener('change', function () {
                memberProfileExtraFieldSyncOptions(modal);
            });
        }
    }

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

    memberProfileExtraFieldInit();

    document.addEventListener('click', function (event) {
        var extraFieldAdd = event.target.closest && event.target.closest('[data-member-profile-extra-field-add]');
        if (extraFieldAdd) {
            var extraFieldModal = document.querySelector('[data-member-profile-extra-field-modal]');
            var extraFieldRootForAdd = document.querySelector('[data-member-profile-extra-fields-builder]');
            var extraFieldTextareaForAdd = extraFieldRootForAdd ? extraFieldRootForAdd.querySelector('[data-member-profile-extra-fields-json]') : null;
            var extraFieldDefinitionsForAdd = memberProfileExtraFieldParse(extraFieldTextareaForAdd);
            memberProfileExtraFieldSetModal(extraFieldModal, {
                key: memberProfileExtraFieldRandomKey(extraFieldDefinitionsForAdd),
                label: '',
                type: 'text',
                required: false,
                options: [],
                visibility: 'public',
                show_on_profile: true,
                show_in_admin: false,
                privacy_purpose: '',
                export_policy: 'include',
                cleanup_policy: 'anonymize'
            }, -1);
            return;
        }

        var extraFieldAction = event.target.closest && event.target.closest('[data-member-profile-extra-field-action]');
        if (extraFieldAction) {
            event.preventDefault();
            var extraFieldRoot = document.querySelector('[data-member-profile-extra-fields-builder]');
            var extraFieldTextarea = extraFieldRoot ? extraFieldRoot.querySelector('[data-member-profile-extra-fields-json]') : null;
            var extraFieldDefinitions = memberProfileExtraFieldParse(extraFieldTextarea);
            var extraFieldIndex = parseInt(extraFieldAction.getAttribute('data-member-profile-extra-field-index-value') || '-1', 10);
            var extraFieldActionName = extraFieldAction.getAttribute('data-member-profile-extra-field-action') || '';
            if (extraFieldIndex < 0 || extraFieldIndex >= extraFieldDefinitions.length) {
                return;
            }
            if (extraFieldActionName === 'edit') {
                var openButton = document.querySelector('[data-member-profile-extra-field-add]');
                if (openButton) {
                    openButton.click();
                }
                memberProfileExtraFieldSetModal(document.querySelector('[data-member-profile-extra-field-modal]'), extraFieldDefinitions[extraFieldIndex], extraFieldIndex);
                return;
            }
            if (extraFieldActionName === 'up' && extraFieldIndex > 0) {
                var previous = extraFieldDefinitions[extraFieldIndex - 1];
                extraFieldDefinitions[extraFieldIndex - 1] = extraFieldDefinitions[extraFieldIndex];
                extraFieldDefinitions[extraFieldIndex] = previous;
            } else if (extraFieldActionName === 'down' && extraFieldIndex < extraFieldDefinitions.length - 1) {
                var next = extraFieldDefinitions[extraFieldIndex + 1];
                extraFieldDefinitions[extraFieldIndex + 1] = extraFieldDefinitions[extraFieldIndex];
                extraFieldDefinitions[extraFieldIndex] = next;
            } else if (extraFieldActionName === 'remove') {
                extraFieldDefinitions.splice(extraFieldIndex, 1);
            }
            memberProfileExtraFieldWrite(extraFieldRoot, extraFieldDefinitions);
            memberProfileExtraFieldRender(extraFieldRoot);
            return;
        }

        var extraFieldSave = event.target.closest && event.target.closest('[data-member-profile-extra-field-save]');
        if (extraFieldSave) {
            event.preventDefault();
            var saveRoot = document.querySelector('[data-member-profile-extra-fields-builder]');
            var saveTextarea = saveRoot ? saveRoot.querySelector('[data-member-profile-extra-fields-json]') : null;
            var saveModal = document.querySelector('[data-member-profile-extra-field-modal]');
            var saveDefinitions = memberProfileExtraFieldParse(saveTextarea);
            var collected = memberProfileExtraFieldCollect(saveModal, saveDefinitions);
            if (!collected) {
                return;
            }
            if (collected.index >= 0 && collected.index < saveDefinitions.length) {
                saveDefinitions[collected.index] = collected.field;
            } else {
                saveDefinitions.push(collected.field);
            }
            memberProfileExtraFieldWrite(saveRoot, saveDefinitions);
            memberProfileExtraFieldRender(saveRoot);
            var closeButton = saveModal ? saveModal.querySelector('.modal-close') : null;
            if (closeButton) {
                closeButton.click();
            }
        }
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
