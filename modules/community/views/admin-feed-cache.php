<?php

declare(strict_types=1);

$adminPageTitle = '피드 캐시';
$adminPageSubtitle = [
    '커뮤니티 요약 피드와 목록 밖 피드 출력을 빠르게 보여주기 위한 저장값입니다.',
    '표에는 현재 재사용 가능한 캐시만 표시합니다. 갱신 정책은 피드 성격에 맞춰 코드에서 고정합니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/feed-cache');
$feedCacheStoreStatus = isset($feedCacheStoreStatus) && is_array($feedCacheStoreStatus) ? $feedCacheStoreStatus : [];
$feedCacheContextRows = isset($feedCacheContextRows) && is_array($feedCacheContextRows) ? $feedCacheContextRows : [];
$persistentMode = (string) ($feedCacheStoreStatus['mode'] ?? 'contract_only');
$persistentModeLabel = match ($persistentMode) {
    'db_persistent' => 'DB 영속 캐시 사용',
    'file_persistent_detected' => '파일 영속 캐시 감지',
    default => '영속 캐시 미설치',
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-page admin-page-community-feed-cache admin-ui-scope">
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">현재 유효한 컨텍스트</h2>
        </div>
        <div class="admin-list-summary-row admin-community-feed-cache-summary-row">
            <div class="badge-list">
                <span class="badge badge-soft-secondary">방식 <?php echo sr_e($persistentModeLabel); ?></span>
                <span class="badge badge-soft-secondary">DB 테이블 <?php echo !empty($feedCacheStoreStatus['table_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">파일 캐시 <?php echo !empty($feedCacheStoreStatus['file_cache_exists']) ? '있음' : '없음'; ?></span>
                <span class="badge badge-soft-secondary">저장 row <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['row_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">현재 유효 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['active_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">갱신 대기 <?php echo sr_e(number_format((int) ($feedCacheStoreStatus['stale_count'] ?? 0))); ?></span>
                <span class="badge badge-soft-secondary">마지막 생성 <?php echo sr_admin_time_html((string) ($feedCacheStoreStatus['latest_generated_at'] ?? ''), '-'); ?></span>
                <span class="badge badge-soft-secondary">마지막 변경 <?php echo sr_admin_time_html((string) ($feedCacheStoreStatus['latest_updated_at'] ?? ''), '-'); ?></span>
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
                        <th>캐시 키</th>
                        <th>정렬</th>
                        <th>저장 항목</th>
                        <th>표시/후보</th>
                        <th>게시판 수</th>
                        <th>Locale</th>
                        <th>정책 버전</th>
                        <th>갱신 정책</th>
                        <th>컨텍스트 해시</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedCacheContextRows as $contextRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) ($contextRow['feed_key'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['sort'] ?? '')); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['snapshot_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['display_count'] ?? 0)) . ' / ' . number_format((int) ($contextRow['fetch_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(number_format((int) ($contextRow['board_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['locale'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['policy_version'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($contextRow['refresh_policy'] ?? '변경 시 갱신')); ?></td>
                            <td><code><?php echo sr_e((string) ($contextRow['context_hash'] ?? '')); ?></code></td>
                        </tr>
                    <?php } ?>
                    <?php if ($feedCacheContextRows === []) { ?>
                        <tr><td colspan="9" class="admin-empty-state">현재 유효한 피드 캐시 row가 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
