<?php

$adminPageTitle = sr_t('member::ui.member.list.d8e6279a');
$adminPageSubtitle = [
    sr_t('member::ui.status.member.login.1e2b02c0'),
];
$adminContainerClass = 'admin-page-member-list admin-ui-scope';
$memberAdminPage = isset($memberAdminPage) ? (string) $memberAdminPage : 'members';
$memberMessageWriteAvailable = !empty($memberMessageWriteAvailable);
if ($memberAdminPage === 'create_form') {
    $adminPageTitle = sr_t('member::ui.member.e9679572');
    $adminPageSubtitle = '가입 안내와 초기 비밀번호 전달 절차를 함께 확인하세요.';
} elseif ($memberAdminPage === 'edit_form') {
    $adminPageTitle = sr_t('member::ui.member.edit.7eaadfda');
    $adminPageSubtitle = '';
}
$statusCounts = isset($statusCounts) && is_array($statusCounts) ? $statusCounts : [];
$totalMembers = (int) ($statusCounts['total'] ?? count($members));
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$statusFilter = isset($statusFilter) && is_array($statusFilter) ? $statusFilter : [];
$adminPageTitleUrl = sr_admin_page_title_reset_url($memberAdminPage === 'members', '/admin/members');
$memberSort = isset($memberSort) && is_array($memberSort) ? $memberSort : sr_admin_member_default_sort();
$memberCreateValues = isset($memberCreateValues) && is_array($memberCreateValues) ? $memberCreateValues : sr_admin_member_create_default_values($site ?? []);
$memberEditValues = isset($memberEditValues) && is_array($memberEditValues) ? $memberEditValues : [];
$memberAdminProfileExtraFieldDefinitions = isset($memberAdminProfileExtraFieldDefinitions) && is_array($memberAdminProfileExtraFieldDefinitions) ? $memberAdminProfileExtraFieldDefinitions : [];
$memberAdminProfileExtraValues = isset($memberAdminProfileExtraValues) && is_array($memberAdminProfileExtraValues) ? $memberAdminProfileExtraValues : [];
$memberAdminProfileExtraByKey = [];
foreach ($memberAdminProfileExtraFieldDefinitions as $memberAdminProfileExtraDefinition) {
    $memberAdminProfileExtraByKey[(string) ($memberAdminProfileExtraDefinition['key'] ?? '')] = $memberAdminProfileExtraDefinition;
}
$memberAdminProfileOrderItems = sr_member_profile_field_order_items($memberSettings ?? [], $memberAdminProfileExtraFieldDefinitions);
$memberTerminalStatuses = sr_admin_member_terminal_statuses();
$memberWithdrawalAssetWarnings = isset($memberWithdrawalAssetWarnings) && is_array($memberWithdrawalAssetWarnings) ? $memberWithdrawalAssetWarnings : [];
$memberEditWithdrawalAssetWarning = isset($memberEditWithdrawalAssetWarning) && is_array($memberEditWithdrawalAssetWarning) ? $memberEditWithdrawalAssetWarning : ['assets' => [], 'lines' => [], 'summary' => ''];
$memberTerminalAssetFollowup = [];
if (isset($flashResult['data']['terminal_asset_followup']) && is_array($flashResult['data']['terminal_asset_followup'])) {
    $memberTerminalAssetFollowup = $flashResult['data']['terminal_asset_followup'];
}
$memberEditWithdrawalAssetSummary = trim((string) ($memberEditWithdrawalAssetWarning['summary'] ?? ''));
if ($memberEditWithdrawalAssetSummary === '') {
    $memberEditWithdrawalAssetSummary = '없음';
}
$memberEditMarketingConsent = isset($memberEditMarketingConsent) && is_array($memberEditMarketingConsent) ? $memberEditMarketingConsent : null;
$memberEditWithdrawConfirmMessage = sr_admin_member_terminal_status_confirm_message('withdrawn', $memberEditWithdrawalAssetWarning);
$memberEditAnonymizeConfirmMessage = sr_admin_member_terminal_status_confirm_message('anonymized', $memberEditWithdrawalAssetWarning);
$memberEditHasActionContext = $memberAdminPage === 'edit_form' && is_array($editMember ?? null);
$memberEditAccountId = $memberEditHasActionContext ? (int) ($editMember['id'] ?? 0) : 0;
$memberEditStatus = $memberEditHasActionContext ? (string) ($editMember['status'] ?? '') : '';
$memberEditPublicHash = $memberEditHasActionContext ? sr_admin_member_public_hash($runtimeConfig, $memberEditAccountId) : '';
$memberEditReturnTo = $memberEditHasActionContext ? sr_admin_current_get_url('/admin/members/edit?id=' . rawurlencode((string) $memberEditAccountId)) : '';
$memberEditActionFormPrefix = $memberEditHasActionContext ? 'member-edit-action-' . $memberEditAccountId . '-' : '';
$memberEditCanMessage = $memberEditHasActionContext && $memberMessageWriteAvailable && $memberEditStatus === 'active' && $memberEditPublicHash !== '';
$memberEditCanSuspend = $memberEditHasActionContext && !in_array($memberEditStatus, $memberTerminalStatuses, true) && $memberEditStatus !== 'suspended';
$memberEditCanWithdraw = $memberEditHasActionContext && !in_array($memberEditStatus, $memberTerminalStatuses, true);
$memberEditCanAnonymize = $memberEditHasActionContext && $memberEditStatus !== 'anonymized';
$memberEditCanEvaluateGroups = $memberEditHasActionContext && !in_array($memberEditStatus, $memberTerminalStatuses, true);
$memberAdminProfileExtraFieldHtml = static function (array $definition, array $values): string {
    $key = (string) ($definition['key'] ?? '');
    if ($key === '') {
        return '';
    }

    $label = (string) ($definition['label'] ?? $key);
    $type = (string) ($definition['type'] ?? 'text');
    $required = !empty($definition['required']);
    $id = 'modules_member_admin_edit_profile_extra_' . $key;
    $name = 'member_profile_fields[' . $key . ']';
    $rawValue = $values[$key] ?? '';
    $value = is_array($rawValue) ? '' : (string) $rawValue;
    $requiredHtml = $required ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : '';
    $requiredAttribute = $required ? ' required' : '';
    $html = '<div class="form-row">';
    $html .= '<label class="form-label" for="' . sr_e($id) . '">' . sr_e($label) . $requiredHtml . '</label>';
    $html .= '<div class="form-field">';
    if ($type === 'textarea') {
        $html .= '<textarea id="' . sr_e($id) . '" name="' . sr_e($name) . '" rows="4" maxlength="5000" class="form-textarea form-control-full"' . $requiredAttribute . '>' . sr_e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $html .= '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '" class="form-select"' . $requiredAttribute . '>';
        $html .= '<option value="">' . sr_e('선택') . '</option>';
        foreach ((array) ($definition['options'] ?? []) as $option) {
            $option = (string) $option;
            $html .= '<option value="' . sr_e($option) . '"' . ($value === $option ? ' selected' : '') . '>' . sr_e($option) . '</option>';
        }
        $html .= '</select>';
    } elseif ($type === 'checkbox') {
        $html .= '<label class="form-check form-label" for="' . sr_e($id) . '">';
        $html .= '<input id="' . sr_e($id) . '" type="checkbox" name="' . sr_e($name) . '" value="1" class="form-checkbox"' . ($value === '1' ? ' checked' : '') . $requiredAttribute . '>';
        $html .= sr_admin_choice_label_html($label);
        $html .= '</label>';
    } else {
        $html .= '<input id="' . sr_e($id) . '" type="text" name="' . sr_e($name) . '" maxlength="1000" value="' . sr_e($value) . '" class="form-input form-control-full"' . $requiredAttribute . '>';
    }
    $html .= '</div></div>';

    return $html;
};
$memberMarketingConsentBadgeHtml = static function (?array $consent): string {
    if ($consent === null) {
        return '<span class="badge badge-outline-secondary">기록 없음</span>';
    }

    $consented = !empty($consent['consented']);
    $titleParts = [];
    $documentTitle = trim((string) ($consent['consent_title_snapshot'] ?? ''));
    if ($documentTitle !== '') {
        $titleParts[] = '문서: ' . $documentTitle;
    }
    $version = trim((string) ($consent['consent_version'] ?? ''));
    if ($version !== '') {
        $titleParts[] = '버전: ' . $version;
    }
    $createdAt = trim((string) ($consent['created_at'] ?? ''));
    if ($createdAt !== '') {
        $titleParts[] = '기록: ' . $createdAt;
    }

    return '<span class="badge ' . ($consented ? 'badge-soft-success' : 'badge-soft-danger') . '"' . ($titleParts !== [] ? ' title="' . sr_e(implode(' · ', $titleParts)) . '"' : '') . '>'
        . sr_e($consented ? '동의' : '미동의')
        . '</span>';
};
$createStatuses = sr_admin_member_create_allowed_statuses();
$memberLocaleOptions = sr_supported_locales($site ?? null);
$memberAdminHelpOpenLabel = sr_t('member::help.open');
$memberAdminHelp = [
    'public_hash' => [
        'id' => 'member-admin-help-public-hash-modal',
        'title' => sr_t('member::help.members.public_hash.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.public_hash.body.1',
            'member::help.members.public_hash.body.2',
        ]),
    ],
    'email' => [
        'id' => 'member-admin-help-email-modal',
        'title' => sr_t('member::help.members.email.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.email.body.1',
            'member::help.members.email.body.2',
        ]),
    ],
    'login_id' => [
        'id' => 'member-admin-help-login-id-modal',
        'title' => sr_t('member::help.members.login_id.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.login_id.body.1',
            'member::help.members.login_id.body.2',
        ]),
    ],
    'password' => [
        'id' => 'member-admin-help-password-modal',
        'title' => sr_t('member::help.members.password.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.password.body.1',
            'member::help.members.password.body.2',
        ]),
    ],
    'locale' => [
        'id' => 'member-admin-help-locale-modal',
        'title' => sr_t('member::help.members.locale.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.locale.body.1',
            'member::help.members.locale.body.2',
        ]),
    ],
    'status' => [
        'id' => 'member-admin-help-status-modal',
        'title' => sr_t('member::help.members.status.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.status.body.1',
            'member::help.members.status.body.2',
            'member::help.members.status.body.3',
        ]),
    ],
    'email_verified' => [
        'id' => 'member-admin-help-email-verified-modal',
        'title' => sr_t('member::help.members.email_verified.title'),
        'body_html' => sr_member_admin_help_body_html([
            'member::help.members.email_verified.body.1',
            'member::help.members.email_verified.body.2',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($memberEditHasActionContext) { ?>
    <div class="admin-member-edit-actions" aria-label="회원 관리">
        <div class="admin-member-edit-action-group admin-member-edit-action-group-normal" aria-label="일반 작업">
            <?php if ($memberEditCanMessage) { ?>
                <a href="<?php echo sr_e(sr_url('/message/write?to_account=' . rawurlencode($memberEditPublicHash))); ?>" class="btn btn-sm btn-outline-secondary" aria-label="쪽지 발송" title="쪽지 발송"><?php echo sr_material_icon_html('mail'); ?><span>쪽지 발송</span></a>
            <?php } ?>
            <?php if ($memberEditCanEvaluateGroups) { ?>
                <button type="submit" form="<?php echo sr_e($memberEditActionFormPrefix . 'evaluate-groups'); ?>" class="btn btn-sm btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>" title="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>"><?php echo sr_material_icon_html('rule'); ?><span><?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?></span></button>
            <?php } ?>
        </div>
        <div class="admin-member-edit-action-group admin-member-edit-action-group-risk" aria-label="위험 작업">
            <?php if ($memberEditCanSuspend) { ?>
                <button type="submit" form="<?php echo sr_e($memberEditActionFormPrefix . 'suspend'); ?>" class="btn btn-sm btn-outline-warning" aria-label="회원 차단" title="회원 차단"><?php echo sr_material_icon_html('block'); ?><span>회원 차단</span></button>
            <?php } ?>
            <?php if ($memberEditCanWithdraw) { ?>
                <button type="submit" form="<?php echo sr_e($memberEditActionFormPrefix . 'withdraw'); ?>" class="btn btn-sm btn-outline-danger" aria-label="회원 탈퇴 처리" title="회원 탈퇴 처리"><?php echo sr_material_icon_html('person_remove'); ?><span>회원 탈퇴 처리</span></button>
            <?php } ?>
            <?php if ($memberEditCanAnonymize) { ?>
                <button type="submit" form="<?php echo sr_e($memberEditActionFormPrefix . 'anonymize'); ?>" class="btn btn-sm btn-outline-danger" aria-label="회원 익명화" title="회원 익명화"><?php echo sr_material_icon_html('no_accounts'); ?><span>회원 익명화</span></button>
            <?php } ?>
            <button type="submit" form="<?php echo sr_e($memberEditActionFormPrefix . 'revoke-sessions'); ?>" class="btn btn-sm btn-outline-danger" aria-label="<?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?>" title="<?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?>"><?php echo sr_material_icon_html('delete'); ?><span><?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?></span></button>
        </div>
    </div>
<?php } ?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberTerminalAssetFollowup !== []) { ?>
    <?php
    $memberTerminalAssetFollowupLinks = isset($memberTerminalAssetFollowup['links']) && is_array($memberTerminalAssetFollowup['links']) ? $memberTerminalAssetFollowup['links'] : [];
    ?>
    <div class="alert alert-info admin-member-terminal-followup" role="status">
        <strong>보유 자산 후속 확인</strong>
        <p>
            계정 #<?php echo sr_e((string) (int) ($memberTerminalAssetFollowup['account_id'] ?? 0)); ?>
            · 공개 해시 <code><?php echo sr_e((string) ($memberTerminalAssetFollowup['account_public_hash'] ?? '')); ?></code>
            · 처리 전 보유 자산: <?php echo sr_e((string) ($memberTerminalAssetFollowup['asset_summary'] ?? '')); ?>
        </p>
        <p>탈퇴/익명화 처리는 자산을 자동 정산하지 않았습니다. 필요한 화면에서 이 계정을 바로 조회해 후속 처리하세요.</p>
        <?php if ($memberTerminalAssetFollowupLinks !== []) { ?>
            <div class="admin-row-actions admin-member-terminal-followup-actions">
                <?php foreach ($memberTerminalAssetFollowupLinks as $memberTerminalAssetFollowupLink) { ?>
                    <?php
                    $followupLinkUrl = (string) ($memberTerminalAssetFollowupLink['url'] ?? '');
                    $followupLinkLabel = (string) ($memberTerminalAssetFollowupLink['label'] ?? '');
                    if ($followupLinkUrl === '' || $followupLinkLabel === '') {
                        continue;
                    }
                    ?>
                    <a class="btn btn-sm btn-outline-info" href="<?php echo sr_e(sr_url($followupLinkUrl)); ?>"><?php echo sr_e($followupLinkLabel); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<?php if ($memberAdminPage === 'create_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create">
        <section class="card">
            <h2><?php echo sr_e(sr_t('member::ui.member.e9679572')); ?></h2>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('member_admin_create_email', sr_t('member::ui.email.3b7dbc4c'), $memberAdminHelp['email']['id'], $memberAdminHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="member_admin_create_email" type="email" name="email" value="<?php echo sr_e((string) ($memberCreateValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('member_admin_create_login_id', sr_t('member::ui.login.0cdb28b5'), $memberAdminHelp['login_id']['id'], $memberAdminHelpOpenLabel); ?>
                <div class="form-field">
                    <input id="member_admin_create_login_id" type="text" name="login_id" value="<?php echo sr_e((string) ($memberCreateValues['login_id'] ?? '')); ?>" class="form-input" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" inputmode="latin" autocapitalize="none" spellcheck="false" autocomplete="username" data-admin-login-id-input>
                    <small class="form-help"><?php echo sr_e(sr_t('member::ui.email.login.email.active.eb627985')); ?></small>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="member_admin_create_display_name"><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <input id="member_admin_create_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberCreateValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                <div class="form-row">
                    <label class="form-label" for="member_admin_create_nickname"><?php echo sr_e(sr_t('member::ui.nickname')); ?><?php echo !empty($memberSettings['nickname_required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></label>
                    <div class="form-field">
                        <input id="member_admin_create_nickname" type="text" name="nickname" value="<?php echo sr_e((string) ($memberCreateValues['nickname'] ?? '')); ?>" class="form-input form-control-full" maxlength="80"<?php echo !empty($memberSettings['nickname_required']) ? ' required' : ''; ?>>
                        <small class="form-help"><?php echo sr_e(sr_t('member::ui.nickname.help')); ?></small>
                    </div>
                </div>
            <?php } ?>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('member_admin_create_password', sr_t('member::ui.password.4fa210a0'), $memberAdminHelp['password']['id'], $memberAdminHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="member_admin_create_password" type="password" name="password" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="member_admin_create_password_confirm"><?php echo sr_e(sr_t('member::ui.password.61081c91')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="form-field">
                    <input id="member_admin_create_password_confirm" type="password" name="password_confirm" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('member_admin_create_locale', sr_t('member::help.members.locale.label'), $memberAdminHelp['locale']['id'], $memberAdminHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="member_admin_create_locale" name="locale" class="form-select" required>
                        <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                            <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberCreateValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                <?php echo sr_e($localeOption); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('member_admin_create_status', sr_t('member::ui.status.e10195a1'), $memberAdminHelp['status']['id'], $memberAdminHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="member_admin_create_status" name="status" class="form-select">
                        <?php foreach ($createStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberCreateValues['status'] ?? 'active') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.email.2f905abd'), $memberAdminHelp['email_verified']['id'], $memberAdminHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></span></span>
                <div class="form-field form-check">
                    <input id="member_admin_create_email_verified" type="checkbox" name="email_verified" value="1" class="form-switch form-switch-light"<?php echo (string) ($memberCreateValues['email_verified'] ?? '1') === '1' ? ' checked' : ''; ?>>
                    <label for="member_admin_create_email_verified"><?php echo sr_admin_choice_label_html('완료'); ?></label>
                </div>
            </div>
        </section>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } elseif ($memberAdminPage === 'edit_form') { ?>
    <?php if (is_array($editMember)) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme" data-sr-validate-form data-member-status-edit-form data-member-withdraw-confirm="<?php echo sr_e($memberEditWithdrawConfirmMessage); ?>" data-member-anonymize-confirm="<?php echo sr_e($memberEditAnonymizeConfirmMessage); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="edit">
            <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
            <section class="card">
                <h2><?php echo sr_e(sr_t('member::ui.member.edit.7eaadfda')); ?></h2>
                <div class="form-row">
                    <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.text.4ca2f9ab'), $memberAdminHelp['public_hash']['id'], $memberAdminHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.text.4ca2f9ab')); ?></span></span>
                    <div class="form-field">
                        <code><?php echo sr_e($memberEditPublicHash); ?></code>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('member_admin_edit_email', sr_t('member::ui.email.3b7dbc4c'), $memberAdminHelp['email']['id'], $memberAdminHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="member_admin_edit_email" type="email" name="email" value="<?php echo sr_e((string) ($memberEditValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="member_admin_edit_display_name"><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="member_admin_edit_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberEditValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <?php if (!empty($memberSettings['nickname_enabled'])) { ?>
                    <div class="form-row">
                        <label class="form-label" for="member_admin_edit_nickname"><?php echo sr_e(sr_t('member::ui.nickname')); ?><?php echo !empty($memberSettings['nickname_required']) ? ' <span class="sr-required-label">' . sr_e(sr_t('member::ui.required.1f227c67')) . '</span>' : ''; ?></label>
                        <div class="form-field">
                            <input id="member_admin_edit_nickname" type="text" name="nickname" value="<?php echo sr_e((string) ($memberEditValues['nickname'] ?? '')); ?>" class="form-input form-control-full" maxlength="80"<?php echo !empty($memberSettings['nickname_required']) ? ' required' : ''; ?>>
                            <small class="form-help"><?php echo sr_e(sr_t('member::ui.nickname.help')); ?></small>
                        </div>
                    </div>
                <?php } ?>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('member_admin_edit_locale', sr_t('member::help.members.locale.label'), $memberAdminHelp['locale']['id'], $memberAdminHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="member_admin_edit_locale" name="locale" class="form-select" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberEditValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('member_admin_edit_status', sr_t('member::ui.status.e10195a1'), $memberAdminHelp['status']['id'], $memberAdminHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="member_admin_edit_status" name="status" class="form-select" data-member-status-select data-current-status="<?php echo sr_e((string) ($editMember['status'] ?? '')); ?>">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberEditValues['status'] ?? '') === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <small class="form-help">현재 조회된 보유 자산: <?php echo sr_e($memberEditWithdrawalAssetSummary); ?></small>
                        <small class="form-help">관리자 탈퇴/익명화는 현재 보유 자산을 자동 정산하지 않습니다. 처리 후에도 계정 ID 또는 공개 해시로 자산 관리자 화면에서 조회해 후속 처리하세요.</small>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.email.2f905abd'), $memberAdminHelp['email_verified']['id'], $memberAdminHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></span></span>
                    <div class="form-field">
                        <?php echo sr_admin_switch_html('member_admin_edit_email_verified', 'email_verified', '1', (string) ($memberEditValues['email_verified'] ?? (((string) ($editMember['email_verified_at'] ?? '') !== '') ? '1' : '0')) === '1', '완료'); ?>
                        <?php if ((string) ($editMember['email_verified_at'] ?? '') !== '') { ?>
                            <small class="form-help">현재 인증 완료 시각: <?php echo sr_admin_time_html((string) $editMember['email_verified_at']); ?></small>
                        <?php } ?>
                    </div>
                </div>
            </section>
            <section class="card">
                <h2>동의 정보</h2>
                <div class="form-row">
                    <span class="form-label">마케팅 수신동의</span>
                    <div class="form-field">
                        <?php echo $memberMarketingConsentBadgeHtml($memberEditMarketingConsent); ?>
                        <?php if ($memberEditMarketingConsent === null) { ?>
                            <small class="form-help">회원 모듈의 최신 marketing 동의 기록이 없습니다.</small>
                        <?php } else { ?>
                            <small class="form-help">기록 시각: <?php echo sr_admin_time_html((string) ($memberEditMarketingConsent['created_at'] ?? ''), '-'); ?></small>
                            <?php if (trim((string) ($memberEditMarketingConsent['consent_title_snapshot'] ?? '')) !== '') { ?>
                                <small class="form-help">문서: <?php echo sr_e((string) $memberEditMarketingConsent['consent_title_snapshot']); ?></small>
                            <?php } ?>
                            <?php if (trim((string) ($memberEditMarketingConsent['consent_version'] ?? '')) !== '') { ?>
                                <small class="form-help">버전: <?php echo sr_e((string) $memberEditMarketingConsent['consent_version']); ?></small>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </div>
            </section>
            <?php if ($memberAdminProfileExtraFieldDefinitions !== []) { ?>
                <section class="card">
                    <h2>선택 프로필</h2>
                    <p class="form-help">회원 설정에서 관리자 수정 화면 표시를 켠 추가 프로필 항목입니다.</p>
                    <?php foreach ($memberAdminProfileOrderItems as $memberAdminProfileOrderItem) { ?>
                        <?php if ((string) ($memberAdminProfileOrderItem['kind'] ?? '') === 'extra') { ?>
                            <?php echo $memberAdminProfileExtraFieldHtml($memberAdminProfileExtraByKey[(string) ($memberAdminProfileOrderItem['key'] ?? '')] ?? [], $memberAdminProfileExtraValues); ?>
                        <?php } ?>
                    <?php } ?>
                </section>
            <?php } ?>
            <div class="form-sticky-actions form-actions form-actions-split">
                <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
            </div>
        </form>
        <?php if ($memberEditCanSuspend) { ?>
            <form id="<?php echo sr_e($memberEditActionFormPrefix . 'suspend'); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm('이 회원을 차단할까요? 활성 세션이 함께 폐기됩니다.');" hidden>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($memberEditReturnTo); ?>">
                <input type="hidden" name="intent" value="status">
                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
                <input type="hidden" name="status" value="suspended">
            </form>
        <?php } ?>
        <?php if ($memberEditCanWithdraw) { ?>
            <form id="<?php echo sr_e($memberEditActionFormPrefix . 'withdraw'); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm(<?php echo sr_e(sr_js_json_encode($memberEditWithdrawConfirmMessage)); ?>);" hidden>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($memberEditReturnTo); ?>">
                <input type="hidden" name="intent" value="status">
                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
                <input type="hidden" name="status" value="withdrawn">
            </form>
        <?php } ?>
        <?php if ($memberEditCanAnonymize) { ?>
            <form id="<?php echo sr_e($memberEditActionFormPrefix . 'anonymize'); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm(<?php echo sr_e(sr_js_json_encode($memberEditAnonymizeConfirmMessage)); ?>);" hidden>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($memberEditReturnTo); ?>">
                <input type="hidden" name="intent" value="status">
                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
                <input type="hidden" name="status" value="anonymized">
            </form>
        <?php } ?>
        <?php if ($memberEditCanEvaluateGroups) { ?>
            <form id="<?php echo sr_e($memberEditActionFormPrefix . 'evaluate-groups'); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form hidden>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="return_to" value="<?php echo sr_e($memberEditReturnTo); ?>">
                <input type="hidden" name="intent" value="evaluate_groups">
                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
            </form>
        <?php } ?>
        <form id="<?php echo sr_e($memberEditActionFormPrefix . 'revoke-sessions'); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form hidden>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="return_to" value="<?php echo sr_e($memberEditReturnTo); ?>">
            <input type="hidden" name="intent" value="revoke_sessions">
            <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberEditAccountId); ?>">
        </form>
    <?php } else { ?>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
        </div>
    <?php } ?>
<?php } else { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.964f82c2')); ?> <strong><?php echo sr_e((string) $totalMembers); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=suspended')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.c7d4f680')); ?> <?php echo sr_e((string) ($statusCounts['suspended'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=withdrawn')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.871d2076')); ?> <?php echo sr_e((string) (($statusCounts['withdrawn'] ?? 0) + ($statusCounts['anonymized'] ?? 0))); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></a>
    </div>
</div>

<?php
$selectedMemberStatuses = is_array($statusFilter ?? null) ? $statusFilter : [];
$memberDetailFilterOpen = $selectedMemberStatuses !== [];
$memberStatusFilterOptions = [];
foreach ($allowedStatuses as $status) {
    $memberStatusFilterOptions[$status] = sr_admin_code_label($status, 'member_status');
}
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="filtering-form admin-member-filter ui-form-theme">
    <div class="filtering-fields admin-member-search-grid">
        <div class="filtering filtering-card<?php echo $memberDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields">
                <div class="filtering-field admin-member-filter-field">
                    <label for="member-search-field" class="filtering-label">검색조건</label>
                    <select name="field" id="member-search-field" class="form-select filtering-input">
                        <?php foreach (['all' => sr_t('member::ui.all.a4b69faf'), 'hash' => sr_t('member::ui.text.93971787'), 'email' => sr_t('member::ui.email.3b7dbc4c'), 'login_id' => sr_t('member::ui.login.0cdb28b5'), 'name' => sr_t('member::ui.public_name')] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                <?php echo sr_e($fieldLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field-fill filtering-field admin-member-filter-keyword">
                    <label for="member-search-keyword" class="filtering-label"><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></label>
                    <input type="text" id="member-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" placeholder="<?php echo sr_e(sr_t('member::ui.email.login.name.c26ba637')); ?>">
                </div>
            </div>
            <div id="member_admin_detail_filters" class="filtering-body" data-filtering-body<?php echo $memberDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field admin-member-filter-status">
                    <span class="filtering-label"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></span>
                    <?php echo sr_admin_filter_toggle_group_html('admin-status-filter', 'status', $memberStatusFilterOptions, $selectedMemberStatuses, sr_t('member::ui.all.a4b69faf')); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $memberDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="member_admin_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.member.list.d8e6279a')); ?></h2>
        <a href="<?php echo sr_e(sr_url('/admin/members/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('member::ui.member.9df41111')); ?></a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($memberSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_member_sort_options(), sr_admin_member_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="회원 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <form id="member-bulk-session-form" method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="admin-member-bulk-form" data-member-bulk-session-form data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="batch_revoke_sessions">
            <input type="hidden" name="operation_key" value="member.revoke_sessions">
            <input type="hidden" name="return_to" value="<?php echo sr_e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/members')); ?>">
            <div class="admin-member-bulk-actions admin-row-actions" data-member-bulk-session-bar>
                <div class="admin-member-bulk-controls admin-row-actions">
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-member-bulk-session-submit disabled>세션 회수</button>
                    <button type="button" class="btn btn-sm btn-outline-light" data-member-bulk-session-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-member-selected-count>0</span></button>
                </div>
            </div>
        </form>
        <?php echo sr_admin_pagination_summary_html($memberPagination); ?>
    </div>
    <?php $memberListShowNicknameColumn = !empty($memberSettings['nickname_enabled']); ?>
    <div class="table-wrapper">
        <table class="table table-list admin-member-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('member::ui.member.list.5e737292')); ?></caption>
            <thead>
                <tr>
                    <th class="admin-table-checkbox-cell admin-member-select-cell">
                        <label class="sr-only" for="member_bulk_select_all">현재 페이지 회원 전체 선택</label>
                        <input id="member_bulk_select_all" type="checkbox" class="form-checkbox" data-member-select-all<?php echo $members === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('email', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.email.3b7dbc4c') . ' / ' . sr_t('member::ui.text.4ca2f9ab'), 'email', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('name', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.public_name'), 'name', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <?php if ($memberListShowNicknameColumn) { ?>
                        <th class="admin-member-mobile-optional"<?php echo sr_admin_sort_aria('nickname', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.nickname'), 'nickname', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <?php } ?>
                    <th class="admin-member-mobile-optional"<?php echo sr_admin_sort_aria('status', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.status.e10195a1'), 'status', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th class="admin-member-mobile-optional">마케팅 동의</th>
                    <th class="admin-member-session-cell"<?php echo sr_admin_sort_aria('active_session_count', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.fda1ae9a'), 'active_session_count', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members === []) { ?>
                    <tr>
                        <td colspan="<?php echo $memberListShowNicknameColumn ? '8' : '7'; ?>" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.d2605064')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($members as $member) { ?>
                    <?php
                    $memberStatus = (string) $member['status'];
                    $memberWithdrawalAssetWarning = $memberWithdrawalAssetWarnings[(int) ($member['id'] ?? 0)] ?? ['assets' => [], 'lines' => [], 'summary' => ''];
                    $memberWithdrawalAssetLines = isset($memberWithdrawalAssetWarning['lines']) && is_array($memberWithdrawalAssetWarning['lines']) ? $memberWithdrawalAssetWarning['lines'] : [];
                    $memberWithdrawalAssetSummary = trim((string) ($memberWithdrawalAssetWarning['summary'] ?? ''));
                    if ($memberWithdrawalAssetSummary === '') {
                        $memberWithdrawalAssetSummary = '없음';
                    }
                    $memberWithdrawConfirmMessage = sr_admin_member_terminal_status_confirm_message('withdrawn', $memberWithdrawalAssetWarning);
                    $memberAnonymizeConfirmMessage = sr_admin_member_terminal_status_confirm_message('anonymized', $memberWithdrawalAssetWarning);
                    $memberRiskModalId = 'member-risk-modal-' . (int) ($member['id'] ?? 0);
                    $statusClass = match ($memberStatus) {
                        'active' => 'is-normal',
                        'suspended', 'pending' => 'is-blocked',
                        default => 'is-left',
                    };
                    ?>
                    <tr>
                        <td class="admin-table-checkbox-cell admin-member-select-cell">
                            <label class="sr-only" for="member_bulk_select_<?php echo sr_e((string) (int) $member['id']); ?>"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?> 선택</label>
                            <input id="member_bulk_select_<?php echo sr_e((string) (int) $member['id']); ?>" type="checkbox" name="selected_account_ids[]" value="<?php echo sr_e((string) (int) $member['id']); ?>" class="form-checkbox" form="member-bulk-session-form" data-member-row-select>
                        </td>
                        <td class="admin-table-break admin-member-email-cell">
                            <span class="admin-member-email-value"><?php echo sr_e(sr_admin_member_email_display($member)); ?></span>
                            <span class="admin-member-hash-value" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></span>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_e(sr_admin_member_display_name_preview($member)); ?>
                            <?php if ($memberWithdrawalAssetLines !== []) { ?>
                                <span class="admin-member-hash-value">자산: <?php echo sr_e($memberWithdrawalAssetSummary); ?></span>
                            <?php } elseif (!empty($memberWithdrawalAssetWarning['lookup_failed'])) { ?>
                                <span class="admin-member-hash-value">자산: <?php echo sr_e($memberWithdrawalAssetSummary); ?></span>
                            <?php } ?>
                        </td>
                        <?php if ($memberListShowNicknameColumn) { ?>
                            <td class="admin-table-nowrap admin-member-mobile-optional"><?php echo sr_e(trim((string) ($member['nickname'] ?? '')) !== '' ? (string) $member['nickname'] : '-'); ?></td>
                        <?php } ?>
                        <td class="admin-table-nowrap admin-member-mobile-optional"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-marketing-cell admin-member-mobile-optional">
                            <?php $memberMarketingConsent = isset($member['marketing_consent']) && is_array($member['marketing_consent']) ? $member['marketing_consent'] : null; ?>
                            <?php echo $memberMarketingConsentBadgeHtml($memberMarketingConsent); ?>
                            <?php if ($memberMarketingConsent !== null) { ?>
                                <span class="admin-member-hash-value"><?php echo sr_admin_time_html((string) ($memberMarketingConsent['created_at'] ?? ''), '-'); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-member-session-cell"><?php echo sr_e((string) $member['active_session_count']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $member['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                <?php if ($memberMessageWriteAvailable && $memberStatus === 'active' && (string) ($member['account_public_hash'] ?? '') !== '') { ?>
                                    <a href="<?php echo sr_e(sr_url('/message/write?to_account=' . rawurlencode((string) $member['account_public_hash']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="쪽지 발송" title="쪽지 발송"><?php echo sr_material_icon_html('mail'); ?></a>
                                <?php } ?>
                                <?php if (!in_array($memberStatus, $memberTerminalStatuses, true)) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form>
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                        <input type="hidden" name="intent" value="evaluate_groups">
                                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>" title="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>"><?php echo sr_material_icon_html('rule'); ?></button>
                                    </form>
                                <?php } ?>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger" aria-label="위험작업" title="위험작업" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($memberRiskModalId); ?>" data-overlay="#<?php echo sr_e($memberRiskModalId); ?>"><?php echo sr_material_icon_html('warning'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php foreach ($members as $member) { ?>
        <?php
        $memberStatus = (string) $member['status'];
        $memberWithdrawalAssetWarning = $memberWithdrawalAssetWarnings[(int) ($member['id'] ?? 0)] ?? ['assets' => [], 'lines' => [], 'summary' => ''];
        $memberWithdrawalAssetSummary = trim((string) ($memberWithdrawalAssetWarning['summary'] ?? ''));
        if ($memberWithdrawalAssetSummary === '') {
            $memberWithdrawalAssetSummary = '없음';
        }
        $memberWithdrawConfirmMessage = sr_admin_member_terminal_status_confirm_message('withdrawn', $memberWithdrawalAssetWarning);
        $memberAnonymizeConfirmMessage = sr_admin_member_terminal_status_confirm_message('anonymized', $memberWithdrawalAssetWarning);
        $memberRiskModalId = 'member-risk-modal-' . (int) ($member['id'] ?? 0);
        ?>
        <div id="<?php echo sr_e($memberRiskModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($memberRiskModalId); ?>-title" aria-hidden="true" inert>
            <div class="modal-dialog">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($memberRiskModalId); ?>-title" class="modal-title">회원 위험 작업</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($memberRiskModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <p class="form-help">대상: <?php echo sr_e(sr_admin_member_display_name_preview($member)); ?> · 현재 상태: <?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></p>
                        <p class="form-help">현재 조회된 보유 자산: <?php echo sr_e($memberWithdrawalAssetSummary); ?></p>
                        <p class="form-help">탈퇴/익명화는 세션, 2차 인증, 소셜 로그인 연결과 개인정보 정리에 영향을 줍니다.</p>
                    </div>
                    <div class="modal-footer admin-member-risk-actions">
                        <?php if (!in_array($memberStatus, $memberTerminalStatuses, true) && $memberStatus !== 'suspended') { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm('이 회원을 차단할까요? 활성 세션이 함께 폐기됩니다.');">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                <input type="hidden" name="status" value="suspended">
                                <button type="submit" class="btn btn-sm btn-ghost-warning modal-action"><?php echo sr_material_icon_html('block'); ?><span>차단</span></button>
                            </form>
                        <?php } ?>
                        <?php if (!in_array($memberStatus, $memberTerminalStatuses, true)) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm(<?php echo sr_e(sr_js_json_encode($memberWithdrawConfirmMessage)); ?>);">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                <input type="hidden" name="status" value="withdrawn">
                                <button type="submit" class="btn btn-sm btn-ghost-danger modal-action"><?php echo sr_material_icon_html('person_remove'); ?><span>탈퇴 처리</span></button>
                            </form>
                        <?php } ?>
                        <?php if ($memberStatus !== 'anonymized') { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form onsubmit="return confirm(<?php echo sr_e(sr_js_json_encode($memberAnonymizeConfirmMessage)); ?>);">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                <input type="hidden" name="status" value="anonymized">
                                <button type="submit" class="btn btn-sm btn-ghost-danger modal-action"><?php echo sr_material_icon_html('no_accounts'); ?><span>익명화</span></button>
                            </form>
                        <?php } ?>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form>
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                            <input type="hidden" name="intent" value="revoke_sessions">
                            <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                            <button type="submit" class="btn btn-sm btn-ghost-danger modal-action"><?php echo sr_material_icon_html('delete'); ?><span><?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?></span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?></span>
        <?php if ($memberMessageWriteAvailable) { ?>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('mail'); ?> 쪽지 발송</span>
        <?php } ?>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('rule'); ?> <?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('warning'); ?> 위험작업</span>
    </div>
    <?php echo sr_admin_status_description_list_html('member_status'); ?>
</section>

<?php echo sr_admin_pagination_html($memberPagination, '회원 목록 페이지'); ?>

<script>
(function () {
    var statusEditForm = document.querySelector('[data-member-status-edit-form]');
    if (statusEditForm) {
        statusEditForm.addEventListener('submit', function (event) {
            var statusSelect = statusEditForm.querySelector('[data-member-status-select]');
            if (!statusSelect) {
                return;
            }
            var currentStatus = statusSelect.getAttribute('data-current-status') || '';
            var nextStatus = statusSelect.value || '';
            if (nextStatus === currentStatus || (nextStatus !== 'withdrawn' && nextStatus !== 'anonymized')) {
                return;
            }
            var message = nextStatus === 'anonymized'
                ? (statusEditForm.getAttribute('data-member-anonymize-confirm') || '이 회원을 익명화할까요? 계정 식별 정보가 되돌릴 수 없는 익명값으로 바뀌고 소셜 로그인 연결이 해제됩니다.')
                : (statusEditForm.getAttribute('data-member-withdraw-confirm') || '이 회원을 탈퇴 처리할까요? 세션, 2차 인증, 소셜 로그인 연결이 해제되고 privacy cleanup이 실행됩니다.');
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    }

    var form = document.querySelector('[data-member-bulk-session-form]');
    if (!form) {
        return;
    }
    var countNode = document.querySelector('[data-member-selected-count]');
    var submit = document.querySelector('[data-member-bulk-session-submit]');
    var clear = document.querySelector('[data-member-bulk-session-clear]');
    var selectAll = document.querySelector('[data-member-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-member-row-select]'));

    function checkedRows() {
        return rowChecks.filter(function (input) {
            return input.checked && !input.disabled;
        });
    }

    function syncBulkState() {
        var selectedCount = checkedRows().length;
        if (countNode) {
            countNode.textContent = String(selectedCount);
        }
        if (submit) {
            submit.disabled = selectedCount < 1;
        }
        if (clear) {
            clear.hidden = selectedCount < 1;
        }
        if (selectAll) {
            selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (input) {
                if (!input.disabled) {
                    input.checked = selectAll.checked;
                }
            });
            syncBulkState();
        });
    }
    rowChecks.forEach(function (input) {
        input.addEventListener('change', syncBulkState);
    });
    if (clear) {
        clear.addEventListener('click', function () {
            rowChecks.forEach(function (input) {
                input.checked = false;
            });
            syncBulkState();
        });
    }
    form.addEventListener('submit', function (event) {
        var selectedCount = checkedRows().length;
        if (selectedCount < 1) {
            event.preventDefault();
            syncBulkState();
            return;
        }
        if (!window.confirm('선택한 회원 ' + selectedCount + '명의 활성 세션을 회수합니다.')) {
            event.preventDefault();
        }
    });
    syncBulkState();
}());
</script>

<?php } ?>

<?php foreach ($memberAdminHelp as $memberAdminHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $memberAdminHelpModal['id'], (string) $memberAdminHelpModal['title'], (string) $memberAdminHelpModal['body_html']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
