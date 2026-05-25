<?php

$adminPageTitle = sr_t('admin::ui.admin.menu.c4a18693');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/menu')); ?>" class="admin-card admin-list-card card admin-list-form admin-menu-form">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="reset_confirmed" value="0" data-admin-menu-reset-confirmed>
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('admin::ui.admin.menu.settings.80f94b6f')); ?></h2>
    </div>
    <div class="table-wrapper">
    <table class="table admin-menu-table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.83b651b8')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.2281025b')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.a9e7497f')); ?></th>
                <th class="admin-menu-sort-order-cell"><?php echo sr_e(sr_t('admin::ui.text.ff0e602e')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.0eeb676f')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menuRows as $row) { ?>
                <?php
                $rowDepth = max(0, min(2, (int) ($row['depth'] ?? 0)));
                $rowContext = (string) ($row['context'] ?? '');
                $rowPath = (string) ($row['path'] ?? '');
                $canHide = !empty($row['can_hide']);
                $hiddenInputId = 'modules_admin_menu_is_hidden_' . preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $row['form_key']);
                ?>
                <tr class="admin-menu-row admin-menu-row-depth-<?php echo sr_e((string) $rowDepth); ?>" data-admin-sortable-row data-sort-scope="<?php echo sr_e((string) $row['scope']); ?>" data-sort-parent="<?php echo sr_e((string) $row['parent_key']); ?>" data-sort-key="<?php echo sr_e((string) $row['target_key']); ?>" data-sort-depth="<?php echo sr_e((string) $rowDepth); ?>">
                    <td><span class="admin-drag-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.baef0d03')); ?>"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span></td>
                    <td>
                        <span class="admin-menu-scope-badge admin-menu-scope-<?php echo sr_e((string) $row['scope']); ?>">
                            <?php echo sr_e(sr_admin_code_label((string) $row['scope'], 'admin_menu_scope')); ?>
                        </span>
                    </td>
                    <td class="admin-menu-target-cell">
                        <div class="admin-menu-target admin-menu-target-depth-<?php echo sr_e((string) $rowDepth); ?>">
                            <?php if ($rowDepth > 0) { ?>
                                <span class="admin-menu-tree-branch" aria-hidden="true"></span>
                            <?php } ?>
                            <span class="admin-menu-target-copy">
                                <span class="admin-menu-target-label"><?php echo sr_e((string) $row['label']); ?></span>
                                <?php if ($rowContext !== '' || $rowPath !== '') { ?>
                                    <span class="admin-menu-target-context">
                                        <?php echo sr_e($rowContext); ?><?php echo $rowContext !== '' && $rowPath !== '' ? ' · ' : ''; ?><?php echo sr_e($rowPath); ?>
                                    </span>
                                <?php } ?>
                            </span>
                        </div>
                    </td>
                    <td><?php echo sr_e((string) $row['default_order']); ?></td>
                    <td class="admin-menu-sort-order-cell">
                        <input
                            type="number"
                            name="sort_order[<?php echo sr_e((string) $row['form_key']); ?>]"
                            value="<?php echo sr_e((string) $row['sort_order']); ?>"
                            data-admin-sort-order
                            min="-999999"
                            max="999999"
                            required class="form-input admin-menu-sort-order-input">
                    </td>
                    <td>
                        <?php if ($canHide) { ?>
                            <label class="admin-form-check form-label" for="<?php echo sr_e($hiddenInputId); ?>">
                                <input id="<?php echo sr_e($hiddenInputId); ?>"
                                    type="checkbox"
                                    name="is_hidden[]"
                                    value="<?php echo sr_e((string) $row['form_key']); ?>"
                                    class="form-checkbox"
                                    <?php echo !empty($row['is_hidden']) ? 'checked' : ''; ?>
                                >
                                <?php echo sr_e(sr_t('admin::ui.text.0eeb676f')); ?>
                            </label>
                        <?php } else { ?>
                            <span class="text-muted"><?php echo sr_e(sr_t('admin::ui.text.dee6ce99')); ?></span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <div class="admin-form-actions admin-form-sticky-actions admin-menu-form-actions">
        <button type="submit" name="intent" value="reset_menu_overrides" class="btn btn-outline-danger" data-admin-menu-reset-confirm data-confirm-message="<?php echo sr_e(sr_t('admin::ui.admin.menu.settings.d694bdec')); ?>"><?php echo sr_e(sr_t('admin::ui.text.4fa71701')); ?></button>
        <button type="submit" name="intent" value="save_menu_overrides" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.menu.settings.save.914d293b')); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
