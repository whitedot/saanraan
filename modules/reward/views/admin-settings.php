<?php

$adminPageTitle = '적립금 환경설정';
$adminPageSubtitle = '적립금 수동 조정 정책을 관리합니다.';
$settings = isset($settings) && is_array($settings) ? $settings : ['manual_adjust_group_policies_json' => ''];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>

    <?php
    $assetGroupPolicySectionTitle = '수동 조정 회원 그룹 정책';
    $assetGroupPolicyFieldName = 'manual_adjust_group_policies';
    $assetGroupPolicyInputId = 'reward_settings_manual_adjust_group_policies';
    $assetGroupPolicyRows = isset($manualAdjustGroupPolicies) && is_array($manualAdjustGroupPolicies) ? $manualAdjustGroupPolicies : [];
    $assetGroupPolicyGroups = isset($memberGroups) && is_array($memberGroups) ? $memberGroups : [];
    $assetGroupPolicyHelpText = '회원 그룹별로 적립금 수동 조정 금액을 다르게 적용합니다.';
    include SR_ROOT . '/modules/admin/views/asset-group-policy-editor.php';
    ?>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/rewards/balances')); ?>" class="btn btn-solid-light">적립금 잔액</a>
        <button type="submit" class="btn btn-solid-primary">환경설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
