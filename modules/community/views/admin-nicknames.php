<?php

$adminPageTitle = sr_t('community::ui.nickname.manage');
$adminPageSubtitle = sr_t('community::ui.nickname.manage.subtitle');
$adminContainerClass = 'admin-page-community-nicknames admin-ui-scope';
$nicknameFilter = isset($nicknameFilter) && is_array($nicknameFilter) ? $nicknameFilter : ['field' => 'all', 'keyword' => ''];
$nicknameRows = isset($nicknameRows) && is_array($nicknameRows) ? $nicknameRows : [];
$nicknamePagination = isset($nicknamePagination) && is_array($nicknamePagination) ? $nicknamePagination : sr_admin_pagination_meta(0, 50, 1);
$canResetNicknames = !empty($canResetNicknames);
$nicknameSearchSubmitted = !empty($nicknameSearchSubmitted);
$nicknameNotificationAvailable = !empty($nicknameNotificationAvailable);
$nicknameResetReasonOptions = function_exists('sr_community_nickname_reset_reason_options')
    ? sr_community_nickname_reset_reason_options()
    : [];
$nicknameReturnPath = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/community/nicknames');
$nicknameReturnPathInfo = parse_url($nicknameReturnPath);
if (!is_array($nicknameReturnPathInfo)
    || isset($nicknameReturnPathInfo['scheme'])
    || isset($nicknameReturnPathInfo['host'])
    || (string) ($nicknameReturnPathInfo['path'] ?? '') !== '/admin/community/nicknames'
    || str_contains($nicknameReturnPath, "\r")
    || str_contains($nicknameReturnPath, "\n")
) {
    $nicknameReturnPath = '/admin/community/nicknames';
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-member-search-grid">
        <label class="admin-filter-field">
            <span><?php echo sr_e(sr_t('community::ui.search.condition')); ?></span>
            <select name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('community::ui.all'), 'hash' => sr_t('community::ui.public_hash'), 'email' => sr_t('community::ui.email'), 'name' => sr_t('community::ui.name'), 'nickname' => sr_t('community::ui.nickname')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($nicknameFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="admin-filter-field">
            <span><?php echo sr_e(sr_t('community::ui.search.keyword')); ?></span>
            <input type="search" name="q" value="<?php echo sr_e((string) ($nicknameFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('community::ui.nickname.search.placeholder')); ?>">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('community::ui.search.submit')); ?></button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.nickname.list')); ?></h2>
    </div>
    <?php if ($nicknameSearchSubmitted) { ?>
        <?php echo sr_admin_pagination_summary_html($nicknamePagination); ?>
    <?php } ?>
    <div class="table-wrapper">
        <table class="table admin-member-nickname-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.nickname.list')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('community::ui.public_hash')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.email')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.name')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.nickname')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.status')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.nickname.updated_at')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('community::ui.manage')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$nicknameSearchSubmitted) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.nickname.search_first')); ?></td>
                    </tr>
                <?php } elseif ($nicknameRows === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.empty')); ?></td>
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
                    $nicknameResettable = $canResetNicknames && !sr_community_nickname_status_blocks_identity($memberStatus);
                    ?>
                    <tr>
                        <td class="admin-table-nowrap admin-member-hash-cell" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="admin-table-break admin-member-email-cell"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                        <td class="admin-table-break"><?php echo sr_e((string) ($member['nickname'] ?? '') !== '' ? (string) $member['nickname'] : '-'); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['nickname_updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-admin-nickname-reset-modal-<?php echo sr_e((string) ((int) $member['id'])); ?>" data-overlay="#community-admin-nickname-reset-modal-<?php echo sr_e((string) ((int) $member['id'])); ?>" aria-label="<?php echo sr_e(sr_t('community::ui.nickname.reset')); ?>" title="<?php echo sr_e(sr_t('community::ui.nickname.reset')); ?>"<?php echo $nicknameResettable ? '' : ' disabled'; ?>><?php echo sr_material_icon_html('restart_alt'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($nicknameSearchSubmitted) { ?>
    <?php echo sr_admin_pagination_html($nicknamePagination, '닉네임 관리 목록 페이지'); ?>
<?php } ?>

<?php foreach ($nicknameRows as $member) { ?>
    <?php
    $memberId = (int) ($member['id'] ?? 0);
    $memberStatus = (string) ($member['status'] ?? '');
    $nicknameResettable = $memberId > 0 && $canResetNicknames && !sr_community_nickname_status_blocks_identity($memberStatus);
    if (!$nicknameResettable) {
        continue;
    }
    $modalId = 'community-admin-nickname-reset-modal-' . (string) $memberId;
    $nicknameDisplay = (string) ($member['nickname'] ?? '') !== '' ? (string) $member['nickname'] : '-';
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-label">
        <div class="modal-dialog-sm">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.nickname.reset')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                        <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberId); ?>">
                    <input type="hidden" name="return_path" value="<?php echo sr_e($nicknameReturnPath); ?>">
                    <p><?php echo sr_e(sr_t($nicknameNotificationAvailable ? 'community::ui.nickname.reset.confirm_body' : 'community::ui.nickname.reset.confirm_body_no_notification')); ?></p>
                    <dl class="admin-module-detail-list">
                        <div>
                            <dt><?php echo sr_e(sr_t('community::ui.public_hash')); ?></dt>
                            <dd><?php echo sr_e((string) ($member['account_public_hash'] ?? '')); ?></dd>
                        </div>
                        <div>
                            <dt><?php echo sr_e(sr_t('community::ui.nickname')); ?></dt>
                            <dd><?php echo sr_e($nicknameDisplay); ?></dd>
                        </div>
                    </dl>
                    <p>
                        <label for="<?php echo sr_e($modalId); ?>-reason">
                            <span><?php echo sr_e(sr_t('community::ui.nickname.reset.reason')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></span>
                            <select id="<?php echo sr_e($modalId); ?>-reason" name="reset_reason" class="form-select" required>
                                <option value=""><?php echo sr_e(sr_t('community::ui.select.placeholder')); ?></option>
                                <?php foreach ($nicknameResetReasonOptions as $reasonValue => $reasonLabel) { ?>
                                    <option value="<?php echo sr_e((string) $reasonValue); ?>"><?php echo sr_e((string) $reasonLabel); ?></option>
                                <?php } ?>
                            </select>
                        </label>
                        <small><?php echo sr_e(sr_t('community::ui.nickname.reset.reason.help')); ?></small>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                    <button type="submit" class="btn btn-outline-secondary modal-action" data-overlay-focus><?php echo sr_e(sr_t('community::ui.nickname.reset')); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('community::ui.nickname.notice.title')); ?></strong>
        <p><?php echo sr_e(sr_t($nicknameNotificationAvailable ? 'community::ui.nickname.notice.body' : 'community::ui.nickname.notice.body_no_notification')); ?></p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
