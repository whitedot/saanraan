<?php

$pageTitle = '보유 쿠폰·이용권';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/coupons'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>
        <?php if ($coupons === []) { ?>
            <p>사용 가능한 쿠폰이 없습니다.</p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th>쿠폰</th>
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
                            <td><?php echo sr_e((string) $coupon['target_type']); ?> <?php echo sr_e((string) $coupon['target_id']); ?></td>
                            <td><?php echo sr_e((string) $coupon['used_count']); ?> / <?php echo sr_e((string) $coupon['max_uses_per_issue']); ?></td>
                            <td><?php echo $coupon['expires_at'] === null ? '제한 없음' : sr_e((string) $coupon['expires_at']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
