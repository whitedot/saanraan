<?php

$adminPageTitle = sr_t('member::ui.member.list.d8e6279a');
$adminPageSubtitle = [
    sr_t('member::ui.member.status.search.5798c9ca'),
    sr_t('member::ui.status.member.login.1e2b02c0'),
];
$adminContainerClass = 'admin-page-member-list admin-ui-scope';
$memberAdminPage = isset($memberAdminPage) ? (string) $memberAdminPage : 'members';
if ($memberAdminPage === 'create_form') {
    $adminPageTitle = sr_t('member::ui.member.e9679572');
    $adminPageSubtitle = sr_t('member::ui.member.5a522a3e');
} elseif ($memberAdminPage === 'edit_form') {
    $adminPageTitle = sr_t('member::ui.member.edit.7eaadfda');
    $adminPageSubtitle = sr_t('member::ui.member.status.edit.a11441b7');
}
$statusCounts = isset($statusCounts) && is_array($statusCounts) ? $statusCounts : [];
$totalMembers = (int) ($statusCounts['total'] ?? count($members));
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$statusFilter = isset($statusFilter) && is_array($statusFilter) ? $statusFilter : [];
$adminPageTitleUrl = sr_admin_page_title_reset_url($memberAdminPage === 'members', '/admin/members');
$memberSort = isset($memberSort) && is_array($memberSort) ? $memberSort : sr_admin_member_default_sort();
$memberCreateValues = isset($memberCreateValues) && is_array($memberCreateValues) ? $memberCreateValues : sr_admin_member_create_default_values($site ?? []);
$memberEditValues = isset($memberEditValues) && is_array($memberEditValues) ? $memberEditValues : [];
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

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

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
                    <input id="member_admin_create_email_verified" type="checkbox" name="email_verified" value="1" class="form-switch form-choice-dark"<?php echo (string) ($memberCreateValues['email_verified'] ?? '1') === '1' ? ' checked' : ''; ?>>
                    <label for="member_admin_create_email_verified"><?php echo sr_admin_choice_label_html(sr_t('member::ui.text.386deb8d')); ?></label>
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
        <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme" data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="edit">
            <input type="hidden" name="account_id" value="<?php echo sr_e((string) ($memberEditValues['id'] ?? $editMember['id'])); ?>">
            <section class="card">
                <h2><?php echo sr_e(sr_t('member::ui.member.edit.7eaadfda')); ?></h2>
                <div class="form-row">
                    <span class="form-label form-label-help"><?php echo sr_member_admin_help_button_html(sr_t('member::ui.text.4ca2f9ab'), $memberAdminHelp['public_hash']['id'], $memberAdminHelpOpenLabel); ?><span><?php echo sr_e(sr_t('member::ui.text.4ca2f9ab')); ?></span></span>
                    <div class="form-field">
                        <code><?php echo sr_e(sr_admin_member_public_hash($runtimeConfig, (int) $editMember['id'])); ?></code>
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
                        <select id="member_admin_edit_status" name="status" class="form-select">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberEditValues['status'] ?? '') === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
            </section>
            <div class="form-sticky-actions form-actions form-actions-split">
                <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
            </div>
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
                        <th<?php echo sr_admin_sort_aria('nickname', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.nickname'), 'nickname', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <?php } ?>
                    <th<?php echo sr_admin_sort_aria('status', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.status.e10195a1'), 'status', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('email_verified_at', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.email.2f905abd'), 'email_verified_at', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('last_login_at', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.login.677d154e'), 'last_login_at', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('active_session_count', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.fda1ae9a'), 'active_session_count', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th<?php echo sr_admin_sort_aria('created_at', $memberSort); ?>><?php echo sr_admin_sort_header_html(sr_t('member::ui.text.5efd3ddd'), 'created_at', $memberSort, sr_admin_member_sort_options(), sr_admin_member_default_sort()); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members === []) { ?>
                    <tr>
                        <td colspan="<?php echo $memberListShowNicknameColumn ? '10' : '9'; ?>" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.d2605064')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($members as $member) { ?>
                    <?php
                    $memberStatus = (string) $member['status'];
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
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <?php if ($memberListShowNicknameColumn) { ?>
                            <td class="admin-table-nowrap"><?php echo sr_e(trim((string) ($member['nickname'] ?? '')) !== '' ? (string) $member['nickname'] : '-'); ?></td>
                        <?php } ?>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_admin_time_html((string) ($member['email_verified_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_admin_time_html((string) ($member['last_login_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-session-cell"><?php echo sr_e((string) $member['active_session_count']); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_admin_time_html((string) $member['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $member['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                    <input type="hidden" name="intent" value="evaluate_groups">
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>" title="<?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?>"><?php echo sr_material_icon_html('rule'); ?></button>
                                </form>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>" data-sr-validate-form>
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="return_to" value="<?php echo sr_e(sr_admin_current_get_url('/admin/members')); ?>">
                                    <input type="hidden" name="intent" value="revoke_sessions">
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?>" title="<?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('rule'); ?> <?php echo sr_e(sr_t('member::ui.member.evaluate_groups.5da8ff32')); ?></span>
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> <?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?></span>
    </div>
    <?php echo sr_admin_status_description_list_html('member_status'); ?>
</section>

<?php echo sr_admin_pagination_html($memberPagination, '회원 목록 페이지'); ?>

<script>
(function () {
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
