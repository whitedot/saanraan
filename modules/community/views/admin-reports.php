<?php

$adminPageTitle = '커뮤니티 신고';
$adminPageSubtitle = '신고 상태와 대상을 확인하고 조건 검색과 처리 작업을 이어가세요.';
$adminContainerClass = 'admin-page-community-report-list admin-ui-scope';
$reportListFilters = isset($reportListFilters) && is_array($reportListFilters) ? $reportListFilters : ['status' => '', 'target_type' => '', 'reason_key' => '', 'field' => 'all', 'q' => ''];
$reportStatusCounts = isset($reportStatusCounts) && is_array($reportStatusCounts) ? $reportStatusCounts : [];
$allowedStatuses = isset($allowedStatuses) && is_array($allowedStatuses) ? $allowedStatuses : [];
$allowedReasonKeys = isset($allowedReasonKeys) && is_array($allowedReasonKeys) ? $allowedReasonKeys : [];
$allowedTargetTypes = isset($allowedTargetTypes) && is_array($allowedTargetTypes) ? $allowedTargetTypes : ['post', 'comment', 'message'];
$reportTargetLabels = [
    'post' => '게시글',
    'comment' => '댓글',
    'message' => '쪽지',
];
$totalReports = (int) ($reportStatusCounts['total'] ?? count($reports ?? []));
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

<div class="admin-local-nav-wrap">
    <div class="admin-local-nav">
        <a href="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="btn btn-solid-light">전체 보기</a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">총신고 <strong><?php echo sr_e((string) $totalReports); ?>개</strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=open')); ?>" class="admin-summary-meta">접수 <?php echo sr_e((string) ($reportStatusCounts['open'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=reviewing')); ?>" class="admin-summary-meta">검토 <?php echo sr_e((string) ($reportStatusCounts['reviewing'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=resolved')); ?>" class="admin-summary-meta">처리 <?php echo sr_e((string) ($reportStatusCounts['resolved'] ?? 0)); ?>개</a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=dismissed')); ?>" class="admin-summary-meta">기각 <?php echo sr_e((string) ($reportStatusCounts['dismissed'] ?? 0)); ?>개</a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="admin-filter admin-community-report-filter ui-form-theme">
    <div class="admin-filter-grid admin-community-report-search-grid">
        <div class="admin-filter-field admin-community-report-filter-status">
            <label for="community_admin_reports_status_filter" class="admin-filter-label">상태</label>
            <select id="community_admin_reports_status_filter" name="status" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($reportListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-target">
            <label for="community_admin_reports_target_type_filter" class="admin-filter-label">대상</label>
            <select id="community_admin_reports_target_type_filter" name="target_type" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['target_type'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($allowedTargetTypes as $targetType) { ?>
                    <option value="<?php echo sr_e($targetType); ?>"<?php echo (string) ($reportListFilters['target_type'] ?? '') === $targetType ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'))); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-reason">
            <label for="community_admin_reports_reason_filter" class="admin-filter-label">사유</label>
            <select id="community_admin_reports_reason_filter" name="reason_key" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['reason_key'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <?php foreach ($allowedReasonKeys as $reasonKey) { ?>
                    <option value="<?php echo sr_e($reasonKey); ?>"<?php echo (string) ($reportListFilters['reason_key'] ?? '') === $reasonKey ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-field">
            <label for="community_admin_reports_field" class="admin-filter-label">검색 조건</label>
            <select id="community_admin_reports_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'target' => '대상', 'reporter' => '신고자', 'reported' => '대상 회원', 'reviewer' => '처리자', 'memo' => '메모'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($reportListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-keyword">
            <label for="community_admin_reports_q" class="admin-filter-label">검색어</label>
            <input id="community_admin_reports_q" type="search" name="q" value="<?php echo sr_e((string) ($reportListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="대상, 회원, 메모">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">신고 목록</h2></div>
    <div class="table-wrapper">
    <table class="table admin-community-report-table">
        <caption class="sr-only">커뮤니티 신고 목록</caption>
        <thead class="ui-table-head">
            <tr>
                <th>ID</th>
                <th>대상</th>
                <th>사유</th>
                <th>상태</th>
                <th>신고자</th>
                <th>대상 회원</th>
                <th>메모</th>
                <th>접수일</th>
                <th>처리자</th>
                <th>처리일</th>
                <th class="text-end">처리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports === []) { ?>
                <tr>
                    <td colspan="11" class="admin-empty-state">접수된 신고가 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($reports as $report) { ?>
                    <?php
                    $reportStatus = (string) $report['status'];
                    $statusClass = match ($reportStatus) {
                        'resolved' => 'is-normal',
                        'open', 'reviewing' => 'is-blocked',
                        default => 'is-left',
                    };
                    $targetType = (string) $report['target_type'];
                    $reportStatusSelectId = 'community_admin_report_status_' . (string) $report['id'];
                    $reportReviewNoteId = 'community_admin_report_review_note_' . (string) $report['id'];
                    ?>
                    <tr>
                        <td class="admin-table-nowrap community-id"><?php echo sr_e((string) $report['id']); ?></td>
                        <td class="admin-table-nowrap admin-community-report-target-cell"><?php echo sr_e((string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type')) . ' #' . (string) $report['target_id']); ?></td>
                        <td class="admin-table-nowrap admin-community-report-reason-cell"><?php echo sr_e(sr_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($reportStatus, 'report_status')); ?></span></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id']
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id']
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-memo-cell"><?php echo sr_e((string) ($report['memo_text'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_e((string) $report['created_at']); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell">
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo sr_e(sr_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id']
                                )); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_e((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="report_id" value="<?php echo sr_e((string) $report['id']); ?>">
                                    <label for="<?php echo sr_e($reportStatusSelectId); ?>" class="sr-only">상태</label>
                                    <select id="<?php echo sr_e($reportStatusSelectId); ?>" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $report['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                                            <?php } ?>
                                        </select>
                                    <label for="<?php echo sr_e($reportReviewNoteId); ?>" class="sr-only">처리 메모</label>
                                    <textarea id="<?php echo sr_e($reportReviewNoteId); ?>" name="review_note" rows="2" cols="24" class="form-textarea" placeholder="처리 메모"><?php echo sr_e((string) ($report['review_note'] ?? '')); ?></textarea>
                                    <button type="submit" class="btn btn-sm btn-solid-light">변경</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
