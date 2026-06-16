<?php

$adminPageTitle = sr_t('privacy::ui.privacy.216d449a');
$adminPageSubtitle = sr_t('privacy::ui.privacy.status.18ec0f1a');
$adminContainerClass = 'admin-page-privacy-request-list admin-ui-scope';
$privacyRequestListFilters = isset($privacyRequestListFilters) && is_array($privacyRequestListFilters) ? $privacyRequestListFilters : [
    'request_id' => 0,
    'status' => '',
    'request_type' => '',
    'field' => 'all',
    'q' => '',
];
$privacyRequestStatusCounts = isset($privacyRequestStatusCounts) && is_array($privacyRequestStatusCounts) ? $privacyRequestStatusCounts : [];
$privacyRequestSort = isset($privacyRequestSort) && is_array($privacyRequestSort) ? $privacyRequestSort : sr_admin_privacy_request_default_sort();
$allowedTypes = isset($allowedTypes) && is_array($allowedTypes) ? $allowedTypes : [];
$privacyRequestCreateDraft = isset($privacyRequestCreateDraft) && is_array($privacyRequestCreateDraft) ? $privacyRequestCreateDraft : [];
$privacyRequestCreateErrors = isset($privacyRequestCreateErrors) && is_array($privacyRequestCreateErrors) ? array_values(array_map('strval', $privacyRequestCreateErrors)) : [];
$privacyRequestCreateModalOpen = !empty($privacyRequestCreateModalOpen);
$selectedPrivacyRequestStatuses = is_array($privacyRequestListFilters['status'] ?? null) ? $privacyRequestListFilters['status'] : [];
$selectedPrivacyRequestTypes = is_array($privacyRequestListFilters['request_type'] ?? null) ? $privacyRequestListFilters['request_type'] : [];
$totalPrivacyRequests = (int) ($privacyRequestStatusCounts['total'] ?? count($requests ?? []));
$privacyRequestCurrentQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
$privacyRequestActionSuffix = $privacyRequestCurrentQuery !== '' ? '?' . $privacyRequestCurrentQuery : '';
$privacyRequestHasSearch = $selectedPrivacyRequestStatuses !== [] || $selectedPrivacyRequestTypes !== [] || trim((string) ($privacyRequestListFilters['q'] ?? '')) !== '';
$privacyRequestCreateModalId = 'privacy-request-create-modal';
$privacyRequestCreateOverlayClass = $privacyRequestCreateModalOpen
    ? 'modal-overlay modal-overlay-fade overlay overlay-open open'
    : 'modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/privacy-requests');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('privacy::ui.text.e65a8646')); ?> <strong><?php echo sr_e((string) $totalPrivacyRequests); ?><?php echo sr_e(sr_t('privacy::ui.text.f5fd44f2')); ?></strong></span>
        <?php foreach ($allowedStatuses as $status) { ?>
            <a href="<?php echo sr_e(sr_url('/admin/privacy-requests?status=' . rawurlencode($status))); ?>" class="admin-summary-meta">
                <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?> <?php echo sr_e((string) ($privacyRequestStatusCounts[$status] ?? 0)); ?><?php echo sr_e(sr_t('privacy::ui.text.f5fd44f2')); ?>
            </a>
        <?php } ?>
    </div>
</div>

<?php $privacyRequestDetailFilterOpen = $selectedPrivacyRequestStatuses !== [] || $selectedPrivacyRequestTypes !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="filtering-form admin-privacy-request-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $privacyRequestDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-privacy-request-search-grid">
                <div class="filtering-field">
                    <label for="privacy_request_search_field" class="filtering-label">검색조건</label>
                    <select name="field" id="privacy_request_search_field" class="form-select filtering-input">
                        <?php foreach (['all' => sr_t('privacy::ui.all.a4b69faf'), 'id' => sr_t('privacy::ui.id.ea1d060e'), 'account' => sr_t('privacy::ui.id.e2088e89'), 'requester' => sr_t('privacy::ui.text.16bf0f07'), 'message' => sr_t('privacy::ui.text.c165c36d'), 'note' => sr_t('privacy::ui.admin.35568056')] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($privacyRequestListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                <?php echo sr_e($fieldLabel); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field filtering-field-fill">
                    <label for="privacy_request_search_keyword" class="filtering-label"><?php echo sr_e(sr_t('privacy::ui.search.bda397fc')); ?></label>
                    <input type="text" name="q" id="privacy_request_search_keyword" value="<?php echo sr_e((string) ($privacyRequestListFilters['q'] ?? '')); ?>" class="form-input filtering-input" placeholder="<?php echo sr_e(sr_t('privacy::ui.id.602ff8c1')); ?>">
                </div>
        </div>
        <div id="privacy_request_detail_filters" class="filtering-body" data-filtering-body<?php echo $privacyRequestDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field">
                    <span class="filtering-label"><?php echo sr_e(sr_t('privacy::ui.status.e10195a1')); ?></span>
                    <?php echo sr_admin_filter_toggle_group_html('privacy_request_status', 'status', sr_admin_code_label_options($allowedStatuses, 'privacy_request_status'), $selectedPrivacyRequestStatuses, sr_t('privacy::ui.all.a4b69faf')); ?>
                </div>
                <div class="filtering-field">
                    <label for="privacy_request_type" class="filtering-label"><?php echo sr_e(sr_t('privacy::ui.text.9305558c')); ?></label>
                    <select id="privacy_request_type" name="request_type" class="form-select filtering-input">
                        <option value=""><?php echo sr_e(sr_t('privacy::ui.all.a4b69faf')); ?></option>
                        <?php foreach ($allowedTypes as $requestType) { ?>
                            <option value="<?php echo sr_e($requestType); ?>"<?php echo in_array($requestType, $selectedPrivacyRequestTypes, true) ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?></option>
                        <?php } ?>
                    </select>
                </div>
        </div>
        <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $privacyRequestDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="privacy_request_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('privacy::ui.search.4b8d541e')); ?></button>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('privacy::ui.privacy.list.ba466a40')); ?></h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $privacyRequestCreateModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($privacyRequestCreateModalId); ?>" data-overlay="#<?php echo sr_e($privacyRequestCreateModalId); ?>">
            기록 추가
        </button>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($privacyRequestSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_privacy_request_sort_options(), sr_admin_privacy_request_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="개인정보 대응 기록 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($privacyRequestPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table admin-privacy-request-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('privacy::ui.privacy.list.ba466a40')); ?></caption>
        <thead class="ui-table-head">
            <tr>
                <th<?php echo sr_admin_sort_aria('request_type', $privacyRequestSort); ?>><?php echo sr_admin_sort_header_html(sr_t('privacy::ui.text.5cf2792b'), 'request_type', $privacyRequestSort, sr_admin_privacy_request_sort_options(), sr_admin_privacy_request_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('status', $privacyRequestSort); ?>><?php echo sr_admin_sort_header_html(sr_t('privacy::ui.status.e10195a1'), 'status', $privacyRequestSort, sr_admin_privacy_request_sort_options(), sr_admin_privacy_request_default_sort()); ?></th>
                <th><?php echo sr_e(sr_t('privacy::ui.text.16bf0f07')); ?></th>
                <th><?php echo sr_e(sr_t('privacy::ui.text.c165c36d')); ?></th>
                <th<?php echo sr_admin_sort_aria('handled_at', $privacyRequestSort); ?>><?php echo sr_admin_sort_header_html(sr_t('privacy::ui.text.73bb6cce'), 'handled_at', $privacyRequestSort, sr_admin_privacy_request_sort_options(), sr_admin_privacy_request_default_sort()); ?></th>
                <th class="text-end"><?php echo sr_e(sr_t('privacy::ui.text.16f64fe4')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($requests === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state"><?php echo sr_e(sr_t('privacy::ui.privacy.b027f225')); ?></td>
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
                $requestId = (string) $request['id'];
                $storedAdminNote = trim((string) ($request['admin_note'] ?? ''));
                $noteRequired = in_array($requestStatus, sr_admin_privacy_request_terminal_statuses(), true) && $storedAdminNote === '';
                $completedRequired = $requestStatus === 'completed';
                ?>
                <tr>
                    <td><?php echo sr_e(sr_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                    <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($requestStatus, 'privacy_request_status')); ?></span></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_requester_display($request)); ?></td>
                    <td><?php echo sr_e(sr_admin_privacy_request_list_preview($request['request_message'] ?? null)); ?></td>
                    <td><?php echo sr_privacy_time_html((string) ($request['handled_at'] ?? '')); ?></td>
                    <td class="admin-table-actions-cell">
                        <div class="admin-row-actions privacy-request-manage">
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests/export')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo sr_e($requestId); ?>">
                                <label class="sr-only" for="privacy_export_password_<?php echo sr_e($requestId); ?>"><?php echo sr_e(sr_t('privacy::ui.admin.password.d9e14cef')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                                <input type="password" name="admin_password" id="privacy_export_password_<?php echo sr_e($requestId); ?>" class="form-input" autocomplete="current-password" required placeholder="<?php echo sr_e(sr_t('privacy::ui.admin.password.d9e14cef')); ?>">
                                <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('privacy::ui.text.af6fa16d')); ?></button>
                            </form>
                            <details class="admin-inline-edit-details privacy-request-details">
                                <summary class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('privacy::ui.status.22916f6e')); ?></summary>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests' . $privacyRequestActionSuffix)); ?>" class="admin-inline-edit-form privacy-request-edit-form" data-privacy-request-form data-terminal-statuses="<?php echo sr_e(implode(',', sr_admin_privacy_request_terminal_statuses())); ?>" data-has-admin-note="<?php echo $storedAdminNote !== '' ? '1' : '0'; ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo sr_e($requestId); ?>">
                                    <input type="hidden" name="status" value="<?php echo sr_e($requestStatus); ?>" data-privacy-status>
                                    <p class="admin-form-help">상태 변경은 대응 기록만 저장합니다. 실제 정정, 처리 제한, 동의 철회 조치는 소유 모듈 화면에서 처리하고 메모에 근거를 남기세요.</p>
                                    <div class="admin-row-actions" role="group" aria-label="<?php echo sr_e(sr_t('privacy::ui.status.e10195a1')); ?>">
                                        <?php if (!in_array($requestStatus, sr_admin_privacy_request_terminal_statuses(), true)) { ?>
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <?php if ($requestStatus === $status) { ?>
                                                    <?php continue; ?>
                                                <?php } ?>
                                                <?php $statusLabel = sr_admin_code_label($status, 'privacy_request_status'); ?>
                                                <?php $confirmMessage = sr_admin_row_action_confirm_message($status, $statusLabel); ?>
                                                <button type="submit" name="status" value="<?php echo sr_e($status); ?>" class="btn btn-sm <?php echo sr_e(sr_admin_row_action_button_class($status)); ?>" data-privacy-status-action<?php echo $confirmMessage !== '' ? ' data-confirm-message="' . sr_e($confirmMessage) . '"' : ''; ?>><?php echo sr_e($statusLabel); ?></button>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                    <label for="privacy_note_<?php echo sr_e($requestId); ?>">
                                        <span><?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?> <span class="sr-required-label" data-privacy-note-required<?php echo $noteRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                        <textarea name="admin_note" id="privacy_note_<?php echo sr_e($requestId); ?>" class="form-textarea" rows="3" cols="30" placeholder="<?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?>" data-privacy-note<?php echo $noteRequired ? ' required' : ''; ?>></textarea>
                                        <small class="admin-form-help">처리 근거와 결과만 적고 제3자 개인정보, 주민등록번호, 원문 연락처, 비밀번호, 토큰은 넣지 마세요.</small>
                                    </label>
                                    <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group">
                                        <span class="filtering-toggle-item">
                                            <input id="modules_privacy_admin_privacy_requests_identity_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="identity_confirmed" value="1" class="form-choice-toggle-input sr-only" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                            <label for="modules_privacy_admin_privacy_requests_identity_confirmed_<?php echo sr_e($requestId); ?>" class="btn btn-choice-light btn-group-start"><?php echo sr_e(sr_t('privacy::ui.text.68a81b47')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                                        </span>
                                        <span class="filtering-toggle-item">
                                            <input id="modules_privacy_admin_privacy_requests_export_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="export_confirmed" value="1" class="form-choice-toggle-input sr-only" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                            <label for="modules_privacy_admin_privacy_requests_export_confirmed_<?php echo sr_e($requestId); ?>" class="btn btn-choice-light btn-group-middle"><?php echo sr_e(sr_t('privacy::ui.text.8a54a65a')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                                        </span>
                                        <span class="filtering-toggle-item">
                                            <input id="modules_privacy_admin_privacy_requests_action_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="action_confirmed" value="1" class="form-choice-toggle-input sr-only" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                            <label for="modules_privacy_admin_privacy_requests_action_confirmed_<?php echo sr_e($requestId); ?>" class="btn btn-choice-light btn-group-end"><?php echo sr_e(sr_t('privacy::ui.admin.5a81e50f')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                                        </span>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('privacy::ui.save.5fb92622')); ?></button>
                                </form>
                            </details>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <?php echo sr_admin_status_description_list_html('privacy_request_status'); ?>
</section>

<div id="<?php echo sr_e($privacyRequestCreateModalId); ?>" class="<?php echo sr_e($privacyRequestCreateOverlayClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($privacyRequestCreateModalId); ?>_title" aria-hidden="<?php echo $privacyRequestCreateModalOpen ? 'false' : 'true'; ?>"<?php echo $privacyRequestCreateModalOpen ? '' : ' inert'; ?>>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests' . $privacyRequestActionSuffix)); ?>" class="modal-content ui-form-theme" data-sr-validate-form data-privacy-create-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create_request">
            <div class="modal-header">
                <h2 id="<?php echo sr_e($privacyRequestCreateModalId); ?>_title" class="modal-title">대응 기록 추가</h2>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($privacyRequestCreateModalId); ?>">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <?php if ($privacyRequestCreateErrors !== []) { ?>
                    <div class="alert alert-danger alert-block privacy-request-create-error-summary" tabindex="-1" data-overlay-focus>
                        <strong>입력값을 확인하세요.</strong>
                        <ul>
                            <?php foreach ($privacyRequestCreateErrors as $createError) { ?>
                                <li><?php echo sr_e($createError); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>
                <div class="admin-form-row">
                    <label class="form-label" for="privacy_create_account_id">계정 ID</label>
                    <div class="admin-form-field">
                        <input id="privacy_create_account_id" type="number" name="account_id" value="<?php echo sr_e((string) ($privacyRequestCreateDraft['account_id'] ?? '')); ?>" class="form-input" min="1" inputmode="numeric" data-privacy-create-account data-validation-message="계정 ID 또는 요청자 중 하나를 입력하세요."<?php echo $privacyRequestCreateErrors === [] ? ' data-overlay-focus' : ''; ?>>
                        <small class="admin-form-help">회원 계정과 연결할 때만 입력하세요.</small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="privacy_create_requester_snapshot">요청자</label>
                    <div class="admin-form-field">
                        <input id="privacy_create_requester_snapshot" type="text" name="requester_snapshot" value="<?php echo sr_e((string) ($privacyRequestCreateDraft['requester_snapshot'] ?? '')); ?>" class="form-input" maxlength="255" autocomplete="off" data-privacy-create-requester data-validation-message="계정 ID 또는 요청자 중 하나를 입력하세요.">
                        <small class="admin-form-help">계정 ID가 없으면 이메일 또는 문의 식별값을 입력하세요.</small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="privacy_create_request_type">요청 유형 <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="privacy_create_request_type" name="request_type" class="form-select" required data-validation-message="요청 유형을 선택하세요.">
                            <option value="">선택</option>
                            <?php foreach ($allowedTypes as $requestType) { ?>
                                <option value="<?php echo sr_e($requestType); ?>"<?php echo (string) ($privacyRequestCreateDraft['request_type'] ?? '') === $requestType ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?></option>
                            <?php } ?>
                        </select>
                        <small class="admin-form-help">요청 유형은 대응 기록입니다. 정정, 처리 제한, 동의 철회는 실제 모듈 데이터를 자동 변경하지 않으므로 처리 메모에 확인한 화면과 조치를 남기세요.</small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="privacy_create_request_message">요청 내용 <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <textarea id="privacy_create_request_message" name="request_message" class="form-textarea" rows="4" maxlength="2000" required data-validation-message="요청 내용을 입력하세요."><?php echo sr_e((string) ($privacyRequestCreateDraft['request_message'] ?? '')); ?></textarea>
                        <small class="admin-form-help">외부 문의로 접수한 요청 취지와 확인해야 할 범위만 적으세요. 제3자 개인정보, 주민등록번호, 원문 비밀번호, 토큰은 넣지 마세요.</small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="privacy_create_admin_note">관리자 메모</label>
                    <div class="admin-form-field">
                        <textarea id="privacy_create_admin_note" name="admin_note" class="form-textarea" rows="3" maxlength="2000"><?php echo sr_e((string) ($privacyRequestCreateDraft['admin_note'] ?? '')); ?></textarea>
                        <small class="admin-form-help">본인 확인 경로와 처리 근거만 남기고 제3자 개인정보, 주민등록번호, 원문 연락처, 비밀번호, 토큰은 넣지 마세요.</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($privacyRequestCreateModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">기록 추가</button>
            </div>
        </form>
    </div>
</div>

<noscript>
    <section class="admin-card card admin-form-card">
        <div class="card-header">
            <h2 class="card-title">대응 기록 추가</h2>
        </div>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests' . $privacyRequestActionSuffix)); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create_request">
            <div class="admin-form-grid">
                <label for="privacy_create_nojs_account_id">
                    <span>계정 ID</span>
                    <input id="privacy_create_nojs_account_id" type="number" name="account_id" value="<?php echo sr_e((string) ($privacyRequestCreateDraft['account_id'] ?? '')); ?>" class="form-input" min="1" inputmode="numeric">
                    <small class="admin-form-help">회원 계정과 연결할 때만 입력하세요.</small>
                </label>
                <label for="privacy_create_nojs_requester_snapshot">
                    <span>요청자</span>
                    <input id="privacy_create_nojs_requester_snapshot" type="text" name="requester_snapshot" value="<?php echo sr_e((string) ($privacyRequestCreateDraft['requester_snapshot'] ?? '')); ?>" class="form-input" maxlength="255" autocomplete="off">
                    <small class="admin-form-help">계정 ID가 없으면 이메일 또는 문의 식별값을 입력하세요. 계정 ID 또는 요청자 중 하나는 서버에서 필수로 확인합니다.</small>
                </label>
                <label for="privacy_create_nojs_request_type">
                    <span>요청 유형 <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                    <select id="privacy_create_nojs_request_type" name="request_type" class="form-select" required>
                        <option value="">선택</option>
                        <?php foreach ($allowedTypes as $requestType) { ?>
                            <option value="<?php echo sr_e($requestType); ?>"<?php echo (string) ($privacyRequestCreateDraft['request_type'] ?? '') === $requestType ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?></option>
                        <?php } ?>
                    </select>
                    <small class="admin-form-help">요청 유형은 대응 기록입니다. 정정, 처리 제한, 동의 철회는 실제 모듈 데이터를 자동 변경하지 않으므로 처리 메모에 확인한 화면과 조치를 남기세요.</small>
                </label>
            </div>
            <label for="privacy_create_nojs_request_message">
                <span>요청 내용 <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                <textarea id="privacy_create_nojs_request_message" name="request_message" class="form-textarea" rows="4" maxlength="2000" required><?php echo sr_e((string) ($privacyRequestCreateDraft['request_message'] ?? '')); ?></textarea>
                <small class="admin-form-help">외부 문의로 접수한 요청 취지와 확인해야 할 범위만 적으세요. 제3자 개인정보, 주민등록번호, 원문 비밀번호, 토큰은 넣지 마세요.</small>
            </label>
            <label for="privacy_create_nojs_admin_note">
                <span>관리자 메모</span>
                <textarea id="privacy_create_nojs_admin_note" name="admin_note" class="form-textarea" rows="3" maxlength="2000"><?php echo sr_e((string) ($privacyRequestCreateDraft['admin_note'] ?? '')); ?></textarea>
                <small class="admin-form-help">본인 확인 경로와 처리 근거만 남기고 제3자 개인정보, 주민등록번호, 원문 연락처, 비밀번호, 토큰은 넣지 마세요.</small>
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-solid-primary">기록 추가</button>
            </div>
        </form>
    </section>
</noscript>

<?php echo sr_admin_pagination_html($privacyRequestPagination, '개인정보 대응 기록 목록 페이지'); ?>

<script>
(function () {
    function syncPrivacyRequestForm(form) {
        var status = form.querySelector('[data-privacy-status]');
        var note = form.querySelector('[data-privacy-note]');
        var noteRequiredLabel = form.querySelector('[data-privacy-note-required]');
        var terminalStatuses = (form.getAttribute('data-terminal-statuses') || '').split(',');
        var hasAdminNote = form.getAttribute('data-has-admin-note') === '1';
        var noteNeeded = !!(status && terminalStatuses.indexOf(status.value) !== -1 && !hasAdminNote);
        var completedNeeded = !!(status && status.value === 'completed');

        if (note) {
            note.required = noteNeeded;
        }
        if (noteRequiredLabel) {
            noteRequiredLabel.hidden = !noteNeeded;
        }
        form.querySelectorAll('[data-privacy-completed-check]').forEach(function (check) {
            check.required = completedNeeded;
        });
        form.querySelectorAll('[data-privacy-completed-required]').forEach(function (label) {
            label.hidden = !completedNeeded;
        });
    }

    document.querySelectorAll('[data-privacy-request-form]').forEach(function (form) {
        syncPrivacyRequestForm(form);
        form.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[data-privacy-status]')) {
                syncPrivacyRequestForm(form);
            }
        });
        form.querySelectorAll('[data-privacy-status-action]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                var confirmMessage = button.getAttribute('data-confirm-message') || '';
                if (confirmMessage !== '' && !window.confirm(confirmMessage)) {
                    event.preventDefault();
                    return;
                }
                var status = form.querySelector('[data-privacy-status]');
                if (status) {
                    status.value = button.value;
                    syncPrivacyRequestForm(form);
                }
            });
        });
    });

    function syncPrivacyCreateIdentity(form) {
        var account = form.querySelector('[data-privacy-create-account]');
        var requester = form.querySelector('[data-privacy-create-requester]');
        if (!account || !requester) {
            return true;
        }

        var hasIdentity = account.value.trim() !== '' || requester.value.trim() !== '';
        var message = hasIdentity ? '' : '계정 ID 또는 요청자 중 하나를 입력하세요.';
        account.setCustomValidity(message);
        requester.setCustomValidity(message);

        return hasIdentity;
    }

    document.querySelectorAll('[data-privacy-create-form]').forEach(function (form) {
        syncPrivacyCreateIdentity(form);
        form.addEventListener('input', function (event) {
            if (event.target && event.target.matches('[data-privacy-create-account], [data-privacy-create-requester]')) {
                syncPrivacyCreateIdentity(form);
            }
        });
        form.addEventListener('submit', function () {
            syncPrivacyCreateIdentity(form);
        });
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
