<?php

$adminPageTitle = '쪽지 환경설정';
$adminPageSubtitle = '회원 간 쪽지 사용 여부, 발신/수신 범위, 회원별 수신 선택과 발송 제한을 관리합니다.';
$adminContainerClass = 'admin-message-settings';
$messagePolicyOptions = [
    'all' => '전체 회원',
    'group' => '지정 회원 그룹',
    'disabled' => '사용 안 함',
];
$messageReceivePolicyOptions = [
    'all' => '전체 회원',
    'group' => '지정 회원 그룹',
    'opt_in' => '수신 동의 회원',
    'disabled' => '사용 안 함',
];
$messageSettingsSectionNavItems = [
    'message-settings-section-general' => '기본',
    'message-settings-section-permission' => '발신/수신',
    'message-settings-section-member' => '회원 설정',
    'message-settings-section-limit' => '발송 제한',
];
$messageMemberGroupCount = is_array($memberGroups) ? count($memberGroups) : 0;
$messageSendPolicy = (string) ($settings['send_policy'] ?? 'all');
$messageReceivePolicy = (string) ($settings['receive_policy'] ?? 'all');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="쪽지 설정 섹션">
    <?php $messageSettingsSectionNavIndex = 0; ?>
    <?php foreach ($messageSettingsSectionNavItems as $messageSettingsSectionId => $messageSettingsSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $messageSettingsSectionId); ?>" class="tab-trigger-underline-justified<?php echo $messageSettingsSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $messageSettingsSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $messageSettingsSectionLabel); ?>
        </a>
        <?php $messageSettingsSectionNavIndex++; ?>
    <?php } ?>
</nav>

<form method="post" action="<?php echo sr_e(sr_url('/admin/message/settings')); ?>" class="admin-form ui-form-theme" data-message-settings-form>
    <?php echo sr_csrf_field(); ?>

    <section id="message-settings-section-general" class="card" data-admin-section-anchor>
        <h2>기본</h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label">쪽지 기능</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_enabled', 'message_enabled', '1', !empty($settings['message_enabled']), '사용'); ?>
                    <small class="form-help">끄면 일반 회원의 쪽지 발신과 수신이 모두 중단됩니다. 관리자 권한 확인 화면과 기존 쪽지 보관 데이터는 삭제하지 않습니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-permission" class="card" data-admin-section-anchor>
        <h2>발신/수신</h2>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="message_admin_send_policy">발신 정책 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <select id="message_admin_send_policy" name="send_policy" class="form-select" required data-message-policy="send">
                        <?php foreach ($messagePolicyOptions as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e($policyKey); ?>"<?php echo $messageSendPolicy === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                        <?php } ?>
                    </select>
                    <small class="form-help">관리자 권한을 가진 계정은 운영 처리를 위해 발신 제한을 우회할 수 있습니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_send_group_keys_select">발신 가능 회원 그룹 <span class="sr-required-label" data-message-group-required-label="send"<?php echo $messageSendPolicy === 'group' ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field" data-message-group-root="send">
                    <?php echo sr_admin_member_group_key_badge_select_html('message_admin_send_group_keys', 'send_group_keys', (array) ($settings['send_group_keys'] ?? []), $memberGroups); ?>
                    <small class="form-help">발신 정책이 지정 회원 그룹일 때 적용합니다. 현재 선택 가능한 회원 그룹은 <?php echo sr_e(number_format($messageMemberGroupCount)); ?>개입니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_receive_policy">수신 정책 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <select id="message_admin_receive_policy" name="receive_policy" class="form-select" required data-message-policy="receive">
                        <?php foreach ($messageReceivePolicyOptions as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e($policyKey); ?>"<?php echo $messageReceivePolicy === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                        <?php } ?>
                    </select>
                    <small class="form-help">수신 동의 회원은 회원별 수신 설정 row가 있고 수신 허용 상태인 회원만 받을 수 있습니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_receive_group_keys_select">수신 가능 회원 그룹 <span class="sr-required-label" data-message-group-required-label="receive"<?php echo $messageReceivePolicy === 'group' ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field" data-message-group-root="receive">
                    <?php echo sr_admin_member_group_key_badge_select_html('message_admin_receive_group_keys', 'receive_group_keys', (array) ($settings['receive_group_keys'] ?? []), $memberGroups); ?>
                    <small class="form-help">수신 정책이 지정 회원 그룹일 때 적용합니다. 발신자가 관리자 권한을 가진 경우 운영 처리를 위해 수신 제한을 우회할 수 있습니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-member" class="card" data-admin-section-anchor>
        <h2>회원 설정</h2>
        <div class="form-grid">
            <div class="form-row">
                <span class="form-label">회원별 수신 설정</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_member_receive_opt_enabled', 'member_receive_opt_enabled', '1', !empty($settings['member_receive_opt_enabled']), '사용'); ?>
                    <small class="form-help">사용하면 회원이 계정 화면에서 쪽지 수신 여부를 선택할 수 있습니다.</small>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label">기본 수신 허용</span>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_default_receive_enabled', 'default_member_receive_enabled', '1', !empty($settings['default_member_receive_enabled']), '사용'); ?>
                    <small class="form-help">회원별 수신 설정 row가 아직 없는 회원에게 적용할 기본값입니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-limit" class="card" data-admin-section-anchor>
        <h2>발송 제한</h2>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="message_admin_create_window_seconds">발송 제한 시간 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="message_admin_create_window_seconds" type="number" name="message_create_window_seconds" min="60" max="86400" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_window_seconds'] ?? 300)); ?>">
                    <small class="form-help">초 단위입니다. 60초부터 86400초까지 입력합니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_create_limit">발송 제한 건수 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="message_admin_create_limit" type="number" name="message_create_limit" min="1" max="200" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_limit'] ?? 20)); ?>">
                    <small class="form-help">위 시간 안에 같은 회원이 보낼 수 있는 최대 쪽지 수입니다. 1건부터 200건까지 입력합니다.</small>
                </div>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-message-settings-form]');
    if (!form) {
        return;
    }

    function groupRoot(kind) {
        return form.querySelector('[data-message-group-root="' + kind + '"]');
    }

    function groupSelect(kind) {
        var root = groupRoot(kind);
        return root ? root.querySelector('[data-admin-select-badge-list-select]') : null;
    }

    function selectedGroupCount(kind) {
        var root = groupRoot(kind);
        return root ? root.querySelectorAll('[data-admin-select-badge-value]').length : 0;
    }

    function updateGroupRequirement(kind) {
        var policy = form.querySelector('[data-message-policy="' + kind + '"]');
        var select = groupSelect(kind);
        var requiredLabel = form.querySelector('[data-message-group-required-label="' + kind + '"]');
        var required = !!policy && policy.value === 'group';
        var valid = !required || selectedGroupCount(kind) > 0;

        if (requiredLabel) {
            requiredLabel.hidden = !required;
        }
        if (select) {
            select.setCustomValidity(valid ? '' : '회원 그룹을 하나 이상 선택해 주세요.');
        }
    }

    ['send', 'receive'].forEach(function (kind) {
        var policy = form.querySelector('[data-message-policy="' + kind + '"]');
        var root = groupRoot(kind);
        if (policy) {
            policy.addEventListener('change', function () {
                updateGroupRequirement(kind);
            });
        }
        if (root) {
            root.addEventListener('click', function () {
                window.setTimeout(function () {
                    updateGroupRequirement(kind);
                }, 0);
            });
            root.addEventListener('change', function () {
                updateGroupRequirement(kind);
            });
        }
        updateGroupRequirement(kind);
    });

    form.addEventListener('submit', function (event) {
        updateGroupRequirement('send');
        updateGroupRequirement('receive');
        if (!form.reportValidity()) {
            event.preventDefault();
        }
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
