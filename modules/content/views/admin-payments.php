<?php

$adminContainerClass = 'admin-content-payments admin-ui-scope';
$paymentSortOptions = isset($paymentSortOptions) && is_array($paymentSortOptions) ? $paymentSortOptions : sr_content_admin_payment_history_sort_options();
$paymentDefaultSort = isset($paymentDefaultSort) && is_array($paymentDefaultSort) ? $paymentDefaultSort : sr_content_admin_payment_history_default_sort();
$paymentSort = isset($paymentSort) && is_array($paymentSort) ? $paymentSort : $paymentDefaultSort;
$canEditContentPayments = !empty($canEditContentPayments);
$selectedKinds = is_array($filters['kind'] ?? null) ? $filters['kind'] : [];
$selectedPaymentTypes = is_array($filters['payment_type'] ?? null) ? $filters['payment_type'] : [];
$selectedSettlementKinds = is_array($filters['settlement_kind'] ?? null) ? $filters['settlement_kind'] : [];
$selectedRefundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
$selectedCouponUsed = is_array($filters['coupon_used'] ?? null) ? $filters['coupon_used'] : [];
$legacySettlementKind = sr_content_asset_settlement_kind_for_use(1, 0, '');
$kindLabels = ['content_view' => '콘텐츠 열람', 'content_file_download' => '파일 다운로드'];
$paymentTypeLabels = ['asset_only' => '자산 결제', 'coupon_access' => '쿠폰 접근권', 'coupon_partial_discount_asset' => '쿠폰+자산'];
$settlementFilterLabels = ['paid' => '결제 완료', 'paid_settled_zero' => '0원 정산', $legacySettlementKind => '레거시 불명'];
$settlementKindLabels = $settlementFilterLabels + ['' => '레거시 불명'];
$refundStatusLabels = ['none' => '미처리', 'refunded' => '환불 완료', 'access_revoked' => '접근권 회수'];
$paymentLogAccessIds = static function (array $paymentLog): array {
    $decoded = json_decode((string) ($paymentLog['asset_access_log_ids_json'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }
    $ids = [];
    foreach ($decoded as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/content/payments');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php
$detailFilterOpen = (int) ($filters['target_id'] ?? 0) > 0
    || (int) ($filters['account_id'] ?? 0) > 0
    || (string) ($filters['date_from'] ?? '') !== ''
    || (string) ($filters['date_to'] ?? '') !== '';
?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/content/payments')); ?>" class="filtering-form admin-content-payment-filter ui-form-theme">
    <div class="filtering-fields admin-content-filter-stack">
        <div class="filtering filtering-card<?php echo $detailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields filtering-fields-fit">
                <div class="filtering-field">
                    <span class="filtering-label">대상 유형</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('content_payment_filter_kind', 'kind', $kindLabels, $selectedKinds, '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">결제 유형</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('content_payment_filter_payment_type', 'payment_type', $paymentTypeLabels, $selectedPaymentTypes, '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">환불 상태</span>
                    <?php echo sr_admin_filter_toggle_group_html('content_payment_filter_refund_status', 'refund_status', $refundStatusLabels, $selectedRefundStatuses, '전체'); ?>
                </div>
                <label class="filtering-field-fill filtering-field" for="content_payment_filter_q">
                    <span class="filtering-label">검색</span>
                    <input id="content_payment_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="제목, 쿠폰 키, 결제 키">
                </label>
            </div>
            <div id="content_payment_detail_filters" class="filtering-body" data-filtering-body<?php echo $detailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field">
                    <span class="filtering-label">정산</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('content_payment_filter_settlement_kind', 'settlement_kind', $settlementFilterLabels, $selectedSettlementKinds, '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">쿠폰</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('content_payment_filter_coupon_used', 'coupon_used', ['yes' => '사용', 'no' => '미사용'], $selectedCouponUsed, '전체'); ?>
                </div>
                <label class="filtering-field" for="content_payment_filter_target_id">
                    <span class="filtering-label">대상 ID</span>
                    <input id="content_payment_filter_target_id" type="number" min="1" name="target_id" value="<?php echo (int) ($filters['target_id'] ?? 0) > 0 ? sr_e((string) (int) $filters['target_id']) : ''; ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="content_payment_filter_account_id">
                    <span class="filtering-label">회원</span>
                    <input id="content_payment_filter_account_id" type="text" name="account_id" value="<?php echo (int) ($filters['account_id'] ?? 0) > 0 ? sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $filters['account_id'])) : ''; ?>" class="form-input filtering-input" maxlength="80" autocomplete="off">
                </label>
                <label class="filtering-field" for="content_payment_filter_date_from">
                    <span class="filtering-label">시작일</span>
                    <input id="content_payment_filter_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field" for="content_payment_filter_date_to">
                    <span class="filtering-label">종료일</span>
                    <input id="content_payment_filter_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
                </label>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $detailFilterOpen ? 'true' : 'false'; ?>" aria-controls="content_payment_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">유료 결제 내역</h2>
            <p class="form-help">콘텐츠 유료 열람과 파일 다운로드 결제 단위를 함께 조회합니다.</p>
        </div>
        <a href="<?php echo sr_e(sr_url('/admin/content/file-downloads')); ?>" class="btn btn-sm btn-outline-secondary">파일 다운로드 내역</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($paymentSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($paymentSortOptions, $paymentDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="결제 내역 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($paymentPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-content-payment-table">
            <caption class="sr-only">콘텐츠 유료 결제 내역</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('created_at', $paymentSort); ?>><?php echo sr_admin_sort_header_html('결제 시각', 'created_at', $paymentSort, $paymentSortOptions, $paymentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('target', $paymentSort); ?>><?php echo sr_admin_sort_header_html('대상', 'target', $paymentSort, $paymentSortOptions, $paymentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $paymentSort); ?>><?php echo sr_admin_sort_header_html('회원', 'account_id', $paymentSort, $paymentSortOptions, $paymentDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('payment_type', $paymentSort); ?>><?php echo sr_admin_sort_header_html('결제 유형', 'payment_type', $paymentSort, $paymentSortOptions, $paymentDefaultSort); ?></th>
                    <th>계약/관계</th>
                    <th>처리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($paymentLogs === []) { ?>
                    <tr>
                        <td colspan="6" class="admin-empty-state">유료 결제 내역이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($paymentLogs as $paymentLog) { ?>
                    <?php
                    $sourceKind = (string) ($paymentLog['source_kind'] ?? '');
                    $refundStatus = (string) ($paymentLog['refund_status'] ?? '');
                    $settlementKind = (string) ($paymentLog['settlement_kind'] ?? $legacySettlementKind);
                    $paymentType = (string) ($paymentLog['payment_type'] ?? 'asset_only');
                    $accountId = (int) ($paymentLog['account_id'] ?? 0);
                    $memberName = trim((string) ($paymentLog['display_name'] ?? ''));
                    $memberPublicHash = $accountId > 0 ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $accountId) : '';
                    $accessIds = $paymentLogAccessIds($paymentLog);
                    $couponRedemptionId = (int) ($paymentLog['coupon_redemption_id'] ?? 0);
                    $canProcess = $canEditContentPayments && $accountId > 0 && $refundStatus === '' && $settlementKind !== $legacySettlementKind && $settlementKind !== '' && ($sourceKind === 'content_view' || $accessIds !== []);
                    $modalId = 'content-payment-refund-modal-' . $sourceKind . '-' . (int) ($paymentLog['source_id'] ?? 0);
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_content_time_html((string) ($paymentLog['created_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <span class="admin-status is-normal"><?php echo sr_e((string) ($kindLabels[$sourceKind] ?? $sourceKind)); ?></span>
                            <strong><?php echo sr_e((string) ($paymentLog['target_title'] ?? '삭제된 대상')); ?></strong>
                            <small class="admin-summary-meta">콘텐츠 #<?php echo sr_e((string) (int) ($paymentLog['content_id'] ?? 0)); ?><?php echo (int) ($paymentLog['file_id'] ?? 0) > 0 ? ' · 파일 #' . sr_e((string) (int) ($paymentLog['file_id'] ?? 0)) : ''; ?></small>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($accountId > 0) { ?>
                                <strong><?php echo sr_e($memberName !== '' ? $memberName : '회원'); ?></strong>
                                <?php if ($memberPublicHash !== '') { ?>
                                    <small class="admin-summary-meta"><?php echo sr_e($memberPublicHash); ?></small>
                                <?php } ?>
                            <?php } else { ?>
                                <?php echo sr_e('회원 없음'); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <span class="admin-status <?php echo $settlementKind === $legacySettlementKind || $settlementKind === '' ? 'is-blocked' : 'is-warning'; ?>"><?php echo sr_e((string) ($paymentTypeLabels[$paymentType] ?? $paymentType)); ?></span>
                            <p class="form-help"><?php echo sr_e((string) ($settlementKindLabels[$settlementKind] ?? $settlementKind)); ?> · <?php echo sr_e(number_format((int) ($paymentLog['settlement_amount'] ?? 0))); ?> <?php echo sr_e((string) ($paymentLog['settlement_currency'] ?? 'KRW')); ?></p>
                        </td>
                        <td class="admin-table-break">
                            <p class="form-help">환불 계약 <?php echo sr_e((string) ($paymentLog['refund_policy_version'] ?? '')); ?></p>
                            <?php if ($accessIds !== []) { ?>
                                <p class="form-help">자산 allocation #<?php echo sr_e(implode(', #', array_map('strval', $accessIds))); ?></p>
                            <?php } else { ?>
                                <p class="form-help">자산 allocation 없음</p>
                            <?php } ?>
                            <?php if ($couponRedemptionId > 0) { ?>
                                <p class="form-help">쿠폰 redemption #<?php echo sr_e((string) $couponRedemptionId); ?> <?php echo (string) ($paymentLog['coupon_dedupe_key'] ?? '') !== '' ? '· ' . sr_e((string) $paymentLog['coupon_dedupe_key']) : ''; ?></p>
                            <?php } ?>
                            <?php if (trim((string) ($paymentLog['asset_log_summary'] ?? '')) !== '') { ?>
                                <p class="form-help"><?php echo sr_e(str_replace("\n", ' / ', (string) $paymentLog['asset_log_summary'])); ?></p>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($refundStatus === 'refunded') { ?>
                                <span class="admin-status is-normal">환불 완료</span>
                                <p class="form-help"><?php echo sr_e((string) ($paymentLog['refunded_at'] ?? '')); ?></p>
                            <?php } elseif ($refundStatus === 'access_revoked') { ?>
                                <span class="admin-status is-left">접근권 회수</span>
                                <p class="form-help"><?php echo sr_e((string) ($paymentLog['access_revoked_at'] ?? '')); ?></p>
                            <?php } elseif ($canProcess) { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($modalId); ?>" data-overlay="#<?php echo sr_e($modalId); ?>">
                                    <?php echo (int) ($paymentLog['settlement_amount'] ?? 0) > 0 ? '수동 환불' : '접근권 회수'; ?>
                                </button>
                            <?php } elseif ($settlementKind === $legacySettlementKind || $settlementKind === '') { ?>
                                <span class="admin-status is-blocked">수동 확인</span>
                                <p class="form-help">레거시 정산 불명 row는 자동 처리하지 않습니다.</p>
                            <?php } elseif (!$canEditContentPayments) { ?>
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
    <?php echo sr_admin_status_description_list_html('content_payment_refund_status', $refundStatusLabels, [], '환불 처리 상태 설명'); ?>
</section>

<?php echo sr_admin_pagination_html($paymentPagination, '콘텐츠 결제 내역 페이지'); ?>

<?php foreach ($paymentLogs as $paymentLog) { ?>
    <?php
    $sourceKind = (string) ($paymentLog['source_kind'] ?? '');
    $refundStatus = (string) ($paymentLog['refund_status'] ?? '');
    $settlementKind = (string) ($paymentLog['settlement_kind'] ?? $legacySettlementKind);
    $accountId = (int) ($paymentLog['account_id'] ?? 0);
    $accessIds = $paymentLogAccessIds($paymentLog);
    if (!$canEditContentPayments || $accountId <= 0 || $refundStatus !== '' || $settlementKind === $legacySettlementKind || $settlementKind === '' || ($sourceKind !== 'content_view' && $accessIds === [])) {
        continue;
    }
    $modalId = 'content-payment-refund-modal-' . $sourceKind . '-' . (int) ($paymentLog['source_id'] ?? 0);
    $fieldPrefix = 'content_payment_refund_' . $sourceKind . '_' . (int) ($paymentLog['source_id'] ?? 0);
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($fieldPrefix); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/content/payments')); ?>" class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($fieldPrefix); ?>_title" class="modal-title"><?php echo (int) ($paymentLog['settlement_amount'] ?? 0) > 0 ? '콘텐츠 결제 수동 환불' : '콘텐츠 접근권 회수'; ?></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="<?php echo $sourceKind === 'content_view' ? 'refund_view_payment' : 'refund_file_download'; ?>">
                    <input type="hidden" name="<?php echo $sourceKind === 'content_view' ? 'payment_log_id' : 'download_log_id'; ?>" value="<?php echo sr_e((string) (int) ($paymentLog['source_id'] ?? 0)); ?>">
                    <div class="admin-summary-stats">
                        <span class="admin-summary-meta">대상 <strong>#<?php echo sr_e((string) (int) ($paymentLog['target_id'] ?? 0)); ?></strong></span>
                        <span class="admin-summary-meta">금액 <strong><?php echo sr_e(number_format((int) ($paymentLog['settlement_amount'] ?? 0))); ?></strong></span>
                        <span class="admin-summary-meta">계약 <strong><?php echo sr_e((string) ($paymentLog['refund_policy_version'] ?? '')); ?></strong></span>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_expiration_policy">포인트 환불 유효기간</label>
                        <div class="form-field">
                            <select id="<?php echo sr_e($fieldPrefix); ?>_expiration_policy" name="refund_expiration_policy" class="form-select">
                                <option value="original">환불 참조 원거래의 유효기간</option>
                                <option value="reset">환불 시점부터 유효기간 계산</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_note">처리 사유 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="<?php echo sr_e($fieldPrefix); ?>_note" type="text" name="refund_note" class="form-input form-control-full" maxlength="255" required data-overlay-focus>
                            <p class="form-help">원장 거래가 있으면 환불 거래를 만들고, 최초 1회 또는 0원 정산 접근권은 함께 회수합니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">처리</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
