<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/message/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();
$settings = sr_message_settings($pdo);
if (!sr_message_enabled($pdo, $settings)) {
    sr_render_error(403, '쪽지 기능을 사용할 수 없습니다.');
}

$messageIdValue = sr_post_string('message_id', 20);
$messageId = preg_match('/\A[1-9][0-9]*\z/', $messageIdValue) === 1 ? (int) $messageIdValue : 0;
$message = sr_message_participants_for_account($pdo, $messageId, (int) $account['id']);
if (!is_array($message)) {
    sr_render_error(404, '쪽지를 찾을 수 없습니다.');
}

sr_message_soft_delete($pdo, $message, (int) $account['id']);
$box = (int) $message['sender_account_id'] === (int) $account['id'] ? 'sent' : 'inbox';
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'message.deleted_by_account',
    'target_type' => 'message',
    'target_id' => (string) $messageId,
    'result' => 'success',
    'message' => 'Message deleted by account.',
    'metadata' => [
        'box' => $box,
        'sender_account_id' => (int) $message['sender_account_id'],
        'recipient_account_id' => (int) $message['recipient_account_id'],
    ],
]);
$_SESSION['sr_message_notice'] = '쪽지를 삭제했습니다.';
$returnPageValue = sr_post_string('return_page', 20);
$returnPage = preg_match('/\A[1-9][0-9]*\z/', $returnPageValue) === 1 ? (int) $returnPageValue : 1;
$returnPageQuery = $returnPage > 1 ? '&page=' . (string) $returnPage : '';

if ($box === 'sent') {
    sr_redirect('/messages?box=sent' . $returnPageQuery);
}

sr_redirect('/messages' . ($returnPage > 1 ? '?page=' . (string) $returnPage : ''));
