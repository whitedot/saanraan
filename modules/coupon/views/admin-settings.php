<?php

$usageEnabled = !isset($settings['usage_enabled']) || !empty($settings['usage_enabled']);
$couponZoneLabel = sr_coupon_normalize_zone_label((string) ($settings['coupon_zone_label'] ?? ''));
$couponSettingsHelp = [
    'usage' => [
        'id' => 'coupon-admin-settings-help-usage',
        'title' => '쿠폰·이용권 기능 도움말',
        'body' => '<p>끄면 공개 쿠폰 발급 화면과 사이트 메뉴에서 쿠폰존을 숨기고, 새 쿠폰 지급과 사용처 차감을 중단합니다. 회원 화면의 보유 쿠폰 요약도 표시하지 않습니다.</p>'
            . '<p>이미 지급된 쿠폰, 사용 기록, 환불 기록은 삭제하거나 상태를 바꾸지 않습니다. 기능을 다시 켜면 유효 기간과 상태가 남아 있는 쿠폰을 다시 조회하고 사용할 수 있습니다.</p>',
    ],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/settings')); ?>" class="admin-form ui-form-theme">
    <section class="card">
        <h2>기본 사용</h2>
        <?php echo sr_csrf_field(); ?>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('coupon_usage_enabled', '쿠폰·이용권 기능', $couponSettingsHelp['usage']['id'], '도움말 보기'); ?>
            <div class="form-field">
                <?php echo sr_admin_switch_html('coupon_usage_enabled', 'usage_enabled', '1', $usageEnabled, '사용'); ?>
                <p class="form-help">끄면 쿠폰존 노출, 새 쿠폰 지급과 사용, 회원 보유 쿠폰 표시를 중단합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="coupon_zone_label">쿠폰존 명칭</label>
            <div class="form-field">
                <input id="coupon_zone_label" type="text" name="coupon_zone_label" value="<?php echo sr_e($couponZoneLabel); ?>" class="form-control" maxlength="40">
                <p class="form-help">공개 쿠폰 발급 화면과 사이트 메뉴에 표시할 이름입니다. 비워 두면 ‘쿠폰존’으로 저장합니다.</p>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($couponSettingsHelp as $couponSettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $couponSettingsHelpModal['id'], (string) $couponSettingsHelpModal['title'], (string) $couponSettingsHelpModal['body']); ?>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
