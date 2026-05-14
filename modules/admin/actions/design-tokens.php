<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$commonCssPath = SR_ROOT . '/assets/common.css';
$commonCss = is_file($commonCssPath) ? (string) file_get_contents($commonCssPath) : '';

function sr_admin_design_tokens_reference_path(): string
{
    $candidates = [
        dirname(SR_ROOT) . '/ui-kit/css/common.css',
        dirname(SR_ROOT) . '/chmedical/docs/ui-kit/css/common.css',
        dirname(SR_ROOT) . '/chmedical.git/docs/ui-kit/css/common.css',
        SR_ROOT . '/docs/ui-kit/css/common.css',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return '';
}

function sr_admin_design_tokens_normalize_values(array $values): array
{
    $normalized = array_values(array_unique(array_map(static function ($value): string {
        return preg_replace('/\s+/', ' ', trim((string) $value)) ?? trim((string) $value);
    }, $values)));
    sort($normalized, SORT_NATURAL);

    return $normalized;
}

function sr_admin_design_tokens_add_value(array &$target, string $key, string $value): void
{
    $value = trim($value);
    if ($key === '' || $value === '') {
        return;
    }

    if (!isset($target[$key])) {
        $target[$key] = [];
    }

    if (!in_array($value, $target[$key], true)) {
        $target[$key][] = $value;
    }
}

function sr_admin_design_tokens_rule_selector(string $css, int $offset): string
{
    $beforeDeclaration = substr($css, 0, $offset);
    $bracePosition = strrpos($beforeDeclaration, '{');
    if ($bracePosition === false) {
        return '';
    }

    $beforeBrace = substr($css, 0, $bracePosition);
    $lastClose = strrpos($beforeBrace, '}');
    $lastOpen = strrpos($beforeBrace, '{');
    $selectorStart = max($lastClose === false ? -1 : $lastClose, $lastOpen === false ? -1 : $lastOpen) + 1;

    return trim(substr($css, $selectorStart, $bracePosition - $selectorStart));
}

function sr_admin_design_tokens_token_category(string $name): string
{
    if (str_starts_with($name, '--color-')) {
        return '색상';
    }

    if (str_starts_with($name, '--font-') || str_starts_with($name, '--text-') || str_starts_with($name, '--leading-')) {
        return '타이포그래피';
    }

    if (str_starts_with($name, '--spacing') || str_starts_with($name, '--container-')) {
        return '간격과 레이아웃';
    }

    if (str_starts_with($name, '--radius')) {
        return '모서리';
    }

    if (str_starts_with($name, '--shadow') || str_starts_with($name, '--inset-shadow')) {
        return '그림자';
    }

    if (str_contains($name, 'transition') || str_contains($name, 'duration') || str_starts_with($name, '--ease-')) {
        return '모션';
    }

    if (str_starts_with($name, '--tw-')) {
        return '내부 속성';
    }

    return '기타';
}

function sr_admin_design_tokens_class_category(string $className): string
{
    if ($className === 'btn' || str_starts_with($className, 'btn-')) {
        return '버튼';
    }

    if ($className === 'badge' || str_starts_with($className, 'badge-')) {
        return '배지';
    }

    if ($className === 'hint-text' || $className === 'validation-icon') {
        return '유효성 및 피드백';
    }

    if (str_starts_with($className, 'form-') || str_starts_with($className, 'input-') || str_starts_with($className, 'password-') || str_starts_with($className, 'ui-form-') || str_starts_with($className, 'ui-floating-') || str_starts_with($className, 'af-')) {
        return '폼 컨트롤';
    }

    if ($className === 'card' || str_starts_with($className, 'card-')) {
        return '카드';
    }

    if ($className === 'table' || str_starts_with($className, 'table-') || $className === 'pagination' || str_starts_with($className, 'page-')) {
        return '테이블과 페이지네이션';
    }

    if ($className === 'nav-tabs' || $className === 'nav-link' || str_starts_with($className, 'nav-link-') || str_starts_with($className, 'tab-')) {
        return '탭과 내비게이션';
    }

    if ($className === 'dropdown' || str_starts_with($className, 'dropdown-') || str_starts_with($className, 'hs-dropdown')) {
        return '드롭다운';
    }

    if (str_starts_with($className, 'modal-') || str_starts_with($className, 'hs-overlay') || $className === 'close-icon') {
        return '모달과 오버레이';
    }

    if (str_starts_with($className, 'admin-flash-message')) {
        return '알림과 토스트';
    }

    if ($className === 'container' || $className === 'container-fluid' || $className === 'app-header' || $className === 'page-content' || $className === 'footer') {
        return '간격과 레이아웃';
    }

    if ($className === 'hidden' || $className === 'relative' || $className === 'sr-only' || $className === 'caption-sr-only' || $className === 'peer' || $className === 'text-dark' || $className === 'iconify-icon' || str_starts_with($className, 'animate-') || str_starts_with($className, 'progress-')) {
        return '유틸리티';
    }

    return '기타';
}

function sr_admin_design_tokens_extract_tokens(string $css): array
{
    preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]+);/', $css, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    $tokens = [];
    foreach ($matches as $match) {
        $name = trim((string) $match[1][0]);
        $value = trim((string) $match[2][0]);
        $offset = (int) $match[0][1];
        if ($name === '' || $value === '') {
            continue;
        }

        if (!isset($tokens[$name])) {
            $tokens[$name] = [
                'name' => $name,
                'category' => sr_admin_design_tokens_token_category($name),
                'values' => [],
                'root_values' => [],
                'dark_values' => [],
                'other_values' => [],
                'property_values' => [],
            ];
        }

        sr_admin_design_tokens_add_value($tokens[$name], 'values', $value);
        $selector = sr_admin_design_tokens_rule_selector($css, $offset);
        if (str_contains($selector, ':root')) {
            sr_admin_design_tokens_add_value($tokens[$name], 'root_values', $value);
        } elseif (str_contains($selector, '[data-theme=dark]')) {
            sr_admin_design_tokens_add_value($tokens[$name], 'dark_values', $value);
        } else {
            sr_admin_design_tokens_add_value($tokens[$name], 'other_values', $value);
        }
    }

    preg_match_all('/@property\s+(--[A-Za-z0-9_-]+)\s*\{([^{}]*)\}/', $css, $propertyMatches, PREG_SET_ORDER);
    foreach ($propertyMatches as $propertyMatch) {
        $name = trim((string) $propertyMatch[1]);
        if ($name === '') {
            continue;
        }

        if (!isset($tokens[$name])) {
            $tokens[$name] = [
                'name' => $name,
                'category' => sr_admin_design_tokens_token_category($name),
                'values' => [],
                'root_values' => [],
                'dark_values' => [],
                'other_values' => [],
                'property_values' => [],
            ];
        }

        $body = trim((string) $propertyMatch[2]);
        if ($body !== '') {
            sr_admin_design_tokens_add_value($tokens[$name], 'property_values', $body);
        }
    }

    ksort($tokens, SORT_NATURAL);

    return $tokens;
}

function sr_admin_design_tokens_extract_classes(string $css): array
{
    preg_match_all('/(?<![A-Za-z0-9_-])\.([A-Za-z_][A-Za-z0-9_-]*)/', $css, $matches);
    $classes = array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    sort($classes, SORT_NATURAL);

    $records = [];
    foreach ($classes as $className) {
        $records[$className] = [
            'name' => $className,
            'category' => sr_admin_design_tokens_class_category($className),
        ];
    }

    return $records;
}

function sr_admin_design_tokens_status(array $currentValues, array $referenceValues, bool $hasReference): string
{
    if (!$hasReference) {
        return 'current';
    }

    if ($currentValues === [] && $referenceValues !== []) {
        return 'missing';
    }

    if ($currentValues !== [] && $referenceValues === []) {
        return 'added';
    }

    if (sr_admin_design_tokens_normalize_values($currentValues) === sr_admin_design_tokens_normalize_values($referenceValues)) {
        return 'same';
    }

    return 'changed';
}

function sr_admin_design_tokens_status_label(string $status): string
{
    $labels = [
        'same' => '원본 동일',
        'added' => 'saanraan 추가',
        'missing' => '원본 대비 누락',
        'changed' => '값 변경',
        'current' => '현재 항목',
    ];

    return $labels[$status] ?? $status;
}

function sr_admin_design_tokens_merge_tokens(array $currentTokens, array $referenceTokens, bool $hasReference): array
{
    $names = array_values(array_unique(array_merge(array_keys($currentTokens), array_keys($referenceTokens))));
    sort($names, SORT_NATURAL);

    $records = [];
    foreach ($names as $name) {
        $current = $currentTokens[$name] ?? [
            'name' => $name,
            'category' => sr_admin_design_tokens_token_category($name),
            'values' => [],
            'root_values' => [],
            'dark_values' => [],
            'other_values' => [],
            'property_values' => [],
        ];
        $reference = $referenceTokens[$name] ?? [
            'values' => [],
            'root_values' => [],
            'dark_values' => [],
            'other_values' => [],
            'property_values' => [],
        ];
        $currentComparableValues = array_merge($current['values'], $current['property_values']);
        $referenceComparableValues = array_merge($reference['values'], $reference['property_values']);
        $status = sr_admin_design_tokens_status($currentComparableValues, $referenceComparableValues, $hasReference);

        $records[] = [
            'name' => $name,
            'category' => (string) ($current['category'] ?? sr_admin_design_tokens_token_category($name)),
            'values' => $current['values'],
            'root_values' => $current['root_values'],
            'dark_values' => $current['dark_values'],
            'other_values' => $current['other_values'],
            'property_values' => $current['property_values'],
            'reference_values' => $reference['values'],
            'reference_root_values' => $reference['root_values'],
            'reference_dark_values' => $reference['dark_values'],
            'reference_property_values' => $reference['property_values'],
            'status' => $status,
            'status_label' => sr_admin_design_tokens_status_label($status),
        ];
    }

    return $records;
}

function sr_admin_design_tokens_merge_classes(array $currentClasses, array $referenceClasses, bool $hasReference): array
{
    $names = array_values(array_unique(array_merge(array_keys($currentClasses), array_keys($referenceClasses))));
    sort($names, SORT_NATURAL);

    $records = [];
    foreach ($names as $name) {
        $currentExists = isset($currentClasses[$name]);
        $referenceExists = isset($referenceClasses[$name]);
        $status = 'current';
        if ($hasReference) {
            if ($currentExists && $referenceExists) {
                $status = 'same';
            } elseif ($currentExists) {
                $status = 'added';
            } else {
                $status = 'missing';
            }
        }

        $records[] = [
            'name' => $name,
            'category' => $currentExists ? (string) $currentClasses[$name]['category'] : sr_admin_design_tokens_class_category($name),
            'status' => $status,
            'status_label' => sr_admin_design_tokens_status_label($status),
        ];
    }

    return $records;
}

function sr_admin_design_tokens_group_records(array $records): array
{
    $groups = [];
    foreach ($records as $record) {
        $groups[(string) $record['category']][] = $record;
    }

    return $groups;
}

function sr_admin_design_tokens_count_status(array $records, string $status): int
{
    return count(array_filter($records, static function (array $record) use ($status): bool {
        return ($record['status'] ?? '') === $status;
    }));
}

function sr_admin_design_tokens_filter_classes(array $records, string $category): array
{
    return array_values(array_filter($records, static function (array $record) use ($category): bool {
        return ($record['category'] ?? '') === $category && ($record['status'] ?? '') !== 'missing';
    }));
}

$referenceCssPath = sr_admin_design_tokens_reference_path();
$referenceCss = $referenceCssPath !== '' ? (string) file_get_contents($referenceCssPath) : '';
$hasDesignTokenReference = $referenceCss !== '';

$currentTokenMap = sr_admin_design_tokens_extract_tokens($commonCss);
$referenceTokenMap = $hasDesignTokenReference ? sr_admin_design_tokens_extract_tokens($referenceCss) : [];
$currentClassMap = sr_admin_design_tokens_extract_classes($commonCss);
$referenceClassMap = $hasDesignTokenReference ? sr_admin_design_tokens_extract_classes($referenceCss) : [];

$designTokenRecords = sr_admin_design_tokens_merge_tokens($currentTokenMap, $referenceTokenMap, $hasDesignTokenReference);
$designClassRecords = sr_admin_design_tokens_merge_classes($currentClassMap, $referenceClassMap, $hasDesignTokenReference);
$designTokenGroups = sr_admin_design_tokens_group_records($designTokenRecords);
$designClassGroups = sr_admin_design_tokens_group_records($designClassRecords);

$designTokenCategoryOrder = [
    '색상',
    '타이포그래피',
    '간격과 레이아웃',
    '모서리',
    '그림자',
    '모션',
    '내부 속성',
    '기타',
];
$designClassCategoryOrder = [
    '버튼',
    '배지',
    '폼 컨트롤',
    '유효성 및 피드백',
    '카드',
    '테이블과 페이지네이션',
    '탭과 내비게이션',
    '드롭다운',
    '모달과 오버레이',
    '알림과 토스트',
    '간격과 레이아웃',
    '유틸리티',
    '기타',
];

$designTokenSummary = [
    'current_token_count' => count($currentTokenMap),
    'current_class_count' => count($currentClassMap),
    'reference_token_count' => count($referenceTokenMap),
    'reference_class_count' => count($referenceClassMap),
    'added_token_count' => sr_admin_design_tokens_count_status($designTokenRecords, 'added'),
    'missing_token_count' => sr_admin_design_tokens_count_status($designTokenRecords, 'missing'),
    'changed_token_count' => sr_admin_design_tokens_count_status($designTokenRecords, 'changed'),
    'added_class_count' => sr_admin_design_tokens_count_status($designClassRecords, 'added'),
    'missing_class_count' => sr_admin_design_tokens_count_status($designClassRecords, 'missing'),
    'changed_class_count' => 0,
];

$designButtonClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '버튼');
$designBadgeClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '배지');
$designFormClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '폼 컨트롤');
$designFeedbackClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '유효성 및 피드백');
$designCardClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '카드');
$designTableClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '테이블과 페이지네이션');
$designTabClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '탭과 내비게이션');
$designDropdownClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '드롭다운');
$designModalClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '모달과 오버레이');
$designUtilityClasses = sr_admin_design_tokens_filter_classes($designClassRecords, '유틸리티');

include SR_ROOT . '/modules/admin/views/design-tokens.php';
