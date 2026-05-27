<?php

$adminPageTitle = '예치금 환경설정';
$adminPageSubtitle = '예치금 수동 조정 정책을 관리합니다.';
$settings = isset($settings) && is_array($settings) ? $settings : ['manual_adjust_group_policies_json' => ''];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/deposits/settings')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2>예치금 환경설정</h2>
        <?php echo sr_csrf_field(); ?>

        <div class="admin-form-row">
            <label class="form-label" for="deposit_settings_manual_adjust_group_policies_json">수동 조정 회원 그룹 정책</label>
            <div class="admin-form-field">
                <textarea id="deposit_settings_manual_adjust_group_policies_json" name="manual_adjust_group_policies_json" class="form-textarea form-control-full" rows="10"><?php echo sr_e((string) ($settings['manual_adjust_group_policies_json'] ?? '')); ?></textarea>
                <small class="admin-form-help">JSON 배열로 입력합니다. 예: [{"group_key":"vip","mode":"multiplier","value":"1.5","priority":100,"status":"active"}]</small>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/deposits/balances')); ?>" class="btn btn-solid-light">예치금 잔액</a>
        <button type="submit" class="btn btn-solid-primary">환경설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
