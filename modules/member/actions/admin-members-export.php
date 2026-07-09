<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/members', 'view');

$allowedStatuses = sr_admin_member_allowed_statuses();
$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
$statusFilter = sr_admin_member_status_filter($allowedStatuses);
$searchFilter = sr_admin_member_search_filter($pdo, $runtimeConfig);
$memberSort = sr_admin_sort_from_request(sr_admin_member_sort_options(), sr_admin_member_default_sort());
$totalMembers = sr_admin_member_count($pdo, $statusFilter, $searchFilter);
$exportLimit = sr_admin_member_export_limit_from_request();
$memberExportPage = sr_admin_member_export_page_from_request($totalMembers, $exportLimit);
$memberExportRange = sr_admin_member_export_range($totalMembers, $exportLimit, $memberExportPage);
$expectedExportCount = (int) $memberExportRange['count'];
$memberExportDownload = sr_get_string('download', 5) === '1';
$memberExportColumnConfig = sr_admin_member_export_column_config_from_request();
$memberExportColumns = $memberExportColumnConfig['selected'];
$memberExportColumnError = $memberExportColumns === [] ? '다운로드할 컬럼을 하나 이상 선택하세요.' : '';
$memberExportMaskOptions = sr_admin_member_export_mask_options_from_request();

if (!$memberExportDownload || $memberExportColumnError !== '') {
    include SR_ROOT . '/modules/member/views/admin-members-export.php';
    return;
}

sr_audit_log($pdo, [
    'actor_account_id' => (int) ($account['id'] ?? 0),
    'actor_type' => 'admin',
    'event_type' => 'member.members.exported',
    'target_type' => 'member_account',
    'target_id' => 'filtered',
    'result' => 'success',
    'message' => 'Member list exported.',
    'metadata' => [
        'status_filter' => $statusFilter,
        'search_field' => (string) ($searchFilter['field'] ?? 'all'),
        'keyword_present' => trim((string) ($searchFilter['keyword'] ?? '')) !== '',
        'sort' => $memberSort,
        'matched_count' => $totalMembers,
        'export_count' => $expectedExportCount,
        'limit' => $exportLimit,
        'page' => (int) $memberExportRange['page'],
        'total_pages' => (int) $memberExportRange['total_pages'],
        'offset' => (int) $memberExportRange['offset'],
        'range_start' => (int) $memberExportRange['start'],
        'range_end' => (int) $memberExportRange['end'],
        'truncated' => !empty($memberExportRange['has_next']),
        'columns' => $memberExportColumns,
        'mask_email' => !empty($memberExportMaskOptions['email']),
        'mask_phone' => !empty($memberExportMaskOptions['phone']),
    ],
]);

$rangeSuffix = $expectedExportCount > 0 ? '-' . (string) $memberExportRange['start'] . '-' . (string) $memberExportRange['end'] : '';
sr_send_download_headers('text/csv; charset=UTF-8', 'saanraan-members' . $rangeSuffix . '-' . date('Ymd-His') . '.csv');
$output = fopen('php://output', 'wb');
if ($output === false) {
    sr_finish_response();
}
fwrite($output, "\xEF\xBB\xBF");

sr_admin_member_csv_row($output, sr_admin_member_export_column_labels($memberExportColumns));

$offset = (int) $memberExportRange['offset'];
$exportedCount = 0;
$batchSize = 1000;
while ($exportedCount < $expectedExportCount) {
    $remaining = $expectedExportCount - $exportedCount;
    $limit = min($batchSize, $remaining);
    $rows = sr_admin_members($pdo, $statusFilter, $searchFilter, $limit, $offset, $memberSort);
    if ($rows === []) {
        break;
    }

    $rows = sr_admin_member_rows_with_public_hash($runtimeConfig, $rows);
    $marketingConsents = sr_member_latest_consents_by_account_ids($pdo, array_column($rows, 'id'), 'marketing');
    $memberExportContext = sr_admin_member_export_context($pdo, array_column($rows, 'id'), $memberExportColumns);
    foreach ($rows as $member) {
        $marketingConsent = $marketingConsents[(int) ($member['id'] ?? 0)] ?? null;
        $marketingValues = sr_admin_member_marketing_consent_export_values(is_array($marketingConsent) ? $marketingConsent : null);
        $memberExportRowContext = $memberExportContext;
        $memberExportRowContext['sequence'] = (int) $memberExportRange['offset'] + $exportedCount + 1;
        sr_admin_member_csv_row($output, sr_admin_member_export_row_values($memberExportColumns, $member, $marketingValues, $memberExportRowContext, $memberExportMaskOptions));
        $exportedCount++;
        if ($exportedCount >= $expectedExportCount) {
            break;
        }
    }

    $rowCount = count($rows);
    $offset += $rowCount;
    if ($rowCount < $limit) {
        break;
    }
}

fclose($output);
sr_finish_response();
