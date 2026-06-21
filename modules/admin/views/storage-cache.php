<?php

$adminPageTitle = '썸네일 캐시';
$adminPageSubtitle = [
    '공개 이미지 썸네일 캐시를 조회하고 정리합니다.',
    '정리해도 원본 파일은 삭제되지 않습니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/storage-cache');
include SR_ROOT . '/modules/admin/views/layout-header.php';

$dateCounts = isset($cacheSummary['date_counts']) && is_array($cacheSummary['date_counts']) ? $cacheSummary['date_counts'] : [];
$dateBytes = isset($cacheSummary['date_bytes']) && is_array($cacheSummary['date_bytes']) ? $cacheSummary['date_bytes'] : [];
$variantCounts = isset($cacheSummary['variant_counts']) && is_array($cacheSummary['variant_counts']) ? $cacheSummary['variant_counts'] : [];
$cleanupTargetCount = (int) ($cacheSummary['total_count'] ?? 0);
$cleanupTargetBytes = (int) ($cacheSummary['total_bytes'] ?? 0);
$cleanupLimit = sr_admin_thumbnail_cache_cleanup_limit();
$currentQuery = http_build_query(array_filter($filters, static fn (string $value): bool => $value !== ''));
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/storage-cache')); ?>" class="filtering-form filtering filtering-plain admin-storage-cache-filter ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
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
            <select id="admin_storage_cache_module_key" name="module_key" class="form-select">
                <option value="">전체</option>
                <?php foreach ($moduleOptions as $moduleOptionKey => $moduleOptionLabel) { ?>
                    <option value="<?php echo sr_e((string) $moduleOptionKey); ?>"<?php echo (string) ($filters['module_key'] ?? '') === (string) $moduleOptionKey ? ' selected' : ''; ?>><?php echo sr_e((string) $moduleOptionLabel); ?></option>
                <?php } ?>
            </select>
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">조회</button>
        <?php if ($canDeleteStorageCache) { ?>
            <div class="filtering-actions">
                <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-storage-cache-cleanup-modal" data-overlay="#admin-storage-cache-cleanup-modal">정리</button>
            </div>
        <?php } ?>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">일자별 생성 내역</h2>
    </div>
    <div class="admin-list-summary-row admin-storage-cache-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-secondary">파일 수 <?php echo sr_e(number_format((int) ($cacheSummary['total_count'] ?? 0))); ?>개</span>
            <span class="badge badge-soft-secondary">총 용량 <?php echo sr_e(sr_format_bytes((int) ($cacheSummary['total_bytes'] ?? 0))); ?></span>
            <span class="badge badge-soft-secondary">가장 오래된 파일 <?php echo (string) ($cacheSummary['oldest_at'] ?? '') !== '' ? sr_admin_time_html((string) $cacheSummary['oldest_at']) : '-'; ?></span>
            <span class="badge badge-soft-secondary">가장 최근 파일 <?php echo (string) ($cacheSummary['newest_at'] ?? '') !== '' ? sr_admin_time_html((string) $cacheSummary['newest_at']) : '-'; ?></span>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
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

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">Variant 분포</h2>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
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

<section class="card admin-list-card admin-list-form">
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
        <table class="table table-list">
            <thead>
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

<?php if ($canDeleteStorageCache) { ?>
    <div id="admin-storage-cache-cleanup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-storage-cache-cleanup-modal-label" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/storage-cache')); ?>" class="modal-content admin-form ui-form-theme" data-admin-storage-cache-cleanup-form data-sr-validate-form>
                <div class="modal-header">
                    <h3 id="admin-storage-cache-cleanup-modal-label" class="modal-title">썸네일 캐시 정리</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#admin-storage-cache-cleanup-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>">
                    <input type="hidden" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>">
                    <input type="hidden" name="module_key" value="<?php echo sr_e((string) ($filters['module_key'] ?? '')); ?>">
                    <p class="form-help">현재 조회 조건에 맞는 썸네일 캐시 파일만 삭제합니다. 원본 파일과 게시글 첨부는 삭제하지 않습니다.</p>
                    <div class="admin-storage-cache-cleanup-summary">
                        <dl>
                            <div>
                                <dt>정리 대상</dt>
                                <dd><?php echo sr_e(number_format($cleanupTargetCount)); ?>개</dd>
                            </div>
                            <div>
                                <dt>예상 용량</dt>
                                <dd><?php echo sr_e(sr_format_bytes($cleanupTargetBytes)); ?></dd>
                            </div>
                        </dl>
                        <p>정리는 한 번에 최대 <?php echo sr_e(number_format($cleanupLimit)); ?>개씩 처리합니다. 실행 시점에 캐시 파일을 다시 확인하므로 실제 삭제 결과는 위 숫자와 다를 수 있습니다.</p>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="admin_storage_cache_cleanup_confirm_text">확인 문구 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <div class="validation-field">
                                <input id="admin_storage_cache_cleanup_confirm_text" type="text" name="confirm_text" value="" maxlength="40" class="form-input form-control-icon-end" autocomplete="off" required aria-describedby="admin_storage_cache_cleanup_confirm_text_error" data-admin-confirm-phrase="정리" data-admin-confirm-message="정리를 정확히 입력하세요.">
                                <div class="validation-static-icon" hidden data-admin-confirm-phrase-icon>
                                    <?php echo sr_material_icon_html('info', 'validation-error-icon', '정리를 정확히 입력하세요.'); ?>
                                </div>
                            </div>
                            <p class="form-help">정리를 실행하려면 <strong class="admin-storage-cache-confirm-phrase">정리</strong>를 입력하세요.</p>
                            <p id="admin_storage_cache_cleanup_confirm_text_error" class="validation-error-note" hidden data-admin-confirm-phrase-error>정리를 정확히 입력하세요.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-storage-cache-cleanup-modal">닫기</button>
                    <button type="submit" class="btn btn-outline-danger modal-action" data-admin-storage-cache-cleanup-submit data-ready-label="정리" data-busy-label="정리 중">정리</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-admin-storage-cache-cleanup-form]');
    if (!form) {
        return;
    }

    var input = form.querySelector('[data-admin-confirm-phrase]');
    var errorNote = form.querySelector('[data-admin-confirm-phrase-error]');
    var errorIcon = form.querySelector('[data-admin-confirm-phrase-icon]');
    var submitButton = form.querySelector('[data-admin-storage-cache-cleanup-submit]');
    if (!input) {
        return;
    }

    var expectedPhrase = input.getAttribute('data-admin-confirm-phrase') || '';
    var errorMessage = input.getAttribute('data-admin-confirm-message') || '';

    var clearPhraseError = function () {
        input.setCustomValidity('');
        input.classList.remove('form-input-invalid');
        input.removeAttribute('aria-invalid');
        if (errorNote) {
            errorNote.hidden = true;
        }
        if (errorIcon) {
            errorIcon.hidden = true;
        }
    };

    var showPhraseError = function () {
        input.setCustomValidity(errorMessage);
        input.classList.add('form-input-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (errorNote) {
            errorNote.hidden = false;
        }
        if (errorIcon) {
            errorIcon.hidden = false;
        }
    };

    input.addEventListener('input', function () {
        if (input.value.trim() === expectedPhrase) {
            clearPhraseError();
        } else if (errorNote && !errorNote.hidden) {
            showPhraseError();
        } else {
            input.setCustomValidity('');
            input.classList.remove('form-input-invalid');
            input.removeAttribute('aria-invalid');
            if (errorIcon) {
                errorIcon.hidden = true;
            }
        }
    });

    form.addEventListener('submit', function (event) {
        var normalizedValue = input.value.trim();
        if (normalizedValue === '' || normalizedValue === expectedPhrase) {
            if (normalizedValue === expectedPhrase) {
                clearPhraseError();
            }
            return;
        }

        showPhraseError();
        event.preventDefault();
        input.focus();
        input.reportValidity();
        return;
    });

    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) {
            return;
        }
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = submitButton.getAttribute('data-busy-label') || '정리 중';
        }
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
