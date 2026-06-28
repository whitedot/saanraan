<?php

declare(strict_types=1);

$adminPageTitle = $urlEmbedCacheModuleLabel . ' 임베드 캐시';
$adminPageSubtitle = [
    '공개 URL 임베드 렌더링에서 재사용하는 HTML 조각 파일입니다.',
    '정리해도 원본 콘텐츠와 URL 캐시 row는 삭제되지 않으며, 공개 화면에서 다시 필요해지면 새로 생성됩니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, $urlEmbedCacheAdminPath);
$cleanupTargetCount = (int) ($urlEmbedCacheSummary['total_count'] ?? 0);
$cleanupTargetBytes = (int) ($urlEmbedCacheSummary['total_bytes'] ?? 0);
$currentQuery = http_build_query(array_filter([
    'date_from' => (string) ($filters['date_from'] ?? ''),
    'date_to' => (string) ($filters['date_to'] ?? ''),
], static fn (string $value): bool => $value !== ''));

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url($urlEmbedCacheAdminPath)); ?>" class="filtering-form filtering filtering-plain admin-url-embed-cache-filter ui-form-theme">
    <div class="filtering-fields filtering-fields-fit">
        <label class="filtering-field" for="admin_url_embed_cache_date_from">
            <span class="filtering-label">시작일</span>
            <input id="admin_url_embed_cache_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <label class="filtering-field" for="admin_url_embed_cache_date_to">
            <span class="filtering-label">종료일</span>
            <input id="admin_url_embed_cache_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">조회</button>
        <?php if ($canDeleteUrlEmbedFragmentCache) { ?>
            <div class="filtering-actions">
                <button type="button" class="btn btn-outline-danger" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-url-embed-cache-cleanup-modal" data-overlay="#admin-url-embed-cache-cleanup-modal">정리</button>
            </div>
        <?php } ?>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">캐시 파일</h2>
        <?php if ((int) ($urlEmbedCacheRowTotal ?? 0) > count($urlEmbedCacheRows)) { ?>
            <span class="admin-summary-meta">최근 <?php echo sr_e(number_format(count($urlEmbedCacheRows))); ?>개 표시 / 전체 <?php echo sr_e(number_format((int) $urlEmbedCacheRowTotal)); ?>개</span>
        <?php } ?>
        <?php if ($currentQuery !== '') { ?>
            <a class="btn btn-sm btn-ghost-secondary" href="<?php echo sr_e(sr_url($urlEmbedCacheAdminPath . '?' . $currentQuery)); ?>">현재 조건 링크</a>
        <?php } ?>
    </div>
    <div class="admin-list-summary-row admin-url-embed-cache-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-secondary">대상 모듈 <?php echo sr_e($urlEmbedCacheModuleLabel); ?></span>
            <span class="badge badge-soft-secondary">파일 수 <?php echo sr_e(number_format((int) ($urlEmbedCacheSummary['total_count'] ?? 0))); ?>개</span>
            <span class="badge badge-soft-secondary">총 용량 <?php echo sr_e(sr_format_bytes((int) ($urlEmbedCacheSummary['total_bytes'] ?? 0))); ?></span>
            <span class="badge badge-soft-secondary">가장 오래된 파일 <?php echo (string) ($urlEmbedCacheSummary['oldest_at'] ?? '') !== '' ? sr_admin_time_html((string) $urlEmbedCacheSummary['oldest_at']) : '-'; ?></span>
            <span class="badge badge-soft-secondary">가장 최근 파일 <?php echo (string) ($urlEmbedCacheSummary['newest_at'] ?? '') !== '' ? sr_admin_time_html((string) $urlEmbedCacheSummary['newest_at']) : '-'; ?></span>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
                <tr>
                    <th>수정 시각</th>
                    <th>경로</th>
                    <th>해시</th>
                    <th>용량</th>
                    <th>미리보기</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($urlEmbedCacheRows === []) { ?>
                    <tr><td colspan="5" class="admin-empty-state">조회된 임베드 캐시 파일이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($urlEmbedCacheRows as $row) { ?>
                    <tr>
                        <td><?php echo sr_admin_time_html((string) ($row['modified_at'] ?? '')); ?></td>
                        <td><code><?php echo sr_e((string) ($row['relative_path'] ?? '')); ?></code></td>
                        <td><code><?php echo sr_e((string) ($row['cache_hash'] ?? '')); ?></code></td>
                        <td><?php echo sr_e(sr_format_bytes((int) ($row['size_bytes'] ?? 0))); ?></td>
                        <td><?php echo (string) ($row['preview'] ?? '') !== '' ? sr_e((string) $row['preview']) : '-'; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canDeleteUrlEmbedFragmentCache) { ?>
    <div id="admin-url-embed-cache-cleanup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-url-embed-cache-cleanup-modal-label" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url($urlEmbedCacheAdminPath)); ?>" class="modal-content admin-form ui-form-theme" data-admin-url-embed-cache-cleanup-form>
                <div class="modal-header">
                    <h3 id="admin-url-embed-cache-cleanup-modal-label" class="modal-title">임베드 캐시 정리</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#admin-url-embed-cache-cleanup-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>">
                    <input type="hidden" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>">
                    <p class="form-help">현재 조회 조건에 맞는 <?php echo sr_e($urlEmbedCacheModuleLabel); ?> 임베드 캐시 파일만 삭제합니다. 원본 콘텐츠와 URL 캐시 row는 삭제하지 않습니다.</p>
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
                        <p>정리는 한 번에 최대 <?php echo sr_e(number_format($urlEmbedCacheCleanupLimit)); ?>개씩 처리합니다. 실행 시점에 캐시 파일을 다시 확인하므로 실제 삭제 결과는 위 숫자와 다를 수 있습니다.</p>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="admin_url_embed_cache_cleanup_confirm_text">확인 문구 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <div class="validation-field">
                                <input id="admin_url_embed_cache_cleanup_confirm_text" type="text" name="confirm_text" value="" maxlength="40" class="form-input form-control-icon-end" autocomplete="off" required aria-describedby="admin_url_embed_cache_cleanup_confirm_text_error" data-admin-confirm-phrase="정리" data-admin-confirm-message="정리를 정확히 입력하세요.">
                                <div class="validation-static-icon" hidden data-admin-confirm-phrase-icon>
                                    <?php echo sr_material_icon_html('info', 'validation-error-icon', '정리를 정확히 입력하세요.'); ?>
                                </div>
                            </div>
                            <p class="form-help">정리를 실행하려면 <strong>정리</strong>를 입력하세요.</p>
                            <p id="admin_url_embed_cache_cleanup_confirm_text_error" class="validation-error-note" hidden data-admin-confirm-phrase-error>정리를 정확히 입력하세요.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-url-embed-cache-cleanup-modal">닫기</button>
                    <button type="submit" class="btn btn-outline-danger modal-action" data-admin-url-embed-cache-cleanup-submit data-ready-label="정리" data-busy-label="정리 중">정리</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-admin-url-embed-cache-cleanup-form]');
    if (!form) {
        return;
    }
    var input = form.querySelector('[data-admin-confirm-phrase]');
    var errorNote = form.querySelector('[data-admin-confirm-phrase-error]');
    var errorIcon = form.querySelector('[data-admin-confirm-phrase-icon]');
    var submitButton = form.querySelector('[data-admin-url-embed-cache-cleanup-submit]');
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
        if (input.value.trim() !== expectedPhrase) {
            showPhraseError();
            event.preventDefault();
            input.focus();
            input.reportValidity();
            return;
        }
        clearPhraseError();
    });
    form.addEventListener('submit', function (event) {
        if (event.defaultPrevented || !submitButton) {
            return;
        }
        submitButton.disabled = true;
        submitButton.textContent = submitButton.getAttribute('data-busy-label') || '정리 중';
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
