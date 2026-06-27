<?php

declare(strict_types=1);

$adminPageTitle = '피드 캐시';
$adminPageSubtitle = [
    '커뮤니티 요약 피드와 목록 밖 피드 출력을 빠르게 보여주기 위한 저장값입니다.',
    '게시글, 댓글, 게시판 공개 조건이 바뀌면 기존 저장값은 자동으로 갱신 대상으로 표시되고 다음 조회 때 현재 조건으로 다시 계산됩니다.',
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
            <h2 class="card-title">저장된 컨텍스트</h2>
        </div>
        <div class="admin-list-summary-row admin-community-feed-cache-summary-row">
            <div class="badge-list">
                <span class="badge badge-soft-secondary">방식 <?php echo sr_e($persistentModeLabel); ?></span>
                <span class="badge badge-soft-secondary">DB 테이블 <?php echo !empty($feedCacheStoreStatus['table_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">파일 캐시 <?php echo !empty($feedCacheStoreStatus['file_cache_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">저장 row <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['row_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">최신 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['fresh_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">갱신 필요 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['stale_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">만료됨 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['expired_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">마지막 생성 <?php echo sr_admin_time_html((string) ($feedCacheStoreStatus['latest_generated_at'] ?? ''), '-'); ?></span>
                <span class="badge badge-soft-secondary">마지막 변경 <?php echo sr_admin_time_html((string) ($feedCacheStoreStatus['latest_updated_at'] ?? ''), '-'); ?></span>
                <span class="badge badge-soft-secondary">다음 만료 <?php echo sr_admin_time_html((string) ($feedCacheStoreStatus['next_expires_at'] ?? ''), '-'); ?></span>
                <span class="badge badge-soft-secondary">공개 기준 <?php echo sr_e(number_format($baselineBoardCount)); ?>개</span>
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
                        <th>상태</th>
                        <th>스냅샷</th>
                        <th>표시/조회</th>
                        <th>게시판</th>
                        <th>Locale</th>
                        <th>정책</th>
                        <th>만료</th>
                        <th>Context hash</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedCacheContextRows as $contextRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($contextRow['feed_key'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['sort'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['cache_status'] ?? '')); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['snapshot_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['display_count'] ?? 0)) . ' / ' . number_format((int) ($contextRow['fetch_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['board_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['locale'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['policy_version'] ?? '')); ?></td>
                            <td><?php echo sr_admin_time_html((string) ($contextRow['expires_at'] ?? ''), '-'); ?></td>
                            <td><code><?php echo sr_e((string) ($contextRow['context_hash'] ?? '')); ?></code></td>
                        </tr>
                    <?php } ?>
                    <?php if ($feedCacheContextRows === []) { ?>
                        <tr><td colspan="10" class="admin-empty-state">저장된 피드 캐시 row가 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">게시판 기준</h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>게시판</th>
                        <th>상태</th>
                        <th>읽기</th>
                        <th>피드 후보</th>
                        <th>공개 기준</th>
                        <th>요약 본문</th>
                        <th>유료 열람 제한</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedCacheBoardRows as $boardRow) { ?>
                        <tr>
                            <td>
                                <strong><?php echo sr_e((string) ($boardRow['title'] ?? '')); ?></strong>
                                <br>
                                <code><?php echo sr_e((string) ($boardRow['board_key'] ?? '')); ?></code>
                            </td>
                            <td><?php echo sr_e((string) ($boardRow['status'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($boardRow['read_policy'] ?? '')); ?></td>
                            <td><?php echo !empty($boardRow['summary_feed_enabled']) ? '표시' : '제외'; ?></td>
                            <td><?php echo !empty($boardRow['public_baseline']) ? '예' : '아니오'; ?></td>
                            <td><?php echo !empty($boardRow['home_excerpt_allowed']) ? '허용' : '숨김'; ?></td>
                            <td><?php echo !empty($boardRow['paid_read_required']) ? '예' : '아니오'; ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ($feedCacheBoardRows === []) { ?>
                        <tr><td colspan="7" class="admin-empty-state">표시할 게시판 기준이 없습니다.</td></tr>
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
