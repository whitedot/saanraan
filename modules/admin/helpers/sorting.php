<?php

declare(strict_types=1);

function sr_admin_sort_default(string $key, string $dir = 'desc'): array
{
    return [
        'key' => $key,
        'dir' => strtolower($dir) === 'asc' ? 'asc' : 'desc',
    ];
}

function sr_admin_sort_from_request(array $options, array $defaultSort, string $sortParam = 'sort', string $dirParam = 'dir'): array
{
    $defaultKey = (string) ($defaultSort['key'] ?? '');
    $defaultDir = strtolower((string) ($defaultSort['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $sortKey = sr_get_string($sortParam, 60);
    $sortDir = strtolower(sr_get_string($dirParam, 10));

    if (!isset($options[$sortKey])) {
        $sortKey = $defaultKey;
        $sortDir = $defaultDir;
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = $defaultDir;
    }

    return [
        'key' => $sortKey,
        'dir' => $sortDir,
        'is_default' => $sortKey === $defaultKey && $sortDir === $defaultDir,
        'sort_param' => $sortParam,
        'dir_param' => $dirParam,
    ];
}

function sr_admin_sort_order_sql(array $options, array $sort, array $defaultSort): string
{
    $defaultKey = (string) ($defaultSort['key'] ?? '');
    $defaultDir = strtolower((string) ($defaultSort['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $sortKey = (string) ($sort['key'] ?? $defaultKey);
    $sortDir = strtolower((string) ($sort['dir'] ?? $defaultDir));

    if (!isset($options[$sortKey])) {
        $sortKey = $defaultKey;
        $sortDir = $defaultDir;
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = $defaultDir;
    }

    $orderParts = [];
    foreach ((array) ($options[$sortKey]['columns'] ?? []) as $column) {
        $orderParts[] = (string) $column . ' ' . strtoupper($sortDir);
    }

    if ($orderParts === []) {
        return '';
    }

    return ' ORDER BY ' . implode(', ', $orderParts);
}

function sr_admin_sort_url(array $options, array $defaultSort, string $sortKey = '', string $sortDir = '', string $sortParam = 'sort', string $dirParam = 'dir', string $pageParam = 'page'): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $contextPath = function_exists('sr_request_path') ? sr_request_path() : $path;
    $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
    $params = [];
    if ($queryString !== '') {
        parse_str($queryString, $params);
        if (!is_array($params)) {
            $params = [];
        }
        $params = sr_admin_normalize_query_params($params, $contextPath);
    }

    unset($params[$pageParam], $params[$sortParam], $params[$dirParam]);

    $defaultKey = (string) ($defaultSort['key'] ?? '');
    $defaultDir = strtolower((string) ($defaultSort['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
    $sortDir = strtolower($sortDir);
    if (isset($options[$sortKey]) && in_array($sortDir, ['asc', 'desc'], true)) {
        if ($sortKey !== $defaultKey || $sortDir !== $defaultDir) {
            $params[$sortParam] = $sortKey;
            $params[$dirParam] = $sortDir;
        }
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return sr_url($path . ($query !== '' ? '?' . $query : ''));
}

function sr_admin_sort_header_html(string $label, string $sortKey, array $currentSort, array $options, array $defaultSort, string $sortParam = 'sort', string $dirParam = 'dir', string $pageParam = 'page'): string
{
    $currentKey = (string) ($currentSort['key'] ?? '');
    $currentDir = (string) ($currentSort['dir'] ?? 'desc');
    $isCurrent = $currentKey === $sortKey;
    $ascActive = $isCurrent && $currentDir === 'asc';
    $descActive = $isCurrent && $currentDir === 'desc';

    $ascClass = 'btn btn-sm admin-sort-button btn-group-start ' . ($ascActive ? 'btn-solid-primary' : 'btn-solid-light');
    $descClass = 'btn btn-sm admin-sort-button btn-group-end ' . ($descActive ? 'btn-solid-primary' : 'btn-solid-light');
    $ascLabel = $label . ' 오름차순 정렬';
    $descLabel = $label . ' 내림차순 정렬';

    return '<div class="admin-sort-header">'
        . '<span class="admin-sort-label">' . sr_e($label) . '</span>'
        . '<span class="admin-sort-button-group" role="group" aria-label="' . sr_e($label . ' 정렬 방향') . '">'
        . '<a href="' . sr_e(sr_admin_sort_url($options, $defaultSort, $sortKey, 'asc', $sortParam, $dirParam, $pageParam)) . '" class="' . sr_e($ascClass) . '" aria-label="' . sr_e($ascLabel) . '" title="' . sr_e($ascLabel) . '"' . ($ascActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_upward') . '</a>'
        . '<a href="' . sr_e(sr_admin_sort_url($options, $defaultSort, $sortKey, 'desc', $sortParam, $dirParam, $pageParam)) . '" class="' . sr_e($descClass) . '" aria-label="' . sr_e($descLabel) . '" title="' . sr_e($descLabel) . '"' . ($descActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_downward') . '</a>'
        . '</span>'
        . ($isCurrent ? '<span class="sr-only">현재 ' . sr_e($currentDir === 'asc' ? '오름차순' : '내림차순') . '</span>' : '')
        . '</div>';
}

function sr_admin_sort_aria(string $sortKey, array $currentSort): string
{
    if ((string) ($currentSort['key'] ?? '') !== $sortKey) {
        return '';
    }

    return (string) ($currentSort['dir'] ?? 'desc') === 'asc' ? ' aria-sort="ascending"' : ' aria-sort="descending"';
}

function sr_admin_asset_balance_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'b.account_id']],
        'status' => ['columns' => ['a.status', 'b.account_id']],
        'balance' => ['columns' => ['b.balance', 'b.account_id']],
        'updated_at' => ['columns' => ['b.updated_at', 'b.account_id']],
    ];
}

function sr_admin_asset_balance_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_admin_asset_transaction_sort_options(): array
{
    return [
        'member' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 't.id']],
        'transaction_type' => ['columns' => ['t.transaction_type', 't.id']],
        'amount' => ['columns' => ['t.amount', 't.id']],
        'balance_after' => ['columns' => ['t.balance_after', 't.id']],
        'reason' => ['columns' => ['t.reason', 't.id']],
        'reference_type' => ['columns' => ['t.reference_type', 't.reference_id', 't.id']],
        'created_at' => ['columns' => ['t.created_at', 't.id']],
    ];
}

function sr_admin_asset_transaction_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_admin_logo_sort_options(): array
{
    return [
        'position_key' => ['columns' => ['position_key', 'sort_order', 'id']],
        'title' => ['columns' => ['title', 'id']],
        'status' => ['columns' => ['status', 'id']],
        'starts_at' => ['columns' => ['starts_at', 'ends_at', 'id']],
        'ends_at' => ['columns' => ['ends_at', 'starts_at', 'id']],
        'duration' => ['columns' => ["CASE WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, starts_at, ends_at) ELSE 2147483647 END", 'id']],
        'sort_order' => ['columns' => ['sort_order', 'id']],
        'created_at' => ['columns' => ['created_at', 'id']],
    ];
}

function sr_admin_logo_default_sort(): array
{
    return sr_admin_sort_default('position_key', 'asc');
}

function sr_admin_audit_log_sort_options(): array
{
    return [
        'created_at' => ['columns' => ['created_at', 'id']],
        'event_type' => ['columns' => ['event_type', 'id']],
        'target_type' => ['columns' => ['target_type', 'target_id', 'id']],
        'result' => ['columns' => ['result', 'id']],
        'ip_address' => ['columns' => ['ip_address', 'id']],
    ];
}

function sr_admin_audit_log_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_admin_permission_account_sort_options(): array
{
    return [
        'email' => ['columns' => ['a.email', 'a.id']],
        'display_name' => ['columns' => ["COALESCE(a.display_name, '')", 'a.email', 'a.id']],
        'status' => ['columns' => ['a.status', 'a.id']],
        'permission_count' => ['columns' => ['owner_count', 'permission_count', 'a.id']],
        'created_at' => ['columns' => ['a.id']],
    ];
}

function sr_admin_permission_account_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}
