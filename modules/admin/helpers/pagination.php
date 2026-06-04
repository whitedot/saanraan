<?php

declare(strict_types=1);

function sr_admin_pagination_items(int $currentPage, int $totalPages, int $siblingCount = 2): array
{
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($totalPages, $currentPage));
    $siblingCount = max(0, min(5, $siblingCount));

    $visiblePages = [
        1 => true,
        $totalPages => true,
    ];

    for ($page = $currentPage - $siblingCount; $page <= $currentPage + $siblingCount; $page++) {
        if ($page >= 1 && $page <= $totalPages) {
            $visiblePages[$page] = true;
        }
    }

    ksort($visiblePages);

    $items = [];
    $previousPage = 0;
    foreach (array_keys($visiblePages) as $page) {
        if ($previousPage > 0 && $page > $previousPage + 1) {
            $items[] = [
                'type' => 'gap',
            ];
        }

        $items[] = [
            'type' => 'page',
            'page' => $page,
            'current' => $page === $currentPage,
        ];
        $previousPage = $page;
    }

    return $items;
}

function sr_admin_pagination_group_class(int $index, int $count): string
{
    if ($count <= 1) {
        return '';
    }

    if ($index <= 0) {
        return 'btn-group-start';
    }

    if ($index >= $count - 1) {
        return 'btn-group-end';
    }

    return 'btn-group-middle';
}

function sr_admin_page_number_from_request(string $param = 'page'): int
{
    $page = sr_get_string($param, 20);
    if (!ctype_digit($page)) {
        return 1;
    }

    return max(1, (int) $page);
}

function sr_admin_pagination_meta(int $total, int $perPage, int $page, string $pageParam = 'page'): array
{
    $total = max(0, $total);
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($totalPages, $page));
    $start = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
    $end = $total > 0 ? min($total, $page * $perPage) : 0;

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'start' => $start,
        'end' => $end,
        'page_param' => $pageParam,
    ];
}

function sr_admin_paginate_array(PDO $pdo, array $rows, string $pageParam = 'page'): array
{
    $adminSettings = sr_admin_settings($pdo);
    $perPage = sr_admin_list_pagination_per_page($adminSettings);
    $pagination = sr_admin_pagination_meta(count($rows), $perPage, sr_admin_page_number_from_request($pageParam), $pageParam);
    $offset = ((int) $pagination['page'] - 1) * (int) $pagination['per_page'];

    return [
        'rows' => array_slice($rows, $offset, (int) $pagination['per_page']),
        'pagination' => $pagination,
    ];
}

function sr_admin_pagination_from_total(PDO $pdo, int $total, string $pageParam = 'page'): array
{
    $adminSettings = sr_admin_settings($pdo);

    return sr_admin_pagination_meta($total, sr_admin_list_pagination_per_page($adminSettings), sr_admin_page_number_from_request($pageParam), $pageParam);
}

function sr_admin_pagination_offset(array $pagination): int
{
    return max(0, ((int) ($pagination['page'] ?? 1) - 1) * (int) ($pagination['per_page'] ?? 1));
}

function sr_admin_pagination_url(int $page, string $pageParam = 'page'): string
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

    unset($params[$pageParam]);
    if ($page > 1) {
        $params[$pageParam] = (string) $page;
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return sr_url($path . ($query !== '' ? '?' . $query : ''));
}

function sr_admin_pagination_summary_html(array $pagination): string
{
    $total = max(0, (int) ($pagination['total'] ?? 0));
    $start = max(0, (int) ($pagination['start'] ?? 0));
    $end = max(0, (int) ($pagination['end'] ?? 0));

    if ($total <= 0) {
        return '<div class="admin-list-summary"><span>전체 <strong>0</strong>건</span></div>';
    }

    return '<div class="admin-list-summary"><span>전체 <strong>'
        . sr_e((string) $total)
        . '</strong>건 중 '
        . sr_e((string) $start)
        . '-'
        . sr_e((string) $end)
        . '건 표시</span></div>';
}

function sr_admin_pagination_html(array $pagination, string $label): string
{
    $page = max(1, (int) ($pagination['page'] ?? 1));
    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
    if ($totalPages <= 1) {
        return '';
    }

    $pageParam = (string) ($pagination['page_param'] ?? 'page');
    if ($pageParam === '') {
        $pageParam = 'page';
    }

    $items = sr_admin_pagination_items($page, $totalPages, 2);
    $controlCount = count($items) + 4;
    $index = 0;
    $html = '<nav class="admin-pagination" aria-label="' . sr_e($label) . '">';
    $html .= '<div class="admin-pagination-group" role="group" aria-label="' . sr_e($label . ' 이동') . '">';

    $html .= sr_admin_pagination_control_html($page > 1, 1, $pageParam, $index, $controlCount, 'keyboard_double_arrow_left', '처음 페이지');
    $index++;
    $html .= sr_admin_pagination_control_html($page > 1, $page - 1, $pageParam, $index, $controlCount, 'chevron_left', '이전 페이지');
    $index++;

    foreach ($items as $item) {
        $groupClass = sr_admin_pagination_group_class($index, $controlCount);
        if (($item['type'] ?? '') === 'gap') {
            $html .= '<span class="btn btn-sm btn-solid-light admin-pagination-gap ' . sr_e($groupClass) . '" aria-hidden="true">...</span>';
        } else {
            $itemPage = max(1, (int) ($item['page'] ?? 1));
            if (!empty($item['current'])) {
                $html .= '<span class="btn btn-sm btn-solid-primary admin-pagination-page ' . sr_e($groupClass) . '" aria-current="page">' . sr_e((string) $itemPage) . '</span>';
            } else {
                $html .= '<a href="' . sr_e(sr_admin_pagination_url($itemPage, $pageParam)) . '" class="btn btn-sm btn-solid-light admin-pagination-page ' . sr_e($groupClass) . '" aria-label="' . sr_e((string) $itemPage) . '페이지">' . sr_e((string) $itemPage) . '</a>';
            }
        }
        $index++;
    }

    $html .= sr_admin_pagination_control_html($page < $totalPages, $page + 1, $pageParam, $index, $controlCount, 'chevron_right', '다음 페이지');
    $index++;
    $html .= sr_admin_pagination_control_html($page < $totalPages, $totalPages, $pageParam, $index, $controlCount, 'keyboard_double_arrow_right', '마지막 페이지');

    $html .= '</div></nav>';

    return $html;
}

function sr_admin_pagination_control_html(bool $enabled, int $page, string $pageParam, int $index, int $controlCount, string $icon, string $label): string
{
    $groupClass = sr_admin_pagination_group_class($index, $controlCount);
    if ($enabled) {
        return '<a href="' . sr_e(sr_admin_pagination_url($page, $pageParam)) . '" class="btn btn-sm btn-icon btn-solid-light ' . sr_e($groupClass) . '" aria-label="' . sr_e($label) . '" title="' . sr_e($label) . '">' . sr_material_icon_html($icon) . '</a>';
    }

    return '<span class="btn btn-sm btn-icon btn-solid-light ' . sr_e($groupClass) . '" aria-disabled="true" aria-label="' . sr_e($label) . '">' . sr_material_icon_html($icon) . '</span>';
}
