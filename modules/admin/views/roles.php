<?php

$adminPageTitle = '관리자 권한';
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

<div class="member-table-card admin-member-list-form">
<div class="table-wrapper">
<table class="table">
    <thead class="ui-table-head">
        <tr>
            <th>공개 해시</th>
            <th>이메일</th>
            <th>표시명</th>
            <th>계정 상태</th>
            <th>현재 역할</th>
            <th class="text-end">변경</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($accounts as $adminAccount) { ?>
            <tr>
                <td><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></td>
                <td><?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $adminAccount['status'], 'member_status')); ?></td>
                <td><?php echo sr_e(implode(', ', array_map(static function (string $roleKey): string {
                    return sr_admin_code_label($roleKey, 'role');
                }, $adminAccount['roles']))); ?></td>
                <td class="member-cell-manage">
                    <div class="member-manage">
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/roles')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $adminAccount['id']); ?>">
                        <select name="role_key">
                            <?php foreach ($allowedRoles as $roleKey) { ?>
                                <option value="<?php echo sr_e($roleKey); ?>"><?php echo sr_e(sr_admin_code_label($roleKey, 'role')); ?></option>
                            <?php } ?>
                        </select>
                        <select name="role_action">
                            <option value="grant">부여</option>
                            <option value="revoke">회수</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-surface-default-soft">저장</button>
                    </form>
                    </div>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
