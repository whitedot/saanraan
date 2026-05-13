<?php

$adminPageTitle = '관리자 메뉴';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/menu')); ?>" class="member-table-card admin-member-list-form">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_menu_overrides">
    <div class="card-header">
        <h2 class="card-title">관리자 메뉴 표시 설정</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>범위</th>
                <th>대상</th>
                <th>기본 순서</th>
                <th>표시 순서</th>
                <th>숨김</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($menuRows as $row) { ?>
                <tr>
                    <td><?php echo sr_e(sr_admin_code_label((string) $row['scope'], 'admin_menu_scope')); ?></td>
                    <td><?php echo sr_e((string) $row['label']); ?></td>
                    <td><?php echo sr_e((string) $row['default_order']); ?></td>
                    <td>
                        <input
                            type="number"
                            name="sort_order[<?php echo sr_e((string) $row['form_key']); ?>]"
                            value="<?php echo sr_e((string) $row['sort_order']); ?>"
                            min="-999999"
                            max="999999"
                            required
                        >
                    </td>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="is_hidden[]"
                                value="<?php echo sr_e((string) $row['form_key']); ?>"
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
    <div class="member-list-actions">
        <button type="submit" class="btn btn-solid-primary">메뉴 표시 설정 저장</button>
    </div>
</form>

<form method="post" action="<?php echo sr_e(sr_url('/admin/menu')); ?>">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="reset_menu_overrides">
    <button type="submit" class="btn btn-outline-danger">기본값으로 초기화</button>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
