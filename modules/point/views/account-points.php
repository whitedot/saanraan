<?php

$pointDisplayName = (string) ($pointDisplayName ?? '포인트');
$pointUnitLabel = (string) ($pointUnitLabel ?? 'P');
$pageTitle = $pointDisplayName . ' 거래 내역';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/points'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>
        <section>
            <h2>현재 잔액</h2>
            <p><?php echo sr_e(number_format((int) $balance)); ?><?php echo sr_e($pointUnitLabel); ?></p>
        </section>
        <section>
            <h2>거래 내역</h2>
            <?php if ($transactions === []) { ?>
                <p>거래 내역이 없습니다.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr>
                            <th>일시</th>
                            <th>유형</th>
                            <th>변동</th>
                            <th>잔액</th>
                            <th>유효기간</th>
                            <th>사유</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction) { ?>
                            <tr>
                                <td><?php echo sr_point_time_html((string) $transaction['created_at']); ?></td>
                                <td><?php echo sr_e((string) $transaction['transaction_type']); ?></td>
                                <td><?php echo sr_e(number_format((int) $transaction['amount'])); ?></td>
                                <td><?php echo sr_e(number_format((int) $transaction['balance_after'])); ?></td>
                                <td>
                                    <?php if ((string) ($transaction['expires_at'] ?? '') !== '') { ?>
                                        <?php echo sr_point_time_html((string) $transaction['expires_at']); ?>
                                    <?php } elseif ((string) ($transaction['transaction_type'] ?? '') === 'expire' && (string) ($transaction['created_at'] ?? '') !== '') { ?>
                                        <?php echo sr_point_time_html((string) $transaction['created_at']); ?>
                                    <?php } else { ?>
                                        -
                                    <?php } ?>
                                </td>
                                <td><?php echo sr_e((string) $transaction['reason']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
