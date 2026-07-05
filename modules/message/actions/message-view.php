<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers/reports.php';
require_once SR_ROOT . '/modules/message/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_message_settings($pdo);
if (!sr_message_enabled($pdo, $settings)) {
    sr_render_error(403, '쪽지 기능을 사용할 수 없습니다.');
}
$canViewMemberIdentifiers = function_exists('sr_admin_has_permission')
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/members', 'view');
$messageIdValue = sr_get_string('id', 20);
$messageId = preg_match('/\A[1-9][0-9]*\z/', $messageIdValue) === 1 ? (int) $messageIdValue : 0;
$message = sr_message_by_id_for_account($pdo, $messageId, (int) $account['id']);
if (!is_array($message)) {
    sr_render_error(404, '쪽지를 찾을 수 없습니다.');
}

sr_message_mark_read($pdo, $message, (int) $account['id']);
if ((int) $message['recipient_account_id'] === (int) $account['id'] && (string) ($message['read_at'] ?? '') === '') {
    $message['read_at'] = sr_now();
}
$messageBox = (int) $message['sender_account_id'] === (int) $account['id'] ? 'sent' : 'inbox';
$replyAccountId = (int) $message['sender_account_id'] === (int) $account['id']
    ? (int) $message['recipient_account_id']
    : (int) $message['sender_account_id'];
$replyAccountHash = sr_member_public_account_hash($config, $replyAccountId);
$reportReasonKeys = sr_community_report_reason_keys();
$reportErrors = [];
$reportNotice = '';
if (isset($_SESSION['sr_community_report_errors']) && is_array($_SESSION['sr_community_report_errors'])) {
    foreach ($_SESSION['sr_community_report_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $reportErrors[] = $error;
        }
    }
}
if (isset($_SESSION['sr_community_report_notice']) && is_string($_SESSION['sr_community_report_notice'])) {
    $reportNotice = $_SESSION['sr_community_report_notice'];
}
unset($_SESSION['sr_community_report_errors'], $_SESSION['sr_community_report_notice']);

include SR_ROOT . '/modules/message/views/message-view.php';
