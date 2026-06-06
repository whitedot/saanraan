<?php

$adminPageTitle = '첨부 다운로드 게시자 리워드';
$adminPageSubtitle = '첨부 다운로드 차감과 게시자 지급 로그를 확인합니다.';
$adminContainerClass = 'admin-page-community-publisher-rewards admin-ui-scope';
$publisherRewardFilters = isset($publisherRewardFilters) && is_array($publisherRewardFilters) ? $publisherRewardFilters : ['status' => '', 'q' => ''];
$publisherRewardLogs = isset($publisherRewardLogs) && is_array($publisherRewardLogs) ? $publisherRewardLogs : [];

?>

<section class="admin-card card">
    <form method="get" action="<?php echo sr_e(sr_url('/admin/community/publisher-rewards')); ?>" class="filtering-form filtering filtering-plain admin-asset-member-filter ui-form-theme">
        <div class="filter-row">
            <label class="sr-only" for="community_publisher_reward_status">상태</label>
            <select id="community_publisher_reward_status" name="status" class="form-select">
                <option value="">전체 상태</option>
                <?php foreach (sr_community_publisher_reward_statuses() as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($publisherRewardFilters['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e($status); ?></option>
                <?php } ?>
            </select>
            <label class="sr-only" for="community_publisher_reward_q">검색어</label>
            <input id="community_publisher_reward_q" type="search" name="q" value="<?php echo sr_e((string) ($publisherRewardFilters['q'] ?? '')); ?>" class="form-input" placeholder="게시글/첨부/ID 검색">
            <button type="submit" class="btn btn-solid-primary">검색</button>
            <a href="<?php echo sr_e(sr_url('/admin/community/publisher-rewards')); ?>" class="btn btn-solid-light">초기화</a>
        </div>
    </form>
</section>

<section class="admin-card card">
    <h2>
        <span>리워드 로그</span>
        <span class="admin-form-actions"><?php echo sr_admin_pagination_summary_html($publisherRewardPagination); ?></span>
    </h2>
    <table class="table">
        <thead>
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
                <tr><td colspan="9" class="text-muted">리워드 로그가 없습니다.</td></tr>
            <?php } ?>
            <?php foreach ($publisherRewardLogs as $rewardLog) { ?>
                <tr>
                    <td>#<?php echo sr_e((string) (int) ($rewardLog['id'] ?? 0)); ?></td>
                    <td><?php echo sr_e((string) ($rewardLog['status'] ?? '')); ?></td>
                    <td>
                        <a href="<?php echo sr_e(sr_url('/community/post?id=' . rawurlencode((string) (int) ($rewardLog['post_id'] ?? 0)))); ?>" class="btn btn-sm btn-solid-light">게시글</a>
                        <br><?php echo sr_e((string) ($rewardLog['post_title'] ?? '')); ?>
                        <br><span class="text-muted"><?php echo sr_e((string) ($rewardLog['attachment_name'] ?? '')); ?></span>
                    </td>
                    <td>#<?php echo sr_e((string) (int) ($rewardLog['downloader_account_id'] ?? 0)); ?><br><?php echo sr_e((string) (($rewardLog['downloader_display_name'] ?? '') ?: ($rewardLog['downloader_email'] ?? ''))); ?></td>
                    <td>#<?php echo sr_e((string) (int) ($rewardLog['publisher_account_id'] ?? 0)); ?><br><?php echo sr_e((string) (($rewardLog['publisher_display_name'] ?? '') ?: ($rewardLog['publisher_email'] ?? ''))); ?></td>
                    <td><?php echo sr_e(number_format((int) ($rewardLog['charge_amount'] ?? 0))); ?> <?php echo sr_e(sr_community_asset_module_unit_label((string) ($rewardLog['asset_module'] ?? ''), $pdo)); ?></td>
                    <td><?php echo sr_e((string) (int) ($rewardLog['reward_rate'] ?? 0)); ?>%<br><?php echo sr_e(number_format((int) ($rewardLog['reward_amount'] ?? 0))); ?> <?php echo sr_e(sr_community_asset_module_unit_label((string) ($rewardLog['asset_module'] ?? ''), $pdo)); ?></td>
                    <td>
                        차감 #<?php echo sr_e((string) (int) ($rewardLog['charge_transaction_id'] ?? 0)); ?>
                        <br>지급 #<?php echo sr_e((string) (int) ($rewardLog['reward_transaction_id'] ?? 0)); ?>
                        <?php if ((string) ($rewardLog['failure_message'] ?? '') !== '') { ?>
                            <br><span class="text-danger"><?php echo sr_e((string) $rewardLog['failure_message']); ?></span>
                        <?php } ?>
                    </td>
                    <td><?php echo sr_community_time_html((string) ($rewardLog['created_at'] ?? '')); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php echo sr_admin_pagination_html($publisherRewardPagination, '게시자 리워드 로그 페이지'); ?>
</section>
