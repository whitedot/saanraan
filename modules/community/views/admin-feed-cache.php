<?php

declare(strict_types=1);

$adminPageTitle = '최신글 캐시 관리';
$adminPageSubtitle = [
    '영속 캐시를 생성하거나 삭제하지 않고 현재 최신글 조회 기준만 확인합니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/feed-cache');
$feedCacheStoreStatus = isset($feedCacheStoreStatus) && is_array($feedCacheStoreStatus) ? $feedCacheStoreStatus : [];
$feedCacheBoardRows = isset($feedCacheBoardRows) && is_array($feedCacheBoardRows) ? $feedCacheBoardRows : [];
$feedCacheContextRows = isset($feedCacheContextRows) && is_array($feedCacheContextRows) ? $feedCacheContextRows : [];
$persistentMode = (string) ($feedCacheStoreStatus['mode'] ?? 'contract_only');
$baselineBoardCount = count(array_filter($feedCacheBoardRows, static fn (array $row): bool => !empty($row['public_baseline'])));
$paidReadLimitedCount = count(array_filter($feedCacheBoardRows, static fn (array $row): bool => !empty($row['paid_read_required'])));

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-page admin-page-community-feed-cache admin-ui-scope">
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">컨텍스트 해시</h2>
        </div>
        <div class="admin-list-summary-row admin-community-feed-cache-summary-row">
            <div class="badge-list">
                <span class="badge badge-soft-secondary">방식 <?php echo sr_e($persistentMode === 'contract_only' ? '영속 캐시 없음' : '영속 캐시 후보 감지'); ?></span>
                <span class="badge badge-soft-secondary">DB 테이블 <?php echo !empty($feedCacheStoreStatus['table_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">파일 캐시 <?php echo !empty($feedCacheStoreStatus['file_cache_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">저장 row <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['row_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">공개 baseline <?php echo sr_e(number_format($baselineBoardCount)); ?>개</span>
                <span class="badge badge-soft-secondary">유료 열람 제한 <?php echo sr_e(number_format($paidReadLimitedCount)); ?>개</span>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>Feed</th>
                        <th>정렬</th>
                        <th>표시</th>
                        <th>조회</th>
                        <th>Locale</th>
                        <th>정책</th>
                        <th>Context hash</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedCacheContextRows as $contextRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($contextRow['feed_key'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['sort'] ?? '')); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['display_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['fetch_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['locale'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['policy_version'] ?? '')); ?></td>
                            <td><code><?php echo sr_e((string) ($contextRow['context_hash'] ?? '')); ?></code></td>
                        </tr>
                    <?php } ?>
                    <?php if ($feedCacheContextRows === []) { ?>
                        <tr><td colspan="7" class="admin-empty-state">공개 baseline 게시판이 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
