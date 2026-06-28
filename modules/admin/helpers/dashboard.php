<?php

declare(strict_types=1);

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
