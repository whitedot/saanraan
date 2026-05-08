<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$messageIdValue = toy_get_string('id', 20);
$messageId = preg_match('/\A[1-9][0-9]*\z/', $messageIdValue) === 1 ? (int) $messageIdValue : 0;
$message = toy_community_message_by_id_for_account($pdo, $messageId, (int) $account['id']);
if (!is_array($message)) {
    toy_render_error(404, '쪽지를 찾을 수 없습니다.');
}

toy_community_mark_message_read($pdo, $message, (int) $account['id']);
if ((int) $message['recipient_account_id'] === (int) $account['id'] && (string) ($message['read_at'] ?? '') === '') {
    $message['read_at'] = toy_now();
}
$messageBox = (int) $message['sender_account_id'] === (int) $account['id'] ? 'sent' : 'inbox';
$reportReasonKeys = toy_community_report_reason_keys();
$reportErrors = [];
$reportNotice = '';
if (isset($_SESSION['toy_community_report_errors']) && is_array($_SESSION['toy_community_report_errors'])) {
    foreach ($_SESSION['toy_community_report_errors'] as $error) {
        if (is_string($error) && $error !== '') {
            $reportErrors[] = $error;
        }
    }
}
if (isset($_SESSION['toy_community_report_notice']) && is_string($_SESSION['toy_community_report_notice'])) {
    $reportNotice = $_SESSION['toy_community_report_notice'];
}
unset($_SESSION['toy_community_report_errors'], $_SESSION['toy_community_report_notice']);

include TOY_ROOT . '/modules/community/views/message-view.php';
