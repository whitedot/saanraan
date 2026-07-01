<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
$canEditContentPayments = sr_admin_has_permission($pdo, (int) $account['id'], '/admin/content/payments', 'edit');
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content/payments', sr_request_method() === 'POST' ? 'edit' : 'view');

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 60);
    $paymentLogId = (int) sr_post_string('payment_log_id', 20);
    $downloadLogId = (int) sr_post_string('download_log_id', 20);
    $refundNote = sr_post_string('refund_note', 255);
    $refundExpirationPolicy = sr_post_string('refund_expiration_policy', 40);
    $result = ['ok' => false, 'message' => '처리할 작업을 확인할 수 없습니다.'];

    if ($intent === 'refund_view_payment') {
        $result = sr_content_refund_view_payment($pdo, $paymentLogId, (int) $account['id'], $refundNote, $refundExpirationPolicy);
    } elseif ($intent === 'refund_file_download') {
        $result = sr_content_refund_file_download($pdo, $downloadLogId, (int) $account['id'], $refundNote, $refundExpirationPolicy);
    }

    if (!empty($result['ok']) && !empty($result['coupon_notification']) && function_exists('sr_coupon_notify_issue_event')) {
        foreach ((array) $result['coupon_notification'] as $notification) {
            if (!is_array($notification) || (int) ($notification['coupon_issue_id'] ?? 0) <= 0) {
                continue;
            }
            sr_coupon_notify_issue_event(
                $pdo,
                (int) $notification['coupon_issue_id'],
                (string) ($notification['event_key'] ?? 'redemption.refunded'),
                (int) $account['id'],
                is_array($notification['payload'] ?? null) ? $notification['payload'] : []
            );
        }
    }

    if (!empty($result['ok'])) {
        sr_admin_flash_result(sr_admin_action_result([], (string) ($result['message'] ?? '처리했습니다.')));
    } else {
        sr_admin_flash_result(sr_admin_action_result([(string) ($result['message'] ?? '처리에 실패했습니다.')], ''));
    }

    sr_redirect('/admin/content/payments');
}

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];

$filters = sr_content_admin_payment_history_filters_from_request($pdo);
$paymentSortOptions = sr_content_admin_payment_history_sort_options();
$paymentDefaultSort = sr_content_admin_payment_history_default_sort();
$paymentSort = sr_admin_sort_from_request($paymentSortOptions, $paymentDefaultSort);
$paymentPagination = sr_admin_pagination_from_total($pdo, sr_content_admin_payment_history_count($pdo, $filters));
$paymentLogs = sr_content_admin_payment_history_logs($pdo, $filters, (int) $paymentPagination['per_page'], sr_admin_pagination_offset($paymentPagination), $paymentSort);

$adminPageTitle = '콘텐츠 결제 내역';
$adminPageSubtitle = '';

include SR_ROOT . '/modules/content/views/admin-payments.php';
