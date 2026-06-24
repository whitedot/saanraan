<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_current_account($pdo);
$accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $campaignKey = sr_post_string('campaign_key', 60);
    $intentToken = sr_post_string('claim_intent_token', 120);
    try {
        if ($accountId <= 0) {
            throw new InvalidArgumentException('로그인 후 쿠폰을 받을 수 있습니다.');
        }

        $result = sr_coupon_claim_free_campaign($pdo, $campaignKey, $accountId, $intentToken, 'coupon_zone');
        $campaign = sr_coupon_claim_campaign_by_key($pdo, $campaignKey);
        if (is_array($campaign)) {
            sr_coupon_public_rotate_claim_intent_token((int) ($campaign['id'] ?? 0));
        }
        $notice = !empty($result['already_claimed']) ? '이미 발급된 쿠폰을 확인했습니다.' : '쿠폰을 발급했습니다.';
        sr_coupon_public_flash_result(['errors' => [], 'notice' => $notice]);
    } catch (Throwable $exception) {
        sr_coupon_public_flash_result(['errors' => [$exception->getMessage()], 'notice' => '']);
    }

    sr_redirect('/coupons');
}

$flashResult = sr_coupon_public_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$campaigns = sr_coupon_public_claim_campaigns($pdo, $accountId);

include SR_ROOT . '/modules/coupon/views/coupons.php';
