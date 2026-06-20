<?php

$adminPageTitle = '포인트/금액 환전 정책';
$adminContainerClass = 'admin-page-asset-exchange admin-ui-scope';
$editPolicy = isset($editPolicy) && is_array($editPolicy) ? $editPolicy : [];
$policyDefaults = isset($policyDefaults) && is_array($policyDefaults) ? $policyDefaults : [];
$policyForm = array_merge([
    'id' => '',
    'from_module_key' => '',
    'to_module_key' => '',
    'status' => 'disabled',
    'rate_numerator' => 1,
    'rate_denominator' => 1,
    'min_amount' => 1,
    'max_amount' => '',
    'rounding_mode' => 'floor',
    'fee_trigger' => 'none',
    'fee_basis' => 'to_amount',
    'fee_type' => '',
    'fee_rate_numerator' => 0,
    'fee_fixed_amount' => 0,
    'fee_min_amount' => '',
    'fee_max_amount' => '',
    'sort_order' => 0,
], $policyDefaults, $editPolicy);
$policyForm['rate_ratio'] = (string) ($policyForm['rate_ratio'] ?? '');
if ($policyForm['rate_ratio'] === '') {
    $policyForm['rate_ratio'] = (string) ($policyForm['rate_denominator'] ?? 1) . ':' . (string) ($policyForm['rate_numerator'] ?? 1);
}
if ((string) ($policyForm['fee_type'] ?? '') === '') {
    $policyForm['fee_type'] = (int) ($policyForm['fee_fixed_amount'] ?? 0) > 0 && (int) ($policyForm['fee_rate_numerator'] ?? 0) === 0 ? 'fixed' : 'rate';
}
$fromAssetOptions = $assets;
$toAssetOptions = $assets;
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeTriggerModeLabels = ['always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeBasisLabels = ['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'];
$feeTypeLabels = ['rate' => '정률', 'fixed' => '정액'];
$policyPagination = ['total' => count($policies), 'start' => count($policies) > 0 ? 1 : 0, 'end' => count($policies)];
$policyFilters = isset($policyFilters) && is_array($policyFilters) ? $policyFilters : ['status' => [], 'from_module_key' => [], 'to_module_key' => []];
$policyFilters['status'] = is_array($policyFilters['status'] ?? null) ? $policyFilters['status'] : [];
$policyFilters['from_module_key'] = is_array($policyFilters['from_module_key'] ?? null) ? $policyFilters['from_module_key'] : [];
$policyFilters['to_module_key'] = is_array($policyFilters['to_module_key'] ?? null) ? $policyFilters['to_module_key'] : [];
$policyFilterOptions = isset($policyFilterOptions) && is_array($policyFilterOptions) ? $policyFilterOptions : $assets;
$policyModalOpen = $editPolicy !== [];
$policyModalId = 'asset-exchange-policy-modal';
$policyModalClass = 'modal-overlay modal-overlay-fade overlay';
$policyModalClass .= $policyModalOpen ? ' overlay-open open' : ' hidden pointer-events-none opacity-0';
$policyModalAriaHidden = $policyModalOpen ? 'false' : 'true';
$policyModalInert = $policyModalOpen ? '' : ' inert';
$policyFilterActive = $policyFilters['status'] !== []
    || $policyFilters['from_module_key'] !== []
    || $policyFilters['to_module_key'] !== [];
$assetExchangeHelpOpenLabel = '도움말';
$assetExchangeHelpBodyHtml = static function (array $paragraphs): string {
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $html .= '<p>' . sr_e((string) $paragraph) . '</p>';
    }

    return $html;
};
$assetExchangeHelp = [
    'asset_pair' => [
        'id' => 'asset-exchange-help-asset-pair-modal',
        'title' => '출금/입금 항목',
        'body_html' => $assetExchangeHelpBodyHtml([
            '출금 항목은 회원 보유분에서 차감되고, 입금 항목은 환전 결과가 더해지는 항목입니다.',
            '같은 출금/입금 조합은 하나의 정책만 저장할 수 있습니다. 비활성화된 항목이 포함된 기존 정책은 목록에서 실행 불가로 표시됩니다.',
        ]),
    ],
    'status' => [
        'id' => 'asset-exchange-help-status-modal',
        'title' => '상태',
        'body_html' => $assetExchangeHelpBodyHtml([
            '사용 상태인 정책만 회원 환전 신청 후보로 노출됩니다.',
            '중지 상태로 저장하면 기존 로그는 유지하지만 신규 환전 실행은 막습니다.',
        ]),
    ],
    'rate' => [
        'id' => 'asset-exchange-help-rate-modal',
        'title' => '비율',
        'body_html' => $assetExchangeHelpBodyHtml([
            '출금 기준량과 입금 환산량을 콜론으로 이어서 입력합니다.',
            '예를 들어 100:1이면 출금 100당 입금 1로 계산됩니다. 1:2이면 출금 1당 입금 2로 계산됩니다.',
            '입금 예정액은 회원이 입력한 출금 금액에 이 비율을 적용한 뒤 반올림 설정에 따라 정수로 계산됩니다.',
        ]),
    ],
    'rounding' => [
        'id' => 'asset-exchange-help-rounding-modal',
        'title' => '반올림',
        'body_html' => $assetExchangeHelpBodyHtml([
            '비율 계산 결과가 정수가 아닐 때 입금 예정액을 처리하는 방식입니다.',
            '버림은 소수 이하를 버리고, 반올림은 가장 가까운 정수로, 올림은 소수 이하가 있으면 다음 정수로 계산합니다.',
        ]),
    ],
    'amount_limit' => [
        'id' => 'asset-exchange-help-amount-limit-modal',
        'title' => '환전량 제한',
        'body_html' => $assetExchangeHelpBodyHtml([
            '최소 환전량은 회원이 한 번에 신청할 수 있는 출금 항목 기준 최소 금액입니다.',
            '최대 환전량을 비워두면 1회 신청 상한을 두지 않습니다. 최대값을 입력하면 최소값보다 크거나 같아야 합니다.',
        ]),
    ],
    'fee' => [
        'id' => 'asset-exchange-help-fee-modal',
        'title' => '수수료',
        'body_html' => $assetExchangeHelpBodyHtml([
            '수수료는 환전 입금 처리 후 같은 환전 묶음 ID로 별도 차감 원장을 남깁니다.',
            '정률과 정액 중 하나의 방식만 선택할 수 있습니다. 정률 수수료는 퍼센트 숫자로 입력합니다.',
            '최소/최대 수수료가 있으면 선택한 방식으로 계산한 수수료를 그 범위 안으로 보정합니다.',
        ]),
    ],
    'fee_trigger' => [
        'id' => 'asset-exchange-help-fee-trigger-modal',
        'title' => '수수료 적용',
        'body_html' => $assetExchangeHelpBodyHtml([
            '사용 안 함은 수수료를 계산하지 않습니다. 항상 적용은 모든 환전에 수수료를 적용합니다.',
            '환금성 항목 재환전만 적용은 환금성 항목에서 다른 항목으로 다시 환전하는 경우에만 수수료를 적용합니다.',
        ]),
    ],
    'fee_basis' => [
        'id' => 'asset-exchange-help-fee-basis-modal',
        'title' => '수수료 기준',
        'body_html' => $assetExchangeHelpBodyHtml([
            '정률 수수료를 선택했을 때 어떤 금액에 퍼센트를 적용할지 정합니다.',
            '출금액 기준은 회원이 요청한 출금 금액을 기준으로, 입금액 기준은 비율과 반올림을 적용한 입금 예정액을 기준으로 계산합니다.',
        ]),
    ],
    'sort_order' => [
        'id' => 'asset-exchange-help-sort-order-modal',
        'title' => '정렬순서',
        'body_html' => $assetExchangeHelpBodyHtml([
            '회원 환전 신청 화면과 관리자 정책 목록에서 정책을 정렬할 때 사용하는 숫자입니다.',
            '숫자가 작을수록 먼저 표시됩니다.',
        ]),
    ],
];
$selectedFromModuleKey = (string) ($policyForm['from_module_key'] ?? '');
$selectedToModuleKey = (string) ($policyForm['to_module_key'] ?? '');
if ($selectedFromModuleKey !== '' && !isset($fromAssetOptions[$selectedFromModuleKey])) {
    $fromAssetOptions[$selectedFromModuleKey] = [
        'module_key' => $selectedFromModuleKey,
        'label' => $selectedFromModuleKey . ' (비활성)',
    ];
}
if ($selectedToModuleKey !== '' && !isset($toAssetOptions[$selectedToModuleKey])) {
    $toAssetOptions[$selectedToModuleKey] = [
        'module_key' => $selectedToModuleKey,
        'label' => $selectedToModuleKey . ' (비활성)',
    ];
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="filtering-form filtering filtering-plain admin-asset-exchange-filter ui-form-theme">
    <div class="filtering-fields admin-asset-exchange-search-grid">
        <div class="filtering-field admin-asset-exchange-filter-status">
            <span class="filtering-label">상태</span>
            <?php echo sr_admin_filter_radio_toggle_group_html('asset_exchange_filter_status', 'status', $policyStatusLabels, $policyFilters['status'], '전체'); ?>
        </div>
        <div class="filtering-field admin-asset-exchange-filter-from">
            <span class="filtering-label">출금 항목</span>
            <?php
            $assetExchangePolicyAssetOptions = [];
            foreach ($policyFilterOptions as $asset) {
                $moduleKey = (string) ($asset['module_key'] ?? '');
                if ($moduleKey !== '') {
                    $assetExchangePolicyAssetOptions[$moduleKey] = (string) ($asset['label'] ?? $moduleKey);
                }
            }
            echo sr_admin_filter_radio_toggle_group_html('asset_exchange_filter_from', 'from_module_key', $assetExchangePolicyAssetOptions, $policyFilters['from_module_key'], '전체');
            ?>
        </div>
        <div class="filtering-field admin-asset-exchange-filter-to">
            <span class="filtering-label">입금 항목</span>
            <?php echo sr_admin_filter_radio_toggle_group_html('asset_exchange_filter_to', 'to_module_key', $assetExchangePolicyAssetOptions, $policyFilters['to_module_key'], '전체'); ?>
        </div>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">정책 목록</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $policyModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($policyModalId); ?>" data-overlay="#<?php echo sr_e($policyModalId); ?>">정책 등록</button>
        </div>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($policyPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list">
            <thead>
                <tr>
                    <th>출금 항목</th>
                    <th>입금 항목</th>
                    <th>비율</th>
                    <th>금액 제한</th>
                    <th>수수료</th>
                    <th>상태</th>
                    <th>수정일</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($policies === []) { ?>
                    <tr><td colspan="8" class="admin-empty-state"><?php echo $policyFilterActive ? '조건에 맞는 환전 정책이 없습니다.' : '등록된 환전 정책이 없습니다.'; ?></td></tr>
                <?php } ?>
                <?php foreach ($policies as $policy) { ?>
                    <?php
                    $fromMissing = !isset($assets[(string) $policy['from_module_key']]);
                    $toMissing = !isset($assets[(string) $policy['to_module_key']]);
                    ?>
                    <tr>
                        <td>
                            <?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['from_module_key'])); ?>
                            <?php if ($fromMissing) { ?>
                                <br><small>비활성</small>
                            <?php } ?>
                        </td>
                        <td>
                            <?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['to_module_key'])); ?>
                            <?php if ($toMissing) { ?>
                                <br><small>비활성</small>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e('출금 ' . number_format((int) $policy['rate_denominator']) . '당 입금 ' . number_format((int) $policy['rate_numerator'])); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) $policy['min_amount']) . ' - ' . ($policy['max_amount'] === null ? '제한 없음' : number_format((int) $policy['max_amount']))); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) ($feeTriggerLabels[(string) $policy['fee_trigger']] ?? $policy['fee_trigger'])); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo (string) $policy['status'] === 'enabled' ? 'is-normal' : 'is-blocked'; ?>"><?php echo sr_e((string) ($policyStatusLabels[(string) $policy['status']] ?? $policy['status'])); ?></span>
                            <?php if ($fromMissing || $toMissing) { ?>
                                <br><small>실행 불가</small>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_asset_exchange_time_html((string) $policy['updated_at']); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <a href="<?php echo sr_e(sr_url('/admin/asset-exchange?edit=' . (int) $policy['id'])); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="환전 정책 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
    </div>
    <?php echo sr_admin_status_description_list_html('asset_exchange_policy_status', $policyStatusLabels); ?>
</section>

<div id="<?php echo sr_e($policyModalId); ?>" class="<?php echo sr_e($policyModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($policyModalId); ?>_title" aria-hidden="<?php echo sr_e($policyModalAriaHidden); ?>"<?php echo $policyModalInert; ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="modal-content ui-form-theme" data-asset-exchange-policy-form>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo sr_e((string) $policyForm['id']); ?>">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($policyModalId); ?>_title" class="modal-title"><?php echo (int) ($policyForm['id'] ?? 0) > 0 ? '환전 정책 수정' : '환전 정책 등록'; ?></h3>
                <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($policyModalId); ?>">
                    <?php echo sr_material_icon_html('close'); ?>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_from_module_key', '출금 항목', $assetExchangeHelp['asset_pair']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="asset_exchange_from_module_key" name="from_module_key" class="form-select" required data-overlay-focus>
                            <option value="">선택</option>
                            <?php foreach ($fromAssetOptions as $asset) { ?>
                                <option value="<?php echo sr_e((string) $asset['module_key']); ?>"<?php echo (string) $policyForm['from_module_key'] === (string) $asset['module_key'] ? ' selected' : ''; ?>><?php echo sr_e((string) $asset['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_to_module_key', '입금 항목', $assetExchangeHelp['asset_pair']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="asset_exchange_to_module_key" name="to_module_key" class="form-select" required>
                            <option value="">선택</option>
                            <?php foreach ($toAssetOptions as $asset) { ?>
                                <option value="<?php echo sr_e((string) $asset['module_key']); ?>"<?php echo (string) $policyForm['to_module_key'] === (string) $asset['module_key'] ? ' selected' : ''; ?>><?php echo sr_e((string) $asset['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_status', '상태', $assetExchangeHelp['status']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <?php echo sr_admin_radio_toggle_group_html('asset_exchange_status', 'status', $policyStatusLabels, (string) $policyForm['status'], true); ?>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_rounding_mode', '반올림', $assetExchangeHelp['rounding']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <?php echo sr_admin_radio_toggle_group_html('asset_exchange_rounding_mode', 'rounding_mode', $roundingModeLabels, (string) $policyForm['rounding_mode'], true); ?>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_rate_ratio', '환전 비율', $assetExchangeHelp['rate']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="asset_exchange_rate_ratio" type="text" name="rate_ratio" value="<?php echo sr_e((string) $policyForm['rate_ratio']); ?>" class="form-input" maxlength="80" pattern="[0-9]+\s*[:/]\s*[0-9]+" required placeholder="100:1">
                        <span class="form-help">출금 기준량:입금 환산량 형식입니다. 예: 100:1</span>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_min_amount', '최소 환전량', $assetExchangeHelp['amount_limit']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="asset_exchange_min_amount" type="number" name="min_amount" value="<?php echo sr_e((string) $policyForm['min_amount']); ?>" class="form-input" min="1" required>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_max_amount', '최대 환전량', $assetExchangeHelp['amount_limit']['id'], $assetExchangeHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="asset_exchange_max_amount" type="number" name="max_amount" value="<?php echo sr_e((string) $policyForm['max_amount']); ?>" class="form-input" min="0">
                        <span class="form-help">비워두면 1회 최대 금액을 제한하지 않습니다.</span>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_enabled', '수수료 사용', $assetExchangeHelp['fee_trigger']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <?php $feeTriggerValue = (string) ($policyForm['fee_trigger'] ?? 'none'); ?>
                        <?php $feeTriggerModeValue = $feeTriggerValue === 'reexchange' ? 'reexchange' : 'always'; ?>
                        <label class="form-check form-label" for="asset_exchange_fee_enabled">
                            <input id="asset_exchange_fee_enabled" type="checkbox" value="1" class="form-switch form-switch-light"<?php echo $feeTriggerValue !== 'none' ? ' checked' : ''; ?> data-asset-exchange-fee-enabled>
                            <span class="sr-only">수수료 사용</span>
                        </label>
                        <input id="asset_exchange_fee_trigger" type="hidden" name="fee_trigger" value="<?php echo sr_e($feeTriggerValue); ?>" data-asset-exchange-fee-trigger-value>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting<?php echo $feeTriggerValue === 'none' ? ' hidden' : ''; ?>>
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_trigger_mode', '적용 대상', $assetExchangeHelp['fee_trigger']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="asset_exchange_fee_trigger_mode" class="form-select" data-asset-exchange-fee-trigger-mode<?php echo $feeTriggerValue === 'none' ? ' disabled' : ''; ?>>
                            <?php foreach ($feeTriggerModeLabels as $value => $label) { ?>
                                <option value="<?php echo sr_e($value); ?>"<?php echo $feeTriggerModeValue === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting data-asset-exchange-fee-type="rate">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_basis', '수수료 기준', $assetExchangeHelp['fee_basis']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <?php echo sr_admin_radio_toggle_group_html('asset_exchange_fee_basis', 'fee_basis', $feeBasisLabels, (string) $policyForm['fee_basis'], true); ?>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting>
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_type', '수수료 방식', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <?php echo sr_admin_radio_toggle_group_html('asset_exchange_fee_type', 'fee_type', $feeTypeLabels, (string) $policyForm['fee_type'], true); ?>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting data-asset-exchange-fee-type="rate">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_rate_numerator', '정률 수수료', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="asset_exchange_fee_rate_numerator" type="number" name="fee_rate_numerator" value="<?php echo sr_e((string) $policyForm['fee_rate_numerator']); ?>" class="form-input" min="0">
                        <span class="form-help">5%라면 5를 입력합니다.</span>
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting data-asset-exchange-fee-type="fixed">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_fixed_amount', '정액 수수료', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="asset_exchange_fee_fixed_amount" type="number" name="fee_fixed_amount" value="<?php echo sr_e((string) $policyForm['fee_fixed_amount']); ?>" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting>
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_min_amount', '최소 수수료', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="asset_exchange_fee_min_amount" type="number" name="fee_min_amount" value="<?php echo sr_e((string) $policyForm['fee_min_amount']); ?>" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-row" data-asset-exchange-fee-setting>
                    <?php echo sr_admin_form_label_help_html('asset_exchange_fee_max_amount', '최대 수수료', $assetExchangeHelp['fee']['id'], $assetExchangeHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="asset_exchange_fee_max_amount" type="number" name="fee_max_amount" value="<?php echo sr_e((string) $policyForm['fee_max_amount']); ?>" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('asset_exchange_sort_order', '정렬순서', $assetExchangeHelp['sort_order']['id'], $assetExchangeHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="asset_exchange_sort_order" type="number" name="sort_order" value="<?php echo sr_e((string) $policyForm['sort_order']); ?>" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <?php if ((int) ($policyForm['id'] ?? 0) > 0) { ?>
                    <a href="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="btn btn-solid-light modal-action">새 정책</a>
                <?php } else { ?>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($policyModalId); ?>">취소</button>
                <?php } ?>
                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($assetExchangeHelp as $assetExchangeHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $assetExchangeHelpModal['id'], (string) $assetExchangeHelpModal['title'], (string) $assetExchangeHelpModal['body_html']); ?>
<?php } ?>

<script>
(function () {
    var form = document.querySelector('[data-asset-exchange-policy-form]');
    if (!form) {
        return;
    }

    var feeEnabled = form.querySelector('[data-asset-exchange-fee-enabled]');
    var feeTriggerInput = form.querySelector('[data-asset-exchange-fee-trigger-value]');
    var feeTriggerMode = form.querySelector('[data-asset-exchange-fee-trigger-mode]');
    var typeControls = form.querySelectorAll('input[name="fee_type"]');
    var settings = form.querySelectorAll('[data-asset-exchange-fee-setting]');

    function checkedValue(name, fallback) {
        var checked = form.querySelector('input[name="' + name + '"]:checked, select[name="' + name + '"]');
        return checked ? checked.value : fallback;
    }

    function feeTriggerValue() {
        if (!feeEnabled || !feeTriggerInput) {
            return checkedValue('fee_trigger', 'none');
        }

        if (!feeEnabled.checked) {
            return 'none';
        }

        return feeTriggerMode ? feeTriggerMode.value : 'always';
    }

    function syncFeeTriggerValue() {
        if (feeTriggerInput) {
            feeTriggerInput.value = feeTriggerValue();
        }
        if (feeTriggerMode) {
            feeTriggerMode.disabled = feeTriggerValue() === 'none';
        }
    }

    function setControls(row, enabled) {
        row.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.disabled = !enabled;
            if (control.id === 'asset_exchange_fee_basis' || control.id === 'asset_exchange_fee_type' || control.id === 'asset_exchange_fee_rate_numerator' || control.id === 'asset_exchange_fee_fixed_amount') {
                control.required = enabled;
            }
        });
    }

    function syncFeeFields() {
        syncFeeTriggerValue();
        var usesFee = feeTriggerValue() !== 'none';
        var feeType = checkedValue('fee_type', 'rate');

        settings.forEach(function (row) {
            var rowType = row.getAttribute('data-asset-exchange-fee-type');
            var visible = usesFee && (!rowType || rowType === feeType);
            row.hidden = !visible;
            setControls(row, visible);
        });
    }

    if (feeEnabled) {
        feeEnabled.addEventListener('change', syncFeeFields);
    }
    if (feeTriggerMode) {
        feeTriggerMode.addEventListener('change', syncFeeFields);
    }
    typeControls.forEach(function (control) {
        control.addEventListener('change', syncFeeFields);
    });
    syncFeeFields();
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
