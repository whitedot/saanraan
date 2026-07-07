<?php

$adminPageTitle = '자동등록방지 설정';
$adminPageSubtitle = '';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$antispamSectionNavItems = [
    'antispam-section-policy' => '기본 정책',
    'antispam-section-targets' => '적용 대상',
    'antispam-section-challenge' => '검증 방식',
    'antispam-section-provider-common' => '외부 검사 공통',
];
$antispamSelectedChallengeType = (string) $settings['challenge_type'];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="자동등록방지 설정 섹션">
    <?php $antispamSectionNavIndex = 0; ?>
    <?php foreach ($antispamSectionNavItems as $antispamSectionId => $antispamSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $antispamSectionId); ?>" class="tab-trigger-underline-justified<?php echo $antispamSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $antispamSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $antispamSectionLabel); ?>
        </a>
        <?php $antispamSectionNavIndex++; ?>
    <?php } ?>
</nav>

<form method="post" action="<?php echo sr_e(sr_url('/admin/antispam/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section id="antispam-section-policy" class="card" data-admin-section-anchor>
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

    <section id="antispam-section-targets" class="card" data-admin-section-anchor>
        <h2>적용 대상</h2>
        <?php foreach ($targetOptions as $surfaceKey => $targetOption) { ?>
            <?php
            $surfaceSettingKey = sr_antispam_surface_setting_key((string) $surfaceKey);
            $surfaceLabel = (string) ($targetOption['label'] ?? $surfaceKey);
            ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $surfaceSettingKey); ?>"><?php echo sr_e($surfaceLabel); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('antispam_admin_' . $surfaceSettingKey, $surfaceSettingKey, $modeOptions, (string) $settings[$surfaceSettingKey], true); ?>
                </div>
            </div>
        <?php } ?>
    </section>

    <section id="antispam-section-challenge" class="card" data-admin-section-anchor>
        <h2>검증 방식</h2>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_challenge_type">검증 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="antispam_admin_challenge_type" name="challenge_type" class="form-select" required data-antispam-challenge-type-select>
                    <?php foreach ($challengeTypeOptions as $typeKey => $typeLabel) { ?>
                        <option value="<?php echo sr_e((string) $typeKey); ?>"<?php echo $antispamSelectedChallengeType === (string) $typeKey ? ' selected' : ''; ?>><?php echo sr_e((string) $typeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-grid" data-antispam-challenge-panel="math"<?php echo $antispamSelectedChallengeType === 'math' ? '' : ' hidden'; ?>>
            <div class="form-row">
                <span class="form-label">산술 문제</span>
                <div class="form-field">
                    <p class="admin-form-static">외부 CAPTCHA 연결 없이 짧은 산술 문제로 검증합니다.</p>
                </div>
            </div>
        </div>

        <?php foreach ($providerOptions as $providerKey => $provider) { ?>
            <?php
            $siteKeySetting = (string) $provider['site_key_setting'];
            $secretKeySetting = (string) $provider['secret_key_setting'];
            $providerSelected = $antispamSelectedChallengeType === (string) $providerKey;
            $providerControlDisabled = $providerSelected ? '' : ' disabled';
            ?>
            <div class="form-grid" data-antispam-challenge-panel="<?php echo sr_e((string) $providerKey); ?>"<?php echo $providerSelected ? '' : ' hidden'; ?>>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $providerKey . '_site_key'); ?>"><?php echo sr_e((string) $provider['label']); ?> 사이트 키</label>
                    <div class="form-field">
                        <input id="<?php echo sr_e('antispam_admin_' . $providerKey . '_site_key'); ?>" type="text" name="<?php echo sr_e($siteKeySetting); ?>" maxlength="255" value="<?php echo sr_e((string) ($settings[$siteKeySetting] ?? '')); ?>" class="form-input form-control-full" data-antispam-challenge-control<?php echo $providerControlDisabled; ?>>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $providerKey . '_secret_key'); ?>"><?php echo sr_e((string) $provider['label']); ?> 비밀 키</label>
                    <div class="form-field">
                        <input id="<?php echo sr_e('antispam_admin_' . $providerKey . '_secret_key'); ?>" type="password" name="<?php echo sr_e($secretKeySetting); ?>" maxlength="255" value="" placeholder="<?php echo sr_e(sr_antispam_secret_display((string) ($settings[$secretKeySetting] ?? ''))); ?>" class="form-input form-control-full" autocomplete="new-password" data-antispam-challenge-control<?php echo $providerControlDisabled; ?>>
                        <p class="form-help">비워 두면 기존 비밀 키를 유지합니다.</p>
                    </div>
                </div>
                <?php if ((string) ($provider['score_setting'] ?? '') !== '') { ?>
                    <?php $scoreSetting = (string) $provider['score_setting']; ?>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e('antispam_admin_' . $scoreSetting); ?>"><?php echo sr_e((string) $provider['label']); ?> 최소 점수 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="<?php echo sr_e('antispam_admin_' . $scoreSetting); ?>" type="number" name="<?php echo sr_e($scoreSetting); ?>" min="0" max="1" step="0.1" value="<?php echo sr_e((string) ($settings[$scoreSetting] ?? '0.5')); ?>" required class="form-input" data-antispam-challenge-control<?php echo $providerControlDisabled; ?>>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

    </section>

    <section id="antispam-section-provider-common" class="card" data-admin-section-anchor>
        <h2>외부 검사 공통</h2>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_timeout_seconds">외부 검사 제한 시간 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_provider_timeout_seconds" type="number" name="provider_timeout_seconds" min="1" max="10" value="<?php echo sr_e((string) $settings['provider_timeout_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_failure_policy">외부 검사 실패 시 처리 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="antispam_admin_provider_failure_policy" name="provider_failure_policy" class="form-select" required>
                    <option value="fail_closed"<?php echo (string) $settings['provider_failure_policy'] === 'fail_closed' ? ' selected' : ''; ?>>검증 실패</option>
                    <option value="fallback_math"<?php echo (string) $settings['provider_failure_policy'] === 'fallback_math' ? ' selected' : ''; ?>>산술 문제로 대체</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_verify_remote_ip_enabled">원격 IP 전달</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_verify_remote_ip_enabled">
                    <input id="antispam_admin_verify_remote_ip_enabled" type="checkbox" name="verify_remote_ip_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['verify_remote_ip_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('포함'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_action_check_enabled">외부 검사 동작 확인</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_action_check_enabled">
                    <input id="antispam_admin_provider_action_check_enabled" type="checkbox" name="provider_action_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_action_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_provider_hostname_check_enabled">외부 검사 호스트 이름 확인</label>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_hostname_check_enabled">
                    <input id="antispam_admin_provider_hostname_check_enabled" type="checkbox" name="provider_hostname_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_hostname_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var select = document.querySelector('[data-antispam-challenge-type-select]');
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-antispam-challenge-panel]'));
    if (!select || panels.length === 0) {
        return;
    }

    function syncChallengePanels() {
        var selectedValue = select.value || 'math';
        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-antispam-challenge-panel') === selectedValue;
            panel.hidden = !active;
            Array.prototype.slice.call(panel.querySelectorAll('[data-antispam-challenge-control]')).forEach(function (control) {
                control.disabled = !active;
            });
        });
    }

    select.addEventListener('change', syncChallengePanels);
    syncChallengePanels();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
