<?php

$adminPageTitle = '포인트/금액 환전 환경설정';
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$assetExchangeAssets = isset($assetExchangeAssets) && is_array($assetExchangeAssets) ? $assetExchangeAssets : sr_asset_exchange_assets($pdo);
$assetExchangeAvailable = isset($assetExchangeAvailable) ? (bool) $assetExchangeAvailable : count($assetExchangeAssets) >= 2;
$assetExchangeInputAttributes = $assetExchangeAvailable
    ? ''
    : ' disabled aria-describedby="asset-exchange-settings-unavailable"';
$notificationGroups = isset($notificationGroups) && is_array($notificationGroups) ? $notificationGroups : [];
$assetExchangeIdentityAvailable = isset($assetExchangeIdentityAvailable)
    ? (bool) $assetExchangeIdentityAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'asset.exchange'));
$assetExchangeIdentityVerificationInputAttributes = $assetExchangeIdentityAvailable
    ? ''
    : ' disabled aria-describedby="asset-exchange-settings-identity-unavailable"';
$allNotificationCasesEnabled = $notificationGroups !== [];
foreach ($notificationGroups as $notificationGroup) {
    foreach ((array) ($notificationGroup['cases'] ?? []) as $notificationCaseKey => $_notificationCase) {
        $notificationCaseKey = (string) $notificationCaseKey;
        $caseSettings = is_array($notificationGroup['all_case_settings'] ?? null) ? $notificationGroup['all_case_settings'] : [];
        if (empty($caseSettings[$notificationCaseKey]['enabled'])) {
            $allNotificationCasesEnabled = false;
            break 2;
        }
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange/settings')); ?>" class="admin-form ui-form-theme" data-asset-exchange-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">환전 사용 여부</h2>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_settings_exchange_enabled">환전 사용 여부<?php echo $assetExchangeAvailable ? ' <span class="sr-required-label">(필수)</span>' : ''; ?></label>
            <div class="form-field">
                <?php echo sr_admin_radio_toggle_group_html('asset_exchange_settings_exchange_enabled', 'exchange_enabled', ['0' => '끄기', '1' => '켜기'], $assetExchangeAvailable ? (string) ($settings['exchange_enabled'] ?? '1') : '0', true, $assetExchangeInputAttributes); ?>
                <p class="form-help">끄면 환전 정책이 사용 상태여도 회원 환전 신청, 예상 금액 계산, 확정 실행을 모두 막습니다. 기존 환전 로그 조회와 정정은 유지됩니다.</p>
                <?php if (!$assetExchangeAvailable) { ?>
                    <p id="asset-exchange-settings-unavailable" class="form-help form-help-warning">
                        환전 가능한 자산 모듈이 2개 이상 설치되어 있고 활성화되어야 환전을 켤 수 있습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_settings_identity_exchange_required">환전 신청 본인확인</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('asset_exchange_settings_identity_exchange_required', 'identity_exchange_required', '1', $assetExchangeIdentityAvailable && (string) ($settings['identity_exchange_required'] ?? '0') === '1', '사용', '', $assetExchangeIdentityVerificationInputAttributes); ?>
                <p class="form-help">사용하면 회원이 환전을 실행할 때마다 본인확인을 요구합니다.</p>
                <?php if (!$assetExchangeIdentityAvailable) { ?>
                    <p id="asset-exchange-settings-identity-unavailable" class="form-help form-help-warning">
                        본인확인 사용이 꺼져 있거나 자산 환전 신청 목적을 지원하는 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
    </section>

    <?php if ($notificationGroups !== []) { ?>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">회원 알림</h2>
                <div class="type-small">
                    <?php echo sr_admin_switch_html('asset_exchange_notification_bulk_toggle', 'asset_exchange_notification_bulk_toggle', '1', $allNotificationCasesEnabled, $allNotificationCasesEnabled ? '전체비활성' : '전체활성', '', ' data-asset-exchange-notification-bulk-toggle'); ?>
                </div>
            </div>
            <p class="form-help">환전 출금, 입금, 수수료 알림의 사용 여부와 채널을 자산별로 설정합니다.</p>
            <?php foreach ($notificationGroups as $moduleKey => $notificationGroup) { ?>
                <?php
                $moduleKey = (string) $moduleKey;
                $moduleLabel = (string) ($notificationGroup['label'] ?? $moduleKey);
                $caseSettings = is_array($notificationGroup['all_case_settings'] ?? null) ? $notificationGroup['all_case_settings'] : [];
                $channelOptions = isset($notificationGroup['channel_options']) && is_array($notificationGroup['channel_options']) ? $notificationGroup['channel_options'] : ['site'];
                $channelsFunction = (string) ($notificationGroup['channels_function'] ?? '');
                ?>
                <?php foreach ((array) ($notificationGroup['cases'] ?? []) as $caseKey => $case) { ?>
                    <?php
                    $caseKey = (string) $caseKey;
                    $caseSetting = isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey]) ? $caseSettings[$caseKey] : ['enabled' => true, 'channels' => ['site']];
                    $caseEnabled = !empty($caseSetting['enabled']);
                    $caseChannels = function_exists($channelsFunction) ? $channelsFunction($caseSetting['channels'] ?? ['site']) : ['site'];
                    $caseId = 'asset_exchange_notification_case_' . $moduleKey . '_' . $caseKey;
                    $caseDataKey = $moduleKey . ':' . $caseKey;
                    ?>
                    <div class="form-row" data-asset-exchange-notification-case="<?php echo sr_e($caseDataKey); ?>">
                        <label class="form-label" for="<?php echo sr_e($caseId); ?>"><?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 사용 여부'); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_switch_html($caseId, 'notification_cases[' . $moduleKey . '][' . $caseKey . '][enabled]', '1', $caseEnabled, '사용', '0', ' data-asset-exchange-notification-case-toggle data-asset-exchange-notification-case-key="' . sr_e($caseDataKey) . '"'); ?>
                            <p class="form-help"><?php echo sr_e((string) ($case['description'] ?? '')); ?></p>
                        </div>
                    </div>
                    <div class="form-row" data-asset-exchange-notification-case="<?php echo sr_e($caseDataKey); ?>">
                        <label class="form-label"><?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 채널'); ?> <span class="sr-required-label" data-asset-exchange-notification-required-label data-asset-exchange-notification-case-key="<?php echo sr_e($caseDataKey); ?>">(필수)</span></label>
                        <div class="form-field">
                            <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="<?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 채널'); ?>">
                                <?php foreach ($channelOptions as $channelIndex => $channel) { ?>
                                    <?php
                                    $channel = (string) $channel;
                                    $channelInputId = $caseId . '_channel_' . (string) $channelIndex;
                                    $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($channelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                    ?>
                                    <span class="filtering-toggle-item">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="notification_cases[<?php echo sr_e($moduleKey); ?>][<?php echo sr_e($caseKey); ?>][channels][]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only" data-asset-exchange-notification-channel data-asset-exchange-notification-case-key="<?php echo sr_e($caseDataKey); ?>"<?php echo in_array($channel, $caseChannels, true) ? ' checked' : ''; ?>>
                                        <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="btn btn-solid-light">기준값</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-asset-exchange-settings-form]');
    if (!form) {
        return;
    }

    var bulkToggle = form.querySelector('[data-asset-exchange-notification-bulk-toggle]');
    var caseKeys = [];
    Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-key]')).forEach(function (control) {
        var caseKey = control.getAttribute('data-asset-exchange-notification-case-key') || '';
        if (caseKey && caseKeys.indexOf(caseKey) === -1) {
            caseKeys.push(caseKey);
        }
    });

    function syncCase(caseKey) {
        var toggle = form.querySelector('[data-asset-exchange-notification-case-toggle][data-asset-exchange-notification-case-key="' + caseKey + '"]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-channel][data-asset-exchange-notification-case-key="' + caseKey + '"]'));
        var enabled = !toggle || toggle.checked;
        var selected = channels.some(function (channel) {
            return channel.checked;
        });
        channels.forEach(function (channel) {
            channel.disabled = !enabled;
        });
        Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-required-label][data-asset-exchange-notification-case-key="' + caseKey + '"]')).forEach(function (label) {
            label.hidden = !enabled;
        });
        if (channels[0] && typeof channels[0].setCustomValidity === 'function') {
            channels[0].setCustomValidity(!enabled || selected ? '' : '알림 채널을 하나 이상 선택하세요.');
        }
    }

    function setBulkLabel(text) {
        var label = bulkToggle && bulkToggle.closest ? bulkToggle.closest('label') : null;
        if (!label) {
            return;
        }
        for (var index = label.childNodes.length - 1; index >= 0; index -= 1) {
            if (label.childNodes[index].nodeType === 3 && label.childNodes[index].nodeValue.trim() !== '') {
                label.childNodes[index].nodeValue = text;
                return;
            }
        }
        label.appendChild(document.createTextNode(text));
    }

    function syncBulkToggle() {
        if (!bulkToggle) {
            return;
        }
        var toggles = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-toggle]'));
        var allEnabled = toggles.length > 0 && toggles.every(function (toggle) {
            return toggle.checked;
        });
        bulkToggle.checked = allEnabled;
        setBulkLabel(allEnabled ? '전체비활성' : '전체활성');
    }

    if (bulkToggle) {
        bulkToggle.addEventListener('change', function () {
            Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-toggle]')).forEach(function (toggle) {
                toggle.checked = bulkToggle.checked;
            });
            caseKeys.forEach(syncCase);
            syncBulkToggle();
        });
    }
    caseKeys.forEach(function (caseKey) {
        Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-key="' + caseKey + '"]')).forEach(function (control) {
            control.addEventListener('change', function () {
                syncCase(caseKey);
                syncBulkToggle();
            });
        });
        syncCase(caseKey);
    });
    syncBulkToggle();
    form.addEventListener('submit', function (event) {
        var invalidChannel = null;
        caseKeys.forEach(function (caseKey) {
            syncCase(caseKey);
            if (!invalidChannel) {
                invalidChannel = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-channel][data-asset-exchange-notification-case-key="' + caseKey + '"]')).find(function (channel) {
                    return !channel.validity.valid;
                }) || null;
            }
        });
        if (invalidChannel) {
            event.preventDefault();
            invalidChannel.reportValidity();
        }
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
