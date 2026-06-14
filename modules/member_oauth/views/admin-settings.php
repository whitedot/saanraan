<?php

$pageTitle = sr_t('member_oauth::ui.settings');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="admin-section">
    <div class="admin-section-header">
        <div>
            <h1><?php echo sr_e($pageTitle); ?></h1>
            <p><?php echo sr_e('Callback URL: ' . sr_absolute_url($site ?? [], '/oauth/callback')); ?></p>
        </div>
    </div>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/member-oauth')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_settings">

        <section class="admin-card card">
            <h2><?php echo sr_e('기본 설정'); ?></h2>
            <div class="admin-form-row">
                <label class="form-label" for="member_oauth_mock_enabled"><?php echo sr_e('Mock provider'); ?></label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="member_oauth_mock_enabled">
                        <input id="member_oauth_mock_enabled" type="checkbox" name="mock_enabled" value="1" class="form-switch form-choice-dark"<?php echo !empty($settings['mock_enabled']) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('개발/검증용 mock provider 사용'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_oauth_mock_label"><?php echo sr_e('Mock provider 라벨'); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="member_oauth_mock_label" type="text" name="mock_label" maxlength="80" value="<?php echo sr_e((string) $settings['mock_label']); ?>" required class="form-input form-control-full">
                    <p class="admin-form-help">로그인 화면의 mock provider 버튼에 표시됩니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_oauth_state_ttl_seconds"><?php echo sr_e('State 유효 시간'); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <div class="input-group admin-input-unit">
                        <input id="member_oauth_state_ttl_seconds" type="number" name="state_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['state_ttl_seconds']); ?>" required class="form-input">
                        <span class="input-group-text">초</span>
                    </div>
                    <p class="admin-form-help">provider 로그인/연결 callback을 기다리는 시간입니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_oauth_completion_ttl_seconds"><?php echo sr_e('가입 완료 유효 시간'); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <div class="input-group admin-input-unit">
                        <input id="member_oauth_completion_ttl_seconds" type="number" name="completion_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['completion_ttl_seconds']); ?>" required class="form-input">
                        <span class="input-group-text">초</span>
                    </div>
                    <p class="admin-form-help">OAuth 신규 가입 completion state를 유지하는 시간입니다.</p>
                </div>
            </div>
        </section>

        <section class="admin-card card">
            <h2><?php echo sr_e('외부 provider'); ?></h2>
            <?php $externalProviderCount = 0; ?>
            <?php foreach ($providers as $provider) { ?>
                <?php if (!empty($provider['mock'])) { continue; } ?>
                <?php
                $externalProviderCount++;
                $providerKey = (string) $provider['provider_key'];
                $enabledKey = sr_member_oauth_provider_setting_key($providerKey, 'enabled');
                $labelKey = sr_member_oauth_provider_setting_key($providerKey, 'label');
                $clientIdKey = sr_member_oauth_provider_setting_key($providerKey, 'client_id');
                $secretKey = sr_member_oauth_provider_setting_key($providerKey, 'client_secret');
                $scopeKey = sr_member_oauth_provider_setting_key($providerKey, 'scope');
                $sortOrderKey = sr_member_oauth_provider_setting_key($providerKey, 'sort_order');
                ?>
                <fieldset class="admin-fieldset">
                    <legend><?php echo sr_e($providerKey); ?></legend>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_enabled'); ?>"><?php echo sr_e('사용'); ?></label>
                        <div class="admin-form-field">
                            <label class="admin-form-check form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_enabled'); ?>">
                                <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_enabled'); ?>" type="checkbox" name="<?php echo sr_e($enabledKey); ?>" value="1" class="form-switch form-choice-dark"<?php echo !empty($provider['enabled']) ? ' checked' : ''; ?>>
                                <?php echo sr_admin_choice_label_html('로그인 화면에 provider 표시'); ?>
                            </label>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>"><?php echo sr_e('라벨'); ?> <span class="sr-required-label"<?php echo empty($provider['enabled']) ? ' hidden' : ''; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_label'); ?>" type="text" name="<?php echo sr_e($labelKey); ?>" maxlength="80" value="<?php echo sr_e((string) ($provider['label'] ?? $providerKey)); ?>"<?php echo !empty($provider['enabled']) ? ' required' : ''; ?> class="form-input form-control-full" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>"><?php echo sr_e('Client ID'); ?> <span class="sr-required-label"<?php echo empty($provider['enabled']) ? ' hidden' : ''; ?> data-oauth-required-for="<?php echo sr_e($providerKey); ?>">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_id'); ?>" type="text" name="<?php echo sr_e($clientIdKey); ?>" maxlength="255" value="<?php echo sr_e((string) ($provider['client_id'] ?? '')); ?>"<?php echo !empty($provider['enabled']) ? ' required' : ''; ?> class="form-input form-control-full" autocomplete="off" data-oauth-required-provider="<?php echo sr_e($providerKey); ?>">
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>"><?php echo sr_e('Client secret'); ?></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_client_secret'); ?>" type="password" name="<?php echo sr_e($secretKey); ?>" maxlength="512" value="" placeholder="<?php echo sr_e(sr_member_oauth_secret_display((string) ($provider['client_secret'] ?? ''))); ?>" class="form-input form-control-full" autocomplete="new-password">
                            <p class="admin-form-help">비워 두면 기존 secret을 유지합니다. 저장 후 화면에는 원문을 표시하지 않습니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_scope'); ?>"><?php echo sr_e('Scope'); ?></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_scope'); ?>" type="text" name="<?php echo sr_e($scopeKey); ?>" maxlength="255" value="<?php echo sr_e(sr_member_oauth_provider_scopes($provider)); ?>" class="form-input form-control-full">
                            <p class="admin-form-help">비워 두면 provider 계약의 기본 scope를 사용합니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>"><?php echo sr_e('정렬 순서'); ?> <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e('member_oauth_' . $providerKey . '_sort_order'); ?>" type="number" name="<?php echo sr_e($sortOrderKey); ?>" min="-9999" max="9999" value="<?php echo sr_e((string) ((int) ($provider['sort_order'] ?? 0))); ?>" required class="form-input">
                        </div>
                    </div>
                </fieldset>
            <?php } ?>
            <?php if ($externalProviderCount < 1) { ?>
                <p class="admin-empty-state"><?php echo sr_e('설치된 외부 OAuth provider 계약이 없습니다. provider 모듈의 oauth-providers.php 계약을 활성화하면 이 화면에서 자격 증명을 저장할 수 있습니다.'); ?></p>
            <?php } ?>
        </section>

        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('저장'); ?></button>
        </div>
    </form>
</section>
<script>
document.querySelectorAll('[name$="_enabled"][id^="member_oauth_"]').forEach(function (toggle) {
    var providerKey = toggle.id.replace(/^member_oauth_/, '').replace(/_enabled$/, '');
    function syncRequired() {
        document.querySelectorAll('[data-oauth-required-provider="' + providerKey + '"]').forEach(function (input) {
            input.required = toggle.checked;
        });
        document.querySelectorAll('[data-oauth-required-for="' + providerKey + '"]').forEach(function (label) {
            label.hidden = !toggle.checked;
        });
    }
    toggle.addEventListener('change', syncRequired);
    syncRequired();
});
</script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
