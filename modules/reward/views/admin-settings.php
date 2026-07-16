<?php

$allowedGroupKeys = isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
    ? $settings['withdrawal_allowed_group_keys']
    : [];
$usageEnabled = !isset($settings['usage_enabled']) || !empty($settings['usage_enabled']);
$rewardDisplayName = (string) ($settings['display_name'] ?? '적립금');
$rewardUnitLabel = (string) ($settings['unit_label'] ?? '원');
$rewardDefaultExpirationDays = (string) sr_reward_normalize_expiration_days($settings['default_expiration_days'] ?? 0);
$rewardIdentityWithdrawalAvailable = isset($rewardIdentityWithdrawalAvailable)
    ? (bool) $rewardIdentityWithdrawalAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'reward.withdrawal_request'));
$rewardIdentityVerificationInputAttributes = $rewardIdentityWithdrawalAvailable
    ? ''
    : ' disabled aria-describedby="reward-settings-identity-unavailable"';
$rewardIdentityModuleReferences = [['module_key' => 'identity_verification', 'path' => '/admin/identity-providers']];
$withdrawalRequestsEnabled = !empty($settings['withdrawal_requests_enabled']);
$enabledMemberGroups = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') === 'enabled') {
        $enabledMemberGroups[] = $memberGroup;
    }
}
$withdrawalTargetOptions = [];
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
    'usage' => [
        'id' => 'reward-settings-help-usage-modal',
        'title' => $rewardDisplayName . ' 기능 도움말',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '끄면 ' . $rewardDisplayName . '을 새로 지급하거나 사용·환불·조정하는 거래를 만들 수 없습니다. 보상, 환전, 유료 쿠폰처럼 ' . $rewardDisplayName . '을 선택하는 다른 기능에서도 제외됩니다.',
            '기존 잔액과 거래 내역은 삭제하지 않습니다. 기능을 다시 켜면 저장되어 있던 잔액과 거래 내역을 이어서 사용합니다.',
        ]),
    ],
    'expiration' => [
        'id' => 'reward-settings-help-expiration-modal',
        'title' => '기본 유효기간 도움말',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '별도 만료일을 지정하지 않은 새 지급 거래에 적용할 기본 유효기간입니다. 이미 지급된 ' . $rewardDisplayName . '의 만료일은 바뀌지 않습니다.',
            '0을 입력하면 새 지급 ' . $rewardDisplayName . '에 만료일을 두지 않습니다. 1일부터 3,650일까지 설정할 수 있습니다.',
            '기한이 지난 잔여 ' . $rewardDisplayName . '은 해당 회원이 ' . $rewardDisplayName . ' 화면을 열거나 다음 거래를 처리할 때 차감됩니다.',
        ]),
    ],
    'withdrawal_requests_enabled' => [
        'id' => 'reward-settings-help-withdrawal-requests-enabled-modal',
        'title' => '출금 신청 기능 도움말',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '회원 화면에서 적립금 출금 신청을 받을지 정합니다.',
            '끄면 회원 화면의 신청 입력란을 숨기며, 화면 주소로 직접 신청을 보내도 서버에서 거부합니다. 접수된 기존 신청 내역은 삭제하지 않습니다.',
        ]),
    ],
    'identity_withdrawal_required' => [
        'id' => 'reward-settings-help-identity-withdrawal-required-modal',
        'title' => '출금 신청 본인확인 도움말',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '회원이 출금 신청을 제출하기 전에 본인확인을 완료하도록 요구합니다. 확인 결과는 신청 한 건을 접수한 뒤 사용 완료 처리하므로 다음 신청에는 다시 확인해야 합니다.',
            '본인확인 모듈이 켜져 있고 적립금 출금 신청 용도를 지원하는 제공자가 준비되어 있을 때만 사용할 수 있습니다.',
        ]),
    ],
    'withdrawal_allowed_group_keys' => [
        'id' => 'reward-settings-help-withdrawal-allowed-group-keys-modal',
        'title' => '출금 신청 허용',
        'body_html' => $rewardSettingsHelpBodyHtml([
            '출금 신청을 사용할 때 신청할 수 있는 회원 범위를 정합니다.',
            '회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.',
            '회원 그룹을 선택하지 않으면 전체 로그인 회원이 신청할 수 있습니다.',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>기본 사용</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('reward_usage_enabled', $rewardDisplayName . ' 기능', (string) $rewardSettingsHelp['usage']['id'], $rewardSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('reward_usage_enabled', 'usage_enabled', '1', $usageEnabled, '사용'); ?>
                <p class="form-help">끄면 새 <?php echo sr_e($rewardDisplayName); ?> 거래와 보상·환전·유료 쿠폰 등 <?php echo sr_e($rewardDisplayName); ?>을 사용하는 기능을 중단합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="reward_display_name">표시명 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="reward_display_name" type="text" name="display_name" value="<?php echo sr_e($rewardDisplayName); ?>" class="form-input" maxlength="40" required>
                <p class="form-help">관리자와 회원 화면에서 이 항목을 부르는 이름입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="reward_unit_label">단위</label>
            <div class="form-field">
                <input id="reward_unit_label" type="text" name="unit_label" value="<?php echo sr_e($rewardUnitLabel); ?>" class="form-input" maxlength="20">
                <p class="form-help">금액 뒤에 표시할 짧은 단위입니다. 비워두면 원을 사용합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('reward_default_expiration_days', '기본 유효기간', (string) $rewardSettingsHelp['expiration']['id'], $rewardSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <div class="input-group admin-input-unit">
                    <input id="reward_default_expiration_days" type="number" name="default_expiration_days" value="<?php echo sr_e($rewardDefaultExpirationDays); ?>" class="form-input" min="0" max="3650" step="1">
                    <span class="input-group-text">일</span>
                </div>
                <p class="form-help">새로 지급하는 <?php echo sr_e($rewardDisplayName); ?>에 적용합니다. 0이면 만료일을 두지 않습니다.</p>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>출금 신청</h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('reward_withdrawal_requests_enabled', '출금 신청 기능', (string) $rewardSettingsHelp['withdrawal_requests_enabled']['id'], $rewardSettingsHelpOpenLabel); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('reward_withdrawal_requests_enabled', 'withdrawal_requests_enabled', '1', $withdrawalRequestsEnabled, '사용', '', ' data-reward-withdrawal-enabled'); ?>
                <p class="form-help">끄면 회원 화면에서 출금 신청을 받지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('reward_identity_withdrawal_required', '출금 신청 본인확인', (string) $rewardSettingsHelp['identity_withdrawal_required']['id'], $rewardSettingsHelpOpenLabel, false, true); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('reward_identity_withdrawal_required', 'identity_withdrawal_required', '1', $rewardIdentityWithdrawalAvailable && !empty($settings['identity_withdrawal_required']), '사용', '', $rewardIdentityVerificationInputAttributes); ?>
                <p class="form-help">켜면 회원이 출금 신청을 제출할 때마다 본인확인을 요구합니다.</p>
                <?php echo sr_admin_module_reference_list_html($pdo, $rewardIdentityModuleReferences); ?>
                <?php if (!$rewardIdentityWithdrawalAvailable) { ?>
                    <p id="reward-settings-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 적립금 출금 신청 목적을 지원하는 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label form-label-help">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e('출금 신청 허용 ' . $rewardSettingsHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e((string) $rewardSettingsHelp['withdrawal_allowed_group_keys']['id']); ?>" data-overlay="#<?php echo sr_e((string) $rewardSettingsHelp['withdrawal_allowed_group_keys']['id']); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
                <label for="reward_withdrawal_allowed_group_keys_select">출금 신청 허용</label>
            </div>
            <div class="form-field">
                <?php echo sr_admin_select_badge_list_html('reward_withdrawal_allowed_group_keys', 'withdrawal_allowed_group_keys', $withdrawalTargetOptions, $allowedGroupKeys, '활성 회원 그룹 없음', '그룹 선택', ' data-reward-withdrawal-targets'); ?>
                <p class="form-help">회원 그룹을 선택하지 않으면 전체 로그인 회원이 적립금 출금 신청을 할 수 있습니다.</p>
                <p class="form-help">회원 그룹을 선택하면 해당 그룹 중 하나에 속한 회원만 신청할 수 있습니다.</p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var root = document.querySelector('[data-reward-withdrawal-targets]');
    if (!root) {
        return;
    }

    var enabled = document.querySelector('[data-reward-withdrawal-enabled]');
    function syncAllSelectionState() {
        var select = root.querySelector('[data-admin-select-badge-list-select]');
        if (select) {
            var withdrawalEnabled = !(enabled && !enabled.checked);
            select.disabled = !withdrawalEnabled;
            select.required = false;
            select.setCustomValidity('');
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
