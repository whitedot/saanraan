<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_require_csrf();

$messageIdValue = sr_post_string('message_id', 20);
$messageId = preg_match('/\A[1-9][0-9]*\z/', $messageIdValue) === 1 ? (int) $messageIdValue : 0;
$message = sr_community_message_participants_for_account($pdo, $messageId, (int) $account['id']);
if (!is_array($message)) {
    sr_render_error(404, sr_t('community::action.error.message_not_found'));
}

sr_community_soft_delete_message($pdo, $message, (int) $account['id']);
$box = (int) $message['sender_account_id'] === (int) $account['id'] ? 'sent' : 'inbox';
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'community.message.deleted_by_account',
    'target_type' => 'community_message',
    'target_id' => (string) $messageId,
    'result' => 'success',
    'message' => 'Community message deleted by account.',
    'metadata' => [
        'box' => $box,
        'sender_account_id' => (int) $message['sender_account_id'],
        'recipient_account_id' => (int) $message['recipient_account_id'],
    ],
]);
$_SESSION['sr_community_message_notice'] = '쪽지를 삭제했습니다.';

if ($box === 'sent') {
    sr_redirect('/community/messages?box=sent');
}

sr_redirect('/community/messages');
