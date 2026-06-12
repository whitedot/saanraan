<?php

$adminPageTitle = '자산 원장 정합성';
$assetIssueLabels = [
    'missing_balance_row' => '잔액 행 없음',
    'balance_sum_mismatch' => '잔액 합계 불일치',
    'last_balance_after_mismatch' => '마지막 거래 잔액 불일치',
    'nonzero_balance_without_transactions' => '거래 없는 잔액',
    'balance_after_sequence_mismatch' => '거래별 잔액 연쇄 불일치',
];
$assetStatusLabels = [
    'checked' => '점검 완료',
    'skipped' => '건너뜀',
    'error' => '오류',
];
$assetTotals = sr_asset_reconciliation_summary($reconciliationResults);

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/assets/reconciliation')); ?>" class="filtering-form filtering filtering-plain ui-form-theme">
    <div class="filtering-fields">
        <div class="filtering-field">
            <label for="asset-reconciliation-max-rows" class="filtering-label">표시 행 수</label>
            <input id="asset-reconciliation-max-rows" type="number" name="max_rows" value="<?php echo sr_e((string) $maxRows); ?>" class="form-input filtering-input" min="1" max="500">
            <p class="admin-form-help">자산별 불일치 상세 표시 한도</p>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">점검</button>
    </div>
</form>

<section class="admin-card admin-list-card card">
    <div class="card-header">
        <h2 class="card-title">점검 요약</h2>
    </div>
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">점검 완료 <strong><?php echo sr_e((string) $assetTotals['checked']); ?></strong></span>
        <span class="admin-summary-meta">건너뜀 <strong><?php echo sr_e((string) $assetTotals['skipped']); ?></strong></span>
        <span class="admin-summary-meta">오류 <strong><?php echo sr_e((string) $assetTotals['error']); ?></strong></span>
        <span class="admin-summary-meta">불일치 <strong><?php echo sr_e(number_format((int) $assetTotals['mismatch_count'])); ?></strong></span>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>자산</th>
                    <th>상태</th>
                    <th>계정 수</th>
                    <th>불일치</th>
                    <th>메모</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reconciliationResults as $assetResult) { ?>
                    <?php
                    $assetStatus = (string) ($assetResult['status'] ?? '');
                    $assetStatusLabel = (string) ($assetStatusLabels[$assetStatus] ?? $assetStatus);
                    ?>
                    <tr>
                        <td><?php echo sr_e((string) ($assetResult['label'] ?? $assetResult['module_key'] ?? '')); ?></td>
                        <td><?php echo sr_e($assetStatusLabel); ?></td>
                        <td><?php echo sr_e(number_format((int) ($assetResult['total_accounts'] ?? 0))); ?></td>
                        <td><?php echo sr_e(number_format((int) ($assetResult['mismatch_count'] ?? 0))); ?></td>
                        <td><?php echo sr_e((string) ($assetResult['message'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php foreach ($reconciliationResults as $assetResult) { ?>
    <?php
    $assetMismatches = isset($assetResult['mismatches']) && is_array($assetResult['mismatches']) ? $assetResult['mismatches'] : [];
    if ($assetMismatches === []) {
        continue;
    }
    ?>
    <section class="admin-card admin-list-card card">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e((string) ($assetResult['label'] ?? $assetResult['module_key'] ?? '')); ?> 불일치</h2>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>계정 ID</th>
                        <th>저장 잔액</th>
                        <th>거래 합계</th>
                        <th>마지막 거래 잔액</th>
                        <th>거래 수</th>
                        <th>연쇄 오류 거래</th>
                        <th>연쇄 기대/실제</th>
                        <th>유형</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assetMismatches as $assetMismatch) { ?>
                        <?php
                        $assetIssues = [];
                        foreach ((array) ($assetMismatch['issues'] ?? []) as $assetIssue) {
                            $assetIssues[] = (string) ($assetIssueLabels[(string) $assetIssue] ?? $assetIssue);
                        }
                        ?>
                        <tr>
                            <td><?php echo sr_e((string) (int) ($assetMismatch['account_id'] ?? 0)); ?></td>
                            <td><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['stored_balance'] ?? null)); ?></td>
                            <td><?php echo sr_e(number_format((int) ($assetMismatch['ledger_balance'] ?? 0))); ?></td>
                            <td><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['last_balance_after'] ?? null)); ?></td>
                            <td><?php echo sr_e(number_format((int) ($assetMismatch['transaction_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_mismatch_transaction_id'] ?? null)); ?></td>
                            <td>
                                <?php if (($assetMismatch['sequence_expected_balance_after'] ?? null) !== null || ($assetMismatch['sequence_actual_balance_after'] ?? null) !== null) { ?>
                                    <?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_expected_balance_after'] ?? null)); ?>
                                    /
                                    <?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_actual_balance_after'] ?? null)); ?>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e(implode(', ', $assetIssues)); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($assetResult['truncated'])) { ?>
            <p class="admin-empty-state">표시 행 수를 초과한 불일치가 있습니다.</p>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
