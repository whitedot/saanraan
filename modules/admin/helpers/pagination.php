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
