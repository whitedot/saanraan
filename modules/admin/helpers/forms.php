<?php

declare(strict_types=1);

function sr_admin_form_label_help_html(string $forId, string $label, string $modalId, string $helpLabel = '설명 보기'): string
{
    $forId = trim($forId);
    $modalId = trim($modalId);
    $helpLabel = trim($helpLabel) !== '' ? trim($helpLabel) : '설명 보기';

    return '<div class="form-label admin-form-label-help">'
        . '<label for="' . sr_e($forId) . '">' . sr_e($label) . '</label>'
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

function sr_admin_member_group_key_select_html(string $id, string $name, array $selectedKeys, array $memberGroups): string
{
    $selectedKeyMap = [];
    foreach ($selectedKeys as $selectedKey) {
        $selectedKeyMap[(string) $selectedKey] = true;
    }

    $size = max(3, min(8, count($memberGroups)));
    $html = '<select id="' . sr_e($id) . '" name="' . sr_e($name) . '[]" class="form-select form-control-full admin-member-group-key-select" multiple size="' . sr_e((string) $size) . '">';

    if ($memberGroups === []) {
        $html .= '<option value="" disabled>활성 회원 그룹 없음</option>';
    }

    foreach ($memberGroups as $memberGroup) {
        $groupKey = (string) ($memberGroup['group_key'] ?? '');
        if ($groupKey === '') {
            continue;
        }

        $title = trim((string) ($memberGroup['title'] ?? ''));
        $label = $title !== '' ? $title . ' (' . $groupKey . ')' : $groupKey;
        $html .= '<option value="' . sr_e($groupKey) . '"' . (isset($selectedKeyMap[$groupKey]) ? ' selected' : '') . '>' . sr_e($label) . '</option>';
    }

    return $html . '</select>';
}
