<?php

$adminPageTitle = '관리자 작업 로그';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/audit-logs')); ?>">
    <p>
        <label>이벤트 유형<br>
            <input type="text" name="event_type" value="<?php echo sr_e($filters['event_type']); ?>" maxlength="80">
        </label>
    </p>
    <p>
        <label>대상 유형<br>
            <input type="text" name="target_type" value="<?php echo sr_e($filters['target_type']); ?>" maxlength="60">
        </label>
    </p>
    <p>
        <label>처리자 계정 ID<br>
            <input type="text" name="actor_account_id" value="<?php echo sr_e($filters['actor_account_id']); ?>" maxlength="20" inputmode="numeric" pattern="[0-9]*">
        </label>
    </p>
    <p>
        <label>결과<br>
            <select name="result">
                <?php foreach (['' => '전체', 'success' => '성공', 'failure' => '실패'] as $value => $label) { ?>
                    <option value="<?php echo sr_e((string) $value); ?>"<?php echo $filters['result'] === (string) $value ? ' selected' : ''; ?>>
                        <?php echo sr_e($label); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
    </p>
    <p>
        <label>시작일<br>
            <input type="date" name="date_from" value="<?php echo sr_e($filters['date_from']); ?>">
        </label>
    </p>
    <p>
        <label>종료일<br>
            <input type="date" name="date_to" value="<?php echo sr_e($filters['date_to']); ?>">
        </label>
    </p>
    <button type="submit">조회</button>
</form>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>시각</th>
            <th>처리자</th>
            <th>이벤트</th>
            <th>대상</th>
            <th>결과</th>
            <th>IP</th>
            <th>메시지</th>
            <th>메타</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($logs === []) { ?>
            <tr>
                <td colspan="9">감사 로그가 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($logs as $log) { ?>
            <tr>
                <td><?php echo sr_e((string) $log['id']); ?></td>
                <td><?php echo sr_e((string) $log['created_at']); ?></td>
                <td><?php echo sr_e((string) ($log['actor_account_id'] ?? $log['actor_type'])); ?></td>
                <td><?php echo sr_e(sr_admin_event_type_label((string) $log['event_type'])); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $log['target_type'], 'target_type') . ':' . (string) $log['target_id']); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $log['result'], 'result')); ?></td>
                <td><?php echo sr_e((string) $log['ip_address']); ?></td>
                <td><?php echo sr_e(sr_admin_audit_log_display_message($log)); ?></td>
                <td><?php echo sr_e(sr_admin_audit_log_display_metadata($log)); ?></td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
