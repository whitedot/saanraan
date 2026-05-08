<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$errors = [];
$notice = '';
$recipientAccountIdValue = toy_get_string('to_account', 20);
$recipientAccountId = preg_match('/\A[1-9][0-9]*\z/', $recipientAccountIdValue) === 1 ? (int) $recipientAccountIdValue : 0;
$presetRecipient = $recipientAccountId > 0 ? toy_member_public_account_summary($pdo, $recipientAccountId) : null;
$values = [
    'recipient_account_id' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? (int) $presetRecipient['id'] : 0,
    'recipient_identifier' => toy_get_string('to', 255),
    'body_text' => '',
];
$recipientPresetNotice = (int) $values['recipient_account_id'] > 0 || $values['recipient_identifier'] !== '' ? '받는 회원이 미리 입력되었습니다.' : '';
$recipientLabel = (int) $values['recipient_account_id'] > 0 && is_array($presetRecipient)
    ? toy_community_message_account_label((string) $presetRecipient['display_name'], (int) $presetRecipient['id'])
    : '';

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $values = toy_community_message_input_values();
    $errors = toy_community_validate_message_input($values);
    $recipient = null;
    $submittedRecipient = (int) ($values['recipient_account_id'] ?? 0) > 0
        ? toy_member_public_account_summary($pdo, (int) $values['recipient_account_id'])
        : null;
    if (is_array($submittedRecipient)) {
        $recipientLabel = toy_community_message_account_label((string) $submittedRecipient['display_name'], (int) $submittedRecipient['id']);
    }
    if ($errors === []) {
        if (is_array($submittedRecipient)) {
            $recipient = $submittedRecipient;
        } else {
            $recipient = toy_member_find_by_identifier($pdo, $config, (string) $values['recipient_identifier']);
        }
        if (!is_array($recipient) || (string) $recipient['status'] !== 'active') {
            $errors[] = '받는 회원을 찾을 수 없습니다.';
        } elseif ((int) $recipient['id'] === (int) $account['id']) {
            $errors[] = '본인에게는 쪽지를 보낼 수 없습니다.';
        }
    }
    if (is_array($recipient)) {
        $recipientLabel = toy_community_message_account_label((string) $recipient['display_name'], (int) $recipient['id']);
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
