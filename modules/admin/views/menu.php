<?php

$adminPageTitle = '관리자 메뉴';
include TOY_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo toy_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo toy_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<form method="post" action="<?php echo toy_e(toy_url('/admin/menu')); ?>">
    <?php echo toy_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_menu_overrides">
    <table>
        <thead>
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
                    <td><?php echo toy_e(toy_admin_code_label((string) $row['scope'], 'admin_menu_scope')); ?></td>
                    <td><?php echo toy_e((string) $row['label']); ?></td>
                    <td><?php echo toy_e((string) $row['default_order']); ?></td>
                    <td>
                        <input
                            type="number"
                            name="sort_order[<?php echo toy_e((string) $row['form_key']); ?>]"
                            value="<?php echo toy_e((string) $row['sort_order']); ?>"
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
                                value="<?php echo toy_e((string) $row['form_key']); ?>"
                                <?php echo !empty($row['is_hidden']) ? 'checked' : ''; ?>
                            >
                            숨김
                        </label>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <button type="submit">메뉴 표시 설정 저장</button>
</form>

<form method="post" action="<?php echo toy_e(toy_url('/admin/menu')); ?>">
    <?php echo toy_csrf_field(); ?>
    <input type="hidden" name="intent" value="reset_menu_overrides">
    <button type="submit">기본값으로 초기화</button>
</form>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
