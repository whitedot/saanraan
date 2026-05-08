<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$errors = [];
$notice = '';
$values = [
    'recipient_identifier' => toy_get_string('to', 255),
    'body_text' => '',
];
$recipientPresetNotice = $values['recipient_identifier'] !== '' ? '받는 회원이 미리 입력되었습니다.' : '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $values = toy_community_message_input_values();
    $errors = toy_community_validate_message_input($values);
    $recipient = null;
    if ($errors === []) {
        $recipient = toy_member_find_by_identifier($pdo, $config, (string) $values['recipient_identifier']);
        if (!is_array($recipient) || (string) $recipient['status'] !== 'active') {
            $errors[] = '받는 회원을 찾을 수 없습니다.';
        } elseif ((int) $recipient['id'] === (int) $account['id']) {
            $errors[] = '본인에게는 쪽지를 보낼 수 없습니다.';
        }
    }

    $settings = toy_module_settings($pdo, 'community');
    if ($errors === [] && toy_community_message_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = '짧은 시간에 쪽지를 너무 많이 보냈습니다. 잠시 후 다시 시도해 주세요.';
    }

    if ($errors === [] && is_array($recipient)) {
        $messageId = toy_community_create_message($pdo, (int) $account['id'], (int) $recipient['id'], (string) $values['body_text']);
        toy_community_record_message_rate_limit($pdo, (int) $account['id'], $settings);
        toy_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.message.sent',
            'target_type' => 'community_message',
            'target_id' => (string) $messageId,
            'result' => 'success',
            'message' => 'Community message sent.',
            'metadata' => [
                'recipient_account_id' => (int) $recipient['id'],
            ],
        ]);
        $_SESSION['toy_community_message_notice'] = '쪽지를 보냈습니다.';
        toy_redirect('/community/messages?box=sent');
    }
}

include TOY_ROOT . '/modules/community/views/message-write.php';
