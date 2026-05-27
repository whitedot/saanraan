<?php

$assetGroupPolicyFieldName = isset($assetGroupPolicyFieldName) ? (string) $assetGroupPolicyFieldName : 'manual_adjust_group_policies';
$assetGroupPolicyInputId = isset($assetGroupPolicyInputId) ? (string) $assetGroupPolicyInputId : 'manual_adjust_group_policies';
$assetGroupPolicyRows = isset($assetGroupPolicyRows) && is_array($assetGroupPolicyRows) ? $assetGroupPolicyRows : [];
$assetGroupPolicyGroups = isset($assetGroupPolicyGroups) && is_array($assetGroupPolicyGroups) ? $assetGroupPolicyGroups : [];
$assetGroupPolicyAssetModules = isset($assetGroupPolicyAssetModules) && is_array($assetGroupPolicyAssetModules) ? $assetGroupPolicyAssetModules : [];
$assetGroupPolicySectionTitle = isset($assetGroupPolicySectionTitle) ? (string) $assetGroupPolicySectionTitle : '회원 그룹 정책';
$assetGroupPolicyHelpText = isset($assetGroupPolicyHelpText) ? (string) $assetGroupPolicyHelpText : '회원 그룹별로 수동 조정 금액을 다르게 적용합니다.';
$assetGroupPolicyModeHelpModalId = $assetGroupPolicyInputId . '_mode_help_modal';
if ($assetGroupPolicyAssetModules !== [] && $assetGroupPolicyRows !== []) {
    $assetGroupPolicyExpandedRows = [];
    $assetGroupPolicyDefaultAssetModule = (string) array_key_first($assetGroupPolicyAssetModules);
    foreach ($assetGroupPolicyRows as $assetGroupPolicyRow) {
        if (!is_array($assetGroupPolicyRow)) {
            continue;
        }

        $assetGroupPolicyAssetValues = is_array($assetGroupPolicyRow['asset_values'] ?? null) ? $assetGroupPolicyRow['asset_values'] : [];
        $assetGroupPolicyFilledAssetValues = [];
        foreach ($assetGroupPolicyAssetModules as $assetModule => $assetOption) {
            $assetModule = (string) $assetModule;
            $assetValue = trim((string) ($assetGroupPolicyAssetValues[$assetModule] ?? ''));
            if ($assetValue !== '') {
                $assetGroupPolicyFilledAssetValues[$assetModule] = $assetValue;
            }
        }

        if ($assetGroupPolicyFilledAssetValues !== []) {
            foreach ($assetGroupPolicyFilledAssetValues as $assetModule => $assetValue) {
                $assetGroupPolicyExpandedRow = $assetGroupPolicyRow;
                $assetGroupPolicyExpandedRow['asset_module'] = $assetModule;
                $assetGroupPolicyExpandedRow['value'] = $assetValue;
                $assetGroupPolicyExpandedRow['asset_values'] = [];
                $assetGroupPolicyExpandedRows[] = $assetGroupPolicyExpandedRow;
            }
            continue;
        }

        if ((string) ($assetGroupPolicyRow['asset_module'] ?? '') === '' && trim((string) ($assetGroupPolicyRow['value'] ?? '')) !== '') {
            $assetGroupPolicyRow['asset_module'] = $assetGroupPolicyDefaultAssetModule;
        }
        $assetGroupPolicyExpandedRows[] = $assetGroupPolicyRow;
    }
    $assetGroupPolicyRows = $assetGroupPolicyExpandedRows;
}
if ($assetGroupPolicyRows === []) {
    $assetGroupPolicyRows[] = [
        'group_key' => '',
        'mode' => '',
        'asset_module' => '',
        'value' => '',
        'min_level' => 0,
        'status' => 'active',
    ];
}
$assetGroupPolicyModes = sr_admin_asset_group_policy_modes();
?>
<section class="admin-card admin-list-card card admin-list-form admin-asset-group-policy-editor" data-admin-asset-group-policy-editor>
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e($assetGroupPolicySectionTitle); ?></h2>
        <div class="admin-row-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-admin-asset-group-policy-add><?php echo sr_material_icon_html('add'); ?><?php echo sr_e('세트 추가'); ?></button>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table admin-asset-group-policy-table">
            <caption class="sr-only"><?php echo sr_e('회원 그룹 조건 행 목록'); ?></caption>
            <colgroup>
                <col class="admin-asset-group-policy-col-group">
                <col class="admin-asset-group-policy-col-mode">
                <?php if ($assetGroupPolicyAssetModules !== []) { ?>
                    <col class="admin-asset-group-policy-col-target">
                <?php } ?>
                <col class="admin-asset-group-policy-col-value">
                <col class="admin-asset-group-policy-col-status">
                <col class="admin-asset-group-policy-col-actions">
            </colgroup>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e('회원 그룹'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></th>
                    <th>
                        <span class="admin-asset-group-policy-heading-help">
                            <span><?php echo sr_e('적용 방식'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></span>
                            <button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="<?php echo sr_e('적용 방식 도움말'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($assetGroupPolicyModeHelpModalId); ?>" data-overlay="#<?php echo sr_e($assetGroupPolicyModeHelpModalId); ?>">
                                <?php echo sr_material_icon_html('help'); ?>
                            </button>
                        </span>
                    </th>
                    <?php if ($assetGroupPolicyAssetModules !== []) { ?>
                        <th><?php echo sr_e('대상'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></th>
                    <?php } ?>
                    <th><?php echo sr_e('값'); ?></th>
                    <th><?php echo sr_e('상태'); ?></th>
                    <th class="text-end"><?php echo sr_e('관리'); ?></th>
                </tr>
            </thead>
            <tbody data-admin-asset-group-policy-rows>
                <?php foreach ($assetGroupPolicyRows as $assetGroupPolicyIndex => $assetGroupPolicyRow) { ?>
                    <?php
                    $assetGroupPolicyRowId = $assetGroupPolicyInputId . '_' . (int) $assetGroupPolicyIndex;
                    $assetGroupPolicyGroupKey = (string) ($assetGroupPolicyRow['group_key'] ?? '');
                    $assetGroupPolicyMode = (string) ($assetGroupPolicyRow['mode'] ?? '');
                    $assetGroupPolicyAssetModule = (string) ($assetGroupPolicyRow['asset_module'] ?? '');
                    $assetGroupPolicyValue = (string) ($assetGroupPolicyRow['value'] ?? '');
                    $assetGroupPolicyUnitLabel = isset($assetGroupPolicyAssetModules[$assetGroupPolicyAssetModule])
                        ? (string) ($assetGroupPolicyAssetModules[$assetGroupPolicyAssetModule]['unit_label'] ?? '')
                        : '';
                    $assetGroupPolicyValueSuffix = $assetGroupPolicyMode === 'multiplier'
                        ? '배'
                        : (in_array($assetGroupPolicyMode, ['fixed', 'delta'], true) ? $assetGroupPolicyUnitLabel : '');
                    $assetGroupPolicyStatus = (string) ($assetGroupPolicyRow['status'] ?? 'active');
                    ?>
                    <tr data-admin-asset-group-policy-row>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_group_key"><?php echo sr_e('회원 그룹'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_group_key" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[group_key][]" class="form-select" data-admin-asset-group-policy-group>
                                <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                                <?php foreach ($assetGroupPolicyGroups as $assetGroupPolicyGroup) { ?>
                                    <?php $assetGroupKey = (string) ($assetGroupPolicyGroup['group_key'] ?? ''); ?>
                                    <?php if ($assetGroupKey === '') { continue; } ?>
                                    <option value="<?php echo sr_e($assetGroupKey); ?>"<?php echo $assetGroupPolicyGroupKey === $assetGroupKey ? ' selected' : ''; ?>>
                                        <?php echo sr_e((string) ($assetGroupPolicyGroup['title'] ?? $assetGroupKey)); ?> (<?php echo sr_e($assetGroupKey); ?>)
                                        <?php echo (string) ($assetGroupPolicyGroup['status'] ?? '') !== 'enabled' ? ' - ' . sr_e('비활성') : ''; ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_mode"><?php echo sr_e('적용 방식'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_mode" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[mode][]" class="form-select" data-admin-asset-group-policy-mode>
                                <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                                <?php foreach ($assetGroupPolicyModes as $assetGroupPolicyModeValue) { ?>
                                    <option value="<?php echo sr_e((string) $assetGroupPolicyModeValue); ?>"<?php echo $assetGroupPolicyMode === (string) $assetGroupPolicyModeValue ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_asset_group_policy_mode_label((string) $assetGroupPolicyModeValue)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <?php if ($assetGroupPolicyAssetModules !== []) { ?>
                            <td>
                                <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_asset_module"><?php echo sr_e('대상'); ?></label>
                                <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_asset_module" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[asset_module][]" class="form-select" data-admin-asset-group-policy-asset-module>
                                    <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                                    <?php foreach ($assetGroupPolicyAssetModules as $assetModule => $assetOption) { ?>
                                        <?php
                                        $assetModule = (string) $assetModule;
                                        $assetLabel = (string) ($assetOption['label'] ?? $assetModule);
                                        $assetUnit = (string) ($assetOption['unit_label'] ?? '');
                                        ?>
                                        <option value="<?php echo sr_e($assetModule); ?>" data-admin-asset-group-policy-unit="<?php echo sr_e($assetUnit); ?>"<?php echo $assetGroupPolicyAssetModule === $assetModule ? ' selected' : ''; ?>><?php echo sr_e($assetLabel); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                        <?php } ?>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_value"><?php echo sr_e('값'); ?></label>
                            <div class="input-group admin-asset-group-policy-value-group<?php echo $assetGroupPolicyValueSuffix === '' ? ' admin-asset-group-policy-value-group-unitless' : ''; ?>">
                                <input id="<?php echo sr_e($assetGroupPolicyRowId); ?>_value" type="text" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[value][]" value="<?php echo sr_e($assetGroupPolicyValue); ?>" class="form-input admin-asset-group-policy-value-input" maxlength="30" placeholder="<?php echo sr_e('금액 또는 배율'); ?>" data-admin-asset-group-policy-value>
                                <span class="input-group-text" data-admin-asset-group-policy-unit-label<?php echo $assetGroupPolicyValueSuffix === '' ? ' hidden' : ''; ?>><?php echo sr_e($assetGroupPolicyValueSuffix); ?></span>
                            </div>
                        </td>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_status"><?php echo sr_e('상태'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_status" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[status][]" class="form-select" data-admin-asset-group-policy-status>
                                <?php foreach (['active', 'inactive'] as $assetGroupPolicyStatusValue) { ?>
                                    <option value="<?php echo sr_e($assetGroupPolicyStatusValue); ?>"<?php echo $assetGroupPolicyStatus === $assetGroupPolicyStatusValue ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_asset_group_policy_status_label($assetGroupPolicyStatusValue)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-admin-asset-group-policy-remove aria-label="<?php echo sr_e('행 삭제'); ?>" title="<?php echo sr_e('삭제'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-list-summary admin-asset-group-policy-notes">
        <?php if ($assetGroupPolicyHelpText !== '') { ?>
            <p><?php echo sr_e($assetGroupPolicyHelpText); ?></p>
        <?php } ?>
        <p class="admin-asset-group-policy-summary-help"><?php echo sr_e('고정 금액과 증감액은 정수로 입력하고, 배율은 1.5처럼 입력합니다. 차감 면제와 지급/차감 안 함은 값을 비워도 됩니다.'); ?></p>
    </div>
    <template data-admin-asset-group-policy-template>
        <tr data-admin-asset-group-policy-row>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="group_key"><?php echo sr_e('회원 그룹'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[group_key][]" class="form-select" data-admin-asset-group-policy-control="group_key" data-admin-asset-group-policy-group>
                    <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                    <?php foreach ($assetGroupPolicyGroups as $assetGroupPolicyGroup) { ?>
                        <?php $assetGroupKey = (string) ($assetGroupPolicyGroup['group_key'] ?? ''); ?>
                        <?php if ($assetGroupKey === '') { continue; } ?>
                        <option value="<?php echo sr_e($assetGroupKey); ?>">
                            <?php echo sr_e((string) ($assetGroupPolicyGroup['title'] ?? $assetGroupKey)); ?> (<?php echo sr_e($assetGroupKey); ?>)
                            <?php echo (string) ($assetGroupPolicyGroup['status'] ?? '') !== 'enabled' ? ' - ' . sr_e('비활성') : ''; ?>
                        </option>
                    <?php } ?>
                </select>
            </td>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="mode"><?php echo sr_e('적용 방식'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[mode][]" class="form-select" data-admin-asset-group-policy-control="mode" data-admin-asset-group-policy-mode>
                    <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                    <?php foreach ($assetGroupPolicyModes as $assetGroupPolicyModeValue) { ?>
                        <option value="<?php echo sr_e((string) $assetGroupPolicyModeValue); ?>"><?php echo sr_e(sr_admin_asset_group_policy_mode_label((string) $assetGroupPolicyModeValue)); ?></option>
                    <?php } ?>
                </select>
            </td>
            <?php if ($assetGroupPolicyAssetModules !== []) { ?>
                <td>
                    <label class="sr-only" data-admin-asset-group-policy-label="asset_module"><?php echo sr_e('대상'); ?></label>
                    <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[asset_module][]" class="form-select" data-admin-asset-group-policy-control="asset_module" data-admin-asset-group-policy-asset-module>
                        <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                        <?php foreach ($assetGroupPolicyAssetModules as $assetModule => $assetOption) { ?>
                            <?php
                            $assetModule = (string) $assetModule;
                            $assetLabel = (string) ($assetOption['label'] ?? $assetModule);
                            $assetUnit = (string) ($assetOption['unit_label'] ?? '');
                            ?>
                            <option value="<?php echo sr_e($assetModule); ?>" data-admin-asset-group-policy-unit="<?php echo sr_e($assetUnit); ?>"><?php echo sr_e($assetLabel); ?></option>
                        <?php } ?>
                    </select>
                </td>
            <?php } ?>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="value"><?php echo sr_e('값'); ?></label>
                <div class="input-group admin-asset-group-policy-value-group admin-asset-group-policy-value-group-unitless">
                    <input type="text" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[value][]" class="form-input admin-asset-group-policy-value-input" maxlength="30" placeholder="<?php echo sr_e('금액 또는 배율'); ?>" data-admin-asset-group-policy-control="value" data-admin-asset-group-policy-value>
                    <span class="input-group-text" data-admin-asset-group-policy-unit-label hidden></span>
                </div>
            </td>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="status"><?php echo sr_e('상태'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[status][]" class="form-select" data-admin-asset-group-policy-control="status" data-admin-asset-group-policy-status>
                    <option value="active"><?php echo sr_e('활성'); ?></option>
                    <option value="inactive"><?php echo sr_e('비활성'); ?></option>
                </select>
            </td>
            <td class="admin-table-actions-cell">
                <div class="admin-row-actions">
                    <button type="button" class="btn btn-sm btn-icon btn-outline-danger" data-admin-asset-group-policy-remove aria-label="<?php echo sr_e('행 삭제'); ?>" title="<?php echo sr_e('삭제'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                </div>
            </td>
        </tr>
    </template>
</section>
<?php
$assetGroupPolicyModeHelpBodyHtml = '<p>' . sr_e('적용 방식에 따라 값 입력 여부와 최종 금액 계산 방식이 달라집니다.') . '</p>'
    . '<ul>'
    . '<li><strong>' . sr_e('고정 금액') . '</strong>: ' . sr_e('최종 금액으로 사용할 정수를 입력합니다. 예: 1000') . '</li>'
    . '<li><strong>' . sr_e('증감액') . '</strong>: ' . sr_e('기본 금액에 더하거나 뺄 정수를 입력합니다. 예: -500') . '</li>'
    . '<li><strong>' . sr_e('배율') . '</strong>: ' . sr_e('기본 금액에 곱할 숫자를 입력합니다. 예: 1.5') . '</li>'
    . '<li><strong>' . sr_e('차감 면제') . '</strong>: ' . sr_e('차감 대상 금액을 0으로 처리합니다.') . '</li>'
    . '<li><strong>' . sr_e('지급/차감 안 함') . '</strong>: ' . sr_e('이 조건에서는 지급 또는 차감 금액을 만들지 않습니다.') . '</li>'
    . '</ul>'
    . '<p>' . sr_e('여러 자산을 지원하는 혜택 세트에서는 한 행에 대상 하나와 값 하나만 입력합니다. 다른 대상에는 세트를 추가해 별도 행으로 관리합니다.') . '</p>';
echo sr_admin_help_modal_html($assetGroupPolicyModeHelpModalId, '적용 방식 도움말', $assetGroupPolicyModeHelpBodyHtml);
?>
<script>
(function () {
    var editors = document.querySelectorAll('[data-admin-asset-group-policy-editor]');
    var rowSequence = 0;

    function setSequentialLock(control, locked) {
        if (!control) {
            return;
        }

        control.classList.toggle('admin-asset-group-policy-locked', locked);
        control.setAttribute('aria-disabled', locked ? 'true' : 'false');
        control.tabIndex = locked ? -1 : 0;
    }

    function syncValueHelp(row) {
        if (!row) {
            return;
        }

        var mode = row.querySelector('[data-admin-asset-group-policy-mode]');
        var assetModule = row.querySelector('[data-admin-asset-group-policy-asset-module]');
        var values = row.querySelectorAll('[data-admin-asset-group-policy-value]');
        var group = row.querySelector('[data-admin-asset-group-policy-group]');
        var status = row.querySelector('[data-admin-asset-group-policy-status]');
        var groupSelected = !!(group && group.value);
        var modeValue = mode ? mode.value : '';
        var modeSelected = modeValue !== '';
        var assetModuleSelected = !assetModule || !!assetModule.value;
        var hasValue = false;
        values.forEach(function (valueInput) {
            if (valueInput.value.trim() !== '') {
                hasValue = true;
            }
        });
        var rowActive = !!(
            (group && group.value)
            || modeValue
            || (assetModule && assetModule.value)
            || hasValue
        );
        var requiresValue = modeValue === 'fixed' || modeValue === 'delta' || modeValue === 'multiplier';
        if (group) {
            group.required = rowActive;
        }
        if (mode) {
            setSequentialLock(mode, !groupSelected);
            if (!groupSelected && mode.value !== '') {
                mode.value = '';
                modeValue = '';
                modeSelected = false;
                requiresValue = false;
            }
            mode.required = rowActive;
        }
        if (assetModule) {
            setSequentialLock(assetModule, !groupSelected || !modeSelected);
            if ((!groupSelected || !modeSelected) && assetModule.value !== '') {
                assetModule.value = '';
                assetModuleSelected = false;
            }
            assetModule.required = rowActive;
        }
        var requiredSelectionsReady = !!(
            groupSelected
            && modeSelected
            && assetModuleSelected
        );
        var valueHasContent = false;
        values.forEach(function (valueInput) {
            var valueEnabled = requiredSelectionsReady && requiresValue;
            valueInput.readOnly = !valueEnabled;
            valueInput.setAttribute('aria-disabled', valueEnabled ? 'false' : 'true');
            if (!valueEnabled && valueInput.value !== '') {
                valueInput.value = '';
            }
            if (valueInput.value.trim() !== '') {
                valueHasContent = true;
            }
            valueInput.required = valueEnabled;
            valueInput.setCustomValidity('');
            if (!requiredSelectionsReady) {
                valueInput.placeholder = '대기';
            } else if (requiresValue) {
                valueInput.placeholder = modeValue === 'multiplier' ? '필수: 예 1.5' : '필수: 예 1000';
            } else {
                valueInput.placeholder = '입력 없음';
            }
        });
        if (status) {
            setSequentialLock(status, !requiredSelectionsReady || (requiresValue && !valueHasContent));
        }
        syncValueUnit(row);
    }

    function syncValueUnit(row) {
        var assetModule = row.querySelector('[data-admin-asset-group-policy-asset-module]');
        var unitLabel = row.querySelector('[data-admin-asset-group-policy-unit-label]');
        if (!unitLabel) {
            return;
        }

        var option = assetModule && assetModule.selectedOptions && assetModule.selectedOptions.length > 0 ? assetModule.selectedOptions[0] : null;
        var mode = row.querySelector('[data-admin-asset-group-policy-mode]');
        var modeValue = mode ? mode.value : '';
        var unit = option ? (option.getAttribute('data-admin-asset-group-policy-unit') || '') : '';
        if (modeValue === 'multiplier') {
            unit = '배';
        } else if (modeValue !== 'fixed' && modeValue !== 'delta') {
            unit = '';
        }
        unitLabel.textContent = unit;
        unitLabel.hidden = unit === '';
        var group = unitLabel.closest('.admin-asset-group-policy-value-group');
        if (group) {
            group.classList.toggle('admin-asset-group-policy-value-group-unitless', unit === '');
        }
    }

    function prepareClonedRow(row) {
        if (!row) {
            return;
        }

        rowSequence += 1;
        var rowId = 'admin_asset_group_policy_added_' + String(Date.now()) + '_' + String(rowSequence);
        var controls = row.querySelectorAll('[data-admin-asset-group-policy-control]');
        controls.forEach(function (control) {
            var field = control.getAttribute('data-admin-asset-group-policy-control') || '';
            if (!field) {
                return;
            }

            var controlId = rowId + '_' + field;
            var label = row.querySelector('[data-admin-asset-group-policy-label="' + field + '"]');
            control.id = controlId;
            if (label) {
                label.setAttribute('for', controlId);
            }
        });
        syncValueHelp(row);
    }

    editors.forEach(function (editor) {
        var rows = editor.querySelector('[data-admin-asset-group-policy-rows]');
        var template = editor.querySelector('[data-admin-asset-group-policy-template]');
        var addButton = editor.querySelector('[data-admin-asset-group-policy-add]');
        if (addButton && rows && template) {
            addButton.addEventListener('click', function () {
                var fragment = template.content.cloneNode(true);
                prepareClonedRow(fragment.querySelector('[data-admin-asset-group-policy-row]'));
                rows.appendChild(fragment);
            });
        }
        rows.querySelectorAll('[data-admin-asset-group-policy-row]').forEach(syncValueHelp);
        editor.addEventListener('change', function (event) {
            var field = event.target && event.target.closest ? event.target.closest('[data-admin-asset-group-policy-row]') : null;
            if (!field) {
                return;
            }
            syncValueHelp(field);
        });
        editor.addEventListener('input', function (event) {
            var field = event.target && event.target.closest ? event.target.closest('[data-admin-asset-group-policy-row]') : null;
            if (!field) {
                return;
            }
            syncValueHelp(field);
        });
        editor.addEventListener('click', function (event) {
            var removeButton = event.target && event.target.closest ? event.target.closest('[data-admin-asset-group-policy-remove]') : null;
            if (!removeButton || !rows) {
                return;
            }
            var row = removeButton.closest('[data-admin-asset-group-policy-row]');
            if (row) {
                row.remove();
            }
        });
    });
})();
</script>
