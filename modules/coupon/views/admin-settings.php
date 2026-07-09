<?php

$usageEnabled = !isset($settings['usage_enabled']) || !empty($settings['usage_enabled']);
$couponZoneLabel = sr_coupon_normalize_zone_label((string) ($settings['coupon_zone_label'] ?? ''));

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>기본 사용</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="form-row">
            <label class="form-label" for="coupon_usage_enabled">쿠폰·이용권 사용 여부</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('coupon_usage_enabled', 'usage_enabled', '1', $usageEnabled, '사용'); ?>
                <p class="form-help">사용하지 않으면 쿠폰존 노출, 직접 발급/보상 지급, 사용처 차감, 회원 요약에서 제외됩니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="coupon_zone_label">쿠폰존 명칭</label>
            <div class="form-field">
                <input id="coupon_zone_label" type="text" name="coupon_zone_label" value="<?php echo sr_e($couponZoneLabel); ?>" class="form-control" maxlength="40">
                <p class="form-help">공개 쿠폰 발급 화면과 사이트 메뉴 링크 후보에 표시할 이름입니다. 비워 두면 쿠폰존으로 저장됩니다.</p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
