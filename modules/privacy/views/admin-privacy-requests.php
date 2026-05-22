<?php

$adminPageTitle = '개인정보 처리 요청';
$adminPageSubtitle = '접수된 개인정보 처리 요청을 확인하고 처리 상태를 관리합니다.';
$adminContainerClass = 'admin-page-privacy-request-list admin-ui-scope';
$privacyRequestListFilters = isset($privacyRequestListFilters) && is_array($privacyRequestListFilters) ? $privacyRequestListFilters : [
    'status' => '',
    'request_type' => '',
    'field' => 'all',
    'q' => '',
];
$privacyRequestStatusCounts = isset($privacyRequestStatusCounts) && is_array($privacyRequestStatusCounts) ? $privacyRequestStatusCounts : [];
$allowedTypes = isset($allowedTypes) && is_array($allowedTypes) ? $allowedTypes : [];
$totalPrivacyRequests = (int) ($privacyRequestStatusCounts['total'] ?? count($requests ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="btn btn-solid-light">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총요청 <strong><?php echo sr_e((string) $totalPrivacyRequests); ?>건</strong></span>
        <?php foreach ($allowedStatuses as $status) { ?>
            <a href="<?php echo sr_e(sr_url('/admin/privacy-requests?status=' . rawurlencode($status))); ?>" class="admin-summary-meta">
                <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?> <?php echo sr_e((string) ($privacyRequestStatusCounts[$status] ?? 0)); ?>건
            </a>
        <?php } ?>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="admin-filter admin-privacy-request-filter ui-form-theme">
    <div class="admin-filter-grid admin-privacy-request-search-grid">
        <div class="admin-filter-field">
            <label for="privacy_request_status" class="admin-filter-label">상태</label>
            <select name="status" id="privacy_request_status" class="form-select admin-filter-input">
                <option value="">전체</option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($privacyRequestListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_type" class="admin-filter-label">요청 유형</label>
            <select name="request_type" id="privacy_request_type" class="form-select admin-filter-input">
                <option value="">전체</option>
                <?php foreach ($allowedTypes as $requestType) { ?>
                    <option value="<?php echo sr_e($requestType); ?>"<?php echo (string) ($privacyRequestListFilters['request_type'] ?? '') === $requestType ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_search_field" class="admin-filter-label">검색 조건</label>
            <select name="field" id="privacy_request_search_field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'id' => '요청 ID', 'account' => '계정 ID', 'requester' => '요청자', 'message' => '요청 내용', 'note' => '관리자 메모'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($privacyRequestListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_search_keyword" class="admin-filter-label">검색어</label>
            <input type="text" name="q" id="privacy_request_search_keyword" value="<?php echo sr_e((string) ($privacyRequestListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="ID, 요청자, 요청 내용, 메모">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">개인정보 처리 요청 목록</h2>
    </div>
    <div class="table-wrapper">
    <table class="table admin-privacy-request-table">
        <caption class="sr-only">개인정보 처리 요청 목록</caption>
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
                <?php
                $requestStatus = (string) $request['status'];
                $statusClass = 'is-blocked';
                if ($requestStatus === 'completed') {
                    $statusClass = 'is-normal';
                } elseif (in_array($requestStatus, ['rejected', 'cancelled'], true)) {
                    $statusClass = 'is-left';
                }
                ?>
                <tr>
                    <td><?php echo sr_e((string) $request['id']); ?></td>
                    <td><?php echo sr_e((string) ($request['account_id'] ?? '')); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($requestStatus, 'privacy_request_status')); ?></span></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_requester_display($request)); ?></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_list_preview($request['request_message'] ?? null)); ?></td>
                    <td><?php echo sr_e((string) ($request['handled_at'] ?? '')); ?></td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions privacy-request-manage">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests/export')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo sr_e((string) $request['id']); ?>">
                                <label class="sr-only" for="privacy_export_password_<?php echo sr_e((string) $request['id']); ?>">관리자 비밀번호 <span class="sr-required-label">(필수)</span></label>
                                <input type="password" name="admin_password" id="privacy_export_password_<?php echo sr_e((string) $request['id']); ?>" class="form-input" autocomplete="current-password" required placeholder="관리자 비밀번호">
                                <button type="submit" class="btn btn-sm btn-solid-light">처리 자료 내려받기</button>
                            </form>
                            <details class="admin-inline-edit-details privacy-request-details">
                                <summary class="btn btn-sm btn-solid-light">상태 변경</summary>
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
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_identity_confirmed">
                                        <input id="modules_privacy_admin_privacy_requests_identity_confirmed" type="checkbox" name="identity_confirmed" value="1" class="form-checkbox">
                                        <span class="form-label">요청자 확인</span>
                                    </label>
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_export_confirmed">
                                        <input id="modules_privacy_admin_privacy_requests_export_confirmed" type="checkbox" name="export_confirmed" value="1" class="form-checkbox">
                                        <span class="form-label">처리 자료 또는 처리 결과 확인</span>
                                    </label>
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_action_confirmed">
                                        <input id="modules_privacy_admin_privacy_requests_action_confirmed" type="checkbox" name="action_confirmed" value="1" class="form-checkbox">
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
