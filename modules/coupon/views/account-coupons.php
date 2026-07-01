<?php

$pageTitle = '보유 쿠폰·이용권';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/coupons'),
    'robots' => 'noindex, nofollow',
];
$now = sr_now();
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">쿠폰 목록</h2>
            </div>
            <div class="card-body ui-card-body-stack">
                <?php if ($coupons === []) { ?>
                    <p>사용 가능한 쿠폰이 없습니다.</p>
                <?php } else { ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>쿠폰</th>
                                    <th>혜택</th>
                                    <th>대상</th>
                                    <th>사용</th>
                                    <th>만료</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $coupon) { ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo sr_e((string) $coupon['title']); ?></strong>
                                            <?php if ((string) ($coupon['description'] ?? '') !== '') { ?>
                                                <br><?php echo sr_e((string) $coupon['description']); ?>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo sr_e(sr_coupon_definition_benefit_label($coupon)); ?></td>
                                        <td><?php echo sr_e(sr_coupon_target_display((string) $coupon['target_type'], (string) $coupon['target_id'], $pdo ?? null)); ?></td>
                                        <td>
                                            <?php echo sr_e((string) $coupon['used_count']); ?> / <?php echo sr_e((string) $coupon['max_uses_per_issue']); ?>
                                            <?php if ((string) ($coupon['starts_at'] ?? '') !== '' && strcmp((string) $coupon['starts_at'], $now) > 0) { ?>
                                                <br><small><?php echo sr_coupon_time_html((string) $coupon['starts_at']); ?>부터 사용 가능</small>
                                            <?php } ?>
                                        </td>
                                        <td><?php echo $coupon['expires_at'] === null ? sr_e('제한 없음') : sr_coupon_time_html((string) $coupon['expires_at']); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
