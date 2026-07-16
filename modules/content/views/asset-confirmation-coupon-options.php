<?php

$assetConfirmationCouponFieldsetClass = (string) ($assetConfirmationCouponFieldsetClass ?? '');
$assetConfirmationCouponLabelClass = (string) ($assetConfirmationCouponLabelClass ?? 'content-asset-confirmation-coupon');
$assetConfirmationCouponInputClass = (string) ($assetConfirmationCouponInputClass ?? 'form-radio');
$assetConfirmationCouponChecked = false;
?>
<fieldset<?php echo $assetConfirmationCouponFieldsetClass !== '' ? ' class="' . sr_e($assetConfirmationCouponFieldsetClass) . '"' : ''; ?>>
    <legend>쿠폰 선택</legend>
    <?php foreach ($assetConfirmationCouponIssues as $couponIssue) { ?>
        <?php
        $couponIssueId = (int) ($couponIssue['id'] ?? 0);
        if ($couponIssueId <= 0) {
            continue;
        }
        $couponInputId = $assetConfirmationModalId . '_coupon_' . (string) $couponIssueId;
        $couponTitle = (string) (($couponIssue['title'] ?? '') ?: ($couponIssue['coupon_key'] ?? '쿠폰'));
        $couponExpiresAt = (string) ($couponIssue['expires_at'] ?? '');
        $couponChecked = !$assetConfirmationCouponChecked;
        $assetConfirmationCouponChecked = true;
        ?>
        <label class="<?php echo sr_e($assetConfirmationCouponLabelClass); ?>" for="<?php echo sr_e($couponInputId); ?>">
            <input id="<?php echo sr_e($couponInputId); ?>" type="radio" name="coupon_issue_id" value="<?php echo sr_e((string) $couponIssueId); ?>"<?php echo $assetConfirmationCouponInputClass !== '' ? ' class="' . sr_e($assetConfirmationCouponInputClass) . '"' : ''; ?><?php echo $couponChecked ? ' checked' : ''; ?>>
            <span>
                <strong><?php echo sr_e($couponTitle); ?></strong>
                <?php if ($couponExpiresAt !== '') { ?>
                    <small><?php echo sr_content_time_html($couponExpiresAt); ?></small>
                <?php } ?>
            </span>
        </label>
    <?php } ?>
</fieldset>
