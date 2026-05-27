<?php

$assetGroupPolicyFieldName = isset($assetGroupPolicyFieldName) ? (string) $assetGroupPolicyFieldName : 'manual_adjust_group_policies';
$assetGroupPolicyInputId = isset($assetGroupPolicyInputId) ? (string) $assetGroupPolicyInputId : 'manual_adjust_group_policies';
$assetGroupPolicyRows = isset($assetGroupPolicyRows) && is_array($assetGroupPolicyRows) ? $assetGroupPolicyRows : [];
$assetGroupPolicyGroups = isset($assetGroupPolicyGroups) && is_array($assetGroupPolicyGroups) ? $assetGroupPolicyGroups : [];
$assetGroupPolicyHelpText = isset($assetGroupPolicyHelpText) ? (string) $assetGroupPolicyHelpText : '회원 그룹별로 수동 조정 금액을 다르게 적용합니다.';
if ($assetGroupPolicyRows === []) {
    $assetGroupPolicyRows[] = [
        'group_key' => '',
        'mode' => '',
        'value' => '',
        'priority' => 0,
        'status' => 'active',
    ];
}
$assetGroupPolicyModes = sr_admin_asset_group_policy_modes();
?>
<div class="admin-asset-group-policy-editor" data-admin-asset-group-policy-editor>
    <div class="admin-list-summary-row">
        <p class="admin-form-help"><?php echo sr_e($assetGroupPolicyHelpText); ?></p>
        <button type="button" class="btn btn-sm btn-solid-light" data-admin-asset-group-policy-add><?php echo sr_e('정책 추가'); ?></button>
    </div>
    <div class="table-wrapper">
        <table class="table admin-asset-group-policy-table">
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e('회원 그룹'); ?></th>
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
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-admin-asset-group-policy-remove><?php echo sr_e('삭제'); ?></button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <p class="admin-form-help"><?php echo sr_e('고정 금액과 증감액은 정수, 배율은 예: 1.5처럼 입력합니다. 면제/미지급은 값이 필요 없습니다.'); ?></p>
    <template data-admin-asset-group-policy-template>
        <tr data-admin-asset-group-policy-row>
            <td>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[group_key][]" class="form-select">
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
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[mode][]" class="form-select">
                    <option value=""><?php echo sr_e('선택 안 함'); ?></option>
                    <?php foreach ($assetGroupPolicyModes as $assetGroupPolicyModeValue) { ?>
                        <option value="<?php echo sr_e((string) $assetGroupPolicyModeValue); ?>"><?php echo sr_e(sr_admin_asset_group_policy_mode_label((string) $assetGroupPolicyModeValue)); ?></option>
                    <?php } ?>
                </select>
            </td>
            <td><input type="text" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[value][]" class="form-input" maxlength="30" placeholder="<?php echo sr_e('금액 또는 배율'); ?>"></td>
            <td><input type="number" name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[priority][]" value="0" class="form-input" step="1" min="0" max="1000000"></td>
            <td>
                <select name="<?php echo sr_e($assetGroupPolicyFieldName); ?>[status][]" class="form-select">
                    <option value="active"><?php echo sr_e('활성'); ?></option>
                    <option value="inactive"><?php echo sr_e('비활성'); ?></option>
                </select>
            </td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-admin-asset-group-policy-remove><?php echo sr_e('삭제'); ?></button></td>
        </tr>
    </template>
</div>
<script>
(function () {
    var editors = document.querySelectorAll('[data-admin-asset-group-policy-editor]');
    editors.forEach(function (editor) {
        var rows = editor.querySelector('[data-admin-asset-group-policy-rows]');
        var template = editor.querySelector('[data-admin-asset-group-policy-template]');
        var addButton = editor.querySelector('[data-admin-asset-group-policy-add]');
        if (addButton && rows && template) {
            addButton.addEventListener('click', function () {
                rows.appendChild(template.content.cloneNode(true));
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
