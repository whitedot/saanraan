<?php

$assetGroupPolicyFieldName = isset($assetGroupPolicyFieldName) ? (string) $assetGroupPolicyFieldName : 'manual_adjust_group_policies';
$assetGroupPolicyInputId = isset($assetGroupPolicyInputId) ? (string) $assetGroupPolicyInputId : 'manual_adjust_group_policies';
$assetGroupPolicyRows = isset($assetGroupPolicyRows) && is_array($assetGroupPolicyRows) ? $assetGroupPolicyRows : [];
$assetGroupPolicyGroups = isset($assetGroupPolicyGroups) && is_array($assetGroupPolicyGroups) ? $assetGroupPolicyGroups : [];
$assetGroupPolicySectionTitle = isset($assetGroupPolicySectionTitle) ? (string) $assetGroupPolicySectionTitle : '회원 그룹 정책';
$assetGroupPolicyHelpText = isset($assetGroupPolicyHelpText) ? (string) $assetGroupPolicyHelpText : '회원 그룹별로 수동 조정 금액을 다르게 적용합니다.';
$assetGroupPolicyLevelEnabled = !empty($assetGroupPolicyLevelEnabled);
$assetGroupPolicyMaxLevel = isset($assetGroupPolicyMaxLevel) ? max(0, (int) $assetGroupPolicyMaxLevel) : 0;
if ($assetGroupPolicyRows === []) {
    $assetGroupPolicyRows[] = [
        'group_key' => '',
        'mode' => '',
        'value' => '',
        'min_level' => 0,
        'priority' => 0,
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
                <?php if ($assetGroupPolicyLevelEnabled) { ?>
                    <col class="admin-asset-group-policy-col-level">
                <?php } ?>
                <col class="admin-asset-group-policy-col-mode">
                <col class="admin-asset-group-policy-col-value">
                <col class="admin-asset-group-policy-col-priority">
                <col class="admin-asset-group-policy-col-status">
                <col class="admin-asset-group-policy-col-actions">
            </colgroup>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e('회원 그룹'); ?></th>
                    <?php if ($assetGroupPolicyLevelEnabled) { ?>
                        <th><?php echo sr_e('최소 레벨'); ?></th>
                    <?php } ?>
                    <th><?php echo sr_e('적용 방식'); ?></th>
                    <th><?php echo sr_e('값'); ?></th>
                    <th><?php echo sr_e('우선순위'); ?></th>
                    <th><?php echo sr_e('상태'); ?></th>
                    <th class="text-end"><?php echo sr_e('관리'); ?></th>
                </tr>
            </thead>
            <tbody data-admin-asset-group-policy-rows>
                <?php foreach ($assetGroupPolicyRows as $assetGroupPolicyIndex => $assetGroupPolicyRow) { ?>
                    <?php
                    $assetGroupPolicyRowId = $assetGroupPolicyInputId . '_' . (int) $assetGroupPolicyIndex;
                    $assetGroupPolicyGroupKey = (string) ($assetGroupPolicyRow['group_key'] ?? '');
                    $assetGroupPolicyMinLevel = max(0, (int) ($assetGroupPolicyRow['min_level'] ?? 0));
                    $assetGroupPolicyMode = (string) ($assetGroupPolicyRow['mode'] ?? '');
                    $assetGroupPolicyValue = (string) ($assetGroupPolicyRow['value'] ?? '');
                    $assetGroupPolicyPriority = (string) ($assetGroupPolicyRow['priority'] ?? '0');
                    $assetGroupPolicyStatus = (string) ($assetGroupPolicyRow['status'] ?? 'active');
                    ?>
                    <tr data-admin-asset-group-policy-row>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_group_key"><?php echo sr_e('회원 그룹'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_group_key" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[group_key][]" class="form-select">
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
                        <?php if ($assetGroupPolicyLevelEnabled) { ?>
                            <td>
                                <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_min_level"><?php echo sr_e('최소 레벨'); ?></label>
                                <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_min_level" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[min_level][]" class="form-select">
                                    <?php for ($assetGroupPolicyLevel = 0; $assetGroupPolicyLevel <= $assetGroupPolicyMaxLevel; $assetGroupPolicyLevel += 1) { ?>
                                        <option value="<?php echo sr_e((string) $assetGroupPolicyLevel); ?>"<?php echo $assetGroupPolicyMinLevel === $assetGroupPolicyLevel ? ' selected' : ''; ?>>
                                            <?php echo $assetGroupPolicyLevel === 0 ? sr_e('제한 없음') : sr_e((string) $assetGroupPolicyLevel); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                        <?php } ?>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_mode"><?php echo sr_e('적용 방식'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_mode" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[mode][]" class="form-select">
                                <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                                <?php foreach ($assetGroupPolicyModes as $assetGroupPolicyModeValue) { ?>
                                    <option value="<?php echo sr_e((string) $assetGroupPolicyModeValue); ?>"<?php echo $assetGroupPolicyMode === (string) $assetGroupPolicyModeValue ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_asset_group_policy_mode_label((string) $assetGroupPolicyModeValue)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </td>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_value"><?php echo sr_e('값'); ?></label>
                            <input id="<?php echo sr_e($assetGroupPolicyRowId); ?>_value" type="text" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[value][]" value="<?php echo sr_e($assetGroupPolicyValue); ?>" class="form-input" maxlength="30" placeholder="<?php echo sr_e('금액 또는 배율'); ?>">
                        </td>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_priority"><?php echo sr_e('우선순위'); ?></label>
                            <input id="<?php echo sr_e($assetGroupPolicyRowId); ?>_priority" type="number" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[priority][]" value="<?php echo sr_e($assetGroupPolicyPriority); ?>" class="form-input" step="1" min="0" max="1000000">
                        </td>
                        <td>
                            <label class="sr-only" for="<?php echo sr_e($assetGroupPolicyRowId); ?>_status"><?php echo sr_e('상태'); ?></label>
                            <select id="<?php echo sr_e($assetGroupPolicyRowId); ?>_status" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[status][]" class="form-select">
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
        <p class="admin-asset-group-policy-summary-help"><?php echo sr_e('고정 금액과 증감액은 정수로 입력하고, 배율은 1.5처럼 입력합니다. 면제/미지급은 값을 비워도 됩니다.'); ?></p>
    </div>
    <template data-admin-asset-group-policy-template>
        <tr data-admin-asset-group-policy-row>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="group_key"><?php echo sr_e('회원 그룹'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[group_key][]" class="form-select" data-admin-asset-group-policy-control="group_key">
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
            <?php if ($assetGroupPolicyLevelEnabled) { ?>
                <td>
                    <label class="sr-only" data-admin-asset-group-policy-label="min_level"><?php echo sr_e('최소 레벨'); ?></label>
                    <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[min_level][]" class="form-select" data-admin-asset-group-policy-control="min_level">
                        <?php for ($assetGroupPolicyLevel = 0; $assetGroupPolicyLevel <= $assetGroupPolicyMaxLevel; $assetGroupPolicyLevel += 1) { ?>
                            <option value="<?php echo sr_e((string) $assetGroupPolicyLevel); ?>"><?php echo $assetGroupPolicyLevel === 0 ? sr_e('제한 없음') : sr_e((string) $assetGroupPolicyLevel); ?></option>
                        <?php } ?>
                    </select>
                </td>
            <?php } ?>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="mode"><?php echo sr_e('적용 방식'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[mode][]" class="form-select" data-admin-asset-group-policy-control="mode">
                    <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                    <?php foreach ($assetGroupPolicyModes as $assetGroupPolicyModeValue) { ?>
                        <option value="<?php echo sr_e((string) $assetGroupPolicyModeValue); ?>"><?php echo sr_e(sr_admin_asset_group_policy_mode_label((string) $assetGroupPolicyModeValue)); ?></option>
                    <?php } ?>
                </select>
            </td>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="value"><?php echo sr_e('값'); ?></label>
                <input type="text" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[value][]" class="form-input" maxlength="30" placeholder="<?php echo sr_e('금액 또는 배율'); ?>" data-admin-asset-group-policy-control="value">
            </td>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="priority"><?php echo sr_e('우선순위'); ?></label>
                <input type="number" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[priority][]" value="0" class="form-input" step="1" min="0" max="1000000" data-admin-asset-group-policy-control="priority">
            </td>
            <td>
                <label class="sr-only" data-admin-asset-group-policy-label="status"><?php echo sr_e('상태'); ?></label>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[status][]" class="form-select" data-admin-asset-group-policy-control="status">
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
<script>
(function () {
    var editors = document.querySelectorAll('[data-admin-asset-group-policy-editor]');
    var rowSequence = 0;

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
