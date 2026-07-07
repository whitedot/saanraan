<?php

declare(strict_types=1);

function sr_admin_action_result(array $errors = [], string $notice = '', array $data = []): array
{
    return [
        'errors' => array_values(array_map('strval', $errors)),
        'notice' => $notice,
        'data' => $data,
    ];
}

function sr_admin_flash_result(array $result): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $_SESSION['sr_admin_action_result'] = sr_admin_action_result(
        isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [],
        (string) ($result['notice'] ?? ''),
        isset($result['data']) && is_array($result['data']) ? $result['data'] : []
    );
}

function sr_admin_pop_flash_result(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return sr_admin_action_result();
    }

    $result = $_SESSION['sr_admin_action_result'] ?? null;
    unset($_SESSION['sr_admin_action_result']);

    if (!is_array($result)) {
        return sr_admin_action_result();
    }

    return sr_admin_action_result(
        isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : [],
        (string) ($result['notice'] ?? ''),
        isset($result['data']) && is_array($result['data']) ? $result['data'] : []
    );
}

function sr_admin_current_get_url(string $fallback = '/admin'): string
{
    $path = sr_request_path();
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $query = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
    $target = $path . ($query !== '' ? '?' . $query : '');

    return sr_is_safe_relative_url($target) ? $target : $fallback;
}

function sr_admin_get_route_exists(string $path): bool
{
    $path = (string) (parse_url($path, PHP_URL_PATH) ?: '');
    if ($path === '' || $path[0] !== '/') {
        return false;
    }

    foreach (glob(SR_ROOT . '/modules/*/paths.php') ?: [] as $pathsFile) {
        $paths = require $pathsFile;
        if (!is_array($paths)) {
            continue;
        }

        foreach ($paths as $route => $_actionRelativePath) {
            $route = (string) $route;
            if (!str_starts_with($route, 'GET ')) {
                continue;
            }

            $routePath = substr($route, 4);
            if ($routePath === $path) {
                return true;
            }

            if (str_ends_with($routePath, '/*')) {
                $prefix = substr($routePath, 0, -1);
                if ($prefix !== '' && str_starts_with($path, $prefix) && strlen($path) > strlen($prefix)) {
                    return true;
                }
            }
        }
    }

    return false;
}

function sr_admin_safe_get_url(string $url, string $fallback = '/admin'): string
{
    if (sr_is_safe_relative_url($url)) {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        if (sr_admin_get_route_exists($path)) {
            return $url;
        }
    }

    if (sr_is_safe_relative_url($fallback)) {
        $fallbackPath = (string) (parse_url($fallback, PHP_URL_PATH) ?: '');
        if (sr_admin_get_route_exists($fallbackPath)) {
            return $fallback;
        }
    }

    return '/admin';
}

function sr_admin_post_return_url(string $fallback = '/admin'): string
{
    return sr_admin_safe_get_url(sr_post_string('return_to', 500), $fallback);
}

function sr_admin_redirect_with_result(array $result, string $fallback = '/admin'): void
{
    sr_admin_flash_result($result);
    $fallbackUrl = sr_admin_safe_get_url($fallback, '/admin');
    $currentUrl = sr_admin_current_get_url($fallbackUrl);
    $currentPath = (string) (parse_url($currentUrl, PHP_URL_PATH) ?: '');
    if (!sr_admin_get_route_exists($currentPath)) {
        $currentUrl = $fallbackUrl;
    }

    sr_redirect($currentUrl);
}

function sr_admin_feedback_toasts(string $notice = '', array $errors = []): string
{
    $items = [];
    if ($notice !== '') {
        $items[] = [
            'type' => 'success',
            'title' => sr_t('admin::feedback.success_title'),
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
            'title' => sr_t('admin::feedback.error_title'),
            'message' => $message,
        ];
    }

    if ($items === []) {
        return '';
    }

    ob_start();
    ?>
    <div data-admin-toast-stack role="status" aria-live="polite" aria-atomic="false">
        <?php foreach ($items as $item) { ?>
            <div class="alert-removable alert <?php echo (string) $item['type'] === 'success' ? 'alert-success' : 'alert-danger'; ?> admin-flash-message admin-flash-message-<?php echo sr_e((string) $item['type']); ?>" data-admin-toast>
                <strong><?php echo sr_e((string) $item['title']); ?></strong>
                <span><?php echo sr_e((string) $item['message']); ?></span>
                <button type="button" class="btn btn-sm btn-icon" data-admin-toast-close aria-label="<?php echo sr_e(sr_t('admin::feedback.close_label')); ?>">
                    <?php echo sr_material_icon_html('close', 'admin-toast-close-icon'); ?>
                </button>
            </div>
        <?php } ?>
    </div>
    <?php
    return (string) ob_get_clean();
}
