<?php

$adminPageTitle = '스토리지 캐시';
$adminPageSubtitle = '공개 썸네일 캐시 조회와 기간별 정리';
include SR_ROOT . '/modules/admin/views/layout-header.php';

$dateCounts = isset($cacheSummary['date_counts']) && is_array($cacheSummary['date_counts']) ? $cacheSummary['date_counts'] : [];
$dateBytes = isset($cacheSummary['date_bytes']) && is_array($cacheSummary['date_bytes']) ? $cacheSummary['date_bytes'] : [];
$variantCounts = isset($cacheSummary['variant_counts']) && is_array($cacheSummary['variant_counts']) ? $cacheSummary['variant_counts'] : [];
$currentQuery = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== ''));
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/storage-cache')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields">
        <label class="filtering-field" for="admin_storage_cache_date_from">
            <span class="filtering-label">시작일</span>
            <input id="admin_storage_cache_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <label class="filtering-field" for="admin_storage_cache_date_to">
            <span class="filtering-label">종료일</span>
            <input id="admin_storage_cache_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <label class="filtering-field" for="admin_storage_cache_module_key">
            <span class="filtering-label">모듈</span>
            <input id="admin_storage_cache_module_key" type="text" name="module_key" value="<?php echo sr_e((string) ($filters['module_key'] ?? '')); ?>" class="form-input filtering-input" placeholder="community">
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">조회</button>
        <a class="btn btn-outline-light" href="<?php echo sr_e(sr_url('/admin/storage-cache')); ?>">
            <span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화
        </a>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">썸네일 캐시 요약</h2>
    </div>
    <div class="admin-list-summary-row">
        <div class="badge-list">
            <span class="badge-list-item">
                <span class="badge-list-label">파일 수</span>
                <span class="badge-list-summary"><?php echo sr_e(number_format((int) ($cacheSummary['total_count'] ?? 0))); ?>개</span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">총 용량</span>
                <span class="badge-list-summary"><?php echo sr_e(sr_format_bytes((int) ($cacheSummary['total_bytes'] ?? 0))); ?></span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">가장 오래된 파일</span>
                <span class="badge-list-summary"><?php echo (string) ($cacheSummary['oldest_at'] ?? '') !== '' ? sr_admin_time_html((string) $cacheSummary['oldest_at']) : '-'; ?></span>
            </span>
            <span class="badge-list-item">
                <span class="badge-list-label">가장 최근 파일</span>
                <span class="badge-list-summary"><?php echo (string) ($cacheSummary['newest_at'] ?? '') !== '' ? sr_admin_time_html((string) $cacheSummary['newest_at']) : '-'; ?></span>
            </span>
        </div>
    </div>
    <div class="alert alert-info">
        <strong>파일 스캔형 조회</strong>
        <p>이 화면은 `storage/cache/thumbnails` 아래에서 helper가 만든 파일명 패턴의 이미지 캐시만 조회합니다. 생성일은 파일 수정 시각 기준으로 표시합니다.</p>
    </div>
</section>

<?php if ($canDeleteStorageCache) { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">기간별 정리</h2>
        </div>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/storage-cache')); ?>" class="ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>">
            <input type="hidden" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>">
            <input type="hidden" name="module_key" value="<?php echo sr_e((string) ($filters['module_key'] ?? '')); ?>">
            <p class="admin-form-help">현재 조회 조건에 맞는 썸네일 캐시 파일만 삭제합니다. 원본 파일과 게시글 첨부는 삭제하지 않습니다.</p>
            <label class="form-field">
                <span class="form-label">확인 문구 <span class="sr-required-label">(필수)</span></span>
                <input type="text" name="confirm_text" value="" maxlength="40" class="form-input" autocomplete="off" required>
                <span class="admin-form-help">정리를 실행하려면 정리를 입력하세요.</span>
            </label>
            <div class="admin-form-actions">
                <button type="submit" class="btn btn-outline-danger">현재 조건 캐시 정리</button>
            </div>
        </form>
    </section>
<?php } ?>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">일자별 생성 내역</h2>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>일자</th>
                    <th>파일 수</th>
                    <th>용량</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($dateCounts === []) { ?>
                    <tr><td colspan="3" class="admin-empty-state">조회된 썸네일 캐시가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($dateCounts as $date => $count) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $date); ?></td>
                        <td><?php echo sr_e(number_format((int) $count)); ?>개</td>
                        <td><?php echo sr_e(sr_format_bytes((int) ($dateBytes[$date] ?? 0))); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">Variant 분포</h2>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>Variant key</th>
                    <th>파일 수</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($variantCounts === []) { ?>
                    <tr><td colspan="2" class="admin-empty-state">조회된 variant가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($variantCounts as $variantKey => $count) { ?>
                    <tr>
                        <td><code><?php echo sr_e((string) $variantKey); ?></code></td>
                        <td><?php echo sr_e(number_format((int) $count)); ?>개</td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">캐시 파일</h2>
        <?php if ((int) ($cacheRowTotal ?? 0) > count($cacheRows)) { ?>
            <span class="admin-summary-meta">최근 <?php echo sr_e(number_format(count($cacheRows))); ?>개 표시 / 전체 <?php echo sr_e(number_format((int) $cacheRowTotal)); ?>개</span>
        <?php } ?>
        <?php if ($currentQuery !== '') { ?>
            <a class="btn btn-sm btn-ghost-secondary" href="<?php echo sr_e(sr_url('/admin/storage-cache?' . $currentQuery)); ?>">현재 조건 링크</a>
        <?php } ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>수정 시각</th>
                    <th>모듈</th>
                    <th>경로</th>
                    <th>Variant</th>
                    <th>용량</th>
                    <th>확장자</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($cacheRows === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">조회된 썸네일 캐시 파일이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($cacheRows as $row) { ?>
                    <tr>
                        <td><?php echo sr_admin_time_html((string) ($row['modified_at'] ?? '')); ?></td>
                        <td><code><?php echo sr_e((string) ($row['module_key'] ?? '')); ?></code></td>
                        <td>
                            <?php if ((string) ($row['public_path'] ?? '') !== '') { ?>
                                <a href="<?php echo sr_e((string) $row['public_path']); ?>" target="_blank" rel="noopener">
                                    <code><?php echo sr_e((string) ($row['relative_path'] ?? '')); ?></code>
                                </a>
                            <?php } else { ?>
                                <code><?php echo sr_e((string) ($row['relative_path'] ?? '')); ?></code>
                            <?php } ?>
                        </td>
                        <td><code><?php echo sr_e((string) ($row['variant_key'] ?? '')); ?></code></td>
                        <td><?php echo sr_e(sr_format_bytes((int) ($row['size_bytes'] ?? 0))); ?></td>
                        <td><?php echo sr_e((string) ($row['extension'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
