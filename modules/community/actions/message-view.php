<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$communitySettings = sr_community_settings($pdo);
$memberSettings = sr_member_settings($pdo);
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, $account);
$messageIdValue = sr_get_string('id', 20);
$messageId = preg_match('/\A[1-9][0-9]*\z/', $messageIdValue) === 1 ? (int) $messageIdValue : 0;
$message = sr_community_message_by_id_for_account($pdo, $messageId, (int) $account['id']);
if (!is_array($message)) {
    sr_render_error(404, sr_t('community::action.error.message_not_found'));
}

sr_community_mark_message_read($pdo, $message, (int) $account['id']);
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

include SR_ROOT . '/modules/community/views/message-view.php';
