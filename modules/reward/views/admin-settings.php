<?php

$allowedGroupKeys = isset($settings['withdrawal_allowed_group_keys']) && is_array($settings['withdrawal_allowed_group_keys'])
    ? $settings['withdrawal_allowed_group_keys']
    : [];
$allowedGroupMap = array_fill_keys($allowedGroupKeys, true);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/rewards/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-form-section">
        <h2>출금 신청 대상</h2>
        <div class="admin-form-row">
            <label class="form-label">출금 가능 회원 그룹</label>
            <div class="admin-form-field">
                <?php if ($memberGroups === []) { ?>
                    <p class="admin-form-help">등록된 회원 그룹이 없습니다. 회원 그룹을 만든 뒤 선택할 수 있습니다.</p>
                <?php } else { ?>
                    <?php foreach ($memberGroups as $group) { ?>
                        <?php
                        $groupKey = (string) ($group['group_key'] ?? '');
                        $disabled = (string) ($group['status'] ?? '') !== 'enabled';
                        ?>
                        <label class="admin-check-option" for="reward_withdrawal_group_<?php echo sr_e($groupKey); ?>">
                            <input id="reward_withdrawal_group_<?php echo sr_e($groupKey); ?>" type="checkbox" name="withdrawal_allowed_group_keys[]" value="<?php echo sr_e($groupKey); ?>" class="form-checkbox"<?php echo isset($allowedGroupMap[$groupKey]) ? ' checked' : ''; ?><?php echo $disabled ? ' disabled' : ''; ?>>
                            <span><?php echo sr_e((string) ($group['title'] ?? $groupKey)); ?> (<?php echo sr_e($groupKey); ?>)</span>
                        </label>
                    <?php } ?>
                    <p class="admin-form-help">선택한 그룹 중 하나에 속한 회원만 적립금 출금 신청을 할 수 있습니다. 아무 그룹도 선택하지 않으면 회원이 출금 신청을 할 수 없습니다.</p>
                <?php } ?>
            </div>
        </div>
    </section>

    <div class="admin-form-actions admin-form-sticky-actions">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
