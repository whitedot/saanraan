<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$commonCssPath = SR_ROOT . '/assets/common.css';
$commonCss = is_file($commonCssPath) ? (string) file_get_contents($commonCssPath) : '';

function sr_admin_design_tokens_token_category(string $name): string
{
    if (str_starts_with($name, '--color-')) {
        return '색상';
    }

    if (str_starts_with($name, '--font-') || str_starts_with($name, '--text-') || str_starts_with($name, '--leading-')) {
        return '타이포그래피';
    }

    if (str_starts_with($name, '--font-weight-')) {
        return '타이포그래피';
    }

    if (str_starts_with($name, '--container-') || $name === '--spacing') {
        return '레이아웃';
    }

    if (str_starts_with($name, '--radius')) {
        return '모서리';
    }

    if (str_starts_with($name, '--shadow') || str_starts_with($name, '--inset-shadow')) {
        return '그림자';
    }

    if (str_contains($name, 'transition') || str_starts_with($name, '--ease-')) {
        return '모션';
    }

    return '기타';
}

function sr_admin_design_tokens_class_category(string $className): string
{
    if ($className === 'btn' || str_starts_with($className, 'btn-') || $className === 'badge' || str_starts_with($className, 'badge-')) {
        return '버튼/배지';
    }

    if (str_starts_with($className, 'form-') || str_starts_with($className, 'input-') || str_starts_with($className, 'password-') || str_starts_with($className, 'validation-')) {
        return '폼';
    }

    if ($className === 'card' || str_starts_with($className, 'card-')) {
        return '카드';
    }

    if ($className === 'table' || str_starts_with($className, 'table-') || $className === 'pagination' || str_starts_with($className, 'page-')) {
        return '테이블/페이지';
    }

    if (str_starts_with($className, 'tab-') || str_starts_with($className, 'nav-')) {
        return '탭/내비게이션';
    }

    if (str_starts_with($className, 'modal-') || str_starts_with($className, 'hs-overlay')) {
        return '모달/오버레이';
    }

    if ($className === 'close-icon' || $className === 'hidden' || $className === 'relative' || $className === 'sr-only' || $className === 'container' || $className === 'container-fluid' || str_starts_with($className, 'animate-') || str_starts_with($className, 'progress-')) {
        return '유틸리티';
    }

    return '기타';
}

function sr_admin_design_tokens_token_groups(string $css): array
{
    preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]+);/', $css, $matches, PREG_SET_ORDER);
    $tokens = [];
    foreach ($matches as $match) {
        $name = trim((string) $match[1]);
        $value = trim((string) $match[2]);
        if ($name === '' || $value === '') {
            continue;
        }

        if (!isset($tokens[$name])) {
            $tokens[$name] = [
                'name' => $name,
                'category' => sr_admin_design_tokens_token_category($name),
                'values' => [],
            ];
        }

        if (!in_array($value, $tokens[$name]['values'], true)) {
            $tokens[$name]['values'][] = $value;
        }
    }

    ksort($tokens, SORT_NATURAL);

    $groups = [];
    foreach ($tokens as $token) {
        $groups[(string) $token['category']][] = $token;
    }

    return $groups;
}

function sr_admin_design_tokens_class_groups(string $css): array
{
    preg_match_all('/(?<![A-Za-z0-9_-])\.([A-Za-z_][A-Za-z0-9_-]*)/', $css, $matches);
    $classes = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    sort($classes, SORT_NATURAL);

    $groups = [];
    foreach ($classes as $className) {
        $groups[sr_admin_design_tokens_class_category($className)][] = $className;
    }

    return $groups;
}

$commonCssTokenGroups = sr_admin_design_tokens_token_groups($commonCss);
$commonCssClassGroups = sr_admin_design_tokens_class_groups($commonCss);
$commonCssTokenCount = array_sum(array_map('count', $commonCssTokenGroups));
$commonCssClassCount = array_sum(array_map('count', $commonCssClassGroups));
$commonCssButtonClasses = array_values(array_filter($commonCssClassGroups['버튼/배지'] ?? [], function (string $className): bool {
    return $className === 'btn' || str_starts_with($className, 'btn-');
}));
$commonCssBadgeClasses = array_values(array_filter($commonCssClassGroups['버튼/배지'] ?? [], function (string $className): bool {
    return $className === 'badge' || str_starts_with($className, 'badge-');
}));
$commonCssFormClasses = $commonCssClassGroups['폼'] ?? [];
$commonCssCardClasses = $commonCssClassGroups['카드'] ?? [];
$commonCssTableClasses = $commonCssClassGroups['테이블/페이지'] ?? [];
$commonCssTabClasses = $commonCssClassGroups['탭/내비게이션'] ?? [];
$commonCssModalClasses = $commonCssClassGroups['모달/오버레이'] ?? [];

include SR_ROOT . '/modules/admin/views/design-tokens.php';
