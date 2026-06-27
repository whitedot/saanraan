<?php

declare(strict_types=1);

$adminPageTitle = '최신글 캐시 관리';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/feed-cache');
$feedCacheStoreStatus = isset($feedCacheStoreStatus) && is_array($feedCacheStoreStatus) ? $feedCacheStoreStatus : [];
$feedCacheBoardRows = isset($feedCacheBoardRows) && is_array($feedCacheBoardRows) ? $feedCacheBoardRows : [];
$feedCacheContextRows = isset($feedCacheContextRows) && is_array($feedCacheContextRows) ? $feedCacheContextRows : [];
$feedCacheLatestPreview = isset($feedCacheLatestPreview) && is_array($feedCacheLatestPreview) ? $feedCacheLatestPreview : [];
$feedCachePopularPreview = isset($feedCachePopularPreview) && is_array($feedCachePopularPreview) ? $feedCachePopularPreview : [];
$persistentMode = (string) ($feedCacheStoreStatus['mode'] ?? 'contract_only');
$baselineBoardCount = count(array_filter($feedCacheBoardRows, static fn (array $row): bool => !empty($row['public_baseline'])));
$paidReadLimitedCount = count(array_filter($feedCacheBoardRows, static fn (array $row): bool => !empty($row['paid_read_required'])));

$statusLabel = static function (bool $enabled, string $onLabel, string $offLabel): string {
    return '<span class="admin-status ' . ($enabled ? 'is-normal' : 'is-muted') . '">' . sr_e($enabled ? $onLabel : $offLabel) . '</span>';
};
$postTitle = static function (array $post): string {
    $title = trim((string) ($post['title'] ?? ''));
    return $title !== '' ? $title : '게시글 #' . (string) (int) ($post['id'] ?? 0);
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<div class="admin-page admin-page-community-feed-cache admin-ui-scope">
    <section class="admin-section">
        <div class="admin-dashboard-grid">
            <article class="admin-card">
                <h2>캐시 저장소</h2>
                <dl class="admin-definition-list">
                    <div>
                        <dt>현재 방식</dt>
                        <dd><?php echo $persistentMode === 'contract_only' ? '영속 캐시 없음' : '영속 캐시 후보 감지'; ?></dd>
                    </div>
                    <div>
                        <dt>DB 테이블</dt>
                        <dd><?php echo $statusLabel(!empty($feedCacheStoreStatus['table_exists']), '있음', '없음'); ?></dd>
                    </div>
                    <div>
                        <dt>파일 캐시</dt>
                        <dd><?php echo $statusLabel(!empty($feedCacheStoreStatus['file_cache_exists']), '있음', '없음'); ?></dd>
                    </div>
                    <div>
                        <dt>저장 row</dt>
                        <dd><?php echo sr_e(number_format((int) ($feedCacheStoreStatus['row_count'] ?? 0))); ?></dd>
                    </div>
                </dl>
            </article>
            <article class="admin-card">
                <h2>공개 baseline</h2>
                <dl class="admin-definition-list">
                    <div>
                        <dt>대상 게시판</dt>
                        <dd><?php echo sr_e(number_format($baselineBoardCount)); ?></dd>
                    </div>
                    <div>
                        <dt>유료 열람 제한</dt>
                        <dd><?php echo sr_e(number_format($paidReadLimitedCount)); ?></dd>
                    </div>
                    <div>
                        <dt>최신글 미리보기</dt>
                        <dd><?php echo sr_e(number_format(count($feedCacheLatestPreview))); ?></dd>
                    </div>
                    <div>
                        <dt>인기글 미리보기</dt>
                        <dd><?php echo sr_e(number_format(count($feedCachePopularPreview))); ?></dd>
                    </div>
                </dl>
            </article>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-header">
            <h2>컨텍스트 해시</h2>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
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
                        <tr><td colspan="7">공개 baseline 게시판이 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-header">
            <h2>게시판 기준</h2>
        </div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>게시판</th>
                        <th>상태</th>
                        <th>읽기 정책</th>
                        <th>Baseline</th>
                        <th>요약/이미지</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedCacheBoardRows as $boardRow) { ?>
                        <tr>
                            <td><?php echo sr_e((string) (int) ($boardRow['id'] ?? 0)); ?></td>
                            <td>
                                <strong><?php echo sr_e((string) ($boardRow['title'] ?? '')); ?></strong>
                                <br><small><?php echo sr_e((string) ($boardRow['board_key'] ?? '')); ?></small>
                            </td>
                            <td><?php echo sr_e((string) ($boardRow['status'] ?? '')); ?></td>
                            <td><?php echo sr_e((string) ($boardRow['read_policy'] ?? '')); ?></td>
                            <td><?php echo $statusLabel(!empty($boardRow['public_baseline']), '포함', '제외'); ?></td>
                            <td><?php echo $statusLabel(!empty($boardRow['home_excerpt_allowed']), '허용', '유료 열람 제한'); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ($feedCacheBoardRows === []) { ?>
                        <tr><td colspan="6">게시판이 없습니다.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="admin-section">
        <div class="admin-section-header">
            <h2>조회 미리보기</h2>
        </div>
        <div class="admin-dashboard-grid">
            <article class="admin-card">
                <h3>최신글</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>제목</th>
                                <th>조회수</th>
                                <th>수정일</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedCacheLatestPreview as $post) { ?>
                                <tr>
                                    <td><?php echo sr_e((string) (int) ($post['id'] ?? 0)); ?></td>
                                    <td><?php echo sr_e($postTitle($post)); ?></td>
                                    <td><?php echo sr_e(number_format((int) ($post['view_count'] ?? 0))); ?></td>
                                    <td><?php echo sr_e((string) ($post['updated_at'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($feedCacheLatestPreview === []) { ?>
                                <tr><td colspan="4">조회 결과가 없습니다.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="admin-card">
                <h3>인기글</h3>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>제목</th>
                                <th>조회수</th>
                                <th>수정일</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feedCachePopularPreview as $post) { ?>
                                <tr>
                                    <td><?php echo sr_e((string) (int) ($post['id'] ?? 0)); ?></td>
                                    <td><?php echo sr_e($postTitle($post)); ?></td>
                                    <td><?php echo sr_e(number_format((int) ($post['view_count'] ?? 0))); ?></td>
                                    <td><?php echo sr_e((string) ($post['updated_at'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if ($feedCachePopularPreview === []) { ?>
                                <tr><td colspan="4">조회 결과가 없습니다.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
    </section>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
