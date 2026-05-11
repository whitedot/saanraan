<?php

declare(strict_types=1);

function toy_load_site(PDO $pdo): ?array
{
    $settings = toy_site_settings($pdo);

    return [
        'name' => (string) ($settings['site.name'] ?? 'Toycore'),
        'base_url' => (string) ($settings['site.base_url'] ?? ''),
        'timezone' => (string) ($settings['site.timezone'] ?? 'Asia/Seoul'),
        'default_locale' => (string) ($settings['site.default_locale'] ?? 'ko'),
        'supported_locales' => (string) ($settings['site.supported_locales'] ?? (string) ($settings['site.default_locale'] ?? 'ko')),
        'status' => (string) ($settings['site.status'] ?? 'active'),
        'public_layout_key' => (string) ($settings['public_layout_key'] ?? 'basic'),
    ];
}

function toy_enabled_module_keys(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT module_key FROM toy_modules WHERE status = 'enabled' ORDER BY id ASC");
    $moduleKeys = [];
    foreach ($stmt->fetchAll() as $row) {
        $moduleKey = (string) ($row['module_key'] ?? '');
        if (toy_is_safe_module_key($moduleKey)) {
            $moduleKeys[] = $moduleKey;
        }
    }

    return $moduleKeys;
}

function toy_is_safe_module_key(string $moduleKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $moduleKey) === 1;
}

function toy_module_enabled(PDO $pdo, string $moduleKey): bool
{
    if (!toy_is_safe_module_key($moduleKey)) {
        return false;
    }

    return in_array($moduleKey, toy_enabled_module_keys($pdo), true);
}

function toy_module_record_status(PDO $pdo, string $moduleKey): string
{
    $module = toy_module_record_entry($pdo, $moduleKey);
    return is_array($module) ? (string) ($module['status'] ?? '') : '';
}

function toy_module_record_entry(PDO $pdo, string $moduleKey): ?array
{
    if (!toy_is_safe_module_key($moduleKey)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT module_key, version, status FROM toy_modules WHERE module_key = :module_key LIMIT 1');
    $stmt->execute(['module_key' => $moduleKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function toy_module_type(string $moduleKey): string
{
    $metadata = toy_module_metadata($moduleKey);
    $type = (string) ($metadata['type'] ?? 'module');

    return in_array($type, ['module', 'plugin'], true) ? $type : 'module';
}

function toy_enabled_module_contract_files(PDO $pdo, string $contractFile, array $excludedModuleKeys = []): array
{
    if (preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $contractFile) !== 1) {
        return [];
    }

    $excluded = [];
    foreach ($excludedModuleKeys as $moduleKey) {
        if (is_string($moduleKey) && toy_is_safe_module_key($moduleKey)) {
            $excluded[$moduleKey] = true;
        }
    }

    $files = [];
    foreach (toy_enabled_module_keys($pdo) as $moduleKey) {
        if (isset($excluded[$moduleKey])) {
            continue;
        }

        if (!toy_module_contract_is_loadable($moduleKey)) {
            continue;
        }

        $moduleDir = TOY_ROOT . '/modules/' . $moduleKey;
        $file = $moduleDir . '/' . $contractFile;
        if (!is_file($file)) {
            continue;
        }

        $realModuleDir = realpath($moduleDir);
        $realFile = realpath($file);
        if ($realModuleDir === false || $realFile === false || strpos($realFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
            continue;
        }

        $files[$moduleKey] = $realFile;
    }

    return $files;
}

function toy_load_module_contract_file(string $moduleKey, string $file): mixed
{
    if (!toy_is_safe_module_key($moduleKey) || !is_file($file)) {
        return null;
    }

    $moduleDir = TOY_ROOT . '/modules/' . $moduleKey;
    $realModuleDir = realpath($moduleDir);
    $realFile = realpath($file);
    if ($realModuleDir === false || $realFile === false || strpos($realFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }

    try {
        return include $realFile;
    } catch (Throwable $exception) {
        if (function_exists('toy_log_exception')) {
            $contractFile = strtolower(basename($realFile));
            $contractLabel = preg_replace('/[^a-z0-9_]+/', '_', $contractFile);
            $contractLabel = is_string($contractLabel) ? trim($contractLabel, '_') : 'contract';
            toy_log_exception($exception, 'module_contract_load_failed_' . $moduleKey . '_' . $contractLabel);
        }

        return null;
    }
}

function toy_site_settings(PDO $pdo): array
{
    static $cache = [];
    static $cacheToken = null;

    $currentToken = (int) ($GLOBALS['toy_site_settings_cache_token'] ?? 0);
    if ($cacheToken !== $currentToken) {
        $cache = [];
        $cacheToken = $currentToken;
    }

    if (isset($cache['all'])) {
        return $cache['all'];
    }

    $stmt = $pdo->query('SELECT setting_key, setting_value, value_type FROM toy_site_settings ORDER BY setting_key ASC');

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = toy_cast_setting_value($row['setting_value'], (string) $row['value_type']);
    }

    $cache['all'] = $settings;
    return $settings;
}

function toy_clear_site_settings_cache(): void
{
    $GLOBALS['toy_site_settings_cache_token'] = (int) ($GLOBALS['toy_site_settings_cache_token'] ?? 0) + 1;
}

function toy_site_setting(PDO $pdo, string $key, mixed $default = null): mixed
{
    $settings = toy_site_settings($pdo);
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function toy_save_site_setting(PDO $pdo, string $key, string $value, string $valueType = 'string'): void
{
    if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $key) !== 1) {
        throw new InvalidArgumentException('Site setting key is invalid.');
    }

    if (!in_array($valueType, ['string', 'int', 'bool', 'json'], true)) {
        throw new InvalidArgumentException('Site setting value type is invalid.');
    }

    $now = toy_now();
    $stmt = $pdo->prepare(
        'INSERT INTO toy_site_settings
            (setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    toy_clear_site_settings_cache();
}

function toy_save_site_settings(PDO $pdo, array $settings): void
{
    foreach ($settings as $key => $setting) {
        if (!is_array($setting)) {
            continue;
        }

        toy_save_site_setting(
            $pdo,
            (string) $key,
            (string) ($setting['value'] ?? ''),
            (string) ($setting['type'] ?? 'string')
        );
    }
}

function toy_module_settings(PDO $pdo, string $moduleKey): array
{
    static $cache = [];
    static $cacheTokens = [];

    if (!toy_is_safe_module_key($moduleKey)) {
        return [];
    }

    $currentToken = (int) ($GLOBALS['toy_module_settings_cache_token'] ?? 0)
        + (int) ($GLOBALS['toy_module_settings_cache_token_' . $moduleKey] ?? 0);
    if (!isset($cacheTokens[$moduleKey]) || $cacheTokens[$moduleKey] !== $currentToken) {
        unset($cache[$moduleKey]);
        $cacheTokens[$moduleKey] = $currentToken;
    }

    if (isset($cache[$moduleKey])) {
        return $cache[$moduleKey];
    }

    $stmt = $pdo->prepare(
        'SELECT s.setting_key, s.setting_value, s.value_type
         FROM toy_module_settings s
         INNER JOIN toy_modules m ON m.id = s.module_id
         WHERE m.module_key = :module_key
         ORDER BY s.setting_key ASC'
    );
    $stmt->execute(['module_key' => $moduleKey]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = toy_cast_setting_value($row['setting_value'], (string) $row['value_type']);
    }

    $cache[$moduleKey] = $settings;
    return $settings;
}

function toy_module_setting(PDO $pdo, string $moduleKey, string $key, mixed $default = null): mixed
{
    $settings = toy_module_settings($pdo, $moduleKey);
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

function toy_clear_module_settings_cache(?string $moduleKey = null): void
{
    if ($moduleKey !== null && !toy_is_safe_module_key($moduleKey)) {
        return;
    }

    $GLOBALS['toy_module_settings_cache_token'] = (int) ($GLOBALS['toy_module_settings_cache_token'] ?? 0) + 1;
    if ($moduleKey !== null) {
        $GLOBALS['toy_module_settings_cache_token_' . $moduleKey] = (int) ($GLOBALS['toy_module_settings_cache_token_' . $moduleKey] ?? 0) + 1;
    }
}

function toy_module_metadata(string $moduleKey): array
{
    static $cache = [];

    if (!toy_is_safe_module_key($moduleKey)) {
        return [];
    }

    if (isset($cache[$moduleKey])) {
        return $cache[$moduleKey];
    }

    $file = TOY_ROOT . '/modules/' . $moduleKey . '/module.php';
    if (!is_file($file)) {
        $cache[$moduleKey] = [];
        return [];
    }

    try {
        $metadata = include $file;
    } catch (Throwable $exception) {
        if (function_exists('toy_log_exception')) {
            toy_log_exception($exception, 'module_metadata_load_failed_' . $moduleKey);
        }

        $cache[$moduleKey] = [];
        return [];
    }

    $cache[$moduleKey] = is_array($metadata) ? $metadata : [];

    return $cache[$moduleKey];
}

function toy_module_toycore_metadata(array $metadata): array
{
    return is_array($metadata['toycore'] ?? null) ? $metadata['toycore'] : [];
}

function toy_module_known_contract_files(): array
{
    return [
        'paths.php',
        'admin-menu.php',
        'output-slots.php',
        'extension-points.php',
        'privacy-export.php',
        'sitemap.php',
        'menu-links.php',
        'member-group-rules.php',
    ];
}

function toy_module_declared_contract_files(array $metadata, string $key): array
{
    $contracts = isset($metadata['contracts']) && is_array($metadata['contracts']) ? $metadata['contracts'] : [];
    $files = isset($contracts[$key]) && is_array($contracts[$key]) ? $contracts[$key] : [];
    $valid = [];

    foreach ($files as $file) {
        if (is_string($file)) {
            $valid[] = $file;
        }
    }

    return array_values(array_unique($valid));
}

function toy_version_format(string $version): string
{
    if (preg_match('/\Av?\d+\.\d+\.\d+\z/', $version) === 1) {
        return 'semver';
    }

    if (preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) === 1) {
        return 'date';
    }

    return '';
}

function toy_core_version_satisfies_minimum(string $minimumVersion): bool
{
    $coreVersion = TOY_CORE_VERSION;
    $coreFormat = toy_version_format($coreVersion);
    $minimumFormat = toy_version_format($minimumVersion);

    if ($coreFormat === '' || $minimumFormat === '' || $coreFormat !== $minimumFormat) {
        return false;
    }

    if ($coreFormat === 'semver') {
        return version_compare(ltrim($coreVersion, 'vV'), ltrim($minimumVersion, 'vV'), '>=');
    }

    return strcmp($coreVersion, $minimumVersion) >= 0;
}

function toy_module_contract_errors(array $metadata): array
{
    $errors = [];
    $toycoreMetadata = toy_module_toycore_metadata($metadata);
    $moduleContract = is_string($toycoreMetadata['module_contract'] ?? null) ? (string) $toycoreMetadata['module_contract'] : '';

    if ($moduleContract === '') {
        $errors[] = 'module.php의 toycore.module_contract가 필요합니다.';
    } elseif ($moduleContract !== TOY_MODULE_CONTRACT_VERSION) {
        $errors[] = 'module.php의 toycore.module_contract가 현재 코어 계약 버전(' . TOY_MODULE_CONTRACT_VERSION . ')과 맞지 않습니다.';
    }

    $minVersion = is_string($toycoreMetadata['min_version'] ?? null) ? (string) $toycoreMetadata['min_version'] : '';
    if ($minVersion === '') {
        $errors[] = 'module.php의 toycore.min_version이 필요합니다.';
    } elseif (preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $minVersion) !== 1) {
        $errors[] = 'module.php의 toycore.min_version 형식이 올바르지 않습니다.';
    } elseif (!toy_core_version_satisfies_minimum($minVersion)) {
        $errors[] = '현재 Toycore 버전(' . TOY_CORE_VERSION . ')이 module.php의 toycore.min_version(' . $minVersion . ') 요구사항을 만족하지 않습니다.';
    }

    $testedWith = $toycoreMetadata['tested_with'] ?? null;
    if (!is_array($testedWith)) {
        $errors[] = 'module.php의 toycore.tested_with는 배열이어야 합니다.';
    } elseif ($testedWith === []) {
        $errors[] = 'module.php의 toycore.tested_with가 필요합니다.';
    } else {
        foreach ($testedWith as $version) {
            if (!is_string($version) || preg_match('/\A(?:v?\d+\.\d+\.\d+|\d{4}\.\d{2}\.\d{3})\z/', $version) !== 1) {
                $errors[] = 'module.php의 toycore.tested_with 버전 형식이 올바르지 않습니다.';
                break;
            }
        }
    }

    return $errors;
}

function toy_module_metadata_errors(array $metadata): array
{
    $errors = [];

    $name = is_string($metadata['name'] ?? null) ? trim((string) $metadata['name']) : '';
    if ($name === '') {
        $errors[] = 'module.php의 name이 필요합니다.';
    }

    $version = is_string($metadata['version'] ?? null) ? (string) $metadata['version'] : '';
    if ($version === '' || preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', $version) !== 1) {
        $errors[] = 'module.php의 version은 YYYY.MM.NNN 형식이어야 합니다.';
    }

    $type = (string) ($metadata['type'] ?? 'module');
    if (!in_array($type, ['module', 'plugin'], true)) {
        $errors[] = 'module.php의 type은 module 또는 plugin이어야 합니다.';
    }

    foreach (toy_module_contract_errors($metadata) as $error) {
        $errors[] = $error;
    }

    $contracts = $metadata['contracts'] ?? null;
    if ($contracts !== null && !is_array($contracts)) {
        $errors[] = 'module.php의 contracts는 배열이어야 합니다.';
    }

    $knownContractFiles = toy_module_known_contract_files();
    $contracts = is_array($contracts) ? $contracts : [];
    foreach (['provides', 'consumes'] as $contractKey) {
        if (!isset($contracts[$contractKey])) {
            continue;
        }

        if (!is_array($contracts[$contractKey])) {
            $errors[] = 'module.php의 contracts.' . $contractKey . '는 배열이어야 합니다.';
            continue;
        }

        foreach ($contracts[$contractKey] as $file) {
            if (
                !is_string($file)
                || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $file) !== 1
                || !in_array($file, $knownContractFiles, true)
            ) {
                $errors[] = 'module.php의 contracts.' . $contractKey . ' 계약 파일 선언이 올바르지 않습니다.';
                break;
            }
        }
    }

    return array_values(array_unique($errors));
}

function toy_module_contract_file_errors(string $moduleDirectory, array $metadata): array
{
    $errors = [];
    $knownContractFiles = toy_module_known_contract_files();
    $declaredProvides = toy_module_declared_contract_files($metadata, 'provides');

    foreach ($declaredProvides as $file) {
        if (!is_file($moduleDirectory . '/' . $file)) {
            $errors[] = 'module.php의 contracts.provides에 선언한 ' . $file . ' 파일이 필요합니다.';
        }
    }

    foreach ($knownContractFiles as $file) {
        if (is_file($moduleDirectory . '/' . $file) && !in_array($file, $declaredProvides, true)) {
            $errors[] = $file . ' 파일은 module.php의 contracts.provides에 선언해야 합니다.';
        }
    }

    return array_values(array_unique($errors));
}

function toy_module_contract_is_loadable(string $moduleKey): bool
{
    if (!toy_is_safe_module_key($moduleKey)) {
        return false;
    }

    $metadata = toy_module_metadata($moduleKey);
    return $metadata !== []
        && toy_module_metadata_errors($metadata) === []
        && toy_module_contract_file_errors(TOY_ROOT . '/modules/' . $moduleKey, $metadata) === [];
}

function toy_module_requirement_errors(PDO $pdo, string $moduleKey, array $metadata, string $targetStatus = 'enabled'): array
{
    if ($targetStatus !== 'enabled') {
        return [];
    }

    $errors = [];
    $requires = isset($metadata['requires']) && is_array($metadata['requires']) ? $metadata['requires'] : [];
    $requiredModules = isset($requires['modules']) && is_array($requires['modules']) ? $requires['modules'] : [];

    foreach ($requiredModules as $key => $value) {
        $requiredModuleKey = is_string($key) ? $key : (string) $value;
        if (!toy_is_safe_module_key($requiredModuleKey) || $requiredModuleKey === $moduleKey) {
            $errors[] = '모듈 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        $requiredModule = toy_module_record_entry($pdo, $requiredModuleKey);
        if (!is_array($requiredModule) || (string) ($requiredModule['status'] ?? '') !== 'enabled') {
            $errors[] = $requiredModuleKey . ' 모듈을 먼저 활성화해야 합니다.';
            continue;
        }

        $minimumVersion = is_string($key) ? (string) $value : '';
        if ($minimumVersion !== '' && strcmp((string) ($requiredModule['version'] ?? ''), $minimumVersion) < 0) {
            $errors[] = $requiredModuleKey . ' 모듈 ' . $minimumVersion . ' 이상이 필요합니다.';
        }
    }

    $requiredContracts = isset($requires['contracts']) && is_array($requires['contracts']) ? $requires['contracts'] : [];
    foreach ($requiredContracts as $contract) {
        if (!is_array($contract)) {
            $errors[] = '계약 파일 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        $requiredModuleKey = (string) ($contract['module'] ?? '');
        $file = (string) ($contract['file'] ?? '');
        if (!toy_is_safe_module_key($requiredModuleKey) || preg_match('/\A[a-z0-9][a-z0-9_.-]{0,80}\.php\z/', $file) !== 1) {
            $errors[] = '계약 파일 의존성 선언이 올바르지 않습니다.';
            continue;
        }

        if (toy_module_record_status($pdo, $requiredModuleKey) !== 'enabled') {
            $errors[] = $requiredModuleKey . ' 모듈을 먼저 활성화해야 합니다.';
            continue;
        }

        if (!toy_module_contract_is_loadable($requiredModuleKey)) {
            $errors[] = $requiredModuleKey . ' 모듈 메타데이터/계약이 현재 Toycore와 맞지 않습니다.';
            continue;
        }

        $requiredMetadata = toy_module_metadata($requiredModuleKey);
        if (!in_array($file, toy_module_declared_contract_files($requiredMetadata, 'provides'), true)) {
            $errors[] = $requiredModuleKey . ' 모듈의 module.php contracts.provides에 ' . $file . ' 선언이 필요합니다.';
            continue;
        }

        if (!is_file(TOY_ROOT . '/modules/' . $requiredModuleKey . '/' . $file)) {
            $errors[] = $requiredModuleKey . ' 모듈의 ' . $file . ' 계약 파일이 필요합니다.';
        }
    }

    return array_values(array_unique($errors));
}

function toy_cast_setting_value(mixed $value, string $type): mixed
{
    if ($type === 'int') {
        return (int) $value;
    }

    if ($type === 'bool') {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    if ($type === 'json') {
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    return $value === null ? '' : (string) $value;
}
