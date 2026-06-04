<?php

declare(strict_types=1);

function sr_admin_form_label_help_html(string $forId, string $label, string $modalId, string $helpLabel = '설명 보기', bool $required = false): string
{
    $forId = trim($forId);
    $modalId = trim($modalId);
    $helpLabel = trim($helpLabel) !== '' ? trim($helpLabel) : '설명 보기';
    $requiredHtml = $required ? ' <span class="sr-required-label">(필수)</span>' : '';

    return '<div class="form-label admin-form-label-help">'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $helpLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>'
        . '<label for="' . sr_e($forId) . '">' . sr_e($label) . $requiredHtml . '</label>'
        . '</div>';
}

function sr_admin_help_modal_html(string $modalId, string $title, string $bodyHtml): string
{
    $modalId = trim($modalId);

    return '<div id="' . sr_e($modalId) . '" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="' . sr_e($modalId) . '_title" aria-hidden="true" inert data-overlay-stack="true">'
        . '<div class="modal-dialog">'
        . '<div class="modal-content">'
        . '<div class="modal-header">'
        . '<h3 id="' . sr_e($modalId) . '_title" class="modal-title">' . sr_e($title) . '</h3>'
        . '<button type="button" class="modal-close" aria-label="닫기" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('close')
        . '</button>'
        . '</div>'
        . '<div class="modal-body admin-help-modal-body">' . $bodyHtml . '</div>'
        . '<div class="modal-footer">'
        . '<button type="button" class="btn btn-solid-light modal-action" data-overlay="#' . sr_e($modalId) . '">닫기</button>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '</div>';
}

function sr_admin_checkbox_list_html(string $id, string $name, array $options, array $selectedValues, string $emptyLabel = '선택 항목 없음'): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_checkbox_list';
    $html = '<div id="' . sr_e($id) . '" class="admin-check-list" role="group">';

    if ($options === []) {
        $html .= '<span class="admin-form-help">' . sr_e($emptyLabel) . '</span>';
        return $html . '</div>';
    }

    $index = 0;
    foreach ($options as $value => $label) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        $inputId = $idBase . '_' . (string) $index;
        $html .= '<label class="admin-form-check form-label" for="' . sr_e($inputId) . '">'
            . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($name) . '[]" value="' . sr_e($value) . '" class="form-checkbox"' . (isset($selectedMap[$value]) ? ' checked' : '') . '>'
            . sr_admin_choice_label_html((string) $label)
            . '</label>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_filter_toggle_group_html(string $id, string $name, array $options, array $selectedValues, string $allLabel = '전체'): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_filter_toggle';
    $name = preg_replace('/\[\]\z/', '', trim($name)) ?? trim($name);
    $name = $name !== '' ? $name : 'filter';
    $toggleOptions = [];
    foreach ($options as $value => $label) {
        $value = (string) $value;
        $labelText = trim((string) $label);
        if ($value === '' || $value === 'all' || $labelText === trim($allLabel)) {
            continue;
        }

        $toggleOptions[$value] = $label;
    }
    $selectedMap = array_intersect_key($selectedMap, $toggleOptions);

    $html = '<div id="' . sr_e($id) . '" class="filtering-toggle-group" data-filtering-toggle-group>';

    $html .= '<span class="filtering-toggle-item">'
        . '<input id="' . sr_e($idBase . '_all') . '" type="checkbox" class="form-choice-toggle-input sr-only" data-filtering-toggle-all' . ($selectedMap === [] ? ' checked' : '') . '>'
        . '<label for="' . sr_e($idBase . '_all') . '" class="btn btn-choice-light btn-group-start">' . sr_e($allLabel) . '</label>'
        . '</span>';

    $index = 0;
    $lastIndex = max(0, count($toggleOptions) - 1);
    foreach ($toggleOptions as $value => $label) {

        $groupClass = $index === $lastIndex ? 'btn-group-end' : 'btn-group-middle';
        $inputId = $idBase . '_' . (string) $index;
        $html .= '<span class="filtering-toggle-item">'
            . '<input id="' . sr_e($inputId) . '" type="checkbox" name="' . sr_e($name) . '[]" value="' . sr_e($value) . '" class="form-choice-toggle-input sr-only" data-filtering-toggle-choice' . (isset($selectedMap[$value]) ? ' checked' : '') . '>'
            . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light ' . $groupClass . '">' . sr_e((string) $label) . '</label>'
            . '</span>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_filter_radio_toggle_group_html(string $id, string $name, array $options, array $selectedValues, string $allLabel = '전체'): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_filter_radio_toggle';
    $name = preg_replace('/\[\]\z/', '', trim($name)) ?? trim($name);
    $name = $name !== '' ? $name : 'filter';
    $toggleOptions = [];
    foreach ($options as $value => $label) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        $toggleOptions[$value] = $label;
    }
    $selectedMap = array_intersect_key($selectedMap, $toggleOptions);

    $html = '<div id="' . sr_e($id) . '" class="filtering-toggle-group filtering-radio-toggle-group" data-filtering-radio-toggle-group>';
    $html .= '<span class="filtering-toggle-item">'
        . '<input id="' . sr_e($idBase . '_all') . '" type="radio" name="' . sr_e($name) . '" value="" class="form-choice-toggle-input sr-only" data-filtering-radio-toggle-choice' . ($selectedMap === [] || isset($selectedMap['']) ? ' checked' : '') . '>'
        . '<label for="' . sr_e($idBase . '_all') . '" class="btn btn-choice-light btn-group-start">' . sr_e($allLabel) . '</label>'
        . '</span>';

    $index = 0;
    $lastIndex = max(0, count($toggleOptions) - 1);
    foreach ($toggleOptions as $value => $label) {
        $groupClass = $index === $lastIndex ? 'btn-group-end' : 'btn-group-middle';
        $inputId = $idBase . '_' . (string) $index;
        $html .= '<span class="filtering-toggle-item">'
            . '<input id="' . sr_e($inputId) . '" type="radio" name="' . sr_e($name) . '" value="' . sr_e($value) . '" class="form-choice-toggle-input sr-only" data-filtering-radio-toggle-choice' . (isset($selectedMap[$value]) ? ' checked' : '') . '>'
            . '<label for="' . sr_e($inputId) . '" class="btn btn-choice-light ' . $groupClass . '">' . sr_e((string) $label) . '</label>'
            . '</span>';
        $index++;
    }

    return $html . '</div>';
}

function sr_admin_code_label_options(array $values, string $type): array
{
    $options = [];
    foreach ($values as $value) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }
        $options[$value] = sr_admin_code_label($value, $type);
    }

    return $options;
}

function sr_admin_select_badge_list_html(string $id, string $name, array $options, array $selectedValues, string $emptyLabel = '선택 항목 없음', string $placeholder = '선택', string $rootAttributes = ''): string
{
    $selectedMap = [];
    foreach ($selectedValues as $selectedValue) {
        $selectedMap[(string) $selectedValue] = true;
    }

    $idBase = preg_replace('/[^a-zA-Z0-9_-]+/', '_', trim($id));
    $idBase = is_string($idBase) && $idBase !== '' ? $idBase : 'admin_select_badge_list';
    $html = '<div id="' . sr_e($id) . '" class="admin-select-badge-list" data-admin-select-badge-list' . $rootAttributes . '>';

    if ($options === []) {
        $html .= '<span class="admin-form-help">' . sr_e($emptyLabel) . '</span>';
        return $html . '</div>';
    }

    $html .= '<select id="' . sr_e($idBase . '_select') . '" class="form-select admin-select-badge-list-select" data-admin-select-badge-list-select>'
        . '<option value="">' . sr_e($placeholder) . '</option>';
    foreach ($options as $value => $option) {
        $value = (string) $value;
        if ($value === '') {
            continue;
        }

        $label = is_array($option) ? (string) ($option['label'] ?? $value) : (string) $option;
        $summary = is_array($option) ? (string) ($option['summary'] ?? '') : '';
        $summaryAttributes = '';
        if (is_array($option) && is_array($option['summaries'] ?? null)) {
            foreach ($option['summaries'] as $summaryKey => $summaryValue) {
                $summaryKey = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) $summaryKey) ?? '';
                if ($summaryKey !== '') {
                    $summaryAttributes .= ' data-admin-select-badge-summary-' . sr_e($summaryKey) . '="' . sr_e((string) $summaryValue) . '"';
                }
            }
        }
        $assetAttributes = '';
        if (is_array($option) && is_array($option['assets'] ?? null)) {
            $assetValues = [];
            foreach ($option['assets'] as $assetValue) {
                $assetValue = preg_replace('/[^a-zA-Z0-9_-]+/', '', (string) $assetValue) ?? '';
                if ($assetValue !== '') {
                    $assetValues[] = $assetValue;
                }
            }
            if ($assetValues !== []) {
                $assetAttributes = ' data-admin-select-badge-assets="' . sr_e(implode(' ', array_values(array_unique($assetValues)))) . '"';
            }
        }
        $html .= '<option value="' . sr_e($value) . '" data-admin-select-badge-label="' . sr_e($label) . '" data-admin-select-badge-summary="' . sr_e($summary) . '"' . $summaryAttributes . $assetAttributes . '>'
            . sr_e($label)
            . '</option>';
    }
    $html .= '</select><div class="badge-list" data-admin-select-badge-list-items>';

    foreach ($options as $value => $option) {
        $value = (string) $value;
        if ($value === '' || !isset($selectedMap[$value])) {
            continue;
        }

        $label = is_array($option) ? (string) ($option['label'] ?? $value) : (string) $option;
        $summary = is_array($option) ? (string) ($option['summary'] ?? '') : '';
        $html .= sr_admin_select_badge_list_item_html($name, $value, $label, $summary);
    }

    $html .= '</div></div>';

    static $scriptPrinted = false;
    if (!$scriptPrinted) {
        $scriptPrinted = true;
        $html .= '<script>
(function () {
    function optionLabel(option) {
        return option ? (option.getAttribute("data-admin-select-badge-label") || option.textContent || "").replace(/\s+/g, " ").trim() : "";
    }
    function optionSummary(option, root) {
        if (!option) {
            return "";
        }
        var sourceSelector = root ? (root.getAttribute("data-admin-select-badge-summary-source") || "") : "";
        var source = sourceSelector ? document.querySelector(sourceSelector) : null;
        var defaultSourceValue = root ? (root.getAttribute("data-admin-select-badge-summary-default") || "") : "";
        var sourceValue = source && source.value ? String(source.value).replace(/[^a-zA-Z0-9_-]/g, "") : "";
        var assets = selectedAssets(root);
        var summarySourceValue = sourceValue || defaultSourceValue || "neutral";
        if (summarySourceValue && assets.length > 0) {
            var assetSummaries = [];
            assets.forEach(function (asset) {
                var sourceAssetSummary = option.getAttribute("data-admin-select-badge-summary-" + summarySourceValue + "_" + asset);
                if (sourceAssetSummary) {
                    assetSummaries.push(sourceAssetSummary);
                }
            });
            if (assetSummaries.length > 0) {
                return assetSummaries.join(" / ");
            }
        }
        if (sourceValue) {
            var sourceSummary = option.getAttribute("data-admin-select-badge-summary-" + sourceValue);
            if (sourceSummary !== null) {
                return sourceSummary || "";
            }
        }
        return option.getAttribute("data-admin-select-badge-summary") || "";
    }
    function selectedAssets(root) {
        var sourceSelector = root ? (root.getAttribute("data-admin-select-badge-asset-source") || "") : "";
        var source = sourceSelector ? document.querySelector(sourceSelector) : null;
        if (!source) {
            return [];
        }
        var values = [];
        source.querySelectorAll("input[type=checkbox], input[type=radio]").forEach(function (control) {
            if (!control.disabled && control.checked && control.value) {
                values.push(String(control.value).replace(/[^a-zA-Z0-9_-]/g, ""));
            }
        });
        if (values.length === 0 && source.matches && (source.matches("select") || source.matches("input"))) {
            if (!source.disabled && source.value) {
                values.push(String(source.value).replace(/[^a-zA-Z0-9_-]/g, ""));
            }
        }
        return values.filter(function (value, index, list) {
            return value && list.indexOf(value) === index;
        });
    }
    function optionMatchesAssets(option, root) {
        var assets = selectedAssets(root);
        var optionAssets = option ? (option.getAttribute("data-admin-select-badge-assets") || "").split(/\s+/).filter(Boolean) : [];
        if (!root || !root.getAttribute("data-admin-select-badge-asset-source")) {
            return true;
        }
        if (assets.length === 0) {
            return true;
        }
        if (optionAssets.length === 0) {
            return true;
        }
        return optionAssets.some(function (asset) {
            return assets.indexOf(asset) !== -1;
        });
    }
    function selectedValues(root) {
        var values = {};
        root.querySelectorAll("[data-admin-select-badge-value]").forEach(function (input) {
            if (input.value) {
                values[input.value] = true;
            }
        });
        return values;
    }
    function optionByValue(select, value) {
        if (!select) {
            return null;
        }
        for (var index = 0; index < select.options.length; index += 1) {
            if (select.options[index].value === value) {
                return select.options[index];
            }
        }
        return null;
    }
    function syncBadgeSummaries(root) {
        var select = root.querySelector("[data-admin-select-badge-list-select]");
        if (!select) {
            return;
        }
        root.querySelectorAll("[data-admin-select-badge-item]").forEach(function (item) {
            var input = item.querySelector("[data-admin-select-badge-value]");
            var option = input ? optionByValue(select, input.value) : null;
            var summary = optionSummary(option, root);
            var meta = item.querySelector(".badge-list-summary");
            if (summary && !meta) {
                meta = document.createElement("span");
                meta.className = "badge-list-summary";
                item.insertBefore(meta, input || item.lastChild);
            }
            if (meta) {
                meta.textContent = summary;
                if (!summary) {
                    meta.remove();
                }
            }
        });
    }
    function syncOptions(root) {
        var select = root.querySelector("[data-admin-select-badge-list-select]");
        if (!select) {
            return;
        }
        syncBadgeSummaries(root);
        var values = selectedValues(root);
        root.querySelectorAll("[data-admin-select-badge-item]").forEach(function (item) {
            var input = item.querySelector("[data-admin-select-badge-value]");
            var option = input ? optionByValue(select, input.value) : null;
            if (option && !optionMatchesAssets(option, root)) {
                item.remove();
            }
        });
        values = selectedValues(root);
        Array.prototype.forEach.call(select.options, function (option) {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            var blocked = !!values[option.value] || !optionMatchesAssets(option, root);
            option.hidden = blocked;
            option.disabled = blocked;
        });
        select.value = "";
    }
    function addItem(root, value, label, summary) {
        var items = root.querySelector("[data-admin-select-badge-list-items]");
        if (!items || !value || selectedValues(root)[value]) {
            return;
        }
        var name = root.getAttribute("data-admin-select-badge-name") || "";
        var badge = document.createElement("span");
        badge.className = "badge-list-item";
        badge.setAttribute("data-admin-select-badge-item", "true");
        var title = document.createElement("span");
        title.className = "badge-list-label";
        title.textContent = label || value;
        badge.appendChild(title);
        if (summary) {
            var meta = document.createElement("span");
            meta.className = "badge-list-summary";
            meta.textContent = summary;
            badge.appendChild(meta);
        }
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = name + "[]";
        input.value = value;
        input.setAttribute("data-admin-select-badge-value", "true");
        badge.appendChild(input);
        var button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-icon-xs btn-ghost-danger admin-select-badge-list-remove";
        button.setAttribute("data-admin-select-badge-remove", "true");
        button.setAttribute("aria-label", "선택 항목 제거");
        button.textContent = "×";
        badge.appendChild(button);
        items.appendChild(badge);
        syncOptions(root);
    }
    document.addEventListener("change", function (event) {
        var select = event.target && event.target.closest ? event.target.closest("[data-admin-select-badge-list-select]") : null;
        if (!select || !select.value) {
            return;
        }
        var root = select.closest("[data-admin-select-badge-list]");
        if (!root) {
            return;
        }
        var option = select.selectedOptions && select.selectedOptions.length > 0 ? select.selectedOptions[0] : null;
        addItem(root, select.value, optionLabel(option), optionSummary(option, root));
    });
    document.addEventListener("click", function (event) {
        var button = event.target && event.target.closest ? event.target.closest("[data-admin-select-badge-remove]") : null;
        if (!button) {
            return;
        }
        var root = button.closest("[data-admin-select-badge-list]");
        var item = button.closest("[data-admin-select-badge-item]");
        if (item) {
            item.remove();
        }
        if (root) {
            syncOptions(root);
        }
    });
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll("[data-admin-select-badge-list]").forEach(syncOptions);
    });
    document.addEventListener("change", function (event) {
        document.querySelectorAll("[data-admin-select-badge-list][data-admin-select-badge-summary-source], [data-admin-select-badge-list][data-admin-select-badge-asset-source]").forEach(function (root) {
            var sourceSelector = root.getAttribute("data-admin-select-badge-summary-source") || "";
            var assetSelector = root.getAttribute("data-admin-select-badge-asset-source") || "";
            var source = sourceSelector ? document.querySelector(sourceSelector) : null;
            var assetSource = assetSelector ? document.querySelector(assetSelector) : null;
            var sourceChanged = !!(sourceSelector && event.target && event.target.matches && event.target.matches(sourceSelector));
            var assetChanged = !!(assetSource && event.target && assetSource.contains(event.target));
            if (sourceChanged || assetChanged) {
                syncOptions(root);
            }
        });
    });
    document.querySelectorAll("[data-admin-select-badge-list]").forEach(syncOptions);
})();
</script>';
    }

    return str_replace('class="admin-select-badge-list"', 'class="admin-select-badge-list" data-admin-select-badge-name="' . sr_e($name) . '"', $html);
}

function sr_admin_select_badge_list_item_html(string $name, string $value, string $label, string $summary = ''): string
{
    $html = '<span class="badge-list-item" data-admin-select-badge-item>'
        . '<span class="badge-list-label">' . sr_e($label) . '</span>';
    if ($summary !== '') {
        $html .= '<span class="badge-list-summary">' . sr_e($summary) . '</span>';
    }
    $html .= '<input type="hidden" name="' . sr_e($name) . '[]" value="' . sr_e($value) . '" data-admin-select-badge-value>'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-danger admin-select-badge-list-remove" data-admin-select-badge-remove aria-label="선택 항목 제거">×</button>'
        . '</span>';

    return $html;
}

function sr_admin_member_group_key_select_html(string $id, string $name, array $selectedKeys, array $memberGroups): string
{
    $options = [];
    foreach ($memberGroups as $memberGroup) {
        $groupKey = (string) ($memberGroup['group_key'] ?? '');
        if ($groupKey === '') {
            continue;
        }

        $title = trim((string) ($memberGroup['title'] ?? ''));
        $label = $title !== '' ? $title : $groupKey;
        $options[$groupKey] = $label;
    }

    return sr_admin_checkbox_list_html($id, $name, $options, $selectedKeys, '활성 회원 그룹 없음');
}

function sr_admin_member_group_key_badge_select_html(string $id, string $name, array $selectedKeys, array $memberGroups): string
{
    $options = [];
    foreach ($memberGroups as $memberGroup) {
        $groupKey = (string) ($memberGroup['group_key'] ?? '');
        if ($groupKey === '') {
            continue;
        }

        $title = trim((string) ($memberGroup['title'] ?? ''));
        $label = $title !== '' ? $title . ' (' . $groupKey . ')' : $groupKey;
        $options[$groupKey] = $label;
    }

    return sr_admin_select_badge_list_html($id, $name, $options, $selectedKeys, '활성 회원 그룹 없음', '그룹 선택');
}
