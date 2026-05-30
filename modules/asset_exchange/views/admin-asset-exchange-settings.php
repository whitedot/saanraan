<?php

$adminPageTitle = '포인트/금액 환전 환경설정';
$adminPageSubtitle = '새 환전 정책을 등록할 때 사용할 기본값을 정합니다.';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeBasisLabels = ['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'];
$feeTypeLabels = ['rate' => '정률', 'fixed' => '정액'];
$settingsHelpId = 'asset-exchange-settings-defaults-help-modal';
$settingsHelpBody = '<p>이 화면의 값은 새 정책 등록 모달을 처음 열 때 채워지는 기본값입니다.</p>'
    . '<p>환경설정을 저장해도 이미 등록된 환전 정책의 비율, 상태, 수수료, 제한 금액은 자동으로 바뀌지 않습니다.</p>';

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange/settings')); ?>" class="admin-form ui-form-theme" data-asset-exchange-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="admin-card card">
        <div class="card-header">
            <h2 class="card-title">정책 등록 기본값</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="정책 등록 기본값 도움말" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($settingsHelpId); ?>" data-overlay="#<?php echo sr_e($settingsHelpId); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_status">기본 상태 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="asset_exchange_settings_policy_default_status" name="policy_default_status" class="form-select" required>
                    <?php foreach ($policyStatusLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_status'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">새 정책 등록 모달의 상태 초기값입니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_rate_ratio">기본 환전 비율 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_rate_ratio" type="text" name="policy_default_rate_ratio" value="<?php echo sr_e((string) $settings['policy_default_rate_ratio']); ?>" class="form-input" maxlength="80" pattern="[0-9]+\s*[:/]\s*[0-9]+" required placeholder="1:1">
                <p class="admin-form-help">출금 기준량:입금 환산량 형식입니다. 예: 100:1</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_rounding_mode">기본 반올림 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="asset_exchange_settings_policy_default_rounding_mode" name="policy_default_rounding_mode" class="form-select" required>
                    <?php foreach ($roundingModeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_rounding_mode'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_min_amount">기본 최소 환전량 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_min_amount" type="number" name="policy_default_min_amount" value="<?php echo sr_e((string) $settings['policy_default_min_amount']); ?>" class="form-input" min="1" required>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_max_amount">기본 최대 환전량</label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_max_amount" type="number" name="policy_default_max_amount" value="<?php echo sr_e((string) $settings['policy_default_max_amount']); ?>" class="form-input" min="0">
                <p class="admin-form-help">비워두면 새 정책의 1회 최대 금액을 제한하지 않는 값으로 시작합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_trigger">기본 수수료 적용 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="asset_exchange_settings_policy_default_fee_trigger" name="policy_default_fee_trigger" class="form-select" required>
                    <?php foreach ($feeTriggerLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_trigger'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="rate">
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_basis">기본 수수료 기준 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="asset_exchange_settings_policy_default_fee_basis" name="policy_default_fee_basis" class="form-select" required>
                    <?php foreach ($feeBasisLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_basis'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row>
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_type">기본 수수료 방식 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="asset_exchange_settings_policy_default_fee_type" name="policy_default_fee_type" class="form-select" required>
                    <?php foreach ($feeTypeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_type'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="rate">
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_rate_numerator">기본 정률 수수료 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_fee_rate_numerator" type="number" name="policy_default_fee_rate_numerator" value="<?php echo sr_e((string) $settings['policy_default_fee_rate_numerator']); ?>" class="form-input" min="0">
                <p class="admin-form-help">5%라면 5를 입력합니다.</p>
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="fixed">
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_fixed_amount">기본 정액 수수료 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_fee_fixed_amount" type="number" name="policy_default_fee_fixed_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_fixed_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row>
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_min_amount">기본 최소 수수료</label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_fee_min_amount" type="number" name="policy_default_fee_min_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_min_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="admin-form-row" data-asset-exchange-settings-fee-row>
            <label class="form-label" for="asset_exchange_settings_policy_default_fee_max_amount">기본 최대 수수료</label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_fee_max_amount" type="number" name="policy_default_fee_max_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_max_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="asset_exchange_settings_policy_default_sort_order">기본 정렬순서</label>
            <div class="admin-form-field">
                <input id="asset_exchange_settings_policy_default_sort_order" type="number" name="policy_default_sort_order" value="<?php echo sr_e((string) $settings['policy_default_sort_order']); ?>" class="form-input">
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="btn btn-solid-light">정책 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php echo sr_admin_help_modal_html($settingsHelpId, '정책 등록 기본값', $settingsHelpBody); ?>

<script>
(function () {
    var form = document.querySelector('[data-asset-exchange-settings-form]');
    if (!form) {
        return;
    }

    var trigger = form.querySelector('#asset_exchange_settings_policy_default_fee_trigger');
    var type = form.querySelector('#asset_exchange_settings_policy_default_fee_type');
    var rows = form.querySelectorAll('[data-asset-exchange-settings-fee-row]');

    function setControls(row, enabled) {
        row.querySelectorAll('input, select, textarea').forEach(function (control) {
            control.disabled = !enabled;
            if (
                control.id === 'asset_exchange_settings_policy_default_fee_basis'
                || control.id === 'asset_exchange_settings_policy_default_fee_type'
                || control.id === 'asset_exchange_settings_policy_default_fee_rate_numerator'
                || control.id === 'asset_exchange_settings_policy_default_fee_fixed_amount'
            ) {
                control.required = enabled;
            }
        });
    }

    function syncFeeRows() {
        var usesFee = trigger && trigger.value !== 'none';
        var feeType = type ? type.value : 'rate';

        rows.forEach(function (row) {
            var rowType = row.getAttribute('data-asset-exchange-settings-fee-type');
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
