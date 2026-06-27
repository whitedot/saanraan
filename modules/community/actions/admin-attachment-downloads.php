<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/attachment-downloads', 'view');

if (!function_exists('sr_community_admin_filter_values')) {
    function sr_community_admin_filter_values(string $key, array $allowedValues): array
    {
        $raw = $_GET[$key] ?? [];
        if (!is_array($raw)) {
            $raw = (string) $raw === '' ? [] : [(string) $raw];
        }

        $values = [];
        foreach ($raw as $value) {
            $value = (string) $value;
            if (in_array($value, $allowedValues, true)) {
                $values[$value] = $value;
            }
        }

        return array_values($values);
    }
}

$filters = [
    'board_id' => (int) sr_get_string('board_id', 20),
    'post_id' => (int) sr_get_string('post_id', 20),
    'attachment_id' => (int) sr_get_string('attachment_id', 20),
    'account_id' => sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), sr_get_string('account_id', 80)),
    'download_type' => sr_community_admin_filter_values('download_type', ['free', 'paid']),
    'date_from' => sr_get_string('date_from', 10),
    'date_to' => sr_get_string('date_to', 10),
    'q' => sr_get_string('q', 120),
];
if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_from']) !== 1) {
    $filters['date_from'] = '';
}
if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) $filters['date_to']) !== 1) {
    $filters['date_to'] = '';
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$downloadLogSortOptions = sr_community_admin_attachment_download_log_sort_options();
$downloadLogDefaultSort = sr_community_admin_attachment_download_log_default_sort();
$downloadLogSort = sr_admin_sort_from_request($downloadLogSortOptions, $downloadLogDefaultSort);
$downloadLogPagination = sr_admin_pagination_from_total($pdo, sr_community_admin_attachment_download_log_count($pdo, $filters));
$downloadLogs = sr_community_admin_attachment_download_logs($pdo, $filters, (int) $downloadLogPagination['per_page'], sr_admin_pagination_offset($downloadLogPagination), $downloadLogSort);
$boards = sr_community_boards($pdo);

$adminPageTitle = '첨부 다운로드 내역';
$adminPageSubtitle = '';

include SR_ROOT . '/modules/community/views/admin-attachment-downloads.php';
