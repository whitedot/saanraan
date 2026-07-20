<?php

$pointDisplayName = (string) ($pointDisplayName ?? '포인트');
$pointUnitLabel = (string) ($pointUnitLabel ?? 'P');
$pageTitle = $pointDisplayName . ' 거래 내역';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/points'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <header class="ui-page-header">
            <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
            <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a>
        </header>
        <section class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">현재 잔액</h2>
            <p><?php echo sr_e(number_format((int) $balance)); ?><?php echo sr_e($pointUnitLabel); ?></p>
        </div></section>
        <section id="point-transaction-history" class="card"><div class="card-body ui-card-body-stack">
            <h2 class="card-title">거래 내역</h2>
            <?php if ($transactions === []) { ?>
                <p>거래 내역이 없습니다.</p>
            <?php } else { ?>
                <div class="table-wrapper">
                    <table class="table">
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
                                <td><?php echo sr_e(sr_point_transaction_type_label((string) $transaction['transaction_type'])); ?></td>
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
                    </div>
            <?php } ?>
            <?php echo sr_public_pagination_html($pointTransactionPagination, '/account/points', '포인트 거래 내역 페이지', 'page', 'point-transaction-history'); ?>
        </div></section>
    </main>
<?php sr_public_layout_end(); ?>
