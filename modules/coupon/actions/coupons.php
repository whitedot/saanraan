<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_current_account($pdo);
$accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
$errors = [];
$notice = '';
$selectedCampaignKey = sr_coupon_clean_key(sr_get_string('campaign', 60));

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $campaignKey = sr_post_string('campaign_key', 60);
    $intentToken = sr_post_string('claim_intent_token', 120);
    $claimSource = sr_post_string('claim_source', 40);
    $returnTo = sr_member_safe_next_path(sr_post_string('return_to', 500));
    if ($returnTo === '/') {
        $returnTo = $campaignKey !== '' ? '/coupons?campaign=' . rawurlencode($campaignKey) : '/coupons';
    }
    try {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('로그인 후 쿠폰을 받을 수 있습니다.');
        }

        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
        if (!is_array($campaign)) {
            throw new InvalidArgumentException('발급 캠페인을 찾을 수 없습니다.');
        }
        if (!sr_coupon_public_claim_intent_token_matches((int) ($campaign['id'] ?? 0), $accountId, $intentToken)) {
            throw new InvalidArgumentException('쿠폰 발급 요청 토큰이 올바르지 않습니다. 화면을 새로고침한 뒤 다시 시도해 주세요.');
        }
        if ((string) ($campaign['claim_type'] ?? 'free') === 'paid') {
            $result = sr_coupon_claim_paid_campaign_with_asset(
                $pdo,
                $campaignKey,
                $accountId,
                $intentToken,
                $_POST['allowed_asset_modules'] ?? [],
                $claimSource !== '' ? $claimSource : 'coupon_zone'
            );
        } else {
            $result = sr_coupon_claim_free_campaign($pdo, $campaignKey, $accountId, $intentToken, $claimSource !== '' ? $claimSource : 'coupon_zone');
        }
        if (is_array($campaign)) {
            sr_coupon_public_rotate_claim_intent_token((int) ($campaign['id'] ?? 0), $accountId);
        }
        $notice = !empty($result['already_claimed']) ? '이미 발급된 쿠폰을 확인했습니다.' : '쿠폰을 발급했습니다.';
        sr_coupon_public_flash_result(['errors' => [], 'notice' => $notice]);
    } catch (Throwable $exception) {
        sr_coupon_public_flash_result(['errors' => [$exception->getMessage()], 'notice' => '']);
    }

    sr_redirect($returnTo);
}

$flashResult = sr_coupon_public_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$singleCampaign = $selectedCampaignKey !== '' ? sr_coupon_public_claim_campaign($pdo, $selectedCampaignKey, $accountId, ['direct_link', 'coupon_zone', 'content_embed']) : null;
$couponCampaignPerPage = 50;
$couponCampaignPageInput = sr_get_string('page', 20);
$couponCampaignPage = preg_match('/\A[1-9][0-9]*\z/', $couponCampaignPageInput) === 1 ? (int) $couponCampaignPageInput : 1;
$couponCampaignCount = is_array($singleCampaign) ? 1 : sr_coupon_public_claim_campaign_count($pdo);
$couponCampaignTotalPages = max(1, (int) ceil($couponCampaignCount / $couponCampaignPerPage));
$couponCampaignPage = min(max(1, $couponCampaignPage), $couponCampaignTotalPages);
$couponCampaignPagination = ['page' => $couponCampaignPage, 'total_pages' => $couponCampaignTotalPages];
$campaigns = is_array($singleCampaign)
    ? [$singleCampaign]
    : sr_coupon_public_claim_campaigns($pdo, $accountId, $couponCampaignPerPage, ($couponCampaignPage - 1) * $couponCampaignPerPage);

include SR_ROOT . '/modules/coupon/views/coupons.php';
