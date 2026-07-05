<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/message/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_message_settings($pdo);
if (!sr_message_enabled($pdo, $settings)) {
    sr_render_error(403, '쪽지 기능을 사용할 수 없습니다.');
}
if (!sr_message_account_can_send($pdo, $account, $settings)) {
    sr_render_error(403, '쪽지를 보낼 권한이 없습니다.');
}
$canViewMemberIdentifiers = function_exists('sr_admin_has_permission')
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/members', 'view');
$errors = [];
$recipientAccountHash = strtolower(trim(sr_get_string('to_account', 40)));
$presetRecipient = sr_member_public_account_hash_is_valid($recipientAccountHash)
    ? sr_member_public_account_summary_by_hash($pdo, $config, $recipientAccountHash)
    : null;
$values = [
    'recipient_account_hash' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? (string) $presetRecipient['public_hash'] : '',
    'recipient_account_hashes' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? [(string) $presetRecipient['public_hash']] : [],
    'recipient_identifier' => '',
    'body_text' => '',
];
$recipientPresetNotice = $values['recipient_account_hash'] !== '' ? '수신자가 미리 선택되었습니다.' : '';
$recipientLabel = $values['recipient_account_hash'] !== '' && is_array($presetRecipient)
    ? sr_message_account_label((string) $presetRecipient['display_name'], (int) $presetRecipient['id'], $canViewMemberIdentifiers, $config, (string) $presetRecipient['status'])
    : '';
$recipientPickerItems = $values['recipient_account_hash'] !== '' && $recipientLabel !== ''
    ? [[
        'hash' => $values['recipient_account_hash'],
        'label' => $recipientLabel,
    ]]
    : [];

$messageWriteFlash = isset($_SESSION['sr_message_write_flash']) && is_array($_SESSION['sr_message_write_flash'])
    ? $_SESSION['sr_message_write_flash']
    : [];
unset($_SESSION['sr_message_write_flash']);
if ($messageWriteFlash !== []) {
    $flashValues = is_array($messageWriteFlash['values'] ?? null) ? $messageWriteFlash['values'] : [];
    $values = array_merge($values, array_intersect_key($flashValues, $values));
    $errors = isset($messageWriteFlash['errors']) && is_array($messageWriteFlash['errors'])
        ? array_values(array_filter(array_map('strval', $messageWriteFlash['errors']), static fn (string $error): bool => $error !== ''))
        : [];
    $recipientPresetNotice = '';
    $recipientPickerItems = [];
    foreach (is_array($values['recipient_account_hashes'] ?? null) ? $values['recipient_account_hashes'] : [] as $recipientHash) {
        $recipientHash = strtolower(trim((string) $recipientHash));
        if (!sr_member_public_account_hash_is_valid($recipientHash)) {
            continue;
        }
        $recipientSummary = sr_member_public_account_summary_by_hash($pdo, $config, $recipientHash);
        if (!is_array($recipientSummary) || (string) ($recipientSummary['status'] ?? '') !== 'active') {
            continue;
        }
        $recipientPickerItems[] = [
            'hash' => $recipientHash,
            'label' => sr_message_account_label((string) ($recipientSummary['display_name'] ?? ''), (int) $recipientSummary['id'], $canViewMemberIdentifiers, $config, (string) ($recipientSummary['status'] ?? '')),
        ];
    }
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $values = sr_message_input_values();
    $errors = sr_message_validate_input($values);
    $recipients = [];
    $createdMessages = [];
    $recipientPickerItems = [];
    if ($errors === []) {
        $recipients = sr_message_recipients_from_values($pdo, $config, $values);
        if ($recipients === []) {
            $errors[] = '쪽지를 보낼 수 있는 수신자를 찾을 수 없습니다.';
        }
        foreach ($recipients as $recipient) {
            if ((int) ($recipient['id'] ?? 0) === (int) $account['id']) {
                $errors[] = '자기 자신에게는 쪽지를 보낼 수 없습니다.';
                break;
            }
            if (!sr_message_account_can_receive($pdo, $recipient, $account, $settings)) {
                $errors[] = '쪽지를 보낼 수 있는 수신자를 확인해 주세요.';
                break;
            }
        }
    }
    foreach ($recipients as $recipient) {
        $recipientPickerItems[] = [
            'hash' => sr_member_public_account_hash($config, (int) $recipient['id']),
            'label' => sr_message_account_label((string) ($recipient['display_name'] ?? ''), (int) $recipient['id'], $canViewMemberIdentifiers, $config, (string) ($recipient['status'] ?? '')),
        ];
    }
    if ($errors === [] && sr_message_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = '짧은 시간에 너무 많은 쪽지를 보냈습니다. 잠시 후 다시 시도해 주세요.';
    }

    if ($errors === [] && $recipients !== []) {
        try {
            $pdo->beginTransaction();
            foreach ($recipients as $recipient) {
                $messageId = sr_message_create($pdo, (int) $account['id'], (int) $recipient['id'], (string) $values['body_text']);
                $createdMessages[] = [
                    'id' => $messageId,
                    'recipient_account_id' => (int) $recipient['id'],
                    'recipient_account_hash' => sr_member_public_account_hash($config, (int) $recipient['id']),
                ];
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = '쪽지를 저장하지 못했습니다.';
        }
    }

    if ($errors === [] && $createdMessages !== []) {
        sr_message_record_rate_limit($pdo, (int) $account['id'], $settings);
        $messageIds = array_map(static fn (array $row): int => (int) $row['id'], $createdMessages);
        $senderLabel = sr_message_account_label((string) ($account['display_name'] ?? ''), (int) $account['id'], false, null, (string) ($account['status'] ?? ''));
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'message.sent',
            'target_type' => count($messageIds) === 1 ? 'message' : 'messages',
            'target_id' => count($messageIds) === 1 ? (string) $messageIds[0] : implode(',', array_map('strval', $messageIds)),
            'result' => 'success',
            'message' => 'Message sent.',
            'metadata' => [
                'recipient_account_hashes' => array_map(static fn (array $row): string => (string) $row['recipient_account_hash'], $createdMessages),
                'message_ids' => $messageIds,
                'policy_bypass' => sr_message_account_is_staff_bypass($pdo, (int) $account['id']),
            ],
        ]);
        foreach ($createdMessages as $createdMessage) {
            sr_message_create_account_notification(
                $pdo,
                (int) $createdMessage['recipient_account_id'],
                '새 쪽지가 도착했습니다.',
                $senderLabel . '님이 쪽지를 보냈습니다.',
                '/message?id=' . (string) $createdMessage['id'],
                (int) $account['id']
            );
        }
        $_SESSION['sr_message_notice'] = '쪽지를 보냈습니다.';
        sr_redirect('/messages?box=sent');
    }

    $_SESSION['sr_message_write_flash'] = [
        'errors' => $errors,
        'values' => $values,
    ];
    sr_redirect('/message/write');
}

include SR_ROOT . '/modules/message/views/message-write.php';
