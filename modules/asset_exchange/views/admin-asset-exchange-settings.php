<?php

$adminPageTitle = '포인트/금액 환전 환경설정';
$adminPageSubtitle = '';
$settings = isset($settings) && is_array($settings) ? sr_asset_exchange_normalize_settings($settings) : sr_asset_exchange_default_settings();
$notificationGroups = isset($notificationGroups) && is_array($notificationGroups) ? $notificationGroups : [];
$allNotificationCasesEnabled = $notificationGroups !== [];
foreach ($notificationGroups as $notificationGroup) {
    foreach ((array) ($notificationGroup['cases'] ?? []) as $notificationCaseKey => $_notificationCase) {
        $notificationCaseKey = (string) $notificationCaseKey;
        $caseSettings = is_array($notificationGroup['all_case_settings'] ?? null) ? $notificationGroup['all_case_settings'] : [];
        if (empty($caseSettings[$notificationCaseKey]['enabled'])) {
            $allNotificationCasesEnabled = false;
            break 2;
        }
    }
}
$policyStatusLabels = ['enabled' => '사용', 'disabled' => '중지'];
$roundingModeLabels = ['floor' => '버림', 'round' => '반올림', 'ceil' => '올림'];
$feeTriggerLabels = ['none' => '사용 안 함', 'always' => '항상 적용', 'reexchange' => '환금성 항목 재환전만 적용'];
$feeBasisLabels = ['from_amount' => '출금액 기준', 'to_amount' => '입금액 기준'];
$feeTypeLabels = ['rate' => '정률', 'fixed' => '정액'];
$settingsHelpOpenLabel = '도움말';
$settingsHelpId = 'asset-exchange-settings-defaults-help-modal';
$settingsHelpBody = '<p>이 화면의 값은 새 정책 등록 모달을 처음 열 때 채워지는 기본값입니다.</p>'
    . '<p>환경설정을 저장해도 이미 등록된 환전 정책의 비율, 상태, 수수료, 제한 금액은 자동으로 바뀌지 않습니다.</p>';
$settingsHelpBodyHtml = static function (array $paragraphs): string {
    $html = '';
    foreach ($paragraphs as $paragraph) {
        $html .= '<p>' . sr_e((string) $paragraph) . '</p>';
    }

    return $html;
};
$settingsHelp = [
    'status' => [
        'id' => 'asset-exchange-settings-help-status-modal',
        'title' => '기본 상태',
        'body_html' => $settingsHelpBodyHtml([
            '새 환전 정책을 등록할 때 처음 선택되어 있는 상태입니다.',
            '사용으로 저장하면 새 정책 등록 시 바로 회원 화면에 노출될 수 있으므로, 검토 후 켜는 운영이라면 중지를 기본값으로 둡니다.',
            '이 값을 바꿔도 이미 등록된 정책의 상태는 바뀌지 않습니다.',
        ]),
    ],
    'rate' => [
        'id' => 'asset-exchange-settings-help-rate-modal',
        'title' => '기본 환전 비율',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 모달의 환전 비율 입력칸에 미리 채워질 값입니다.',
            '출금 기준량:입금 환산량 형식으로 입력합니다. 예를 들어 100:1은 출금 100당 입금 1로 계산됩니다.',
            '기존 정책의 비율과 기존 환전 로그에는 영향을 주지 않습니다.',
        ]),
    ],
    'rounding' => [
        'id' => 'asset-exchange-settings-help-rounding-modal',
        'title' => '기본 반올림',
        'body_html' => $settingsHelpBodyHtml([
            '비율 계산 결과가 정수가 아닐 때 새 정책이 기본으로 사용할 처리 방식입니다.',
            '버림은 소수 이하를 버리고, 반올림은 가장 가까운 정수로, 올림은 소수 이하가 있으면 다음 정수로 계산합니다.',
        ]),
    ],
    'amount_limit' => [
        'id' => 'asset-exchange-settings-help-amount-limit-modal',
        'title' => '기본 환전량 제한',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 시 최소 환전량과 최대 환전량 입력칸에 들어갈 기본값입니다.',
            '최대 환전량을 비워두면 새 정책은 1회 최대 금액 제한 없음으로 시작합니다.',
            '환경설정 저장 후에도 기존 정책의 최소/최대 환전량은 자동 변경되지 않습니다.',
        ]),
    ],
    'fee_trigger' => [
        'id' => 'asset-exchange-settings-help-fee-trigger-modal',
        'title' => '기본 수수료 적용',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 시 수수료를 기본으로 사용할지 정합니다.',
            '사용 안 함이면 수수료 관련 기본값은 새 정책 등록 모달에서 숨겨지고, 저장값도 비워진 상태로 시작합니다.',
            '환금성 항목 재환전만 적용은 회원이 환금성 항목을 다시 다른 항목으로 환전하는 경우를 기본 수수료 대상으로 둡니다.',
        ]),
    ],
    'fee_basis' => [
        'id' => 'asset-exchange-settings-help-fee-basis-modal',
        'title' => '기본 수수료 기준',
        'body_html' => $settingsHelpBodyHtml([
            '정률 수수료를 기본으로 쓸 때 퍼센트를 어느 금액에 적용할지 정합니다.',
            '출금액 기준은 회원이 요청한 출금 금액을 기준으로, 입금액 기준은 비율과 반올림을 적용한 입금 예정액을 기준으로 계산합니다.',
        ]),
    ],
    'fee_type' => [
        'id' => 'asset-exchange-settings-help-fee-type-modal',
        'title' => '기본 수수료 방식',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 시 정률 또는 정액 중 어느 수수료 방식을 기본으로 보여줄지 정합니다.',
            '정률은 퍼센트 숫자를, 정액은 고정 차감 금액을 사용합니다.',
        ]),
    ],
    'fee_amount' => [
        'id' => 'asset-exchange-settings-help-fee-amount-modal',
        'title' => '기본 수수료 금액',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 시 수수료 입력칸에 미리 채워질 값입니다.',
            '정률 수수료는 5%라면 5를 입력하고, 정액 수수료는 입금 항목에서 차감할 고정 금액을 입력합니다.',
            '최소/최대 수수료가 있으면 계산된 수수료를 그 범위 안으로 보정합니다.',
        ]),
    ],
    'sort_order' => [
        'id' => 'asset-exchange-settings-help-sort-order-modal',
        'title' => '기본 정렬순서',
        'body_html' => $settingsHelpBodyHtml([
            '새 정책 등록 시 정렬순서 입력칸에 들어갈 기본값입니다.',
            '숫자가 작을수록 회원 환전 신청 화면과 관리자 정책 목록에서 먼저 표시됩니다.',
        ]),
    ],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice ?? '', $errors ?? []); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/asset-exchange/settings')); ?>" class="admin-form ui-form-theme" data-asset-exchange-settings-form>
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">정책 등록 기본값</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="정책 등록 기본값 도움말" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($settingsHelpId); ?>" data-overlay="#<?php echo sr_e($settingsHelpId); ?>">
                    <?php echo sr_material_icon_html('help'); ?>
                </button>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_status', '기본 상태', $settingsHelp['status']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="asset_exchange_settings_policy_default_status" name="policy_default_status" class="form-select" required>
                    <?php foreach ($policyStatusLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_status'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
                <p class="form-help">새 정책 등록 모달의 상태 초기값입니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_rate_ratio', '기본 환전 비율', $settingsHelp['rate']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_rate_ratio" type="text" name="policy_default_rate_ratio" value="<?php echo sr_e((string) $settings['policy_default_rate_ratio']); ?>" class="form-input" maxlength="80" pattern="[0-9]+\s*[:/]\s*[0-9]+" required placeholder="1:1">
                <p class="form-help">출금 기준량:입금 환산량 형식입니다. 예: 100:1</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_rounding_mode', '기본 반올림', $settingsHelp['rounding']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="asset_exchange_settings_policy_default_rounding_mode" name="policy_default_rounding_mode" class="form-select" required>
                    <?php foreach ($roundingModeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_rounding_mode'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_min_amount', '기본 최소 환전량', $settingsHelp['amount_limit']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_min_amount" type="number" name="policy_default_min_amount" value="<?php echo sr_e((string) $settings['policy_default_min_amount']); ?>" class="form-input" min="1" required>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_max_amount', '기본 최대 환전량', $settingsHelp['amount_limit']['id'], $settingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_max_amount" type="number" name="policy_default_max_amount" value="<?php echo sr_e((string) $settings['policy_default_max_amount']); ?>" class="form-input" min="0">
                <p class="form-help">비워두면 새 정책의 1회 최대 금액을 제한하지 않는 값으로 시작합니다.</p>
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_trigger', '기본 수수료 적용', $settingsHelp['fee_trigger']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="asset_exchange_settings_policy_default_fee_trigger" name="policy_default_fee_trigger" class="form-select" required>
                    <?php foreach ($feeTriggerLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_trigger'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="rate">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_basis', '기본 수수료 기준', $settingsHelp['fee_basis']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="asset_exchange_settings_policy_default_fee_basis" name="policy_default_fee_basis" class="form-select" required>
                    <?php foreach ($feeBasisLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_basis'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row>
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_type', '기본 수수료 방식', $settingsHelp['fee_type']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <select id="asset_exchange_settings_policy_default_fee_type" name="policy_default_fee_type" class="form-select" required>
                    <?php foreach ($feeTypeLabels as $value => $label) { ?>
                        <option value="<?php echo sr_e($value); ?>"<?php echo (string) $settings['policy_default_fee_type'] === $value ? ' selected' : ''; ?>><?php echo sr_e($label); ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="rate">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_rate_numerator', '기본 정률 수수료', $settingsHelp['fee_amount']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_fee_rate_numerator" type="number" name="policy_default_fee_rate_numerator" value="<?php echo sr_e((string) $settings['policy_default_fee_rate_numerator']); ?>" class="form-input" min="0">
                <p class="form-help">5%라면 5를 입력합니다.</p>
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row data-asset-exchange-settings-fee-type="fixed">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_fixed_amount', '기본 정액 수수료', $settingsHelp['fee_amount']['id'], $settingsHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_fee_fixed_amount" type="number" name="policy_default_fee_fixed_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_fixed_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row>
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_min_amount', '기본 최소 수수료', $settingsHelp['fee_amount']['id'], $settingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_fee_min_amount" type="number" name="policy_default_fee_min_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_min_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="form-row" data-asset-exchange-settings-fee-row>
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_fee_max_amount', '기본 최대 수수료', $settingsHelp['fee_amount']['id'], $settingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_fee_max_amount" type="number" name="policy_default_fee_max_amount" value="<?php echo sr_e((string) $settings['policy_default_fee_max_amount']); ?>" class="form-input" min="0">
            </div>
        </div>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('asset_exchange_settings_policy_default_sort_order', '기본 정렬순서', $settingsHelp['sort_order']['id'], $settingsHelpOpenLabel); ?>
            <div class="form-field">
                <input id="asset_exchange_settings_policy_default_sort_order" type="number" name="policy_default_sort_order" value="<?php echo sr_e((string) $settings['policy_default_sort_order']); ?>" class="form-input">
            </div>
        </div>
    </section>

    <?php if ($notificationGroups !== []) { ?>
        <section class="card">
            <div class="card-header">
                <h2 class="card-title">회원 알림</h2>
                <div class="type-small">
                    <?php echo sr_admin_switch_html('asset_exchange_notification_bulk_toggle', 'asset_exchange_notification_bulk_toggle', '1', $allNotificationCasesEnabled, $allNotificationCasesEnabled ? '전체비활성' : '전체활성', '', ' data-asset-exchange-notification-bulk-toggle'); ?>
                </div>
            </div>
            <p class="form-help">환전 출금, 입금, 수수료 알림의 사용 여부와 채널을 자산별로 설정합니다.</p>
            <?php foreach ($notificationGroups as $moduleKey => $notificationGroup) { ?>
                <?php
                $moduleKey = (string) $moduleKey;
                $moduleLabel = (string) ($notificationGroup['label'] ?? $moduleKey);
                $caseSettings = is_array($notificationGroup['all_case_settings'] ?? null) ? $notificationGroup['all_case_settings'] : [];
                $channelOptions = isset($notificationGroup['channel_options']) && is_array($notificationGroup['channel_options']) ? $notificationGroup['channel_options'] : ['site'];
                $channelsFunction = (string) ($notificationGroup['channels_function'] ?? '');
                ?>
                <?php foreach ((array) ($notificationGroup['cases'] ?? []) as $caseKey => $case) { ?>
                    <?php
                    $caseKey = (string) $caseKey;
                    $caseSetting = isset($caseSettings[$caseKey]) && is_array($caseSettings[$caseKey]) ? $caseSettings[$caseKey] : ['enabled' => true, 'channels' => ['site']];
                    $caseEnabled = !empty($caseSetting['enabled']);
                    $caseChannels = function_exists($channelsFunction) ? $channelsFunction($caseSetting['channels'] ?? ['site']) : ['site'];
                    $caseId = 'asset_exchange_notification_case_' . $moduleKey . '_' . $caseKey;
                    $caseDataKey = $moduleKey . ':' . $caseKey;
                    ?>
                    <div class="form-row" data-asset-exchange-notification-case="<?php echo sr_e($caseDataKey); ?>">
                        <label class="form-label" for="<?php echo sr_e($caseId); ?>"><?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 사용 여부'); ?></label>
                        <div class="form-field">
                            <?php echo sr_admin_switch_html($caseId, 'notification_cases[' . $moduleKey . '][' . $caseKey . '][enabled]', '1', $caseEnabled, '사용', '0', ' data-asset-exchange-notification-case-toggle data-asset-exchange-notification-case-key="' . sr_e($caseDataKey) . '"'); ?>
                            <p class="form-help"><?php echo sr_e((string) ($case['description'] ?? '')); ?></p>
                        </div>
                    </div>
                    <div class="form-row" data-asset-exchange-notification-case="<?php echo sr_e($caseDataKey); ?>">
                        <label class="form-label"><?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 채널'); ?> <span class="sr-required-label" data-asset-exchange-notification-required-label data-asset-exchange-notification-case-key="<?php echo sr_e($caseDataKey); ?>">(필수)</span></label>
                        <div class="form-field">
                            <div class="filtering-toggle-group admin-checkbox-toggle-group" role="group" aria-label="<?php echo sr_e($moduleLabel . ' ' . (string) ($case['label'] ?? '알림') . ' 채널'); ?>">
                                <?php foreach ($channelOptions as $channelIndex => $channel) { ?>
                                    <?php
                                    $channel = (string) $channel;
                                    $channelInputId = $caseId . '_channel_' . (string) $channelIndex;
                                    $groupClass = $channelIndex === 0 ? 'btn-group-start' : ($channelIndex === count($channelOptions) - 1 ? 'btn-group-end' : 'btn-group-middle');
                                    ?>
                                    <span class="filtering-toggle-item">
                                        <input id="<?php echo sr_e($channelInputId); ?>" type="checkbox" name="notification_cases[<?php echo sr_e($moduleKey); ?>][<?php echo sr_e($caseKey); ?>][channels][]" value="<?php echo sr_e($channel); ?>" class="form-choice-toggle-input sr-only" data-asset-exchange-notification-channel data-asset-exchange-notification-case-key="<?php echo sr_e($caseDataKey); ?>"<?php echo in_array($channel, $caseChannels, true) ? ' checked' : ''; ?>>
                                        <label for="<?php echo sr_e($channelInputId); ?>" class="btn btn-choice-light <?php echo sr_e($groupClass); ?>"><?php echo sr_admin_choice_label_html(sr_admin_code_label($channel, 'notification_channel')); ?></label>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </section>
    <?php } ?>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/asset-exchange')); ?>" class="btn btn-solid-light">정책 목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>

<?php echo sr_admin_help_modal_html($settingsHelpId, '정책 등록 기본값', $settingsHelpBody); ?>
<?php foreach ($settingsHelp as $settingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $settingsHelpModal['id'], (string) $settingsHelpModal['title'], (string) $settingsHelpModal['body_html']); ?>
<?php } ?>

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

(function () {
    var form = document.querySelector('[data-asset-exchange-settings-form]');
    if (!form) {
        return;
    }

    var bulkToggle = form.querySelector('[data-asset-exchange-notification-bulk-toggle]');
    var caseKeys = [];
    Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-key]')).forEach(function (control) {
        var caseKey = control.getAttribute('data-asset-exchange-notification-case-key') || '';
        if (caseKey && caseKeys.indexOf(caseKey) === -1) {
            caseKeys.push(caseKey);
        }
    });

    function syncCase(caseKey) {
        var toggle = form.querySelector('[data-asset-exchange-notification-case-toggle][data-asset-exchange-notification-case-key="' + caseKey + '"]');
        var channels = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-channel][data-asset-exchange-notification-case-key="' + caseKey + '"]'));
        var enabled = !toggle || toggle.checked;
        var selected = channels.some(function (channel) {
            return channel.checked;
        });
        channels.forEach(function (channel) {
            channel.disabled = !enabled;
        });
        Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-required-label][data-asset-exchange-notification-case-key="' + caseKey + '"]')).forEach(function (label) {
            label.hidden = !enabled;
        });
        if (channels[0] && typeof channels[0].setCustomValidity === 'function') {
            channels[0].setCustomValidity(!enabled || selected ? '' : '알림 채널을 하나 이상 선택하세요.');
        }
    }

    function setBulkLabel(text) {
        var label = bulkToggle && bulkToggle.closest ? bulkToggle.closest('label') : null;
        if (!label) {
            return;
        }
        for (var index = label.childNodes.length - 1; index >= 0; index -= 1) {
            if (label.childNodes[index].nodeType === 3 && label.childNodes[index].nodeValue.trim() !== '') {
                label.childNodes[index].nodeValue = text;
                return;
            }
        }
        label.appendChild(document.createTextNode(text));
    }

    function syncBulkToggle() {
        if (!bulkToggle) {
            return;
        }
        var toggles = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-toggle]'));
        var allEnabled = toggles.length > 0 && toggles.every(function (toggle) {
            return toggle.checked;
        });
        bulkToggle.checked = allEnabled;
        setBulkLabel(allEnabled ? '전체비활성' : '전체활성');
    }

    if (bulkToggle) {
        bulkToggle.addEventListener('change', function () {
            Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-toggle]')).forEach(function (toggle) {
                toggle.checked = bulkToggle.checked;
            });
            caseKeys.forEach(syncCase);
            syncBulkToggle();
        });
    }
    caseKeys.forEach(function (caseKey) {
        Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-case-key="' + caseKey + '"]')).forEach(function (control) {
            control.addEventListener('change', function () {
                syncCase(caseKey);
                syncBulkToggle();
            });
        });
        syncCase(caseKey);
    });
    syncBulkToggle();
    form.addEventListener('submit', function (event) {
        var invalidChannel = null;
        caseKeys.forEach(function (caseKey) {
            syncCase(caseKey);
            if (!invalidChannel) {
                invalidChannel = Array.prototype.slice.call(form.querySelectorAll('[data-asset-exchange-notification-channel][data-asset-exchange-notification-case-key="' + caseKey + '"]')).find(function (channel) {
                    return !channel.validity.valid;
                }) || null;
            }
        });
        if (invalidChannel) {
            event.preventDefault();
            invalidChannel.reportValidity();
        }
    });
})();
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
