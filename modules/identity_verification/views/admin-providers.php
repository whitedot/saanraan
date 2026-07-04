<?php

$adminPageTitle = '본인확인 제공자 설정';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <h2>공통 설정</h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label">사용 여부</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('identity_verification_enabled', 'enabled', '1', !empty($settings['enabled']), '사용'); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="identity_verification_default_provider_key">기본 제공자</label>
                <div class="form-field">
                    <select id="identity_verification_default_provider_key" name="default_provider_key" class="form-select">
                        <option value="">자동 선택</option>
                        <?php foreach ($providers as $providerKey => $provider) { ?>
                            <option value="<?php echo sr_e((string) $providerKey); ?>"<?php echo (string) $settings['default_provider_key'] === (string) $providerKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($provider['display_name'] ?? $providerKey)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="identity_verification_attempt_ttl_seconds">시도 유효 시간</label>
                <div class="form-field">
                    <input id="identity_verification_attempt_ttl_seconds" class="form-control" type="number" name="attempt_ttl_seconds" min="60" max="3600" value="<?php echo sr_e((string) $settings['attempt_ttl_seconds']); ?>" required>
                    <small class="form-help">초 단위입니다. provider return이 이 시간을 넘으면 만료 처리합니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="identity_verification_result_valid_days">결과 기본 유효 기간</label>
                <div class="form-field">
                    <input id="identity_verification_result_valid_days" class="form-control" type="number" name="result_valid_days" min="0" max="3650" value="<?php echo sr_e((string) $settings['result_valid_days']); ?>" required>
                    <small class="form-help">일 단위입니다. 0이면 공통 만료일을 두지 않습니다.</small>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">HTTPS 요구</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('identity_verification_require_https', 'require_https', '1', !empty($settings['require_https']), '요구'); ?>
                </div>
            </div>
        </div>
    </section>

    <?php foreach ($providers as $providerKey => $provider) { ?>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title"><?php echo sr_e((string) ($provider['display_name'] ?? $providerKey)); ?></h2>
                <span class="badge"><?php echo sr_e((string) $providerKey); ?></span>
            </div>
            <div class="form-grid">
                <?php
                $enabledKey = sr_identity_verification_setting_key((string) $providerKey, 'enabled');
                $environmentKey = sr_identity_verification_setting_key((string) $providerKey, 'environment');
                $sortOrderKey = sr_identity_verification_setting_key((string) $providerKey, 'sort_order');
                ?>
                <div class="form-row">
                    <span class="form-label">사용 여부</span>
                    <div class="form-field">
                        <?php echo sr_admin_switch_html('identity_provider_' . sr_e((string) $providerKey) . '_enabled', $enabledKey, '1', !empty($provider['enabled']), '사용'); ?>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="identity_provider_<?php echo sr_e((string) $providerKey); ?>_environment">환경</label>
                    <div class="form-field">
                        <select id="identity_provider_<?php echo sr_e((string) $providerKey); ?>_environment" name="<?php echo sr_e($environmentKey); ?>" class="form-select">
                            <option value="test"<?php echo (string) ($provider['environment'] ?? 'test') === 'test' ? ' selected' : ''; ?>>테스트</option>
                            <option value="production"<?php echo (string) ($provider['environment'] ?? '') === 'production' ? ' selected' : ''; ?>>운영</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="identity_provider_<?php echo sr_e((string) $providerKey); ?>_sort_order">정렬</label>
                    <div class="form-field">
                        <input id="identity_provider_<?php echo sr_e((string) $providerKey); ?>_sort_order" class="form-control" type="number" name="<?php echo sr_e($sortOrderKey); ?>" min="-9999" max="9999" value="<?php echo sr_e((string) ($provider['sort_order'] ?? 0)); ?>">
                    </div>
                </div>
                <?php foreach ((array) ($provider['settings_schema'] ?? []) as $settingKey => $definition) { ?>
                    <?php
                    if (!is_string($settingKey) || !is_array($definition)) {
                        continue;
                    }
                    $storedKey = sr_identity_verification_setting_key((string) $providerKey, $settingKey);
                    $isSecret = !empty($definition['secret']);
                    $value = sr_identity_verification_provider_setting($provider, $settingKey);
                    ?>
                    <div class="form-row">
                        <label class="form-label" for="identity_provider_<?php echo sr_e((string) $providerKey . '_' . $settingKey); ?>"><?php echo sr_e((string) ($definition['label'] ?? $settingKey)); ?><?php echo !empty($definition['required']) ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></label>
                        <div class="form-field">
                            <input id="identity_provider_<?php echo sr_e((string) $providerKey . '_' . $settingKey); ?>" class="form-control" type="<?php echo $isSecret ? 'password' : 'text'; ?>" name="<?php echo sr_e($storedKey); ?>" value="<?php echo $isSecret ? '' : sr_e($value); ?>"<?php echo !$isSecret && !empty($definition['required']) ? ' required' : ''; ?>>
                            <?php if ($isSecret && $value !== '') { ?>
                                <small class="form-help">저장된 값이 있습니다. 변경할 때만 새 값을 입력하세요.</small>
                            <?php } elseif (!empty($definition['help'])) { ?>
                                <small class="form-help"><?php echo sr_e((string) $definition['help']); ?></small>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <?php if ($providers === []) { ?>
        <section class="card">
            <p class="text-muted">설치되고 활성화된 본인확인 provider 플러그인이 없습니다.</p>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
