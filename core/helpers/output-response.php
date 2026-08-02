<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_is_safe_module_action(string $path): bool
{
    if ($path === '' || strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }

    return preg_match('/\Aactions\/[a-z0-9_\-\/]+\.php\z/', $path) === 1;
}

function sr_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sr_public_feedback_toasts(string $namespace, string $notice = '', array $errors = []): string
{
    if (preg_match('/\A[a-z][a-z0-9_-]*\z/', $namespace) !== 1) {
        return '';
    }

    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'message' => $notice,
        ];
    }

    foreach ($errors as $error) {
        $message = trim((string) $error);
        if ($message === '') {
            continue;
        }

        $items[] = [
            'type' => 'error',
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="<?php echo sr_e($namespace); ?>-toast-stack" data-<?php echo sr_e($namespace); ?>-toast-stack role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="alert-removable alert <?php echo (string) $item['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?> <?php echo sr_e($namespace); ?>-toast <?php echo sr_e($namespace); ?>-toast-<?php echo sr_e((string) $item['type']); ?>" data-<?php echo sr_e($namespace); ?>-toast data-sr-public-toast role="<?php echo (string) $item['type'] === 'error' ? 'alert' : 'status'; ?>">
                <?php echo sr_e((string) $item['message']); ?>
                <button type="button" class="btn btn-sm btn-ghost-default btn-icon alert-close-leading <?php echo sr_e($namespace); ?>-toast-close" data-<?php echo sr_e($namespace); ?>-toast-close data-sr-public-toast-close aria-label="<?php echo sr_e('닫기'); ?>">
                    <?php echo sr_material_icon_html('close', $namespace . '-toast-close-icon'); ?>
                </button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function sr_public_pagination_html(
    array $pagination,
    string $basePath,
    string $label,
    string $pageParam = 'page',
    string $anchor = '',
    string $className = 'public-pagination',
    array $options = []
): string {
    $page = max(1, (int) ($pagination['page'] ?? 1));
    $totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
    if ($totalPages <= 1) {
        return '';
    }

    $pageParam = preg_match('/\A[a-z][a-z0-9_]*\z/', $pageParam) === 1 ? $pageParam : 'page';
    $className = preg_match('/\A[a-z][a-z0-9_-]*\z/', $className) === 1 ? $className : 'public-pagination';
    $anchor = preg_match('/\A[a-z][a-z0-9_-]*\z/', $anchor) === 1 ? $anchor : '';
    $urlForPage = static function (int $targetPage) use ($basePath, $pageParam, $anchor): string {
        $separator = str_contains($basePath, '?') ? '&' : '?';
        $url = $basePath . $separator . rawurlencode($pageParam) . '=' . rawurlencode((string) max(1, $targetPage));
        if ($anchor !== '') {
            $url .= '#' . rawurlencode($anchor);
        }

        return sr_url($url);
    };

    $normalizeItemClass = static function (mixed $value): string {
        $value = is_string($value) ? trim($value) : '';
        return $value !== '' && preg_match('/\A[a-zA-Z0-9_-]+(?:\s+[a-zA-Z0-9_-]+)*\z/', $value) === 1 ? $value : '';
    };
    $linkClass = $normalizeItemClass($options['link_class'] ?? '');
    $currentClass = $normalizeItemClass($options['current_class'] ?? '');
    $compactEdges = !empty($options['compact_edges']);
    $pageNumbers = [];
    if ($compactEdges) {
        $pageNumbers = [1 => 1, $totalPages => $totalPages];
        for ($pageNumber = max(1, $page - 2); $pageNumber <= min($totalPages, $page + 2); $pageNumber++) {
            $pageNumbers[$pageNumber] = $pageNumber;
        }
        ksort($pageNumbers);
    } else {
        $startPage = max(1, min($page - 2, $totalPages - 4));
        $endPage = min($totalPages, max($page + 2, 5));
        for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++) {
            $pageNumbers[$pageNumber] = $pageNumber;
        }
    }
    ob_start();
    ?>
    <nav class="<?php echo sr_e($className); ?>" aria-label="<?php echo sr_e($label); ?>">
        <?php if (!$compactEdges && $page > 1) { ?>
            <a<?php echo $linkClass !== '' ? ' class="' . sr_e($linkClass) . '"' : ''; ?> href="<?php echo sr_e($urlForPage($page - 1)); ?>" rel="prev">이전</a>
        <?php } ?>
        <?php $previousPageNumber = 0; ?>
        <?php foreach ($pageNumbers as $pageNumber) { ?>
            <?php if ($compactEdges && $previousPageNumber > 0 && $pageNumber > $previousPageNumber + 1) { ?>
                <span class="<?php echo sr_e($className . '-gap'); ?>" aria-hidden="true">…</span>
            <?php } ?>
            <?php if ($pageNumber === $page) { ?>
                <span<?php echo $currentClass !== '' ? ' class="' . sr_e($currentClass) . '"' : ''; ?> aria-current="page"><?php echo sr_e((string) $pageNumber); ?></span>
            <?php } else { ?>
                <a<?php echo $linkClass !== '' ? ' class="' . sr_e($linkClass) . '"' : ''; ?> href="<?php echo sr_e($urlForPage($pageNumber)); ?>"><?php echo sr_e((string) $pageNumber); ?></a>
            <?php } ?>
            <?php $previousPageNumber = $pageNumber; ?>
        <?php } ?>
        <?php if (!$compactEdges && $page < $totalPages) { ?>
            <a<?php echo $linkClass !== '' ? ' class="' . sr_e($linkClass) . '"' : ''; ?> href="<?php echo sr_e($urlForPage($page + 1)); ?>" rel="next">다음</a>
        <?php } ?>
    </nav>
    <?php
    return (string) ob_get_clean();
}

function sr_time_tooltip_html(?string $value, string $label, string $emptyText = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return sr_e($emptyText);
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $exactValue = date('Y-m-d H:i:s', $timestamp);
    $machineValue = date('Y-m-d\TH:i:sP', $timestamp);

    return '<time class="sr-time-tooltip" datetime="' . sr_e($machineValue) . '" tabindex="0" data-sr-time-tooltip data-sr-time-tooltip-label="' . sr_e($exactValue) . '" aria-label="' . sr_e('정확한 일시: ' . $exactValue) . '">'
        . sr_e($label)
        . '</time>';
}

function sr_relative_time_html(?string $value, string $emptyText = ''): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return sr_e($emptyText);
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $exactValue = date('Y-m-d H:i:s', $timestamp);
    return sr_time_tooltip_html($exactValue, sr_relative_time_label($exactValue));
}

function sr_json_response(mixed $payload, int $statusCode = 200, array $headers = []): void
{
    if ($statusCode !== 200) {
        http_response_code($statusCode);
    }

    header('Content-Type: application/json; charset=utf-8');
    foreach ($headers as $header) {
        if (is_string($header) && sr_response_header_is_allowed($header)) {
            header($header);
        }
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_string($encoded)) {
        http_response_code(500);
        $encoded = '{"ok":false,"message":"JSON response encoding failed."}';
    }

    echo $encoded;
    sr_finish_response();
}

function sr_js_json_encode(mixed $value): string
{
    $encoded = json_encode(
        $value,
        JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
            | JSON_INVALID_UTF8_SUBSTITUTE
    );

    return is_string($encoded) ? $encoded : 'null';
}

function sr_response_header_is_allowed(string $header): bool
{
    if (preg_match('/[\x00-\x1F\x7F]/', $header) === 1) {
        return false;
    }

    $header = trim($header);
    $colonPosition = strpos($header, ':');
    if ($colonPosition === false) {
        return false;
    }

    $name = strtolower(trim(substr($header, 0, $colonPosition)));
    $value = trim(substr($header, $colonPosition + 1));
    if ($name === '' || $value === '') {
        return false;
    }

    if ($name === 'cache-control') {
        return sr_download_cache_control($value) === $value;
    }

    if ($name === 'content-disposition') {
        return preg_match('/\A(?:attachment|inline);\s*filename="[A-Za-z0-9._-]{1,120}"\z/', $value) === 1;
    }

    if ($name === 'content-length') {
        return preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/', $value) === 1;
    }

    if ($name === 'content-security-policy') {
        return preg_match('/\A[A-Za-z][A-Za-z0-9-]*(?:\s+[^,;]+)?(?:;\s*[A-Za-z][A-Za-z0-9-]*(?:\s+[^,;]+)?)*;?\z/', $value) === 1;
    }

    if ($name === 'content-type') {
        return sr_download_content_type($value) === $value;
    }

    if ($name === 'etag') {
        return preg_match('/\A"(?:[A-Fa-f0-9]{32}|[A-Fa-f0-9]{40}|[A-Fa-f0-9]{64})"\z/', $value) === 1;
    }

    if ($name === 'last-modified') {
        return preg_match('/\A(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{2} (?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) \d{4} \d{2}:\d{2}:\d{2} GMT\z/', $value) === 1
            && strtotime($value) !== false;
    }

    if ($name === 'pragma') {
        return strtolower($value) === 'no-cache';
    }

    if ($name === 'x-content-type-options') {
        return strtolower($value) === 'nosniff';
    }

    return false;
}

function sr_http_date(int $timestamp): string
{
    return gmdate('D, d M Y H:i:s', max(0, $timestamp)) . ' GMT';
}

function sr_etag_matches(string $ifNoneMatch, string $etag): bool
{
    $ifNoneMatch = trim($ifNoneMatch);
    if ($ifNoneMatch === '') {
        return false;
    }

    if ($ifNoneMatch === '*') {
        return true;
    }

    foreach (explode(',', $ifNoneMatch) as $candidate) {
        $candidate = trim($candidate);
        if (str_starts_with($candidate, 'W/')) {
            $candidate = trim(substr($candidate, 2));
        }
        if ($candidate === $etag) {
            return true;
        }
    }

    return false;
}

function sr_send_file_cache_headers(string $cacheControl, string $etag, int $lastModified): void
{
    header('Cache-Control: ' . sr_download_cache_control($cacheControl));
    header('ETag: ' . $etag);
    header('Last-Modified: ' . sr_http_date($lastModified));
}

function sr_file_not_modified(string $etag, int $lastModified): bool
{
    $ifNoneMatch = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($ifNoneMatch !== '') {
        return sr_etag_matches($ifNoneMatch, $etag);
    }

    $ifModifiedSince = (string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '');
    if ($ifModifiedSince === '') {
        return false;
    }

    $modifiedSince = strtotime($ifModifiedSince);
    return is_int($modifiedSince) && $modifiedSince >= $lastModified;
}
