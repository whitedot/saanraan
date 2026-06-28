<?php

declare(strict_types=1);

function sr_admin_dashboard_modules(PDO $pdo): array
{
    $modules = [];
    $stmt = $pdo->query('SELECT module_key, name, version, status FROM sr_modules ORDER BY id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $modules[] = $row;
    }

    return $modules;
}

function sr_admin_dashboard_table_exists(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_[a-z0-9_]{1,80}\z/', $tableName) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_admin_dashboard_count(PDO $pdo, string $sql): int
{
    try {
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['count_value'] : 0;
    } catch (PDOException $exception) {
        return 0;
    }
}

function sr_admin_dashboard_operation_summary(PDO $pdo): array
{
    $operationSummary = [];

    if (sr_admin_dashboard_table_exists($pdo, 'sr_site_menus') && sr_admin_dashboard_table_exists($pdo, 'sr_site_menu_items')) {
        $operationSummary[] = [
            'label' => sr_t('admin::ui.menu.a14f2522'),
            'value' => (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_site_menus WHERE status = 'enabled'"),
            'detail' => sr_t('admin::ui.menu.140391a7') . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_site_menu_items WHERE status = 'enabled'"),
        ];
    }

    if (sr_admin_dashboard_table_exists($pdo, 'sr_banners')) {
        $operationSummary[] = [
            'label' => sr_t('admin::ui.banner.63182d60'),
            'value' => (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_banners WHERE status = 'enabled'"),
            'detail' => sr_t('admin::ui.banner.save.31179583') . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_banners WHERE status = 'draft'"),
        ];
    }

    if (sr_admin_dashboard_table_exists($pdo, 'sr_notifications') && sr_admin_dashboard_table_exists($pdo, 'sr_notification_deliveries')) {
        $operationSummary[] = [
            'label' => sr_t('admin::ui.notification.12ddd6ca'),
            'value' => (string) sr_admin_dashboard_count($pdo, 'SELECT COUNT(*) AS count_value FROM sr_notifications'),
            'detail' => sr_t('admin::ui.all.notification.e9b364cb') . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_notification_deliveries WHERE status = 'queued'"),
        ];
    }

    return $operationSummary;
}

function sr_admin_dashboard_scalar(PDO $pdo, string $sql, string $column, string $default = ''): string
{
    $trimmed = trim($sql);
    if ($trimmed === '' || preg_match('/\Aselect\s/i', $trimmed) !== 1 || strpos($trimmed, ';') !== false) {
        return $default;
    }

    try {
        $stmt = $pdo->query($trimmed);
        $row = $stmt->fetch();
    } catch (PDOException $exception) {
        return $default;
    }

    if (!is_array($row) || !array_key_exists($column, $row)) {
        return $default;
    }

    return (string) $row[$column];
}

function sr_admin_dashboard_layout(string $layout): string
{
    $layout = strtolower(trim($layout));

    return in_array($layout, ['table', 'stats'], true) ? $layout : 'table';
}

function sr_admin_dashboard_state(string $state): string
{
    $state = strtolower(trim($state));

    return in_array($state, ['default', 'success', 'warning', 'danger', 'info'], true) ? $state : 'default';
}

function sr_admin_dashboard_emphasis(string $emphasis): string
{
    $emphasis = strtolower(trim($emphasis));

    return in_array($emphasis, ['default', 'primary'], true) ? $emphasis : 'default';
}

function sr_admin_dashboard_default_visible(mixed $value): bool
{
    if ($value === null) {
        return true;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value)) {
        return $value !== 0;
    }

    $normalized = strtolower(trim((string) $value));
    if ($normalized === '') {
        return true;
    }

    return !in_array($normalized, ['0', 'false', 'hidden', 'no', 'off'], true);
}

function sr_admin_dashboard_view_file(string $moduleKey, string $view): string
{
    if (!sr_is_safe_module_key($moduleKey)) {
        return '';
    }

    $view = trim($view);
    if (
        $view === ''
        || str_starts_with($view, '/')
        || str_contains($view, '\\')
        || str_contains($view, '..')
        || preg_match('/\Aviews\/[a-zA-Z0-9_\/.-]{1,120}\.php\z/', $view) !== 1
    ) {
        return '';
    }

    $moduleDir = SR_ROOT . '/modules/' . $moduleKey;
    $viewFile = $moduleDir . '/' . $view;
    $realModuleDir = realpath($moduleDir);
    $realViewFile = realpath($viewFile);

    if ($realModuleDir === false || $realViewFile === false || strpos($realViewFile, $realModuleDir . DIRECTORY_SEPARATOR) !== 0) {
        return '';
    }

    return $realViewFile;
}

function sr_admin_dashboard_module_section_body(PDO $pdo, array $section): string
{
    $viewFile = (string) ($section['view_file'] ?? '');
    if ($viewFile === '' || !is_file($viewFile)) {
        $viewFile = SR_ROOT . '/modules/admin/views/dashboard-module-section-default.php';
    }

    $dashboardSection = $section;
    $dashboardRows = (array) ($section['rows'] ?? []);
    $dashboardModuleKey = (string) ($section['module_key'] ?? '');
    $dashboardSectionTitle = (string) ($section['title'] ?? $dashboardModuleKey);

    try {
        ob_start();
        include $viewFile;
        return (string) ob_get_clean();
    } catch (Throwable $exception) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'admin_dashboard_module_section_render_failed_' . $dashboardModuleKey);
        }

        if ($viewFile !== SR_ROOT . '/modules/admin/views/dashboard-module-section-default.php') {
            return sr_admin_dashboard_module_section_body($pdo, array_merge($section, ['view_file' => '']));
        }

        return '';
    }
}

function sr_admin_dashboard_metric_rows(PDO $pdo, array $rows): array
{
    $metrics = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            continue;
        }

        $detail = sr_admin_dashboard_scalar($pdo, (string) ($row['detail_sql'] ?? ''), 'detail', (string) ($row['detail'] ?? ''));
        if ($detail !== '') {
            $detail = (string) ($row['detail_prefix'] ?? '') . $detail . (string) ($row['detail_suffix'] ?? '');
        }

        $metrics[] = [
            'label' => $label,
            'value' => sr_admin_dashboard_scalar($pdo, (string) ($row['value_sql'] ?? ''), 'value', (string) ($row['value'] ?? '')),
            'detail' => $detail,
            'state' => sr_admin_dashboard_state((string) ($row['state'] ?? 'default')),
            'emphasis' => sr_admin_dashboard_emphasis((string) ($row['emphasis'] ?? 'default')),
        ];
    }

    return $metrics;
}

function sr_admin_dashboard_module_sections(PDO $pdo): array
{
    $sections = [];
    $menuOrderByModule = sr_admin_dashboard_module_menu_order_map($pdo);

    foreach (sr_enabled_module_contract_files($pdo, 'dashboard.php') as $moduleKey => $dashboardFile) {
        $definition = sr_load_module_contract_file($moduleKey, $dashboardFile);
        if (!is_array($definition)) {
            continue;
        }

        foreach ($definition as $index => $section) {
            if (!is_array($section)) {
                continue;
            }

            $rawKey = (string) ($section['key'] ?? ((string) $moduleKey . '_' . (string) $index));
            $sectionKey = preg_replace('/[^a-z0-9_]+/', '_', strtolower($rawKey));
            $sectionKey = is_string($sectionKey) && $sectionKey !== '' ? trim($sectionKey, '_') : (string) $moduleKey;
            $layout = sr_admin_dashboard_layout((string) ($section['layout'] ?? 'table'));
            $viewFile = sr_admin_dashboard_view_file((string) $moduleKey, (string) ($section['view'] ?? ''));
            $sourceRows = ($viewFile !== '' || $layout === 'stats') && is_array($section['items'] ?? null)
                ? (array) $section['items']
                : (array) (is_array($section['rows'] ?? null) ? $section['rows'] : []);
            $rows = sr_admin_dashboard_metric_rows($pdo, $sourceRows);

            if ($viewFile === '' && $rows === []) {
                continue;
            }

            $sections[] = [
                'key' => $sectionKey,
                'layout' => $layout,
                'module_key' => (string) $moduleKey,
                'title' => trim((string) ($section['title'] ?? $moduleKey)),
                'order' => (int) ($section['order'] ?? 100),
                'menu_order' => (int) ($menuOrderByModule[$moduleKey] ?? 100000),
                'default_visible' => sr_admin_dashboard_default_visible($section['default_visible'] ?? null),
                'rows' => $rows,
                'view_file' => $viewFile,
            ];
        }
    }

    usort($sections, static function (array $left, array $right): int {
        return ((int) $left['menu_order'] <=> (int) $right['menu_order'])
            ?: ((int) $left['order'] <=> (int) $right['order'])
            ?: strcmp((string) $left['module_key'], (string) $right['module_key']);
    });

    return $sections;
}

function sr_admin_dashboard_module_menu_order_map(PDO $pdo): array
{
    if (!function_exists('sr_admin_navigation_groups')) {
        return [];
    }

    $orderMap = [];
    $position = 0;

    foreach (sr_admin_navigation_groups($pdo) as $category) {
        foreach ((array) ($category['module_groups'] ?? []) as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleKey = (string) ($moduleGroup['module_key'] ?? '');
            if ($moduleKey === '' || isset($orderMap[$moduleKey])) {
                continue;
            }

            $orderMap[$moduleKey] = $position;
            $position++;
        }
    }

    return $orderMap;
}

function sr_admin_dashboard_auth_runtime_summary(PDO $pdo, array $config): array
{
    $summary = [];
    $session = isset($config['session']) && is_array($config['session']) ? $config['session'] : [];
    $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    $secrets = isset($config['secrets']) && is_array($config['secrets']) ? $config['secrets'] : [];
    $mail = isset($config['mail']) && is_array($config['mail']) ? $config['mail'] : [];

    $sessionHandler = (string) ($session['handler'] ?? 'database');
    $hasRuntimeSessionsTable = sr_admin_dashboard_table_exists($pdo, 'sr_sessions');
    $summary[] = [
        'label' => sr_t('admin::ui.php.save.196310ee'),
        'value' => $sessionHandler === 'database' ? 'DB' : sr_t('admin::ui.text.0c8354d0'),
        'state' => $sessionHandler === 'database' && $hasRuntimeSessionsTable ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => $sessionHandler === 'database'
            ? ($hasRuntimeSessionsTable ? sr_t('admin::ui.sr.sessions.active.32618cfc') : sr_t('admin::ui.sr.sessions.fallback.9c3959ff'))
            : sr_t('admin::ui.save.eb8b7352'),
    ];

    $hasRateLimitsTable = sr_admin_dashboard_table_exists($pdo, 'sr_rate_limits');
    $summary[] = [
        'label' => sr_t('admin::ui.save.41015027'),
        'value' => $hasRateLimitsTable ? sr_t('admin::ui.text.4544383f') : sr_t('admin::ui.fallback.627497a6'),
        'state' => $hasRateLimitsTable ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => $hasRateLimitsTable ? sr_t('admin::ui.sr.rate.limits.active.167521ed') : sr_t('admin::ui.sql.active.534f6761'),
    ];

    $secureCookie = sr_session_cookie_secure($config);
    $summary[] = [
        'label' => sr_t('admin::ui.secure.f6ac91db'),
        'value' => $secureCookie ? sr_t('admin::ui.text.6a1c963d') : sr_t('admin::ui.text.37e22e45'),
        'state' => $secureCookie ? sr_t('admin::ui.text.35688a85') : ((string) ($config['env'] ?? 'production') === 'production' ? sr_t('admin::ui.text.acc90fbf') : sr_t('admin::ui.text.1aacb54c')),
        'detail' => !empty($security['force_https'])
            ? sr_t('admin::ui.force.https.settings.8f6fed27')
            : (sr_is_https_request($config) ? sr_t('admin::ui.text.1fdbd8c1') : sr_t('admin::ui.text.775d4227')),
    ];

    $trustedProxies = sr_trusted_proxy_entries($config);
    $trustedProxyErrors = sr_trusted_proxy_config_errors($config);
    $hasForwardedHeaders = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '') !== '' || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== '';
    $clientIp = sr_client_ip();
    $forwardedClientIp = sr_forwarded_client_ip($config);
    $summary[] = [
        'label' => 'Trusted proxy',
        'value' => (string) count($trustedProxies),
        'state' => $trustedProxyErrors === [] && $trustedProxies !== [] ? sr_t('admin::ui.text.35688a85') : ($hasForwardedHeaders || $trustedProxyErrors !== [] ? sr_t('admin::ui.text.acc90fbf') : sr_t('admin::ui.text.1aacb54c')),
        'detail' => $trustedProxyErrors !== []
            ? sr_t('admin::ui.trusted.ip.cidr.e62ee7e3')
            : ($trustedProxies !== []
            ? sr_t('admin::ui.settings.c1617075')
            : ($hasForwardedHeaders ? sr_t('admin::ui.trusted.652f752f') : sr_t('admin::ui.text.5024f634'))),
    ];

    $summary[] = [
        'label' => sr_t('admin::ui.ip.5c2a95fd'),
        'value' => $clientIp !== '' ? $clientIp : sr_t('admin::ui.text.298859a0'),
        'state' => $clientIp !== '' ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => $forwardedClientIp !== '' ? sr_t('admin::ui.trusted.x.forwarded.for.active.28c96b76') : sr_t('admin::ui.remote.addr.948a80cd'),
    ];

    $memberSettings = sr_member_settings($pdo);
    $summary[] = [
        'label' => sr_t('admin::ui.login.42a3a98c'),
        'value' => (string) $memberSettings['login_throttle_account_limit'] . '/' . (string) $memberSettings['login_throttle_ip_limit'],
        'state' => sr_t('admin::ui.text.35688a85'),
        'detail' => (string) $memberSettings['login_throttle_window_seconds'] . sr_t('admin::ui.ip.647b5bb4'),
    ];

    $summary[] = [
        'label' => sr_t('admin::ui.password.settings.be683f9d'),
        'value' => (string) $memberSettings['password_reset_throttle_account_limit'] . '/' . (string) $memberSettings['password_reset_throttle_ip_limit'],
        'state' => sr_t('admin::ui.text.35688a85'),
        'detail' => (string) $memberSettings['password_reset_throttle_window_seconds'] . sr_t('admin::ui.ip.647b5bb4'),
    ];

    $appKeyEnv = (string) ($secrets['app_key_env'] ?? '');
    $appKeyFromEnv = $appKeyEnv !== '' && getenv($appKeyEnv) !== false && (string) getenv($appKeyEnv) !== '';
    $summary[] = [
        'label' => sr_t('admin::ui.app.key.493861d7'),
        'value' => $appKeyFromEnv ? sr_t('admin::ui.text.5ab2221f') : sr_t('admin::ui.config.e322417f'),
        'state' => sr_app_key($config) !== '' ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => $appKeyFromEnv ? $appKeyEnv . sr_t('admin::ui.active.fa5dac61') : sr_t('admin::ui.config.app.active.623bc6f7'),
    ];

    $transport = (string) ($mail['transport'] ?? 'php_mail');
    $mailReady = sr_admin_dashboard_mail_transport_ready($transport, $mail);
    $summary[] = [
        'label' => sr_t('admin::ui.transport.10a29a19'),
        'value' => $transport,
        'state' => $mailReady ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => $mailReady ? sr_t('admin::ui.settings.fb4a1ed2') : sr_t('admin::ui.settings.f4cd9d47'),
    ];

    $moduleSourcesEnabled = sr_module_sources_enabled($pdo, $config);
    $summary[] = [
        'label' => sr_t('admin::ui.text.c89ee3b7'),
        'value' => $moduleSourcesEnabled ? sr_t('admin::ui.text.688200c4') : sr_t('admin::ui.text.9fd8413e'),
        'state' => $moduleSourcesEnabled && sr_runtime_is_production($config) ? sr_t('admin::ui.text.acc90fbf') : sr_t('admin::ui.text.35688a85'),
        'detail' => $moduleSourcesEnabled
            ? sr_t('admin::ui.zip.d96d97ff')
            : sr_t('admin::ui.zip.928843d9'),
    ];

    $storageDriver = sr_storage_default_driver($config);
    $s3ConfigErrors = sr_storage_s3_config_errors($config);
    $summary[] = [
        'label' => sr_t('admin::ui.save.2fe7ca23'),
        'value' => $storageDriver === 's3' ? 'S3' : sr_t('admin::ui.text.0f61584c'),
        'state' => $storageDriver === 's3' ? (sr_storage_s3_ready($config) ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf')) : ($s3ConfigErrors === [] ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.1aacb54c')),
        'detail' => $storageDriver === 's3'
            ? (sr_storage_s3_ready($config) ? sr_t('admin::ui.s3.settings.8c226869') : sr_t('admin::ui.s3.settings.0aee6cda') . implode(' ', $s3ConfigErrors))
            : ($s3ConfigErrors === [] ? sr_t('admin::ui.storage.save.active.4182a267') : sr_t('admin::ui.save.active.s3.settings.f145035e') . implode(' ', $s3ConfigErrors)),
    ];

    return $summary;
}

function sr_admin_dashboard_install_protection_summary(array $config): array
{
    $configPath = SR_ROOT . '/config/config.php';
    $lockPath = SR_ROOT . '/storage/installed.lock';
    $summary = [];

    $summary[] = [
        'label' => sr_t('admin::ui.settings.845f5c6c'),
        'value' => is_file($configPath) ? sr_t('admin::ui.text.d73ba417') : sr_t('admin::ui.text.72ea3d64'),
        'state' => is_file($configPath) && is_readable($configPath) ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => is_file($configPath) ? sr_t('admin::ui.config.config.php.7742bcb4') : sr_t('admin::ui.status.settings.c663cd87'),
    ];

    $lockDetail = sr_t('admin::ui.storage.installed.lock.90254df3');
    $lockState = is_file($lockPath) && is_readable($lockPath) ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf');
    $lockValue = is_file($lockPath) ? sr_t('admin::ui.text.d73ba417') : sr_t('admin::ui.text.72ea3d64');
    if (is_file($lockPath) && is_readable($lockPath)) {
        $content = file_get_contents($lockPath);
        $decoded = is_string($content) ? json_decode($content, true) : null;
        if (is_array($decoded)) {
            $installedAt = (string) ($decoded['installed_at'] ?? '');
            $fingerprint = (string) ($decoded['app_fingerprint'] ?? '');
            $expectedFingerprint = substr(hash('sha256', sr_app_key($config)), 0, 16);
            if ($fingerprint !== '' && !hash_equals($expectedFingerprint, $fingerprint)) {
                $lockState = sr_t('admin::ui.text.acc90fbf');
                $lockDetail = sr_t('admin::ui.app.settings.4392d04f');
            } else {
                $lockDetail = sr_t('admin::ui.text.67812880') . ($installedAt !== '' ? $installedAt : sr_t('admin::ui.text.6907dab2')) . ($fingerprint !== '' ? sr_t('admin::ui.fingerprint.09329453') : '');
            }
        } else {
            $lockState = sr_t('admin::ui.text.1aacb54c');
            $lockDetail = sr_t('admin::ui.lock.active.7b974e84');
        }
    } elseif (!is_file($lockPath)) {
        $lockDetail = sr_t('admin::ui.lock.41ea1c4a');
    } else {
        $lockDetail = sr_t('admin::ui.lock.56ddd913');
    }

    $summary[] = [
        'label' => sr_t('admin::ui.lock.96be2723'),
        'value' => $lockValue,
        'state' => $lockState,
        'detail' => $lockDetail,
    ];

    $summary[] = [
        'label' => sr_t('admin::ui.text.c7888f6d'),
        'value' => sr_is_installed() ? sr_t('admin::ui.text.727333ab') : sr_t('admin::ui.text.987c6c1e'),
        'state' => sr_is_installed() ? sr_t('admin::ui.text.35688a85') : sr_t('admin::ui.text.acc90fbf'),
        'detail' => sr_t('admin::ui.config.config.storage.installed.cd679b8f'),
    ];

    return $summary;
}

function sr_admin_dashboard_sensitive_setting_summary(PDO $pdo, array $config): array
{
    $labels = [
        'admin.module_sources_enabled' => sr_t('admin::ui.text.c89ee3b7'),
    ];
    $settings = [];
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value, value_type, updated_at
         FROM sr_site_settings
         WHERE setting_key IN ('admin.module_sources_enabled')
         ORDER BY setting_key ASC"
    );
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = $row;
    }

    $summary = [];
    foreach (sr_admin_sensitive_site_setting_keys() as $settingKey => $_enabled) {
        $row = is_array($settings[$settingKey] ?? null) ? $settings[$settingKey] : null;
        $valueType = is_array($row) ? (string) ($row['value_type'] ?? '') : '';
        if ($settingKey === 'admin.module_sources_enabled') {
            $enabled = is_array($row) && $valueType === 'bool'
                ? (bool) sr_cast_setting_value($row['setting_value'] ?? '', $valueType)
                : sr_module_sources_enabled($pdo, $config);
        } else {
            $enabled = is_array($row) && $valueType === 'bool'
                ? (bool) sr_cast_setting_value($row['setting_value'] ?? '', $valueType)
                : false;
        }
        $state = $enabled ? sr_t('admin::ui.text.acc90fbf') : sr_t('admin::ui.text.35688a85');
        $detail = sr_t('admin::ui.status.a37b0235');

        if (is_array($row) && $valueType !== 'bool') {
            $state = sr_t('admin::ui.text.acc90fbf');
            $detail = sr_t('admin::ui.settings.bool.save.678349be');
        } elseif ($settingKey === 'admin.module_sources_enabled' && $enabled) {
            $detail = sr_runtime_is_production($config)
                ? sr_t('admin::ui.text.c0ad1b1e')
                : sr_t('admin::ui.text.f1259957');
        }

        $summary[] = [
            'label' => (string) ($labels[$settingKey] ?? $settingKey),
            'setting_key' => $settingKey,
            'value' => $enabled ? sr_t('admin::ui.text.d9ba6551') : sr_t('admin::ui.text.ffdbb50e'),
            'state' => $state,
            'updated_at' => is_array($row) ? (string) ($row['updated_at'] ?? '') : '',
            'detail' => $detail,
        ];
    }

    return $summary;
}

function sr_admin_dashboard_mail_transport_ready(string $transport, array $mail): bool
{
    $fromEmail = (string) ($mail['from_email'] ?? '');

    if ($transport === 'php_mail') {
        return function_exists('mail') && ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false);
    }

    if ($transport === 'smtp') {
        return (string) ($mail['host'] ?? '') !== ''
            && (int) ($mail['port'] ?? 0) >= 1
            && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    if ($transport === 'http_api') {
        return sr_mail_http_api_endpoint_is_allowed((string) ($mail['endpoint'] ?? ''))
            && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    return false;
}

function sr_admin_dashboard_recovery_marker(string $filename, string $label): ?array
{
    if (preg_match('/\A[a-z0-9_.-]+\.json\z/', $filename) !== 1) {
        return null;
    }

    $path = SR_ROOT . '/storage/' . $filename;
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $content = file_get_contents($path);
    $decoded = is_string($content) ? json_decode($content, true) : null;
    if (!is_array($decoded)) {
        return [
            'label' => $label,
            'filename' => $filename,
            'recorded_at' => '',
            'stage' => '',
            'message' => sr_t('admin::ui.text.fcd200cb'),
        ];
    }

    return [
        'label' => $label,
        'filename' => $filename,
        'recorded_at' => (string) ($decoded['recorded_at'] ?? ''),
        'stage' => (string) ($decoded['stage'] ?? ''),
        'scope' => (string) ($decoded['scope'] ?? ''),
        'module_key' => (string) ($decoded['module_key'] ?? ''),
        'version' => (string) ($decoded['version'] ?? ''),
        'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($decoded['message'] ?? ''), 500)),
    ];
}

function sr_admin_dashboard_recovery_markers(): array
{
    $recoveryMarkers = [];
    foreach ([
        'install-failed.json' => sr_t('admin::ui.text.19d12d29'),
        'update-failed.json' => sr_t('admin::ui.text.dcf9d8bc'),
    ] as $filename => $label) {
        $marker = sr_admin_dashboard_recovery_marker($filename, $label);
        if (is_array($marker)) {
            $recoveryMarkers[] = $marker;
        }
    }

    return $recoveryMarkers;
}

function sr_admin_dashboard_module_backup_summary(): array
{
    $summary = [
        'count' => 0,
        'latest_name' => '',
        'latest_modified_at' => '',
    ];

    $latestTime = 0;
    foreach (sr_admin_module_backup_dirs() as $directory) {
        $summary['count']++;
        $modifiedAt = filemtime($directory);
        if ($modifiedAt !== false && $modifiedAt > $latestTime) {
            $latestTime = $modifiedAt;
            $summary['latest_name'] = basename($directory);
            $summary['latest_modified_at'] = date('Y-m-d H:i:s', $modifiedAt);
        }
    }

    return $summary;
}
