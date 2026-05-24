<?php

declare(strict_types=1);

function sr_admin_form_label_help_html(string $forId, string $label, string $modalId, string $helpLabel = '설명 보기', bool $required = false): string
{
    $forId = trim($forId);
    $modalId = trim($modalId);
    $helpLabel = trim($helpLabel) !== '' ? trim($helpLabel) : '설명 보기';
    $requiredHtml = $required ? ' <span class="sr-required-label">(필수)</span>' : '';

    return '<div class="form-label admin-form-label-help">'
        . '<label for="' . sr_e($forId) . '">' . sr_e($label) . $requiredHtml . '</label>'
        . '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $helpLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>'
        . '</div>';
}

function sr_admin_help_modal_html(string $modalId, string $title, string $bodyHtml): string
{
    $modalId = trim($modalId);

    return '<div id="' . sr_e($modalId) . '" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="' . sr_e($modalId) . '_title" aria-hidden="true" inert>'
        . '<div class="modal-dialog">'
        . '<div class="modal-content">'
        . '<div class="modal-header">'
        . '<h3 id="' . sr_e($modalId) . '_title" class="modal-title">' . sr_e($title) . '</h3>'
        . '<button type="button" class="modal-close" aria-label="닫기" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('close')
        . '</button>'
        . '</div>'
        . '<div class="modal-body">' . $bodyHtml . '</div>'
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

function sr_admin_member_group_key_select_html(string $id, string $name, array $selectedKeys, array $memberGroups): string
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

    return sr_admin_checkbox_list_html($id, $name, $options, $selectedKeys, '활성 회원 그룹 없음');
}
