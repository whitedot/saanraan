<?php

$adminPageTitle = sr_t('community::ui.nickname.manage');
$adminPageSubtitle = sr_t('community::ui.nickname.manage.subtitle');
$adminContainerClass = 'admin-page-community-nicknames admin-ui-scope';
$nicknameFilter = isset($nicknameFilter) && is_array($nicknameFilter) ? $nicknameFilter : ['field' => 'all', 'keyword' => ''];
$nicknameRows = isset($nicknameRows) && is_array($nicknameRows) ? $nicknameRows : [];
$nicknamePagination = isset($nicknamePagination) && is_array($nicknamePagination) ? $nicknamePagination : sr_admin_pagination_meta(0, 50, 1);
$canResetNicknames = !empty($canResetNicknames);
$canSendMemberMessages = !empty($canSendMemberMessages);
$memberMessageSendingEnabled = !empty($memberMessageSendingEnabled);
$communityLevelEnabled = !empty($communityLevelEnabled);
$canEditMemberLevels = !empty($canEditMemberLevels);
$memberLevelBulkEditable = $communityLevelEnabled && $canEditMemberLevels;
$communityLevels = isset($communityLevels) && is_array($communityLevels) ? $communityLevels : [];
$currentAdminAccountId = isset($account) && is_array($account) ? (int) ($account['id'] ?? 0) : 0;
$nicknameSearchSubmitted = !empty($nicknameSearchSubmitted);
$nicknameNotificationAvailable = !empty($nicknameNotificationAvailable);
$nicknameResetReasonOptions = function_exists('sr_community_nickname_reset_reason_options')
    ? sr_community_nickname_reset_reason_options()
    : [];
$nicknameReturnPath = (string) ($_SERVER['REQUEST_URI'] ?? '/admin/community/nicknames');
$memberLevelBulkFormId = 'community-admin-member-level-bulk-form';
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
$memberListColumnCount = ($communityLevelEnabled ? 7 : 6) + ($memberLevelBulkEditable ? 1 : 0);
$communityLevelLabels = [0 => sr_t('community::ui.member.level.default')];
foreach ($communityLevels as $communityLevel) {
    $levelValue = (int) ($communityLevel['level_value'] ?? 0);
    $levelTitle = trim((string) ($communityLevel['title'] ?? ''));
    $communityLevelLabels[$levelValue] = $levelTitle !== ''
        ? ($levelTitle === sr_t('community::ui.member.level.value', ['level' => (string) $levelValue])
            ? $levelTitle
            : sr_t('community::ui.member.level.option', ['level' => (string) $levelValue, 'title' => $levelTitle]))
        : sr_t('community::ui.member.level.value', ['level' => (string) $levelValue]);
}
for ($levelValue = 0; $levelValue <= sr_community_max_level_value(); $levelValue++) {
    if (!isset($communityLevelLabels[$levelValue])) {
        $communityLevelLabels[$levelValue] = sr_t('community::ui.member.level.value', ['level' => (string) $levelValue]);
    }
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="admin-filter admin-member-filter ui-form-theme">
    <div class="admin-filter-grid admin-member-search-grid<?php echo $communityLevelEnabled ? ' has-level-filter' : ''; ?>">
        <label class="admin-filter-field">
            <span><?php echo sr_e(sr_t('community::ui.search.condition')); ?></span>
            <select name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('community::ui.all'), 'hash' => sr_t('community::ui.public_hash'), 'email' => sr_t('community::ui.email'), 'nickname' => sr_t('community::ui.nickname')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($nicknameFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <?php if ($communityLevelEnabled) { ?>
            <label class="admin-filter-field">
                <span><?php echo sr_e(sr_t('community::ui.member.level')); ?></span>
                <select name="level" class="form-select admin-filter-input">
                    <option value=""><?php echo sr_e(sr_t('community::ui.all')); ?></option>
                    <?php foreach ($communityLevelLabels as $levelValue => $levelLabel) { ?>
                        <option value="<?php echo sr_e((string) $levelValue); ?>"<?php echo ($nicknameFilter['level_value'] ?? null) !== null && (int) $nicknameFilter['level_value'] === (int) $levelValue ? ' selected' : ''; ?>><?php echo sr_e((string) $levelLabel); ?></option>
                    <?php } ?>
                </select>
            </label>
        <?php } ?>
        <label class="admin-filter-field admin-member-filter-keyword">
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
        <div class="admin-list-summary community-member-list-toolbar<?php echo $memberLevelBulkEditable && $nicknameRows !== [] ? ' has-bulk-actions' : ''; ?>">
            <?php if ($memberLevelBulkEditable && $nicknameRows !== []) { ?>
                <form id="<?php echo sr_e($memberLevelBulkFormId); ?>" method="post" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="admin-list-actions community-bulk-actions community-member-level-bulk-actions ui-form-theme" data-community-member-level-bulk-form>
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="update_level">
                    <input type="hidden" name="return_path" value="<?php echo sr_e($nicknameReturnPath); ?>">
                    <label class="community-member-select-all">
                        <input type="checkbox" class="form-checkbox" data-community-member-select-all aria-label="<?php echo sr_e(sr_t('community::ui.member.select_all')); ?>">
                        <span class="sr-only"><?php echo sr_e(sr_t('community::ui.member.select_all')); ?></span>
                    </label>
                    <label class="sr-only" for="community-admin-member-bulk-level"><?php echo sr_e(sr_t('community::ui.member.level.bulk_target')); ?></label>
                    <select id="community-admin-member-bulk-level" name="level_value" class="form-select community-action-select" required>
                        <?php foreach ($communityLevelLabels as $levelValue => $levelLabel) { ?>
                            <option value="<?php echo sr_e((string) $levelValue); ?>"><?php echo sr_e((string) $levelLabel); ?></option>
                        <?php } ?>
                    </select>
                    <button type="submit" class="btn btn-outline-success community-action-submit"><span class="sr-only"><?php echo sr_e(sr_t('community::ui.member.selected')); ?></span><?php echo sr_e(sr_t('community::ui.member.level.bulk_update')); ?></button>
                </form>
            <?php } ?>
            <span class="community-member-list-summary-text">
                <?php if ((int) ($nicknamePagination['total'] ?? 0) <= 0) { ?>
                    전체 <strong>0</strong>건
                <?php } else { ?>
                    전체 <strong><?php echo sr_e((string) (int) ($nicknamePagination['total'] ?? 0)); ?></strong>건 중 <?php echo sr_e((string) (int) ($nicknamePagination['start'] ?? 0)); ?>-<?php echo sr_e((string) (int) ($nicknamePagination['end'] ?? 0)); ?>건 표시
                <?php } ?>
            </span>
        </div>
    <?php } ?>
    <div class="table-wrapper">
        <table class="table admin-member-nickname-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.nickname.list')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <?php if ($memberLevelBulkEditable) { ?>
                        <th class="admin-table-select-cell"><span class="sr-only"><?php echo sr_e(sr_t('community::ui.member.select')); ?></span></th>
                    <?php } ?>
                    <th><?php echo sr_e(sr_t('community::ui.public_hash')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.email')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.nickname')); ?></th>
                    <?php if ($communityLevelEnabled) { ?>
                        <th><?php echo sr_e(sr_t('community::ui.member.level')); ?></th>
                    <?php } ?>
                    <th><?php echo sr_e(sr_t('community::ui.status')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.nickname.updated_at')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('community::ui.manage')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$nicknameSearchSubmitted) { ?>
                    <tr>
                        <td colspan="<?php echo sr_e((string) $memberListColumnCount); ?>" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.nickname.search_first')); ?></td>
                    </tr>
                <?php } elseif ($nicknameRows === []) { ?>
                    <tr>
                        <td colspan="<?php echo sr_e((string) $memberListColumnCount); ?>" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.empty')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($nicknameRows as $member) { ?>
                    <?php
                    $memberStatus = (string) ($member['status'] ?? '');
                    $memberId = (int) ($member['id'] ?? 0);
                    $memberLevelValue = sr_community_normalize_level_value($member['community_level_value'] ?? 0);
                    $memberLevelLabel = (string) ($communityLevelLabels[$memberLevelValue] ?? sr_t('community::ui.member.level.value', ['level' => (string) $memberLevelValue]));
                    $memberLevelSelectable = $memberLevelBulkEditable && $memberId > 0 && $memberStatus === 'active';
                    $statusClass = match ($memberStatus) {
                        'active' => 'is-normal',
                        'suspended', 'pending' => 'is-blocked',
                        default => 'is-left',
                    };
                    $nicknameResettable = $canResetNicknames && !sr_community_nickname_status_blocks_identity($memberStatus);
                    $memberMessageSendable = $memberMessageSendingEnabled && $canSendMemberMessages && $memberId > 0 && $memberStatus === 'active' && $memberId !== $currentAdminAccountId;
                    ?>
                    <tr>
                        <?php if ($memberLevelBulkEditable) { ?>
                            <td class="admin-table-select-cell">
                                <input type="checkbox" name="account_ids[]" value="<?php echo sr_e((string) $memberId); ?>" form="<?php echo sr_e($memberLevelBulkFormId); ?>" class="form-checkbox" data-community-member-select<?php echo $memberLevelSelectable ? '' : ' disabled'; ?> aria-label="<?php echo sr_e(sr_t('community::ui.member.select')); ?>">
                            </td>
                        <?php } ?>
                        <td class="admin-table-nowrap admin-member-hash-cell" title="<?php echo sr_e((string) $member['account_public_hash']); ?>"><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                        <td class="admin-table-break admin-member-email-cell"><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                        <td class="admin-table-break"><?php echo sr_e((string) ($member['nickname'] ?? '') !== '' ? (string) $member['nickname'] : '-'); ?></td>
                        <?php if ($communityLevelEnabled) { ?>
                            <td class="admin-table-nowrap"><?php echo sr_e($memberLevelLabel); ?></td>
                        <?php } ?>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($memberStatus, 'member_status')); ?></span></td>
                        <td class="admin-table-nowrap admin-member-date-cell"><?php echo sr_e((string) ($member['nickname_updated_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-admin-member-message-modal-<?php echo sr_e((string) $memberId); ?>" data-overlay="#community-admin-member-message-modal-<?php echo sr_e((string) $memberId); ?>" aria-label="<?php echo sr_e(sr_t('community::ui.member.message.send')); ?>" title="<?php echo sr_e(sr_t('community::ui.member.message.send')); ?>"<?php echo $memberMessageSendable ? '' : ' disabled'; ?>><?php echo sr_material_icon_html('mail'); ?></button>
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-admin-nickname-reset-modal-<?php echo sr_e((string) $memberId); ?>" data-overlay="#community-admin-nickname-reset-modal-<?php echo sr_e((string) $memberId); ?>" aria-label="<?php echo sr_e(sr_t('community::ui.nickname.reset')); ?>" title="<?php echo sr_e(sr_t('community::ui.nickname.reset')); ?>"<?php echo $nicknameResettable ? '' : ' disabled'; ?>><?php echo sr_material_icon_html('restart_alt'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($nicknameSearchSubmitted) { ?>
    <?php echo sr_admin_pagination_html($nicknamePagination, '멤버 관리 목록 페이지'); ?>
<?php } ?>

<?php foreach ($nicknameRows as $member) { ?>
    <?php
    $memberId = (int) ($member['id'] ?? 0);
    $memberStatus = (string) ($member['status'] ?? '');
    $nicknameResettable = $memberId > 0 && $canResetNicknames && !sr_community_nickname_status_blocks_identity($memberStatus);
    $memberMessageSendable = $memberId > 0 && $memberMessageSendingEnabled && $canSendMemberMessages && $memberStatus === 'active' && $memberId !== $currentAdminAccountId;
    $nicknameDisplay = (string) ($member['nickname'] ?? '') !== '' ? (string) $member['nickname'] : '-';
    ?>
    <?php if ($memberMessageSendable) { ?>
        <?php $messageModalId = 'community-admin-member-message-modal-' . (string) $memberId; ?>
        <div id="<?php echo sr_e($messageModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($messageModalId); ?>-label">
            <div class="modal-dialog">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($messageModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.member.message.send')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($messageModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="send_message">
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberId); ?>">
                        <input type="hidden" name="return_path" value="<?php echo sr_e($nicknameReturnPath); ?>">
                        <p class="community-nickname-reset-copy"><?php echo sr_e(sr_t('community::ui.member.message.confirm_body')); ?></p>
                        <div class="community-nickname-reset-summary" aria-label="<?php echo sr_e(sr_t('community::ui.member.message.target')); ?>">
                            <div>
                                <span><?php echo sr_e(sr_t('community::ui.public_hash')); ?></span>
                                <strong><?php echo sr_e((string) ($member['account_public_hash'] ?? '')); ?></strong>
                            </div>
                            <div>
                                <span><?php echo sr_e(sr_t('community::ui.nickname')); ?></span>
                                <strong><?php echo sr_e($nicknameDisplay); ?></strong>
                            </div>
                        </div>
                        <div class="community-nickname-reset-reason">
                            <label class="form-label" for="<?php echo sr_e($messageModalId); ?>-body">
                                <?php echo sr_e(sr_t('community::ui.member.message.body')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span>
                            </label>
                            <textarea id="<?php echo sr_e($messageModalId); ?>-body" name="body_text" class="form-textarea" rows="6" maxlength="5000" required></textarea>
                            <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.member.message.help')); ?></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($messageModalId); ?>"><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action" data-overlay-focus><?php echo sr_e(sr_t('community::ui.member.message.send')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
    <?php if (!$nicknameResettable) {
        continue;
    } ?>
    <?php $modalId = 'community-admin-nickname-reset-modal-' . (string) $memberId; ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-label">
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/nicknames')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($modalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('community::ui.nickname.reset')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('community::ui.close')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                        <?php echo sr_material_icon_html('close', '', sr_t('community::ui.close')); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="reset_nickname">
                    <input type="hidden" name="account_id" value="<?php echo sr_e((string) $memberId); ?>">
                    <input type="hidden" name="return_path" value="<?php echo sr_e($nicknameReturnPath); ?>">
                    <p class="community-nickname-reset-copy"><?php echo sr_e(sr_t($nicknameNotificationAvailable ? 'community::ui.nickname.reset.confirm_body' : 'community::ui.nickname.reset.confirm_body_no_notification')); ?></p>
                    <div class="community-nickname-reset-summary" aria-label="<?php echo sr_e(sr_t('community::ui.nickname.reset.target')); ?>">
                        <div>
                            <span><?php echo sr_e(sr_t('community::ui.public_hash')); ?></span>
                            <strong><?php echo sr_e((string) ($member['account_public_hash'] ?? '')); ?></strong>
                        </div>
                        <div>
                            <span><?php echo sr_e(sr_t('community::ui.nickname')); ?></span>
                            <strong><?php echo sr_e($nicknameDisplay); ?></strong>
                        </div>
                    </div>
                    <div class="community-nickname-reset-reason">
                        <label class="form-label" for="<?php echo sr_e($modalId); ?>-reason">
                            <?php echo sr_e(sr_t('community::ui.nickname.reset.reason')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span>
                        </label>
                        <select id="<?php echo sr_e($modalId); ?>-reason" name="reset_reason" class="form-select" required>
                            <option value=""><?php echo sr_e(sr_t('community::ui.select.placeholder')); ?></option>
                            <?php foreach ($nicknameResetReasonOptions as $reasonValue => $reasonLabel) { ?>
                                <option value="<?php echo sr_e((string) $reasonValue); ?>"><?php echo sr_e((string) $reasonLabel); ?></option>
                            <?php } ?>
                        </select>
                        <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.nickname.reset.reason.help')); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e(sr_t('community::ui.cancel')); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action" data-overlay-focus><?php echo sr_e(sr_t('community::ui.nickname.reset')); ?></button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php if ($memberLevelBulkEditable) { ?>
    <script>
    (function () {
        var form = document.querySelector('[data-community-member-level-bulk-form]');
        if (!form) {
            return;
        }
        var selectAll = form.querySelector('[data-community-member-select-all]');
        var checkboxes = Array.prototype.slice.call(document.querySelectorAll('[data-community-member-select][form="' + form.id + '"]'));
        var selectable = function () {
            return checkboxes.filter(function (checkbox) {
                return !checkbox.disabled;
            });
        };
        var syncSelectAll = function () {
            var enabled = selectable();
            var checked = enabled.filter(function (checkbox) {
                return checkbox.checked;
            });
            selectAll.checked = enabled.length > 0 && checked.length === enabled.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < enabled.length;
        };
        selectAll.addEventListener('change', function () {
            selectable().forEach(function (checkbox) {
                checkbox.checked = selectAll.checked;
            });
            syncSelectAll();
        });
        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', syncSelectAll);
        });
        syncSelectAll();
    })();
    </script>
<?php } ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('community::ui.nickname.notice.title')); ?></strong>
        <p><?php echo sr_e(sr_t($nicknameNotificationAvailable ? 'community::ui.nickname.notice.body' : 'community::ui.nickname.notice.body_no_notification')); ?></p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
