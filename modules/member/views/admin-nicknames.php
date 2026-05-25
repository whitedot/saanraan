<?php

$adminPageTitle = sr_t('member::ui.member.nicknames.6d5b7b92');
$adminPageSubtitle = sr_t('member::ui.member.nicknames.subtitle.1fbe38e7');
$adminContainerClass = 'admin-page-member-list admin-page-member-nicknames admin-ui-scope';
$nicknameFilter = isset($nicknameFilter) && is_array($nicknameFilter) ? $nicknameFilter : ['field' => 'all', 'keyword' => ''];
$nicknameRows = isset($nicknameRows) && is_array($nicknameRows) ? $nicknameRows : [];
$nicknamePagination = isset($nicknamePagination) && is_array($nicknamePagination) ? $nicknamePagination : sr_admin_pagination_meta(0, 50, 1);
$canEditNicknames = !empty($canEditNicknames);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/member-nicknames')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-member-search-grid">
        <label class="admin-filter-field">
            <span><?php echo sr_e(sr_t('member::ui.search.b79bc9c8')); ?></span>
            <select name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('member::ui.all.a4b69faf'), 'hash' => sr_t('member::ui.text.4ca2f9ab'), 'email' => sr_t('member::ui.email.3b7dbc4c'), 'name' => sr_t('member::ui.name.253d1510'), 'nickname' => sr_t('member::ui.text.6211d967')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($nicknameFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field">
            <span><?php echo sr_e(sr_t('member::ui.search.bda397fc')); ?></span>
            <input type="search" name="q" value="<?php echo sr_e((string) ($nicknameFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('member::ui.member.nickname.placeholder.406cf915')); ?>">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('member::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('member::ui.member.nickname.list.78d30d87')); ?></h2>
    </div>
    <?php echo sr_admin_pagination_summary_html($nicknamePagination); ?>
    <div class="table-wrapper">
        <table class="table admin-member-nickname-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('member::ui.member.nickname.list.78d30d87')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('member::ui.text.4ca2f9ab')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.email.3b7dbc4c')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.name.253d1510')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.text.6211d967')); ?></th>
                    <th><?php echo sr_e(sr_t('member::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('admin::ui.edit.d3a98476')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('member::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($nicknameRows === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('member::ui.member.d2605064')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($nicknameRows as $member) { ?>
                    <?php
                    $memberStatus = (string) ($member['status'] ?? '');
                    $statusClass = match ($memberStatus) {
                        'active' => 'is-normal',
                        'suspended', 'pending' => 'is-blocked',
                        default => 'is-left',
                    };
                    $nicknameEditable = $canEditNicknames && $memberStatus !== 'anonymized';
                    $nicknameInputId = 'member_admin_nickname_' . (string) ((int) ($member['id'] ?? 0));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap admin-member-hash-cell" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="admin-table-break admin-member-email-cell"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <td>
                            <input id="<?php echo sr_e($nicknameInputId); ?>" type="text" name="nickname" value="<?php echo sr_e((string) ($member['nickname'] ?? '')); ?>" class="form-input form-control-full" maxlength="80"<?php echo $nicknameEditable ? '' : ' readonly'; ?> form="member-admin-nickname-form-<?php echo sr_e((string) ((int) $member['id'])); ?>">
                        </td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['profile_updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <form id="member-admin-nickname-form-<?php echo sr_e((string) ((int) $member['id'])); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/member-nicknames')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) ((int) $member['id'])); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?>" title="<?php echo sr_e(sr_t('member::ui.save.5fb92622')); ?>"<?php echo $nicknameEditable ? '' : ' disabled'; ?>><?php echo sr_material_icon_html('edit'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($nicknamePagination, '닉네임 관리 목록 페이지'); ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('member::ui.member.nickname.notice.title.31daa3d2')); ?></strong>
        <p><?php echo sr_e(sr_t('member::ui.member.nickname.notice.body.006e7e1b')); ?></p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
