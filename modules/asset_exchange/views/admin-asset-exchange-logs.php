<?php

$adminPageTitle = '포인트/금액 환전 로그';
$logStatusLabels = ['completed' => '완료', 'failed' => '실패'];
$logFilters = isset($logFilters) && is_array($logFilters) ? $logFilters : ['status' => [], 'asset' => [], 'field' => 'all', 'q' => ''];
$selectedLogStatuses = is_array($logFilters['status'] ?? null) ? $logFilters['status'] : [];
$selectedLogAssets = is_array($logFilters['asset'] ?? null) ? $logFilters['asset'] : [];
$logDetailFilterOpen = $selectedLogStatuses !== [] || $selectedLogAssets !== [];
$assetExchangeLogReturnTo = sr_admin_current_get_url('/admin/asset-exchange/logs');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="filtering-form admin-asset-exchange-log-filter ui-form-theme">
    <div class="filtering-fields admin-asset-exchange-log-search-grid">
        <div class="filtering filtering-card<?php echo $logDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields">
                <div class="filtering-field admin-asset-exchange-log-filter-field">
                    <label for="asset_exchange_log_filter_field" class="filtering-label">검색조건</label>
                    <select id="asset_exchange_log_filter_field" name="field" class="form-select filtering-input">
                        <?php foreach (['all' => '전체', 'member' => '회원', 'group_id' => '환전 묶음 ID'] as $fieldValue => $fieldLabel) { ?>
                            <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($logFilters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>><?php echo sr_e($fieldLabel); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="filtering-field-fill filtering-field admin-asset-exchange-log-filter-keyword">
                    <label for="asset_exchange_log_filter_q" class="filtering-label">검색어</label>
                    <input id="asset_exchange_log_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($logFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="회원, 환전 묶음 ID">
                </div>
            </div>
            <div id="asset_exchange_log_detail_filters" class="filtering-body" data-filtering-body<?php echo $logDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field admin-asset-exchange-log-filter-status">
                    <span class="filtering-label">상태</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('asset_exchange_log_filter_status', 'status', $logStatusLabels, $selectedLogStatuses, '전체'); ?>
                </div>
                <div class="filtering-field admin-asset-exchange-log-filter-asset">
                    <span class="filtering-label">항목</span>
                    <?php
                    $assetExchangeLogAssetOptions = [];
                    foreach ($assets as $asset) {
                        $moduleKey = (string) ($asset['module_key'] ?? '');
                        if ($moduleKey !== '') {
                            $assetExchangeLogAssetOptions[$moduleKey] = (string) ($asset['label'] ?? $moduleKey);
                        }
                    }
                    echo sr_admin_filter_radio_toggle_group_html('asset_exchange_log_filter_asset', 'asset', $assetExchangeLogAssetOptions, $selectedLogAssets, '전체');
                    ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $logDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="asset_exchange_log_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<section class="card admin-list-card admin-list-form">
    <div class="card-header"><h2 class="card-title">환전 로그 목록</h2></div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($pagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
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
                    <th>작업</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($logs === []) { ?>
                    <tr><td colspan="10" class="admin-empty-state">환전 로그가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($logs as $log) { ?>
                    <?php $failureReason = trim((string) ($log['failure_reason'] ?? '')); ?>
                    <?php $canCorrectLog = (string) ($log['status'] ?? '') === 'completed' && (int) ($log['request_amount'] ?? 0) > 0 && (int) ($log['deposit_amount'] ?? 0) > 0 && strpos($failureReason, 'correction_for:') !== 0; ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_asset_exchange_time_html((string) $log['created_at']); ?></td>
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
                        <td class="admin-table-nowrap">
                            <?php if ($canCorrectLog) { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="admin-inline-form">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="correct_completed_group">
                                    <input type="hidden" name="exchange_group_id" value="<?php echo sr_e((string) $log['exchange_group_id']); ?>">
                                    <input type="hidden" name="correction_reason" value="관리자 환전 정정">
                                    <input type="hidden" name="return_to" value="<?php echo sr_e($assetExchangeLogReturnTo); ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="환전 묶음 정정" title="환전 묶음 정정" onclick="return confirm('이 완료 환전 묶음을 반대 원장 거래로 정정할까요?');"><?php echo sr_material_icon_html('undo'); ?></button>
                                </form>
                            <?php } else { ?>
                                <span class="text-muted">-</span>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('asset_exchange_log_status', $logStatusLabels); ?>
</section>

<?php echo sr_admin_pagination_html($pagination, '포인트/금액 환전 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
