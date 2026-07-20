<?php

$singleCampaign = isset($singleCampaign) && is_array($singleCampaign) ? $singleCampaign : null;
$couponCampaignPagination = isset($couponCampaignPagination) && is_array($couponCampaignPagination) ? $couponCampaignPagination : ['page' => 1, 'total_pages' => 1];
$couponZoneLabel = isset($pdo) && $pdo instanceof PDO ? sr_coupon_zone_label($pdo) : '쿠폰존';
$pageTitle = is_array($singleCampaign) ? (string) ($singleCampaign['title'] ?? $couponZoneLabel) : $couponZoneLabel;
$canonicalPath = is_array($singleCampaign)
    ? '/coupons?campaign=' . rawurlencode((string) ($singleCampaign['campaign_key'] ?? ''))
    : '/coupons';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, $canonicalPath),
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page coupon-zone">
        <?php echo sr_public_feedback_toasts('coupon', $notice, $errors); ?>
        <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
        <section class="coupon-zone-list">
            <?php if ($campaigns === []) { ?>
                <div class="card">
                    <div class="card-body">
                        <p>지금 받을 수 있는 쿠폰이 없습니다.</p>
                    </div>
                </div>
            <?php } else { ?>
                <?php foreach ($campaigns as $campaign) { ?>
                    <?php
                    $campaignId = (int) ($campaign['id'] ?? 0);
                    $state = is_array($campaign['claim_state'] ?? null) ? $campaign['claim_state'] : [];
                    $remaining = $state['remaining'] ?? null;
                    $canClaim = !empty($state['claimable']) && $accountId > 0;
                    $intentToken = sr_coupon_public_claim_intent_token($campaignId, $accountId);
                    $campaignUrl = '/coupons?campaign=' . rawurlencode((string) ($campaign['campaign_key'] ?? ''));
                    $claimSource = (string) ($campaign['claim_source'] ?? 'coupon_zone');
                    $claimType = (string) ($campaign['claim_type'] ?? 'free');
                    $allowedAssetModules = $claimType === 'paid' ? sr_coupon_asset_module_keys_from_value($pdo, $campaign['allowed_asset_modules_json'] ?? '') : [];
                    ?>
                    <article class="card coupon-zone-card">
                        <div class="card-body ui-card-body-stack">
                            <div>
                                <h2 class="card-title"><?php echo sr_e((string) ($campaign['title'] ?? '')); ?></h2>
                                <?php if ((string) ($campaign['description'] ?? '') !== '') { ?>
                                    <p><?php echo sr_e((string) $campaign['description']); ?></p>
                                <?php } ?>
                            </div>
                            <dl class="ui-description-list">
                                <div>
                                    <dt>쿠폰</dt>
                                    <dd><?php echo sr_e((string) ($campaign['coupon_title'] ?? '')); ?></dd>
                                </div>
                                <div>
                                    <dt>발급 유형</dt>
                                    <dd>
                                        <?php echo sr_e(sr_coupon_claim_type_label($claimType)); ?>
                                        <?php if ($claimType === 'paid') { ?>
                                            · <?php echo sr_e(number_format((int) ($campaign['price_amount'] ?? 0)) . ' ' . (string) ($campaign['price_currency_code'] ?? '')); ?>
                                        <?php } ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt>남은 수량</dt>
                                    <dd><?php echo $remaining === null ? sr_e('제한 없음') : sr_e(number_format((int) $remaining) . '장'); ?></dd>
                                </div>
                                <div>
                                    <dt>발급 기간</dt>
                                    <dd>
                                        <?php echo (string) ($campaign['ends_at'] ?? '') === '' ? sr_e('상시') : sr_coupon_time_html((string) $campaign['ends_at'], '상시'); ?>
                                    </dd>
                                </div>
                            </dl>
                            <?php if ($accountId <= 0) { ?>
                                <a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($campaignUrl))); ?>">로그인하고 받기</a>
                            <?php } elseif ($canClaim) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/coupons')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="campaign_key" value="<?php echo sr_e((string) ($campaign['campaign_key'] ?? '')); ?>">
                                    <input type="hidden" name="claim_source" value="<?php echo sr_e($claimSource); ?>">
                                    <input type="hidden" name="claim_intent_token" value="<?php echo sr_e($intentToken); ?>">
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($campaignUrl); ?>">
                                    <?php if ($claimType === 'paid' && $allowedAssetModules !== []) { ?>
                                        <div class="form-field">
                                            <span class="form-label">사용할 항목</span>
                                            <?php foreach ($allowedAssetModules as $assetModule) { ?>
                                                <label><input type="checkbox" name="allowed_asset_modules[]" value="<?php echo sr_e($assetModule); ?>" class="form-checkbox" checked> <?php echo sr_e(sr_coupon_asset_module_labels($pdo, [$assetModule])); ?></label>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                    <button type="submit" class="btn btn-solid-primary">쿠폰 받기</button>
                                </form>
                            <?php } else { ?>
                                <button type="button" class="btn btn-outline-secondary" disabled><?php echo sr_e((string) ($state['message'] ?? '받을 수 없음')); ?></button>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
            <?php } ?>
        </section>
        <?php if (!is_array($singleCampaign)) { ?>
            <?php echo sr_public_pagination_html($couponCampaignPagination, '/coupons', '쿠폰 발급 캠페인 목록 페이지'); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
