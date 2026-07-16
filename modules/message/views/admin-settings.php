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
$messageSettingsHelpOpenLabel = '도움말 보기';
$messageSettingsHelp = [
    'feature' => [
        'id' => 'message-settings-help-feature',
        'title' => '쪽지 기능 도움말',
        'body' => '<p>끄면 관리자 여부와 관계없이 새 쪽지를 보내거나 기존 쪽지를 열 수 없습니다.</p>'
            . '<p>기존 쪽지와 회원별 수신 설정은 삭제하지 않습니다. 기능을 다시 켜면 보관된 쪽지를 다시 확인할 수 있습니다.</p>',
    ],
    'send_policy' => [
        'id' => 'message-settings-help-send-policy',
        'title' => '발신 범위 도움말',
        'body' => '<p><strong>전체 회원</strong>은 수신 조건을 충족하는 모든 일반 회원이 보낼 수 있고, <strong>지정 회원 그룹</strong>은 아래에서 고른 그룹에 속한 회원만 보낼 수 있습니다. <strong>사용 안 함</strong>은 일반 회원의 발신을 막습니다.</p>'
            . '<p>일반 회원은 자신의 쪽지 수신도 허용된 상태여야 보낼 수 있습니다. 쪽지 설정 권한이나 회원 조회 권한이 있는 관리자는 운영 안내를 위해 발신 범위와 일반 회원의 수신 거부를 적용받지 않습니다.</p>',
    ],
    'receive_policy' => [
        'id' => 'message-settings-help-receive-policy',
        'title' => '수신 범위 도움말',
        'body' => '<p><strong>전체 회원</strong>은 수신을 허용한 모든 활성 회원, <strong>지정 회원 그룹</strong>은 아래에서 고른 그룹에 속하면서 수신을 허용한 회원에게 보낼 수 있습니다.</p>'
            . '<p><strong>수신 동의 회원</strong>은 회원별 수신 설정이 저장되어 있고 수신 허용 상태인 회원만 받습니다. <strong>사용 안 함</strong>은 일반 회원이 보내는 쪽지를 모두 막습니다.</p>'
            . '<p>관리자가 운영 안내를 보내는 경우에는 수신 범위와 회원별 수신 거부를 적용하지 않지만, 탈퇴 등 비활성 계정에는 보낼 수 없습니다.</p>',
    ],
    'member_receive' => [
        'id' => 'message-settings-help-member-receive',
        'title' => '회원별 수신 선택 도움말',
        'body' => '<p>켜면 회원가입 화면에 쪽지 수신 허용 항목을 표시하고 회원이 직접 선택한 값을 저장합니다.</p>'
            . '<p>끄면 회원가입 화면에서 선택 항목을 숨기고 아래 기본 수신 허용 값을 저장합니다. 이미 저장된 회원별 수신 설정은 바뀌지 않습니다.</p>',
    ],
    'rate_limit' => [
        'id' => 'message-settings-help-rate-limit',
        'title' => '발송 횟수 제한 도움말',
        'body' => '<p>같은 회원이 설정한 시간 동안 보낼 수 있는 쪽지 발송 횟수를 제한해 반복 발송을 줄입니다.</p>'
            . '<p>여러 수신자에게 한 번에 보내더라도 한 번의 제출을 1건으로 계산합니다. 제한에 도달하면 집계 시간이 지난 뒤 다시 보낼 수 있습니다.</p>',
    ],
];

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
                <?php echo sr_admin_form_label_help_html('message_admin_enabled', '쪽지 기능', $messageSettingsHelp['feature']['id'], $messageSettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_enabled', 'message_enabled', '1', !empty($settings['message_enabled']), '사용'); ?>
                    <small class="form-help">끄면 새 쪽지 발송과 기존 쪽지 열람을 중단합니다. 보관된 쪽지는 삭제하지 않습니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-permission" class="card" data-admin-section-anchor>
        <h2>발신/수신</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_send_policy', '발신 범위', $messageSettingsHelp['send_policy']['id'], $messageSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <select id="message_admin_send_policy" name="send_policy" class="form-select" required data-message-policy="send">
                        <?php foreach ($messagePolicyOptions as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e($policyKey); ?>"<?php echo $messageSendPolicy === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                        <?php } ?>
                    </select>
                    <small class="form-help">일반 회원 중 쪽지를 보낼 수 있는 범위를 정합니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_send_group_keys_select">발신 가능 회원 그룹 <span class="sr-required-label" data-message-group-required-label="send"<?php echo $messageSendPolicy === 'group' ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field" data-message-group-root="send">
                    <?php echo sr_admin_member_group_key_badge_select_html('message_admin_send_group_keys', 'send_group_keys', (array) ($settings['send_group_keys'] ?? []), $memberGroups); ?>
                    <small class="form-help">발신 범위가 ‘지정 회원 그룹’일 때 하나 이상 선택합니다. 선택 가능: <?php echo sr_e(number_format($messageMemberGroupCount)); ?>개</small>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_receive_policy', '수신 범위', $messageSettingsHelp['receive_policy']['id'], $messageSettingsHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <select id="message_admin_receive_policy" name="receive_policy" class="form-select" required data-message-policy="receive">
                        <?php foreach ($messageReceivePolicyOptions as $policyKey => $policyLabel) { ?>
                            <option value="<?php echo sr_e($policyKey); ?>"<?php echo $messageReceivePolicy === $policyKey ? ' selected' : ''; ?>><?php echo sr_e($policyLabel); ?></option>
                        <?php } ?>
                    </select>
                    <small class="form-help">일반 회원이 보낸 쪽지를 받을 수 있는 회원 범위를 정합니다.</small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="message_admin_receive_group_keys_select">수신 가능 회원 그룹 <span class="sr-required-label" data-message-group-required-label="receive"<?php echo $messageReceivePolicy === 'group' ? '' : ' hidden'; ?>>(필수)</span></label>
                <div class="form-field" data-message-group-root="receive">
                    <?php echo sr_admin_member_group_key_badge_select_html('message_admin_receive_group_keys', 'receive_group_keys', (array) ($settings['receive_group_keys'] ?? []), $memberGroups); ?>
                    <small class="form-help">수신 범위가 ‘지정 회원 그룹’일 때 하나 이상 선택합니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-member" class="card" data-admin-section-anchor>
        <h2>회원 설정</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_member_receive_opt_enabled', '회원별 수신 선택', $messageSettingsHelp['member_receive']['id'], $messageSettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_member_receive_opt_enabled', 'member_receive_opt_enabled', '1', !empty($settings['member_receive_opt_enabled']), '사용'); ?>
                    <small class="form-help">켜면 회원가입할 때 쪽지 수신 여부를 직접 선택할 수 있습니다.</small>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_default_receive_enabled', '기본 수신 허용', $messageSettingsHelp['member_receive']['id'], $messageSettingsHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_switch_html('message_admin_default_receive_enabled', 'default_member_receive_enabled', '1', !empty($settings['default_member_receive_enabled']), '사용'); ?>
                    <small class="form-help">저장된 회원별 수신 설정이 없을 때 적용할 기본값입니다.</small>
                </div>
            </div>
        </div>
    </section>

    <section id="message-settings-section-limit" class="card" data-admin-section-anchor>
        <h2>발송 제한</h2>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_create_window_seconds', '발송 횟수 집계 시간', $messageSettingsHelp['rate_limit']['id'], $messageSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group admin-input-unit">
                        <input id="message_admin_create_window_seconds" type="number" name="message_create_window_seconds" min="60" max="86400" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_window_seconds'] ?? 300)); ?>">
                        <span class="input-group-text">초</span>
                    </div>
                    <small class="form-help">발송 횟수를 합산할 시간입니다. 60초부터 86,400초까지 입력합니다.</small>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('message_admin_create_limit', '최대 발송 횟수', $messageSettingsHelp['rate_limit']['id'], $messageSettingsHelpOpenLabel, true); ?>
                <div class="form-field">
                    <div class="input-group admin-input-unit">
                        <input id="message_admin_create_limit" type="number" name="message_create_limit" min="1" max="200" required class="form-input" value="<?php echo sr_e((string) ($settings['message_create_limit'] ?? 20)); ?>">
                        <span class="input-group-text">건</span>
                    </div>
                    <small class="form-help">위 시간 동안 같은 회원이 제출할 수 있는 최대 횟수입니다. 1건부터 200건까지 입력합니다.</small>
                </div>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($messageSettingsHelp as $messageSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $messageSettingsHelpModal['id'], (string) $messageSettingsHelpModal['title'], (string) $messageSettingsHelpModal['body']); ?>
<?php } ?>

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
