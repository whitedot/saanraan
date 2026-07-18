<?php

declare(strict_types=1);

function sr_ckeditor_default_settings(): array
{
    return [
        'asset_mode' => 'self_hosted',
        'cdn_version' => '48.3.0',
        'license_key' => 'GPL',
        'toolbar_preset' => 'standard',
    ];
}

function sr_ckeditor_settings(PDO $pdo): array
{
    $settings = array_merge(sr_ckeditor_default_settings(), sr_module_settings($pdo, 'ckeditor'));
    $settings['asset_mode'] = in_array((string) $settings['asset_mode'], ['cdn', 'self_hosted'], true)
        ? (string) $settings['asset_mode']
        : 'self_hosted';
    $settings['cdn_version'] = sr_ckeditor_clean_version((string) $settings['cdn_version']);
    $settings['license_key'] = sr_ckeditor_clean_license_key((string) $settings['license_key']);
    $settings['toolbar_preset'] = isset(sr_ckeditor_toolbar_presets()[(string) $settings['toolbar_preset']])
        ? (string) $settings['toolbar_preset']
        : 'standard';

    return $settings;
}

function sr_ckeditor_clean_version(string $value): string
{
    $value = trim($value);
    return preg_match('/\A[0-9]+(?:\.[0-9]+){1,2}\z/', $value) === 1 ? $value : '48.3.0';
}

function sr_ckeditor_clean_license_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'GPL';
    }

    $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
    $value = is_string($value) ? $value : 'GPL';

    return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
}

function sr_ckeditor_toolbar_presets(): array
{
    $standardItems = [
        'undo',
        'redo',
        '|',
        'insertImage',
        'link',
        'insertTable',
        '|',
        'heading',
        'fontSize',
        '|',
        'bold',
        'italic',
        'underline',
        'strikethrough',
        '|',
        'fontColor',
        'fontBackgroundColor',
        'removeFormat',
        '|',
        'alignment',
        '|',
        'horizontalLine',
        'blockQuote',
        '|',
        'bulletedList',
        'numberedList',
        'outdent',
        'indent',
    ];

    return [
        'standard' => [
            'label' => '일반 편집 도구',
            'items' => $standardItems,
        ],
    ];
}

function sr_ckeditor_asset_mode_options(): array
{
    return [
        'cdn' => 'CDN',
        'self_hosted' => '직접 호스팅',
    ];
}

function sr_ckeditor_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'ckeditor' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('CKEditor 플러그인이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['asset_mode', (string) $settings['asset_mode'], 'string'],
        ['cdn_version', sr_ckeditor_clean_version((string) $settings['cdn_version']), 'string'],
        ['license_key', sr_ckeditor_clean_license_key((string) $settings['license_key']), 'string'],
        ['toolbar_preset', (string) $settings['toolbar_preset'], 'string'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = sr_now();
    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => $row[0],
            'setting_value' => $row[1],
            'value_type' => $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('ckeditor');
}

function sr_ckeditor_effective_toolbar_preset(PDO $pdo, string $presetKey = 'default'): string
{
    $settings = sr_ckeditor_settings($pdo);
    $presets = sr_ckeditor_toolbar_presets();
    $presetKey = trim($presetKey);
    if ($presetKey === '' || $presetKey === 'default' || $presetKey === 'inherit') {
        $presetKey = (string) $settings['toolbar_preset'];
    }

    return isset($presets[$presetKey]) ? $presetKey : 'standard';
}

function sr_ckeditor_public_config(PDO $pdo, string $presetKey = 'default'): array
{
    $settings = sr_ckeditor_settings($pdo);
    $presets = sr_ckeditor_toolbar_presets();
    $effectivePresetKey = sr_ckeditor_effective_toolbar_preset($pdo, $presetKey);
    $preset = $presets[$effectivePresetKey] ?? $presets['standard'];
    $assetMode = (string) $settings['asset_mode'];
    $cdnVersion = (string) $settings['cdn_version'];

    $config = [
        'assetMode' => $assetMode,
        'cdnVersion' => $cdnVersion,
        'licenseKey' => (string) $settings['license_key'],
        'toolbarPreset' => $effectivePresetKey,
        'toolbar' => $preset['items'],
        'cdnScriptUrl' => 'https://cdn.ckeditor.com/ckeditor5/' . rawurlencode($cdnVersion) . '/ckeditor5.umd.js',
        'cdnStylesheetUrl' => 'https://cdn.ckeditor.com/ckeditor5/' . rawurlencode($cdnVersion) . '/ckeditor5.css',
        'selfHostedScriptUrl' => sr_asset_url('/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js'),
        'selfHostedStylesheetUrl' => sr_asset_url('/modules/ckeditor/vendor/ckeditor5/ckeditor5.css'),
        'pluginStylesheetUrl' => sr_asset_url('/modules/ckeditor/assets/saanraan-ckeditor.css'),
    ];

    return $config;
}

function sr_ckeditor_public_assets_html(PDO $pdo, string $presetKey = 'default'): string
{
    $configJson = sr_js_json_encode(sr_ckeditor_public_config($pdo, $presetKey));

    return '<script type="application/json" id="sr-ckeditor-config">' . $configJson . '</script>' . PHP_EOL
        . '<script src="' . sr_e(sr_asset_url('/modules/ckeditor/assets/saanraan-ckeditor.js')) . '" defer></script>';
}
