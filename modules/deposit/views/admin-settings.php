<?php

$allowedGroupKeys = isset($settings['refund_allowed_group_keys']) && is_array($settings['refund_allowed_group_keys'])
    ? $settings['refund_allowed_group_keys']
    : [];
$notificationCases = isset($notificationCases) && is_array($notificationCases) ? $notificationCases : sr_deposit_notification_cases();
$notificationCaseSettings = sr_deposit_notification_case_settings_from_value($settings['notification_cases'] ?? []);
$notificationChannelOptions = isset($notificationChannelOptions) && is_array($notificationChannelOptions) ? $notificationChannelOptions : ['site'];
$usageEnabled = !isset($settings['usage_enabled']) || !empty($settings['usage_enabled']);
$depositDisplayName = (string) ($settings['display_name'] ?? '예치금');
$depositUnitLabel = (string) ($settings['unit_label'] ?? '원');
$depositIdentityRefundAvailable = isset($depositIdentityRefundAvailable)
    ? (bool) $depositIdentityRefundAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'deposit.refund_request'));
$depositIdentityVerificationInputAttributes = $depositIdentityRefundAvailable
    ? ''
    : ' disabled aria-describedby="deposit-settings-identity-unavailable"';
$allNotificationCasesEnabled = $notificationCases !== [];
foreach ($notificationCases as $notificationCaseKey => $_notificationCase) {
    $notificationCaseKey = (string) $notificationCaseKey;
    if (empty($notificationCaseSettings[$notificationCaseKey]['enabled'])) {
        $allNotificationCasesEnabled = false;
        break;
    }
}
$refundRequestsEnabled = !empty($settings['refund_requests_enabled']);
$enabledMemberGroups = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') === 'enabled') {
        $enabledMemberGroups[] = $memberGroup;
    }
}
$refundTargetOptions = [];
foreach ($enabledMemberGroups as $memberGroup) {
    $groupKey = (string) ($memberGroup['group_key'] ?? '');
    if ($groupKey === '') {
        continue;
    }

    $title = trim((string) ($memberGroup['title'] ?? ''));
    $refundTargetOptions[$groupKey] = [
        'label' => $title !== '' ? $title : $groupKey,
        'summary' => '회원 그룹',
    ];
}
$depositSettingsHelpOpenLabel = '도움말';
$depositSettingsHelpBodyHtml = static function (array $paragraphs): string {
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim((string) $paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html .= '<p>' . sr_e($paragraph) . '</p>';
    }

    return $html;
};
$depositSettingsHelp = [
    'refund_requests_enabled' => [
        'id' => 'deposit-settings-help-refund-requests-enabled-modal',
        'title' => '환불 신청 사용',
        'body_html' => $depositSettingsHelpBodyHtml([
            '회원 화면에서 예치금 환불 신청 폼을 열지 여부를 정합니다.',
            '사용하지 않으면 회원 화면의 신청 폼을 숨기고 직접 신청 POST도 서버에서 거부합니다.',
        ]),
    ],
    'refund_allowed_group_keys' => [
        'id' => 'deposit-settings-help-refund-allowed-group-keys-modal',
        'title' => '환불 신청 허용',
        'body_html' => $depositSettingsHelpBodyHtml([
            '환불 신청을 사용할 때 신청할 수 있는 회원 범위를 정합니다.',
            '회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.',
            '회원 그룹을 선택하지 않으면 전체 로그인 회원이 신청할 수 있습니다.',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>기본 사용</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="form-row">
            <label class="form-label" for="deposit_usage_enabled"><?php echo sr_e($depositDisplayName); ?> 사용 여부</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('deposit_usage_enabled', 'usage_enabled', '1', $usageEnabled, '사용'); ?>
                <p class="form-help">사용하지 않으면 보상, 환전, 쿠폰 유료 발급 등 <?php echo sr_e($depositDisplayName); ?>을 선택하거나 새 거래를 만드는 사용처에서 제외됩니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="deposit_display_name">표시명 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="deposit_display_name" type="text" name="display_name" value="<?php echo sr_e($depositDisplayName); ?>" class="form-input" maxlength="40" required>
                <p class="form-help">관리자와 회원 화면에서 이 항목을 부르는 이름입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="deposit_unit_label">단위</label>
            <div class="form-field">
                <input id="deposit_unit_label" type="text" name="unit_label" value="<?php echo sr_e($depositUnitLabel); ?>" class="form-input" maxlength="20">
                <p class="form-help">금액 뒤에 표시할 짧은 단위입니다. 비워두면 원을 사용합니다.</p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>환불 신청</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('deposit_refund_requests_enabled', '환불 신청 사용', (string) $depositSettingsHelp['refund_requests_enabled']['id'], $depositSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('deposit_refund_requests_enabled', 'refund_requests_enabled', '1', $refundRequestsEnabled, '사용', '', ' data-deposit-refund-enabled'); ?>
                <p class="form-help">사용하지 않으면 회원 화면에서 예치금 환불 신청 폼을 숨기고 직접 신청 POST도 거부합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="deposit_identity_refund_required">환불 신청 본인확인</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('deposit_identity_refund_required', 'identity_refund_required', '1', $depositIdentityRefundAvailable && !empty($settings['identity_refund_required']), '사용', '', $depositIdentityVerificationInputAttributes); ?>
                <p class="form-help">사용하면 회원이 환불 신청을 제출할 때마다 본인확인을 요구합니다.</p>
                <?php if (!$depositIdentityRefundAvailable) { ?>
                    <div id="deposit-settings-identity-unavailable" class="alert alert-warning" role="alert">
                        본인확인 사용이 꺼져 있거나 예치금 환불 신청 목적을 지원하는 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e('환불 신청 허용 ' . $depositSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e((string) $depositSettingsHelp['refund_allowed_group_keys']['id']); ?>" data-overlay="#<?php echo sr_e((string) $depositSettingsHelp['refund_allowed_group_keys']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="deposit_refund_allowed_group_keys_select">환불 신청 허용</label>
            </div>
            <div class="form-field">
                <?php echo sr_admin_select_badge_list_html('deposit_refund_allowed_group_keys', 'refund_allowed_group_keys', $refundTargetOptions, $allowedGroupKeys, '활성 회원 그룹 없음', '그룹 선택', ' data-deposit-refund-targets'); ?>
                <p class="form-help">회원 그룹을 선택하지 않으면 전체 로그인 회원이 예치금 환불 신청을 할 수 있습니다.</p>
                <p class="form-help">회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.</p>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">회원 알림</h2>
            <div class="type-small">
                <?php echo sr_admin_switch_html('deposit_notification_bulk_toggle', 'deposit_notification_bulk_toggle', '1', $allNotificationCasesEnabled, $allNotificationCasesEnabled ? '전체비활성' : '전체활성', '', ' data-deposit-notification-bulk-toggle'); ?>
            </div>
        </div>
        <p class="form-help">전체 설정 스위치는 아래 알림 항목의 사용 여부를 한 번에 켜거나 끕니다.</p>
        <?php foreach ($notificationCases as $caseKey => $case) { ?>
            <?php
            $caseKey = (string) $caseKey;
            $caseSetting = isset($notificationCaseSettings[$caseKey]) && is_array($notificationCaseSettings[$caseKey]) ? $notificationCaseSettings[$caseKey] : ['enabled' => true, 'channels' => ['site']];
            $caseEnabled = !empty($caseSetting['enabled']);
            $caseChannels = sr_deposit_notification_channels_from_value($caseSetting['channels'] ?? ['site']);
            $caseId = 'deposit_notification_case_' . $caseKey;
            ?>
            <div class="form-row" data-deposit-notification-case="<?php echo sr_e($caseKey); ?>">
                <label class="form-label" for="<?php echo sr_e($caseId); ?>"><?php echo sr_e((string) ($case['label'] ?? '알림') . ' 사용 여부'); ?></label>
                <div class="form-field">
                    <?php echo sr_admin_switch_html($caseId, 'notification_cases[' . $caseKey . '][enabled]', '1', $caseEnabled, '사용', '0', ' data-deposit-notification-case-toggle data-deposit-notification-case-key="' . sr_e($caseKey) . '"'); ?>
                    <p class="form-help"><?php echo sr_e((string) ($case['description'] ?? '')); ?></p>
                </div>
            </div>
            <div class="form-row" data-deposit-notification-case="<?php echo sr_e($caseKey); ?>">
                <label class="form-label"><?php echo sr_e((string) ($case['label'] ?? '알림') . ' 채널'); ?> <span class="sr-required-label" data-deposit-notification-required-label data-deposit-notification-case-key="<?php echo sr_e($caseKey); ?>">(필수)</span></label>
                <div class="form-field">
                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="<?php echo sr_e((string) ($case['label'] ?? '알림') . ' 채널'); ?>">
                        <?php foreach ($notificationChannelOptions as $channelIndex => $channel) { ?>
                            <?php
                            $channel = (string) $channel;
                            $channelInputId = $caseId . '_channel_' . (string) $channelIndex;
                            $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($notificationChannelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                            ?>
                            <span class="filtering-toggle-item">
                                <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="notification_cases[<?php echo sr_e($caseKey); ?>][channels][]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only" data-deposit-notification-channel data-deposit-notification-case-key="<?php echo sr_e($caseKey); ?>"<?php echo in_array($channel, $caseChannels, true) ? ' checked' : ''; ?>>
                                <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                            </span>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var root = document.querySelector('[data-deposit-refund-targets]');
    if (!root) {
        return;
    }

    var enabled = document.querySelector('[data-deposit-refund-enabled]');
    function syncAllSelectionState() {
        var select = root.querySelector('[data-admin-select-badge-list-select]');
        if (select) {
            var refundEnabled = !(enabled && !enabled.checked);
            select.disabled = !refundEnabled;
            select.required = false;
            select.setCustomValidity('');
        }
    }

    if (enabled) {
        enabled.addEventListener('change', syncAllSelectionState);
    }
    document.addEventListener('change', function (event) {
        if (event.target && event.target.closest && event.target.closest('[data-deposit-refund-targets]')) {
            window.setTimeout(syncAllSelectionState, 0);
        }
    });
    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('[data-deposit-refund-targets]')) {
            window.setTimeout(syncAllSelectionState, 0);
        }
    });
    var itemRoot = root.querySelector('[data-admin-select-badge-list-items]');
    if (itemRoot && window.MutationObserver) {
        new MutationObserver(syncAllSelectionState).observe(itemRoot, {
            childList: true
        });
    }
    syncAllSelectionState();
})();

(function () {
    var form = document.querySelector('form[action="<?php echo sr_e(sr_url('/admin/deposits/settings')); ?>"]');
    if (!form) {
        return;
    }
    var bulkToggle = form.querySelector('[data-deposit-notification-bulk-toggle]');
    var caseKeys = [];
    Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-case-key]')).forEach(function (control) {
        var caseKey = control.getAttribute('data-deposit-notification-case-key') || '';
        if (caseKey && caseKeys.indexOf(caseKey) === -1) {
            caseKeys.push(caseKey);
        }
    });
    function syncCase(caseKey) {
        var toggle = form.querySelector('[data-deposit-notification-case-toggle][data-deposit-notification-case-key="' + caseKey + '"]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-channel][data-deposit-notification-case-key="' + caseKey + '"]'));
        var enabled = !toggle || toggle.checked;
        var selected = channels.some(function (channel) {
            return channel.checked;
        });
        channels.forEach(function (channel) {
            channel.disabled = !enabled;
        });
        Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-required-label][data-deposit-notification-case-key="' + caseKey + '"]')).forEach(function (label) {
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
        var toggles = Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-case-toggle]'));
        var allEnabled = toggles.length > 0 && toggles.every(function (toggle) {
            return toggle.checked;
        });
        bulkToggle.checked = allEnabled;
        setBulkLabel(allEnabled ? '전체비활성' : '전체활성');
    }
    if (bulkToggle) {
        bulkToggle.addEventListener('change', function () {
            Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-case-toggle]')).forEach(function (toggle) {
                toggle.checked = bulkToggle.checked;
            });
            caseKeys.forEach(syncCase);
            syncBulkToggle();
        });
    }
    caseKeys.forEach(function (caseKey) {
        Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-case-key="' + caseKey + '"]')).forEach(function (control) {
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
                invalidChannel = Array.prototype.slice.call(form.querySelectorAll('[data-deposit-notification-channel][data-deposit-notification-case-key="' + caseKey + '"]')).find(function (channel) {
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

<?php foreach ($depositSettingsHelp as $depositSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $depositSettingsHelpModal['id'], (string) $depositSettingsHelpModal['title'], (string) $depositSettingsHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
