<?php

$adminPageTitle = '커뮤니티 신고 관리';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-community-report-list admin-ui-scope';
$reportListFilters = isset($reportListFilters) && is_array($reportListFilters) ? $reportListFilters : ['report_id' => 0, 'status' => [], 'target_type' => [], 'reason_key' => [], 'field' => 'all', 'q' => ''];
$reportStatusCounts = isset($reportStatusCounts) && is_array($reportStatusCounts) ? $reportStatusCounts : [];
$allowedStatuses = isset($allowedStatuses) && is_array($allowedStatuses) ? $allowedStatuses : [];
$allowedReasonKeys = isset($allowedReasonKeys) && is_array($allowedReasonKeys) ? $allowedReasonKeys : [];
$allowedTargetTypes = isset($allowedTargetTypes) && is_array($allowedTargetTypes) ? $allowedTargetTypes : ['post', 'comment', 'message'];
$reportAutoActionsByTarget = isset($reportAutoActionsByTarget) && is_array($reportAutoActionsByTarget) ? $reportAutoActionsByTarget : [];
$accountGuardRows = isset($accountGuardRows) && is_array($accountGuardRows) ? $accountGuardRows : [];
$reportTargetLabels = [];
foreach ($allowedTargetTypes as $allowedTargetType) {
    $reportTargetLabels[(string) $allowedTargetType] = sr_community_report_target_type_label((string) $allowedTargetType);
}
$reportStatusPolicyDescriptions = sr_community_report_status_policy_descriptions();
$totalReports = (int) ($reportStatusCounts['total'] ?? count($reports ?? []));
$selectedReportStatuses = is_array($reportListFilters['status'] ?? null) ? $reportListFilters['status'] : [];
$selectedReportTargetTypes = is_array($reportListFilters['target_type'] ?? null) ? $reportListFilters['target_type'] : [];
$selectedReportReasonKeys = is_array($reportListFilters['reason_key'] ?? null) ? $reportListFilters['reason_key'] : [];
$canViewAuditLogs = isset($canViewAuditLogs) && $canViewAuditLogs === true;
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/reports');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.5648e366')); ?> <strong><?php echo sr_e((string) $totalReports); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=open')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.d995d6ab')); ?> <?php echo sr_e((string) ($reportStatusCounts['open'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=reviewing')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.7e0e2126')); ?> <?php echo sr_e((string) ($reportStatusCounts['reviewing'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=resolved')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?> <?php echo sr_e((string) ($reportStatusCounts['resolved'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=dismissed')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.0d655420')); ?> <?php echo sr_e((string) ($reportStatusCounts['dismissed'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
    </div>
</div>

<section class="card admin-list-card admin-community-account-guards">
    <div class="card-header"><h2 class="card-title">회원 작성 제한</h2></div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-account-guard-table">
            <caption class="sr-only">활성 커뮤니티 회원 작성 제한 목록</caption>
            <thead>
                <tr>
                    <th>회원</th>
                    <th>유형</th>
                    <th>상태</th>
                    <th>근거</th>
                    <th>시작</th>
                    <th>만료</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accountGuardRows === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">활성 회원 작성 제한이 없습니다.</td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($accountGuardRows as $accountGuard) { ?>
                        <?php
                        $accountGuardId = (int) ($accountGuard['id'] ?? 0);
                        $accountGuardType = (string) ($accountGuard['guard_type'] ?? '');
                        $accountGuardStatus = (string) ($accountGuard['status'] ?? '');
                        $accountGuardModalId = 'community-account-guard-release-modal-' . (string) $accountGuardId;
                        $accountGuardReason = trim((string) ($accountGuard['trigger_reason'] ?? ''));
                        $accountGuardSourceType = trim((string) ($accountGuard['source_type'] ?? ''));
                        $accountGuardSourceId = (int) ($accountGuard['source_id'] ?? 0);
                        $accountGuardSourceLabels = [
                            'report_auto_action' => '신고 자동조치',
                        ];
                        $accountGuardSourceLabel = (string) ($accountGuardSourceLabels[$accountGuardSourceType] ?? $accountGuardSourceType);
                        $accountGuardSource = $accountGuardSourceLabel !== '' && $accountGuardSourceId > 0 ? $accountGuardSourceLabel . ' #' . (string) $accountGuardSourceId : '';
                        ?>
                        <tr>
                            <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                                is_string($accountGuard['account_display_name'] ?? null) ? $accountGuard['account_display_name'] : null,
                                (int) ($accountGuard['account_id'] ?? 0),
                                is_string($accountGuard['account_status'] ?? null) ? $accountGuard['account_status'] : null,
                                is_string($accountGuard['account_nickname'] ?? null) ? $accountGuard['account_nickname'] : null,
                                isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                            )); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_community_account_guard_type_label($accountGuardType)); ?></td>
                            <td class="admin-table-nowrap"><span class="badge-status is-warning"><?php echo sr_e(sr_community_account_guard_status_label($accountGuardStatus)); ?></span></td>
                            <td class="admin-table-break">
                                <?php echo sr_e($accountGuardReason !== '' ? $accountGuardReason : '-'); ?>
                                <?php if ($accountGuardSource !== '') { ?>
                                    <span class="admin-table-subtext"><?php echo sr_e($accountGuardSource); ?></span>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($accountGuard['starts_at'] ?? $accountGuard['event_created_at'] ?? $accountGuard['created_at'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($accountGuard['expires_at'] ?? '')); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php if ($canViewAuditLogs) { ?>
                                        <?php
                                        $accountGuardLogUrl = sr_url('/admin/audit-logs?' . http_build_query([
                                            'field' => 'metadata_json',
                                            'q' => '"guard_type":"' . $accountGuardType . '"',
                                        ], '', '&', PHP_QUERY_RFC3986));
                                        ?>
                                        <a href="<?php echo sr_e($accountGuardLogUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-icon btn-solid-light" aria-label="작성 제한 로그 새 탭으로 열기" title="작성 제한 로그"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                    <?php } ?>
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-warning" aria-label="작성 제한 해제" title="작성 제한 해제" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($accountGuardModalId); ?>" data-overlay="#<?php echo sr_e($accountGuardModalId); ?>"><?php echo sr_material_icon_html('lock_open'); ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($accountGuardRows !== []) { ?>
    <?php foreach ($accountGuardRows as $accountGuard) { ?>
        <?php
        $accountGuardId = (int) ($accountGuard['id'] ?? 0);
        $accountGuardModalId = 'community-account-guard-release-modal-' . (string) $accountGuardId;
        $accountGuardReleaseNoteId = 'community_account_guard_release_note_' . (string) $accountGuardId;
        ?>
        <div id="<?php echo sr_e($accountGuardModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($accountGuardModalId); ?>-title" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="modal-content ui-form-theme">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="release_account_guard">
                    <input type="hidden" name="guard_id" value="<?php echo sr_e((string) $accountGuardId); ?>">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($accountGuardModalId); ?>-title" class="modal-title">작성 제한 해제</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($accountGuardModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <p class="admin-summary-meta"><?php echo sr_e(sr_community_account_guard_type_label((string) ($accountGuard['guard_type'] ?? ''))); ?> · <?php echo sr_e(sr_community_report_account_label(
                            is_string($accountGuard['account_display_name'] ?? null) ? $accountGuard['account_display_name'] : null,
                            (int) ($accountGuard['account_id'] ?? 0),
                            is_string($accountGuard['account_status'] ?? null) ? $accountGuard['account_status'] : null,
                            is_string($accountGuard['account_nickname'] ?? null) ? $accountGuard['account_nickname'] : null,
                            isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                        )); ?></p>
                        <div class="form-row">
                            <label for="<?php echo sr_e($accountGuardReleaseNoteId); ?>" class="form-label">해제 메모 <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                            <div class="form-field">
                                <textarea id="<?php echo sr_e($accountGuardReleaseNoteId); ?>" name="release_note" rows="5" class="form-textarea" maxlength="1000" required data-overlay-focus></textarea>
                                <small class="form-help">작성 제한 해제는 감사 로그에 운영자, 대상 제한, 해제 메모를 남깁니다.</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($accountGuardModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-warning modal-action">해제</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<?php $communityReportDetailFilterOpen = $selectedReportStatuses !== [] || $selectedReportTargetTypes !== [] || $selectedReportReasonKeys !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="filtering-form admin-community-report-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $communityReportDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-community-report-search-grid">
            <div class="filtering-field admin-community-report-filter-field">
                <label for="community_admin_reports_field" class="filtering-label">검색조건</label>
                <select id="community_admin_reports_field" name="field" class="form-select filtering-input">
                    <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'target' => sr_t('community::ui.text.8c609deb'), 'reporter' => sr_t('community::ui.text.84780e6f'), 'reported' => '게시자', 'reviewer' => sr_t('community::ui.text.750086e9'), 'memo' => sr_t('community::ui.text.c8a14bcd')] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($reportListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-community-report-filter-keyword">
                <label for="community_admin_reports_q" class="filtering-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
                <input id="community_admin_reports_q" type="text" name="q" value="<?php echo sr_e((string) ($reportListFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.member.fbbe7c33')); ?>">
            </div>
        </div>
        <div id="community_report_detail_filters" class="filtering-body" data-filtering-body<?php echo $communityReportDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field admin-community-report-filter-status">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></span>
                <?php echo sr_admin_filter_toggle_group_html('community_admin_reports_status_filter', 'status', sr_admin_code_label_options($allowedStatuses, 'report_status'), $selectedReportStatuses, sr_t('community::ui.all.a4b69faf')); ?>
            </div>
            <div class="filtering-field admin-community-report-filter-target">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></span>
                <?php
                $reportTargetTypeFilterOptions = [];
                foreach ($allowedTargetTypes as $targetType) {
                    $reportTargetTypeFilterOptions[(string) $targetType] = (string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'));
                }
                echo sr_admin_filter_toggle_group_html('community_admin_reports_target_type_filter', 'target_type', $reportTargetTypeFilterOptions, $selectedReportTargetTypes, sr_t('community::ui.all.a4b69faf'));
                ?>
            </div>
            <div class="filtering-field admin-community-report-filter-reason">
                <span class="filtering-label"><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></span>
                <?php
                $reportReasonFilterOptions = [];
                foreach ($allowedReasonKeys as $reasonKey) {
                    $reportReasonFilterOptions[(string) $reasonKey] = sr_community_report_reason_label((string) $reasonKey);
                }
                echo sr_admin_filter_toggle_group_html('community_admin_reports_reason_filter', 'reason_key', $reportReasonFilterOptions, $selectedReportReasonKeys, sr_t('community::ui.all.a4b69faf'));
                ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $communityReportDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_report_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
            <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.27da9d14')); ?></h2></div>
    <div class="admin-list-summary-row">
        <form id="community-report-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="community-report-bulk-form" data-community-report-bulk-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="batch_status">
            <input type="hidden" name="operation_key" value="community.report_set_status">
            <input type="hidden" name="target_status" value="" data-community-report-bulk-target-status>
            <div class="community-report-bulk-actions admin-row-actions" data-community-report-bulk-bar>
                <div class="community-report-bulk-controls admin-row-actions">
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-community-report-bulk-submit data-target-status="<?php echo sr_e($status); ?>" data-status-label="<?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="community-report-bulk-status-modal" data-overlay="#community-report-bulk-status-modal" disabled><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></button>
                    <?php } ?>
                    <button type="button" class="btn btn-sm btn-outline-light" data-community-report-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-community-report-selected-count>0</span></button>
                </div>
            </div>
            <div id="community-report-bulk-status-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="community-report-bulk-status-modal-title" aria-hidden="true" inert>
                <div class="modal-dialog modal-dialog-lg">
                    <div class="modal-content ui-form-theme">
                        <div class="modal-header">
                            <h3 id="community-report-bulk-status-modal-title" class="modal-title">신고 일괄 처리</h3>
                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#community-report-bulk-status-modal"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <p class="form-help">대상 조치는 처리 완료 상태, 허위신고자 조치는 기각 상태로 저장할 때만 실행합니다.</p>
                            <div class="form-row">
                                <label for="community_report_bulk_target_action" class="form-label">대상 조치</label>
                                <div class="form-field">
                                    <select id="community_report_bulk_target_action" name="target_action" class="form-select" data-community-report-bulk-target-action>
                                        <?php foreach (sr_community_report_batch_target_action_options() as $actionKey => $actionLabel) { ?>
                                            <option value="<?php echo sr_e((string) $actionKey); ?>"><?php echo sr_e((string) $actionLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                    <small class="form-help">숨김/삭제는 게시글과 댓글 신고에만 적용할 수 있습니다. 쪽지 신고가 섞여 있으면 서버에서 거부됩니다.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <label for="community_report_bulk_reporter_action" class="form-label">신고자 조치</label>
                                <div class="form-field">
                                    <select id="community_report_bulk_reporter_action" name="reporter_action" class="form-select" data-community-report-bulk-reporter-action>
                                        <?php foreach (sr_community_report_reporter_action_options() as $actionKey => $actionLabel) { ?>
                                            <option value="<?php echo sr_e((string) $actionKey); ?>"><?php echo sr_e((string) $actionLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                    <small class="form-help">허위신고자 조치는 신고 상태를 기각으로 저장할 때만 실행됩니다.</small>
                                </div>
                            </div>
                            <div class="form-row">
                                <label for="community_report_bulk_review_note" class="form-label"><?php echo sr_e(sr_t('community::ui.text.514556d0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                <div class="form-field">
                                    <textarea id="community_report_bulk_review_note" name="review_note" rows="5" class="form-textarea" maxlength="1000" required data-overlay-focus></textarea>
                                </div>
                            </div>
                            <div class="alert alert-warning community-report-bulk-summary-alert" role="alert" data-community-report-bulk-modal-summary><strong data-community-report-bulk-modal-count>0</strong>개 신고를 선택한 상태로 변경합니다.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#community-report-bulk-status-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                            <button type="submit" class="btn btn-solid-primary modal-action">처리</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php echo sr_admin_pagination_summary_html($reportPagination); ?>
    </div>
    <div class="table-wrapper">
    <table class="table table-list admin-community-report-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.b4e41b31')); ?></caption>
        <thead>
            <tr>
                <th class="admin-table-checkbox-cell community-report-select-cell">
                    <label class="sr-only" for="community_report_bulk_select_all">현재 페이지 신고 전체 선택</label>
                    <input id="community_report_bulk_select_all" type="checkbox" class="form-checkbox" data-community-report-select-all<?php echo $reports === [] ? ' disabled' : ''; ?>>
                </th>
                <th><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                <th>자동조치</th>
                <th><?php echo sr_e(sr_t('community::ui.text.84780e6f')); ?></th>
                <th>게시자</th>
                <th><?php echo sr_e(sr_t('community::ui.text.c8a14bcd')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ebc9b96e')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.750086e9')); ?></th>
                <th>최근 검토</th>
                <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports === []) { ?>
                <tr>
                    <td colspan="12" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.7efff05f')); ?></td>
                </tr>
            <?php } else { ?>
                <?php foreach ($reports as $report) { ?>
                    <?php
                    $reportStatus = (string) $report['status'];
                    $statusClass = match ($reportStatus) {
                        'resolved' => 'is-success',
                        'open', 'reviewing' => 'is-warning',
                        default => 'is-danger',
                    };
                    $targetType = (string) $report['target_type'];
                    $targetLabel = (string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'));
                    $targetId = (int) ($report['target_id'] ?? 0);
                    $targetSummary = $targetType !== '' && $targetId > 0 ? $targetType . ' #' . (string) $targetId : '';
                    $reportAutoActionUid = sr_community_report_auto_action_active_target_uid($targetType, $targetId);
                    $reportAutoAction = $reportAutoActionUid !== '' && is_array($reportAutoActionsByTarget[$reportAutoActionUid] ?? null) ? $reportAutoActionsByTarget[$reportAutoActionUid] : null;
                    $targetPostId = (int) ($report['target_post_id'] ?? 0);
                    $targetPostTitle = trim((string) ($report['target_post_title'] ?? ''));
                    $targetUrl = $targetPostId > 0 ? '/community/post?id=' . rawurlencode((string) $targetPostId) . ($targetType === 'comment' ? '#comments' : '') : '';
                    $reportProcessModalId = 'community-report-process-modal-' . (string) $report['id'];
                    ?>
                    <tr>
                        <td class="admin-table-checkbox-cell community-report-select-cell">
                            <label class="sr-only" for="community_report_bulk_select_<?php echo sr_e((string) (int) $report['id']); ?>"><?php echo sr_e($targetLabel); ?> 신고 선택</label>
                            <input id="community_report_bulk_select_<?php echo sr_e((string) (int) $report['id']); ?>" type="checkbox" name="selected_report_ids[]" value="<?php echo sr_e((string) (int) $report['id']); ?>" class="form-checkbox" form="community-report-bulk-status-form" data-community-report-row-select>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-target-cell">
                            <?php if ($targetUrl !== '') { ?>
                                <a href="<?php echo sr_e(sr_url($targetUrl)); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e($targetLabel . ' 바로가기'); ?>" title="<?php echo sr_e($targetPostTitle); ?>"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                            <?php } else { ?>
                                <span class="admin-table-subtext"><?php echo sr_e($targetSummary !== '' ? $targetSummary : '-'); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-reason-cell"><?php echo sr_e(sr_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($reportStatus, 'report_status')); ?></span></td>
                        <td class="admin-table-nowrap">
                            <?php if (is_array($reportAutoAction)) { ?>
                                <span class="badge-status is-warning"><?php echo sr_e(sr_community_report_auto_action_status_label((string) ($reportAutoAction['status'] ?? 'active'))); ?></span>
                                <span class="admin-table-subtext"><?php echo sr_e((string) (int) ($reportAutoAction['eligible_reporter_count'] ?? 0)); ?>/<?php echo sr_e((string) (int) ($reportAutoAction['threshold_value'] ?? 0)); ?></span>
                            <?php } else { ?>
                                <span class="admin-table-subtext">-</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id'],
                            is_string($report['reporter_account_status'] ?? null) ? $report['reporter_account_status'] : null,
                            is_string($report['reporter_nickname'] ?? null) ? $report['reporter_nickname'] : null,
                            isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id'],
                            is_string($report['reported_account_status'] ?? null) ? $report['reported_account_status'] : null,
                            is_string($report['reported_nickname'] ?? null) ? $report['reported_nickname'] : null,
                            isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-memo-cell">
                            <?php echo sr_e((string) ($report['memo_text'] ?? '')); ?>
                            <?php if (trim((string) ($report['review_note'] ?? '')) !== '') { ?>
                                <span class="admin-community-report-review-note type-caption">처리 메모: <?php echo sr_e((string) $report['review_note']); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_community_time_html((string) $report['created_at']); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell">
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo sr_e(sr_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id'],
                                    is_string($report['reviewer_account_status'] ?? null) ? $report['reviewer_account_status'] : null,
                                    is_string($report['reviewer_nickname'] ?? null) ? $report['reviewer_nickname'] : null,
                                    isset($memberSettings) && is_array($memberSettings) ? $memberSettings : null
                                )); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_community_time_html((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <?php if ($canViewAuditLogs && trim((string) ($report['reviewed_at'] ?? '')) !== '') { ?>
                                    <?php
                                    $reportActionLogUrl = sr_url('/admin/audit-logs?' . http_build_query([
                                        'field' => 'metadata_json',
                                        'q' => '"report_id":' . (string) (int) $report['id'],
                                    ], '', '&', PHP_QUERY_RFC3986));
                                    ?>
                                    <a href="<?php echo sr_e($reportActionLogUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-icon btn-solid-light admin-community-report-action-log-link" aria-label="대상 조치 로그 새 탭으로 열기" title="대상 조치 로그"><?php echo sr_material_icon_html('open_in_new'); ?></a>
                                <?php } ?>
                                <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?>" title="<?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($reportProcessModalId); ?>" data-overlay="#<?php echo sr_e($reportProcessModalId); ?>"><?php echo sr_material_icon_html('fact_check'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
	</table>
	</div>
<div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
    <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('open_in_new'); ?> 바로가기</span>
    <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('fact_check'); ?> <?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></span>
</div>
<?php echo sr_admin_status_description_list_html('report_status'); ?>
</section>
<?php if ($reports !== []) { ?>
    <?php foreach ($reports as $report) { ?>
        <?php
        $reportId = (int) ($report['id'] ?? 0);
        $reportProcessModalId = 'community-report-process-modal-' . (string) $reportId;
        $reportProcessStatusId = 'community_admin_report_status_' . (string) $reportId;
        $reportProcessReviewNoteId = 'community_admin_report_review_note_' . (string) $reportId;
        $reportProcessTargetActionId = 'community_admin_report_target_action_' . (string) $reportId;
        $reportProcessReporterActionId = 'community_admin_report_reporter_action_' . (string) $reportId;
        $targetType = (string) ($report['target_type'] ?? '');
        $targetLabel = (string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'));
        $targetId = (int) ($report['target_id'] ?? 0);
        $targetSummary = $targetType !== '' && $targetId > 0 ? $targetLabel . ' #' . (string) $targetId : $targetLabel;
        $reportAutoActionUid = sr_community_report_auto_action_active_target_uid($targetType, $targetId);
        $reportAutoAction = $reportAutoActionUid !== '' && is_array($reportAutoActionsByTarget[$reportAutoActionUid] ?? null) ? $reportAutoActionsByTarget[$reportAutoActionUid] : null;
        ?>
        <div id="<?php echo sr_e($reportProcessModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($reportProcessModalId); ?>-title" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="modal-content ui-form-theme">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="report_id" value="<?php echo sr_e((string) $reportId); ?>">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($reportProcessModalId); ?>-title" class="modal-title">신고 처리</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($reportProcessModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <p class="admin-summary-meta"><?php echo sr_e($targetSummary); ?> · <?php echo sr_e(sr_community_report_reason_label((string) ($report['reason_key'] ?? ''))); ?></p>
                        <?php if (is_array($reportAutoAction)) { ?>
                            <div class="alert alert-info" role="status">
                                활성 신고 자동조치가 있습니다. 현재 <?php echo sr_e((string) (int) ($reportAutoAction['eligible_reporter_count'] ?? 0)); ?>명이 임계값 <?php echo sr_e((string) (int) ($reportAutoAction['threshold_value'] ?? 0)); ?>명에 도달해 대상이 임시 숨김 처리되었습니다.
                            </div>
                        <?php } ?>
                        <div class="form-row">
                            <label for="<?php echo sr_e($reportProcessStatusId); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                            <div class="form-field">
                                <select id="<?php echo sr_e($reportProcessStatusId); ?>" name="status" class="form-select" required>
                                    <?php foreach ($allowedStatuses as $status) { ?>
                                        <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) ($report['status'] ?? '') ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="form-help">
                                    <?php foreach ($reportStatusPolicyDescriptions as $statusKey => $description) { ?>
                                        <span data-community-report-status-help="<?php echo sr_e((string) $statusKey); ?>"<?php echo $statusKey === (string) ($report['status'] ?? '') ? '' : ' hidden'; ?>><?php echo sr_e((string) $description); ?></span>
                                    <?php } ?>
                                </small>
                            </div>
                        </div>
                        <div class="form-row">
                            <label for="<?php echo sr_e($reportProcessTargetActionId); ?>" class="form-label">대상 조치</label>
                            <div class="form-field">
                                <select id="<?php echo sr_e($reportProcessTargetActionId); ?>" name="target_action" class="form-select">
                                    <?php foreach (sr_community_report_target_action_options($targetType) as $actionKey => $actionLabel) { ?>
                                        <option value="<?php echo sr_e((string) $actionKey); ?>"><?php echo sr_e((string) $actionLabel); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="form-help">대상 조치는 신고 상태를 처리 완료로 저장할 때만 실행됩니다. 기각은 이미 적용된 게시글/댓글/게시자 조치를 되돌리지 않습니다.</small>
                            </div>
                        </div>
                        <div class="form-row">
                            <label for="<?php echo sr_e($reportProcessReporterActionId); ?>" class="form-label">신고자 조치</label>
                            <div class="form-field">
                                <select id="<?php echo sr_e($reportProcessReporterActionId); ?>" name="reporter_action" class="form-select">
                                    <?php foreach (sr_community_report_reporter_action_options() as $actionKey => $actionLabel) { ?>
                                        <option value="<?php echo sr_e((string) $actionKey); ?>"><?php echo sr_e((string) $actionLabel); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="form-help">허위신고자 조치는 신고 상태를 기각으로 저장할 때만 실행됩니다.</small>
                            </div>
                        </div>
                        <?php if (is_array($reportAutoAction)) { ?>
                            <div class="form-row">
                                <label for="community_admin_report_auto_action_<?php echo sr_e((string) $reportId); ?>" class="form-label">자동조치 판단</label>
                                <div class="form-field">
                                    <select id="community_admin_report_auto_action_<?php echo sr_e((string) $reportId); ?>" name="auto_action_status" class="form-select">
                                        <?php foreach (sr_community_report_auto_action_review_options() as $autoActionStatus => $autoActionLabel) { ?>
                                            <option value="<?php echo sr_e((string) $autoActionStatus); ?>"><?php echo sr_e((string) $autoActionLabel); ?></option>
                                        <?php } ?>
                                    </select>
                                    <small class="form-help">해제는 대상이 아직 신고 임계치 자동 숨김 상태일 때 원래 상태로 복원하고 활성 자동조치를 종료합니다.</small>
                                </div>
                            </div>
                        <?php } else { ?>
                            <input type="hidden" name="auto_action_status" value="none">
                        <?php } ?>
                        <div class="form-row">
                            <label for="<?php echo sr_e($reportProcessReviewNoteId); ?>" class="form-label"><?php echo sr_e(sr_t('community::ui.text.514556d0')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                            <div class="form-field">
                                <textarea id="<?php echo sr_e($reportProcessReviewNoteId); ?>" name="review_note" rows="5" class="form-textarea" maxlength="1000" required data-overlay-focus><?php echo sr_e((string) ($report['review_note'] ?? '')); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($reportProcessModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
<?php } ?>
<?php echo sr_admin_pagination_html($reportPagination, '신고 목록 페이지'); ?>

<script>
(function () {
    var bulkForm = document.querySelector('[data-community-report-bulk-form]');
    if (!bulkForm) {
        return;
    }

    var countNode = document.querySelector('[data-community-report-selected-count]');
    var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-community-report-bulk-submit]'));
    var clear = document.querySelector('[data-community-report-bulk-clear]');
    var targetStatusInput = document.querySelector('[data-community-report-bulk-target-status]');
    var modalCountNode = document.querySelector('[data-community-report-bulk-modal-count]');
    var modalSummaryNode = document.querySelector('[data-community-report-bulk-modal-summary]');
    var bulkTargetAction = document.querySelector('[data-community-report-bulk-target-action]');
    var bulkReporterAction = document.querySelector('[data-community-report-bulk-reporter-action]');
    var selectAll = document.querySelector('[data-community-report-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-community-report-row-select]'));

    var checkedRows = function () {
        return rowChecks.filter(function (input) {
            return input.checked && !input.disabled;
        });
    };

    var selectedOptionText = function (select, fallback) {
        if (!select || !select.options || select.selectedIndex < 0 || !select.options[select.selectedIndex]) {
            return fallback;
        }

        return select.options[select.selectedIndex].text || fallback;
    };

    var statusParticle = function (label) {
        if (!label) {
            return '로';
        }
        var code = label.charCodeAt(label.length - 1);
        if (code < 0xAC00 || code > 0xD7A3) {
            return '로';
        }
        var jong = (code - 0xAC00) % 28;

        return jong > 0 && jong !== 8 ? '으로' : '로';
    };

    var syncBulkSummary = function () {
        if (!modalSummaryNode) {
            return;
        }

        var selectedCount = checkedRows().length;
        var targetStatus = targetStatusInput ? targetStatusInput.value : '';
        var statusLabel = '선택한 상태';
        var activeButton = submitButtons.find(function (button) {
            return (button.getAttribute('data-target-status') || '') === targetStatus;
        });
        if (activeButton) {
            statusLabel = activeButton.getAttribute('data-status-label') || activeButton.textContent.replace(/\s+/g, ' ').trim() || statusLabel;
        }

        var targetActionValue = bulkTargetAction ? bulkTargetAction.value : 'none';
        var reporterActionValue = bulkReporterAction ? bulkReporterAction.value : 'none';
        var actionLabel = '';
        if (targetStatus === 'resolved' && targetActionValue !== 'none') {
            actionLabel = selectedOptionText(bulkTargetAction, '대상 조치');
        } else if (targetStatus === 'dismissed' && reporterActionValue !== 'none') {
            actionLabel = selectedOptionText(bulkReporterAction, '신고자 조치');
        }

        if (actionLabel !== '') {
            modalSummaryNode.textContent = String(selectedCount) + '개 신고를 ' + actionLabel + ' 조치로 변경(' + statusLabel + ')합니다.';
            return;
        }

        modalSummaryNode.textContent = String(selectedCount) + '개 신고를 ' + statusLabel + statusParticle(statusLabel) + ' 변경합니다.';
    };

    var syncBulkState = function () {
        var selectedCount = checkedRows().length;
        if (countNode) {
            countNode.textContent = String(selectedCount);
        }
        if (modalCountNode) {
            modalCountNode.textContent = String(selectedCount);
        }
        syncBulkSummary();
        submitButtons.forEach(function (button) {
            button.disabled = selectedCount < 1;
        });
        if (clear) {
            clear.hidden = selectedCount < 1;
        }
        if (selectAll) {
            selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
        }
    };

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (input) {
                if (!input.disabled) {
                    input.checked = selectAll.checked;
                }
            });
            syncBulkState();
        });
    }
    rowChecks.forEach(function (input) {
        input.addEventListener('change', syncBulkState);
    });
    submitButtons.forEach(function (button) {
        button.addEventListener('click', function (event) {
            var selectedCount = checkedRows().length;
            if (selectedCount < 1) {
                event.preventDefault();
                syncBulkState();
                return;
            }
            var targetStatus = button.getAttribute('data-target-status') || '';
            var statusLabel = button.getAttribute('data-status-label') || button.textContent.replace(/\s+/g, ' ').trim();
            if (targetStatusInput) {
                targetStatusInput.value = targetStatus;
            }
            if (modalCountNode) {
                modalCountNode.textContent = String(selectedCount);
            }
            if (bulkTargetAction) {
                bulkTargetAction.disabled = targetStatus !== 'resolved';
                if (targetStatus !== 'resolved') {
                    bulkTargetAction.value = 'none';
                }
            }
            if (bulkReporterAction) {
                bulkReporterAction.disabled = targetStatus !== 'dismissed';
                if (targetStatus !== 'dismissed') {
                    bulkReporterAction.value = 'none';
                }
            }
            syncBulkSummary();
        });
    });
    if (bulkTargetAction) {
        bulkTargetAction.addEventListener('change', function () {
            syncBulkSummary();
        });
    }
    if (bulkReporterAction) {
        bulkReporterAction.addEventListener('change', function () {
            syncBulkSummary();
        });
    }
    if (clear) {
        clear.addEventListener('click', function () {
            rowChecks.forEach(function (input) {
                input.checked = false;
            });
            syncBulkState();
        });
    }
    bulkForm.addEventListener('submit', function (event) {
        var selectedCount = checkedRows().length;
        if (selectedCount < 1) {
            event.preventDefault();
            syncBulkState();
            return;
        }
        if (!targetStatusInput || targetStatusInput.value === '') {
            event.preventDefault();
            syncBulkState();
        }
    });
    syncBulkState();
})();
</script>

<script>
(function () {
    document.querySelectorAll('[id^="community-report-process-modal-"]').forEach(function (modal) {
        var status = modal.querySelector('select[name="status"]');
        var targetAction = modal.querySelector('select[name="target_action"]');
        var reporterAction = modal.querySelector('select[name="reporter_action"]');
        if (!status || !targetAction || !reporterAction) {
            return;
        }
        var syncPolicyHelp = function () {
            modal.querySelectorAll('[data-community-report-status-help]').forEach(function (help) {
                help.hidden = help.getAttribute('data-community-report-status-help') !== status.value;
            });
            if (status.value !== 'resolved') {
                targetAction.value = 'none';
            }
            targetAction.disabled = status.value !== 'resolved';
            if (status.value !== 'dismissed') {
                reporterAction.value = 'none';
            }
            reporterAction.disabled = status.value !== 'dismissed';
        };
        status.addEventListener('change', syncPolicyHelp);
        syncPolicyHelp();
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
