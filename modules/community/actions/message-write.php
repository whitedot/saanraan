<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, $account);
$settings = sr_community_settings($pdo);
if (!sr_community_account_can_write_message($pdo, $account, $settings)) {
    sr_render_error(403, '쪽지를 보낼 수 없습니다.');
}
$errors = [];
$notice = '';
$recipientAccountHash = strtolower(trim(sr_get_string('to_account', 40)));
$presetRecipient = sr_member_public_account_hash_is_valid($recipientAccountHash)
    ? sr_member_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)
    : null;
$values = [
    'recipient_account_hash' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? (string) $presetRecipient['public_hash'] : '',
    'recipient_identifier' => '',
    'body_text' => '',
];
$recipientPresetNotice = $values['recipient_account_hash'] !== '' ? '받는 회원이 미리 입력되었습니다.' : '';
$recipientLabel = $values['recipient_account_hash'] !== '' && is_array($presetRecipient)
    ? sr_community_message_account_label((string) $presetRecipient['display_name'], (int) $presetRecipient['id'], $canViewMemberIdentifiers, $config)
    : '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $values = sr_community_message_input_values();
    $errors = sr_community_validate_message_input($values);
    $recipient = null;
    $submittedRecipient = is_string($values['recipient_account_hash'] ?? null) && (string) $values['recipient_account_hash'] !== ''
        ? sr_member_public_account_summary_by_hash($pdo, $config, (string) $values['recipient_account_hash'])
        : null;
    if (is_array($submittedRecipient)) {
        $recipientLabel = sr_community_message_account_label((string) $submittedRecipient['display_name'], (int) $submittedRecipient['id'], $canViewMemberIdentifiers, $config);
    }
    if ($errors === []) {
        if (is_array($submittedRecipient)) {
            $recipient = $submittedRecipient;
        } else {
            $recipient = sr_member_find_by_identifier($pdo, $config, (string) $values['recipient_identifier']);
        }
        if (!is_array($recipient) || (string) $recipient['status'] !== 'active') {
            $errors[] = '받는 회원을 찾을 수 없습니다.';
        } elseif ((int) $recipient['id'] === (int) $account['id']) {
            $errors[] = '본인에게는 쪽지를 보낼 수 없습니다.';
        }
    }
    if (is_array($recipient)) {
        $recipientLabel = sr_community_message_account_label((string) $recipient['display_name'], (int) $recipient['id'], $canViewMemberIdentifiers, $config);
    }

    if ($errors === [] && sr_community_message_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = '짧은 시간에 쪽지를 너무 많이 보냈습니다. 잠시 후 다시 시도해 주세요.';
    }

    if ($errors === [] && is_array($recipient)) {
        $messageId = sr_community_create_message($pdo, (int) $account['id'], (int) $recipient['id'], (string) $values['body_text']);
        sr_community_record_message_rate_limit($pdo, (int) $account['id'], $settings);
        sr_audit_log($pdo, [
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
        sr_community_create_account_notification(
            $pdo,
            (int) $recipient['id'],
            '새 쪽지가 도착했습니다.',
            sr_community_message_account_label((string) ($account['display_name'] ?? ''), (int) $account['id']) . '님이 쪽지를 보냈습니다.',
            '/community/message?id=' . (string) $messageId,
            (int) $account['id']
        );
        $_SESSION['sr_community_message_notice'] = '쪽지를 보냈습니다.';
        sr_redirect('/community/messages?box=sent');
    }
}

include SR_ROOT . '/modules/community/views/message-write.php';
