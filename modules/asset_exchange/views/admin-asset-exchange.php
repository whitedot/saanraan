<?php

$adminPageTitle = '환전 정책';
$adminContainerClass = 'admin-page-asset-exchange admin-ui-scope';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$assets = isset($assets) && is_array($assets) ? $assets : [];
$relativeValues = isset($relativeValues) && is_array($relativeValues) ? $relativeValues : sr_asset_exchange_relative_values_from_settings($settings);
$policySlots = isset($policySlots) && is_array($policySlots) ? $policySlots : [];
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeBasisLabels = ['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'];
$feeTypeLabels = ['rate' => '정률', 'fixed' => '정액'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];

$assetExchangePolicyFeeType = static function (array $policy): string {
    return (int) ($policy['fee_fixed_amount'] ?? 0) > 0 ? 'fixed' : 'rate';
};
$assetExchangePolicyMinimumExchangeAmounts = static function (array $policy): array {
    $numerator = max(1, (int) ($policy['rate_numerator'] ?? 1));
    $denominator = max(1, (int) ($policy['rate_denominator'] ?? 1));
    $roundingMode = (string) ($policy['rounding_mode'] ?? 'floor');
    if ($roundingMode === 'ceil') {
        $minimumFromAmount = 1;
    } elseif ($roundingMode === 'round') {
        $minimumProduct = intdiv($denominator + 1, 2);
        $minimumFromAmount = intdiv($minimumProduct + $numerator - 1, $numerator);
    } else {
        $minimumFromAmount = intdiv($denominator + $numerator - 1, $numerator);
    }
    $minimumFromAmount = max(1, $minimumFromAmount);
    $minimumToAmount = max(1, sr_asset_exchange_apply_ratio($minimumFromAmount, $numerator, $denominator, $roundingMode));

    return [$minimumFromAmount, $minimumToAmount];
};
$assetExchangePolicyTitleLabel = static function (array $policy, array $assets) use ($assetExchangePolicyMinimumExchangeAmounts): string {
    $fromModuleKey = (string) ($policy['from_module_key'] ?? '');
    $toModuleKey = (string) ($policy['to_module_key'] ?? '');
    [$minimumFromAmount, $minimumToAmount] = $assetExchangePolicyMinimumExchangeAmounts($policy);

    return sr_asset_exchange_asset_label($assets, $fromModuleKey)
        . '('
        . number_format($minimumFromAmount)
        . ') -> '
        . sr_asset_exchange_asset_label($assets, $toModuleKey)
        . '('
        . number_format($minimumToAmount)
        . ')';
};
$assetExchangePolicyLimitLabel = static function (array $policy): string {
    $maxAmount = $policy['max_amount'] ?? null;
    return number_format((int) ($policy['min_amount'] ?? 1)) . ' - ' . ($maxAmount === null ? '제한 없음' : number_format((int) $maxAmount));
};
$assetExchangePolicyFeeLabel = static function (array $policy) use ($feeTriggerLabels, $feeBasisLabels, $assetExchangePolicyFeeType): string {
    $feeTrigger = (string) ($policy['fee_trigger'] ?? 'none');
    if ($feeTrigger === 'none') {
        return '수수료 없음';
    }

    $feeType = $assetExchangePolicyFeeType($policy);
    $parts = [(string) ($feeTriggerLabels[$feeTrigger] ?? $feeTrigger)];
    if ($feeType === 'fixed') {
        $parts[] = '정액 ' . number_format((int) ($policy['fee_fixed_amount'] ?? 0));
    } else {
        $parts[] = '정률 ' . number_format((int) ($policy['fee_rate_numerator'] ?? 0)) . '%';
        $parts[] = (string) ($feeBasisLabels[(string) ($policy['fee_basis'] ?? 'to_amount')] ?? ($policy['fee_basis'] ?? 'to_amount'));
    }

    return implode(' · ', $parts);
};
$assetExchangePolicyFieldValue = static function (array $policy, string $key): string {
    if (!array_key_exists($key, $policy) || $policy[$key] === null) {
        return '';
    }

    return (string) $policy[$key];
};
$assetExchangePolicyForSlot = static function (array $slot) use ($settings): array {
    $fromModuleKey = (string) ($slot['from_module_key'] ?? '');
    $toModuleKey = (string) ($slot['to_module_key'] ?? '');
    $policy = isset($slot['policy']) && is_array($slot['policy']) ? $slot['policy'] : null;
    if (is_array($policy)) {
        return $policy;
    }

    foreach (sr_asset_exchange_canonical_policy_rows_from_settings($settings) as $defaultRow) {
        if ((string) ($defaultRow['from_module_key'] ?? '') === $fromModuleKey && (string) ($defaultRow['to_module_key'] ?? '') === $toModuleKey) {
            $defaultRow['id'] = 0;
            return $defaultRow;
        }
    }

    return [
        'id' => 0,
        'from_module_key' => $fromModuleKey,
        'to_module_key' => $toModuleKey,
        'status' => 'disabled',
        'rate_numerator' => 1,
        'rate_denominator' => 1,
        'min_amount' => 1,
        'max_amount' => null,
        'rounding_mode' => 'floor',
        'fee_trigger' => 'none',
        'fee_basis' => 'to_amount',
        'fee_rate_numerator' => 0,
        'fee_fixed_amount' => 0,
        'fee_min_amount' => null,
        'fee_max_amount' => null,
    ];
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<?php
$assetExchangeSectionNavItems = [
    'asset-exchange-section-values' => '환산 기준',
];
foreach ($policySlots as $assetExchangeNavSlot) {
    $assetExchangeNavFrom = (string) ($assetExchangeNavSlot['from_module_key'] ?? '');
    $assetExchangeNavTo = (string) ($assetExchangeNavSlot['to_module_key'] ?? '');
    if ($assetExchangeNavFrom === '' || $assetExchangeNavTo === '') {
        continue;
    }
    $assetExchangeNavPolicy = $assetExchangePolicyForSlot($assetExchangeNavSlot);
    $assetExchangeSectionNavItems['asset-exchange-section-policy-' . $assetExchangeNavFrom . '-' . $assetExchangeNavTo] =
        $assetExchangePolicyTitleLabel($assetExchangeNavPolicy, $assets);
}
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="환전 정책 설정 섹션">
    <?php $assetExchangeSectionNavIndex = 0; ?>
    <?php foreach ($assetExchangeSectionNavItems as $assetExchangeSectionId => $assetExchangeSectionLabel) { ?>
        <a href="#<?php echo sr_e($assetExchangeSectionId); ?>" class="tab-trigger-underline-justified<?php echo $assetExchangeSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $assetExchangeSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e($assetExchangeSectionLabel); ?>
        </a>
        <?php $assetExchangeSectionNavIndex++; ?>
    <?php } ?>
</nav>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="admin-form ui-form-theme admin-asset-exchange-admin-form" data-asset-exchange-policy-form data-sr-validate-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_all">

    <section id="asset-exchange-section-values" class="card admin-asset-exchange-value-card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">환산 기준</h2>
        </div>
        <?php foreach (sr_asset_exchange_relative_value_setting_keys() as $moduleKey => $settingKey) { ?>
            <?php $inputId = 'asset_exchange_' . (string) $settingKey; ?>
            <div class="form-row">
                <label class="form-label" for="<?php echo sr_e($inputId); ?>"><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $moduleKey)); ?> <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="<?php echo sr_e($inputId); ?>" type="number" name="<?php echo sr_e((string) $settingKey); ?>" value="<?php echo sr_e((string) ($relativeValues[$moduleKey] ?? 1)); ?>" class="form-input" min="1" required>
                    <p class="form-help">1단위 기준 환율입니다. 값이 낮을수록 같은 1단위의 가치가 큽니다.</p>
                </div>
            </div>
        <?php } ?>
        <div class="form-row">
            <span class="form-label">저장 영향</span>
            <div class="form-field">
                <p class="admin-form-static">기준값을 저장하면 각 방향 정책의 계산 비율만 새로 반영되고, 카드별 상태와 조건은 유지됩니다.</p>
            </div>
        </div>
    </section>

    <?php foreach ($policySlots as $slot) { ?>
        <?php
        $fromModuleKey = (string) ($slot['from_module_key'] ?? '');
        $toModuleKey = (string) ($slot['to_module_key'] ?? '');
        $policy = $assetExchangePolicyForSlot($slot);
        $status = (string) ($policy['status'] ?? 'disabled');
        $executable = !empty($slot['executable']);
        $fieldPrefix = 'asset_exchange_policy_' . $fromModuleKey . '_' . $toModuleKey;
        $feeType = $assetExchangePolicyFeeType($policy);
        $sectionId = 'asset-exchange-section-policy-' . $fromModuleKey . '-' . $toModuleKey;
        $slotKey = sr_asset_exchange_policy_slot_key($fromModuleKey, $toModuleKey);
        $policyFieldNamePrefix = 'policies[' . $slotKey . ']';
        ?>
        <section id="<?php echo sr_e($sectionId); ?>" class="card admin-asset-exchange-policy-card<?php echo $executable ? ' is-executable' : ''; ?>" data-asset-exchange-policy-section data-admin-section-anchor>
                <input type="hidden" name="<?php echo sr_e($policyFieldNamePrefix); ?>[id]" value="<?php echo sr_e((string) ((int) ($policy['id'] ?? 0))); ?>">
                <input type="hidden" name="<?php echo sr_e($policyFieldNamePrefix); ?>[from_module_key]" value="<?php echo sr_e($fromModuleKey); ?>">
                <input type="hidden" name="<?php echo sr_e($policyFieldNamePrefix); ?>[to_module_key]" value="<?php echo sr_e($toModuleKey); ?>">
                <div class="card-header">
                    <h2 class="card-title admin-asset-exchange-policy-card-title">
                        <span class="badge <?php echo $status === 'enabled' ? 'badge-soft-success' : 'badge-soft-secondary'; ?>"><?php echo sr_e((string) ($policyStatusLabels[$status] ?? $status)); ?></span>
                        <span><?php echo sr_e($assetExchangePolicyTitleLabel($policy, $assets)); ?></span>
                    </h2>
                    <label class="form-check" for="<?php echo sr_e($fieldPrefix); ?>_status">
                        <input type="hidden" name="<?php echo sr_e($policyFieldNamePrefix); ?>[status]" value="disabled">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_status" type="checkbox" name="<?php echo sr_e($policyFieldNamePrefix); ?>[status]" value="enabled" class="form-switch form-switch-light"<?php echo $status === 'enabled' ? ' checked' : ''; ?>>
                        <span class="sr-only"><?php echo sr_e(sr_asset_exchange_asset_label($assets, $fromModuleKey) . '에서 ' . sr_asset_exchange_asset_label($assets, $toModuleKey) . ' 환전 사용'); ?></span>
                    </label>
                </div>

                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_rounding_mode">반올림 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_rounding_mode" name="<?php echo sr_e($policyFieldNamePrefix); ?>[rounding_mode]" class="form-select" required>
                            <?php foreach ($roundingModeLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo (string) ($policy['rounding_mode'] ?? 'floor') === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">환산 비율로 계산한 입금액의 소수 처리 방식입니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_min_amount">최소 환전량 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_min_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[min_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'min_amount')); ?>" class="form-input" min="1" required>
                        <p class="form-help">회원이 한 번에 신청할 수 있는 최소 출금 수량입니다. 이 수량의 환전 결과가 0이면 저장할 수 없습니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_max_amount">최대 환전량</label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_max_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[max_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'max_amount')); ?>" class="form-input" min="0">
                        <p class="form-help">비워두면 1회 최대 환전량을 제한하지 않습니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_trigger">수수료 적용 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_trigger" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_trigger]" class="form-select" required data-asset-exchange-fee-trigger>
                            <?php foreach ($feeTriggerLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo (string) ($policy['fee_trigger'] ?? 'none') === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">수수료를 적용하지 않거나, 모든 환전 또는 재환전 상황에만 적용할 수 있습니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_type">수수료 방식 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_type" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_type]" class="form-select" required data-asset-exchange-fee-type-control>
                            <?php foreach ($feeTypeLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo $feeType === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">정률은 비율로 계산하고, 정액은 환전 1건마다 고정 금액을 적용합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="rate">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_basis">수수료 기준 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_basis" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_basis]" class="form-select" required>
                            <?php foreach ($feeBasisLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo (string) ($policy['fee_basis'] ?? 'to_amount') === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">정률 수수료를 출금액과 입금액 중 어느 금액에서 계산할지 정합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="rate">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_rate_numerator">정률 수수료 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_fee_rate_numerator" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_rate_numerator]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_rate_numerator')); ?>" class="form-input" min="0">
                        <p class="form-help">5%라면 5를 입력합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="fixed">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_fixed_amount">정액 수수료 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_fee_fixed_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_fixed_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_fixed_amount')); ?>" class="form-input" min="0">
                        <p class="form-help">환전 1건마다 차감할 고정 수수료입니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_min_amount">최소 수수료</label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_fee_min_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_min_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_min_amount')); ?>" class="form-input" min="0">
                        <p class="form-help">비워두면 최소 수수료를 강제하지 않습니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_max_amount">최대 수수료</label>
                    <div class="form-field">
                        <input id="<?php echo sr_e($fieldPrefix); ?>_fee_max_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_max_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_max_amount')); ?>" class="form-input" min="0">
                        <p class="form-help">비워두면 최대 수수료를 제한하지 않습니다.</p>
                    </div>
                </div>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary form-actions-split">
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<script>
(function () {
    function setControls(row, enabled) {
        row.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.disabled = !enabled;
            if (
                control.name === 'fee_basis'
                || control.name === 'fee_type'
                || control.name === 'fee_rate_numerator'
                || control.name === 'fee_fixed_amount'
            ) {
                control.required = enabled;
            }
        });
    }

    function syncFeeRows(form) {
        var trigger = form.querySelector('[data-asset-exchange-fee-trigger]');
        var type = form.querySelector('[data-asset-exchange-fee-type-control]');
        var rows = form.querySelectorAll('[data-asset-exchange-fee-row]');
        var usesFee = trigger && trigger.value !== 'none';
        var feeType = type ? type.value : 'rate';

        rows.forEach(function (row) {
            var rowType = row.getAttribute('data-asset-exchange-fee-type');
            var visible = usesFee && (!rowType || rowType === feeType);
            row.hidden = !visible;
            setControls(row, visible);
        });
    }

    document.querySelectorAll('[data-asset-exchange-policy-section]').forEach(function (section) {
        section.querySelectorAll('[data-asset-exchange-fee-trigger], [data-asset-exchange-fee-type-control]').forEach(function (control) {
            control.addEventListener('change', function () {
                syncFeeRows(section);
            });
        });
        syncFeeRows(section);
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
