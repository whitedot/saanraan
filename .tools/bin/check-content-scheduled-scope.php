#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

function sr_check_order(string $content, string $firstNeedle, string $secondNeedle): bool
{
    $first = strpos($content, $firstNeedle);
    $second = strpos($content, $secondNeedle);

    return $first !== false && $second !== false && $first < $second;
}

function sr_check_function_body(string $content, string $functionName): string
{
    $start = strpos($content, 'function ' . $functionName . '(');
    if ($start === false) {
        return '';
    }

    $next = strpos($content, "\nfunction ", $start + 1);
    if ($next === false) {
        return substr($content, $start);
    }

    return substr($content, $start, $next - $start);
}

$action = file_get_contents($root . '/modules/content/actions/admin-content-save.php');
if (!is_string($action)) {
    $errors[] = 'Content admin save action cannot be read.';
} else {
    if (strpos($action, "\$statusScope = sr_content_normalize_setting_source((string) (\$values['source_status'] ?? 'content'));") === false) {
        $errors[] = 'Content admin save must normalize source_status before scope audit snapshots.';
    }
    if (strpos($action, '$statusBeforeTargetIds = sr_content_apply_scope_target_ids($pdo, $pageId,') === false
        || strpos($action, '$statusBeforeRows = sr_content_status_rows_for_ids($pdo, $statusBeforeTargetIds);') === false
    ) {
        $errors[] = 'Content admin save must collect before status rows for the selected status scope.';
    }
    if (!sr_check_order($action, '$statusBeforeRows = sr_content_status_rows_for_ids($pdo, $statusBeforeTargetIds);', '$savedPageId = sr_content_save(')) {
        $errors[] = 'Content status before snapshot must be collected before saving the content.';
    }
    if (strpos($action, '$statusAfterTargetIds = sr_content_apply_scope_target_ids($pdo, $savedPageId,') === false
        || strpos($action, '$statusAfterRows = sr_content_status_rows_for_ids($pdo, $statusAfterTargetIds);') === false
    ) {
        $errors[] = 'Content admin save must collect after status rows from the saved content scope.';
    }
    if (!sr_check_order($action, 'sr_audit_log($pdo, [', 'sr_content_audit_status_schedule_changes($pdo, $statusBeforeRows, $statusAfterRows, $account);')) {
        $errors[] = 'Content scheduled/schedule-cleared audit must run after the base content audit log.';
    }
    if (!sr_check_order($action, '$savedPageId = sr_content_save(', '$statusAfterTargetIds = sr_content_apply_scope_target_ids($pdo, $savedPageId,')) {
        $errors[] = 'Content status after snapshot must be collected after saving with the saved content id.';
    }
}

$helper = file_get_contents($root . '/modules/content/helpers/groups.php');
if (!is_string($helper)) {
    $errors[] = 'Content group helper cannot be read.';
} else {
    foreach ([
        'sr_content_apply_scope_target_ids',
        'sr_content_status_rows_for_ids',
        'sr_content_audit_status_schedule_changes',
        'sr_content_apply_setting_scope',
    ] as $functionName) {
        if (sr_check_function_body($helper, $functionName) === '') {
            $errors[] = 'Content group helper function is missing: ' . $functionName;
        }
    }

    $scopeBody = sr_check_function_body($helper, 'sr_content_apply_scope_target_ids');
    if ($scopeBody !== '') {
        if (!sr_check_order($scopeBody, "\$scope === 'all'", '$pageId < 1')) {
            $errors[] = 'Content all-scope targets must be resolved before rejecting a missing page id.';
        }
        if (!sr_check_order($scopeBody, "\$scope === 'group'", '$pageId < 1')) {
            $errors[] = 'Content group-scope targets must be resolved before rejecting a missing page id.';
        }
    }

    $statusRowsBody = sr_check_function_body($helper, 'sr_content_status_rows_for_ids');
    if ($statusRowsBody !== ''
        && (strpos($statusRowsBody, 'SELECT id, slug, status, published_at FROM sr_content_items') === false
            || strpos($statusRowsBody, '$rows[(int) ($row[\'id\'] ?? 0)] = $row;') === false)
    ) {
        $errors[] = 'Content status row snapshots must include id, slug, status, and published_at keyed by id.';
    }

    $auditBody = sr_check_function_body($helper, 'sr_content_audit_status_schedule_changes');
    if ($auditBody !== '') {
        foreach (['content.scheduled', 'content.schedule_cleared', 'scheduled_publish_at', 'previous_status', 'previous_published_at'] as $needle) {
            if (strpos($auditBody, $needle) === false) {
                $errors[] = 'Content schedule audit helper is missing required marker: ' . $needle;
            }
        }
    }

    $applyScopeBody = sr_check_function_body($helper, 'sr_content_apply_setting_scope');
    if ($applyScopeBody !== '') {
        if (strpos($applyScopeBody, "WHEN status = 'published' AND published_at IS NOT NULL THEN published_at") === false) {
            $errors[] = 'Content status scope publishing must only preserve published_at for already-published targets.';
        }
        if (strpos($applyScopeBody, "WHEN ? = 'published' THEN COALESCE(published_at, ?)") !== false) {
            $errors[] = 'Content status scope publishing must not keep future scheduled dates when publishing targets.';
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "content scheduled scope checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "content scheduled scope checks completed.\n";
