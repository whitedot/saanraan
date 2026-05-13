<?php

$adminPageTitle = '관리자 권한';
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

<table>
    <thead>
        <tr>
            <th>공개 해시</th>
            <th>이메일</th>
            <th>표시명</th>
            <th>계정 상태</th>
            <th>현재 역할</th>
            <th>변경</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts as $adminAccount) { ?>
            <tr>
                <td><?php echo toy_e((string) $adminAccount['account_public_hash']); ?></td>
                <td><?php echo toy_e(toy_admin_member_email_display($adminAccount)); ?></td>
                <td><?php echo toy_e(toy_admin_member_display_name_preview($adminAccount)); ?></td>
                <td><?php echo toy_e(toy_admin_code_label((string) $adminAccount['status'], 'member_status')); ?></td>
                <td><?php echo toy_e(implode(', ', array_map(static function (string $roleKey): string {
                    return toy_admin_code_label($roleKey, 'role');
                }, $adminAccount['roles']))); ?></td>
                <td>
                    <form method="post" action="<?php echo toy_e(toy_url('/admin/roles')); ?>">
                        <?php echo toy_csrf_field(); ?>
                        <input type="hidden" name="account_id" value="<?php echo toy_e((string) $adminAccount['id']); ?>">
                        <select name="role_key">
                            <?php foreach ($allowedRoles as $roleKey) { ?>
                                <option value="<?php echo toy_e($roleKey); ?>"><?php echo toy_e(toy_admin_code_label($roleKey, 'role')); ?></option>
                            <?php } ?>
                        </select>
                        <select name="role_action">
                            <option value="grant">부여</option>
                            <option value="revoke">회수</option>
                        </select>
                        <button type="submit">저장</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
