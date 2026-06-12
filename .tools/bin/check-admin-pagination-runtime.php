#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];
$srPaginationRuntimePerPage = 10;

function sr_admin_pagination_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_admin_pagination_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_admin_pagination_runtime_error($message);
    }
}

if (!function_exists('sr_get_string')) {
    function sr_get_string(string $key, int $maxLength = 255): string
    {
        $value = (string) ($_GET[$key] ?? '');
        return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
    }
}

if (!function_exists('sr_admin_settings')) {
    function sr_admin_settings(PDO $pdo): array
    {
        global $srPaginationRuntimePerPage;
        return ['list_per_page' => $srPaginationRuntimePerPage];
    }
}

if (!function_exists('sr_admin_list_pagination_per_page')) {
    function sr_admin_list_pagination_per_page(array $settings): int
    {
        return max(1, min(100, (int) ($settings['list_per_page'] ?? 20)));
    }
}

if (!function_exists('sr_admin_normalize_query_params')) {
    function sr_admin_normalize_query_params(array $params, string $contextPath): array
    {
        return $params;
    }
}

if (!function_exists('sr_request_path')) {
    function sr_request_path(): string
    {
        return (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    }
}

if (!function_exists('sr_url')) {
    function sr_url(string $path): string
    {
        return $path;
    }
}

if (!function_exists('sr_e')) {
    function sr_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sr_material_icon_html')) {
    function sr_material_icon_html(string $name): string
    {
        return '<span class="material-symbols-outlined">' . sr_e($name) . '</span>';
    }
}

require_once 'modules/admin/helpers/pagination.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$meta = sr_admin_pagination_meta(0, 0, -5, 'p');
sr_admin_pagination_runtime_assert((int) $meta['page'] === 1, 'pagination meta must clamp negative page to 1.');
sr_admin_pagination_runtime_assert((int) $meta['per_page'] === 1, 'pagination meta must clamp invalid per_page to 1.');
sr_admin_pagination_runtime_assert((int) $meta['total_pages'] === 1, 'pagination meta must keep at least one total page.');
sr_admin_pagination_runtime_assert((int) $meta['start'] === 0 && (int) $meta['end'] === 0, 'pagination meta must show empty ranges as 0-0.');

$meta = sr_admin_pagination_meta(95, 10, 99, 'page');
sr_admin_pagination_runtime_assert((int) $meta['page'] === 10, 'pagination meta must clamp page above total_pages.');
sr_admin_pagination_runtime_assert((int) $meta['start'] === 91 && (int) $meta['end'] === 95, 'pagination meta must calculate final page range.');
sr_admin_pagination_runtime_assert(sr_admin_pagination_offset($meta) === 90, 'pagination offset must match clamped page and per_page.');

$_GET = ['page' => 'abc'];
sr_admin_pagination_runtime_assert(sr_admin_page_number_from_request('page') === 1, 'request page parser must reject non-digit values.');
$_GET = ['page' => '0'];
sr_admin_pagination_runtime_assert(sr_admin_page_number_from_request('page') === 1, 'request page parser must clamp zero to 1.');
$_GET = ['page' => '3'];
sr_admin_pagination_runtime_assert(sr_admin_page_number_from_request('page') === 3, 'request page parser must accept positive digits.');

global $srPaginationRuntimePerPage;
$srPaginationRuntimePerPage = 5;
$_GET = ['page' => '3'];
$paginated = sr_admin_paginate_array($pdo, range(1, 14), 'page');
sr_admin_pagination_runtime_assert(($paginated['rows'] ?? []) === [11, 12, 13, 14], 'array pagination must return the clamped page slice.');
sr_admin_pagination_runtime_assert((int) ($paginated['pagination']['total'] ?? 0) === 14, 'array pagination must preserve total row count.');
sr_admin_pagination_runtime_assert((int) ($paginated['pagination']['total_pages'] ?? 0) === 3, 'array pagination must calculate total pages.');

$_GET = ['page' => '99'];
$fromTotal = sr_admin_pagination_from_total($pdo, 14, 'page');
sr_admin_pagination_runtime_assert((int) $fromTotal['page'] === 3, 'pagination_from_total must clamp request page to final page.');
sr_admin_pagination_runtime_assert(sr_admin_pagination_offset($fromTotal) === 10, 'pagination_from_total offset must match final page.');

$_SERVER['REQUEST_URI'] = '/admin/items?page=3&q=hello&status=active';
sr_admin_pagination_runtime_assert(sr_admin_pagination_url(2, 'page') === '/admin/items?q=hello&status=active&page=2', 'pagination URL must preserve filters and replace page.');
sr_admin_pagination_runtime_assert(sr_admin_pagination_url(1, 'page') === '/admin/items?q=hello&status=active', 'pagination URL must omit page parameter for page 1.');

$items = sr_admin_pagination_items(50, 100, 1);
$pageItems = array_values(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'page'));
sr_admin_pagination_runtime_assert(($pageItems[0]['page'] ?? null) === 1, 'pagination items must include first page.');
sr_admin_pagination_runtime_assert(($pageItems[count($pageItems) - 1]['page'] ?? null) === 100, 'pagination items must include last page.');
sr_admin_pagination_runtime_assert(count(array_filter($items, static fn (array $item): bool => ($item['type'] ?? '') === 'gap')) === 2, 'pagination items must include expected gap markers.');

$summary = sr_admin_pagination_summary_html($fromTotal);
sr_admin_pagination_runtime_assert(str_contains($summary, '전체 <strong>14</strong>건 중 11-14건 표시'), 'pagination summary must show clamped range.');
$html = sr_admin_pagination_html($fromTotal, '테스트 목록');
sr_admin_pagination_runtime_assert(str_contains($html, 'aria-current="page">3</span>'), 'pagination HTML must mark current page.');
sr_admin_pagination_runtime_assert(str_contains($html, 'aria-disabled="true" aria-label="다음 페이지"'), 'pagination HTML must disable next on final page.');
sr_admin_pagination_runtime_assert(str_contains($html, 'aria-label="테스트 목록"'), 'pagination HTML must include navigation label.');

if ($errors !== []) {
    fwrite(STDERR, "admin pagination runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "admin pagination runtime checks completed.\n";
