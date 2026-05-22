<?php

$adminPageTitle = sr_t('admin::ui.admin.004791bd');
$adminPageSubtitle = sr_t('admin::ui.member.search.admin.411aa70f');
$adminContainerClass = 'admin-page-role-list admin-ui-scope';
$searchFilter = isset($searchFilter) && is_array($searchFilter) ? $searchFilter : ['field' => 'all', 'keyword' => ''];
$statusFilter = isset($statusFilter) ? (string) $statusFilter : '';
$roleFilter = isset($roleFilter) ? (string) $roleFilter : '';
$hasRoleFilters = !empty($hasRoleFilters);
$roleFormAction = sr_url(sr_admin_role_filter_url($statusFilter, $roleFilter, $searchFilter));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/roles')); ?>" class="admin-filter admin-role-filter ui-form-theme">
    <div class="admin-filter-grid admin-role-search-grid">
        <div class="admin-filter-field">
            <label for="admin-role-status-filter" class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.status.3808960c')); ?></label>
            <select name="status" id="admin-role-status-filter" class="form-select admin-filter-input">
                <option value=""><?php echo sr_e(sr_t('admin::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-filter" class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.text.a9fa0dfa')); ?></label>
            <select name="role" id="admin-role-filter" class="form-select admin-filter-input">
                <option value=""><?php echo sr_e(sr_t('admin::ui.all.a4b69faf')); ?></option>
                <option value="any"<?php echo $roleFilter === 'any' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.99a1ec1c')); ?></option>
                <option value="none"<?php echo $roleFilter === 'none' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('admin::ui.text.ca7516f2')); ?></option>
                <?php foreach ($allowedRoles as $roleKey) { ?>
                    <option value="<?php echo sr_e($roleKey); ?>"<?php echo $roleFilter === $roleKey ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($roleKey, 'role')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-search-field" class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.b79bc9c8')); ?></label>
            <select name="field" id="admin-role-search-field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('admin::ui.all.a4b69faf'), 'hash' => sr_t('admin::ui.text.93971787'), 'email' => sr_t('admin::ui.email.3b7dbc4c'), 'login_id' => sr_t('admin::ui.login.0cdb28b5'), 'name' => sr_t('admin::ui.name.253d1510')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($searchFilter['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="admin-role-search-keyword" class="admin-filter-label"><?php echo sr_e(sr_t('admin::ui.search.bda397fc')); ?></label>
            <input type="text" id="admin-role-search-keyword" name="q" value="<?php echo sr_e((string) ($searchFilter['keyword'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('admin::ui.email.login.name.c26ba637')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('admin::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<div class="admin-card admin-list-card card admin-list-form">
<div class="table-wrapper">
<table class="table">
    <thead class="ui-table-head">
        <tr>
            <th><?php echo sr_e(sr_t('admin::ui.text.4ca2f9ab')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.e8857c35')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.status.3808960c')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.4b72a63a')); ?></th>
            <th class="text-end"><?php echo sr_e(sr_t('admin::ui.text.16f64fe4')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$hasRoleFilters) { ?>
            <tr>
                <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.select.search.member.f6af5629')); ?></td>
            </tr>
        <?php } elseif ($accounts === []) { ?>
            <tr>
                <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('admin::ui.member.4f210836')); ?></td>
            </tr>
        <?php } ?>
        <?php foreach ($accounts as $adminAccount) { ?>
            <?php $roleModalId = 'admin-role-modal-' . (string) $adminAccount['id']; ?>
            <tr>
                <td><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></td>
                <td><?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $adminAccount['status'], 'member_status')); ?></td>
                <td><?php echo sr_e($adminAccount['roles'] === [] ? sr_t('admin::ui.text.72ea3d64') : implode(', ', array_map(static function (string $roleKey): string {
                    return sr_admin_code_label($roleKey, 'role');
                }, $adminAccount['roles']))); ?></td>
                <td class="admin-table-actions-cell">
                    <div class="admin-row-actions">
                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($roleModalId); ?>" data-overlay="#<?php echo sr_e($roleModalId); ?>">
                            <?php echo sr_e(sr_t('admin::ui.text.5336e811')); ?>
                        </button>
                    </div>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<?php foreach ($accounts as $adminAccount) { ?>
    <?php $roleModalId = 'admin-role-modal-' . (string) $adminAccount['id']; ?>
    <div id="<?php echo sr_e($roleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($roleModalId); ?>-label">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo sr_e($roleFormAction); ?>" class="admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($roleModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.admin.bedced78')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($roleModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $adminAccount['id']); ?>">
                        <div class="admin-form-row">
                            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.member.e335b899')); ?></span>
                            <div class="admin-form-field">
                                <strong><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></strong><br>
                                <?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?> · <?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.4b72a63a')); ?></span>
                            <div class="admin-form-field">
                                <?php echo sr_e($adminAccount['roles'] === [] ? sr_t('admin::ui.text.72ea3d64') : implode(', ', array_map(static function (string $roleKey): string {
                                    return sr_admin_code_label($roleKey, 'role');
                                }, $adminAccount['roles']))); ?>
                            </div>
                        </div>
                        <input type="hidden" name="intent" value="sync_roles">
                        <div class="admin-form-row">
                            <span class="form-label"><?php echo sr_e(sr_t('admin::ui.text.7258c171')); ?></span>
                            <div class="admin-form-field">
                                <fieldset class="admin-role-choice-list">
                                    <legend class="sr-only"><?php echo sr_e(sr_t('admin::ui.admin.40b9a17c')); ?></legend>
                                    <?php foreach ($allowedRoles as $roleKey) { ?>
                                        <?php $roleInputId = $roleModalId . '-role-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($roleKey)); ?>
                                        <label class="admin-role-choice admin-form-check form-label" for="<?php echo sr_e($roleInputId); ?>">
                                            <input id="<?php echo sr_e($roleInputId); ?>" type="checkbox" name="role_keys[]" value="<?php echo sr_e($roleKey); ?>" class="form-checkbox"<?php echo in_array($roleKey, $adminAccount['roles'], true) ? ' checked' : ''; ?><?php echo $roleKey === $allowedRoles[0] ? ' data-overlay-focus' : ''; ?>>
                                            <span><?php echo sr_e(sr_admin_code_label($roleKey, 'role')); ?></span>
                                        </label>
                                    <?php } ?>
                                </fieldset>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.save.member.admin.f97e1d67')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($roleModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.save.a6e3d7fe')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('admin::ui.admin.c6bfc841')); ?></strong>
        <p><?php echo sr_e(sr_t('admin::ui.member.6c9f2a2d')); ?></p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
