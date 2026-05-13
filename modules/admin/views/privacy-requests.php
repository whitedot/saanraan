<?php

$adminPageTitle = '개인정보 요청';
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

<form method="get" action="<?php echo toy_e(toy_url('/admin/privacy-requests')); ?>">
    <label>상태<br>
        <select name="status">
            <option value="">전체</option>
            <?php foreach ($allowedStatuses as $status) { ?>
                <option value="<?php echo toy_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                    <?php echo toy_e(toy_admin_code_label($status, 'privacy_request_status')); ?>
                </option>
            <?php } ?>
        </select>
    </label>
    <button type="submit">조회</button>
</form>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>계정</th>
            <th>유형</th>
            <th>상태</th>
            <th>요청자</th>
            <th>요청 내용</th>
            <th>처리일</th>
            <th>변경</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($requests === []) { ?>
            <tr>
                <td colspan="8">개인정보 요청이 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($requests as $request) { ?>
            <tr>
                <td><?php echo toy_e((string) $request['id']); ?></td>
                <td><?php echo toy_e((string) ($request['account_id'] ?? '')); ?></td>
                <td><?php echo toy_e(toy_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                <td><?php echo toy_e(toy_admin_code_label((string) $request['status'], 'privacy_request_status')); ?></td>
                <td><?php echo toy_e(toy_admin_privacy_request_requester_display($request)); ?></td>
                <td><?php echo toy_e(toy_admin_privacy_request_list_preview($request['request_message'] ?? null)); ?></td>
                <td><?php echo toy_e((string) ($request['handled_at'] ?? '')); ?></td>
                <td>
                    <form method="post" action="<?php echo toy_e(toy_url('/admin/privacy-requests/export')); ?>">
                        <?php echo toy_csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo toy_e((string) $request['id']); ?>">
                        <label>관리자 비밀번호<br>
                            <input type="password" name="admin_password" autocomplete="current-password" required>
                        </label>
                        <button type="submit">JSON</button>
                    </form>
                    <form method="post" action="<?php echo toy_e(toy_url('/admin/privacy-requests')); ?>">
                        <?php echo toy_csrf_field(); ?>
                        <input type="hidden" name="request_id" value="<?php echo toy_e((string) $request['id']); ?>">
                        <select name="status">
                            <?php foreach ($allowedStatuses as $status) { ?>
                                <option value="<?php echo toy_e($status); ?>"<?php echo $request['status'] === $status ? ' selected' : ''; ?>>
                                    <?php echo toy_e(toy_admin_code_label($status, 'privacy_request_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <textarea name="admin_note" rows="3" cols="30" placeholder="새 관리자 메모"></textarea>
                        <label>
                            <input type="checkbox" name="identity_confirmed" value="1">
                            요청자 확인
                        </label>
                        <label>
                            <input type="checkbox" name="export_confirmed" value="1">
                            내보내기 또는 처리 결과 확인
                        </label>
                        <label>
                            <input type="checkbox" name="action_confirmed" value="1">
                            관리자 메모에 처리 내용 기록
                        </label>
                        <button type="submit">저장</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php include TOY_ROOT . '/modules/admin/views/layout-footer.php'; ?>
