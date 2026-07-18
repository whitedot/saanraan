<?php

declare(strict_types=1);

function sr_community_ckeditor_public_body_html(string $html, string $themeKey = 'basic'): string
{
    $themeKey = trim($themeKey) !== '' ? trim($themeKey) : 'basic';

    return '<div class="sr-ckeditor" data-sr-editor-output data-sr-editor-body-theme="community.' . sr_e($themeKey) . '">'
        . '<div class="ck-content" lang="' . sr_e(sr_locale()) . '" dir="ltr">'
        . $html
        . '</div>'
        . '</div>';
}

function sr_community_post_body_setting_max_length(): int
{
    return 16000000;
}

function sr_community_post_body_length_setting(mixed $value): int
{
    return min(sr_community_post_body_setting_max_length(), max(0, (int) $value));
}

function sr_community_post_body_storage_max_bytes(): int
{
    return 16000000;
}
