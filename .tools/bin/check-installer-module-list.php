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

foreach (['function sr_install_module_dependency_keys', 'sr_install_module_dependency_keys($selectedOptionalModuleKeys'] as $marker) {
    if (!str_contains($install, $marker)) {
        $errors[] = 'initial installer must auto-include selected module dependencies: ' . $marker;
    }
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo "installer module list checks completed.\n";
