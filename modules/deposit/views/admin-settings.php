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
        'label' => $title !== '' ? $title . ' (' . $groupKey . ')' : $groupKey,
        'summary' => '회원 그룹',
    ];
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>환불 신청</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="admin-form-row">
            <label class="form-label" for="deposit_refund_requests_enabled">환불 신청 사용</label>
            <div class="admin-form-field">
                <label class="admin-form-check form-label" for="deposit_refund_requests_enabled">
                    <input
                        id="deposit_refund_requests_enabled"
                        type="checkbox"
                        name="refund_requests_enabled"
                        value="1"
                        class="form-checkbox"
                        data-deposit-refund-enabled
                        <?php echo $refundRequestsEnabled ? 'checked' : ''; ?>
                    >
                    사용
                </label>
                <p class="admin-form-help">사용하지 않으면 회원 화면에서 예치금 환불 신청 폼을 숨기고 직접 신청 POST도 거부합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="deposit_refund_allowed_group_keys_select">환불 신청 대상</label>
            <div class="admin-form-field">
                <?php echo sr_admin_select_badge_list_html('deposit_refund_allowed_group_keys', 'refund_allowed_group_keys', $refundTargetOptions, $allowedGroupKeys, '선택 가능한 대상이 없습니다.', '대상 선택', ' data-deposit-refund-targets data-deposit-refund-all-key="' . sr_e($allMembersKey) . '"'); ?>
                <p class="admin-form-help">전체 회원을 선택하면 모든 활성 회원이 예치금 환불 신청을 할 수 있습니다. 전체 회원 뱃지를 제거하면 회원 그룹을 다시 선택할 수 있습니다.</p>
                <p class="admin-form-help">아무 대상도 선택하지 않으면 회원이 예치금 환불 신청을 할 수 없습니다.</p>
            </div>
        </div>
    </section>

    <div class="admin-form-actions admin-form-sticky-actions">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_material_icon_html('save'); ?>저장</button>
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
        if (select) {
            select.disabled = !!allItem || (enabled && !enabled.checked);
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

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
