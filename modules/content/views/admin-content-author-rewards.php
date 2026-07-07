<?php

$adminPageTitle = '작성자 보상 로그';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-content-author-rewards admin-ui-scope';
$authorRewardFilters = isset($authorRewardFilters) && is_array($authorRewardFilters) ? $authorRewardFilters : ['status' => '', 'q' => ''];
$authorRewardLogs = isset($authorRewardLogs) && is_array($authorRewardLogs) ? $authorRewardLogs : [];
$authorRewardStatusClass = static function (string $status): string {
    return match ($status) {
        'granted' => 'is-normal',
        'pending' => 'is-warning',
        'failed' => 'is-danger',
        default => 'is-blocked',
    };
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/author-rewards');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/content/author-rewards')); ?>" class="filtering-form filtering filtering-plain admin-content-author-reward-filter ui-form-theme">
    <div class="filtering-fields admin-content-author-reward-search-grid">
        <label class="filtering-field" for="content_author_reward_status">
            <span class="filtering-label">상태</span>
            <select id="content_author_reward_status" name="status" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (sr_content_author_reward_statuses() as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($authorRewardFilters['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_content_author_reward_status_label($status)); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field admin-content-author-reward-filter-keyword" for="content_author_reward_q">
            <span class="filtering-label">검색</span>
            <input id="content_author_reward_q" type="search" name="q" value="<?php echo sr_e((string) ($authorRewardFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="콘텐츠, 제출본 ID, 작성자, 공개 해시, 거래 ID">
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">보상 로그</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($authorRewardPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-content-author-reward-table">
            <caption class="sr-only">회원 제출 콘텐츠 작성자 보상 로그</caption>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>상태</th>
                    <th>콘텐츠/제출본</th>
                    <th>작성자</th>
                    <th>지급</th>
                    <th>거래</th>
                    <th>검수자</th>
                    <th>생성</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($authorRewardLogs === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">보상 로그가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($authorRewardLogs as $rewardLog) { ?>
                    <?php
                    $rewardStatus = (string) ($rewardLog['status'] ?? '');
                    $unitLabel = sr_content_asset_module_unit_label((string) ($rewardLog['asset_module'] ?? ''), $pdo);
                    $contentTitle = trim((string) ($rewardLog['content_title'] ?? ''));
                    $submissionTitle = trim((string) ($rewardLog['submission_title'] ?? ''));
                    $authorLabel = trim((string) (($rewardLog['author_display_name'] ?? '') ?: ($rewardLog['author_email'] ?? '')));
                    $reviewerLabel = trim((string) (($rewardLog['reviewer_display_name'] ?? '') ?: ($rewardLog['reviewer_email'] ?? '')));
                    $authorAccountId = (int) ($rewardLog['author_account_id'] ?? 0);
                    $reviewerAccountId = (int) ($rewardLog['created_by_account_id'] ?? 0);
                    $authorHash = $authorAccountId > 0 && function_exists('sr_admin_member_public_hash')
                        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $authorAccountId)
                        : '';
                    $reviewerHash = $reviewerAccountId > 0 && function_exists('sr_admin_member_public_hash')
                        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $reviewerAccountId)
                        : '';
                    $failureReason = trim((string) ($rewardLog['failure_reason'] ?? ''));
                    $contentSlug = trim((string) ($rewardLog['content_slug'] ?? ''));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($rewardLog['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($authorRewardStatusClass($rewardStatus)); ?>"><?php echo sr_e(sr_content_author_reward_status_label($rewardStatus)); ?></span>
                        </td>
                        <td class="admin-table-break admin-content-author-reward-subject-cell">
                            <?php if ($contentSlug !== '') { ?>
                                <a href="<?php echo sr_e(sr_url('/content/' . rawurlencode($contentSlug))); ?>" class="btn btn-sm btn-solid-light" target="_blank" rel="noopener noreferrer">콘텐츠</a>
                            <?php } ?>
                            <strong><?php echo sr_e($contentTitle !== '' ? $contentTitle : ($submissionTitle !== '' ? $submissionTitle : '삭제된 콘텐츠')); ?></strong>
                            <small class="admin-summary-meta">
                                콘텐츠 #<?php echo sr_e((string) (int) ($rewardLog['content_id'] ?? 0)); ?>
                                · 제출본 #<?php echo sr_e((string) (int) ($rewardLog['submission_id'] ?? 0)); ?>
                            </small>
                        </td>
                        <td class="admin-table-break admin-content-author-reward-account-cell">
                            <strong><?php echo sr_e($authorHash !== '' ? $authorHash : '회원 정보 없음'); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e($authorLabel !== '' ? $authorLabel : '회원 정보 없음'); ?></small>
                        </td>
                        <td class="admin-table-nowrap text-end">
                            <strong><?php echo sr_e(number_format((int) ($rewardLog['amount'] ?? 0))); ?> <?php echo sr_e($unitLabel); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e(sr_content_asset_module_label((string) ($rewardLog['asset_module'] ?? ''), $pdo)); ?></small>
                        </td>
                        <td class="admin-table-break admin-content-author-reward-transaction-cell">
                            <span>지급 #<?php echo sr_e((string) (int) ($rewardLog['transaction_id'] ?? 0)); ?></span>
                            <?php if ($failureReason !== '') { ?>
                                <small class="text-danger"><?php echo sr_e($failureReason); ?></small>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break admin-content-author-reward-account-cell">
                            <?php if ($reviewerAccountId > 0) { ?>
                                <strong><?php echo sr_e($reviewerHash !== '' ? $reviewerHash : '회원 정보 없음'); ?></strong>
                                <small class="admin-summary-meta"><?php echo sr_e($reviewerLabel !== '' ? $reviewerLabel : '회원 정보 없음'); ?></small>
                            <?php } else { ?>
                                <span class="admin-summary-meta">없음</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-content-author-reward-date-cell"><?php echo sr_content_time_html((string) ($rewardLog['created_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('content_author_reward_status', array_combine(sr_content_author_reward_statuses(), array_map('sr_content_author_reward_status_label', sr_content_author_reward_statuses())) ?: []); ?>
</section>

<?php echo sr_admin_pagination_html($authorRewardPagination, '작성자 보상 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
