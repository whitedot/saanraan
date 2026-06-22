<?php

$adminPageTitle = '자동등록방지 설정';
$adminPageSubtitle = '';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/antispam/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="card">
        <h2>기본 정책</h2>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_enabled">사용</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_enabled">
                    <input id="antispam_admin_enabled" type="checkbox" name="enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_default_mode">기본 적용 모드 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('antispam_admin_default_mode', 'default_mode', $modeOptions, (string) $settings['default_mode'], true); ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_challenge_type">검증 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="antispam_admin_challenge_type" name="challenge_type" class="form-select" required>
                    <?php foreach ($challengeTypeOptions as $typeKey => $typeLabel) { ?>
                        <option value="<?php echo sr_e((string) $typeKey); ?>"<?php echo (string) $settings['challenge_type'] === (string) $typeKey ? ' selected' : ''; ?>><?php echo sr_e((string) $typeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_ttl_seconds">문제 유효 시간 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_ttl_seconds" type="number" name="ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_min_submit_seconds">최소 제출 시간 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_min_submit_seconds" type="number" name="min_submit_seconds" min="0" max="60" value="<?php echo sr_e((string) $settings['min_submit_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>적용 표면</h2>
        <?php foreach ([
            'surface_member_register' => '회원가입',
            'surface_community_post_guest' => '비회원 커뮤니티 글',
            'surface_community_comment_guest' => '비회원 커뮤니티 댓글',
        ] as $surfaceSettingKey => $surfaceLabel) { ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $surfaceSettingKey); ?>"><?php echo sr_e($surfaceLabel); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('antispam_admin_' . $surfaceSettingKey, $surfaceSettingKey, $modeOptions, (string) $settings[$surfaceSettingKey], true); ?>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <h2>외부 provider</h2>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_timeout_seconds">provider timeout <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_provider_timeout_seconds" type="number" name="provider_timeout_seconds" min="1" max="10" value="<?php echo sr_e((string) $settings['provider_timeout_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_failure_policy">provider 실패 정책 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="antispam_admin_provider_failure_policy" name="provider_failure_policy" class="form-select" required>
                    <option value="fail_closed"<?php echo (string) $settings['provider_failure_policy'] === 'fail_closed' ? ' selected' : ''; ?>>검증 실패</option>
                    <option value="fallback_math"<?php echo (string) $settings['provider_failure_policy'] === 'fallback_math' ? ' selected' : ''; ?>>산술 문제 fallback</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_verify_remote_ip_enabled">remote IP 전달</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_verify_remote_ip_enabled">
                    <input id="antispam_admin_verify_remote_ip_enabled" type="checkbox" name="verify_remote_ip_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['verify_remote_ip_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('포함'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_action_check_enabled">provider action 확인</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_action_check_enabled">
                    <input id="antispam_admin_provider_action_check_enabled" type="checkbox" name="provider_action_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_action_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_hostname_check_enabled">provider hostname 확인</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_hostname_check_enabled">
                    <input id="antispam_admin_provider_hostname_check_enabled" type="checkbox" name="provider_hostname_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_hostname_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
            </div>
        </div>
        <?php foreach ($providerOptions as $providerKey => $provider) { ?>
            <?php
            $siteKeySetting = (string) $provider['site_key_setting'];
            $secretKeySetting = (string) $provider['secret_key_setting'];
            ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $providerKey . '_site_key'); ?>"><?php echo sr_e((string) $provider['label']); ?> site key</label>
                <div class="form-field">
                    <input id="<?php echo sr_e('antispam_admin_' . $providerKey . '_site_key'); ?>" type="text" name="<?php echo sr_e($siteKeySetting); ?>" maxlength="255" value="<?php echo sr_e((string) ($settings[$siteKeySetting] ?? '')); ?>" class="form-input form-control-full">
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $providerKey . '_secret_key'); ?>"><?php echo sr_e((string) $provider['label']); ?> secret key</label>
                <div class="form-field">
                    <input id="<?php echo sr_e('antispam_admin_' . $providerKey . '_secret_key'); ?>" type="password" name="<?php echo sr_e($secretKeySetting); ?>" maxlength="255" value="" placeholder="<?php echo sr_e(sr_antispam_secret_display((string) ($settings[$secretKeySetting] ?? ''))); ?>" class="form-input form-control-full" autocomplete="new-password">
                    <p class="form-help">비워 두면 기존 secret을 유지합니다.</p>
                </div>
            </div>
            <?php if ((string) ($provider['score_setting'] ?? '') !== '') { ?>
                <?php $scoreSetting = (string) $provider['score_setting']; ?>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $scoreSetting); ?>"><?php echo sr_e((string) $provider['label']); ?> 최소 점수 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="<?php echo sr_e('antispam_admin_' . $scoreSetting); ?>" type="number" name="<?php echo sr_e($scoreSetting); ?>" min="0" max="1" step="0.1" value="<?php echo sr_e((string) ($settings[$scoreSetting] ?? '0.5')); ?>" required class="form-input">
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
