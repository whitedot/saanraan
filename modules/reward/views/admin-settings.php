<?php

$allowedGroupKeys = isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
    ? $settings['withdrawal_allowed_group_keys']
    : [];
$withdrawalRequestsEnabled = !empty($settings['withdrawal_requests_enabled']);
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
        'label' => $title !== '' ? $title : $groupKey,
        'summary' => '회원 그룹',
    ];
}
$rewardSettingsHelpOpenLabel = '도움말';
$rewardSettingsHelpBodyHtml = static function (array $paragraphs): string {
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
$rewardSettingsHelp = [
    'withdrawal_requests_enabled' => [
        'id' => 'reward-settings-help-withdrawal-requests-enabled-modal',
        'title' => '출금 신청 사용',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '회원 화면에서 적립금 출금 신청 폼을 열지 여부를 정합니다.',
            '사용하지 않으면 회원 화면의 신청 폼을 숨기고 직접 신청 POST도 서버에서 거부합니다.',
        ]),
    ],
    'withdrawal_allowed_group_keys' => [
        'id' => 'reward-settings-help-withdrawal-allowed-group-keys-modal',
        'title' => '출금 신청 허용',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '출금 신청을 사용할 때 신청할 수 있는 회원 범위를 정합니다.',
            '전체 회원을 선택하면 모든 활성 회원이 신청할 수 있고, 회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.',
            '출금 신청 사용이 켜져 있으면 최소 하나의 허용 대상을 선택해야 합니다.',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>출금 신청</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('reward_withdrawal_requests_enabled', '출금 신청 사용', (string) $rewardSettingsHelp['withdrawal_requests_enabled']['id'], $rewardSettingsHelpOpenLabel); ?>
            <div class="admin-form-field">
                <?php echo sr_admin_switch_html('reward_withdrawal_requests_enabled', 'withdrawal_requests_enabled', '1', $withdrawalRequestsEnabled, '사용', '', ' data-reward-withdrawal-enabled'); ?>
                <p class="admin-form-help">사용하지 않으면 회원 화면에서 적립금 출금 신청 폼을 숨기고 직접 신청 POST도 거부합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="form-label admin-form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e('출금 신청 허용 ' . $rewardSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e((string) $rewardSettingsHelp['withdrawal_allowed_group_keys']['id']); ?>" data-overlay="#<?php echo sr_e((string) $rewardSettingsHelp['withdrawal_allowed_group_keys']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="reward_withdrawal_allowed_group_keys_select">출금 신청 허용 <span class="sr-required-label" data-reward-withdrawal-targets-required-label<?php echo $withdrawalRequestsEnabled ? '' : ' hidden'; ?>>(필수)</span></label>
            </div>
            <div class="admin-form-field">
                <?php echo sr_admin_select_badge_list_html('reward_withdrawal_allowed_group_keys', 'withdrawal_allowed_group_keys', $withdrawalTargetOptions, $allowedGroupKeys, '선택 가능한 대상이 없습니다.', '대상 선택', ' data-reward-withdrawal-targets data-reward-withdrawal-all-key="' . sr_e($allMembersKey) . '"'); ?>
                <p class="admin-form-help">전체 회원을 선택하면 모든 활성 회원이 적립금 출금 신청을 할 수 있습니다. 전체 회원 뱃지를 제거하면 회원 그룹을 다시 선택할 수 있습니다.</p>
                <p class="admin-form-help">아무 대상도 선택하지 않으면 회원이 적립금 출금 신청을 할 수 없습니다.</p>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">환경설정 저장</button>
    </div>
</form>

<script>
(function () {
    var root = document.querySelector('[data-reward-withdrawal-targets]');
    if (!root) {
        return;
    }

    var allKey = root.getAttribute('data-reward-withdrawal-all-key') || '';
    var enabled = document.querySelector('[data-reward-withdrawal-enabled]');
    var requiredLabel = document.querySelector('[data-reward-withdrawal-targets-required-label]');
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
            var withdrawalEnabled = !(enabled && !enabled.checked);
            var hasSelection = items.length > 0;
            select.disabled = !!allItem || !withdrawalEnabled;
            select.required = withdrawalEnabled && !hasSelection;
            select.setCustomValidity(withdrawalEnabled && !hasSelection ? '출금 신청 허용 대상을 선택하세요.' : '');
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
        if (event.target && event.target.closest && event.target.closest('[data-reward-withdrawal-targets]')) {
            window.setTimeout(syncAllSelectionState, 0);
        }
    });
    document.addEventListener('click', function (event) {
        if (event.target && event.target.closest && event.target.closest('[data-reward-withdrawal-targets]')) {
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

<?php foreach ($rewardSettingsHelp as $rewardSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $rewardSettingsHelpModal['id'], (string) $rewardSettingsHelpModal['title'], (string) $rewardSettingsHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
