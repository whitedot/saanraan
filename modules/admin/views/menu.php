<?php

$adminPageTitle = '관리자 메뉴';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/menu')); ?>" class="admin-card admin-list-card card admin-list-form admin-menu-form">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="reset_confirmed" value="0" data-admin-menu-reset-confirmed>
    <div class="card-header">
        <h2 class="card-title">관리자 메뉴 표시 설정</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>이동</th>
                <th>범위</th>
                <th>대상</th>
                <th>기본 순서</th>
                <th>표시 순서</th>
                <th>숨김</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menuRows as $row) { ?>
                <?php
                $rowDepth = max(0, min(2, (int) ($row['depth'] ?? 0)));
                $rowContext = (string) ($row['context'] ?? '');
                $rowPath = (string) ($row['path'] ?? '');
                ?>
                <tr class="admin-menu-row admin-menu-row-depth-<?php echo sr_e((string) $rowDepth); ?>" data-admin-sortable-row data-sort-scope="<?php echo sr_e((string) $row['scope']); ?>" data-sort-parent="<?php echo sr_e((string) $row['parent_key']); ?>" data-sort-key="<?php echo sr_e((string) $row['target_key']); ?>" data-sort-depth="<?php echo sr_e((string) $rowDepth); ?>">
                    <td><span class="admin-drag-handle" draggable="true" aria-label="드래그해서 순서 변경"><?php echo sr_material_icon_html('apps', 'admin-drag-handle-icon'); ?></span></td>
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
                    <td>
                        <input
                            type="number"
                            name="sort_order[<?php echo sr_e((string) $row['form_key']); ?>]"
                            value="<?php echo sr_e((string) $row['sort_order']); ?>"
                            data-admin-sort-order
                            min="-999999"
                            max="999999"
                            required class="form-input">
                    </td>
                    <td>
                        <label class="admin-form-check form-label">
                            <input
                                type="checkbox"
                                name="is_hidden[]"
                                value="<?php echo sr_e((string) $row['form_key']); ?>"
                                class="form-checkbox"
                                <?php echo !empty($row['is_hidden']) ? 'checked' : ''; ?>
                            >
                            숨김
                        </label>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <div class="admin-form-actions admin-form-sticky-actions admin-menu-form-actions">
        <button type="submit" name="intent" value="reset_menu_overrides" class="btn btn-outline-danger" data-admin-menu-reset-confirm data-confirm-message="관리자 메뉴 표시 순서와 숨김 설정이 모두 기본값으로 초기화됩니다. 계속 진행할까요?">기본값으로 초기화</button>
        <button type="submit" name="intent" value="save_menu_overrides" class="btn btn-solid-primary">메뉴 표시 설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
