<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, $account);
$settings = sr_community_settings($pdo);
if (!sr_community_account_can_write_message($pdo, $account, $settings)) {
    sr_render_error(403, sr_t('community::action.error.message_send_forbidden'));
}
$errors = [];
$notice = '';
$recipientAccountHash = strtolower(trim(sr_get_string('to_account', 40)));
$presetRecipient = sr_member_public_account_hash_is_valid($recipientAccountHash)
    ? sr_community_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)
    : null;
$values = [
    'recipient_account_hash' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? (string) $presetRecipient['public_hash'] : '',
    'recipient_identifier' => '',
    'body_text' => '',
];
$recipientPresetNotice = $values['recipient_account_hash'] !== '' ? sr_t('community::action.notice.recipient_preset') : '';
$recipientLabel = $values['recipient_account_hash'] !== '' && is_array($presetRecipient)
    ? sr_community_message_account_label((string) $presetRecipient['display_name'], (int) $presetRecipient['id'], $canViewMemberIdentifiers, $config, (string) $presetRecipient['status'], (string) ($presetRecipient['community_nickname'] ?? ''), $settings)
    : '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $values = sr_community_message_input_values();
    $errors = sr_community_validate_message_input($values);
    $recipient = null;
    $submittedRecipient = is_string($values['recipient_account_hash'] ?? null) && (string) $values['recipient_account_hash'] !== ''
        ? sr_community_public_account_summary_by_hash($pdo, $config, (string) $values['recipient_account_hash'])
        : null;
    if (is_array($submittedRecipient)) {
        $recipientLabel = sr_community_message_account_label((string) $submittedRecipient['display_name'], (int) $submittedRecipient['id'], $canViewMemberIdentifiers, $config, (string) $submittedRecipient['status'], (string) ($submittedRecipient['community_nickname'] ?? ''), $settings);
    }
    if ($errors === []) {
        if (is_array($submittedRecipient)) {
            $recipient = $submittedRecipient;
        } else {
            $recipient = sr_member_find_by_identifier($pdo, $config, (string) $values['recipient_identifier']);
        }
        if (!is_array($recipient) || (string) $recipient['status'] !== 'active') {
            $errors[] = sr_t('community::action.error.recipient_not_found');
        } elseif ((int) $recipient['id'] === (int) $account['id']) {
            $errors[] = sr_t('community::action.error.message_self_forbidden');
        }
    }
    if (is_array($recipient)) {
        $recipientSummary = sr_community_public_account_summary($pdo, (int) $recipient['id']);
        $recipientLabelAccount = is_array($recipientSummary) ? $recipientSummary : $recipient;
        $recipientLabel = sr_community_message_account_label((string) ($recipientLabelAccount['display_name'] ?? ''), (int) $recipient['id'], $canViewMemberIdentifiers, $config, (string) ($recipientLabelAccount['status'] ?? $recipient['status'] ?? ''), (string) ($recipientLabelAccount['community_nickname'] ?? ''), $settings);
    }

    if ($errors === [] && sr_community_message_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = sr_t('community::action.rate_limit.message');
    }

    if ($errors === [] && is_array($recipient)) {
        $messageId = sr_community_create_message($pdo, (int) $account['id'], (int) $recipient['id'], (string) $values['body_text']);
        sr_community_record_message_rate_limit($pdo, (int) $account['id'], $settings);
        $senderLabel = sr_community_message_account_label(
            (string) ($account['display_name'] ?? ''),
            (int) $account['id'],
            false,
            null,
            (string) ($account['status'] ?? ''),
            sr_community_member_nickname($pdo, (int) $account['id']),
            $settings
        );
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
            sr_t('community::notification.message.title'),
            sr_t('community::notification.message.body', [
                'account' => $senderLabel,
            ]),
            '/community/message?id=' . (string) $messageId,
            (int) $account['id']
        );
        $_SESSION['sr_community_message_notice'] = sr_t('community::action.notice.message_sent');
        sr_redirect('/community/messages?box=sent');
    }
}

include SR_ROOT . '/modules/community/views/message-write.php';
