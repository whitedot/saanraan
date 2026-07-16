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
$antispamHelpOpenLabel = '도움말 보기';
$antispamHelp = [
    'mode' => [
        'id' => 'antispam-admin-help-mode',
        'title' => '적용 모드 도움말',
        'body' => '<p><strong>사용 안 함</strong>은 해당 화면에서 자동등록방지 검사를 하지 않습니다.</p>'
            . '<p><strong>비회원</strong>은 로그인하지 않은 방문자에게만 검사하고, <strong>항상</strong>은 로그인 여부와 관계없이 모든 제출을 검사합니다.</p>'
            . '<p>기본 적용 모드는 개별 설정이 없는 화면에 사용합니다. 아래 적용 대상에서 화면별 모드를 따로 정할 수 있습니다.</p>',
    ],
    'challenge_type' => [
        'id' => 'antispam-admin-help-challenge-type',
        'title' => '검증 방식 도움말',
        'body' => '<p><strong>산술 문제</strong>는 별도 서비스 연결 없이 사이트에서 간단한 계산 문제를 보여줍니다.</p>'
            . '<p>Turnstile, hCaptcha, reCAPTCHA 같은 외부 검사를 선택하면 해당 서비스에서 발급한 사이트 키와 비밀 키가 필요합니다. 외부 서비스의 스크립트가 방문자 화면에 로드될 수 있으므로 개인정보 처리 안내도 함께 확인하세요.</p>',
    ],
    'score' => [
        'id' => 'antispam-admin-help-score',
        'title' => '최소 통과 점수 도움말',
        'body' => '<p>외부 검사가 반환한 점수가 이 값 이상일 때 통과시킵니다. 0에서 1 사이로 입력하며, 값이 높을수록 자동 제출을 더 엄격하게 차단합니다.</p>'
            . '<p>너무 높게 설정하면 정상 방문자의 제출도 거절될 수 있습니다. 운영 중인 서비스의 점수 분포를 확인한 뒤 조정하세요.</p>',
    ],
    'failure_policy' => [
        'id' => 'antispam-admin-help-failure-policy',
        'title' => '외부 검사 장애 처리 도움말',
        'body' => '<p><strong>검증 실패</strong>는 외부 검사 서비스에 연결할 수 없을 때 제출을 거절합니다.</p>'
            . '<p><strong>산술 문제로 대체</strong>는 외부 서비스 장애가 확인된 경우에만 화면에 미리 표시한 예비 산술 문제로 검사합니다. 토큰 누락, 점수 부족, 사이트 정보 불일치 같은 실제 검증 실패를 산술 문제로 우회하지는 않습니다.</p>',
    ],
    'remote_ip' => [
        'id' => 'antispam-admin-help-remote-ip',
        'title' => '방문자 IP 전달 도움말',
        'body' => '<p>외부 검사 서비스가 위험도를 판단할 때 참고하도록 제출한 방문자의 IP 주소를 함께 보냅니다.</p>'
            . '<p>사용하면 외부 업체로 개인정보가 전달될 수 있습니다. 사이트의 개인정보 처리 안내와 외부 처리자 관리 기준을 확인한 뒤 켜세요.</p>',
    ],
    'action_check' => [
        'id' => 'antispam-admin-help-action-check',
        'title' => '검사 목적 일치 확인 도움말',
        'body' => '<p>외부 검사 결과에 포함된 작업 이름이 현재 제출 폼과 같은지 확인합니다. 다른 화면에서 발급된 검사 결과를 재사용하는 공격을 줄이는 설정입니다.</p>'
            . '<p>외부 서비스가 작업 이름을 보내지 않는 경우에는 이 항목만으로 제출을 거절하지 않습니다.</p>',
    ],
    'hostname_check' => [
        'id' => 'antispam-admin-help-hostname-check',
        'title' => '사이트 주소 일치 확인 도움말',
        'body' => '<p>외부 검사 결과에 포함된 사이트 주소가 현재 사이트 주소와 같은지 확인합니다. 다른 사이트에서 발급된 검사 결과의 재사용을 줄이는 설정입니다.</p>'
            . '<p>외부 서비스가 사이트 주소를 보내지 않는 경우에는 이 항목만으로 제출을 거절하지 않습니다.</p>',
    ],
];
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
                <p class="form-help">끄면 아래 적용 대상 설정과 관계없이 모든 자동등록방지 검사를 중단합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_default_mode', '기본 적용 모드', $antispamHelp['mode']['id'], $antispamHelpOpenLabel, true); ?>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('antispam_admin_default_mode', 'default_mode', $modeOptions, (string) $settings['default_mode'], true); ?>
                <p class="form-help">개별 설정이 없는 적용 대상에 사용할 기본값입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_ttl_seconds">문제 유효 시간 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_ttl_seconds" type="number" name="ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['ttl_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">문제가 표시된 뒤 답안을 제출할 수 있는 시간입니다. 60초부터 3,600초까지 입력합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="antispam_admin_min_submit_seconds">최소 제출 시간 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="antispam_admin_min_submit_seconds" type="number" name="min_submit_seconds" min="0" max="60" value="<?php echo sr_e((string) $settings['min_submit_seconds']); ?>" required class="form-input">
                    <span class="input-group-text">초</span>
                </div>
                <p class="form-help">폼을 연 뒤 이 시간보다 빨리 제출하면 자동 요청으로 보고 거절합니다. 사용하지 않으려면 0을 입력합니다.</p>
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
                <?php echo sr_admin_form_label_help_html('antispam_admin_' . $surfaceSettingKey, $surfaceLabel, $antispamHelp['mode']['id'], $antispamHelpOpenLabel, true); ?>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('antispam_admin_' . $surfaceSettingKey, $surfaceSettingKey, $modeOptions, (string) $settings[$surfaceSettingKey], true); ?>
                </div>
            </div>
        <?php } ?>
    </section>

    <section id="antispam-section-challenge" class="card" data-admin-section-anchor>
        <h2>검증 방식</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_challenge_type', '검증 방식', $antispamHelp['challenge_type']['id'], $antispamHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="antispam_admin_challenge_type" name="challenge_type" class="form-select" required data-antispam-challenge-type-select>
                    <?php foreach ($challengeTypeOptions as $typeKey => $typeLabel) { ?>
                        <option value="<?php echo sr_e((string) $typeKey); ?>"<?php echo $antispamSelectedChallengeType === (string) $typeKey ? ' selected' : ''; ?>><?php echo sr_e((string) $typeLabel); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">방문자에게 제시할 자동등록방지 검사 종류를 선택합니다.</p>
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
                        <p class="form-help"><?php echo sr_e((string) $provider['label']); ?> 관리 화면에서 발급한 공개용 사이트 키를 입력합니다.</p>
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
                        <?php echo sr_admin_form_label_help_html('antispam_admin_' . $scoreSetting, (string) $provider['label'] . ' 최소 통과 점수', $antispamHelp['score']['id'], $antispamHelpOpenLabel, true); ?>
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
                <p class="form-help">외부 검사 서비스의 응답을 기다릴 최대 시간입니다. 1초부터 10초까지 입력합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_provider_failure_policy', '외부 검사 장애 시 처리', $antispamHelp['failure_policy']['id'], $antispamHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="antispam_admin_provider_failure_policy" name="provider_failure_policy" class="form-select" required>
                    <option value="fail_closed"<?php echo (string) $settings['provider_failure_policy'] === 'fail_closed' ? ' selected' : ''; ?>>검증 실패</option>
                    <option value="fallback_math"<?php echo (string) $settings['provider_failure_policy'] === 'fallback_math' ? ' selected' : ''; ?>>산술 문제로 대체</option>
                </select>
                <p class="form-help">외부 서비스에 연결할 수 없을 때 제출을 막을지, 예비 산술 문제로 검사할지 정합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_verify_remote_ip_enabled', '방문자 IP 전달', $antispamHelp['remote_ip']['id'], $antispamHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_verify_remote_ip_enabled">
                    <input id="antispam_admin_verify_remote_ip_enabled" type="checkbox" name="verify_remote_ip_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['verify_remote_ip_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('포함'); ?>
                </label>
                <p class="form-help">외부 검사 요청에 제출한 방문자의 IP 주소를 포함합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_provider_action_check_enabled', '검사 목적 일치 확인', $antispamHelp['action_check']['id'], $antispamHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_action_check_enabled">
                    <input id="antispam_admin_provider_action_check_enabled" type="checkbox" name="provider_action_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_action_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
                <p class="form-help">검사 결과가 현재 제출 폼에서 발급된 것인지 확인합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('antispam_admin_provider_hostname_check_enabled', '사이트 주소 일치 확인', $antispamHelp['hostname_check']['id'], $antispamHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="antispam_admin_provider_hostname_check_enabled">
                    <input id="antispam_admin_provider_hostname_check_enabled" type="checkbox" name="provider_hostname_check_enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['provider_hostname_check_enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('확인'); ?>
                </label>
                <p class="form-help">검사 결과의 사이트 주소가 현재 사이트와 같은지 확인합니다.</p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($antispamHelp as $antispamHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $antispamHelpModal['id'], (string) $antispamHelpModal['title'], (string) $antispamHelpModal['body']); ?>
<?php } ?>

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
