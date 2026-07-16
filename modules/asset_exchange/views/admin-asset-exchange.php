<?php

$adminPageTitle = '포인트·금액 교환 환경설정';
$adminContainerClass = 'admin-page-asset-exchange admin-ui-scope';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$assets = isset($assets) && is_array($assets) ? $assets : [];
$assetExchangeAssets = isset($assetExchangeAssets) && is_array($assetExchangeAssets) ? $assetExchangeAssets : $assets;
$assetExchangeAvailable = isset($assetExchangeAvailable) ? (bool) $assetExchangeAvailable : count($assetExchangeAssets) >= 2;
$assetExchangeInputAttributes = $assetExchangeAvailable
    ? ''
    : ' disabled aria-describedby="asset-exchange-settings-unavailable"';
$assetExchangeIdentityAvailable = isset($assetExchangeIdentityAvailable)
    ? (bool) $assetExchangeIdentityAvailable
    : (function_exists('sr_identity_verification_available') && sr_identity_verification_available($pdo, 'asset.exchange'));
$assetExchangeIdentityVerificationInputAttributes = $assetExchangeIdentityAvailable
    ? ''
    : ' disabled aria-describedby="asset-exchange-settings-identity-unavailable"';
$assetExchangeIdentityModuleReferences = [['module_key' => 'identity_verification', 'path' => '/admin/identity-providers']];
$policySlots = isset($policySlots) && is_array($policySlots) ? $policySlots : [];
$assetExchangePostedPolicies = isset($assetExchangePostedPolicies) && is_array($assetExchangePostedPolicies) ? $assetExchangePostedPolicies : [];
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용'];
$feeBasisLabels = ['from_amount' => '차감 수량 기준', 'to_amount' => '지급 수량 기준'];
$feeTypeLabels = ['rate' => '비율', 'fixed' => '고정 금액'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];
$assetExchangeHelpOpenLabel = '도움말 보기';
$assetExchangeHelpButtonHtml = static function (string $label, string $modalId) use ($assetExchangeHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $assetExchangeHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$assetExchangeHelp = [
    'rate' => [
        'id' => 'asset-exchange-help-rate',
        'title' => '교환 비율 도움말',
        'body' => '<p>회원이 신청한 차감 수량에 지급 기준 수량을 곱한 뒤 차감 기준 수량으로 나누어 수수료 전 지급액을 계산합니다. 예를 들어 차감 100, 지급 80으로 설정하면 100을 차감할 때 80을 지급합니다.</p>'
            . '<p>교환 방향마다 비율을 따로 저장합니다. 반대 방향을 함께 사용하더라도 두 방향의 값을 서로 자동 계산하거나 맞추지 않으므로 각각 확인하세요.</p>',
    ],
    'amount' => [
        'id' => 'asset-exchange-help-amount',
        'title' => '1회 교환 수량 도움말',
        'body' => '<p>최소·최대 수량은 회원이 한 번의 신청에서 차감할 수 있는 수량을 제한합니다. 최소 수량은 교환과 소수점 처리 후 지급액이 1 이상이 되는 값이어야 합니다.</p>'
            . '<p>수수료를 사용하면 최소 수량으로 계산한 최종 지급액도 1 이상이어야 저장할 수 있습니다. 화면의 계산 결과를 확인하고 필요하면 최소 수량, 교환 비율 또는 수수료를 조정하세요.</p>',
    ],
    'rounding' => [
        'id' => 'asset-exchange-help-rounding',
        'title' => '소수점 처리 도움말',
        'body' => '<p>교환 비율 계산 결과에 소수점이 생겼을 때 버림, 반올림, 올림 중 어떤 방식으로 정수 수량을 만들지 정합니다. 같은 방식이 비율 수수료 계산 결과에도 별도로 적용됩니다.</p>'
            . '<p>지급 예정액과 비율 수수료를 각각 정수로 만든 뒤 수수료를 빼므로, 설정을 바꾸면 회원의 최종 지급 수량도 달라질 수 있습니다.</p>',
    ],
    'fee' => [
        'id' => 'asset-exchange-help-fee',
        'title' => '교환 수수료 도움말',
        'body' => '<p>수수료는 교환 비율로 계산한 지급 예정액에서 차감하며, 단위는 회원이 지급받을 포인트·금액 항목입니다. 비율 방식은 선택한 차감 수량 또는 지급 수량에 비율을 적용하고, 고정 금액 방식은 신청 한 건마다 같은 수량을 뺍니다.</p>'
            . '<p>차감 수량 기준을 선택해도 계산된 숫자를 지급 항목의 수수료 수량으로 사용합니다. 예를 들어 차감 수량 100에 수수료 5%를 적용하면 지급 항목에서 5를 뺍니다.</p>'
            . '<p>최소·최대 수수료는 계산된 수수료를 각각 그 값 이상 또는 이하로 조정합니다. 최종 지급액이 0 이하가 되는 설정은 저장할 수 없습니다.</p>',
    ],
];

$assetExchangePolicyFeeType = static function (array $policy): string {
    return (int) ($policy['fee_fixed_amount'] ?? 0) > 0 ? 'fixed' : 'rate';
};
$assetExchangePolicyMinimumExchangeAmounts = static function (array $policy): array {
    $numerator = max(1, (int) ($policy['rate_numerator'] ?? 1));
    $denominator = max(1, (int) ($policy['rate_denominator'] ?? 1));
    $roundingMode = (string) ($policy['rounding_mode'] ?? 'floor');
    $minimumFromAmount = sr_asset_exchange_minimum_request_amount_for_positive_deposit($numerator, $denominator, $roundingMode);
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
    $parts = [(string) ($feeTriggerLabels[$feeTrigger] ?? '사용 안 함')];
    if ($feeType === 'fixed') {
        $parts[] = '고정 금액 ' . number_format((int) ($policy['fee_fixed_amount'] ?? 0));
    } else {
        $parts[] = '비율 ' . number_format((int) ($policy['fee_rate_numerator'] ?? 0)) . '%';
        $parts[] = (string) ($feeBasisLabels[(string) ($policy['fee_basis'] ?? 'to_amount')] ?? ($policy['fee_basis'] ?? 'to_amount'));
    }

    return implode(' · ', $parts);
};
$assetExchangeAssetUnitLabel = static function (array $assets, string $moduleKey): string {
    $unitLabel = trim((string) ($assets[$moduleKey]['unit_label'] ?? ''));

    return $unitLabel !== '' ? $unitLabel : sr_asset_exchange_asset_label($assets, $moduleKey);
};
$assetExchangePolicyFieldValue = static function (array $policy, string $key): string {
    if (!array_key_exists($key, $policy) || $policy[$key] === null) {
        return '';
    }

    return (string) $policy[$key];
};
$assetExchangePolicyForSlot = static function (array $slot) use ($settings, $assetExchangePostedPolicies): array {
    $fromModuleKey = (string) ($slot['from_module_key'] ?? '');
    $toModuleKey = (string) ($slot['to_module_key'] ?? '');
    $policy = isset($slot['policy']) && is_array($slot['policy']) ? $slot['policy'] : null;
    if (is_array($policy)) {
        $resolvedPolicy = $policy;
    } else {
        $resolvedPolicy = [];
        foreach (sr_asset_exchange_canonical_policy_rows_from_settings($settings) as $defaultRow) {
            if ((string) ($defaultRow['from_module_key'] ?? '') === $fromModuleKey && (string) ($defaultRow['to_module_key'] ?? '') === $toModuleKey) {
                $defaultRow['id'] = 0;
                $resolvedPolicy = $defaultRow;
                break;
            }
        }
    }

    if ($resolvedPolicy === []) {
        $resolvedPolicy = [
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
    }

    $slotKey = sr_asset_exchange_policy_slot_key($fromModuleKey, $toModuleKey);
    if (isset($assetExchangePostedPolicies[$slotKey]) && is_array($assetExchangePostedPolicies[$slotKey])) {
        $resolvedPolicy = array_merge($resolvedPolicy, $assetExchangePostedPolicies[$slotKey]);
        $resolvedPolicy['from_module_key'] = $fromModuleKey;
        $resolvedPolicy['to_module_key'] = $toModuleKey;
    }

    return $resolvedPolicy;
};

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<?php
$assetExchangeSectionNavItems = [
    'asset-exchange-section-settings' => '기본 설정',
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
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="포인트·금액 교환 환경설정 섹션">
    <?php $assetExchangeSectionNavIndex = 0; ?>
    <?php foreach ($assetExchangeSectionNavItems as $assetExchangeSectionId => $assetExchangeSectionLabel) { ?>
        <a href="#<?php echo sr_e($assetExchangeSectionId); ?>" class="tab-trigger-underline-justified<?php echo $assetExchangeSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $assetExchangeSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e($assetExchangeSectionLabel); ?>
        </a>
        <?php $assetExchangeSectionNavIndex++; ?>
    <?php } ?>
</nav>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="admin-form ui-form-theme admin-asset-exchange-admin-form" data-asset-exchange-policy-form data-asset-exchange-settings-form data-sr-validate-form novalidate>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_all">

    <section id="asset-exchange-section-settings" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">기본 설정</h2>
        </div>
        <div class="form-row">
            <span class="form-label">포인트·금액 교환</span>
            <div class="form-field">
                <?php echo sr_admin_switch_html('asset_exchange_settings_exchange_enabled', 'exchange_enabled', '1', $assetExchangeAvailable && (string) ($settings['exchange_enabled'] ?? '1') === '1', '사용', '0', $assetExchangeInputAttributes); ?>
                <p class="form-help">끄면 모든 방향의 새 교환 신청과 실행을 막습니다. 기존 교환 내역 조회와 정정은 유지됩니다.</p>
                <?php if (!$assetExchangeAvailable) { ?>
                    <p id="asset-exchange-settings-unavailable" class="form-help form-help-warning">
                        교환할 수 있는 포인트·금액 모듈이 2개 이상 설치되어 있고 활성화되어야 사용할 수 있습니다.
                    </p>
                <?php } ?>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="asset_exchange_settings_identity_exchange_required">교환 신청 본인확인</label>
            <div class="form-field">
                <?php echo sr_admin_switch_html('asset_exchange_settings_identity_exchange_required', 'identity_exchange_required', '1', $assetExchangeIdentityAvailable && (string) ($settings['identity_exchange_required'] ?? '0') === '1', '사용', '', $assetExchangeIdentityVerificationInputAttributes); ?>
                <p class="form-help">사용하면 회원이 교환을 실행할 때마다 본인확인을 요구합니다.</p>
                <?php echo sr_admin_module_reference_list_html($pdo, $assetExchangeIdentityModuleReferences); ?>
                <?php if (!$assetExchangeIdentityAvailable) { ?>
                    <p id="asset-exchange-settings-identity-unavailable" class="form-help form-help-warning">
                        <a href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>" target="_blank" rel="noopener noreferrer">본인확인 환경설정</a>에서 본인확인 사용이 꺼져 있거나 자산 환전 신청 목적을 지원하는 제공자가 준비되지 않아 설정을 사용할 수 없습니다.
                    </p>
                <?php } ?>
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
        $policyAlertId = $fieldPrefix . '_policy_alert';
        ?>
        <section id="<?php echo sr_e($sectionId); ?>" class="card admin-asset-exchange-policy-card<?php echo $executable ? ' is-executable' : ''; ?>" data-asset-exchange-policy-section data-asset-exchange-from="<?php echo sr_e($fromModuleKey); ?>" data-asset-exchange-to="<?php echo sr_e($toModuleKey); ?>" data-asset-exchange-label="<?php echo sr_e(sr_asset_exchange_asset_label($assets, $fromModuleKey) . ' -> ' . sr_asset_exchange_asset_label($assets, $toModuleKey)); ?>" data-asset-exchange-from-unit="<?php echo sr_e($assetExchangeAssetUnitLabel($assets, $fromModuleKey)); ?>" data-asset-exchange-to-unit="<?php echo sr_e($assetExchangeAssetUnitLabel($assets, $toModuleKey)); ?>" data-admin-section-anchor>
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
                <div id="<?php echo sr_e($policyAlertId); ?>" class="alert alert-danger admin-asset-exchange-policy-alert" role="alert" tabindex="-1" hidden data-asset-exchange-policy-alert></div>

                <div class="form-row admin-asset-exchange-rate-row">
                    <span class="form-label form-label-help">
                        <?php echo $assetExchangeHelpButtonHtml('교환 비율', $assetExchangeHelp['rate']['id']); ?>
                        <span>교환 비율 <span class="sr-required-label">(필수)</span></span>
                    </span>
                    <div class="form-field">
                        <div class="admin-asset-exchange-rate-grid">
                            <label class="admin-asset-exchange-rate-field" for="<?php echo sr_e($fieldPrefix); ?>_rate_denominator">
                                <span>차감 기준 수량</span>
                                <span class="input-group admin-input-unit">
                                    <input id="<?php echo sr_e($fieldPrefix); ?>_rate_denominator" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[rate_denominator]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'rate_denominator')); ?>" class="form-input" min="1" required>
                                    <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $fromModuleKey)); ?></span>
                                </span>
                            </label>
                            <label class="admin-asset-exchange-rate-field" for="<?php echo sr_e($fieldPrefix); ?>_rate_numerator">
                                <span>지급 기준 수량</span>
                                <span class="input-group admin-input-unit">
                                    <input id="<?php echo sr_e($fieldPrefix); ?>_rate_numerator" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[rate_numerator]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'rate_numerator')); ?>" class="form-input" min="1" required>
                                    <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $toModuleKey)); ?></span>
                                </span>
                            </label>
                        </div>
                        <p class="form-help">차감 기준 수량만큼 뺄 때 지급 기준 수량만큼 줍니다.</p>
                        <div class="admin-asset-exchange-preview" data-asset-exchange-preview>
                            <span class="badge badge-soft-secondary" data-asset-exchange-preview-status>확인 중</span>
                        </div>
                    </div>
                </div>
                <?php $minAmountHelpId = $fieldPrefix . '_min_amount_help'; ?>
                <?php $maxAmountHelpId = $fieldPrefix . '_max_amount_help'; ?>
                <div class="form-row admin-asset-exchange-amount-row">
                    <span class="form-label form-label-help">
                        <?php echo $assetExchangeHelpButtonHtml('1회 교환 수량', $assetExchangeHelp['amount']['id']); ?>
                        <span>1회 교환 수량</span>
                    </span>
                    <div class="form-field">
                        <div class="admin-asset-exchange-rate-grid">
                            <label class="admin-asset-exchange-rate-field" for="<?php echo sr_e($fieldPrefix); ?>_min_amount">
                                <span>최소 차감 수량 <span class="sr-required-label">(필수)</span></span>
                                <span class="input-group admin-input-unit">
                                    <input id="<?php echo sr_e($fieldPrefix); ?>_min_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[min_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'min_amount')); ?>" class="form-input" min="1" required aria-describedby="<?php echo sr_e($minAmountHelpId); ?>">
                                    <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $fromModuleKey)); ?></span>
                                </span>
                            </label>
                            <label class="admin-asset-exchange-rate-field" for="<?php echo sr_e($fieldPrefix); ?>_max_amount">
                                <span>최대 차감 수량</span>
                                <span class="input-group admin-input-unit">
                                    <input id="<?php echo sr_e($fieldPrefix); ?>_max_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[max_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'max_amount')); ?>" class="form-input" min="0" aria-describedby="<?php echo sr_e($maxAmountHelpId); ?>">
                                    <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $fromModuleKey)); ?></span>
                                </span>
                            </label>
                        </div>
                        <p id="<?php echo sr_e($minAmountHelpId); ?>" class="form-help">회원이 한 번에 차감할 수 있는 최소 수량입니다.</p>
                        <p id="<?php echo sr_e($maxAmountHelpId); ?>" class="form-help">비워 두면 한 번에 차감할 수 있는 최대 수량을 제한하지 않습니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html($fieldPrefix . '_rounding_mode', '소수점 처리', $assetExchangeHelp['rounding']['id'], $assetExchangeHelpOpenLabel, true, true); ?>
                    <div class="form-field">
                        <?php echo sr_admin_radio_toggle_group_html($fieldPrefix . '_rounding_mode', $policyFieldNamePrefix . '[rounding_mode]', $roundingModeLabels, (string) ($policy['rounding_mode'] ?? 'floor'), true); ?>
                        <p class="form-help">교환 지급액과 비율 수수료의 소수점을 정수로 처리하는 방식입니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html($fieldPrefix . '_fee_trigger', '수수료 적용', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true, true); ?>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_trigger" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_trigger]" class="form-select" required data-asset-exchange-fee-trigger>
                            <?php foreach ($feeTriggerLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo (string) ($policy['fee_trigger'] ?? 'none') === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">수수료를 사용하면 모든 교환 신청에 적용합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <?php echo sr_admin_form_label_help_html($fieldPrefix . '_fee_type', '수수료 방식', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_type" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_type]" class="form-select" required data-asset-exchange-fee-type-control>
                            <?php foreach ($feeTypeLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo $feeType === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">비율로 계산하거나 교환 한 건마다 같은 수량을 뺍니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="rate">
                    <?php echo sr_admin_form_label_help_html($fieldPrefix . '_fee_basis', '비율 수수료 계산 기준', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="<?php echo sr_e($fieldPrefix); ?>_fee_basis" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_basis]" class="form-select" required>
                            <?php foreach ($feeBasisLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo (string) ($policy['fee_basis'] ?? 'to_amount') === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help">차감 수량과 수수료 전 지급 수량 중 계산에 사용할 값을 선택합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="rate">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_rate_numerator">비율 수수료 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <div class="input-group admin-input-unit">
                            <input id="<?php echo sr_e($fieldPrefix); ?>_fee_rate_numerator" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_rate_numerator]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_rate_numerator')); ?>" class="form-input" min="0">
                            <span class="input-group-text">%</span>
                        </div>
                        <p class="form-help">5%를 적용하려면 5를 입력합니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row data-asset-exchange-fee-type="fixed">
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_fixed_amount">고정 수수료 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <div class="input-group admin-input-unit">
                            <input id="<?php echo sr_e($fieldPrefix); ?>_fee_fixed_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_fixed_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_fixed_amount')); ?>" class="form-input" min="0">
                            <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $toModuleKey)); ?></span>
                        </div>
                        <p class="form-help">교환 한 건마다 지급 예정액에서 뺄 수량입니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_min_amount">최소 수수료</label>
                    <div class="form-field">
                        <div class="input-group admin-input-unit">
                            <input id="<?php echo sr_e($fieldPrefix); ?>_fee_min_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_min_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_min_amount')); ?>" class="form-input" min="0">
                            <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $toModuleKey)); ?></span>
                        </div>
                        <p class="form-help">비워 두면 계산된 수수료의 최솟값을 따로 두지 않습니다.</p>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-row>
                    <label class="form-label" for="<?php echo sr_e($fieldPrefix); ?>_fee_max_amount">최대 수수료</label>
                    <div class="form-field">
                        <div class="input-group admin-input-unit">
                            <input id="<?php echo sr_e($fieldPrefix); ?>_fee_max_amount" type="number" name="<?php echo sr_e($policyFieldNamePrefix); ?>[fee_max_amount]" value="<?php echo sr_e($assetExchangePolicyFieldValue($policy, 'fee_max_amount')); ?>" class="form-input" min="0">
                            <span class="input-group-text"><?php echo sr_e($assetExchangeAssetUnitLabel($assets, $toModuleKey)); ?></span>
                        </div>
                        <p class="form-help">비워 두면 계산된 수수료의 최댓값을 따로 두지 않습니다.</p>
                    </div>
                </div>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-primary form-actions-split">
        <button type="button" class="btn btn-outline-secondary" data-asset-exchange-enable-all>
            전체 사용
        </button>
        <button type="button" class="btn btn-solid-light" data-asset-exchange-disable-all>
            전체 해제
        </button>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php foreach ($assetExchangeHelp as $assetExchangeHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $assetExchangeHelpModal['id'], (string) $assetExchangeHelpModal['title'], (string) $assetExchangeHelpModal['body']); ?>
<?php } ?>

<script>
(function () {
    var policyForm = document.querySelector('[data-asset-exchange-policy-form]');

    function nameEndsWith(control, suffix) {
        return control.name && control.name.slice(-suffix.length) === suffix;
    }

    function numberValue(control, fallback) {
        if (!control || String(control.value || '').trim() === '') {
            return fallback;
        }
        var parsed = parseInt(control.value, 10);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('ko-KR');
    }

    function formatAssetAmount(value, unit) {
        return formatNumber(value) + (unit ? ' ' + unit : '');
    }

    function setControlError(control, message, note) {
        if (!control) {
            return;
        }
        if (typeof control.setCustomValidity === 'function') {
            control.setCustomValidity(message);
        }
        if (message) {
            control.setAttribute('aria-invalid', 'true');
        } else {
            control.removeAttribute('aria-invalid');
        }
        if (note) {
            note.textContent = message;
            note.hidden = message === '';
        }
    }

    function clearNode(node) {
        while (node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function appendAlertText(parent, tagName, className, text) {
        if (!text) {
            return null;
        }
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        node.textContent = text;
        parent.appendChild(node);

        return node;
    }

    function setSectionAlert(section, details) {
        var alert = section.querySelector('[data-asset-exchange-policy-alert]');
        if (!alert) {
            return;
        }
        clearNode(alert);
        if (!details) {
            alert.hidden = true;
            return;
        }
        if (typeof details === 'string') {
            details = {problem: details};
        }
        appendAlertText(alert, 'strong', 'admin-asset-exchange-policy-alert-title', details.title || '저장할 수 없습니다');
        appendAlertText(alert, 'p', 'admin-asset-exchange-policy-alert-problem', details.problem || '');
        appendAlertText(alert, 'p', 'admin-asset-exchange-policy-alert-formula', details.formula || '');
        if (details.actions && details.actions.length > 0) {
            var actionList = document.createElement('ul');
            actionList.className = 'admin-asset-exchange-policy-alert-actions';
            details.actions.forEach(function (action) {
                if (!action) {
                    return;
                }
                var item = document.createElement('li');
                item.textContent = action;
                actionList.appendChild(item);
            });
            if (actionList.children.length > 0) {
                alert.appendChild(actionList);
            }
        }
        alert.hidden = false;
    }

    function applyRatio(amount, numerator, denominator, roundingMode) {
        if (amount <= 0 || numerator <= 0 || denominator <= 0) {
            return 0;
        }
        var product = amount * numerator;
        if (roundingMode === 'ceil') {
            return Math.floor((product + denominator - 1) / denominator);
        }
        if (roundingMode === 'round') {
            return Math.floor(((product * 2) + denominator) / (denominator * 2));
        }
        return Math.floor(product / denominator);
    }

    function minimumAmountForPositiveDeposit(numerator, denominator, roundingMode) {
        if (numerator <= 0 || denominator <= 0) {
            return 1;
        }
        if (roundingMode === 'ceil') {
            return 1;
        }
        if (roundingMode === 'round') {
            return Math.max(1, Math.ceil(Math.floor((denominator + 1) / 2) / numerator));
        }
        return Math.max(1, Math.ceil(denominator / numerator));
    }

    function firstPositiveFinalAmount(section, startAmount, numerator, denominator, roundingMode) {
        var limit = Math.max(startAmount + 10000, startAmount * 4);
        for (var amount = Math.max(1, startAmount); amount <= limit; amount += 1) {
            var deposit = applyRatio(amount, numerator, denominator, roundingMode);
            if (deposit > feeAmount(section, amount, deposit)) {
                return amount;
            }
        }

        return 0;
    }

    function feeAmount(section, fromAmount, toAmount) {
        var basisControl = section.querySelector('select[name$="[fee_basis]"]');
        var rateControl = section.querySelector('input[name$="[fee_rate_numerator]"]');
        var fixedControl = section.querySelector('input[name$="[fee_fixed_amount]"]');
        var minControl = section.querySelector('input[name$="[fee_min_amount]"]');
        var maxControl = section.querySelector('input[name$="[fee_max_amount]"]');
        var roundingControl = section.querySelector('input[type="radio"][name$="[rounding_mode]"]:checked');
        var roundingMode = roundingControl ? roundingControl.value : 'floor';
        var basis = basisControl && basisControl.value === 'from_amount' ? fromAmount : toAmount;
        var fee = numberValue(fixedControl, 0);
        var rate = numberValue(rateControl, 0);
        if (rate > 0) {
            fee += applyRatio(basis, rate, 100, roundingMode);
        }
        if (minControl && String(minControl.value || '').trim() !== '') {
            fee = Math.max(fee, numberValue(minControl, 0));
        }
        if (maxControl && String(maxControl.value || '').trim() !== '') {
            fee = Math.min(fee, numberValue(maxControl, 0));
        }
        return Math.max(0, fee);
    }

    function setPreviewStatus(section, kind) {
        var status = section.querySelector('[data-asset-exchange-preview-status]');
        if (!status) {
            return;
        }
        status.className = 'badge';
        if (kind === 'ok') {
            status.classList.add('badge-soft-success');
            status.textContent = '저장 가능';
        } else if (kind === 'blocked') {
            status.classList.add('badge-soft-danger');
            status.textContent = '저장 불가';
        } else {
            status.classList.add('badge-soft-secondary');
            status.textContent = '중지';
        }
    }

    function updatePreview(section, state) {
        setPreviewStatus(section, state.kind);
    }

    function setControls(row, enabled) {
        row.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.disabled = !enabled;
            if (
                nameEndsWith(control, '[fee_basis]')
                || nameEndsWith(control, '[fee_type]')
                || nameEndsWith(control, '[fee_rate_numerator]')
                || nameEndsWith(control, '[fee_fixed_amount]')
            ) {
                control.required = enabled;
            }
        });
    }

    function validateSection(section) {
        section.querySelectorAll('input, select, textarea').forEach(function (control) {
            setControlError(control, '', null);
        });
        setSectionAlert(section, '');

        var label = section.getAttribute('data-asset-exchange-label') || '이 방향';
        var rateDenominatorControl = section.querySelector('input[name$="[rate_denominator]"]');
        var rateNumeratorControl = section.querySelector('input[name$="[rate_numerator]"]');
        var statusControl = section.querySelector('input[type="checkbox"][name$="[status]"]');
        var minControl = section.querySelector('input[name$="[min_amount]"]');
        var roundingControl = section.querySelector('input[type="radio"][name$="[rounding_mode]"]:checked');
        var triggerControl = section.querySelector('select[name$="[fee_trigger]"]');
        var typeControl = section.querySelector('select[name$="[fee_type]"]');
        var rateControl = section.querySelector('input[name$="[fee_rate_numerator]"]');
        var fixedControl = section.querySelector('input[name$="[fee_fixed_amount]"]');
        var feeMaxControl = section.querySelector('input[name$="[fee_max_amount]"]');
        var feeMinControl = section.querySelector('input[name$="[fee_min_amount]"]');
        var usesFee = triggerControl && triggerControl.value !== 'none';
        var feeType = typeControl ? typeControl.value : 'rate';
        var rateNumerator = numberValue(rateNumeratorControl, 0);
        var rateDenominator = numberValue(rateDenominatorControl, 0);

        if (rateDenominatorControl && rateDenominator <= 0) {
            var denominatorMessage = '출금 기준값은 1 이상이어야 합니다.';
            rateDenominatorControl.setCustomValidity(denominatorMessage);
            setSectionAlert(section, {
                problem: denominatorMessage,
                actions: ['출금 기준값을 1 이상으로 입력하기']
            });
            updatePreview(section, {
                kind: statusControl && statusControl.checked ? 'blocked' : 'off',
                message: denominatorMessage,
                fromAmount: numberValue(minControl, 0),
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: 0
            });
            return;
        }
        if (rateNumeratorControl && rateNumerator <= 0) {
            var numeratorMessage = '입금 기준값은 1 이상이어야 합니다.';
            rateNumeratorControl.setCustomValidity(numeratorMessage);
            setSectionAlert(section, {
                problem: numeratorMessage,
                actions: ['입금 기준값을 1 이상으로 입력하기']
            });
            updatePreview(section, {
                kind: statusControl && statusControl.checked ? 'blocked' : 'off',
                message: numeratorMessage,
                fromAmount: numberValue(minControl, 0),
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: 0
            });
            return;
        }
        var roundingMode = roundingControl ? roundingControl.value : 'floor';
        var computedMinimumAmount = minimumAmountForPositiveDeposit(rateNumerator, rateDenominator, roundingMode);
        if (minControl) {
            minControl.min = String(computedMinimumAmount);
        }

        if (usesFee && feeType === 'rate' && rateControl && numberValue(rateControl, 0) <= 0) {
            var rateMessage = '정률 수수료는 1 이상이어야 합니다.';
            rateControl.setCustomValidity(rateMessage);
            setSectionAlert(section, {
                problem: rateMessage,
                actions: ['정률 수수료를 1 이상으로 입력하기', '수수료 적용을 사용 안 함으로 바꾸기']
            });
            updatePreview(section, {
                kind: statusControl && statusControl.checked ? 'blocked' : 'off',
                message: rateMessage,
                fromAmount: numberValue(minControl, 0),
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: computedMinimumAmount
            });
            return;
        }
        if (usesFee && feeType === 'fixed' && fixedControl && numberValue(fixedControl, 0) <= 0) {
            var fixedMessage = '정액 수수료는 1 이상이어야 합니다.';
            fixedControl.setCustomValidity(fixedMessage);
            setSectionAlert(section, {
                problem: fixedMessage,
                actions: ['정액 수수료를 1 이상으로 입력하기', '수수료 적용을 사용 안 함으로 바꾸기']
            });
            updatePreview(section, {
                kind: statusControl && statusControl.checked ? 'blocked' : 'off',
                message: fixedMessage,
                fromAmount: numberValue(minControl, 0),
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: computedMinimumAmount
            });
            return;
        }
        if (
            feeMinControl
            && feeMaxControl
            && String(feeMinControl.value || '').trim() !== ''
            && String(feeMaxControl.value || '').trim() !== ''
            && numberValue(feeMaxControl, 0) < numberValue(feeMinControl, 0)
        ) {
            var feeLimitMessage = '최대 수수료는 최소 수수료 이상이어야 합니다.';
            feeMaxControl.setCustomValidity(feeLimitMessage);
            setSectionAlert(section, {
                problem: feeLimitMessage,
                formula: '최소 수수료 ' + formatNumber(numberValue(feeMinControl, 0)) + ' / 최대 수수료 ' + formatNumber(numberValue(feeMaxControl, 0)),
                actions: ['최대 수수료를 최소 수수료 이상으로 올리기', '최소 수수료를 낮추기']
            });
            updatePreview(section, {
                kind: statusControl && statusControl.checked ? 'blocked' : 'off',
                message: feeLimitMessage,
                fromAmount: numberValue(minControl, 0),
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: computedMinimumAmount
            });
            return;
        }
        var minAmount = numberValue(minControl, 0);
        if (minControl && minAmount < computedMinimumAmount) {
            var minimumMessage = '최소 환전량이 계산 최소보다 작습니다.';
            setControlError(minControl, minimumMessage, null);
            setSectionAlert(section, {
                problem: minimumMessage,
                formula: '입력 최소 ' + formatNumber(minAmount) + ' / 계산 최소 ' + formatNumber(computedMinimumAmount),
                actions: [
                    '최소 환전량을 ' + formatNumber(computedMinimumAmount) + ' 이상으로 올리기',
                    '입금 기준값을 높이기',
                    '출금 기준값을 낮추기',
                    '소수 처리 방식을 조정하기'
                ]
            });
            updatePreview(section, {
                kind: 'blocked',
                message: '',
                fromAmount: minAmount,
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: computedMinimumAmount
            });
            return;
        }

        if (!statusControl || !statusControl.checked || !minControl || !rateDenominatorControl || !rateNumeratorControl) {
            var offMinAmount = numberValue(minControl, 0);
            var offDepositAmount = applyRatio(offMinAmount, rateNumerator, rateDenominator, roundingMode);
            var offFeeAmount = usesFee ? feeAmount(section, offMinAmount, offDepositAmount) : 0;
            updatePreview(section, {
                kind: 'off',
                message: '중지 상태입니다. 저장해도 회원 환전 후보에는 표시되지 않습니다.',
                fromAmount: offMinAmount,
                beforeFee: offDepositAmount,
                fee: offFeeAmount,
                finalAmount: Math.max(0, offDepositAmount - offFeeAmount),
                minimumAmount: computedMinimumAmount
            });
            return;
        }

        var depositAmount = applyRatio(minAmount, rateNumerator, rateDenominator, roundingMode);
        if (depositAmount <= 0) {
            var zeroMessage = '입금액이 0이라 환전 결과가 없습니다.';
            setControlError(minControl, zeroMessage, null);
            setSectionAlert(section, {
                problem: zeroMessage,
                formula: '최소 환전량 ' + formatNumber(minAmount) + ' → 입금액 ' + formatNumber(depositAmount),
                actions: [
                    '최소 환전량을 ' + formatNumber(computedMinimumAmount) + ' 이상으로 올리기',
                    '입금 기준값을 높이기',
                    '출금 기준값을 낮추기',
                    '소수 처리 방식을 조정하기'
                ]
            });
            updatePreview(section, {
                kind: 'blocked',
                message: '',
                fromAmount: minAmount,
                beforeFee: 0,
                fee: 0,
                finalAmount: 0,
                minimumAmount: computedMinimumAmount
            });
            return;
        }

        var currentFeeAmount = usesFee ? feeAmount(section, minAmount, depositAmount) : 0;
        var finalAmount = depositAmount - currentFeeAmount;
        if (usesFee) {
            var feeControl = feeType === 'fixed' ? fixedControl : rateControl;
            if (feeControl && finalAmount <= 0) {
                var firstPositive = firstPositiveFinalAmount(section, minAmount + 1, rateNumerator, rateDenominator, roundingMode);
                var feeMessage = '최종 입금액이 0 이하입니다.';
                var feeActions = [];
                if (firstPositive > 0) {
                    feeActions.push('최소 환전량을 ' + formatNumber(firstPositive) + ' 이상으로 올리기');
                } else {
                    feeActions.push('최소 환전량을 올리기');
                }
                feeActions.push('입금 기준값을 높이기');
                feeActions.push('수수료를 낮추기');
                setControlError(minControl, feeMessage, null);
                setSectionAlert(section, {
                    problem: feeMessage,
                    formula: '입금액 ' + formatNumber(depositAmount) + ' - 수수료 ' + formatNumber(currentFeeAmount) + ' = ' + formatNumber(finalAmount),
                    actions: feeActions
                });
                updatePreview(section, {
                    kind: 'blocked',
                    message: '',
                    fromAmount: minAmount,
                    beforeFee: depositAmount,
                    fee: currentFeeAmount,
                    finalAmount: Math.max(0, finalAmount),
                    minimumAmount: computedMinimumAmount
                });
                return;
            }
        }
        updatePreview(section, {
            kind: 'ok',
            message: formatAssetAmount(minAmount, section.getAttribute('data-asset-exchange-from-unit') || '') + ' 신청 시 최종 ' + formatAssetAmount(finalAmount, section.getAttribute('data-asset-exchange-to-unit') || '') + ' 입금됩니다.',
            fromAmount: minAmount,
            beforeFee: depositAmount,
            fee: currentFeeAmount,
            finalAmount: finalAmount,
            minimumAmount: computedMinimumAmount
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

    function syncAllPolicySections() {
        document.querySelectorAll('[data-asset-exchange-policy-section]').forEach(function (section) {
            syncFeeRows(section);
            validateSection(section);
        });
    }

    function setAllPolicyStatus(enabled) {
        document.querySelectorAll('[data-asset-exchange-policy-section] input[type="checkbox"][name$="[status]"]').forEach(function (control) {
            control.checked = enabled;
            control.dispatchEvent(new Event('change', {bubbles: true}));
        });
        syncAllPolicySections();
    }

    document.querySelectorAll('[data-asset-exchange-policy-section]').forEach(function (section) {
        section.querySelectorAll('[data-asset-exchange-fee-trigger], [data-asset-exchange-fee-type-control]').forEach(function (control) {
            control.addEventListener('change', function () {
                syncFeeRows(section);
                validateSection(section);
            });
        });
        section.querySelectorAll('input, select').forEach(function (control) {
            control.addEventListener('input', function () {
                validateSection(section);
            });
            control.addEventListener('change', function () {
                validateSection(section);
            });
        });
        syncFeeRows(section);
        validateSection(section);
    });

    if (policyForm) {
        var enableAllButton = policyForm.querySelector('[data-asset-exchange-enable-all]');
        var disableAllButton = policyForm.querySelector('[data-asset-exchange-disable-all]');
        if (enableAllButton) {
            enableAllButton.addEventListener('click', function () {
                setAllPolicyStatus(true);
            });
        }
        if (disableAllButton) {
            disableAllButton.addEventListener('click', function () {
                setAllPolicyStatus(false);
            });
        }
        policyForm.addEventListener('submit', function (event) {
            var invalidControl = null;
            var firstAlert = null;
            document.querySelectorAll('[data-asset-exchange-policy-section]').forEach(function (section) {
                syncFeeRows(section);
                validateSection(section);
                if (!firstAlert) {
                    firstAlert = section.querySelector('[data-asset-exchange-policy-alert]:not([hidden])');
                }
                if (!invalidControl) {
                    invalidControl = Array.prototype.slice.call(section.querySelectorAll('input, select, textarea')).find(function (control) {
                        return !control.disabled && control.validity && !control.validity.valid;
                    }) || null;
                }
            });
            if (invalidControl) {
                event.preventDefault();
                event.stopPropagation();
                if (firstAlert) {
                    firstAlert.focus();
                    firstAlert.scrollIntoView({block: 'center', behavior: 'smooth'});
                } else {
                    invalidControl.reportValidity();
                }
            }
        });
    }

})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
