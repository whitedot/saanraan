<?php

$adminContainerClass = 'admin-content-file-downloads admin-ui-scope';
$downloadLogSortOptions = sr_content_admin_file_download_log_sort_options();
$downloadLogDefaultSort = sr_content_admin_file_download_log_default_sort();
$downloadLogSort = isset($downloadLogSort) && is_array($downloadLogSort) ? $downloadLogSort : $downloadLogDefaultSort;
$canEditFileDownloads = !empty($canEditFileDownloads);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/content/file-downloads')); ?>" class="admin-filter admin-content-file-download-filter ui-form-theme">
    <div class="admin-filter-grid admin-content-file-download-search-grid">
        <label class="admin-filter-field" for="content_file_download_filter_type">
            <span class="admin-filter-label">구분</span>
            <select id="content_file_download_filter_type" name="download_type" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($filters['download_type'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <option value="free"<?php echo (string) ($filters['download_type'] ?? '') === 'free' ? ' selected' : ''; ?>>무료</option>
                <option value="paid"<?php echo (string) ($filters['download_type'] ?? '') === 'paid' ? ' selected' : ''; ?>>유료</option>
            </select>
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_refund_status">
            <span class="admin-filter-label">환불 상태</span>
            <select id="content_file_download_filter_refund_status" name="refund_status" class="form-select admin-filter-input">
                <option value=""<?php echo (string) ($filters['refund_status'] ?? '') === '' ? ' selected' : ''; ?>>전체</option>
                <option value="none"<?php echo (string) ($filters['refund_status'] ?? '') === 'none' ? ' selected' : ''; ?>>미처리</option>
                <option value="refunded"<?php echo (string) ($filters['refund_status'] ?? '') === 'refunded' ? ' selected' : ''; ?>>환불 완료</option>
                <option value="access_revoked"<?php echo (string) ($filters['refund_status'] ?? '') === 'access_revoked' ? ' selected' : ''; ?>>접근권 회수</option>
            </select>
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_content_id">
            <span class="admin-filter-label">콘텐츠 ID</span>
            <input id="content_file_download_filter_content_id" type="number" min="1" name="content_id" value="<?php echo (int) ($filters['content_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['content_id']) : ''; ?>" class="form-input admin-filter-input">
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_file_id">
            <span class="admin-filter-label">파일 ID</span>
            <input id="content_file_download_filter_file_id" type="number" min="1" name="file_id" value="<?php echo (int) ($filters['file_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['file_id']) : ''; ?>" class="form-input admin-filter-input">
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_account_id">
            <span class="admin-filter-label">회원 ID</span>
            <input id="content_file_download_filter_account_id" type="number" min="1" name="account_id" value="<?php echo (int) ($filters['account_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['account_id']) : ''; ?>" class="form-input admin-filter-input">
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_date_from">
            <span class="admin-filter-label">시작일</span>
            <input id="content_file_download_filter_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input admin-filter-input">
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_date_to">
            <span class="admin-filter-label">종료일</span>
            <input id="content_file_download_filter_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input admin-filter-input">
        </label>
        <label class="admin-filter-field" for="content_file_download_filter_q">
            <span class="admin-filter-label">검색</span>
            <input id="content_file_download_filter_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="콘텐츠, 파일, 회원">
        </label>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">다운로드 내역</h2>
            <p class="admin-form-help">무료 다운로드는 로그인 회원만 회원 ID가 남고, 유료 다운로드는 차감 로그와 접근권을 함께 대조합니다.</p>
        </div>
        <a href="<?php echo sr_e(sr_url('/admin/content/files')); ?>" class="btn btn-sm btn-outline-secondary">파일 관리</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($downloadLogSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($downloadLogSortOptions, $downloadLogDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="다운로드 내역 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($downloadLogPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table admin-content-file-download-table">
            <caption class="sr-only">콘텐츠 파일 다운로드 내역</caption>
            <thead class="ui-table-head">
                <tr>
                    <th<?php echo sr_admin_sort_aria('created_at', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('다운로드 시각', 'created_at', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('content_title', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('콘텐츠', 'content_title', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('file_title', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('파일', 'file_title', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('회원', 'account_id', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('download_type', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('구분', 'download_type', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th>차감/정책</th>
                    <th>환불/회수</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($downloadLogs === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">다운로드 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($downloadLogs as $downloadLog) { ?>
                    <?php
                    $isPaid = (string) ($downloadLog['download_type'] ?? '') === 'paid';
                    $memberLabel = (int) ($downloadLog['account_id'] ?? 0) > 0
                        ? '#' . (string) (int) $downloadLog['account_id'] . ' ' . trim((string) ($downloadLog['display_name'] ?? ''))
                        : '비회원';
                    $accessSummary = trim((string) ($downloadLog['access_log_summary'] ?? ''));
                    $refundStatus = (string) ($downloadLog['refund_status'] ?? '');
                    $refundModalId = 'content-file-download-refund-modal-' . (int) ($downloadLog['id'] ?? 0);
                    $hasRefundAccessLogs = sr_content_file_download_log_access_log_ids($downloadLog) !== [];
                    $downloadAmount = (int) ($downloadLog['amount'] ?? 0);
                    $canRefund = $canEditFileDownloads && $isPaid && $hasRefundAccessLogs && (int) ($downloadLog['account_id'] ?? 0) > 0 && $refundStatus === '';
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) $downloadLog['created_at']); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['content_title'] ?? '삭제된 콘텐츠')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['content_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['content_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['file_title'] ?? '삭제된 파일')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['file_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['original_name'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break"><?php echo sr_e($memberLabel); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo $isPaid ? 'is-warning' : 'is-normal'; ?>"><?php echo $isPaid ? '유료' : '무료'; ?></span></td>
                        <td class="admin-table-break">
                            <?php if ($isPaid) { ?>
                                <?php if (!$hasRefundAccessLogs && $downloadAmount <= 0) { ?>
                                    기존 접근권 사용
                                <?php } else { ?>
                                    <?php echo sr_e(sr_content_asset_module_labels((string) ($downloadLog['asset_module'] ?? ''), $pdo)); ?>
                                    <?php echo sr_e(number_format($downloadAmount)); ?>
                                    · <?php echo sr_e((string) (sr_content_asset_download_charge_policies()[(string) ($downloadLog['charge_policy'] ?? 'once')] ?? $downloadLog['charge_policy'] ?? '')); ?>
                                <?php } ?>
                                <?php if ($accessSummary !== '') { ?>
                                    <p class="admin-form-help"><?php echo sr_e(str_replace("\n", ' / ', $accessSummary)); ?></p>
                                <?php } elseif (!$hasRefundAccessLogs && $downloadAmount > 0) { ?>
                                    <p class="admin-form-help">연결된 차감 로그 없음</p>
                                <?php } ?>
                            <?php } else { ?>
                                차감 없음
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($refundStatus === 'refunded') { ?>
                                <span class="admin-status is-normal">환불 완료</span>
                                <p class="admin-form-help">
                                    <?php echo sr_e((string) ($downloadLog['refunded_at'] ?? '')); ?>
                                    <?php if ((string) ($downloadLog['refund_note'] ?? '') !== '') { ?>
                                        · <?php echo sr_e((string) ($downloadLog['refund_note'] ?? '')); ?>
                                    <?php } ?>
                                </p>
                            <?php } elseif ($refundStatus === 'access_revoked') { ?>
                                <span class="admin-status is-left">접근권 회수</span>
                                <p class="admin-form-help"><?php echo sr_e((string) ($downloadLog['refunded_at'] ?? '')); ?></p>
                            <?php } elseif (!$isPaid) { ?>
                                <span class="admin-status is-normal">대상 아님</span>
                            <?php } elseif ($canRefund) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($refundModalId); ?>" data-overlay="#<?php echo sr_e($refundModalId); ?>">
                                    <?php echo $downloadAmount > 0 ? '수동 환불' : '접근권 회수'; ?>
                                </button>
                            <?php } elseif (!$canEditFileDownloads) { ?>
                                <span class="admin-status is-left">조회 전용</span>
                            <?php } else { ?>
                                <span class="admin-status is-blocked">처리 불가</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($downloadLogPagination, '파일 다운로드 내역 페이지'); ?>

<?php foreach ($downloadLogs as $downloadLog) { ?>
    <?php
    if (!$canEditFileDownloads) {
        continue;
    }
    $isPaid = (string) ($downloadLog['download_type'] ?? '') === 'paid';
    $refundStatus = (string) ($downloadLog['refund_status'] ?? '');
    if (!$isPaid || $refundStatus !== '' || (int) ($downloadLog['account_id'] ?? 0) <= 0 || sr_content_file_download_log_access_log_ids($downloadLog) === []) {
        continue;
    }
    $refundModalId = 'content-file-download-refund-modal-' . (int) ($downloadLog['id'] ?? 0);
    $refundFieldPrefix = 'content_file_download_refund_' . (int) ($downloadLog['id'] ?? 0);
    $refundTitle = (int) ($downloadLog['amount'] ?? 0) > 0 ? '파일 다운로드 수동 환불' : '파일 다운로드 접근권 회수';
    ?>
    <div id="<?php echo sr_e($refundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($refundFieldPrefix); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/file-downloads')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($refundFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e($refundTitle); ?></h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($refundModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="refund_download">
                    <input type="hidden" name="download_log_id" value="<?php echo sr_e((string) (int) ($downloadLog['id'] ?? 0)); ?>">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta">회원 <strong>#<?php echo sr_e((string) (int) ($downloadLog['account_id'] ?? 0)); ?></strong></span>
                        <span class="admin-summary-meta">파일 <strong>#<?php echo sr_e((string) (int) ($downloadLog['file_id'] ?? 0)); ?></strong></span>
                        <span class="admin-summary-meta">금액 <strong><?php echo sr_e(number_format((int) ($downloadLog['amount'] ?? 0))); ?></strong></span>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="<?php echo sr_e($refundFieldPrefix); ?>_note">처리 사유 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <input id="<?php echo sr_e($refundFieldPrefix); ?>_note" type="text" name="refund_note" class="form-input form-control-full" maxlength="255" required data-overlay-focus>
                            <p class="admin-form-help">원장 거래가 있으면 같은 금액의 환불 거래를 만들고, 최초 1회 접근권은 함께 회수합니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($refundModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">처리</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
