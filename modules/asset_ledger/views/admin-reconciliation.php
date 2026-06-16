<?php

$adminPageTitle = '포인트/금액 정합성 점검';
$adminPageSubtitle = [
    '회원 포인트/금액 모듈의 잔액 행과 거래 원장을 대조합니다.',
    '조회는 읽기 전용이며 불일치 상세는 항목별 최대 50건까지 노출됩니다.',
];
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/assets/reconciliation');
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

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">점검 요약</h2>
    </div>
    <div class="admin-list-summary-row admin-asset-ledger-reconciliation-summary-row">
        <div class="badge-list">
            <span class="badge badge-soft-secondary">점검 완료 <?php echo sr_e(number_format((int) $assetTotals['checked'])); ?></span>
            <span class="badge badge-soft-secondary">건너뜀 <?php echo sr_e(number_format((int) $assetTotals['skipped'])); ?></span>
            <span class="badge badge-soft-secondary">오류 <?php echo sr_e(number_format((int) $assetTotals['error'])); ?></span>
            <span class="badge badge-soft-danger">불일치 <?php echo sr_e(number_format((int) $assetTotals['mismatch_count'])); ?></span>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>포인트/금액 항목</th>
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
                    $assetStatusClass = match ($assetStatus) {
                        'checked' => 'is-normal',
                        'skipped' => 'is-warning',
                        'error' => 'is-danger',
                        default => 'is-blocked',
                    };
                    ?>
                    <tr>
                        <td><?php echo sr_e((string) ($assetResult['label'] ?? $assetResult['module_key'] ?? '')); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($assetStatusClass); ?>"><?php echo sr_e($assetStatusLabel); ?></span></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($assetResult['total_accounts'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($assetResult['mismatch_count'] ?? 0))); ?></td>
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
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e((string) ($assetResult['label'] ?? $assetResult['module_key'] ?? '')); ?> 불일치</h2>
        </div>
        <div class="admin-list-summary-row">
            <div class="admin-list-summary">
                불일치 <?php echo sr_e(number_format((int) ($assetResult['mismatch_count'] ?? count($assetMismatches)))); ?>건 중 최대 <?php echo sr_e(number_format(count($assetMismatches))); ?>건을 표시합니다.
            </div>
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
                            <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($assetMismatch['account_id'] ?? 0)); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['stored_balance'] ?? null)); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($assetMismatch['ledger_balance'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['last_balance_after'] ?? null)); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) ($assetMismatch['transaction_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_mismatch_transaction_id'] ?? null)); ?></td>
                            <td class="admin-table-nowrap text-end">
                                <?php if (($assetMismatch['sequence_expected_balance_after'] ?? null) !== null || ($assetMismatch['sequence_actual_balance_after'] ?? null) !== null) { ?>
                                    <?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_expected_balance_after'] ?? null)); ?>
                                    /
                                    <?php echo sr_e(sr_asset_reconcile_nullable_int($assetMismatch['sequence_actual_balance_after'] ?? null)); ?>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>
                            <td class="admin-table-break"><?php echo sr_e(implode(', ', $assetIssues)); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($assetResult['truncated'])) { ?>
            <p class="admin-list-summary">표시 한도 50건을 초과한 불일치가 있습니다. 전체 결과는 CLI 점검 도구로 확인하세요.</p>
        <?php } ?>
    </section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
