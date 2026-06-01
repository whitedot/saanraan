<?php

$allowedGroupKeys = isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
    ? $settings['withdrawal_allowed_group_keys']
    : [];
$enabledMemberGroups = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') === 'enabled') {
        $enabledMemberGroups[] = $memberGroup;
    }
}
$allMembersKey = sr_reward_withdrawal_all_members_key();
$withdrawalTargetOptions = [
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
    $withdrawalTargetOptions[$groupKey] = [
        'label' => $title !== '' ? $title . ' (' . $groupKey . ')' : $groupKey,
        'summary' => '회원 그룹',
    ];
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <h2>출금 신청 대상</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <label class="form-label" for="reward_withdrawal_allowed_group_keys_select">출금 신청 대상</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_select_badge_list_html('reward_withdrawal_allowed_group_keys', 'withdrawal_allowed_group_keys', $withdrawalTargetOptions, $allowedGroupKeys, '선택 가능한 대상이 없습니다.', '대상 선택', ' data-reward-withdrawal-targets data-reward-withdrawal-all-key="' . sr_e($allMembersKey) . '"'); ?>
                    <p class="admin-form-help">전체 회원을 선택하면 모든 활성 회원이 출금 신청을 할 수 있습니다. 회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.</p>
                    <p class="admin-form-help">아무 대상도 선택하지 않으면 회원이 출금 신청을 할 수 없습니다.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="admin-form-actions admin-form-sticky-actions">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_material_icon_html('save'); ?>저장</button>
    </div>
</form>

<script>
(function () {
    var root = document.querySelector('[data-reward-withdrawal-targets]');
    if (!root) {
        return;
    }

    var allKey = root.getAttribute('data-reward-withdrawal-all-key') || '';
    function selectedItems() {
        return Array.prototype.slice.call(root.querySelectorAll('[data-admin-select-badge-item]'));
    }
    function itemValue(item) {
        var input = item.querySelector('[data-admin-select-badge-value]');
        return input ? input.value : '';
    }
    function syncExclusiveSelection() {
        var items = selectedItems();
        var allItem = null;
        items.forEach(function (item) {
            if (itemValue(item) === allKey) {
                allItem = item;
            }
        });
        if (!allItem) {
            return;
        }
        items.forEach(function (item) {
            if (item !== allItem) {
                item.remove();
            }
        });
        var select = root.querySelector('[data-admin-select-badge-list-select]');
        if (select) {
            Array.prototype.forEach.call(select.options, function (option) {
                if (option.value && option.value !== allKey) {
                    option.hidden = true;
                    option.disabled = true;
                }
            });
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target && event.target.closest && event.target.closest('[data-reward-withdrawal-targets]')) {
            window.setTimeout(syncExclusiveSelection, 0);
        }
    });
    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('[data-reward-withdrawal-targets]')) {
            window.setTimeout(syncExclusiveSelection, 0);
        }
    });
    syncExclusiveSelection();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
