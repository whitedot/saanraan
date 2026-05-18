<?php

$adminPageTitle = '개인정보 처리 요청';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="admin-filter ui-form-theme">
    <div class="admin-filter-grid admin-filter-grid-compact">
        <label class="admin-filter-field" for="privacy_request_status">
            <span class="admin-filter-label">상태</span>
            <select name="status" id="privacy_request_status" class="form-select">
                <option value="">전체</option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo $statusFilter === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">개인정보 처리 요청 목록</h2>
    </div>
    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>ID</th>
                <th>계정</th>
                <th>유형</th>
                <th>상태</th>
                <th>요청자</th>
                <th>요청 내용</th>
                <th>처리일</th>
                <th class="text-end">변경</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($requests === []) { ?>
                <tr>
                    <td colspan="8" class="admin-empty-state">개인정보 처리 요청이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($requests as $request) { ?>
                <tr>
                    <td><?php echo sr_e((string) $request['id']); ?></td>
                    <td><?php echo sr_e((string) ($request['account_id'] ?? '')); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $request['status'], 'privacy_request_status')); ?></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_requester_display($request)); ?></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_list_preview($request['request_message'] ?? null)); ?></td>
                    <td><?php echo sr_e((string) ($request['handled_at'] ?? '')); ?></td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions privacy-request-manage">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests/export')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo sr_e((string) $request['id']); ?>">
                                <label class="sr-only" for="privacy_export_password_<?php echo sr_e((string) $request['id']); ?>">관리자 비밀번호</label>
                                <input type="password" name="admin_password" id="privacy_export_password_<?php echo sr_e((string) $request['id']); ?>" class="form-input" autocomplete="current-password" required placeholder="관리자 비밀번호">
                                <button type="submit" class="btn btn-sm btn-surface-default-soft">처리 자료 내려받기</button>
                            </form>
                            <details class="admin-inline-edit-details privacy-request-details">
                                <summary class="btn btn-sm btn-surface-default-soft">상태 변경</summary>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="admin-inline-edit-form privacy-request-edit-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo sr_e((string) $request['id']); ?>">
                                    <label for="privacy_status_<?php echo sr_e((string) $request['id']); ?>">
                                        <span>상태</span>
                                        <select name="status" id="privacy_status_<?php echo sr_e((string) $request['id']); ?>" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $request['status'] === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                    <label for="privacy_note_<?php echo sr_e((string) $request['id']); ?>">
                                        <span>새 관리자 메모</span>
                                        <textarea name="admin_note" id="privacy_note_<?php echo sr_e((string) $request['id']); ?>" class="form-textarea" rows="3" cols="30" placeholder="새 관리자 메모"></textarea>
                                    </label>
                                    <label class="admin-form-check form-label">
                                        <input type="checkbox" name="identity_confirmed" value="1" class="form-checkbox">
                                        <span class="form-label">요청자 확인</span>
                                    </label>
                                    <label class="admin-form-check form-label">
                                        <input type="checkbox" name="export_confirmed" value="1" class="form-checkbox">
                                        <span class="form-label">처리 자료 또는 처리 결과 확인</span>
                                    </label>
                                    <label class="admin-form-check form-label">
                                        <input type="checkbox" name="action_confirmed" value="1" class="form-checkbox">
                                        <span class="form-label">관리자 메모에 처리 내용 기록</span>
                                    </label>
                                    <button type="submit" class="btn btn-sm btn-solid-primary">저장</button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
