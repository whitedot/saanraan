#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}
require_once $root . '/core/version.php';
require_once $root . '/core/helpers/settings.php';

$errors = [];
$fail = static function (string $message) use (&$errors): void {
    $errors[] = $message;
};

$metadataFixture = [
    'name' => 'Contract Fixture',
    'version' => '2026.07.001',
    'type' => 'module',
    'saanraan' => [
        'min_version' => SR_CORE_VERSION,
        'tested_with' => [SR_CORE_VERSION],
        'module_contract' => SR_MODULE_CONTRACT_VERSION,
    ],
    'requires' => [
        'modules' => ['member'],
        'contracts' => [
            ['module' => 'member', 'file' => 'public-identity.php'],
        ],
    ],
    'contracts' => [
        'consumes' => [],
    ],
];
$metadataFixtureErrors = sr_module_metadata_errors($metadataFixture);
if (!in_array('requires.contracts의 계약 파일은 contracts.consumes에도 선언해야 합니다.', $metadataFixtureErrors, true)) {
    $fail('Module metadata validation must reject a required contract omitted from contracts.consumes.');
}
$metadataFixture['contracts']['consumes'][] = 'public-identity.php';
$metadataFixture['requires']['modules'] = [];
$metadataFixtureErrors = sr_module_metadata_errors($metadataFixture);
if (!in_array('requires.contracts의 제공 모듈은 requires.modules에도 선언해야 합니다.', $metadataFixtureErrors, true)) {
    $fail('Module metadata validation must reject a contract provider omitted from requires.modules.');
}

$moduleMetadata = [];
foreach (glob($root . '/modules/*/module.php') ?: [] as $moduleFile) {
    $moduleKey = basename(dirname($moduleFile));
    $metadata = include $moduleFile;
    if (is_array($metadata)) {
        $moduleMetadata[$moduleKey] = $metadata;
    }
}

foreach ($moduleMetadata as $moduleKey => $metadata) {
    $requires = is_array($metadata['requires'] ?? null) ? $metadata['requires'] : [];
    $requiredModules = is_array($requires['modules'] ?? null) ? $requires['modules'] : [];
    $requiredModuleKeys = [];
    foreach ($requiredModules as $key => $value) {
        $requiredModuleKey = is_string($key) ? $key : (is_string($value) ? $value : '');
        if ($requiredModuleKey !== '') {
            $requiredModuleKeys[] = $requiredModuleKey;
        }
    }
    $contracts = is_array($metadata['contracts'] ?? null) ? $metadata['contracts'] : [];
    $consumes = array_values(array_filter((array) ($contracts['consumes'] ?? []), 'is_string'));

    foreach ((array) ($requires['contracts'] ?? []) as $requiredContract) {
        if (!is_array($requiredContract)) {
            $fail($moduleKey . ' requires.contracts must contain arrays.');
            continue;
        }

        $provider = (string) ($requiredContract['module'] ?? '');
        $file = (string) ($requiredContract['file'] ?? '');
        if ($provider === '' || $file === '') {
            $fail($moduleKey . ' requires.contracts must name a provider module and contract file.');
            continue;
        }
        if (!in_array($provider, $requiredModuleKeys, true)) {
            $fail($moduleKey . ' must list required contract provider ' . $provider . ' in requires.modules.');
        }
        if (!in_array($file, $consumes, true)) {
            $fail($moduleKey . ' must list required contract ' . $file . ' in contracts.consumes.');
        }
        $providerMetadata = $moduleMetadata[$provider] ?? null;
        $providerContracts = is_array($providerMetadata['contracts'] ?? null) ? $providerMetadata['contracts'] : [];
        if (!in_array($file, (array) ($providerContracts['provides'] ?? []), true)) {
            $fail($provider . ' must provide required contract ' . $file . ' for ' . $moduleKey . '.');
        }
        if (!is_file($root . '/modules/' . $provider . '/' . $file)) {
            $fail($provider . ' contract file is missing: ' . $file);
        }
    }
}

$publicIdentityConsumers = ['admin', 'content', 'community', 'quiz', 'survey'];
$memberMetadata = $moduleMetadata['member'] ?? [];
if (!in_array('public-identity.php', (array) ($memberMetadata['contracts']['provides'] ?? []), true)) {
    $fail('member must declare public-identity.php in contracts.provides.');
}

foreach ($publicIdentityConsumers as $consumerModuleKey) {
    $metadata = $moduleMetadata[$consumerModuleKey] ?? [];
    if (!in_array('public-identity.php', (array) ($metadata['contracts']['consumes'] ?? []), true)) {
        $fail($consumerModuleKey . ' must declare public-identity.php in contracts.consumes.');
    }

    $required = false;
    foreach ((array) ($metadata['requires']['contracts'] ?? []) as $requiredContract) {
        if (is_array($requiredContract)
            && (string) ($requiredContract['module'] ?? '') === 'member'
            && (string) ($requiredContract['file'] ?? '') === 'public-identity.php'
        ) {
            $required = true;
            break;
        }
    }
    if (!$required) {
        $fail($consumerModuleKey . ' must require member/public-identity.php explicitly.');
    }
}

$requestFiles = [
    'modules/admin/themes/basic/layout-header.php',
    'modules/content/actions/home.php',
    'modules/content/actions/group.php',
    'modules/content/actions/view.php',
    'modules/community/actions/list.php',
    'modules/community/actions/view.php',
    'modules/quiz/actions/view.php',
    'modules/survey/actions/view.php',
];
foreach ($requestFiles as $requestFile) {
    $source = file_get_contents($root . '/' . $requestFile);
    if (!is_string($source) || !str_contains($source, "require_once SR_ROOT . '/modules/member/public-identity.php'")) {
        $fail($requestFile . ' must explicitly require the member public identity contract in the request flow.');
    }
}

$consumerViewFiles = [
    'modules/admin/themes/basic/layout-header.php',
    'modules/content/theme/basic/home.php',
    'modules/content/views/home.php',
    'modules/content/theme/basic/group.php',
    'modules/content/views/group.php',
    'modules/content/theme/basic/content.php',
    'modules/content/views/content.php',
    'modules/community/theme/basic/list.php',
    'modules/community/skins/basic/list.php',
    'modules/community/theme/basic/post.php',
    'modules/community/skins/basic/view.php',
    'modules/quiz/theme/basic/view.php',
    'modules/quiz/skins/basic/view.php',
    'modules/survey/theme/basic/view.php',
    'modules/survey/skins/basic/view.php',
];
foreach ($consumerViewFiles as $consumerViewFile) {
    $source = file_get_contents($root . '/' . $consumerViewFile);
    if (!is_string($source)
        || !str_contains($source, 'sr_member_public_identity_parts(')
        || !str_contains($source, 'PublicIdentityAssets')
    ) {
        $fail($consumerViewFile . ' must render through the public identity contract and merge its assets explicitly.');
    }
}

$forbiddenConsumerMarkers = [
    'sr_member_public_profile_images_enabled(',
    'sr_member_public_profile_image_sources(',
    'sr_member_public_profile_image_html(',
    'sr_member_follow_statuses(',
    'sr_member_public_name_menu_html(',
    '/modules/member/assets/profile-menu.js',
    '/modules/member/assets/public-identity.css',
];
foreach ($publicIdentityConsumers as $consumerModuleKey) {
    $moduleDirectory = $root . '/modules/' . $consumerModuleKey;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDirectory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) {
            continue;
        }
        $relative = substr($file->getPathname(), strlen($root) + 1);
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            continue;
        }
        foreach ($forbiddenConsumerMarkers as $forbiddenMarker) {
            if (str_contains($source, $forbiddenMarker)) {
                $fail($relative . ' bypasses member/public-identity.php with: ' . $forbiddenMarker);
            }
        }
        if (strtolower($file->getExtension()) === 'css'
            && preg_match('/^\s*\.member-profile-(?:menu|image)(?:[\s.:>{,#]|$)/m', $source) === 1
        ) {
            $fail($relative . ' must not own the member public identity component selectors.');
        }
    }
}

$contractSource = file_get_contents($root . '/modules/member/public-identity.php');
$helperSource = file_get_contents($root . '/modules/member/helpers/public-identity.php');
foreach (['context_function', 'parts_function', 'assets_function'] as $contractKey) {
    if (!is_string($contractSource) || !str_contains($contractSource, "'" . $contractKey . "'")) {
        $fail('member/public-identity.php must expose ' . $contractKey . '.');
    }
}
foreach ([
    'function sr_member_public_identity_context(',
    'function sr_member_public_identity_parts(',
    'function sr_member_public_identity_assets(',
    "'/modules/member/assets/public-identity.css'",
    "'/modules/member/assets/profile-menu.js'",
] as $helperMarker) {
    if (!is_string($helperSource) || !str_contains($helperSource, $helperMarker)) {
        $fail('member public identity helper is missing: ' . $helperMarker);
    }
}

$publicFeatureContracts = [
    'banner' => [
        'file' => 'public-banner.php',
        'consumers' => ['content', 'community'],
        'functions' => [
            'sr_banner_public_assets',
            'sr_banner_public_banners',
            'sr_banner_render_public_banner',
        ],
        'asset_markers' => ['/modules/banner/assets/module.css'],
    ],
    'popup_layer' => [
        'file' => 'public-popup-layer.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_popup_layer_public_assets',
            'sr_popup_layer_public_layers',
            'sr_popup_layer_render_public_layer',
        ],
        'asset_markers' => ['/modules/popup_layer/assets/module.css'],
    ],
    'reaction' => [
        'file' => 'public-reaction.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_reaction_delete_target_records',
            'sr_reaction_disabled_preset_key',
            'sr_reaction_preset_options',
            'sr_reaction_preset_options_with_disabled',
            'sr_reaction_public_assets',
            'sr_reaction_record_summaries',
            'sr_reaction_render_widget',
            'sr_reaction_resolve_targets',
            'sr_reaction_setting_preset_key',
            'sr_reaction_setting_preset_key_or_disabled',
            'sr_reaction_tables_available',
        ],
        'asset_markers' => [
            '/modules/reaction/assets/module.css',
            '/modules/reaction/assets/public.js',
        ],
    ],
    'logo_manager' => [
        'file' => 'public-branding.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_logo_manager_render_logo',
            'sr_logo_manager_render_public_symbol_logo',
            'sr_logo_manager_favicon_link_tag',
        ],
        'asset_markers' => [],
    ],
    'privacy' => [
        'file' => 'public-cookie-consent.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_privacy_cookie_consent_public_html',
            'sr_privacy_cookie_consent_public_assets',
        ],
        'asset_markers' => ['/modules/privacy/assets/cookie-consent.css'],
    ],
    'message' => [
        'file' => 'public-message-summary.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_message_enabled',
            'sr_message_unread_count',
        ],
        'asset_markers' => [],
    ],
    'notification' => [
        'file' => 'public-notification-summary.php',
        'consumers' => ['content', 'community', 'quiz', 'survey'],
        'functions' => [
            'sr_notification_public_header_summary',
            'sr_notification_item_link_attributes',
            'sr_notification_clean_single_line',
            'sr_notification_time_html',
        ],
        'asset_markers' => [],
    ],
];
foreach ($publicFeatureContracts as $providerModuleKey => $definition) {
    $contractFile = (string) $definition['file'];
    $providerMetadata = $moduleMetadata[$providerModuleKey] ?? [];
    if (!in_array($contractFile, (array) ($providerMetadata['contracts']['provides'] ?? []), true)) {
        $fail($providerModuleKey . ' must declare ' . $contractFile . ' in contracts.provides.');
    }

    $contractPath = $root . '/modules/' . $providerModuleKey . '/' . $contractFile;
    $contractSource = is_file($contractPath) ? file_get_contents($contractPath) : false;
    $helperSource = file_get_contents($root . '/modules/' . $providerModuleKey . '/helpers.php');
    foreach ((array) $definition['functions'] as $function) {
        if (!is_string($contractSource) || !str_contains($contractSource, "'" . $function . "'")) {
            $fail($providerModuleKey . '/' . $contractFile . ' must export ' . $function . '.');
        }
        if (!is_string($helperSource) || !str_contains($helperSource, 'function ' . $function . '(')) {
            $fail($providerModuleKey . ' helper must define exported function ' . $function . '.');
        }
    }
    foreach ((array) $definition['asset_markers'] as $assetMarker) {
        if (!is_string($helperSource) || !str_contains($helperSource, "'" . $assetMarker . "'")) {
            $fail($providerModuleKey . ' public feature assets must own ' . $assetMarker . '.');
        }
    }

    foreach ((array) $definition['consumers'] as $consumerModuleKey) {
        $consumerMetadata = $moduleMetadata[$consumerModuleKey] ?? [];
        if (!in_array($contractFile, (array) ($consumerMetadata['contracts']['consumes'] ?? []), true)) {
            $fail($consumerModuleKey . ' must declare ' . $contractFile . ' in contracts.consumes.');
        }
    }
}

$publicFeatureRequestContracts = [
    'modules/content/actions/view.php' => ['public-banner.php', 'public-popup-layer.php', 'public-reaction.php'],
    'modules/community/actions/list.php' => ['public-banner.php', 'public-popup-layer.php', 'public-reaction.php'],
    'modules/community/actions/view.php' => ['public-banner.php', 'public-popup-layer.php', 'public-reaction.php'],
    'modules/community/actions/write.php' => ['public-banner.php', 'public-popup-layer.php'],
    'modules/community/actions/edit.php' => ['public-banner.php', 'public-popup-layer.php'],
    'modules/quiz/actions/view.php' => ['public-reaction.php'],
    'modules/survey/actions/view.php' => ['public-reaction.php'],
];
foreach ($publicFeatureRequestContracts as $requestFile => $contractFiles) {
    $source = file_get_contents($root . '/' . $requestFile);
    foreach ($contractFiles as $contractFile) {
        if (!is_string($source) || !str_contains($source, '/' . $contractFile)) {
            $fail($requestFile . ' must explicitly load ' . $contractFile . ' before rendering.');
        }
    }
}

$publicLayoutContracts = [
    'public-branding.php',
    'public-cookie-consent.php',
    'public-message-summary.php',
    'public-notification-summary.php',
];
foreach (['content', 'community', 'quiz', 'survey'] as $consumerModuleKey) {
    $layoutFile = 'modules/' . $consumerModuleKey . '/theme/basic/layout.php';
    $source = file_get_contents($root . '/' . $layoutFile);
    foreach ($publicLayoutContracts as $contractFile) {
        if (!is_string($source) || !str_contains($source, '/' . $contractFile)) {
            $fail($layoutFile . ' must explicitly load ' . $contractFile . '.');
        }
    }
}

$forbiddenPublicFeaturePaths = [
    '/modules/banner/helpers.php',
    '/modules/banner/assets/',
    '/modules/popup_layer/helpers.php',
    '/modules/popup_layer/assets/',
    '/modules/reaction/helpers.php',
    '/modules/reaction/assets/',
    '/modules/logo_manager/helpers.php',
    '/modules/privacy/helpers.php',
    '/modules/privacy/assets/',
    '/modules/message/helpers.php',
    '/modules/notification/helpers.php',
];
foreach (['content', 'community', 'quiz', 'survey'] as $consumerModuleKey) {
    $moduleDirectory = $root . '/modules/' . $consumerModuleKey;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($moduleDirectory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'js', 'css'], true)) {
            continue;
        }
        $source = file_get_contents($file->getPathname());
        if (!is_string($source)) {
            continue;
        }
        foreach ($forbiddenPublicFeaturePaths as $forbiddenPath) {
            if (str_contains($source, $forbiddenPath)) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $fail($relative . ' bypasses an owner public feature contract with: ' . $forbiddenPath);
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Module feature contract checks failed:\n- " . implode("\n- ", array_values(array_unique($errors))) . "\n");
    exit(1);
}

fwrite(STDOUT, "Module feature contract checks passed.\n");
