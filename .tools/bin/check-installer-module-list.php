#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

$read = static function (string $path) use ($root, &$errors): string {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        $errors[] = 'cannot read ' . $path;
        return '';
    }

    return str_replace(["\r\n", "\r"], "\n", $content);
};

$extractArrayKeys = static function (string $content, string $variableName, string $endMarker) use (&$errors): array {
    $startNeedle = '$' . $variableName . ' = [';
    $start = strpos($content, $startNeedle);
    if ($start === false) {
        $errors[] = 'installer list is missing $' . $variableName;
        return [];
    }

    $end = strpos($content, $endMarker, $start);
    if ($end === false) {
        $errors[] = 'installer list end marker is missing for $' . $variableName;
        return [];
    }

    $block = substr($content, $start, $end - $start);
    preg_match_all('/^    \'([a-z0-9_]+)\'\s*=>\s*\[/m', $block, $matches);

    return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
};

$install = $read('core/actions/install.php');
$requiredKeys = $extractArrayKeys($install, 'requiredModules', '$foundationModuleDefaults = [');
$foundationDefaultKeys = $extractArrayKeys($install, 'foundationModuleDefaults', '$foundationModules = [];');
$optionalKeys = $extractArrayKeys($install, 'optionalModules', "\n];\n\nfunction sr_install_module_definition");
$installerKeys = array_values(array_unique(array_merge($requiredKeys, $foundationDefaultKeys, $optionalKeys)));

$moduleKeys = [];
foreach (glob($root . '/modules/*/module.php') ?: [] as $moduleFile) {
    $moduleKey = basename(dirname($moduleFile));
    if (!preg_match('/\A[a-z0-9_]+\z/', $moduleKey)) {
        continue;
    }

    if (!is_file(dirname($moduleFile) . '/install.sql')) {
        continue;
    }

    $moduleKeys[] = $moduleKey;
}
$moduleKeys = array_values(array_unique($moduleKeys));
sort($moduleKeys);

$installerModuleKeys = $installerKeys;
sort($installerModuleKeys);

$missing = array_values(array_diff($moduleKeys, $installerModuleKeys));
foreach ($missing as $moduleKey) {
    $errors[] = 'installable module is missing from initial installer lists: ' . $moduleKey;
}

$extra = array_values(array_diff($installerModuleKeys, $moduleKeys));
foreach ($extra as $moduleKey) {
    $errors[] = 'initial installer references a missing installable module: ' . $moduleKey;
}

$optionalPosition = array_flip($optionalKeys);
foreach ($optionalKeys as $moduleKey) {
    $metadata = include $root . '/modules/' . $moduleKey . '/module.php';
    $requiredModules = is_array($metadata['requires']['modules'] ?? null) ? $metadata['requires']['modules'] : [];
    foreach ($requiredModules as $requiredModuleKey) {
        $requiredModuleKey = is_string($requiredModuleKey) ? $requiredModuleKey : '';
        if ($requiredModuleKey === '' || in_array($requiredModuleKey, $requiredKeys, true) || in_array($requiredModuleKey, $foundationDefaultKeys, true)) {
            continue;
        }

        if (!in_array($requiredModuleKey, $optionalKeys, true)) {
            $errors[] = $moduleKey . ' requires a module unavailable in the initial installer: ' . $requiredModuleKey;
            continue;
        }

        if (($optionalPosition[$requiredModuleKey] ?? PHP_INT_MAX) > ($optionalPosition[$moduleKey] ?? -1)) {
            $errors[] = $moduleKey . ' must be listed after its optional dependency: ' . $requiredModuleKey;
        }
    }
}

if (str_contains($install, "'url_embed' => [")) {
    $errors[] = 'url_embed must not be listed as an initial installer module.';
}
if (!str_contains($install, "'seo' => [\n        'name' => 'SEO',\n        'version' => '2026.05.001'")) {
    $errors[] = 'seo initial installer version must match its file-only current module version.';
}
if (glob($root . '/modules/seo/updates/*.sql')) {
    $errors[] = 'seo must not keep version-only update SQL files for the current fresh-install baseline.';
}
if (is_file($root . '/modules/asset_exchange/updates/2026.05.006.sql')) {
    $errors[] = 'asset_exchange version-only update must stay removed from the current fresh-install baseline.';
}
foreach (['2026.05.015', '2026.05.016', '2026.05.019', '2026.06.007'] as $contentVersionOnlyUpdate) {
    if (is_file($root . '/modules/content/updates/' . $contentVersionOnlyUpdate . '.sql')) {
        $errors[] = 'content version-only update must stay removed from the current fresh-install baseline: ' . $contentVersionOnlyUpdate;
    }
}
foreach (['2026.05.014', '2026.05.024', '2026.05.026', '2026.06.008', '2026.06.027', '2026.06.029'] as $communityVersionOnlyUpdate) {
    if (is_file($root . '/modules/community/updates/' . $communityVersionOnlyUpdate . '.sql')) {
        $errors[] = 'community version-only update must stay removed from the current fresh-install baseline: ' . $communityVersionOnlyUpdate;
    }
}
if (is_file($root . '/modules/member/updates/2026.04.006.sql')) {
    $errors[] = 'member version-only update must stay removed from the current fresh-install baseline.';
}
foreach ([
    'point' => ['2026.05.001', '2026.05.004'],
    'deposit' => ['2026.05.001', '2026.05.004'],
    'reward' => ['2026.05.001', '2026.05.004', '2026.06.001'],
] as $assetModuleKey => $assetVersionOnlyUpdates) {
    foreach ($assetVersionOnlyUpdates as $assetVersionOnlyUpdate) {
        if (is_file($root . '/modules/' . $assetModuleKey . '/updates/' . $assetVersionOnlyUpdate . '.sql')) {
            $errors[] = $assetModuleKey . ' version-only update must stay removed from the current fresh-install baseline: ' . $assetVersionOnlyUpdate;
        }
    }
}

foreach (['point', 'deposit', 'reward'] as $moduleKey) {
    foreach (glob($root . '/modules/' . $moduleKey . '/{helpers.php,module.php,install.sql,updates/*.sql}', GLOB_BRACE) ?: [] as $path) {
        $content = file_get_contents($path);
        if (is_string($content) && str_contains($content, 'manual_adjust_group_policies_json')) {
            $errors[] = 'legacy manual adjustment group policy setting must stay removed from ' . str_replace($root . '/', '', $path);
        }
    }
}

foreach (['function sr_install_module_dependency_keys', 'sr_install_module_dependency_keys($selectedOptionalModuleKeys'] as $marker) {
    if (!str_contains($install, $marker)) {
        $errors[] = 'initial installer must auto-include selected module dependencies: ' . $marker;
    }
}

foreach ([
    "foreach (sr_member_display_name_validation_errors((string) \$values['admin_display_name']) as \$displayNameError)",
    "\$addInstallError(\$displayNameError, 'admin', ['admin_display_name']);",
] as $marker) {
    if (!str_contains($install, $marker)) {
        $errors[] = 'initial installer must reject an invalid owner display name before schema mutation: ' . $marker;
    }
}

$installView = $read('core/views/install.php');
foreach ([
    '$selectedModuleSummaryLabels = array_values(array_unique(array_merge($selectedModuleLabels, $selectedAutoDependencyModuleLabels)));',
    'data-install-foundation-module',
    'data-install-foundation-status',
    '이번 설치 포함',
    "updateTextSummary('optional_modules', moduleLabels.concat(autoDependencyLabels).length",
    'name="admin_display_name" value="<?php echo sr_e($values[\'admin_display_name\']); ?>" pattern="[^\\s]+" required',
    '<span class="sr-install-help">공백 없이 입력해 주세요.</span>',
] as $marker) {
    if (!str_contains($installView, $marker)) {
        $errors[] = 'initial installer must show auto-included foundation modules in the selected module list: ' . $marker;
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "installer module list checks completed.\n";
