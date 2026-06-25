<?php

$couponAdminPage = isset($couponAdminPage) ? (string) $couponAdminPage : 'definitions';
if (!in_array($couponAdminPage, ['definitions', 'issues', 'redemptions', 'campaigns'], true)) {
    $couponAdminPage = 'definitions';
}
$couponAdminPageMeta = [
    'definitions' => [
        'title' => '쿠폰 관리',
        'subtitle' => '',
    ],
    'issues' => [
        'title' => '지급 내역',
        'subtitle' => '',
    ],
    'redemptions' => [
        'title' => '사용 내역',
        'subtitle' => '',
    ],
    'campaigns' => [
        'title' => '발급 캠페인',
        'subtitle' => '',
    ],
];
$adminPageTitle = $couponAdminPageMeta[$couponAdminPage]['title'];
$adminPageSubtitle = $couponAdminPageMeta[$couponAdminPage]['subtitle'];
$targetTypes = sr_coupon_target_types($pdo);
$couponHistoryTargetTypes = $targetTypes;
unset($couponHistoryTargetTypes['all']);
$couponSearchableTargetTypes = array_filter($targetTypes, static function (string $targetType): bool {
    return $targetType !== 'all';
}, ARRAY_FILTER_USE_KEY);
$refundablePolicies = sr_coupon_refundable_policies();
$definitionStatusLabels = [
    'active' => '사용 중',
    'disabled' => '중지',
];
$definitionStatusClasses = [
    'active' => 'is-normal',
    'disabled' => 'is-blocked',
];
$issueStatusClasses = [
    'active' => 'is-normal',
    'used' => 'is-normal',
    'expired' => 'is-blocked',
    'revoked' => 'is-left',
    'withdrawn_expired' => 'is-left',
    'refund_requested' => 'is-blocked',
    'refunded' => 'is-normal',
];
$redemptionStatusClasses = [
    'redeemed' => 'is-normal',
    'refunded' => 'is-normal',
];
$claimLogStatusClasses = [
    'reserved' => 'is-blocked',
    'pending_payment' => 'is-blocked',
    'issued' => 'is-normal',
    'failed' => 'is-left',
    'cancelled' => 'is-left',
    'expired' => 'is-left',
    'expired_unmaterialized' => 'is-left',
];
$definitionFilters = isset($definitionFilters) && is_array($definitionFilters) ? $definitionFilters : ['status' => [], 'target_type' => [], 'q' => ''];
$issueFilters = isset($issueFilters) && is_array($issueFilters) ? $issueFilters : ['status' => [], 'target_type' => [], 'coupon_q' => '', 'account' => ['field' => 'all', 'keyword' => '']];
$redemptionFilters = isset($redemptionFilters) && is_array($redemptionFilters) ? $redemptionFilters : ['status' => [], 'target_type' => [], 'refundable_policy' => [], 'coupon_q' => '', 'account' => ['field' => 'all', 'keyword' => '']];
$definitionSort = isset($definitionSort) && is_array($definitionSort) ? $definitionSort : (sr_coupon_admin_definition_default_sort() + ['is_default' => true]);
$issueSort = isset($issueSort) && is_array($issueSort) ? $issueSort : (sr_coupon_admin_issue_default_sort() + ['is_default' => true]);
$redemptionSort = isset($redemptionSort) && is_array($redemptionSort) ? $redemptionSort : (sr_coupon_admin_redemption_default_sort() + ['is_default' => true]);
$definitionPagination = isset($definitionPagination) && is_array($definitionPagination) ? $definitionPagination : [];
$issuePagination = isset($issuePagination) && is_array($issuePagination) ? $issuePagination : [];
$redemptionPagination = isset($redemptionPagination) && is_array($redemptionPagination) ? $redemptionPagination : [];
$claimCampaigns = isset($claimCampaigns) && is_array($claimCampaigns) ? $claimCampaigns : [];
$claimLogs = isset($claimLogs) && is_array($claimLogs) ? $claimLogs : [];
$claimCampaignDefinitionOptions = isset($claimCampaignDefinitionOptions) && is_array($claimCampaignDefinitionOptions) ? $claimCampaignDefinitionOptions : [];
$editClaimCampaign = isset($editClaimCampaign) && is_array($editClaimCampaign) ? $editClaimCampaign : null;
$editingClaimCampaign = $editClaimCampaign !== null;
$claimCampaignForm = $editingClaimCampaign ? $editClaimCampaign : [];
$claimCampaignFormSurfaces = sr_coupon_claim_surfaces_from_value($claimCampaignForm['exposure_surfaces_json'] ?? ['coupon_zone']);
$claimCampaignAssetOptions = isset($claimCampaignAssetOptions) && is_array($claimCampaignAssetOptions) ? $claimCampaignAssetOptions : [];
$claimCampaignAllowedAssets = sr_coupon_asset_module_keys_from_value($pdo, $claimCampaignForm['allowed_asset_modules_json'] ?? []);
if ($claimCampaignFormSurfaces === []) {
    $claimCampaignFormSurfaces = ['coupon_zone'];
}
$claimCampaignFormDateTime = static function (?string $value): string {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d\TH:i', $timestamp);
};
$issueAccountFilter = is_array($issueFilters['account'] ?? null) ? $issueFilters['account'] : ['field' => 'all', 'keyword' => ''];
$redemptionAccountFilter = is_array($redemptionFilters['account'] ?? null) ? $redemptionFilters['account'] : ['field' => 'all', 'keyword' => ''];
$selectedDefinitionStatuses = is_array($definitionFilters['status'] ?? null) ? $definitionFilters['status'] : [];
$selectedDefinitionTargetTypes = is_array($definitionFilters['target_type'] ?? null) ? $definitionFilters['target_type'] : [];
$selectedIssueStatuses = is_array($issueFilters['status'] ?? null) ? $issueFilters['status'] : [];
$selectedIssueTargetTypes = is_array($issueFilters['target_type'] ?? null) ? $issueFilters['target_type'] : [];
$selectedRedemptionStatuses = is_array($redemptionFilters['status'] ?? null) ? $redemptionFilters['status'] : [];
$selectedRedemptionPolicies = is_array($redemptionFilters['refundable_policy'] ?? null) ? $redemptionFilters['refundable_policy'] : [];
$selectedRedemptionTargetTypes = is_array($redemptionFilters['target_type'] ?? null) ? $redemptionFilters['target_type'] : [];
$couponAccountSearchFields = [
    'all' => '전체',
    'hash' => '공개 해시',
    'email' => '이메일',
    'login_id' => '로그인 ID',
    'name' => '이름',
];
$couponCreateModalId = 'coupon-create-modal';
$couponCreateModalOpen = isset($couponCreateModalOpen) && $couponCreateModalOpen === true;
$couponCreateModalClass = $couponCreateModalOpen
    ? 'modal-overlay modal-overlay-fade overlay overlay-open'
    : 'modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0';
$couponDefinitionReferenceModals = '';
$couponIssueModalOpenDefinitionId = isset($couponIssueModalOpenDefinitionId) ? (int) $couponIssueModalOpenDefinitionId : 0;
$couponTargetLookupModalId = 'coupon-target-lookup-modal';
$couponTargetLookupResultsId = 'coupon-target-lookup-results';
$couponInitialTargetType = (string) array_key_first($targetTypes);
$couponTargetSearchEnabled = $couponInitialTargetType !== 'all' && array_key_exists($couponInitialTargetType, $couponSearchableTargetTypes);
$couponMemberLookupModalId = 'coupon-member-lookup-modal';
$couponMemberLookupResultsId = 'coupon-member-lookup-results';
$couponDefinitionHasSearch = $selectedDefinitionStatuses !== [] || $selectedDefinitionTargetTypes !== [] || trim((string) ($definitionFilters['q'] ?? '')) !== '';
$couponIssueHasSearch = $selectedIssueStatuses !== []
    || $selectedIssueTargetTypes !== []
    || trim((string) ($issueFilters['coupon_q'] ?? '')) !== ''
    || trim((string) ($issueAccountFilter['keyword'] ?? '')) !== '';
$couponRedemptionHasSearch = $selectedRedemptionStatuses !== []
    || $selectedRedemptionPolicies !== []
    || $selectedRedemptionTargetTypes !== []
    || trim((string) ($redemptionFilters['coupon_q'] ?? '')) !== ''
    || trim((string) ($redemptionAccountFilter['keyword'] ?? '')) !== '';
$adminPageTitleUrl = sr_admin_page_title_reset_url($couponAdminPage === 'definitions', '/admin/coupons')
    . sr_admin_page_title_reset_url($couponAdminPage === 'issues', '/admin/coupons/issues')
    . sr_admin_page_title_reset_url($couponAdminPage === 'redemptions', '/admin/coupons/redemptions')
    . sr_admin_page_title_reset_url($couponAdminPage === 'campaigns', '/admin/coupons/campaigns');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($couponAdminPage === 'campaigns') { ?>
<section class="admin-card">
    <div class="admin-card-header">
        <h2><?php echo $editingClaimCampaign ? '발급 캠페인 수정' : '발급 캠페인 추가'; ?></h2>
    </div>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/campaigns')); ?>" class="admin-form admin-card-body">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="<?php echo $editingClaimCampaign ? 'update_campaign' : 'create_campaign'; ?>">
        <?php if ($editingClaimCampaign) { ?>
            <input type="hidden" name="campaign_id" value="<?php echo sr_e((string) (int) ($claimCampaignForm['id'] ?? 0)); ?>">
            <input type="hidden" name="return_to" value="<?php echo sr_e('/admin/coupons/campaigns?edit_campaign_id=' . (string) (int) ($claimCampaignForm['id'] ?? 0)); ?>">
        <?php } ?>
        <div class="admin-form-grid">
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_key">캠페인 key <span class="sr-required-label">(필수)</span></label>
                <input id="coupon_claim_campaign_key" type="text" name="campaign_key" value="<?php echo sr_e((string) ($claimCampaignForm['campaign_key'] ?? '')); ?>" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocomplete="off" data-admin-key-input required>
                <?php if ($editingClaimCampaign) { ?>
                    <p class="form-help">발급 로그가 생긴 캠페인의 key는 변경할 수 없습니다.</p>
                <?php } ?>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_definition_id">연결 쿠폰 <span class="sr-required-label">(필수)</span></label>
                <select id="coupon_claim_campaign_definition_id" name="coupon_definition_id" class="form-select" required>
                    <option value="">선택</option>
                    <?php foreach ($claimCampaignDefinitionOptions as $definition) { ?>
                        <?php $definitionId = (int) ($definition['id'] ?? 0); ?>
                        <option value="<?php echo sr_e((string) $definitionId); ?>"<?php echo (int) ($claimCampaignForm['coupon_definition_id'] ?? 0) === $definitionId ? ' selected' : ''; ?>><?php echo sr_e((string) ($definition['title'] ?? '')); ?> (<?php echo sr_e((string) ($definition['coupon_key'] ?? '')); ?>)</option>
                    <?php } ?>
                </select>
                <?php if ($editingClaimCampaign) { ?>
                    <p class="form-help">발급 로그가 생긴 캠페인의 연결 쿠폰은 변경할 수 없습니다.</p>
                <?php } ?>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_title">제목 <span class="sr-required-label">(필수)</span></label>
                <input id="coupon_claim_campaign_title" type="text" name="title" value="<?php echo sr_e((string) ($claimCampaignForm['title'] ?? '')); ?>" class="form-input" maxlength="120" required>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_status">상태 <span class="sr-required-label">(필수)</span></label>
                <select id="coupon_claim_campaign_status" name="status" class="form-select" required>
                    <?php foreach (sr_coupon_claim_campaign_statuses() as $statusOption) { ?>
                        <option value="<?php echo sr_e($statusOption); ?>"<?php echo (string) ($claimCampaignForm['status'] ?? 'draft') === $statusOption ? ' selected' : ''; ?>><?php echo sr_e(sr_coupon_claim_campaign_status_label($statusOption)); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_claim_type">발급 유형 <span class="sr-required-label">(필수)</span></label>
                <select id="coupon_claim_campaign_claim_type" name="claim_type" class="form-select" required>
                    <?php foreach (sr_coupon_claim_types() as $claimTypeOption) { ?>
                        <option value="<?php echo sr_e($claimTypeOption); ?>"<?php echo (string) ($claimCampaignForm['claim_type'] ?? 'free') === $claimTypeOption ? ' selected' : ''; ?>><?php echo sr_e(sr_coupon_claim_type_label($claimTypeOption)); ?></option>
                    <?php } ?>
                </select>
                <?php if ($editingClaimCampaign) { ?>
                    <p class="form-help">발급 로그가 생긴 캠페인의 발급 유형은 변경할 수 없습니다.</p>
                <?php } ?>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_price_amount">유료 발급 가격</label>
                <input id="coupon_claim_campaign_price_amount" type="number" name="price_amount" value="<?php echo isset($claimCampaignForm['price_amount']) && $claimCampaignForm['price_amount'] !== null ? sr_e((string) (int) $claimCampaignForm['price_amount']) : ''; ?>" class="form-input" min="1" max="999999999" step="1">
                <p class="form-help">발급 유형이 유료일 때 필수입니다.</p>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_price_currency_code">유료 발급 통화</label>
                <input id="coupon_claim_campaign_price_currency_code" type="text" name="price_currency_code" value="<?php echo sr_e((string) ($claimCampaignForm['price_currency_code'] ?? 'KRW')); ?>" class="form-input" maxlength="3" pattern="[A-Za-z]{3}" inputmode="latin" autocomplete="off">
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_visibility">공개 여부 <span class="sr-required-label">(필수)</span></label>
                <select id="coupon_claim_campaign_visibility" name="visibility" class="form-select" required>
                    <option value="hidden"<?php echo (string) ($claimCampaignForm['visibility'] ?? 'hidden') === 'hidden' ? ' selected' : ''; ?>>숨김</option>
                    <option value="public"<?php echo (string) ($claimCampaignForm['visibility'] ?? 'hidden') === 'public' ? ' selected' : ''; ?>>공개</option>
                </select>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_total_limit">총 발급 한도</label>
                <input id="coupon_claim_campaign_total_limit" type="number" name="total_claim_limit" value="<?php echo isset($claimCampaignForm['total_claim_limit']) && $claimCampaignForm['total_claim_limit'] !== null ? sr_e((string) (int) $claimCampaignForm['total_claim_limit']) : ''; ?>" class="form-input" min="1" max="999999999" step="1">
                <p class="form-help">비워 두면 수량 제한 없이 발급합니다.</p>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_per_account_limit">회원당 발급 한도 <span class="sr-required-label">(필수)</span></label>
                <input id="coupon_claim_campaign_per_account_limit" type="number" name="per_account_limit" class="form-input" min="1" max="1000" step="1" value="<?php echo sr_e((string) (int) ($claimCampaignForm['per_account_limit'] ?? 1)); ?>" required>
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_issue_expires_in_days">발급본 만료일수</label>
                <input id="coupon_claim_campaign_issue_expires_in_days" type="number" name="issue_expires_in_days" value="<?php echo isset($claimCampaignForm['issue_expires_in_days']) && $claimCampaignForm['issue_expires_in_days'] !== null ? sr_e((string) (int) $claimCampaignForm['issue_expires_in_days']) : ''; ?>" class="form-input" min="1" max="3650" step="1">
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_starts_at">발급 시작</label>
                <input id="coupon_claim_campaign_starts_at" type="datetime-local" name="starts_at" value="<?php echo sr_e($claimCampaignFormDateTime((string) ($claimCampaignForm['starts_at'] ?? ''))); ?>" class="form-input">
            </div>
            <div class="admin-form-field">
                <label class="form-label" for="coupon_claim_campaign_ends_at">발급 종료</label>
                <input id="coupon_claim_campaign_ends_at" type="datetime-local" name="ends_at" value="<?php echo sr_e($claimCampaignFormDateTime((string) ($claimCampaignForm['ends_at'] ?? ''))); ?>" class="form-input">
            </div>
        </div>
        <div class="admin-form-field">
            <span class="form-label">유료 발급 허용 포인트/금액 항목</span>
            <?php if ($claimCampaignAssetOptions === []) { ?>
                <p class="form-help">사용 가능한 포인트/금액 항목이 없습니다.</p>
            <?php } else { ?>
                <?php foreach ($claimCampaignAssetOptions as $assetModule => $assetOption) { ?>
                    <label><input type="checkbox" name="allowed_asset_modules[]" value="<?php echo sr_e((string) $assetModule); ?>"<?php echo in_array((string) $assetModule, $claimCampaignAllowedAssets, true) ? ' checked' : ''; ?>> <?php echo sr_e((string) ($assetOption['label'] ?? $assetModule)); ?></label>
                <?php } ?>
                <p class="form-help">회원 선택은 강제 제약으로 처리합니다. 선택한 항목으로 부족하면 발급 전 실패합니다.</p>
            <?php } ?>
        </div>
        <div class="admin-form-field">
            <span class="form-label">노출 위치 <span class="sr-required-label">(필수)</span></span>
            <label><input type="checkbox" name="exposure_surfaces[]" value="coupon_zone"<?php echo in_array('coupon_zone', $claimCampaignFormSurfaces, true) ? ' checked' : ''; ?>> 쿠폰존</label>
            <label><input type="checkbox" name="exposure_surfaces[]" value="content_embed"<?php echo in_array('content_embed', $claimCampaignFormSurfaces, true) ? ' checked' : ''; ?>> 본문 임베드</label>
            <label><input type="checkbox" name="exposure_surfaces[]" value="popup_layer"<?php echo in_array('popup_layer', $claimCampaignFormSurfaces, true) ? ' checked' : ''; ?>> 팝업레이어</label>
        </div>
        <div class="admin-form-field">
            <label><input type="checkbox" name="login_required" value="1"<?php echo $editingClaimCampaign ? ((int) ($claimCampaignForm['login_required'] ?? 1) === 1 ? ' checked' : '') : ' checked'; ?>> 로그인 필요</label>
        </div>
        <div class="admin-form-field">
            <label class="form-label" for="coupon_claim_campaign_description">설명</label>
            <textarea id="coupon_claim_campaign_description" name="description" class="form-textarea" rows="3" maxlength="1000"><?php echo sr_e((string) ($claimCampaignForm['description'] ?? '')); ?></textarea>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-primary"><?php echo $editingClaimCampaign ? '수정 저장' : '저장'; ?></button>
            <?php if ($editingClaimCampaign) { ?>
                <a class="btn btn-secondary" href="<?php echo sr_e(sr_url('/admin/coupons/campaigns')); ?>">취소</a>
            <?php } ?>
        </div>
    </form>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>발급 캠페인 목록</h2>
    </div>
    <div class="admin-card-body">
        <?php if ($claimCampaigns === []) { ?>
            <p>등록된 발급 캠페인이 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
                <table class="table table-list">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>제목</th>
                            <th>쿠폰</th>
                            <th>유형</th>
                            <th>상태</th>
                            <th>공개</th>
                            <th>한도</th>
                            <th>수정</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claimCampaigns as $campaign) { ?>
                            <tr>
                                <td><?php echo sr_e((string) ($campaign['campaign_key'] ?? '')); ?></td>
                                <td><?php echo sr_e((string) ($campaign['title'] ?? '')); ?></td>
                                <td><?php echo sr_e((string) ($campaign['coupon_title'] ?? '')); ?></td>
                                <td>
                                    <?php echo sr_e(sr_coupon_claim_type_label((string) ($campaign['claim_type'] ?? 'free'))); ?>
                                    <?php if ((string) ($campaign['claim_type'] ?? 'free') === 'paid') { ?>
                                        <br><small><?php echo sr_e(number_format((int) ($campaign['price_amount'] ?? 0)) . ' ' . (string) ($campaign['price_currency_code'] ?? '')); ?> · <?php echo sr_e(sr_coupon_asset_module_labels($pdo, $campaign['allowed_asset_modules_json'] ?? '')); ?></small>
                                    <?php } ?>
                                </td>
                                <td><?php echo sr_e(sr_coupon_claim_campaign_status_label((string) ($campaign['status'] ?? ''))); ?></td>
                                <td><?php echo sr_e((string) ($campaign['visibility'] ?? '') === 'public' ? '공개' : '숨김'); ?></td>
                                <td><?php echo (int) ($campaign['total_claim_limit'] ?? 0) > 0 ? sr_e(number_format((int) $campaign['total_claim_limit'])) : sr_e('제한 없음'); ?> / <?php echo sr_e('회원당 ' . number_format((int) ($campaign['per_account_limit'] ?? 1))); ?></td>
                                <td><a class="btn btn-secondary btn-sm" href="<?php echo sr_e(sr_url('/admin/coupons/campaigns?edit_campaign_id=' . (string) (int) ($campaign['id'] ?? 0))); ?>">수정</a></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>

<section class="admin-card">
    <div class="admin-card-header">
        <h2>최근 발급 로그</h2>
    </div>
    <div class="admin-card-body">
        <?php if ($claimLogs === []) { ?>
            <p>아직 발급 로그가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
                <table class="table table-list">
                    <thead>
                        <tr>
                            <th>캠페인</th>
                            <th>쿠폰</th>
                            <th>회원</th>
                            <th>상태</th>
                            <th>발급 표면</th>
                            <th>발급본</th>
                            <th>예약 만료</th>
                            <th>생성</th>
                            <th>실패 사유</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($claimLogs as $log) { ?>
                            <?php
                            $displayStatus = (string) ($log['display_status'] ?? $log['status'] ?? '');
                            $accountLabel = (string) ($log['account_display_name'] ?? '');
                            if ($accountLabel === '') {
                                $accountLabel = (string) ($log['account_login_id'] ?? '');
                            }
                            if ($accountLabel === '') {
                                $accountLabel = (string) ($log['account_email'] ?? '');
                            }
                            if ($accountLabel === '') {
                                $accountLabel = '#' . (string) (int) ($log['account_id'] ?? 0);
                            }
                            $failureText = trim((string) ($log['failure_code'] ?? '') . ' ' . (string) ($log['failure_message'] ?? ''));
                            ?>
                            <tr>
                                <td>
                                    <?php echo sr_e((string) ($log['campaign_title'] ?? '')); ?>
                                    <br><small><?php echo sr_e((string) ($log['campaign_key'] ?? '')); ?></small>
                                </td>
                                <td>
                                    <?php echo sr_e((string) ($log['coupon_title'] ?? '')); ?>
                                    <br><small><?php echo sr_e((string) ($log['coupon_key'] ?? '')); ?></small>
                                </td>
                                <td><?php echo sr_e($accountLabel); ?></td>
                                <td><span class="coupon-status <?php echo sr_e((string) ($claimLogStatusClasses[$displayStatus] ?? '')); ?>"><?php echo sr_e(sr_coupon_claim_log_status_label($displayStatus)); ?></span></td>
                                <td><?php echo sr_e(sr_coupon_claim_source_label((string) ($log['claim_source'] ?? ''))); ?></td>
                                <td><?php echo (int) ($log['coupon_issue_id'] ?? 0) > 0 ? '#' . sr_e((string) (int) $log['coupon_issue_id']) : sr_e('-'); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_coupon_time_html((string) ($log['reserved_until'] ?? ''), '-'); ?></td>
                                <td class="admin-table-nowrap"><?php echo sr_coupon_time_html((string) ($log['created_at'] ?? ''), '-'); ?></td>
                                <td><?php echo $failureText !== '' ? sr_e($failureText) : sr_e('-'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</section>
<?php } elseif ($couponAdminPage === 'definitions') { ?>
<?php $couponDefinitionDetailFilterOpen = $selectedDefinitionStatuses !== [] || $selectedDefinitionTargetTypes !== []; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="filtering-form admin-coupon-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $couponDefinitionDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-coupon-definition-filter-grid">
            <div class="filtering-field filtering-field-fill admin-coupon-filter-keyword">
                <label for="coupon_definition_keyword_filter" class="filtering-label">검색어</label>
                <input id="coupon_definition_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($definitionFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="쿠폰 키, 이름, 대상 번호">
            </div>
        </div>
        <div id="coupon_definition_detail_filters" class="filtering-body" data-filtering-body<?php echo $couponDefinitionDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('coupon_definition_status_filter', 'status', $definitionStatusLabels, $selectedDefinitionStatuses, '전체'); ?>
            </div>
            <div class="filtering-field">
                <label for="coupon_definition_target_type_filter" class="filtering-label">사용처</label>
                <select id="coupon_definition_target_type_filter" name="target_type" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($couponHistoryTargetTypes as $targetType => $targetTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $targetType); ?>"<?php echo in_array((string) $targetType, $selectedDefinitionTargetTypes, true) ? ' selected' : ''; ?>><?php echo sr_e((string) $targetTypeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $couponDefinitionDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="coupon_definition_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <div>
            <h2 class="card-title">쿠폰 종류</h2>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponCreateModalId); ?>" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">쿠폰 추가</button>
    </div>
    <div class="admin-list-summary-row admin-coupon-definition-bulk-row">
        <?php echo sr_admin_pagination_summary_html($definitionPagination); ?>
        <?php if (empty($definitionSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="쿠폰 종류 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <form id="coupon-definition-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="admin-coupon-definition-bulk-form" data-coupon-definition-bulk-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="batch_definition_status">
            <input type="hidden" name="operation_key" value="coupon.definition_set_status">
            <input type="hidden" name="return_to" value="<?php echo sr_e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/coupons')); ?>">
            <div class="admin-coupon-definition-bulk-actions admin-row-actions" data-coupon-definition-bulk-bar>
                <div class="admin-coupon-definition-bulk-controls admin-row-actions">
                    <button type="submit" name="target_status" value="active" class="btn btn-sm btn-outline-warning" data-coupon-definition-bulk-submit data-status-label="사용 중" disabled>사용 중</button>
                    <button type="submit" name="target_status" value="disabled" class="btn btn-sm btn-outline-warning" data-coupon-definition-bulk-submit data-status-label="중지" disabled>중지</button>
                    <button type="button" class="btn btn-sm btn-outline-light" data-coupon-definition-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-coupon-definition-selected-count>0</span></button>
                </div>
            </div>
        </form>
    </div>
    <div class="table-wrapper">
    <table class="table table-list admin-coupon-definition-table">
        <thead>
            <tr>
                <th class="admin-table-checkbox-cell admin-coupon-definition-select-cell">
                    <label class="sr-only" for="coupon_definition_bulk_select_all">현재 페이지 쿠폰 종류 전체 선택</label>
                    <input id="coupon_definition_bulk_select_all" type="checkbox" class="form-checkbox" data-coupon-definition-select-all<?php echo $definitions === [] ? ' disabled' : ''; ?>>
                </th>
                <th<?php echo sr_admin_sort_aria('coupon_key', $definitionSort); ?>><?php echo sr_admin_sort_header_html('Key', 'coupon_key', $definitionSort, sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('title', $definitionSort); ?>><?php echo sr_admin_sort_header_html('쿠폰 이름', 'title', $definitionSort, sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('target_type', $definitionSort); ?>><?php echo sr_admin_sort_header_html('사용처', 'target_type', $definitionSort, sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('status', $definitionSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $definitionSort, sr_coupon_admin_definition_sort_options(), sr_coupon_admin_definition_default_sort()); ?></th>
                <th class="text-end">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($definitions === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state">등록된 쿠폰 종류가 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($definitions as $definition) { ?>
                    <tr>
                        <td class="admin-table-checkbox-cell admin-coupon-definition-select-cell">
                            <label class="sr-only" for="coupon_definition_bulk_select_<?php echo sr_e((string) (int) $definition['id']); ?>"><?php echo sr_e((string) $definition['title']); ?> 선택</label>
                            <input id="coupon_definition_bulk_select_<?php echo sr_e((string) (int) $definition['id']); ?>" type="checkbox" name="selected_definition_ids[]" value="<?php echo sr_e((string) (int) $definition['id']); ?>" class="form-checkbox" form="coupon-definition-bulk-status-form" data-coupon-definition-row-select>
                        </td>
                        <td><?php echo sr_e((string) $definition['coupon_key']); ?></td>
                        <td><?php echo sr_e((string) $definition['title']); ?></td>
                        <td><?php echo sr_e(sr_coupon_target_display((string) $definition['target_type'], (string) $definition['target_id'], $pdo)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e((string) ($definitionStatusClasses[(string) $definition['status']] ?? 'is-blocked')); ?>"><?php echo sr_e((string) ($definitionStatusLabels[(string) $definition['status']] ?? sr_coupon_status_label((string) $definition['status']))); ?></span></td>
                        <td class="admin-table-actions-cell">
                            <?php
                            $definitionId = (int) ($definition['id'] ?? 0);
                            $issueModalId = 'coupon-issue-modal-' . $definitionId;
                            $definitionReferenceModalId = 'coupon-definition-reference-modal-' . $definitionId;
                            $definitionReferenceResult = $couponDefinitionReadReferencesById[$definitionId] ?? ['rows' => [], 'errors' => []];
                            $couponDefinitionReferenceModals .= sr_admin_read_reference_modal_html($definitionReferenceModalId, '쿠폰 정의 참조 현황', $definitionReferenceResult);
                            ?>
                            <div class="admin-row-actions">
                                <?php echo sr_admin_read_reference_button_html($definitionReferenceModalId, $definitionReferenceResult); ?>
                                <?php if ((string) $definition['status'] === 'active') { ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($issueModalId); ?>" data-overlay="#<?php echo sr_e($issueModalId); ?>">지급하기</button>
                                <?php } ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="set_definition_status">
                                    <input type="hidden" name="definition_id" value="<?php echo sr_e((string) $definition['id']); ?>">
                                    <input type="hidden" name="status" value="<?php echo (string) $definition['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                    <button type="submit" class="btn btn-sm btn-solid-light"><?php echo sr_e((string) $definition['status'] === 'active' ? '사용 중지' : '다시 사용'); ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
	</table>
	</div>
<?php echo sr_admin_pagination_html($definitionPagination, '쿠폰 종류 목록 페이지'); ?>
<div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
    <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('travel_explore'); ?> 참조 현황</span>
</div>
<?php echo sr_admin_status_description_list_html('coupon_definition_status', $definitionStatusLabels); ?>
</section>

<?php echo $couponDefinitionReferenceModals; ?>

<script>
(function () {
    var form = document.querySelector('[data-coupon-definition-bulk-form]');
    if (!form) {
        return;
    }
    var countNode = document.querySelector('[data-coupon-definition-selected-count]');
    var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-coupon-definition-bulk-submit]'));
    var clear = document.querySelector('[data-coupon-definition-bulk-clear]');
    var selectAll = document.querySelector('[data-coupon-definition-select-all]');
    var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-coupon-definition-row-select]'));

    function checkedRows() {
        return rowChecks.filter(function (input) {
            return input.checked && !input.disabled;
        });
    }

    function syncBulkState() {
        var selectedCount = checkedRows().length;
        if (countNode) {
            countNode.textContent = String(selectedCount);
        }
        submitButtons.forEach(function (button) {
            button.disabled = selectedCount < 1;
        });
        if (clear) {
            clear.hidden = selectedCount < 1;
        }
        if (selectAll) {
            selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks.forEach(function (input) {
                if (!input.disabled) {
                    input.checked = selectAll.checked;
                }
            });
            syncBulkState();
        });
    }
    rowChecks.forEach(function (input) {
        input.addEventListener('change', syncBulkState);
    });
    if (clear) {
        clear.addEventListener('click', function () {
            rowChecks.forEach(function (input) {
                input.checked = false;
            });
            syncBulkState();
        });
    }
    form.addEventListener('submit', function (event) {
        var selectedCount = checkedRows().length;
        if (selectedCount < 1) {
            event.preventDefault();
            syncBulkState();
            return;
        }
        var submitter = event.submitter || document.activeElement;
        var statusLabel = submitter && submitter.getAttribute ? submitter.getAttribute('data-status-label') : '';
        if (!statusLabel) {
            statusLabel = submitter && submitter.textContent ? submitter.textContent.replace(/\s+/g, ' ').trim() : '선택한 상태';
        }
        if (!window.confirm('선택한 쿠폰 종류 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
            event.preventDefault();
        }
    });
    syncBulkState();
}());
</script>

<?php foreach ($definitions as $definition) { ?>
    <?php
    $definitionId = (int) ($definition['id'] ?? 0);
    $issueModalId = 'coupon-issue-modal-' . $definitionId;
    $issueModalOpen = $couponIssueModalOpenDefinitionId === $definitionId;
    $issueModalClass = $issueModalOpen
        ? 'modal-overlay modal-overlay-fade overlay overlay-open'
        : 'modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0';
    ?>
    <div id="<?php echo sr_e($issueModalId); ?>" class="<?php echo sr_e($issueModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($issueModalId); ?>_title" aria-hidden="<?php echo $issueModalOpen ? 'false' : 'true'; ?>"<?php echo $issueModalOpen ? '' : ' inert'; ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="issue_coupon">
                <input type="hidden" name="coupon_definition_id" value="<?php echo sr_e((string) $definitionId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($issueModalId); ?>_title" class="modal-title">지급하기</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($issueModalId); ?>">
                        <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label class="form-label" for="coupon_admin_issue_mode_<?php echo sr_e((string) $definitionId); ?>">지급 대상 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <select id="coupon_admin_issue_mode_<?php echo sr_e((string) $definitionId); ?>" name="issue_target_mode" class="form-select" data-coupon-issue-mode required data-validation-message="지급 대상을 선택해 주세요.">
                                <option value="member">회원</option>
                                <option value="all">전체</option>
                                <option value="group"<?php echo $memberGroups === [] ? ' disabled' : ''; ?>>그룹</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row" data-coupon-issue-member-row>
                        <label class="form-label" for="coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>">회원 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field" data-coupon-issue-member-field>
                            <div class="admin-lookup-control">
                                <input id="coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>" type="text" name="account_identifier" class="form-control form-input" maxlength="80" data-overlay-focus required data-validation-message="지급할 회원을 입력해 주세요.">
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>" data-overlay-stack="true" data-admin-member-lookup-open data-target="#coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>">회원 검색</button>
                            </div>
                            <p class="form-help">회원 관리 화면의 공개 해시를 입력하거나 회원 검색으로 대상을 선택합니다.</p>
                        </div>
                    </div>
                    <div class="form-row" data-coupon-issue-group-row hidden>
                        <label class="form-label" for="coupon_admin_issue_group_<?php echo sr_e((string) $definitionId); ?>">그룹 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <select id="coupon_admin_issue_group_<?php echo sr_e((string) $definitionId); ?>" name="group_key" class="form-select" data-coupon-issue-group disabled data-validation-message="지급할 그룹을 선택해 주세요.">
                                <option value="">그룹 선택</option>
                                <?php foreach ($memberGroups as $memberGroup) { ?>
                                    <option value="<?php echo sr_e((string) ($memberGroup['group_key'] ?? '')); ?>"><?php echo sr_e((string) ($memberGroup['title'] ?? '')); ?> (<?php echo sr_e((string) ($memberGroup['active_member_count'] ?? '0')); ?>명)</option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="coupon_admin_issue_reason_<?php echo sr_e((string) $definitionId); ?>">지급 사유</label>
                        <div class="form-field">
                            <input id="coupon_admin_issue_reason_<?php echo sr_e((string) $definitionId); ?>" type="text" name="issued_reason" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($issueModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">쿠폰 지급</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<div id="<?php echo sr_e($couponMemberLookupModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($couponMemberLookupModalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
    <div class="modal-dialog admin-lookup-dialog">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($couponMemberLookupModalId); ?>_title" class="modal-title">회원 검색</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="admin-lookup-search-form" data-admin-member-search-form data-endpoint="<?php echo sr_e(sr_url('/admin/coupons/member-search')); ?>" data-target="" data-results="#<?php echo sr_e($couponMemberLookupResultsId); ?>">
                    <select name="field" class="form-select" aria-label="회원 검색 조건">
                        <option value="all">전체</option>
                        <option value="hash">공개 해시</option>
                        <option value="email">이메일</option>
                        <option value="login_id">로그인 ID</option>
                        <option value="name">이름</option>
                    </select>
                    <input type="text" name="q" maxlength="120" class="form-input" placeholder="이메일, 로그인 ID, 이름" data-overlay-focus>
                    <button type="submit" class="btn btn-solid-primary">검색</button>
                </form>
                <div id="<?php echo sr_e($couponMemberLookupResultsId); ?>" class="admin-lookup-results">
                    <p class="admin-empty-state admin-lookup-empty">검색어 없이 검색하면 최근 회원이 표시됩니다.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>">닫기</button>
            </div>
        </div>
    </div>
</div>

<div id="<?php echo sr_e($couponCreateModalId); ?>" class="<?php echo sr_e($couponCreateModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="coupon_create_modal_title" aria-hidden="<?php echo $couponCreateModalOpen ? 'false' : 'true'; ?>"<?php echo $couponCreateModalOpen ? '' : ' inert'; ?>>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create_definition">
            <div class="modal-header">
                <h3 id="coupon_create_modal_title" class="modal-title">쿠폰 추가</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_coupon_key">Key <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="coupon_admin_coupon_key" type="text" name="coupon_key" class="form-control" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" data-admin-key-input data-admin-key-suggest-source="#coupon_admin_title" data-admin-key-suggest-fallback="coupon" data-overlay-focus required data-validation-message="영문 소문자로 시작하고 소문자, 숫자, 밑줄만 입력해 주세요.">
                        <p class="form-help">관리자가 구분하기 위한 고유값입니다. 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_title">쿠폰 이름 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="coupon_admin_title" type="text" name="title" class="form-control" maxlength="120" required data-validation-message="쿠폰 이름을 입력해 주세요.">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_description">설명</label>
                    <div class="form-field">
                        <textarea id="coupon_admin_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_target_type">사용처 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="coupon_admin_target_type" name="target_type" class="form-select" required data-validation-message="사용처를 선택해 주세요.">
                            <?php foreach ($targetTypes as $targetType => $targetTypeLabel) { ?>
                                <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_target_id">대상 번호</label>
                    <div class="form-field">
                        <div class="admin-lookup-control">
                            <input id="coupon_admin_target_id" type="text" name="target_id" class="form-control form-input" maxlength="80">
                            <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponTargetLookupModalId); ?>" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>" data-overlay-stack="true" data-admin-reference-lookup-open data-coupon-target-search-button data-type-target="#coupon_admin_target_type" data-id-target="#coupon_admin_target_id"<?php echo $couponTargetSearchEnabled ? '' : ' disabled hidden'; ?>>검색</button>
                        </div>
                        <p class="form-help">특정 콘텐츠나 게시글에만 쓰게 할 때 해당 번호를 입력합니다. 비워 두면 선택한 사용처 전체에 사용할 수 있습니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_refundable_policy">환급 정책 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="coupon_admin_refundable_policy" name="refundable_policy" class="form-select" required data-validation-message="환급 정책을 선택해 주세요.">
                            <?php foreach ($refundablePolicies as $policy => $policyLabel) { ?>
                                <option value="<?php echo sr_e((string) $policy); ?>"><?php echo sr_e((string) $policyLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="coupon_admin_max_uses">사용 횟수 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="coupon_admin_max_uses" type="number" name="max_uses_per_issue" class="form-control" min="1" max="1000" value="1" required data-validation-message="사용 횟수는 1부터 1000 사이로 입력해 주세요.">
                        <p class="form-help">발급된 쿠폰 1장이 몇 번까지 사용할 수 있는지 정합니다.</p>
                    </div>
                </div>
                <input type="hidden" name="status" value="active">
                <input type="hidden" name="coupon_type" value="access">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">닫기</button>
                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
            </div>
        </form>
    </div>
</div>

<div id="<?php echo sr_e($couponTargetLookupModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($couponTargetLookupModalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
    <div class="modal-dialog admin-lookup-dialog">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($couponTargetLookupModalId); ?>_title" class="modal-title">대상 검색</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="admin-lookup-search-form" data-admin-reference-search-form data-endpoint="<?php echo sr_e(sr_url('/admin/coupons/target-search')); ?>" data-type-target="#coupon_admin_target_type" data-id-target="#coupon_admin_target_id" data-results="#<?php echo sr_e($couponTargetLookupResultsId); ?>">
                    <select name="reference_type" class="form-select" aria-label="대상 유형">
                        <?php foreach ($couponSearchableTargetTypes as $targetType => $targetTypeLabel) { ?>
                            <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                        <?php } ?>
                    </select>
                    <input type="text" name="q" maxlength="120" class="form-input" placeholder="번호, 제목, Key" data-overlay-focus>
                    <button type="submit" class="btn btn-solid-primary">검색</button>
                </form>
                <div id="<?php echo sr_e($couponTargetLookupResultsId); ?>" class="admin-lookup-results">
                    <p class="admin-empty-state admin-lookup-empty">사용처를 선택하고 검색어 없이 검색하면 최근 대상이 표시됩니다.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var targetType = document.getElementById('coupon_admin_target_type');
    var searchButton = document.querySelector('[data-coupon-target-search-button]');
    var targetId = document.getElementById('coupon_admin_target_id');
    var searchableTypes = <?php echo sr_js_json_encode(array_keys($couponSearchableTargetTypes)); ?>;

    function syncTargetSearchButton() {
        if (!targetType || !searchButton) {
            return;
        }

        var searchable = searchableTypes.indexOf(targetType.value) !== -1;
        searchButton.disabled = !searchable;
        searchButton.hidden = !searchable;
        searchButton.classList.toggle('hidden', !searchable);
        if (!searchable && targetId) {
            targetId.value = '';
        }
    }

    if (targetType) {
        targetType.addEventListener('change', syncTargetSearchButton);
    }
    syncTargetSearchButton();

    function syncIssueTargetMode(select) {
        var modal = select.closest('.modal-content');
        if (!modal) {
            return;
        }

        var memberRow = modal.querySelector('[data-coupon-issue-member-row]');
        var memberInput = modal.querySelector('input[name="account_identifier"]');
        var groupRow = modal.querySelector('[data-coupon-issue-group-row]');
        var groupSelect = modal.querySelector('[data-coupon-issue-group]');
        var mode = select.value;

        if (memberRow) {
            memberRow.hidden = mode !== 'member';
        }
        if (memberInput) {
            memberInput.required = mode === 'member';
            memberInput.disabled = mode !== 'member';
        }
        if (groupRow) {
            groupRow.hidden = mode !== 'group';
        }
        if (groupSelect) {
            groupSelect.required = mode === 'group';
            groupSelect.disabled = mode !== 'group';
        }
    }

    document.querySelectorAll('[data-coupon-issue-mode]').forEach(function (select) {
        select.addEventListener('change', function () {
            syncIssueTargetMode(select);
        });
        syncIssueTargetMode(select);
    });
})();
</script>
<?php } ?>

<?php if ($couponAdminPage === 'issues') { ?>
<?php $couponIssueDetailFilterOpen = $selectedIssueStatuses !== [] || $selectedIssueTargetTypes !== [] || trim((string) ($issueFilters['coupon_q'] ?? '')) !== ''; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/coupons/issues')); ?>" class="filtering-form admin-coupon-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $couponIssueDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-coupon-history-filter-grid">
            <div class="filtering-field">
                <label for="coupon_issue_member_field_filter" class="filtering-label">회원 검색</label>
                <select id="coupon_issue_member_field_filter" name="field" class="form-select filtering-input">
                    <?php foreach ($couponAccountSearchFields as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e((string) $fieldValue); ?>"<?php echo (string) ($issueAccountFilter['field'] ?? 'all') === (string) $fieldValue ? ' selected' : ''; ?>><?php echo sr_e((string) $fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-coupon-filter-keyword">
                <label for="coupon_issue_member_keyword_filter" class="filtering-label">회원 검색어</label>
                <input id="coupon_issue_member_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($issueAccountFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="공개 해시, 이메일, 로그인 ID, 이름">
            </div>
        </div>
        <div id="coupon_issue_detail_filters" class="filtering-body" data-filtering-body<?php echo $couponIssueDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php
                $issueStatusOptions = sr_coupon_issue_statuses();
                $issueStatusLabels = [];
                foreach ($issueStatusOptions as $issueStatus) {
                    $issueStatusLabels[(string) $issueStatus] = sr_coupon_issue_status_label((string) $issueStatus);
                }
                echo sr_admin_filter_toggle_group_html('coupon_issue_status_filter', 'status', $issueStatusLabels, $selectedIssueStatuses, '전체');
                ?>
            </div>
            <div class="filtering-field">
                <label for="coupon_issue_target_type_filter" class="filtering-label">사용처</label>
                <select id="coupon_issue_target_type_filter" name="target_type" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($couponHistoryTargetTypes as $targetType => $targetTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $targetType); ?>"<?php echo in_array((string) $targetType, $selectedIssueTargetTypes, true) ? ' selected' : ''; ?>><?php echo sr_e((string) $targetTypeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field admin-coupon-filter-keyword">
                <label for="coupon_issue_keyword_filter" class="filtering-label">쿠폰 검색어</label>
                <input id="coupon_issue_keyword_filter" type="text" name="coupon_q" value="<?php echo sr_e((string) ($issueFilters['coupon_q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="쿠폰 키, 이름">
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $couponIssueDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="coupon_issue_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header"><h2 class="card-title">최근 지급 내역</h2></div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($issuePagination); ?>
        <?php if (empty($issueSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="쿠폰 지급 내역 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
    </div>
    <div class="table-wrapper">
    <table class="table table-list admin-coupon-issue-table">
        <thead>
            <tr>
                <th<?php echo sr_admin_sort_aria('member', $issueSort); ?>><?php echo sr_admin_sort_header_html('회원', 'member', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('coupon', $issueSort); ?>><?php echo sr_admin_sort_header_html('쿠폰', 'coupon', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('target_type', $issueSort); ?>><?php echo sr_admin_sort_header_html('사용처', 'target_type', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('status', $issueSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('used_count', $issueSort); ?>><?php echo sr_admin_sort_header_html('사용 횟수', 'used_count', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('issued_at', $issueSort); ?>><?php echo sr_admin_sort_header_html('지급일', 'issued_at', $issueSort, sr_coupon_admin_issue_sort_options(), sr_coupon_admin_issue_default_sort()); ?></th>
                <th class="text-end">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($issues === []) { ?>
                <tr>
                    <td colspan="7" class="admin-empty-state">최근 지급 내역이 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($issues as $issue) { ?>
                    <?php
                    $issueId = (int) ($issue['id'] ?? 0);
                    $canRefundPaidIssue = (string) ($issue['claim_type'] ?? '') === 'paid'
                        && (string) ($issue['status'] ?? '') === 'active'
                        && (int) ($issue['used_count'] ?? 0) === 0;
                    $paidIssueRefundModalId = 'coupon-paid-issue-refund-modal-' . (string) $issueId;
                    ?>
                    <tr>
                        <td><?php echo sr_e(sr_admin_member_display_name_preview($issue)); ?><br><?php echo sr_e(sr_admin_member_email_display($issue)); ?></td>
                        <td>
                            <?php echo sr_e((string) $issue['title']); ?><br><code><?php echo sr_e((string) $issue['coupon_key']); ?></code>
                            <?php if ((string) ($issue['claim_type'] ?? '') === 'paid') { ?>
                                <br><small><?php echo sr_e('유료 발급 ' . number_format((int) ($issue['nominal_price_amount'] ?? 0)) . ' ' . (string) ($issue['nominal_price_currency_code'] ?? '')); ?></small>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e(sr_coupon_target_display((string) ($issue['target_type'] ?? ''), (string) ($issue['target_id'] ?? ''), $pdo)); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e((string) ($issueStatusClasses[(string) $issue['status']] ?? 'is-blocked')); ?>"><?php echo sr_e(sr_coupon_issue_status_label((string) $issue['status'])); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) $issue['used_count']); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_coupon_time_html((string) $issue['issued_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <?php if ((string) $issue['status'] === 'active') { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/issues')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="set_issue_status">
                                        <input type="hidden" name="issue_id" value="<?php echo sr_e((string) $issue['id']); ?>">
                                        <input type="hidden" name="status" value="revoked">
                                        <button type="submit" class="btn btn-sm btn-solid-light">지급 취소</button>
                                    </form>
                                    <?php if ($canRefundPaidIssue) { ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($paidIssueRefundModalId); ?>" data-overlay="#<?php echo sr_e($paidIssueRefundModalId); ?>">발급 환불</button>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">-</span>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <?php echo sr_admin_status_description_list_html('coupon_issue_status', $issueStatusLabels); ?>
    <?php echo sr_admin_pagination_html($issuePagination, '쿠폰 지급 내역 목록 페이지'); ?>
</section>
<?php foreach (($issues ?? []) as $issue) { ?>
    <?php
    $issueId = (int) ($issue['id'] ?? 0);
    $canRefundPaidIssue = (string) ($issue['claim_type'] ?? '') === 'paid'
        && (string) ($issue['status'] ?? '') === 'active'
        && (int) ($issue['used_count'] ?? 0) === 0;
    if (!$canRefundPaidIssue) {
        continue;
    }
    $paidIssueRefundModalId = 'coupon-paid-issue-refund-modal-' . (string) $issueId;
    ?>
    <div id="<?php echo sr_e($paidIssueRefundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($paidIssueRefundModalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/issues')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="refund_paid_issue">
                <input type="hidden" name="issue_id" value="<?php echo sr_e((string) $issueId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($paidIssueRefundModalId); ?>_title" class="modal-title">유료 발급 환불</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($paidIssueRefundModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <span class="form-label">쿠폰</span>
                        <div class="form-field">
                            <?php echo sr_e((string) ($issue['title'] ?? '')); ?> #<?php echo sr_e((string) $issueId); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <span class="form-label">환불 금액</span>
                        <div class="form-field">
                            <?php echo sr_e(number_format((int) ($issue['nominal_price_amount'] ?? 0)) . ' ' . (string) ($issue['nominal_price_currency_code'] ?? '')); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="coupon_paid_issue_refund_note_<?php echo sr_e((string) $issueId); ?>">환불 사유 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="coupon_paid_issue_refund_note_<?php echo sr_e((string) $issueId); ?>" type="text" name="refund_note" class="form-input form-control-full" maxlength="255" required data-validation-message="환불 사유를 입력해 주세요." data-overlay-focus>
                            <p class="form-help">발급본은 환급 완료 상태가 되고, 원 자산 차감 거래가 환불 거래로 복원됩니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($paidIssueRefundModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">환불 실행</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>
<?php } ?>

<?php if ($couponAdminPage === 'redemptions') { ?>
<?php $couponRedemptionDetailFilterOpen = $selectedRedemptionStatuses !== [] || $selectedRedemptionPolicies !== [] || $selectedRedemptionTargetTypes !== [] || trim((string) ($redemptionFilters['coupon_q'] ?? '')) !== ''; ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/coupons/redemptions')); ?>" class="filtering-form admin-coupon-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $couponRedemptionDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields admin-coupon-redemption-filter-grid">
            <div class="filtering-field">
                <label for="coupon_redemption_member_field_filter" class="filtering-label">회원 검색</label>
                <select id="coupon_redemption_member_field_filter" name="field" class="form-select filtering-input">
                    <?php foreach ($couponAccountSearchFields as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e((string) $fieldValue); ?>"<?php echo (string) ($redemptionAccountFilter['field'] ?? 'all') === (string) $fieldValue ? ' selected' : ''; ?>><?php echo sr_e((string) $fieldLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field filtering-field-fill admin-coupon-filter-keyword">
                <label for="coupon_redemption_member_keyword_filter" class="filtering-label">회원 검색어</label>
                <input id="coupon_redemption_member_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($redemptionAccountFilter['keyword'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="공개 해시, 이메일, 로그인 ID, 이름">
            </div>
        </div>
        <div id="coupon_redemption_detail_filters" class="filtering-body" data-filtering-body<?php echo $couponRedemptionDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php
                $redemptionStatusOptions = ['redeemed', 'refunded'];
                $redemptionStatusLabels = [];
                foreach ($redemptionStatusOptions as $redemptionStatusOption) {
                    $redemptionStatusLabels[(string) $redemptionStatusOption] = sr_coupon_redemption_status_label((string) $redemptionStatusOption);
                }
                echo sr_admin_filter_radio_toggle_group_html('coupon_redemption_status_filter', 'status', $redemptionStatusLabels, $selectedRedemptionStatuses, '전체');
                ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">환급 정책</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('coupon_redemption_refundable_policy_filter', 'refundable_policy', $refundablePolicies, $selectedRedemptionPolicies, '전체'); ?>
            </div>
            <div class="filtering-field">
                <label for="coupon_redemption_target_type_filter" class="filtering-label">사용처</label>
                <select id="coupon_redemption_target_type_filter" name="target_type" class="form-select filtering-input">
                    <option value="">전체</option>
                    <?php foreach ($couponHistoryTargetTypes as $targetType => $targetTypeLabel) { ?>
                        <option value="<?php echo sr_e((string) $targetType); ?>"<?php echo in_array((string) $targetType, $selectedRedemptionTargetTypes, true) ? ' selected' : ''; ?>><?php echo sr_e((string) $targetTypeLabel); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field admin-coupon-filter-keyword">
                <label for="coupon_redemption_keyword_filter" class="filtering-label">쿠폰 검색어</label>
                <input id="coupon_redemption_keyword_filter" type="text" name="coupon_q" value="<?php echo sr_e((string) ($redemptionFilters['coupon_q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="쿠폰 키, 이름">
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $couponRedemptionDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="coupon_redemption_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header"><h2 class="card-title">최근 사용 내역</h2></div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($redemptionPagination); ?>
        <?php if (empty($redemptionSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url(sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort())); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="쿠폰 사용 내역 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
    </div>
    <div class="table-wrapper">
    <table class="table table-list admin-coupon-redemption-table">
        <thead>
            <tr>
                <th<?php echo sr_admin_sort_aria('member', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('회원', 'member', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('coupon', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('쿠폰', 'coupon', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('target_type', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('사용 대상', 'target_type', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th>가격 스냅샷</th>
                <th<?php echo sr_admin_sort_aria('status', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('redeemed_at', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('사용일', 'redeemed_at', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th<?php echo sr_admin_sort_aria('refunded_at', $redemptionSort); ?>><?php echo sr_admin_sort_header_html('환불일', 'refunded_at', $redemptionSort, sr_coupon_admin_redemption_sort_options(), sr_coupon_admin_redemption_default_sort()); ?></th>
                <th class="text-end">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (($redemptions ?? []) === []) { ?>
                <tr>
                    <td colspan="8" class="admin-empty-state">최근 사용 내역이 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach (($redemptions ?? []) as $redemption) { ?>
                    <?php
                    $redemptionId = (int) ($redemption['id'] ?? 0);
                    $redemptionStatus = (string) ($redemption['status'] ?? '');
                    $refundModalId = 'coupon-redemption-refund-modal-' . (string) $redemptionId;
                    $canRefund = $redemptionStatus === 'redeemed'
                        && (string) ($redemption['refundable_policy'] ?? '') === 'refundable';
                    $redemptionPriceUnit = (string) ($redemption['currency_code'] ?? '') !== ''
                        ? (string) ($redemption['currency_code'] ?? '')
                        : (string) ($redemption['asset_unit'] ?? '');
                    $redemptionHasPriceSnapshot = $redemptionPriceUnit !== ''
                        || (string) ($redemption['policy_summary'] ?? '') !== ''
                        || (string) ($redemption['priced_at'] ?? '') !== '';
                    ?>
                    <tr>
                        <td><?php echo sr_e(sr_admin_member_display_name_preview($redemption)); ?><br><?php echo sr_e(sr_admin_member_email_display($redemption)); ?></td>
                        <td>
                            <?php echo sr_e((string) ($redemption['title'] ?? '')); ?><br>
                            <code><?php echo sr_e((string) ($redemption['coupon_key'] ?? '')); ?></code>
                        </td>
                        <td>
                            <?php echo sr_e(sr_coupon_target_display((string) ($redemption['target_type'] ?? ''), (string) ($redemption['target_id'] ?? ''), $pdo)); ?><br>
                            <?php echo sr_e(sr_coupon_reference_display((string) ($redemption['reference_module'] ?? ''), (string) ($redemption['reference_type'] ?? ''), (string) ($redemption['reference_id'] ?? ''))); ?>
                        </td>
                        <td>
                            <?php if ($redemptionHasPriceSnapshot) { ?>
                                <?php if ($redemptionPriceUnit !== '') { ?>
                                    <?php echo sr_e(number_format(max(0, (int) ($redemption['amount'] ?? 0))) . ' ' . $redemptionPriceUnit); ?>
                                <?php } ?>
                                <?php if ((string) ($redemption['policy_summary'] ?? '') !== '') { ?>
                                    <?php if ($redemptionPriceUnit !== '') { ?><br><?php } ?><span class="text-muted"><?php echo sr_e((string) ($redemption['policy_summary'] ?? '')); ?></span>
                                <?php } ?>
                                <?php if ((string) ($redemption['priced_at'] ?? '') !== '') { ?>
                                    <br><?php echo sr_coupon_time_html((string) ($redemption['priced_at'] ?? '')); ?>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="text-muted">-</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e((string) ($redemptionStatusClasses[$redemptionStatus] ?? 'is-blocked')); ?>"><?php echo sr_e(sr_coupon_redemption_status_label($redemptionStatus)); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_coupon_time_html((string) ($redemption['redeemed_at'] ?? '')); ?></td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_coupon_time_html((string) ($redemption['refunded_at'] ?? ''), '-'); ?>
                            <?php if ((string) ($redemption['refund_note'] ?? '') !== '') { ?>
                                <br><?php echo sr_e((string) ($redemption['refund_note'] ?? '')); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <?php if ($canRefund) { ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($refundModalId); ?>" data-overlay="#<?php echo sr_e($refundModalId); ?>">수동 환불</button>
                                <?php } else { ?>
                                    <span class="text-muted">-</span>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
    </div>
    <?php echo sr_admin_status_description_list_html('coupon_redemption_status', $redemptionStatusLabels); ?>
    <?php echo sr_admin_pagination_html($redemptionPagination, '쿠폰 사용 내역 목록 페이지'); ?>
</section>

<?php foreach (($redemptions ?? []) as $redemption) { ?>
    <?php
    $redemptionId = (int) ($redemption['id'] ?? 0);
    $refundModalId = 'coupon-redemption-refund-modal-' . (string) $redemptionId;
    $canRefund = (string) ($redemption['status'] ?? '') === 'redeemed'
        && (string) ($redemption['refundable_policy'] ?? '') === 'refundable';
    if (!$canRefund) {
        continue;
    }
    ?>
    <div id="<?php echo sr_e($refundModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($refundModalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/redemptions')); ?>" class="modal-content ui-form-theme" data-sr-validate-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="refund_redemption">
                <input type="hidden" name="redemption_id" value="<?php echo sr_e((string) $redemptionId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($refundModalId); ?>_title" class="modal-title">쿠폰 사용 수동 환불</h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($refundModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <span class="form-label">쿠폰</span>
                        <div class="form-field">
                            <?php echo sr_e((string) ($redemption['title'] ?? '')); ?> #<?php echo sr_e((string) $redemptionId); ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="coupon_refund_note_<?php echo sr_e((string) $redemptionId); ?>">환불 사유 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="coupon_refund_note_<?php echo sr_e((string) $redemptionId); ?>" type="text" name="refund_note" class="form-input form-control-full" maxlength="255" required data-validation-message="환불 사유를 입력해 주세요." data-overlay-focus>
                            <p class="form-help">수동 환불 이력과 관리자 감사 로그에 남길 사유를 입력합니다.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($refundModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">환불 실행</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
