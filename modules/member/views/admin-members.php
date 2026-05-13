<?php

$adminPageTitle = '회원 관리';
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

<form method="get" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
    <label>상태<br>
        <select name="status">
            <option value="">전체</option>
            <?php foreach ($allowedStatuses as $status) { ?>
                <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                    <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                </option>
            <?php } ?>
        </select>
    </label>
    <button type="submit">조회</button>
</form>

<table>
    <thead>
        <tr>
            <th>공개 해시</th>
            <th>이메일</th>
            <th>표시명</th>
            <th>상태</th>
            <th>이메일 인증</th>
            <th>최근 로그인</th>
            <th>활성 세션</th>
            <th>생성일</th>
            <th>변경</th>
            <th>세션</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($members === []) { ?>
            <tr>
                <td colspan="10">회원이 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($members as $member) { ?>
            <tr>
                <td><?php echo sr_e((string) $member['account_public_hash']); ?></td>
                <td><?php echo sr_e(sr_admin_member_email_display($member)); ?></td>
                <td><?php echo sr_e(sr_admin_member_display_name_preview($member)); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $member['status'], 'member_status')); ?></td>
                <td><?php echo sr_e((string) ($member['email_verified_at'] ?? '')); ?></td>
                <td><?php echo sr_e((string) ($member['last_login_at'] ?? '')); ?></td>
                <td><?php echo sr_e((string) $member['active_session_count']); ?></td>
                <td><?php echo sr_e((string) $member['created_at']); ?></td>
                <td>
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="status">
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo $member['status'] === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'member_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <button type="submit">저장</button>
                    </form>
                </td>
                <td>
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/members')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="revoke_sessions">
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $member['id']); ?>">
                        <button type="submit">폐기</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
