<?php

$pageTitle = '자산 환전';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/asset-exchange'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>">계정으로 돌아가기</a></p>

        <?php foreach ($errors as $error) { ?>
            <p><?php echo sr_e((string) $error); ?></p>
        <?php } ?>
        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <section>
            <h2>보유 자산</h2>
            <?php if ($assets === []) { ?>
                <p>환전 가능한 자산 모듈이 없습니다.</p>
            <?php } else { ?>
                <ul>
                    <?php foreach ($assets as $asset) { ?>
                        <li><?php echo sr_e((string) $asset['label']); ?>: <?php echo sr_e(number_format((int) ($balances[(string) $asset['module_key']] ?? 0))); ?> <?php echo sr_e((string) $asset['unit_label']); ?></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        </section>

        <section>
            <h2>환전 신청</h2>
            <?php if ($availablePolicies === []) { ?>
                <p>현재 신청 가능한 환전 정책이 없습니다.</p>
            <?php } else { ?>
                <form method="get" action="<?php echo sr_e(sr_url('/account/asset-exchange')); ?>">
                    <label for="asset_exchange_policy_id">환전 조합</label>
                    <select id="asset_exchange_policy_id" name="policy_id" required>
                        <?php foreach ($availablePolicies as $policy) { ?>
                            <option value="<?php echo sr_e((string) $policy['id']); ?>"<?php echo is_array($selectedPolicy) && (int) $selectedPolicy['id'] === (int) $policy['id'] ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $policy['to_module_key'])); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <label for="asset_exchange_amount">환전 금액</label>
                    <input id="asset_exchange_amount" type="number" name="amount" value="<?php echo sr_e((string) ($_GET['amount'] ?? '')); ?>" min="1" required>
                    <button type="submit">예상 금액 확인</button>
                </form>
                <?php if (is_array($selectedPolicy) && is_array($quote)) { ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/account/asset-exchange')); ?>">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="policy_id" value="<?php echo sr_e((string) $selectedPolicy['id']); ?>">
                        <input type="hidden" name="amount" value="<?php echo sr_e((string) $quote['request_amount']); ?>">
                        <input type="hidden" name="exchange_submit_token" value="<?php echo sr_e((string) ($exchangeSubmitToken ?? '')); ?>">
                        <p>
                            출금 <?php echo sr_e(number_format((int) $quote['request_amount'])); ?>,
                            입금 <?php echo sr_e(number_format((int) $quote['deposit_before_fee'])); ?>,
                            수수료 <?php echo sr_e(number_format((int) $quote['fee_amount'])); ?>,
                            최종 증가 <?php echo sr_e(number_format((int) $quote['deposit_amount'])); ?>
                        </p>
                        <p>적용 비율 <?php echo sr_e(number_format((int) $selectedPolicy['rate_numerator']) . ' / ' . number_format((int) $selectedPolicy['rate_denominator'])); ?></p>
                        <button type="submit">환전 확정</button>
                    </form>
                <?php } ?>
            <?php } ?>
        </section>

        <section>
            <h2>환전 내역</h2>
            <?php if ($logs === []) { ?>
                <p>환전 내역이 없습니다.</p>
            <?php } else { ?>
                <table>
                    <thead>
                        <tr>
                            <th>일시</th>
                            <th>자산</th>
                            <th>출금</th>
                            <th>입금</th>
                            <th>수수료</th>
                            <th>상태</th>
                            <th>실패 사유</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log) { ?>
                            <?php $failureReason = trim((string) ($log['failure_reason'] ?? '')); ?>
                            <tr>
                                <td><?php echo sr_e((string) $log['created_at']); ?></td>
                                <td><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $log['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $log['to_module_key'])); ?></td>
                                <td><?php echo sr_e(number_format((int) $log['request_amount'])); ?></td>
                                <td><?php echo sr_e(number_format((int) $log['deposit_amount'])); ?></td>
                                <td><?php echo sr_e(number_format((int) $log['fee_amount'])); ?></td>
                                <td><?php echo sr_e((string) $log['status']); ?></td>
                                <td><?php echo sr_e($failureReason !== '' ? $failureReason : '-'); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
