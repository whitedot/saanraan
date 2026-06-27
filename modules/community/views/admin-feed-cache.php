<?php

declare(strict_types=1);

$adminPageTitle = '홈 피드 캐시';
$adminPageSubtitle = [
    '커뮤니티 홈의 공개 게시판 최신글/인기글 목록을 빠르게 보여주기 위한 저장값입니다.',
    '게시글, 댓글, 게시판 공개 조건이 바뀌면 기존 저장값은 자동으로 갱신 대상으로 표시되고 다음 홈 조회 때 현재 조건으로 다시 계산됩니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/feed-cache');
$feedCacheStoreStatus = isset($feedCacheStoreStatus) && is_array($feedCacheStoreStatus) ? $feedCacheStoreStatus : [];
$feedCacheBoardRows = isset($feedCacheBoardRows) && is_array($feedCacheBoardRows) ? $feedCacheBoardRows : [];
$feedCacheContextRows = isset($feedCacheContextRows) && is_array($feedCacheContextRows) ? $feedCacheContextRows : [];
$canViewCommunityThumbnailFileCache = !empty($canViewCommunityThumbnailFileCache);
$canViewCommunityEmbedManager = !empty($canViewCommunityEmbedManager);
$persistentMode = (string) ($feedCacheStoreStatus['mode'] ?? 'contract_only');
$persistentModeLabel = match ($persistentMode) {
    'db_persistent' => 'DB 영속 캐시 사용',
    'file_persistent_detected' => '파일 영속 캐시 감지',
    default => '영속 캐시 미설치',
};
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
                <span class="badge badge-soft-secondary">방식 <?php echo sr_e($persistentModeLabel); ?></span>
                <span class="badge badge-soft-secondary">DB 테이블 <?php echo !empty($feedCacheStoreStatus['table_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">파일 캐시 <?php echo !empty($feedCacheStoreStatus['file_cache_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">저장 row <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['row_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">최신 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['fresh_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">갱신 필요 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['stale_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">공개 baseline <?php echo sr_e(number_format($baselineBoardCount)); ?>개</span>
                <span class="badge badge-soft-secondary">유료 열람 제한 <?php echo sr_e(number_format($paidReadLimitedCount)); ?>개</span>
            </div>
        </div>
        <div class="admin-list-summary-row admin-community-feed-cache-summary-row">
            <div class="badge-list">
                <span class="badge badge-soft-info">게시글 작성/수정/삭제/상태 변경 시 갱신 대상</span>
                <span class="badge badge-soft-info">댓글 작성 시 갱신 대상</span>
                <span class="badge badge-soft-info">게시판 공개/권한/유료 열람 조건 변경 시 갱신 대상</span>
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

    <?php if ($canViewCommunityThumbnailFileCache || $canViewCommunityEmbedManager) { ?>
        <section class="card admin-list-card admin-list-form">
            <div class="card-header">
                <h2 class="card-title">관련 운영 화면</h2>
            </div>
            <div class="admin-list-summary-row admin-community-feed-cache-summary-row">
                <div class="badge-list">
                    <?php if ($canViewCommunityThumbnailFileCache) { ?>
                        <a class="badge badge-soft-secondary" href="<?php echo sr_e(sr_url('/admin/storage-cache?module_key=community')); ?>">커뮤니티 썸네일 파일 캐시</a>
                    <?php } ?>
                    <?php if ($canViewCommunityEmbedManager) { ?>
                        <a class="badge badge-soft-secondary" href="<?php echo sr_e(sr_url('/admin/embed-manager')); ?>">본문 URL 임베드 저장값</a>
                    <?php } ?>
                </div>
            </div>
        </section>
    <?php } ?>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
