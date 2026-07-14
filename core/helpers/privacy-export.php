<?php

declare(strict_types=1);

function sr_privacy_export_limit_rows(array $rows, string $sectionKey, array &$sectionLimits, int $limit): array
{
    $limit = max(1, $limit);
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        $rows = array_slice($rows, 0, $limit);
    }

    $sectionLimits[$sectionKey] = [
        'limit' => $limit,
        'returned' => count($rows),
        'has_more' => $hasMore,
        'policy' => $hasMore ? 'request_follow_up_export' : 'complete_within_section_limit',
    ];

    return $rows;
}

function sr_privacy_export_overflow_sections(array $moduleExport): array
{
    $limits = is_array($moduleExport['_limits'] ?? null) ? $moduleExport['_limits'] : [];
    $sections = [];
    foreach ($limits as $sectionKey => $limitState) {
        if (is_string($sectionKey) && is_array($limitState) && !empty($limitState['has_more'])) {
            $sections[] = $sectionKey;
        }
    }

    sort($sections);

    return $sections;
}
