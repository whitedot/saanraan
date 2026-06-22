<?php

$allowedGroupKeys = isset($settings['refund_allowed_group_keys']) && is_array($settings['refund_allowed_group_keys'])
    ? $settings['refund_allowed_group_keys']
    : [];
$refundRequestsEnabled = !empty($settings['refund_requests_enabled']);
$enabledMemberGroups = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') === 'enabled') {
        $enabledMemberGroups[] = $memberGroup;
    }
}
$allMembersKey = sr_deposit_refund_all_members_key();
$refundTargetOptions = [
    $allMembersKey => [
        'label' => '전체 회원',
        'summary' => '활성 회원 전체',
    ],
];
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
            '전체 회원을 선택하면 모든 활성 회원이 신청할 수 있고, 회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.',
            '환불 신청 사용이 켜져 있으면 최소 하나의 허용 대상을 선택해야 합니다.',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>환불 신청</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('deposit_refund_requests_enabled', '환불 신청 사용', (string) $depositSettingsHelp['refund_requests_enabled']['id'], $depositSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('deposit_refund_requests_enabled', 'refund_requests_enabled', '1', $refundRequestsEnabled, '사용', '', ' data-deposit-refund-enabled'); ?>
                <p class="form-help">사용하지 않으면 회원 화면에서 예치금 환불 신청 폼을 숨기고 직접 신청 POST도 거부합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e('환불 신청 허용 ' . $depositSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e((string) $depositSettingsHelp['refund_allowed_group_keys']['id']); ?>" data-overlay="#<?php echo sr_e((string) $depositSettingsHelp['refund_allowed_group_keys']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="deposit_refund_allowed_group_keys_select">환불 신청 허용 <span class="sr-required-label" data-deposit-refund-targets-required-label<?php echo $refundRequestsEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            </div>
            <div class="form-field">
                <?php echo sr_admin_select_badge_list_html('deposit_refund_allowed_group_keys', 'refund_allowed_group_keys', $refundTargetOptions, $allowedGroupKeys, '선택 가능한 대상이 없습니다.', '대상 선택', ' data-deposit-refund-targets data-deposit-refund-all-key="' . sr_e($allMembersKey) . '"'); ?>
                <p class="form-help">전체 회원을 선택하면 모든 활성 회원이 예치금 환불 신청을 할 수 있습니다. 전체 회원 뱃지를 제거하면 회원 그룹을 다시 선택할 수 있습니다.</p>
                <p class="form-help">아무 대상도 선택하지 않으면 회원이 예치금 환불 신청을 할 수 없습니다.</p>
            </div>
        </div>
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

    var allKey = root.getAttribute('data-deposit-refund-all-key') || '';
    var enabled = document.querySelector('[data-deposit-refund-enabled]');
    var requiredLabel = document.querySelector('[data-deposit-refund-targets-required-label]');
    function selectedItems() {
        return Array.prototype.slice.call(root.querySelectorAll('[data-admin-select-badge-item]'));
    }
    function itemValue(item) {
        var input = item.querySelector('[data-admin-select-badge-value]');
        return input ? input.value : '';
    }
    function syncAllSelectionState() {
        var items = selectedItems();
        var allItem = null;
        items.forEach(function (item) {
            if (itemValue(item) === allKey) {
                allItem = item;
            }
        });
        var select = root.querySelector('[data-admin-select-badge-list-select]');
        if (requiredLabel) {
            requiredLabel.hidden = !!(enabled && !enabled.checked);
        }
        if (select) {
            var refundEnabled = !(enabled && !enabled.checked);
            var hasSelection = items.length > 0;
            select.disabled = !!allItem || !refundEnabled;
            select.required = refundEnabled && !hasSelection;
            select.setCustomValidity(refundEnabled && !hasSelection ? '환불 신청 허용 대상을 선택하세요.' : '');
            Array.prototype.forEach.call(select.options, function (option) {
                if (!option.value) {
                    return;
                }
                if (option.value === allKey && !allItem) {
                    option.hidden = false;
                    option.disabled = false;
                }
            });
        }
        if (allItem) {
            items.forEach(function (item) {
                if (item !== allItem) {
                    item.remove();
                }
            });
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
</script>

<?php foreach ($depositSettingsHelp as $depositSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $depositSettingsHelpModal['id'], (string) $depositSettingsHelpModal['title'], (string) $depositSettingsHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
