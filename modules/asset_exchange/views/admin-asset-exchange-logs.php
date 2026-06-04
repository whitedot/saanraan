<?php

$adminPageTitle = '포인트/금액 환전 로그';
$logStatusLabels = ['completed' => '완료', 'failed' => '실패'];
$logFilters = isset($logFilters) && is_array($logFilters) ? $logFilters : ['status' => [], 'asset' => [], 'field' => 'all', 'q' => ''];
$selectedLogStatuses = is_array($logFilters['status'] ?? null) ? $logFilters['status'] : [];
$selectedLogAssets = is_array($logFilters['asset'] ?? null) ? $logFilters['asset'] : [];
$logDetailFilterOpen = $selectedLogStatuses !== [] || $selectedLogAssets !== [];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="table-filtering-form admin-asset-exchange-log-filter ui-form-theme">
    <div class="table-filtering-fields admin-asset-exchange-log-search-grid">
        <div class="table-filtering table-filtering-card<?php echo $logDetailFilterOpen ? ' table-filtering-open' : ''; ?>" data-table-filtering>
            <div class="table-filtering-fields">
                <div class="table-filtering-field admin-asset-exchange-log-filter-field">
                    <label for="asset_exchange_log_filter_field" class="table-filtering-label">검색조건</label>
                    <select id="asset_exchange_log_filter_field" name="field" class="form-select table-filtering-input">
                        <?php foreach (['all' => '전체', 'member' => '회원', 'group_id' => '환전 묶음 ID'] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($logFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="table-filtering-field-fill table-filtering-field admin-asset-exchange-log-filter-keyword">
                    <label for="asset_exchange_log_filter_q" class="table-filtering-label">검색어</label>
                    <input id="asset_exchange_log_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($logFilters['q'] ?? '')); ?>" class="form-input table-filtering-input" maxlength="120" placeholder="회원, 환전 묶음 ID">
                </div>
            </div>
            <div id="asset_exchange_log_detail_filters" class="table-filtering-body" data-table-filtering-body<?php echo $logDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="table-filtering-field admin-asset-exchange-log-filter-status">
                    <span class="table-filtering-label">상태</span>
                    <?php echo sr_admin_filter_toggle_group_html('asset_exchange_log_filter_status', 'status', $logStatusLabels, $selectedLogStatuses, '전체'); ?>
                </div>
                <div class="table-filtering-field admin-asset-exchange-log-filter-asset">
                    <label for="asset_exchange_log_filter_asset" class="table-filtering-label">항목</label>
                    <select id="asset_exchange_log_filter_asset" name="asset" class="form-select table-filtering-input">
                        <option value="">전체</option>
                        <?php foreach ($assets as $asset) { ?>
                            <?php $moduleKey = (string) ($asset['module_key'] ?? ''); ?>
                            <option value="<?php echo sr_e($moduleKey); ?>"<?php echo in_array($moduleKey, $selectedLogAssets, true) ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($asset['label'] ?? $moduleKey)); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="table-filtering-actions">
                <button type="button" class="btn btn-solid-light table-filtering-toggle" data-table-filtering-toggle aria-expanded="<?php echo $logDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="asset_exchange_log_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light" data-table-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
                <button type="submit" class="btn btn-solid-primary table-filtering-submit">검색</button>
            </div>
        </div>
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
