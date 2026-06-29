<?php

$adminPageTitle = '포인트/금액 환전 기준값';
$adminContainerClass = 'admin-page-asset-exchange admin-ui-scope';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$assets = isset($assets) && is_array($assets) ? $assets : [];
$relativeValues = isset($relativeValues) && is_array($relativeValues) ? $relativeValues : sr_asset_exchange_relative_values_from_settings($settings);
$policyPreviews = isset($policyPreviews) && is_array($policyPreviews) ? $policyPreviews : sr_asset_exchange_canonical_policy_rows_from_settings($settings);
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeBasisLabels = ['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'];
$feeTypeLabels = ['rate' => '정률', 'fixed' => '정액'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="admin-form ui-form-theme" data-asset-exchange-policy-form>
    <?php echo sr_csrf_field(); ?>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">자산별 상대 가치</h2>
            <div class="card-actions">
                <a href="<?php echo sr_e(sr_url('/admin/asset-exchange/settings')); ?>" class="btn btn-sm btn-outline-secondary">환경설정</a>
            </div>
        </div>
        <?php foreach (sr_asset_exchange_relative_value_setting_keys() as $moduleKey => $settingKey) { ?>
            <?php
            $label = sr_asset_exchange_asset_label($assets, (string) $moduleKey);
            $inputId = 'asset_exchange_' . (string) $settingKey;
            ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e($inputId); ?>"><?php echo sr_e($label); ?> 기준값 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e($inputId); ?>" type="number" name="<?php echo sr_e((string) $settingKey); ?>" value="<?php echo sr_e((string) ($relativeValues[$moduleKey] ?? 1)); ?>" class="form-input" min="1" required>
                </div>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">환전 조건</h2>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_policy_default_status">상태 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="asset_exchange_policy_default_status" name="policy_default_status" class="form-select" required>
                    <?php foreach ($policyStatusLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_status'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_policy_default_rounding_mode">반올림 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="asset_exchange_policy_default_rounding_mode" name="policy_default_rounding_mode" class="form-select" required>
                    <?php foreach ($roundingModeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_rounding_mode'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_policy_default_min_amount">최소 환전량 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_min_amount" type="number" name="policy_default_min_amount" value="<?php echo sr_e((string) $settings['policy_default_min_amount']); ?>" class="form-input" min="1" required>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_policy_default_max_amount">최대 환전량</label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_max_amount" type="number" name="policy_default_max_amount" value="<?php echo sr_e((string) $settings['policy_default_max_amount']); ?>" class="form-input" min="0">
                <p class="form-help">비워두면 1회 최대 금액을 제한하지 않습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_policy_default_fee_trigger">수수료 적용 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="asset_exchange_policy_default_fee_trigger" name="policy_default_fee_trigger" class="form-select" required>
                    <?php foreach ($feeTriggerLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_trigger'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row data-asset-exchange-policy-fee-type="rate">
            <label class="form-label" for="asset_exchange_policy_default_fee_basis">수수료 기준 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="asset_exchange_policy_default_fee_basis" name="policy_default_fee_basis" class="form-select" required>
                    <?php foreach ($feeBasisLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_basis'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row>
            <label class="form-label" for="asset_exchange_policy_default_fee_type">수수료 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <select id="asset_exchange_policy_default_fee_type" name="policy_default_fee_type" class="form-select" required>
                    <?php foreach ($feeTypeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_type'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row data-asset-exchange-policy-fee-type="rate">
            <label class="form-label" for="asset_exchange_policy_default_fee_rate_numerator">정률 수수료 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_fee_rate_numerator" type="number" name="policy_default_fee_rate_numerator" value="<?php echo sr_e((string) $settings['policy_default_fee_rate_numerator']); ?>" class="form-input" min="0">
                <p class="form-help">5%라면 5를 입력합니다.</p>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row data-asset-exchange-policy-fee-type="fixed">
            <label class="form-label" for="asset_exchange_policy_default_fee_fixed_amount">정액 수수료 <span class="sr-required-label">(필수)</span></label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_fee_fixed_amount" type="number" name="policy_default_fee_fixed_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_fixed_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row>
            <label class="form-label" for="asset_exchange_policy_default_fee_min_amount">최소 수수료</label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_fee_min_amount" type="number" name="policy_default_fee_min_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_min_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="form-row" data-asset-exchange-policy-fee-row>
            <label class="form-label" for="asset_exchange_policy_default_fee_max_amount">최대 수수료</label>
            <div class="form-field">
                <input id="asset_exchange_policy_default_fee_max_amount" type="number" name="policy_default_fee_max_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_max_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
    </section>

    <section class="card admin-list-card">
        <div class="card-header">
            <h2 class="card-title">파생 환전표</h2>
        </div>
        <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th>출금 항목</th>
                        <th>입금 항목</th>
                        <th>환전 비율</th>
                        <th>금액 제한</th>
                        <th>반올림</th>
                        <th>수수료</th>
                        <th>상태</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($policyPreviews as $policy) { ?>
                        <?php
                        $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
                        $toModuleKey = (string) ($policy['to_module_key'] ?? '');
                        $fromMissing = !isset($assets[$fromModuleKey]);
                        $toMissing = !isset($assets[$toModuleKey]);
                        $status = (string) ($policy['status'] ?? 'disabled');
                        ?>
                        <tr>
                            <td>
                                <?php echo sr_e(sr_asset_exchange_asset_label($assets, $fromModuleKey)); ?>
                                <?php if ($fromMissing) { ?>
                                    <br><small>비활성</small>
                                <?php } ?>
                            </td>
                            <td>
                                <?php echo sr_e(sr_asset_exchange_asset_label($assets, $toModuleKey)); ?>
                                <?php if ($toMissing) { ?>
                                    <br><small>비활성</small>
                                <?php } ?>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e('출금 ' . number_format((int) $policy['rate_denominator']) . '당 입금 ' . number_format((int) $policy['rate_numerator'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) $policy['min_amount']) . ' - ' . ($policy['max_amount'] === null ? '제한 없음' : number_format((int) $policy['max_amount']))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($roundingModeLabels[(string) $policy['rounding_mode']] ?? $policy['rounding_mode'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($feeTriggerLabels[(string) $policy['fee_trigger']] ?? $policy['fee_trigger'])); ?></td>
                            <td class="admin-table-nowrap">
                                <span class="admin-status <?php echo $status === 'enabled' ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e((string) ($policyStatusLabels[$status] ?? $status)); ?></span>
                                <?php if ($status === 'enabled' && ($fromMissing || $toMissing)) { ?>
                                    <br><small>실행 불가</small>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/asset-exchange/logs')); ?>" class="btn btn-solid-light">환전 내역</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    var form = document.querySelector('[data-asset-exchange-policy-form]');
    if (!form) {
        return;
    }

    var trigger = form.querySelector('#asset_exchange_policy_default_fee_trigger');
    var type = form.querySelector('#asset_exchange_policy_default_fee_type');
    var rows = form.querySelectorAll('[data-asset-exchange-policy-fee-row]');

    function setControls(row, enabled) {
        row.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.disabled = !enabled;
            if (
                control.id === 'asset_exchange_policy_default_fee_basis'
                || control.id === 'asset_exchange_policy_default_fee_type'
                || control.id === 'asset_exchange_policy_default_fee_rate_numerator'
                || control.id === 'asset_exchange_policy_default_fee_fixed_amount'
            ) {
                control.required = enabled;
            }
        });
    }

    function syncFeeRows() {
        var usesFee = trigger && trigger.value !== 'none';
        var feeType = type ? type.value : 'rate';

        rows.forEach(function (row) {
            var rowType = row.getAttribute('data-asset-exchange-policy-fee-type');
            var visible = usesFee && (!rowType || rowType === feeType);
            row.hidden = !visible;
            setControls(row, visible);
        });
    }

    if (trigger) {
        trigger.addEventListener('change', syncFeeRows);
    }
    if (type) {
        type.addEventListener('change', syncFeeRows);
    }
    syncFeeRows();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
