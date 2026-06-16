<?php

$adminPageTitle = '회원 리워드 로그';
$adminPageSubtitle = '첨부 다운로드 수익과 게시자 지급 기록을 확인합니다.';
$adminContainerClass = 'admin-page-community-publisher-rewards admin-ui-scope';
$publisherRewardFilters = isset($publisherRewardFilters) && is_array($publisherRewardFilters) ? $publisherRewardFilters : ['status' => '', 'q' => ''];
$publisherRewardLogs = isset($publisherRewardLogs) && is_array($publisherRewardLogs) ? $publisherRewardLogs : [];
$publisherRewardStatusClass = static function (string $status): string {
    return match ($status) {
        'granted' => 'is-normal',
        'pending', 'held' => 'is-warning',
        'failed' => 'is-danger',
        'reversed', 'cancelled' => 'is-left',
        default => 'is-blocked',
    };
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/publisher-rewards');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/community/publisher-rewards')); ?>" class="filtering-form admin-community-publisher-reward-filter ui-form-theme">
    <div class="filtering filtering-card">
        <div class="filtering-fields admin-community-publisher-reward-search-grid">
            <label class="filtering-field" for="community_publisher_reward_status">
                <span class="filtering-label">상태</span>
                <select id="community_publisher_reward_status" name="status" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach (sr_community_publisher_reward_statuses() as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($publisherRewardFilters['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_community_publisher_reward_status_label($status)); ?></option>
                    <?php } ?>
                </select>
            </label>
            <label class="filtering-field admin-community-publisher-reward-filter-keyword" for="community_publisher_reward_q">
                <span class="filtering-label">검색</span>
                <input id="community_publisher_reward_q" type="search" name="q" value="<?php echo sr_e((string) ($publisherRewardFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="게시글, 첨부, ID">
            </label>
            <div class="filtering-actions admin-community-publisher-reward-filter-actions">
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">리워드 로그</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($publisherRewardPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table admin-community-publisher-reward-table">
            <caption class="sr-only">첨부 다운로드 게시자 리워드 로그</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>상태</th>
                    <th>게시글/첨부</th>
                    <th>다운로드 회원</th>
                    <th>게시자</th>
                    <th>차감</th>
                    <th>지급</th>
                    <th>거래</th>
                    <th>생성</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($publisherRewardLogs === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state">리워드 로그가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($publisherRewardLogs as $rewardLog) { ?>
                    <?php
                    $rewardStatus = (string) ($rewardLog['status'] ?? '');
                    $unitLabel = sr_community_asset_module_unit_label((string) ($rewardLog['asset_module'] ?? ''), $pdo);
                    $postId = (int) ($rewardLog['post_id'] ?? 0);
                    $postTitle = trim((string) ($rewardLog['post_title'] ?? ''));
                    $attachmentName = trim((string) ($rewardLog['attachment_name'] ?? ''));
                    $downloaderLabel = trim((string) (($rewardLog['downloader_display_name'] ?? '') ?: ($rewardLog['downloader_email'] ?? '')));
                    $publisherLabel = trim((string) (($rewardLog['publisher_display_name'] ?? '') ?: ($rewardLog['publisher_email'] ?? '')));
                    $failureMessage = trim((string) ($rewardLog['failure_message'] ?? ''));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($rewardLog['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($publisherRewardStatusClass($rewardStatus)); ?>"><?php echo sr_e(sr_community_publisher_reward_status_label($rewardStatus)); ?></span>
                        </td>
                        <td class="admin-table-break admin-community-publisher-reward-subject-cell">
                            <?php if ($postId > 0) { ?>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) $postId))); ?>" class="btn btn-sm btn-solid-light">게시글</a>
                            <?php } ?>
                            <strong><?php echo sr_e($postTitle !== '' ? $postTitle : '삭제된 게시글'); ?></strong>
                            <small class="admin-summary-meta">
                                #<?php echo sr_e((string) $postId); ?>
                                <?php if ($attachmentName !== '') { ?>
                                    · <?php echo sr_e($attachmentName); ?>
                                <?php } ?>
                            </small>
                        </td>
                        <td class="admin-table-break admin-community-publisher-reward-account-cell">
                            <strong>#<?php echo sr_e((string) (int) ($rewardLog['downloader_account_id'] ?? 0)); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e($downloaderLabel !== '' ? $downloaderLabel : '회원 정보 없음'); ?></small>
                        </td>
                        <td class="admin-table-break admin-community-publisher-reward-account-cell">
                            <strong>#<?php echo sr_e((string) (int) ($rewardLog['publisher_account_id'] ?? 0)); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e($publisherLabel !== '' ? $publisherLabel : '회원 정보 없음'); ?></small>
                        </td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($rewardLog['charge_amount'] ?? 0))); ?> <?php echo sr_e($unitLabel); ?></td>
                        <td class="admin-table-nowrap text-end">
                            <strong><?php echo sr_e(number_format((int) ($rewardLog['reward_amount'] ?? 0))); ?> <?php echo sr_e($unitLabel); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e((string) (int) ($rewardLog['reward_rate'] ?? 0)); ?>%</small>
                        </td>
                        <td class="admin-table-break admin-community-publisher-reward-transaction-cell">
                            <span>차감 #<?php echo sr_e((string) (int) ($rewardLog['charge_transaction_id'] ?? 0)); ?></span>
                            <span>지급 #<?php echo sr_e((string) (int) ($rewardLog['reward_transaction_id'] ?? 0)); ?></span>
                            <?php if ($failureMessage !== '') { ?>
                                <small class="text-danger"><?php echo sr_e($failureMessage); ?></small>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-community-publisher-reward-date-cell"><?php echo sr_community_time_html((string) ($rewardLog['created_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($publisherRewardPagination, '게시자 리워드 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
