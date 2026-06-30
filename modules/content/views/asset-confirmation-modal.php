<?php

$assetConfirmationMessage = (string) ($assetConfirmationMessage ?? '차감 내용을 확인해 주세요.');
$assetConfirmationAction = (string) ($assetConfirmationAction ?? '/content');
$assetConfirmationId = (int) ($assetConfirmationId ?? 0);
$assetConfirmationContentId = (int) ($assetConfirmationContentId ?? 0);
$assetConfirmationRequestToken = (string) ($assetConfirmationRequestToken ?? '');
$assetConfirmationTitle = (string) ($assetConfirmationTitle ?? sr_t('content::ui.text.0a4ca9bc'));
$assetConfirmationAssetLabel = (string) ($assetConfirmationAssetLabel ?? '');
$assetConfirmationAmount = (int) ($assetConfirmationAmount ?? 0);
$assetConfirmationSubmitLabel = (string) ($assetConfirmationSubmitLabel ?? sr_t('content::ui.text.ac5b575f'));
$assetConfirmationCouponIssues = is_array($assetConfirmationCouponIssues ?? null) ? $assetConfirmationCouponIssues : [];
$assetConfirmationExchangeSuggestion = is_array($assetConfirmationExchangeSuggestion ?? null) ? $assetConfirmationExchangeSuggestion : [];
$assetConfirmationModalId = (string) ($assetConfirmationModalId ?? 'content_asset_confirmation_modal');
$assetConfirmationOpen = !isset($assetConfirmationOpen) || !empty($assetConfirmationOpen);
$assetConfirmationCancelUrl = (string) ($assetConfirmationCancelUrl ?? '/');
$assetConfirmationCloseOnSubmit = !empty($assetConfirmationCloseOnSubmit);
$assetConfirmationClasses = 'modal-overlay overlay content-asset-confirmation-modal';
$assetConfirmationClasses .= $assetConfirmationOpen ? ' overlay-open open' : ' modal-overlay-fade hidden pointer-events-none opacity-0';
?>
<div id="<?php echo sr_e($assetConfirmationModalId); ?>" class="<?php echo sr_e($assetConfirmationClasses); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetConfirmationModalId . '_title'); ?>" aria-modal="true" aria-hidden="<?php echo $assetConfirmationOpen ? 'false' : 'true'; ?>"<?php echo $assetConfirmationOpen ? '' : ' inert'; ?>>
    <div class="modal-dialog-center">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="<?php echo sr_e($assetConfirmationModalId . '_title'); ?>" class="modal-title"><?php echo sr_e($assetConfirmationTitle); ?></h2>
            </div>
            <div class="modal-body">
                <p class="content-asset-confirmation-message"><?php echo sr_e($assetConfirmationMessage); ?></p>
                <?php if ($assetConfirmationExchangeSuggestion !== []) { ?>
                    <p class="content-asset-confirmation-message">환전 후 결제를 진행합니다.</p>
                <?php } ?>
                <?php if ($assetConfirmationAssetLabel !== '' || $assetConfirmationAmount > 0) { ?>
                    <p class="content-asset-confirmation-price">
                        <?php if ($assetConfirmationAssetLabel !== '') { ?>
                            <span><?php echo sr_e($assetConfirmationAssetLabel); ?></span>
                        <?php } ?>
                        <?php if ($assetConfirmationAmount > 0) { ?>
                            <strong><?php echo sr_e(number_format($assetConfirmationAmount)); ?></strong>
                        <?php } ?>
                    </p>
                <?php } ?>
                <?php if ($assetConfirmationCouponIssues !== []) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>" class="content-asset-confirmation-coupons"<?php echo $assetConfirmationCloseOnSubmit ? ' data-content-asset-confirmation-close-on-submit' : ''; ?>>
                        <?php echo sr_csrf_field(); ?>
                        <?php if ($assetConfirmationId > 0) { ?>
                            <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
                        <?php } ?>
                        <input type="hidden" name="asset_confirm" value="1">
                        <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
                        <?php if ($assetConfirmationContentId > 0) { ?>
                            <input type="hidden" name="content_id" value="<?php echo sr_e((string) $assetConfirmationContentId); ?>">
                        <?php } ?>
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
                                <label class="content-asset-confirmation-coupon" for="<?php echo sr_e($couponInputId); ?>">
                                    <input id="<?php echo sr_e($couponInputId); ?>" type="radio" name="coupon_issue_id" value="<?php echo sr_e((string) $couponIssueId); ?>" class="form-radio"<?php echo $couponIndex === 0 ? ' checked' : ''; ?>>
                                    <span>
                                        <strong><?php echo sr_e($couponTitle); ?></strong>
                                        <?php if ($couponExpiresAt !== '') { ?>
                                            <small><?php echo sr_content_time_html($couponExpiresAt); ?></small>
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
                <?php if ($assetConfirmationCancelUrl === '') { ?>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assetConfirmationModalId); ?>">취소</button>
                <?php } else { ?>
                    <a class="btn btn-solid-light modal-action" href="<?php echo sr_e(sr_url($assetConfirmationCancelUrl)); ?>">취소</a>
                <?php } ?>
                <form method="post" action="<?php echo sr_e(sr_url($assetConfirmationAction)); ?>" class="modal-action"<?php echo $assetConfirmationCloseOnSubmit ? ' data-content-asset-confirmation-close-on-submit' : ''; ?>>
                    <?php echo sr_csrf_field(); ?>
                    <?php if ($assetConfirmationId > 0) { ?>
                        <input type="hidden" name="id" value="<?php echo sr_e((string) $assetConfirmationId); ?>">
                    <?php } ?>
                    <input type="hidden" name="asset_confirm" value="1">
                    <?php echo sr_content_asset_settlement_exchange_hidden_inputs_html($assetConfirmationExchangeSuggestion); ?>
                    <input type="hidden" name="asset_request_token" value="<?php echo sr_e($assetConfirmationRequestToken); ?>">
                    <?php if ($assetConfirmationContentId > 0) { ?>
                        <input type="hidden" name="content_id" value="<?php echo sr_e((string) $assetConfirmationContentId); ?>">
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-warning"><?php echo sr_e($assetConfirmationSubmitLabel); ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
