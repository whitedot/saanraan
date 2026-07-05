<?php

$pageTitle = '쪽지 환경설정';
$policyOptions = [
    'all' => '전체 회원',
    'group' => '지정 회원 그룹',
    'opt_in' => '수신 동의 회원',
    'disabled' => '사용 안 함',
];
?>
<div class="admin-page admin-message-settings">
    <div class="admin-page-header">
        <h1><?php echo sr_e($pageTitle); ?></h1>
    </div>

    <?php echo sr_admin_feedback_toasts($notice, $errors); ?>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/message/settings')); ?>" class="admin-form">
        <?php echo sr_csrf_field(); ?>

        <div class="form-row">
            <label for="message_admin_enabled">쪽지 사용</label>
            <label class="form-check">
                <input id="message_admin_enabled" type="checkbox" name="message_enabled" value="1"<?php echo !empty($settings['message_enabled']) ? ' checked' : ''; ?>>
                <span>회원 간 쪽지 기능을 사용합니다.</span>
            </label>
        </div>

        <div class="form-row">
            <label for="message_admin_send_policy">발신 정책 <span class="sr-required-label">(필수)</span></label>
            <select id="message_admin_send_policy" name="send_policy" class="form-select" required>
                <?php foreach ($policyOptions as $policyKey => $policyLabel) { ?>
                    <?php if ($policyKey === 'opt_in') { continue; } ?>
                    <option value="<?php echo sr_e($policyKey); ?>"<?php echo (string) ($settings['send_policy'] ?? 'all') === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-row">
            <label for="message_admin_send_group_keys">발신 가능 회원 그룹</label>
            <select id="message_admin_send_group_keys" name="send_group_keys[]" class="form-select" multiple>
                <?php foreach ($memberGroups as $group) { ?>
                    <?php $groupKey = (string) ($group['group_key'] ?? ''); ?>
                    <?php if ($groupKey === '') { continue; } ?>
                    <option value="<?php echo sr_e($groupKey); ?>"<?php echo in_array($groupKey, (array) ($settings['send_group_keys'] ?? []), true) ? ' selected' : ''; ?>><?php echo sr_e((string) ($group['title'] ?? $groupKey)); ?></option>
                <?php } ?>
            </select>
            <p class="form-help">발신 정책이 지정 회원 그룹일 때 사용합니다.</p>
        </div>

        <div class="form-row">
            <label for="message_admin_receive_policy">수신 정책 <span class="sr-required-label">(필수)</span></label>
            <select id="message_admin_receive_policy" name="receive_policy" class="form-select" required>
                <?php foreach ($policyOptions as $policyKey => $policyLabel) { ?>
                    <option value="<?php echo sr_e($policyKey); ?>"<?php echo (string) ($settings['receive_policy'] ?? 'all') === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                <?php } ?>
            </select>
        </div>

        <div class="form-row">
            <label for="message_admin_receive_group_keys">수신 가능 회원 그룹</label>
            <select id="message_admin_receive_group_keys" name="receive_group_keys[]" class="form-select" multiple>
                <?php foreach ($memberGroups as $group) { ?>
                    <?php $groupKey = (string) ($group['group_key'] ?? ''); ?>
                    <?php if ($groupKey === '') { continue; } ?>
                    <option value="<?php echo sr_e($groupKey); ?>"<?php echo in_array($groupKey, (array) ($settings['receive_group_keys'] ?? []), true) ? ' selected' : ''; ?>><?php echo sr_e((string) ($group['title'] ?? $groupKey)); ?></option>
                <?php } ?>
            </select>
            <p class="form-help">수신 정책이 지정 회원 그룹일 때 사용합니다.</p>
        </div>

        <div class="form-row">
            <label for="message_admin_member_receive_opt_enabled">회원별 수신 설정</label>
            <label class="form-check">
                <input id="message_admin_member_receive_opt_enabled" type="checkbox" name="member_receive_opt_enabled" value="1"<?php echo !empty($settings['member_receive_opt_enabled']) ? ' checked' : ''; ?>>
                <span>회원이 쪽지 수신 여부를 선택할 수 있습니다.</span>
            </label>
        </div>

        <div class="form-row">
            <label for="message_admin_default_receive_enabled">기본 수신 허용</label>
            <label class="form-check">
                <input id="message_admin_default_receive_enabled" type="checkbox" name="default_member_receive_enabled" value="1"<?php echo !empty($settings['default_member_receive_enabled']) ? ' checked' : ''; ?>>
                <span>회원별 설정이 없을 때 수신을 허용합니다.</span>
            </label>
        </div>

        <div class="form-row">
            <label for="message_admin_create_window_seconds">발송 제한 시간 <span class="sr-required-label">(필수)</span></label>
            <input id="message_admin_create_window_seconds" type="number" name="message_create_window_seconds" min="60" max="86400" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_window_seconds'] ?? 300)); ?>">
            <p class="form-help">초 단위입니다.</p>
        </div>

        <div class="form-row">
            <label for="message_admin_create_limit">발송 제한 건수 <span class="sr-required-label">(필수)</span></label>
            <input id="message_admin_create_limit" type="number" name="message_create_limit" min="1" max="200" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_limit'] ?? 20)); ?>">
        </div>

        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
</div>
