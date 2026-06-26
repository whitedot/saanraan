<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? sr_community_asset_confirmation_required_message());
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/community');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationTitle = (string) ($assetConfirmationTitle ?? sr_t('community::ui.community.4a285775'));
$assetConfirmationAssetLabel = (string) ($assetConfirmationAssetLabel ?? '');
$assetConfirmationAmount = (int) ($assetConfirmationAmount ?? 0);
$assetConfirmationCouponIssues = is_array($assetConfirmationCouponIssues ?? null) ? $assetConfirmationCouponIssues : [];
$assetConfirmationModalId = (string) ($assetConfirmationModalId ?? 'community_asset_confirmation_modal');
?>
<div id="<?php echo sr_e($assetConfirmationModalId); ?>" class="modal-overlay overlay overlay-open community-asset-confirmation-modal" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetConfirmationModalId . '_title'); ?>" aria-modal="true">
    <div class="modal-dialog-center">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="<?php echo sr_e($assetConfirmationModalId . '_title'); ?>" class="modal-title"><?php echo sr_e($assetConfirmationTitle); ?></h2>
            </div>
            <div class="modal-body">
                <p class="community-asset-confirmation-message"><?php echo sr_e($assetConfirmationMessage); ?></p>
                <?php if ($assetConfirmationAssetLabel !== '' || $assetConfirmationAmount > 0) { ?>
                    <p class="community-asset-confirmation-price">
                        <?php if ($assetConfirmationAssetLabel !== '') { ?>
                            <span><?php echo sr_e($assetConfirmationAssetLabel); ?></span>
                        <?php } ?>
                        <?php if ($assetConfirmationAmount > 0) { ?>
                            <strong><?php echo sr_e(number_format($assetConfirmationAmount)); ?></strong>
                        <?php } ?>
                    </p>
                <?php } ?>
                <?php if ($assetConfirmationCouponIssues !== []) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>" class="community-asset-confirmation-coupons">
                        <?php echo sr_csrf_field(); ?>
                        <?php if ($assetConfirmationId > 0) { ?>
                            <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
                        <?php } ?>
                        <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
                        <fieldset>
                            <legend>쿠폰 선택</legend>
                            <?php foreach ($assetConfirmationCouponIssues as $couponIndex => $couponIssue) { ?>
                                <?php
                                $couponIssueId = (int) ($couponIssue['id'] ?? 0);
                                if ($couponIssueId <= 0) {
                                    continue;
                                }
                                $couponInputId = $assetConfirmationModalId . '_coupon_' . (string) $couponIssueId;
                                $couponTitle = (string) (($couponIssue['title'] ?? '') ?: ($couponIssue['coupon_key'] ?? '쿠폰'));
                                $couponExpiresAt = (string) ($couponIssue['expires_at'] ?? '');
                                ?>
                                <label class="community-asset-confirmation-coupon" for="<?php echo sr_e($couponInputId); ?>">
                                    <input id="<?php echo sr_e($couponInputId); ?>" type="radio" name="coupon_issue_id" value="<?php echo sr_e((string) $couponIssueId); ?>" class="form-radio"<?php echo $couponIndex === 0 ? ' checked' : ''; ?>>
                                    <span>
                                        <strong><?php echo sr_e($couponTitle); ?></strong>
                                        <?php if ($couponExpiresAt !== '') { ?>
                                            <small><?php echo sr_community_time_html($couponExpiresAt); ?></small>
                                        <?php } ?>
                                    </span>
                                </label>
                            <?php } ?>
                        </fieldset>
                        <button type="submit" class="btn btn-solid-primary">쿠폰 적용</button>
                    </form>
                <?php } ?>
            </div>
            <div class="modal-footer">
                <a class="btn btn-solid-light modal-action" href="<?php echo sr_e(sr_url('/community')); ?>">취소</a>
                <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>" class="modal-action">
                    <?php echo sr_csrf_field(); ?>
                    <?php if ($assetConfirmationId > 0) { ?>
                        <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
                    <?php } ?>
                    <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
                    <button type="submit" class="btn btn-solid-warning"><?php echo sr_e(sr_t('community::ui.text.ac5b575f')); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
