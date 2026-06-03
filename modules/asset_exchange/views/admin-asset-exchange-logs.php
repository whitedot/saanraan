<?php

$adminPageTitle = '포인트/금액 환전 로그';
$logStatusLabels = ['completed' => '완료', 'failed' => '실패'];
$logFilters = isset($logFilters) && is_array($logFilters) ? $logFilters : ['status' => [], 'asset' => [], 'field' => 'all', 'q' => ''];
$selectedLogStatuses = is_array($logFilters['status'] ?? null) ? $logFilters['status'] : [];
$selectedLogAssets = is_array($logFilters['asset'] ?? null) ? $logFilters['asset'] : [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="admin-filter admin-asset-exchange-log-filter ui-form-theme">
    <div class="admin-filter-grid admin-asset-exchange-log-search-grid">
        <div class="admin-filter-field admin-asset-exchange-log-filter-status">
            <label class="admin-filter-label">상태</label>
            <div class="btn-group admin-asset-exchange-filter-button-group" role="group" aria-label="상태">
                <?php $logStatusValues = array_keys($logStatusLabels); ?>
                <?php foreach ($logStatusLabels as $status => $label) { ?>
                    <?php $statusIndex = array_search($status, $logStatusValues, true); ?>
                    <?php $groupClass = $statusIndex === 0 ? 'btn-group-start' : ($statusIndex === count($logStatusValues) - 1 ? 'btn-group-end' : 'btn-group-middle'); ?>
                    <?php $inputId = 'asset_exchange_log_filter_status_' . (string) $status; ?>
                    <label class="btn btn-choice-light <?php echo sr_e($groupClass); ?>" for="<?php echo sr_e($inputId); ?>">
                        <input id="<?php echo sr_e($inputId); ?>" type="checkbox" name="status[]" value="<?php echo sr_e((string) $status); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array((string) $status, $selectedLogStatuses, true) ? ' checked' : ''; ?>>
                        <?php echo sr_e((string) $label); ?>
                    </label>
                <?php } ?>
            </div>
        </div>
        <div class="admin-filter-field admin-asset-exchange-log-filter-asset">
            <label class="admin-filter-label">항목</label>
            <div class="btn-group admin-asset-exchange-filter-button-group" role="group" aria-label="항목">
                <?php $assetFilterIndex = 0; ?>
                <?php $assetFilterCount = count($assets); ?>
                <?php foreach ($assets as $asset) { ?>
                    <?php
                    $moduleKey = (string) ($asset['module_key'] ?? '');
                    $inputId = 'asset_exchange_log_filter_asset_' . preg_replace('/[^a-z0-9_\-]/', '_', $moduleKey);
                    $groupClass = $assetFilterIndex === 0 ? 'btn-group-start' : ($assetFilterIndex === $assetFilterCount - 1 ? 'btn-group-end' : 'btn-group-middle');
                    $assetFilterIndex++;
                    ?>
                    <label class="btn btn-choice-light <?php echo sr_e($groupClass); ?>" for="<?php echo sr_e($inputId); ?>">
                        <input id="<?php echo sr_e($inputId); ?>" type="checkbox" name="asset[]" value="<?php echo sr_e($moduleKey); ?>" class="form-choice-toggle-input sr-only"<?php echo in_array($moduleKey, $selectedLogAssets, true) ? ' checked' : ''; ?>>
                        <?php echo sr_e((string) ($asset['label'] ?? $moduleKey)); ?>
                    </label>
                <?php } ?>
            </div>
        </div>
        <div class="admin-filter-field admin-asset-exchange-log-filter-field">
            <label for="asset_exchange_log_filter_field" class="admin-filter-label">검색 대상</label>
            <select id="asset_exchange_log_filter_field" name="field" class="form-select admin-filter-input">
                <?php foreach (['all' => '전체', 'member' => '회원', 'group_id' => '환전 묶음 ID'] as $fieldValue => $fieldLabel) { ?>
                    <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($logFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="admin-filter-field admin-asset-exchange-log-filter-keyword">
            <label for="asset_exchange_log_filter_q" class="admin-filter-label">검색어</label>
            <input id="asset_exchange_log_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($logFilters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="회원, 환전 묶음 ID">
        </div>
        <button type="submit" class="btn btn-solid-primary admin-filter-submit">검색</button>
    </div>
</form>

<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header"><h2 class="card-title">환전 로그 목록</h2></div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>처리일</th>
                    <th>회원</th>
                    <th>환전 묶음 ID</th>
                    <th>항목</th>
                    <th>출금</th>
                    <th>입금</th>
                    <th>수수료</th>
                    <th>상태</th>
                    <th>실패 사유</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []) { ?>
                    <tr><td colspan="9" class="admin-empty-state">환전 로그가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($logs as $log) { ?>
                    <?php $failureReason = trim((string) ($log['failure_reason'] ?? '')); ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) $log['created_at']); ?></td>
                        <td>
                            <?php echo sr_e(sr_admin_member_display_name_preview($log)); ?><br>
                            <small><?php echo sr_e(sr_admin_member_email_display($log)); ?></small>
                        </td>
                        <td class="admin-table-nowrap"><code><?php echo sr_e((string) $log['exchange_group_id']); ?></code></td>
                        <td>
                            <?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $log['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $log['to_module_key'])); ?>
                        </td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) $log['request_amount'])); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) $log['deposit_amount'])); ?></td>
                        <td class="admin-table-nowrap text-end"><?php echo sr_e(number_format((int) $log['fee_amount'])); ?></td>
                        <td class="admin-table-nowrap"><span class="admin-status <?php echo (string) $log['status'] === 'completed' ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e((string) ($logStatusLabels[(string) $log['status']] ?? $log['status'])); ?></span></td>
                        <td class="admin-table-break"><?php echo sr_e($failureReason !== '' ? $failureReason : '-'); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<?php echo sr_admin_pagination_html($pagination, '포인트/금액 환전 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
