<?php

$adminPageTitle = sr_t('member::ui.member.781c100e');
$adminPageSubtitle = sr_t('member::ui.member.status.search.5798c9ca');
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
$memberCreateValues = isset($memberCreateValues) && is_array($memberCreateValues) ? $memberCreateValues : sr_admin_member_create_default_values($site ?? []);
$memberEditValues = isset($memberEditValues) && is_array($memberEditValues) ? $memberEditValues : [];
$createStatuses = sr_admin_member_create_allowed_statuses();
$memberLocaleOptions = sr_supported_locales($site ?? null);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($memberAdminPage === 'create_form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="create">
        <section class="admin-card card">
            <h2><?php echo sr_e(sr_t('member::ui.member.e9679572')); ?></h2>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_email"><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_create_email" type="email" name="email" value="<?php echo sr_e((string) ($memberCreateValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_login_id"><?php echo sr_e(sr_t('member::ui.login.0cdb28b5')); ?></label>
                <div class="admin-form-field">
                    <input id="member_admin_create_login_id" type="text" name="login_id" value="<?php echo sr_e((string) ($memberCreateValues['login_id'] ?? '')); ?>" class="form-input" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username">
                    <small class="admin-form-help"><?php echo sr_e(sr_t('member::ui.email.login.email.active.eb627985')); ?></small>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_display_name"><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_create_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberCreateValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_password"><?php echo sr_e(sr_t('member::ui.password.4fa210a0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_create_password" type="password" name="password" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_password_confirm"><?php echo sr_e(sr_t('member::ui.password.61081c91')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <input id="member_admin_create_password_confirm" type="password" name="password_confirm" class="form-input" minlength="8" maxlength="255" autocomplete="new-password" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_locale">Locale <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                <div class="admin-form-field">
                    <select id="member_admin_create_locale" name="locale" class="form-select" required>
                        <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                            <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberCreateValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                <?php echo sr_e($localeOption); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="member_admin_create_status"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></label>
                <div class="admin-form-field">
                    <select id="member_admin_create_status" name="status" class="form-select">
                        <?php foreach ($createStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($memberCreateValues['status'] ?? 'active') === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></span>
                <div class="admin-form-field admin-form-check">
                    <input id="member_admin_create_email_verified" type="checkbox" name="email_verified" value="1" class="form-checkbox"<?php echo (string) ($memberCreateValues['email_verified'] ?? '1') === '1' ? ' checked' : ''; ?>>
                    <label for="member_admin_create_email_verified"><?php echo sr_admin_choice_label_html(sr_t('member::ui.text.386deb8d')); ?></label>
                </div>
            </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } elseif ($memberAdminPage === 'edit_form') { ?>
    <?php if (is_array($editMember)) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/members/save')); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="edit">
            <input type="hidden" name="account_id" value="<?php echo sr_e((string) ($memberEditValues['id'] ?? $editMember['id'])); ?>">
            <section class="admin-card card">
                <h2><?php echo sr_e(sr_t('member::ui.member.edit.7eaadfda')); ?></h2>
                <div class="admin-form-row">
                    <span class="form-label"><?php echo sr_e(sr_t('member::ui.text.4ca2f9ab')); ?></span>
                    <div class="admin-form-field">
                        <code><?php echo sr_e(sr_admin_member_public_hash($runtimeConfig, (int) $editMember['id'])); ?></code>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_email"><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="member_admin_edit_email" type="email" name="email" value="<?php echo sr_e((string) ($memberEditValues['email'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" autocomplete="email" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_login_id"><?php echo sr_e(sr_t('member::ui.login.0cdb28b5')); ?></label>
                    <div class="admin-form-field">
                        <?php
                        $memberEditAccountIdentifierHash = (string) ($editMember['account_identifier_hash'] ?? '');
                        $memberEditEmailHash = (string) ($editMember['email_hash'] ?? '');
                        $memberEditHasLoginId = (string) ($editMember['login_id_hash'] ?? '') !== ''
                            || (
                                $memberEditAccountIdentifierHash !== ''
                                && $memberEditEmailHash !== ''
                                && !hash_equals($memberEditEmailHash, $memberEditAccountIdentifierHash)
                            );
                        ?>
                        <input id="member_admin_edit_login_id" type="text" name="login_id" value="<?php echo sr_e((string) ($memberEditValues['login_id'] ?? '')); ?>" class="form-input" maxlength="40" pattern="[a-z][a-z0-9_]{3,39}" autocomplete="username" placeholder="<?php echo sr_e(sr_t('member::ui.login.02a3f102')); ?>">
                        <small class="admin-form-help"><?php echo sr_e(sr_t('member::ui.status.771e6888')); ?> <?php echo $memberEditHasLoginId ? sr_t('member::ui.create.1b9cb032') : sr_t('member::ui.text.72ea3d64'); ?><?php echo sr_e(sr_t('member::ui.login.status.ba691fb4')); ?></small>
                        <label class="admin-form-check form-label" for="member_admin_edit_clear_login_id">
                            <input id="member_admin_edit_clear_login_id" type="checkbox" name="clear_login_id" value="1" class="form-checkbox"<?php echo (string) ($memberEditValues['clear_login_id'] ?? '0') === '1' ? ' checked' : ''; ?>>
                            <?php echo sr_admin_choice_label_html(sr_t('member::ui.login.bc4bcd11')); ?>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_display_name"><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="member_admin_edit_display_name" type="text" name="display_name" value="<?php echo sr_e((string) ($memberEditValues['display_name'] ?? '')); ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_locale">Locale <span class="sr-required-label"><?php echo sr_e(sr_t('member::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="member_admin_edit_locale" name="locale" class="form-select" required>
                            <?php foreach ($memberLocaleOptions as $localeOption) { ?>
                                <option value="<?php echo sr_e($localeOption); ?>"<?php echo (string) ($memberEditValues['locale'] ?? 'ko') === $localeOption ? ' selected' : ''; ?>>
                                    <?php echo sr_e($localeOption); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="member_admin_edit_status"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></label>
                    <div class="admin-form-field">
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
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
                <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?></button>
            </div>
        </form>
    <?php } else { ?>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.list.f07b3200')); ?></a>
        </div>
    <?php } ?>
<?php } else { ?>
<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/members')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('member::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.member.964f82c2')); ?> <strong><?php echo sr_e((string) $totalMembers); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=suspended')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.c7d4f680')); ?> <?php echo sr_e((string) ($statusCounts['suspended'] ?? 0)); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/members?status=withdrawn')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('member::ui.text.871d2076')); ?> <?php echo sr_e((string) (($statusCounts['withdrawn'] ?? 0) + ($statusCounts['anonymized'] ?? 0))); ?><?php echo sr_e(sr_t('member::ui.text.9f96b8e2')); ?></a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-member-search-grid">
        <div class="admin-filter-field admin-member-filter-status">
            <label for="admin-status-filter" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></label>
            <select name="status" id="admin-status-filter" class="form-select admin-filter-input">
                <option value=""><?php echo sr_e(sr_t('member::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-member-filter-field">
            <label for="member-search-field" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.search.b79bc9c8')); ?></label>
            <select name="field" id="member-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('member::ui.all.a4b69faf'), 'hash' => sr_t('member::ui.text.93971787'), 'email' => sr_t('member::ui.email.3b7dbc4c'), 'login_id' => sr_t('member::ui.login.0cdb28b5'), 'name' => sr_t('member::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-member-filter-keyword">
            <label for="member-search-keyword" class="admin-filter-label"><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></label>
            <input type="text" id="member-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('member::ui.email.login.name.c26ba637')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.member.list.d8e6279a')); ?></h2>
        <a href="<?php echo sr_e(sr_url('/admin/members/new')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.member.9df41111')); ?></a>
    </div>
    <div class="table-wrapper">
        <table class="table admin-member-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('member::ui.member.list.5e737292')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('member::ui.text.4ca2f9ab')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.email.2f905abd')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.login.677d154e')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.fda1ae9a')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.5efd3ddd')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($members === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.d2605064')); ?></td>
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
                        <td class="admin-table-nowrap admin-table-id admin-member-hash-cell" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="admin-table-break admin-member-email-cell"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['email_verified_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['last_login_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-member-session-cell"><?php echo sr_e((string) $member['active_session_count']); ?></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) $member['created_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/members/edit?id=' . rawurlencode((string) $member['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('member::ui.edit.3537f0cc')); ?></a>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="revoke_sessions">
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('member::ui.text.3ceda84f')); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('member::ui.member.7093117c')); ?></strong>
        <p><?php echo sr_e(sr_t('member::ui.status.member.login.1e2b02c0')); ?></p>
    </div>
</div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
