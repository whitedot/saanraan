<?php

$adminPageTitle = sr_t('admin::ui.admin.menu.c4a18693');
$adminPageSubtitle = sr_t('admin::ui.admin.menu.help.7144cc38');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/menu')); ?>" class="admin-card admin-list-card card admin-list-form admin-menu-form">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="reset_confirmed" value="0" data-admin-menu-reset-confirmed>
    <div class="card-header admin-menu-toolbar-header">
        <div class="card-actions admin-menu-toolbar-actions" role="group" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.view.controls.2ef4208b')); ?>">
            <button type="button" class="btn btn-sm btn-ghost-secondary" data-admin-menu-toggle-all data-expand-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.expand.all.193cff6e')); ?>" data-collapse-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.collapse.all.44ea49b3')); ?>">
                <?php echo sr_material_icon_html('unfold_less'); ?><span data-admin-menu-toggle-all-label><?php echo sr_e(sr_t('admin::ui.admin.menu.collapse.all.44ea49b3')); ?></span>
            </button>
        </div>
    </div>
    <div class="table-wrapper">
    <table class="table admin-menu-table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('admin::ui.text.83b651b8')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.2281025b')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.admin.menu.fold.54b48c17')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.8c609deb')); ?></th>
                <th><?php echo sr_e(sr_t('admin::ui.text.0eeb676f')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php $menuRowCount = count($menuRows); ?>
            <?php foreach ($menuRows as $rowIndex => $row) { ?>
                <?php
                $rowDepth = max(0, min(2, (int) ($row['depth'] ?? 0)));
                $rowContext = (string) ($row['context'] ?? '');
                $rowPath = (string) ($row['path'] ?? '');
                $canHide = !empty($row['can_hide']);
                $nextRow = $rowIndex + 1 < $menuRowCount ? $menuRows[$rowIndex + 1] : null;
                $hasChildren = is_array($nextRow) && (int) ($nextRow['depth'] ?? 0) > $rowDepth;
                $hiddenInputId = 'modules_admin_menu_is_hidden_' . preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $row['form_key']);
                ?>
                <tr class="admin-menu-row admin-menu-row-depth-<?php echo sr_e((string) $rowDepth); ?>" data-admin-sortable-row data-sort-scope="<?php echo sr_e((string) $row['scope']); ?>" data-sort-parent="<?php echo sr_e((string) $row['parent_key']); ?>" data-sort-key="<?php echo sr_e((string) $row['target_key']); ?>" data-sort-depth="<?php echo sr_e((string) $rowDepth); ?>" data-sort-has-children="<?php echo $hasChildren ? '1' : '0'; ?>">
                    <td>
                        <input type="hidden" name="sort_order[<?php echo sr_e((string) $row['form_key']); ?>]" value="<?php echo sr_e((string) $row['sort_order']); ?>" data-admin-sort-order>
                        <span class="admin-menu-move-controls" role="group" aria-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.controls.9f2a3e31')); ?>">
                            <span class="admin-drag-handle" draggable="true" aria-label="<?php echo sr_e(sr_t('admin::ui.text.baef0d03')); ?>" title="<?php echo sr_e(sr_t('admin::ui.text.baef0d03')); ?>"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span>
                            <button type="button" class="btn btn-icon-xs btn-ghost-default admin-menu-move-button" data-admin-sort-move="up" aria-label="<?php echo sr_e((string) $row['label'] . ' ' . sr_t('admin::ui.admin.menu.move.up.062e2b54')); ?>" title="<?php echo sr_e(sr_t('admin::ui.admin.menu.move.up.062e2b54')); ?>"><?php echo sr_material_icon_html('keyboard_arrow_up'); ?></button>
                            <button type="button" class="btn btn-icon-xs btn-ghost-default admin-menu-move-button" data-admin-sort-move="down" aria-label="<?php echo sr_e((string) $row['label'] . ' ' . sr_t('admin::ui.admin.menu.move.down.8091bdb8')); ?>" title="<?php echo sr_e(sr_t('admin::ui.admin.menu.move.down.8091bdb8')); ?>"><?php echo sr_material_icon_html('keyboard_arrow_down'); ?></button>
                        </span>
                    </td>
                    <td>
                        <span class="admin-menu-scope-badge admin-menu-scope-<?php echo sr_e((string) $row['scope']); ?>">
                            <?php echo sr_e(sr_admin_code_label((string) $row['scope'], 'admin_menu_scope')); ?>
                        </span>
                    </td>
                    <td class="admin-menu-fold-cell">
                        <?php if ($hasChildren) { ?>
                            <button type="button" class="btn btn-icon-xs btn-ghost-secondary admin-menu-toggle-button" data-admin-menu-children-toggle aria-expanded="true" aria-label="<?php echo sr_e((string) $row['label'] . ' ' . sr_t('admin::ui.admin.menu.collapse.8d967f3f')); ?>" title="<?php echo sr_e(sr_t('admin::ui.admin.menu.collapse.8d967f3f')); ?>" data-collapse-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.collapse.8d967f3f')); ?>" data-expand-label="<?php echo sr_e(sr_t('admin::ui.admin.menu.expand.f083983d')); ?>">
                                <?php echo sr_material_icon_html('unfold_less'); ?>
                            </button>
                        <?php } else { ?>
                            <span class="text-muted" aria-hidden="true">-</span>
                        <?php } ?>
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
