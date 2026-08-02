<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_editor_normalize_key(string $editorKey, bool $allowInherit = false): string
{
    $editorKey = strtolower(trim($editorKey));
    if ($allowInherit && $editorKey === 'inherit') {
        return 'inherit';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,39}\z/', $editorKey) === 1 ? $editorKey : 'textarea';
}

function sr_editor_contract_module_keys(?PDO $pdo): array
{
    if ($pdo instanceof PDO) {
        return array_keys(sr_enabled_module_contract_files($pdo, 'editor-options.php'));
    }

    $moduleKeys = [];
    foreach (glob(SR_ROOT . '/modules/*/editor-options.php') ?: [] as $file) {
        $moduleKey = basename(dirname($file));
        if (sr_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    sort($moduleKeys);
    return $moduleKeys;
}

function sr_editor_contracts(?PDO $pdo = null): array
{
    $contracts = [];
    foreach (sr_editor_contract_module_keys($pdo) as $moduleKey) {
        $file = SR_ROOT . '/modules/' . $moduleKey . '/editor-options.php';
        $contract = is_file($file) ? require $file : null;
        if (!is_array($contract)) {
            continue;
        }

        $editorKey = sr_editor_normalize_key((string) ($contract['key'] ?? ''));
        if ($editorKey === 'textarea' || isset($contracts[$editorKey])) {
            continue;
        }

        $helpers = (string) ($contract['helpers'] ?? '');
        if ($helpers !== '' && preg_match('/\Ahelpers(?:\/[a-z0-9_\-]+)?\.php\z/', $helpers) === 1) {
            $helperPath = SR_ROOT . '/modules/' . $moduleKey . '/' . $helpers;
            if (is_file($helperPath)) {
                require_once $helperPath;
            }
        }

        $contracts[$editorKey] = [
            'module_key' => $moduleKey,
            'label' => (string) ($contract['label'] ?? $editorKey),
            'assets_function' => (string) ($contract['assets_function'] ?? ''),
            'format_value' => sr_body_format((string) ($contract['format_value'] ?? 'plain')),
        ];
    }

    return $contracts;
}

function sr_editor_available(PDO $pdo, string $editorKey): bool
{
    $editorKey = sr_editor_normalize_key($editorKey);
    if ($editorKey === 'textarea' || $editorKey === 'html') {
        return true;
    }

    return isset(sr_editor_contracts($pdo)[$editorKey]);
}

function sr_editor_effective_key(PDO $pdo, string $editorKey): string
{
    $editorKey = sr_editor_normalize_key($editorKey);
    return sr_editor_available($pdo, $editorKey) ? $editorKey : 'textarea';
}

function sr_editor_format_value(PDO $pdo, string $editorKey): string
{
    $editorKey = sr_editor_effective_key($pdo, $editorKey);
    if ($editorKey === 'html') {
        return 'html';
    }
    if ($editorKey === 'textarea') {
        return 'plain';
    }

    $contract = sr_editor_contracts($pdo)[$editorKey] ?? [];
    return sr_body_format((string) ($contract['format_value'] ?? 'plain'));
}

function sr_editor_options(PDO $pdo, bool $allowInherit = false): array
{
    $options = $allowInherit ? ['inherit' => '상위 설정 사용'] : [];
    $options['textarea'] = '기본 textarea';
    $options['html'] = 'HTML';
    foreach (sr_editor_contracts($pdo) as $editorKey => $contract) {
        $options[(string) $editorKey] = (string) ($contract['label'] ?? $editorKey);
    }

    return $options;
}

function sr_editor_textarea_attributes(PDO $pdo, string $editorKey, string $presetKey = 'default', string $formatFieldName = 'body_format'): string
{
    $editorKey = sr_editor_effective_key($pdo, $editorKey);
    if ($editorKey === 'textarea') {
        return '';
    }

    return ' data-sr-editor="' . sr_e($editorKey) . '" data-sr-editor-preset="' . sr_e($presetKey) . '" data-sr-editor-format-name="' . sr_e($formatFieldName) . '" data-sr-editor-format-value="' . sr_e(sr_editor_format_value($pdo, $editorKey)) . '"';
}

function sr_editor_assets_html(PDO $pdo, string $editorKey, string $presetKey = 'default'): string
{
    $editorKey = sr_editor_effective_key($pdo, $editorKey);
    if ($editorKey === 'textarea') {
        return '';
    }

    $contract = sr_editor_contracts($pdo)[$editorKey] ?? [];
    $assetsFunction = (string) ($contract['assets_function'] ?? '');
    return function_exists($assetsFunction) ? (string) $assetsFunction($pdo, $presetKey) : '';
}

function sr_material_icon_name(string $name): string
{
    $name = trim($name);

    return preg_match('/\A[a-z0-9_]+\z/', $name) === 1 ? $name : 'help';
}

function sr_material_icon_class_attr(string $class): string
{
    return sr_ui_icon_class_attr($class);
}

function sr_ui_icon_class_attr(string $class): string
{
    $tokens = [];
    foreach (preg_split('/\s+/', trim($class)) ?: [] as $token) {
        if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $token) === 1) {
            $tokens[] = $token;
        }
    }

    return implode(' ', $tokens);
}

function sr_ui_arrow_icon_paths(): array
{
    return [
        'down' => 'M5 7.5l5 5l5 -5',
        'up' => 'M5 12.5l5 -5l5 5',
        'left' => 'M12.5 5l-5 5l5 5',
        'right' => 'M7.5 5l5 5l-5 5',
    ];
}

function sr_ui_arrow_icon_html(string $direction = 'down', string $class = '', string $label = ''): string
{
    $paths = sr_ui_arrow_icon_paths();
    $direction = isset($paths[$direction]) ? $direction : 'down';
    $classes = trim('ui-arrow-icon ' . sr_ui_icon_class_attr($class));
    $label = trim($label);
    $accessibility = $label === ''
        ? ' aria-hidden="true"'
        : ' role="img" aria-label="' . sr_e($label) . '"';

    return '<svg class="' . sr_e($classes) . '" data-ui-arrow="' . sr_e($direction) . '" viewBox="0 0 20 20"' . $accessibility . ' focusable="false"><path d="' . sr_e($paths[$direction]) . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
}

function sr_material_icon_html(string $name, string $class = '', string $label = '', string $id = ''): string
{
    return sr_icon($name, $class, $label, $id);
}

function sr_icon(string $name, string $class = '', string $label = '', string $id = ''): string
{
    $classes = trim('sr-icon material-symbols-outlined ' . sr_material_icon_class_attr($class));
    $iconName = sr_material_icon_name($name);
    $label = trim($label);
    $accessibility = $label === ''
        ? ' aria-hidden="true"'
        : ' role="img" aria-label="' . sr_e($label) . '"';
    $idAttribute = preg_match('/\A[a-zA-Z][a-zA-Z0-9_-]*\z/', $id) === 1
        ? ' id="' . sr_e($id) . '"'
        : '';

    return '<span class="' . sr_e($classes) . '"' . $idAttribute . ' data-sr-material-icon' . $accessibility . '>' . sr_e($iconName) . '</span>';
}

function sr_icon_bootstrap_script(): string
{
    return '<script' . sr_csp_nonce_attribute() . '>(function(){var r=document.documentElement;function y(){r.classList.add("sr-material-icons-ready")}function n(){r.classList.add("sr-material-icons-unavailable");y()}if(document.fonts&&document.fonts.load){document.fonts.load("24px \\"Material Symbols Outlined\\"").then(y,function(){if(document.fonts.ready){document.fonts.ready.then(y,n)}else{n()}})}else{y()}})();</script>';
}
