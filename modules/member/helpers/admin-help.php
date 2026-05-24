<?php

declare(strict_types=1);

function sr_member_admin_help_button_html(string $label, string $modalId, string $helpLabel = '설명 보기'): string
{
    $modalId = trim($modalId);
    $helpLabel = trim($helpLabel) !== '' ? trim($helpLabel) : '설명 보기';

    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $helpLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
}

function sr_member_admin_help_body_html(array $translationKeys): string
{
    $html = '';
    foreach ($translationKeys as $translationKey) {
        $html .= '<p>' . sr_e(sr_t((string) $translationKey)) . '</p>';
    }

    return $html;
}
