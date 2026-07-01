<?php

$adminContainerClass = 'admin-community-attachment-downloads admin-ui-scope';
$downloadLogSortOptions = isset($downloadLogSortOptions) && is_array($downloadLogSortOptions) ? $downloadLogSortOptions : sr_community_admin_attachment_download_log_sort_options($pdo ?? null);
$downloadLogDefaultSort = isset($downloadLogDefaultSort) && is_array($downloadLogDefaultSort) ? $downloadLogDefaultSort : sr_community_admin_attachment_download_log_default_sort();
$downloadLogSort = isset($downloadLogSort) && is_array($downloadLogSort) ? $downloadLogSort : $downloadLogDefaultSort;
$selectedDownloadTypes = is_array($filters['download_type'] ?? null) ? $filters['download_type'] : [];
$selectedRefundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
$canEditAttachmentDownloads = !empty($canEditAttachmentDownloads);
$hasAttachmentDownloadRefundColumns = isset($pdo) && $pdo instanceof PDO && sr_community_attachment_download_log_refund_columns_exist($pdo);
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/community/attachment-downloads');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$detailFilterOpen = (int) ($filters['board_id'] ?? 0) > 0
    || (int) ($filters['post_id'] ?? 0) > 0
    || (int) ($filters['attachment_id'] ?? 0) > 0
    || (int) ($filters['account_id'] ?? 0) > 0
    || (string) ($filters['date_from'] ?? '') !== ''
    || (string) ($filters['date_to'] ?? '') !== '';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/community/attachment-downloads')); ?>" class="filtering-form admin-community-attachment-download-filter ui-form-theme">
    <div class="filtering-fields admin-content-filter-stack">
        <div class="filtering filtering-card<?php echo $detailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields filtering-fields-fit">
                <div class="filtering-field">
                    <span class="filtering-label">구분</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('community_attachment_download_filter_type', 'download_type', ['free' => '무료', 'paid' => '유료'], $selectedDownloadTypes, '전체'); ?>
                </div>
                <?php if ($hasAttachmentDownloadRefundColumns) { ?>
                    <div class="filtering-field">
                        <span class="filtering-label">환불</span>
                        <?php echo sr_admin_filter_toggle_group_html('community_attachment_download_filter_refund_status', 'refund_status', ['none' => '미처리', 'refunded' => '환불 완료', 'access_revoked' => '접근권 회수'], $selectedRefundStatuses, '전체'); ?>
                    </div>
                <?php } ?>
                <label class="filtering-field-fill filtering-field" for="community_attachment_download_filter_q">
                    <span class="filtering-label">검색</span>
                    <input id="community_attachment_download_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="게시판, 게시글, 첨부파일, 회원">
                </label>
            </div>
            <div id="community_attachment_download_detail_filters" class="filtering-body" data-filtering-body<?php echo $detailFilterOpen ? '' : ' hidden'; ?>>
                <label class="filtering-field" for="community_attachment_download_filter_board">
                    <span class="filtering-label">게시판</span>
                    <select id="community_attachment_download_filter_board" name="board_id" class="form-select filtering-input">
                        <option value="0"<?php echo (int) ($filters['board_id'] ?? 0) === 0 ? ' selected' : ''; ?>>전체 게시판</option>
                        <?php foreach ($boards as $board) { ?>
                            <option value="<?php echo sr_e((string) (int) ($board['id'] ?? 0)); ?>"<?php echo (int) ($filters['board_id'] ?? 0) === (int) ($board['id'] ?? 0) ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($board['board_group_title'] ?? '') !== '' ? (string) $board['board_group_title'] . ' / ' . (string) ($board['title'] ?? '') : (string) ($board['title'] ?? '')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_post_id">
                    <span class="filtering-label">게시글 ID</span>
                    <input id="community_attachment_download_filter_post_id" type="number" min="1" name="post_id" value="<?php echo (int) ($filters['post_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['post_id']) : ''; ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_attachment_id">
                    <span class="filtering-label">첨부 ID</span>
                    <input id="community_attachment_download_filter_attachment_id" type="number" min="1" name="attachment_id" value="<?php echo (int) ($filters['attachment_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['attachment_id']) : ''; ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_account_id">
                    <span class="filtering-label">회원</span>
                    <input id="community_attachment_download_filter_account_id" type="text" name="account_id" value="<?php echo (int) ($filters['account_id'] ?? 0) > 0 ? sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $filters['account_id'])) : ''; ?>" class="form-input filtering-input" maxlength="80" autocomplete="off">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_date_from">
                    <span class="filtering-label">시작일</span>
                    <input id="community_attachment_download_filter_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="community_attachment_download_filter_date_to">
                    <span class="filtering-label">종료일</span>
                    <input id="community_attachment_download_filter_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
                </label>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $detailFilterOpen ? 'true' : 'false'; ?>" aria-controls="community_attachment_download_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">다운로드 내역</h2>
            <p class="form-help">무료 다운로드는 로그인 회원만 회원 정보가 남고, 유료 다운로드는 차감 로그 ID를 함께 보존합니다.</p>
        </div>
        <a href="<?php echo sr_e(sr_url('/admin/community/attachments')); ?>" class="btn btn-sm btn-outline-secondary">첨부파일 관리</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($downloadLogSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($downloadLogSortOptions, $downloadLogDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="다운로드 내역 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($downloadLogPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-community-attachment-download-table">
            <caption class="sr-only">커뮤니티 첨부 다운로드 내역</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('created_at', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('다운로드 시각', 'created_at', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('board', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('게시판', 'board', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('post', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('게시글', 'post', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('attachment', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('첨부파일', 'attachment', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('회원', 'account_id', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('download_type', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('구분', 'download_type', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('amount', $downloadLogSort); ?>><?php echo sr_admin_sort_header_html('차감', 'amount', $downloadLogSort, $downloadLogSortOptions, $downloadLogDefaultSort); ?></th>
                    <th>처리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($downloadLogs === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state">다운로드 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($downloadLogs as $downloadLog) { ?>
                    <?php
                    $isPaid = (string) ($downloadLog['download_type'] ?? '') === 'paid';
                    $refundStatus = (string) ($downloadLog['refund_status'] ?? '');
                    $downloadAccountId = (int) ($downloadLog['account_id'] ?? 0);
                    $memberName = trim((string) ($downloadLog['display_name'] ?? ''));
                    $memberPublicHash = $downloadAccountId > 0 && function_exists('sr_admin_member_public_hash')
                        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $downloadAccountId)
                        : '';
                    $assetLogSummary = trim((string) ($downloadLog['asset_log_summary'] ?? ''));
                    $refundModalId = 'community-attachment-download-refund-modal-' . (int) ($downloadLog['id'] ?? 0);
                    $canRefund = $canEditAttachmentDownloads && $hasAttachmentDownloadRefundColumns && $isPaid && $refundStatus === '' && $downloadAccountId > 0 && sr_community_attachment_download_log_access_log_ids($downloadLog) !== [];
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_community_time_html((string) ($downloadLog['created_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['board_title'] ?? '삭제된 게시판')); ?></strong>
                            <small class="admin-summary-meta"><?php echo sr_e((string) ($downloadLog['board_key'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['post_title'] ?? '삭제된 게시글')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['post_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['post_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e((string) ($downloadLog['attachment_name'] ?? '삭제된 첨부파일')); ?></strong>
                            <small class="admin-summary-meta">#<?php echo sr_e((string) (int) ($downloadLog['attachment_id'] ?? 0)); ?> <?php echo sr_e((string) ($downloadLog['attachment_status'] ?? '')); ?></small>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($downloadAccountId > 0) { ?>
                                <strong><?php echo sr_e($memberName !== '' ? $memberName : '회원'); ?></strong>
                                <?php if ($memberPublicHash !== '') { ?>
                                    <small class="admin-summary-meta"><?php echo sr_e($memberPublicHash); ?></small>
                                <?php } ?>
                            <?php } else { ?>
                                <?php echo sr_e('비회원'); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo $isPaid ? 'is-warning' : 'is-normal'; ?>"><?php echo $isPaid ? '유료' : '무료'; ?></span></td>
                        <td class="admin-table-break">
                            <?php if ($isPaid) { ?>
                                <?php echo sr_e(sr_community_asset_module_labels((string) ($downloadLog['asset_module'] ?? ''), $pdo)); ?>
                                <?php echo sr_e(number_format((int) ($downloadLog['amount'] ?? 0))); ?>
                                · <?php echo sr_e((string) (sr_community_asset_charge_policies()[(string) ($downloadLog['charge_policy'] ?? 'once')] ?? $downloadLog['charge_policy'] ?? '')); ?>
                                <?php if ($assetLogSummary !== '') { ?>
                                    <p class="form-help"><?php echo sr_e(str_replace("\n", ' / ', $assetLogSummary)); ?></p>
                                <?php } elseif (sr_community_attachment_download_log_access_log_ids($downloadLog) !== []) { ?>
                                    <p class="form-help">연결된 차감 로그를 확인할 수 없음</p>
                                <?php } ?>
                            <?php } else { ?>
                                차감 없음
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php if (!$hasAttachmentDownloadRefundColumns) { ?>
                                <span class="admin-status is-blocked">업데이트 필요</span>
                            <?php } elseif ($refundStatus === 'refunded') { ?>
                                <span class="admin-status is-normal">환불 완료</span>
                                <p class="form-help"><?php echo sr_e((string) ($downloadLog['refunded_at'] ?? '')); ?></p>
                            <?php } elseif ($refundStatus === 'access_revoked') { ?>
                                <span class="admin-status is-left">접근권 회수</span>
                                <p class="form-help"><?php echo sr_e((string) ($downloadLog['access_revoked_at'] ?? '')); ?></p>
                            <?php } elseif ($canRefund) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($refundModalId); ?>" data-overlay="#<?php echo sr_e($refundModalId); ?>">
                                    처리
                                </button>
                            <?php } else { ?>
                                <span class="admin-status is-normal">미처리</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('community_attachment_download_type', ['free' => '무료', 'paid' => '유료'], [], '다운로드 유형 설명'); ?>
    <?php if ($hasAttachmentDownloadRefundColumns) { ?>
        <?php echo sr_admin_status_description_list_html('community_attachment_download_refund_status', ['none' => '미처리', 'refunded' => '환불 완료', 'access_revoked' => '접근권 회수'], [], '환불 처리 상태 설명'); ?>
    <?php } ?>
</section>

<?php echo sr_admin_pagination_html($downloadLogPagination, '첨부 다운로드 내역 페이지'); ?>

<?php foreach ($downloadLogs as $downloadLog) { ?>
    <?php
    $isPaid = (string) ($downloadLog['download_type'] ?? '') === 'paid';
    $refundStatus = (string) ($downloadLog['refund_status'] ?? '');
    if (!$canEditAttachmentDownloads || !$hasAttachmentDownloadRefundColumns || !$isPaid || $refundStatus !== '' || (int) ($downloadLog['account_id'] ?? 0) <= 0 || sr_community_attachment_download_log_access_log_ids($downloadLog) === []) {
        continue;
    }
    $refundModalId = 'community-attachment-download-refund-modal-' . (int) ($downloadLog['id'] ?? 0);
    $refundFieldPrefix = 'community_attachment_download_refund_' . (int) ($downloadLog['id'] ?? 0);
    $refundTitle = (int) ($downloadLog['amount'] ?? 0) > 0 ? '첨부 다운로드 수동 환불' : '첨부 다운로드 접근권 회수';
    $refundAccountId = (int) ($downloadLog['account_id'] ?? 0);
    $refundAccountHash = $refundAccountId > 0 && function_exists('sr_admin_member_public_hash')
        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $refundAccountId)
        : '';
    ?>
    <div id="<?php echo sr_e($refundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($refundFieldPrefix); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/community/attachment-downloads')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($refundFieldPrefix); ?>_title" class="modal-title"><?php echo sr_e($refundTitle); ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($refundModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="refund_download">
                    <input type="hidden" name="download_log_id" value="<?php echo sr_e((string) (int) ($downloadLog['id'] ?? 0)); ?>">
                    <div class="admin-summary-stats">
                        <?php if ($refundAccountHash !== '') { ?>
                            <span class="admin-summary-meta">회원 <strong><?php echo sr_e($refundAccountHash); ?></strong></span>
                        <?php } ?>
                        <span class="admin-summary-meta">첨부 <strong>#<?php echo sr_e((string) (int) ($downloadLog['attachment_id'] ?? 0)); ?></strong></span>
                        <span class="admin-summary-meta">금액 <strong><?php echo sr_e(number_format((int) ($downloadLog['amount'] ?? 0))); ?></strong></span>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($refundFieldPrefix); ?>_expiration_policy">포인트 환불 유효기간</label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($refundFieldPrefix); ?>_expiration_policy" name="refund_expiration_policy" class="form-select">
                                <option value="original">환불 참조 원거래의 유효기간</option>
                                <option value="reset">환불 시점부터 유효기간 계산</option>
                            </select>
                            <p class="form-help">포인트 차감 환불에 적용합니다. 다른 포인트/금액 항목 환불에는 영향이 없습니다.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($refundFieldPrefix); ?>_note">처리 사유 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="<?php echo sr_e($refundFieldPrefix); ?>_note" type="text" name="refund_note" class="form-input form-control-full" maxlength="255" required data-overlay-focus>
                            <p class="form-help">원장 거래가 있으면 같은 금액의 환불 거래를 만들고, 최초 1회 접근권은 함께 회수합니다.</p>
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
