<?php

$adminPageTitle = sr_t('community::ui.community.451bb85e');
$adminPageSubtitle = sr_t('community::ui.status.search.9842179b');
$adminContainerClass = 'admin-page-community-report-list admin-ui-scope';
$reportListFilters = isset($reportListFilters) && is_array($reportListFilters) ? $reportListFilters : ['status' => '', 'target_type' => '', 'reason_key' => '', 'field' => 'all', 'q' => ''];
$reportStatusCounts = isset($reportStatusCounts) && is_array($reportStatusCounts) ? $reportStatusCounts : [];
$allowedStatuses = isset($allowedStatuses) && is_array($allowedStatuses) ? $allowedStatuses : [];
$allowedReasonKeys = isset($allowedReasonKeys) && is_array($allowedReasonKeys) ? $allowedReasonKeys : [];
$allowedTargetTypes = isset($allowedTargetTypes) && is_array($allowedTargetTypes) ? $allowedTargetTypes : ['post', 'comment', 'message'];
$reportTargetLabels = [
    'post' => sr_t('community::ui.text.0b138cfe'),
    'comment' => sr_t('community::ui.text.c9fff683'),
    'message' => sr_t('community::ui.text.919bd592'),
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
        <a href="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.all.e078b14a')); ?></a>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.5648e366')); ?> <strong><?php echo sr_e((string) $totalReports); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></strong></span>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=open')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.d995d6ab')); ?> <?php echo sr_e((string) ($reportStatusCounts['open'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=reviewing')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.7e0e2126')); ?> <?php echo sr_e((string) ($reportStatusCounts['reviewing'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=resolved')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?> <?php echo sr_e((string) ($reportStatusCounts['resolved'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
        <a href="<?php echo sr_e(sr_url('/admin/community/reports?status=dismissed')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('community::ui.text.0d655420')); ?> <?php echo sr_e((string) ($reportStatusCounts['dismissed'] ?? 0)); ?><?php echo sr_e(sr_t('community::ui.text.a57ab057')); ?></a>
    </div>
</div>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>" class="admin-filter admin-community-report-filter ui-form-theme">
    <div class="admin-filter-grid admin-community-report-search-grid">
        <div class="admin-filter-field admin-community-report-filter-status">
            <label for="community_admin_reports_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></label>
            <select id="community_admin_reports_status_filter" name="status" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedStatuses as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($reportListFilters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-target">
            <label for="community_admin_reports_target_type_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></label>
            <select id="community_admin_reports_target_type_filter" name="target_type" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['target_type'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedTargetTypes as $targetType) { ?>
                    <option value="<?php echo sr_e($targetType); ?>"<?php echo (string) ($reportListFilters['target_type'] ?? '') === $targetType ? ' selected' : ''; ?>>
                        <?php echo sr_e((string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'))); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-reason">
            <label for="community_admin_reports_reason_filter" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></label>
            <select id="community_admin_reports_reason_filter" name="reason_key" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($reportListFilters['reason_key'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.all.a4b69faf')); ?></option>
                <?php foreach ($allowedReasonKeys as $reasonKey) { ?>
                    <option value="<?php echo sr_e($reasonKey); ?>"<?php echo (string) ($reportListFilters['reason_key'] ?? '') === $reasonKey ? ' selected' : ''; ?>>
                        <?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-field">
            <label for="community_admin_reports_field" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.b79bc9c8')); ?></label>
            <select id="community_admin_reports_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => sr_t('community::ui.all.a4b69faf'), 'target' => sr_t('community::ui.text.8c609deb'), 'reporter' => sr_t('community::ui.text.84780e6f'), 'reported' => sr_t('community::ui.member.7a284377'), 'reviewer' => sr_t('community::ui.text.750086e9'), 'memo' => sr_t('community::ui.text.c8a14bcd')] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($reportListFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                        <?php echo sr_e($fieldLabel); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-community-report-filter-keyword">
            <label for="community_admin_reports_q" class="admin-filter-label"><?php echo sr_e(sr_t('community::ui.search.bda397fc')); ?></label>
            <input id="community_admin_reports_q" type="search" name="q" value="<?php echo sr_e((string) ($reportListFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('community::ui.member.fbbe7c33')); ?>">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title"><?php echo sr_e(sr_t('community::ui.list.27da9d14')); ?></h2></div>
    <?php echo sr_admin_pagination_summary_html($reportPagination); ?>
    <div class="table-wrapper">
    <table class="table admin-community-report-table">
        <caption class="sr-only"><?php echo sr_e(sr_t('community::ui.community.list.b4e41b31')); ?></caption>
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('community::ui.text.8c609deb')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ab9442a2')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.84780e6f')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.member.7a284377')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.c8a14bcd')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.ebc9b96e')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.750086e9')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.73bb6cce')); ?></th>
                <th class="text-end"><?php echo sr_e(sr_t('community::ui.text.460f7d7a')); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ($reports === []) { ?>
                <tr>
                    <td colspan="10" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.7efff05f')); ?></td>
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
                        <td class="admin-table-nowrap admin-community-report-target-cell"><?php echo sr_e((string) ($reportTargetLabels[$targetType] ?? sr_admin_code_label($targetType, 'target_type'))); ?></td>
                        <td class="admin-table-nowrap admin-community-report-reason-cell"><?php echo sr_e(sr_community_report_reason_label((string) $report['reason_key'])); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($reportStatus, 'report_status')); ?></span></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reporter_display_name'] ?? null) ? $report['reporter_display_name'] : null,
                            (int) $report['reporter_account_id'],
                            is_string($report['reporter_account_status'] ?? null) ? $report['reporter_account_status'] : null,
                            is_string($report['reporter_nickname'] ?? null) ? $report['reporter_nickname'] : null,
                            isset($settings) && is_array($settings) ? $settings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell"><?php echo sr_e(sr_community_report_account_label(
                            is_string($report['reported_display_name'] ?? null) ? $report['reported_display_name'] : null,
                            (int) $report['reported_account_id'],
                            is_string($report['reported_account_status'] ?? null) ? $report['reported_account_status'] : null,
                            is_string($report['reported_nickname'] ?? null) ? $report['reported_nickname'] : null,
                            isset($settings) && is_array($settings) ? $settings : null
                        )); ?></td>
                        <td class="admin-table-break admin-community-report-memo-cell"><?php echo sr_e((string) ($report['memo_text'] ?? '')); ?></td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_e((string) $report['created_at']); ?></td>
                        <td class="admin-table-break admin-community-report-account-cell">
                            <?php if ((int) ($report['reviewer_account_id'] ?? 0) > 0) { ?>
                                <?php echo sr_e(sr_community_report_account_label(
                                    is_string($report['reviewer_display_name'] ?? null) ? $report['reviewer_display_name'] : null,
                                    (int) $report['reviewer_account_id'],
                                    is_string($report['reviewer_account_status'] ?? null) ? $report['reviewer_account_status'] : null,
                                    is_string($report['reviewer_nickname'] ?? null) ? $report['reviewer_nickname'] : null,
                                    isset($settings) && is_array($settings) ? $settings : null
                                )); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-report-date-cell"><?php echo sr_e((string) ($report['reviewed_at'] ?? '')); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/community/reports')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="report_id" value="<?php echo sr_e((string) $report['id']); ?>">
                                    <label for="<?php echo sr_e($reportStatusSelectId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
                                    <select id="<?php echo sr_e($reportStatusSelectId); ?>" name="status" class="form-select">
                                            <?php foreach ($allowedStatuses as $status) { ?>
                                                <option value="<?php echo sr_e($status); ?>"<?php echo $status === (string) $report['status'] ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'report_status')); ?></option>
                                            <?php } ?>
                                        </select>
                                    <label for="<?php echo sr_e($reportReviewNoteId); ?>" class="sr-only"><?php echo sr_e(sr_t('community::ui.text.514556d0')); ?></label>
                                    <textarea id="<?php echo sr_e($reportReviewNoteId); ?>" name="review_note" rows="2" cols="24" class="form-textarea" placeholder="<?php echo sr_e(sr_t('community::ui.text.514556d0')); ?>"><?php echo sr_e((string) ($report['review_note'] ?? '')); ?></textarea>
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('community::ui.text.16f64fe4')); ?></button>
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
<?php echo sr_admin_pagination_html($reportPagination, '신고 목록 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
