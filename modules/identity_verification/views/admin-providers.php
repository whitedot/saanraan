<?php

$adminPageTitle = '본인확인 환경설정';
$identityVerificationHelpOpenLabel = '도움말 보기';
$identityVerificationHelpButtonHtml = static function (string $label, string $modalId) use ($identityVerificationHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $identityVerificationHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$identityVerificationHelp = [
    'enabled' => [
        'id' => 'identity-verification-help-enabled',
        'title' => '본인확인 사용 도움말',
        'body' => '<p>사용하면 회원가입, 성인확인, 계정 보안 작업처럼 본인확인이 필요한 화면에서 아래 서비스를 호출할 수 있습니다.</p>'
            . '<p>기본 본인확인 서비스를 선택하고 해당 서비스 카드의 사용도 켜야 합니다. 사용을 끄면 본인확인을 새로 시작할 수 없습니다.</p>',
    ],
    'default_provider' => [
        'id' => 'identity-verification-help-default-provider',
        'title' => '기본 본인확인 서비스 도움말',
        'body' => '<p>요청한 화면이 특정 서비스를 지정하지 않았을 때 먼저 사용할 본인확인 서비스입니다.</p>'
            . '<p>기본 서비스로 선택하면 해당 서비스 카드의 사용이 자동으로 켜집니다. 다만 사용처에 맞는 인증 방식을 지원하지 않으면 다른 사용 가능한 서비스를 선택할 수 있습니다.</p>',
    ],
    'attempt_ttl' => [
        'id' => 'identity-verification-help-attempt-ttl',
        'title' => '본인확인 대기 시간 도움말',
        'body' => '<p>회원이 본인확인을 시작한 뒤 외부 서비스에서 인증을 마치고 사이트로 돌아올 때까지 요청을 유효하게 둘 시간입니다.</p>'
            . '<p>시간이 지나면 이전 요청은 만료되어 사용할 수 없으며 본인확인을 처음부터 다시 시작해야 합니다. 60초부터 3,600초까지 입력합니다.</p>',
    ],
    'result_valid_days' => [
        'id' => 'identity-verification-help-result-valid-days',
        'title' => '본인확인 결과 유효 기간 도움말',
        'body' => '<p>본인확인에 성공해 새로 저장되는 결과에 적용할 공통 만료 기간입니다. 만료된 결과는 유효한 본인확인 결과가 필요한 후속 처리에서 다시 사용할 수 없습니다.</p>'
            . '<p>0일을 입력하면 공통 만료일을 두지 않습니다. 설정을 바꿔도 이미 저장된 결과의 만료일은 변경되지 않습니다.</p>',
    ],
    'require_https' => [
        'id' => 'identity-verification-help-require-https',
        'title' => '보안 연결만 허용 도움말',
        'body' => '<p>사용하면 사이트 기본 URL이 HTTPS로 시작할 때만 본인확인을 시작할 수 있습니다. 외부 서비스와 주고받는 본인확인 정보를 보호하기 위한 설정입니다.</p>'
            . '<p>로컬 개발 주소는 예외로 허용됩니다. 운영 사이트에서는 이 설정을 켜고 사이트 기본 URL과 인증서가 올바른지 확인하세요.</p>',
    ],
    'birth_date' => [
        'id' => 'identity-verification-help-birth-date',
        'title' => '확인된 생년월일 반영 도움말',
        'body' => '<p>사용하면 회원가입 본인확인 결과의 생년월일과 성인 여부를 회원 정보에 반영하고, 회원이 가입 화면에서 임의로 바꾸지 못하게 합니다.</p>'
            . '<p>성인확인이 필요한 정책은 이 설정을 켰을 때만 저장할 수 있습니다. 외부 서비스가 생년월일이나 성인 여부를 보내지 않으면 해당 값은 반영되지 않습니다.</p>',
    ],
    'environment' => [
        'id' => 'identity-verification-help-environment',
        'title' => '연동 환경 도움말',
        'body' => '<p>테스트는 실제 운영 전 연동을 점검하는 환경이고, 운영은 실제 회원의 본인확인에 사용하는 환경입니다.</p>'
            . '<p>환경에 따라 필요한 연결 정보가 달라질 수 있습니다. 운영으로 바꾸면 해당 서비스에서 발급받은 운영용 값을 입력해야 하며, 테스트용 값과 섞어 쓰지 마세요.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" class="admin-form ui-form-theme identity-verification-admin-form" data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>

    <section id="identity-verification-section-basic" class="card" data-admin-section-anchor>
        <h2>기본 설정</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('identity_verification_enabled', '본인확인', $identityVerificationHelp['enabled']['id'], $identityVerificationHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="identity_verification_enabled">
                    <input id="identity_verification_enabled" type="checkbox" name="enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
                <p class="form-help">본인확인이 필요한 회원 화면에서 아래 서비스를 사용할 수 있게 합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label form-label-help"><?php echo $identityVerificationHelpButtonHtml('기본 본인확인 서비스', $identityVerificationHelp['default_provider']['id']); ?><label for="identity_verification_default_provider_key"><?php echo sr_e('기본 본인확인 서비스'); ?> <span class="sr-required-label" data-identity-default-provider-required<?php echo !empty($settings['enabled']) ? '' : ' hidden'; ?>>(필수)</span></label></div>
            <div class="form-field">
                <select id="identity_verification_default_provider_key" name="default_provider_key" class="form-select" data-identity-default-provider-select>
                    <option value=""><?php echo sr_e('선택'); ?></option>
                    <?php foreach ($providers as $providerKey => $provider) { ?>
                        <option value="<?php echo sr_e((string) $providerKey); ?>"<?php echo (string) $settings['default_provider_key'] === (string) $providerKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($provider['display_name'] ?? $providerKey)); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">특정 서비스가 지정되지 않은 요청에 먼저 사용합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('identity_verification_attempt_ttl_seconds', '본인확인 대기 시간', $identityVerificationHelp['attempt_ttl']['id'], $identityVerificationHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="identity_verification_attempt_ttl_seconds" class="form-input" type="number" name="attempt_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['attempt_ttl_seconds']); ?>" required>
                    <span class="input-group-text"><?php echo sr_e('초'); ?></span>
                </div>
                <p class="form-help">외부 서비스에서 인증을 마치고 돌아올 때까지 기다리는 시간입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('identity_verification_result_valid_days', '본인확인 결과 유효 기간', $identityVerificationHelp['result_valid_days']['id'], $identityVerificationHelpOpenLabel, true); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="identity_verification_result_valid_days" class="form-input" type="number" name="result_valid_days" min="0" max="3650" value="<?php echo sr_e((string) $settings['result_valid_days']); ?>" required>
                    <span class="input-group-text"><?php echo sr_e('일'); ?></span>
                </div>
                <p class="form-help">0일이면 새로 저장하는 결과에 공통 만료일을 두지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('identity_verification_require_https', '보안 연결만 허용', $identityVerificationHelp['require_https']['id'], $identityVerificationHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="identity_verification_require_https">
                    <input id="identity_verification_require_https" type="checkbox" name="require_https" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['require_https']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('요구'); ?>
                </label>
                <p class="form-help">운영 사이트에서는 HTTPS 주소일 때만 본인확인을 시작합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('identity_verification_use_birth_date', '확인된 생년월일 반영', $identityVerificationHelp['birth_date']['id'], $identityVerificationHelpOpenLabel); ?>
            <div class="form-field">
                <label class="form-check form-label" for="identity_verification_use_birth_date">
                    <input id="identity_verification_use_birth_date" type="checkbox" name="use_birth_date" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['use_birth_date']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
                <p class="form-help">회원가입 때 확인된 생년월일과 성인 여부를 회원 정보에 반영합니다.</p>
            </div>
        </div>
    </section>

    <?php $identityProviderCount = 0; ?>
    <?php foreach ($providers as $providerKey => $provider) { ?>
        <?php
        $identityProviderCount++;
        $providerKey = (string) $providerKey;
        $providerLabel = (string) ($provider['display_name'] ?? $providerKey);
        $providerUsageHelp = trim((string) ($provider['usage_help'] ?? ''));
        $enabledKey = sr_identity_verification_setting_key($providerKey, 'enabled');
        $environmentKey = sr_identity_verification_setting_key($providerKey, 'environment');
        $providerEnabled = !empty($provider['enabled']);
        $providerEnvironment = (string) ($provider['environment'] ?? 'test');
        ?>
        <section id="<?php echo sr_e('identity-verification-section-provider-' . str_replace('_', '-', $providerKey)); ?>" class="card" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e($providerLabel); ?></h2>
                <label class="form-check" for="<?php echo sr_e('identity_provider_' . $providerKey . '_enabled'); ?>">
                    <input id="<?php echo sr_e('identity_provider_' . $providerKey . '_enabled'); ?>" type="checkbox" name="<?php echo sr_e($enabledKey); ?>" value="1" class="form-switch form-switch-light"<?php echo $providerEnabled ? ' checked' : ''; ?> data-identity-provider-toggle="<?php echo sr_e($providerKey); ?>">
                    <span class="sr-only"><?php echo sr_e($providerLabel . ' 사용'); ?></span>
                </label>
            </div>
            <?php if ($providerUsageHelp !== '') { ?>
                <p class="form-help"><?php echo sr_e($providerUsageHelp); ?></p>
            <?php } ?>
            <div class="form-row" data-identity-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label form-label-help"><?php echo $identityVerificationHelpButtonHtml($providerLabel . ' 연동 환경', $identityVerificationHelp['environment']['id']); ?><span><?php echo sr_e('연동 환경'); ?></span></span>
                <div class="form-field">
                    <?php
                    echo sr_admin_radio_toggle_group_html(
                        'identity_provider_' . $providerKey . '_environment',
                        $environmentKey,
                        ['test' => '테스트', 'production' => '운영'],
                        in_array($providerEnvironment, ['test', 'production'], true) ? $providerEnvironment : 'test',
                        false,
                        ' data-identity-provider-environment="' . sr_e($providerKey) . '"'
                    );
                    ?>
                    <p class="form-help">연동 점검은 테스트, 실제 회원의 본인확인은 운영을 선택합니다.</p>
                </div>
            </div>
            <?php foreach ((array) ($provider['settings_schema'] ?? []) as $settingKey => $definition) { ?>
                <?php
                if (!is_string($settingKey) || !is_array($definition)) {
                    continue;
                }
                $storedKey = sr_identity_verification_setting_key($providerKey, $settingKey);
                $isSecret = !empty($definition['secret']);
                $isRequired = !empty($definition['required']);
                $isRequiredForEnvironment = sr_identity_verification_provider_setting_required($definition, $providerEnvironment);
                $requiredEnvironments = [];
                foreach ((array) ($definition['required_environments'] ?? []) as $requiredEnvironment) {
                    $requiredEnvironment = is_scalar($requiredEnvironment) ? strtolower(trim((string) $requiredEnvironment)) : '';
                    if (in_array($requiredEnvironment, ['test', 'production'], true)) {
                        $requiredEnvironments[] = $requiredEnvironment;
                    }
                }
                $value = sr_identity_verification_provider_setting($provider, $settingKey);
                $inputId = 'identity_provider_' . $providerKey . '_' . $settingKey;
                $inputRequired = $providerEnabled && $isRequiredForEnvironment && (!$isSecret || $value === '');
                ?>
                <div class="form-row" data-identity-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                    <label class="form-label" for="<?php echo sr_e($inputId); ?>">
                        <?php echo sr_e((string) ($definition['label'] ?? $settingKey)); ?>
                        <?php if ($isRequired) { ?>
                            <span class="sr-required-label"<?php echo $providerEnabled && $isRequiredForEnvironment ? '' : ' hidden'; ?> data-identity-required-for="<?php echo sr_e($providerKey); ?>" data-identity-required-environments="<?php echo sr_e(implode(' ', array_values(array_unique($requiredEnvironments)))); ?>">(필수)</span>
                        <?php } ?>
                    </label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($inputId); ?>" class="form-input form-control-full" type="<?php echo $isSecret ? 'password' : 'text'; ?>" name="<?php echo sr_e($storedKey); ?>" maxlength="2000" value="<?php echo $isSecret ? '' : sr_e($value); ?>"<?php echo $inputRequired ? ' required' : ''; ?><?php echo $isSecret ? ' autocomplete="new-password"' : ' autocomplete="off"'; ?><?php echo $isRequired ? ' data-identity-required-provider="' . sr_e($providerKey) . '" data-identity-required-environments="' . sr_e(implode(' ', array_values(array_unique($requiredEnvironments)))) . '"' : ''; ?><?php echo $isSecret ? ' data-identity-secret-provider="' . sr_e($providerKey) . '" data-identity-has-stored-secret="' . ($value !== '' ? '1' : '0') . '"' : ''; ?>>
                        <?php if ($isSecret && $value !== '') { ?>
                            <p class="form-help">저장된 값이 있습니다. 변경할 때만 새 값을 입력하세요.</p>
                        <?php } elseif (!empty($definition['help'])) { ?>
                            <p class="form-help"><?php echo sr_e((string) $definition['help']); ?></p>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </section>
    <?php } ?>

    <?php if ($identityProviderCount < 1) { ?>
        <section id="identity-verification-section-providers-empty" class="card" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e('제공자 활성화'); ?></h2>
                <a class="btn btn-sm btn-solid-light" href="<?php echo sr_e(sr_url('/admin/modules')); ?>"><?php echo sr_e('모듈 화면'); ?></a>
            </div>
            <p class="admin-empty-state"><?php echo sr_e('활성화된 본인확인 서비스 모듈이 없습니다. KCP 또는 KG이니시스 모듈을 활성화하면 이 화면에 설정 카드가 표시됩니다.'); ?></p>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>

<?php foreach ($identityVerificationHelp as $identityVerificationHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $identityVerificationHelpModal['id'], (string) $identityVerificationHelpModal['title'], (string) $identityVerificationHelpModal['body']); ?>
<?php } ?>

<script>
var identityVerificationEnabled = document.getElementById('identity_verification_enabled');
var identityDefaultProviderSelect = document.querySelector('[data-identity-default-provider-select]');
var identityDefaultProviderRequired = document.querySelector('[data-identity-default-provider-required]');
function srIdentityVerificationSyncDefaultProviderRequired() {
    var enabled = identityVerificationEnabled ? identityVerificationEnabled.checked : false;
    if (identityDefaultProviderSelect) {
        identityDefaultProviderSelect.required = enabled;
    }
    if (identityDefaultProviderRequired) {
        identityDefaultProviderRequired.hidden = !enabled;
    }
}
if (identityVerificationEnabled) {
    identityVerificationEnabled.addEventListener('change', srIdentityVerificationSyncDefaultProviderRequired);
}
srIdentityVerificationSyncDefaultProviderRequired();
document.querySelectorAll('[data-identity-provider-toggle]').forEach(function (toggle) {
    var providerKey = toggle.getAttribute('data-identity-provider-toggle') || '';
    function providerEnvironment() {
        var checked = document.querySelector('[data-identity-provider-environment="' + providerKey + '"]:checked');
        return checked ? checked.value : 'test';
    }
    function requiredInCurrentEnvironment(element) {
        var environments = String(element.getAttribute('data-identity-required-environments') || '').trim();
        if (environments === '') {
            return true;
        }
        return environments.split(/\s+/).indexOf(providerEnvironment()) !== -1;
    }
    function syncProviderFields() {
        var defaultProviderSelect = document.querySelector('[data-identity-default-provider-select]');
        if (defaultProviderSelect && defaultProviderSelect.value === providerKey && !toggle.checked) {
            toggle.checked = true;
        }
        document.querySelectorAll('[data-identity-provider-field-row="' + providerKey + '"]').forEach(function (row) {
            row.hidden = !toggle.checked;
        });
        document.querySelectorAll('[data-identity-required-provider="' + providerKey + '"]').forEach(function (input) {
            var isSecret = input.hasAttribute('data-identity-secret-provider');
            var hasStoredSecret = input.getAttribute('data-identity-has-stored-secret') === '1';
            input.required = toggle.checked && requiredInCurrentEnvironment(input) && (!isSecret || !hasStoredSecret);
        });
        document.querySelectorAll('[data-identity-required-for="' + providerKey + '"]').forEach(function (label) {
            label.hidden = !toggle.checked || !requiredInCurrentEnvironment(label);
        });
    }
    toggle.addEventListener('change', syncProviderFields);
    document.querySelectorAll('[data-identity-provider-environment="' + providerKey + '"]').forEach(function (environmentInput) {
        environmentInput.addEventListener('change', syncProviderFields);
    });
    syncProviderFields();
});
if (identityDefaultProviderSelect) {
    identityDefaultProviderSelect.addEventListener('change', function () {
        var providerKey = identityDefaultProviderSelect.value || '';
        if (providerKey === '') {
            return;
        }
        var toggle = document.querySelector('[data-identity-provider-toggle="' + providerKey + '"]');
        if (toggle) {
            toggle.checked = true;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
