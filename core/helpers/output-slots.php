<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_render_output_slot(PDO $pdo, array $context): string
{
    $context = sr_output_slot_normalized_context($context);
    if ($context === null) {
        return '';
    }

    $moduleKey = (string) $context['module_key'];

    $output = [];
    foreach (sr_output_slot_renderer_contracts($pdo, $moduleKey) as $rendererContract) {
        $rendererModuleKey = (string) ($rendererContract['module_key'] ?? '');
        $file = (string) ($rendererContract['contract_file'] ?? '');
        $renderer = sr_output_slot_contract_renderer(sr_load_module_contract_file($rendererModuleKey, $file));
        if (!is_callable($renderer)) {
            continue;
        }

        try {
            $rendered = $renderer($pdo, $context);
            if (is_string($rendered) && $rendered !== '') {
                $output[] = $rendered;
            }
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'module_output_slot_failed_' . $rendererModuleKey);
            }
        }
    }

    return implode("\n", $output);
}

function sr_output_slot_asset_paths(PDO $pdo, array $slotContexts): array
{
    if (isset($slotContexts['module_key'])) {
        $slotContexts = [$slotContexts];
    }

    $stylesheets = [];
    $scripts = [];
    foreach ($slotContexts as $slotContext) {
        if (!is_array($slotContext)) {
            continue;
        }
        $context = sr_output_slot_normalized_context($slotContext);
        if ($context === null) {
            continue;
        }
        $moduleKey = (string) $context['module_key'];
        foreach (sr_output_slot_renderer_contracts($pdo, $moduleKey) as $rendererContract) {
            $rendererModuleKey = (string) ($rendererContract['module_key'] ?? '');
            $file = (string) ($rendererContract['contract_file'] ?? '');
            $assets = sr_output_slot_contract_assets($pdo, sr_load_module_contract_file($rendererModuleKey, $file), $context);
            foreach ($assets['stylesheets'] as $stylesheet) {
                $stylesheets[$stylesheet] = $stylesheet;
            }
            foreach ($assets['scripts'] as $script) {
                $scripts[$script] = $script;
            }
        }
    }

    return [
        'stylesheets' => array_values($stylesheets),
        'scripts' => array_values($scripts),
    ];
}

function sr_public_layout_context_with_output_slot_assets(PDO $pdo, array $layoutContext, array $slotContexts): array
{
    $assets = sr_output_slot_asset_paths($pdo, $slotContexts);
    $stylesheets = is_array($layoutContext['stylesheets'] ?? null) ? $layoutContext['stylesheets'] : [];
    $scripts = is_array($layoutContext['scripts'] ?? null) ? $layoutContext['scripts'] : [];
    foreach ($assets['stylesheets'] as $stylesheet) {
        $stylesheets[] = $stylesheet;
    }
    foreach ($assets['scripts'] as $script) {
        $scripts[] = $script;
    }
    $layoutContext['stylesheets'] = array_values(array_unique($stylesheets));
    $layoutContext['scripts'] = array_values(array_unique($scripts));

    return $layoutContext;
}

function sr_public_layout_output_slot_contexts(array $layoutContext, array $consumerDomains): array
{
    $contexts = [];
    if (is_array($layoutContext['output_slots'] ?? null)) {
        foreach ($layoutContext['output_slots'] as $slotContext) {
            if (is_array($slotContext)) {
                $contexts[] = $slotContext;
            }
        }
    }

    foreach (['navigation', 'primary_navigation', 'secondary_navigation', 'tertiary_navigation', 'quaternary_navigation', 'quinary_navigation'] as $slotKey) {
        $contexts[] = ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => $slotKey];
    }
    foreach (['before_layout', 'before_footer', 'after_layout'] as $slotKey) {
        $contexts[] = ['module_key' => 'core', 'point_key' => 'site.layout', 'slot_key' => $slotKey];
    }
    foreach ($consumerDomains as $consumerDomain) {
        if (!sr_is_safe_module_key((string) $consumerDomain) || $consumerDomain === 'site') {
            continue;
        }
        foreach (['before_layout', 'before_footer'] as $slotKey) {
            $contexts[] = ['module_key' => (string) $consumerDomain, 'point_key' => (string) $consumerDomain . '.layout', 'slot_key' => $slotKey];
        }
    }

    return $contexts;
}

function sr_output_slot_normalized_context(array $context): ?array
{
    $moduleKey = (string) ($context['module_key'] ?? '');
    $pointKey = (string) ($context['point_key'] ?? '');
    $slotKey = (string) ($context['slot_key'] ?? '');

    if (
        !sr_is_safe_module_key($moduleKey)
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,119}\z/', $pointKey) !== 1
        || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,79}\z/', $slotKey) !== 1
    ) {
        return null;
    }

    $context['module_key'] = $moduleKey;
    $context['point_key'] = $pointKey;
    $context['slot_key'] = $slotKey;

    return $context;
}

function sr_output_slot_contract_renderer(mixed $contract): mixed
{
    if (is_callable($contract)) {
        return $contract;
    }
    if (is_array($contract) && is_callable($contract['renderer'] ?? null)) {
        return $contract['renderer'];
    }

    return null;
}

function sr_output_slot_contract_assets(PDO $pdo, mixed $contract, array $context): array
{
    if (!is_array($contract)) {
        return ['stylesheets' => [], 'scripts' => []];
    }

    $declared = [];
    if (is_callable($contract['assets_function'] ?? null)) {
        try {
            $declared = $contract['assets_function']($pdo, $context);
        } catch (Throwable $exception) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($exception, 'module_output_slot_assets_failed');
            }
            $declared = [];
        }
    }
    if (!is_array($declared)) {
        $declared = [];
    }

    if (isset($contract['stylesheets'])) {
        $declared['stylesheets'] = array_merge((array) ($declared['stylesheets'] ?? []), (array) $contract['stylesheets']);
    }
    if (isset($contract['scripts'])) {
        $declared['scripts'] = array_merge((array) ($declared['scripts'] ?? []), (array) $contract['scripts']);
    }

    return [
        'stylesheets' => sr_output_slot_normalize_asset_paths((array) ($declared['stylesheets'] ?? [])),
        'scripts' => sr_output_slot_normalize_asset_paths((array) ($declared['scripts'] ?? [])),
    ];
}

function sr_output_slot_normalize_asset_paths(array $paths): array
{
    $normalized = [];
    foreach ($paths as $path) {
        if (!is_string($path) || !sr_is_safe_relative_url($path)) {
            continue;
        }
        $normalized[$path] = $path;
    }

    return array_values($normalized);
}

function sr_output_slot_renderer_contracts(PDO $pdo, string $excludedModuleKey): array
{
    $excludedModuleKey = sr_is_safe_module_key($excludedModuleKey) ? $excludedModuleKey : '';
    $cache = $GLOBALS['sr_output_slot_renderer_contracts_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    $contractName = 'output-slots.php';
    $contractVersion = defined('SR_MODULE_CONTRACT_VERSION') ? SR_MODULE_CONTRACT_VERSION : 'contract-unknown';
    $cacheKey = implode(':', [
        (string) spl_object_id($pdo),
        $contractName,
        $excludedModuleKey,
        $contractVersion,
        function_exists('sr_module_registry_cache_token') ? (string) sr_module_registry_cache_token() : 'registry-unknown',
    ]);
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Keep this cache value serializable. A future shared cache adapter may reuse
    // this key/value shape, so store contract metadata only.
    $contracts = [];
    foreach (sr_enabled_module_contract_files($pdo, 'output-slots.php', $excludedModuleKey !== '' ? [$excludedModuleKey] : []) as $rendererModuleKey => $file) {
        $contracts[] = [
            'module_key' => (string) $rendererModuleKey,
            'contract_name' => $contractName,
            'contract_version' => $contractVersion,
            'contract_file' => (string) $file,
        ];
    }

    $cache[$cacheKey] = $contracts;
    $GLOBALS['sr_output_slot_renderer_contracts_runtime_cache'] = $cache;

    return $contracts;
}
