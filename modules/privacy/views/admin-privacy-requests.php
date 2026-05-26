<?php

$adminPageTitle = sr_t('privacy::ui.privacy.216d449a');
$adminPageSubtitle = sr_t('privacy::ui.privacy.status.18ec0f1a');
$adminContainerClass = 'admin-page-privacy-request-list admin-ui-scope';
$privacyRequestListFilters = isset($privacyRequestListFilters) && is_array($privacyRequestListFilters) ? $privacyRequestListFilters : [
    'status' => '',
    'request_type' => '',
    'field' => 'all',
    'q' => '',
];
$privacyRequestStatusCounts = isset($privacyRequestStatusCounts) && is_array($privacyRequestStatusCounts) ? $privacyRequestStatusCounts : [];
$privacyRequestSort = isset($privacyRequestSort) && is_array($privacyRequestSort) ? $privacyRequestSort : sr_admin_privacy_request_default_sort();
$allowedTypes = isset($allowedTypes) && is_array($allowedTypes) ? $allowedTypes : [];
$totalPrivacyRequests = (int) ($privacyRequestStatusCounts['total'] ?? count($requests ?? []));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('privacy::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('privacy::ui.text.e65a8646')); ?> <strong><?php echo sr_e((string) $totalPrivacyRequests); ?><?php echo sr_e(sr_t('privacy::ui.text.f5fd44f2')); ?></strong></span>
        <?php foreach ($allowedStatuses as $status) { ?>
            <a href="<?php echo sr_e(sr_url('/admin/privacy-requests?status=' . rawurlencode($status))); ?>" class="admin-summary-meta">
                <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?> <?php echo sr_e((string) ($privacyRequestStatusCounts[$status] ?? 0)); ?><?php echo sr_e(sr_t('privacy::ui.text.f5fd44f2')); ?>
            </a>
        <?php } ?>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="admin-filter admin-privacy-request-filter ui-form-theme">
    <div class="admin-filter-grid admin-privacy-request-search-grid">
        <div class="admin-filter-field">
            <label for="privacy_request_status" class="admin-filter-label"><?php echo sr_e(sr_t('privacy::ui.status.e10195a1')); ?></label>
            <select name="status" id="privacy_request_status" class="form-select admin-filter-input">
                <option value=""><?php echo sr_e(sr_t('privacy::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($privacyRequestListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_type" class="admin-filter-label"><?php echo sr_e(sr_t('privacy::ui.text.9305558c')); ?></label>
            <select name="request_type" id="privacy_request_type" class="form-select admin-filter-input">
                <option value=""><?php echo sr_e(sr_t('privacy::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedTypes as $requestType) { ?>
                    <option value="<?php echo sr_e($requestType); ?>"<?php echo (string) ($privacyRequestListFilters['request_type'] ?? '') === $requestType ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_search_field" class="admin-filter-label"><?php echo sr_e(sr_t('privacy::ui.search.b79bc9c8')); ?></label>
            <select name="field" id="privacy_request_search_field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('privacy::ui.all.a4b69faf'), 'id' => sr_t('privacy::ui.id.ea1d060e'), 'account' => sr_t('privacy::ui.id.e2088e89'), 'requester' => sr_t('privacy::ui.text.16bf0f07'), 'message' => sr_t('privacy::ui.text.c165c36d'), 'note' => sr_t('privacy::ui.admin.35568056')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($privacyRequestListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field">
            <label for="privacy_request_search_keyword" class="admin-filter-label"><?php echo sr_e(sr_t('privacy::ui.search.bda397fc')); ?></label>
            <input type="text" name="q" id="privacy_request_search_keyword" value="<?php echo sr_e((string) ($privacyRequestListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" placeholder="<?php echo sr_e(sr_t('privacy::ui.id.602ff8c1')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('privacy::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('privacy::ui.privacy.list.ba466a40')); ?></h2>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($privacyRequestSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_admin_privacy_request_sort_options(), sr_admin_privacy_request_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="개인정보 요청 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
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
                    <td><?php echo sr_e((string) ($request['handled_at'] ?? '')); ?></td>
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
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/privacy-requests')); ?>" class="admin-inline-edit-form privacy-request-edit-form" data-privacy-request-form data-terminal-statuses="<?php echo sr_e(implode(',', sr_admin_privacy_request_terminal_statuses())); ?>" data-has-admin-note="<?php echo $storedAdminNote !== '' ? '1' : '0'; ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="request_id" value="<?php echo sr_e($requestId); ?>">
                                    <label for="privacy_status_<?php echo sr_e($requestId); ?>">
                                        <span><?php echo sr_e(sr_t('privacy::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                        <select name="status" id="privacy_status_<?php echo sr_e($requestId); ?>" class="form-select" data-privacy-status>
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $request['status'] === $status ? ' selected' : ''; ?>>
                                                    <?php echo sr_e(sr_admin_code_label($status, 'privacy_request_status')); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </label>
                                    <label for="privacy_note_<?php echo sr_e($requestId); ?>">
                                        <span><?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?> <span class="sr-required-label" data-privacy-note-required<?php echo $noteRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                        <textarea name="admin_note" id="privacy_note_<?php echo sr_e($requestId); ?>" class="form-textarea" rows="3" cols="30" placeholder="<?php echo sr_e(sr_t('privacy::ui.admin.79636dee')); ?>" data-privacy-note<?php echo $noteRequired ? ' required' : ''; ?>></textarea>
                                    </label>
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_identity_confirmed_<?php echo sr_e($requestId); ?>">
                                        <input id="modules_privacy_admin_privacy_requests_identity_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="identity_confirmed" value="1" class="form-checkbox" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                        <span class="form-label"><?php echo sr_e(sr_t('privacy::ui.text.68a81b47')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                    </label>
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_export_confirmed_<?php echo sr_e($requestId); ?>">
                                        <input id="modules_privacy_admin_privacy_requests_export_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="export_confirmed" value="1" class="form-checkbox" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                        <span class="form-label"><?php echo sr_e(sr_t('privacy::ui.text.8a54a65a')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                    </label>
                                    <label class="admin-form-check form-label" for="modules_privacy_admin_privacy_requests_action_confirmed_<?php echo sr_e($requestId); ?>">
                                        <input id="modules_privacy_admin_privacy_requests_action_confirmed_<?php echo sr_e($requestId); ?>" type="checkbox" name="action_confirmed" value="1" class="form-checkbox" data-privacy-completed-check<?php echo $completedRequired ? ' required' : ''; ?>>
                                        <span class="form-label"><?php echo sr_e(sr_t('privacy::ui.admin.5a81e50f')); ?> <span class="sr-required-label" data-privacy-completed-required<?php echo $completedRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('privacy::ui.required.1f227c67')); ?></span></span>
                                    </label>
                                    <button type="submit" class="btn btn-sm btn-solid-primary"><?php echo sr_e(sr_t('privacy::ui.save.5fb92622')); ?></button>
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
<?php echo sr_admin_pagination_html($privacyRequestPagination, '개인정보 요청 목록 페이지'); ?>

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
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
