<?php

$adminPageTitle = '본인확인 환경설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" class="admin-form ui-form-theme identity-verification-admin-form" data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>

    <section id="identity-verification-section-basic" class="card" data-admin-section-anchor>
        <h2>기본 설정</h2>
        <div class="form-row">
            <label class="form-label" for="identity_verification_enabled"><?php echo sr_e('본인확인'); ?></label>
            <div class="form-field">
                <label class="form-check form-label" for="identity_verification_enabled">
                    <input id="identity_verification_enabled" type="checkbox" name="enabled" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['enabled']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('사용'); ?>
                </label>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="identity_verification_default_provider_key"><?php echo sr_e('기본 제공자'); ?> <span class="sr-required-label" data-identity-default-provider-required<?php echo !empty($settings['enabled']) ? '' : ' hidden'; ?>>(필수)</span></label>
            <div class="form-field">
                <select id="identity_verification_default_provider_key" name="default_provider_key" class="form-select" data-identity-default-provider-select>
                    <option value=""><?php echo sr_e('선택'); ?></option>
                    <?php foreach ($providers as $providerKey => $provider) { ?>
                        <option value="<?php echo sr_e((string) $providerKey); ?>"<?php echo (string) $settings['default_provider_key'] === (string) $providerKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($provider['display_name'] ?? $providerKey)); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="identity_verification_attempt_ttl_seconds"><?php echo sr_e('시도 유효 시간'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="identity_verification_attempt_ttl_seconds" class="form-input" type="number" name="attempt_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['attempt_ttl_seconds']); ?>" required>
                    <span class="input-group-text"><?php echo sr_e('초'); ?></span>
                </div>
                <p class="form-help">제공자 return을 기다리는 시간입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="identity_verification_result_valid_days"><?php echo sr_e('결과 기본 유효 기간'); ?> <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="identity_verification_result_valid_days" class="form-input" type="number" name="result_valid_days" min="0" max="3650" value="<?php echo sr_e((string) $settings['result_valid_days']); ?>" required>
                    <span class="input-group-text"><?php echo sr_e('일'); ?></span>
                </div>
                <p class="form-help">0이면 공통 만료일을 두지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="identity_verification_require_https"><?php echo sr_e('HTTPS 요구'); ?></label>
            <div class="form-field">
                <label class="form-check form-label" for="identity_verification_require_https">
                    <input id="identity_verification_require_https" type="checkbox" name="require_https" value="1" class="form-switch form-switch-light"<?php echo !empty($settings['require_https']) ? ' checked' : ''; ?>>
                    <?php echo sr_admin_choice_label_html('요구'); ?>
                </label>
            </div>
        </div>
    </section>

    <?php $identityProviderCount = 0; ?>
    <?php foreach ($providers as $providerKey => $provider) { ?>
        <?php
        $identityProviderCount++;
        $providerKey = (string) $providerKey;
        $providerLabel = (string) ($provider['display_name'] ?? $providerKey);
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
            <div class="form-row" data-identity-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label"><?php echo sr_e('제공자 키'); ?></span>
                <div class="form-field">
                    <p class="admin-form-static"><?php echo sr_e($providerKey); ?></p>
                </div>
            </div>
            <div class="form-row" data-identity-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                <span class="form-label"><?php echo sr_e('환경'); ?></span>
                <div class="form-field">
                    <?php
                    echo sr_admin_radio_toggle_group_html(
                        'identity_provider_' . $providerKey . '_environment',
                        $environmentKey,
                        ['test' => '테스트', 'production' => '운영'],
                        in_array($providerEnvironment, ['test', 'production'], true) ? $providerEnvironment : 'test',
                        false
                    );
                    ?>
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
                $value = sr_identity_verification_provider_setting($provider, $settingKey);
                $inputId = 'identity_provider_' . $providerKey . '_' . $settingKey;
                $inputRequired = $providerEnabled && $isRequired && (!$isSecret || $value === '');
                ?>
                <div class="form-row" data-identity-provider-field-row="<?php echo sr_e($providerKey); ?>"<?php echo $providerEnabled ? '' : ' hidden'; ?>>
                    <label class="form-label" for="<?php echo sr_e($inputId); ?>">
                        <?php echo sr_e((string) ($definition['label'] ?? $settingKey)); ?>
                        <?php if ($isRequired) { ?>
                            <span class="sr-required-label"<?php echo $providerEnabled ? '' : ' hidden'; ?> data-identity-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span>
                        <?php } ?>
                    </label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($inputId); ?>" class="form-input form-control-full" type="<?php echo $isSecret ? 'password' : 'text'; ?>" name="<?php echo sr_e($storedKey); ?>" maxlength="2000" value="<?php echo $isSecret ? '' : sr_e($value); ?>"<?php echo $inputRequired ? ' required' : ''; ?><?php echo $isSecret ? ' autocomplete="new-password"' : ' autocomplete="off"'; ?><?php echo $isRequired ? ' data-identity-required-provider="' . sr_e($providerKey) . '"' : ''; ?><?php echo $isSecret ? ' data-identity-secret-provider="' . sr_e($providerKey) . '" data-identity-has-stored-secret="' . ($value !== '' ? '1' : '0') . '"' : ''; ?>>
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
            <p class="admin-empty-state"><?php echo sr_e('설치되고 활성화된 본인확인 제공자 플러그인이 없습니다. KCP 또는 KG이니시스 제공자 모듈을 활성화하면 설정 카드가 표시됩니다.'); ?></p>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
    </div>
</form>

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
            input.required = toggle.checked && (!isSecret || !hasStoredSecret);
        });
        document.querySelectorAll('[data-identity-required-for="' + providerKey + '"]').forEach(function (label) {
            label.hidden = !toggle.checked;
        });
    }
    toggle.addEventListener('change', syncProviderFields);
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
