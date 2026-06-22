<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
$canEditFileDownloads = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content/file-downloads', 'edit');
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/file-downloads', sr_request_method() === 'POST' ? 'edit' : 'view');

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $downloadLogId = (int) sr_post_string('download_log_id', 20);
    $refundNote = sr_post_string('refund_note', 255);
    $refundExpirationPolicy = sr_post_string('refund_expiration_policy', 40);
    $result = ['ok' => false, 'message' => '처리할 작업을 확인할 수 없습니다.'];
    if ($intent === 'refund_download') {
        $result = sr_content_refund_file_download($pdo, $downloadLogId, (int) $account['id'], $refundNote, $refundExpirationPolicy);
    }

    if (!empty($result['ok'])) {
        sr_admin_flash_result(sr_admin_action_result([], (string) ($result['message'] ?? '처리했습니다.')));
    } else {
        sr_admin_flash_result(sr_admin_action_result([(string) ($result['message'] ?? '처리에 실패했습니다.')], ''));
    }

    sr_redirect('/admin/content/file-downloads');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

$filters = [
    'content_id' => (int) sr_get_string('content_id', 20),
    'file_id' => (int) sr_get_string('file_id', 20),
    'account_id' => (int) sr_get_string('account_id', 20),
    'download_type' => sr_content_admin_single_filter_values('download_type', ['free', 'paid']),
    'refund_status' => sr_content_admin_multi_filter_values('refund_status', ['none', 'refunded', 'access_revoked']),
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

$downloadLogSortOptions = sr_content_admin_file_download_log_sort_options();
$downloadLogDefaultSort = sr_content_admin_file_download_log_default_sort();
$downloadLogSort = sr_admin_sort_from_request($downloadLogSortOptions, $downloadLogDefaultSort);
$downloadLogPagination = sr_admin_pagination_from_total($pdo, sr_content_admin_file_download_log_count($pdo, $filters));
$downloadLogs = sr_content_admin_file_download_logs($pdo, $filters, (int) $downloadLogPagination['per_page'], sr_admin_pagination_offset($downloadLogPagination), $downloadLogSort);

$adminPageTitle = '파일 다운로드 내역';
$adminPageSubtitle = '';

include SR_ROOT . '/modules/content/views/admin-file-downloads.php';
