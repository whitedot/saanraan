<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, $account);
$settings = sr_community_settings($pdo);
$memberSettings = sr_member_settings($pdo);
if (!sr_community_messages_enabled($pdo, $settings)) {
    sr_render_error(403, sr_t('community::action.error.message_disabled'));
}
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
    'recipient_account_hashes' => is_array($presetRecipient) && (string) $presetRecipient['status'] === 'active' ? [(string) $presetRecipient['public_hash']] : [],
    'recipient_identifier' => '',
    'body_text' => '',
];
$recipientPresetNotice = $values['recipient_account_hash'] !== '' ? sr_t('community::action.notice.recipient_preset') : '';
$recipientLabel = $values['recipient_account_hash'] !== '' && is_array($presetRecipient)
    ? sr_community_message_account_label((string) $presetRecipient['display_name'], (int) $presetRecipient['id'], $canViewMemberIdentifiers, $config, (string) $presetRecipient['status'], (string) ($presetRecipient['community_nickname'] ?? ''), $memberSettings)
    : '';
$recipientPickerItems = $values['recipient_account_hash'] !== '' && $recipientLabel !== ''
    ? [[
        'hash' => $values['recipient_account_hash'],
        'label' => $recipientLabel,
    ]]
    : [];

$messageWriteFlash = isset($_SESSION['sr_community_message_write_flash']) && is_array($_SESSION['sr_community_message_write_flash'])
    ? $_SESSION['sr_community_message_write_flash']
    : [];
unset($_SESSION['sr_community_message_write_flash']);
if ($messageWriteFlash !== []) {
    $flashValues = is_array($messageWriteFlash['values'] ?? null) ? $messageWriteFlash['values'] : [];
    $values = array_merge($values, array_intersect_key($flashValues, $values));
    $errors = isset($messageWriteFlash['errors']) && is_array($messageWriteFlash['errors'])
        ? array_values(array_filter(array_map('strval', $messageWriteFlash['errors']), static fn (string $error): bool => $error !== ''))
        : [];
    $recipientPresetNotice = '';
    $recipientPickerItems = [];
    $recipientLabels = [];
    foreach (is_array($values['recipient_account_hashes'] ?? null) ? $values['recipient_account_hashes'] : [] as $recipientHash) {
        $recipientHash = strtolower(trim((string) $recipientHash));
        if (!sr_member_public_account_hash_is_valid($recipientHash)) {
            continue;
        }
        $recipientSummary = sr_community_public_account_summary_by_hash($pdo, $config, $recipientHash);
        if (!is_array($recipientSummary) || (string) ($recipientSummary['status'] ?? '') !== 'active') {
            continue;
        }
        $label = sr_community_message_account_label((string) ($recipientSummary['display_name'] ?? ''), (int) $recipientSummary['id'], $canViewMemberIdentifiers, $config, (string) ($recipientSummary['status'] ?? ''), (string) ($recipientSummary['community_nickname'] ?? ''), $memberSettings);
        $recipientLabels[] = $label;
        $recipientPickerItems[] = [
            'hash' => $recipientHash,
            'label' => $label,
        ];
    }
    $recipientLabel = implode(', ', $recipientLabels);
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $values = sr_community_message_input_values();
    $errors = sr_community_validate_message_input($values);
    $recipients = [];
    $messageIds = [];
    $createdMessages = [];
    $recipientPickerItems = [];
    if ($errors === []) {
        $recipients = sr_community_message_recipients_from_values($pdo, $config, $values, (int) $account['id']);
        if ($recipients === []) {
            $errors[] = sr_t('community::action.error.recipient_not_found');
        }
        foreach ($recipients as $recipient) {
            if ((int) ($recipient['id'] ?? 0) === (int) $account['id']) {
                $errors[] = sr_t('community::action.error.message_self_forbidden');
                break;
            }
        }
    }
    $recipientLabels = [];
    foreach ($recipients as $recipient) {
        $recipientSummary = sr_community_public_account_summary($pdo, (int) $recipient['id']);
        $recipientLabelAccount = is_array($recipientSummary) ? $recipientSummary : $recipient;
        $label = sr_community_message_account_label((string) ($recipientLabelAccount['display_name'] ?? ''), (int) $recipient['id'], $canViewMemberIdentifiers, $config, (string) ($recipientLabelAccount['status'] ?? $recipient['status'] ?? ''), (string) ($recipientLabelAccount['community_nickname'] ?? ''), $memberSettings);
        $recipientLabels[] = $label;
        $recipientPickerItems[] = [
            'hash' => sr_member_public_account_hash($config, (int) $recipient['id']),
            'label' => $label,
        ];
    }
    $recipientLabel = implode(', ', $recipientLabels);

    if ($errors === [] && sr_community_message_rate_limited($pdo, (int) $account['id'], $settings)) {
        $errors[] = sr_t('community::action.rate_limit.message');
    }

    $messageChargeConfig = sr_community_asset_event_config($pdo, [], $settings, 'message_charge', 'every_action');
    if ($errors === [] && sr_community_asset_event_required($messageChargeConfig)) {
        $assetModules = sr_community_asset_module_keys_from_value($messageChargeConfig['asset_module'] ?? '', true);
        if (!sr_community_asset_modules_available($pdo, $assetModules)) {
            $errors[] = sr_t('community::action.error.message_asset_modules_unavailable');
        } else {
            $precheckChargeConfig = $messageChargeConfig;
            $recipientCount = count($recipients);
            $precheckChargeConfig['amount'] = (int) ($messageChargeConfig['amount'] ?? 0) * $recipientCount;
            if (is_array($messageChargeConfig['amounts'] ?? null) && $messageChargeConfig['amounts'] !== []) {
                $precheckChargeConfig['amounts'] = array_map(static fn (mixed $amount): int => (int) $amount * $recipientCount, (array) $messageChargeConfig['amounts']);
            }
            if (!sr_community_asset_use_balance_available($pdo, $precheckChargeConfig, (int) $account['id'])) {
                $errors[] = sr_community_asset_config_balance_shortage_message($pdo, $precheckChargeConfig, (int) $account['id'], '쪽지를 보낼 수 없습니다.', sr_t('community::action.error.message_asset_balance_low'));
            }
        }
    }

    if ($errors === [] && $recipients !== []) {
        $messageIds = [];
        $createdMessages = [];
        try {
            $pdo->beginTransaction();
            foreach ($recipients as $recipient) {
                $messageId = sr_community_create_message($pdo, (int) $account['id'], (int) $recipient['id'], (string) $values['body_text']);
                $messageChargeResult = sr_community_asset_event_required($messageChargeConfig)
                    ? sr_community_run_asset_event($pdo, $messageChargeConfig, (int) $account['id'], 'message_send_charge', 'community.message', $messageId, 'use', 'community.message.send')
                    : ['allowed' => true, 'processed' => false];
                if (empty($messageChargeResult['allowed'])) {
                    throw new RuntimeException((string) ($messageChargeResult['message'] ?? sr_t('community::action.error.message_charge_failed')));
                }
                $messageIds[] = $messageId;
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
            $errors[] = $exception->getMessage() !== '' ? $exception->getMessage() : sr_t('community::action.error.message_charge_failed');
        }
    }

    if ($errors === [] && $createdMessages !== []) {
        sr_community_record_message_rate_limit($pdo, (int) $account['id'], $settings);
        $senderLabel = sr_community_message_account_label(
            (string) ($account['display_name'] ?? ''),
            (int) $account['id'],
            false,
            null,
            (string) ($account['status'] ?? ''),
            sr_community_member_nickname($pdo, (int) $account['id']),
            $memberSettings
        );
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'member',
            'event_type' => 'community.message.sent',
            'target_type' => count($messageIds) === 1 ? 'community_message' : 'community_messages',
            'target_id' => count($messageIds) === 1 ? (string) $messageIds[0] : implode(',', array_map('strval', $messageIds)),
            'result' => 'success',
            'message' => 'Community message sent.',
            'metadata' => [
                'recipient_account_hashes' => array_map(static fn (array $row): string => (string) $row['recipient_account_hash'], $createdMessages),
                'message_ids' => $messageIds,
            ],
        ]);
        foreach ($createdMessages as $createdMessage) {
            sr_community_create_account_notification(
                $pdo,
                (int) $createdMessage['recipient_account_id'],
                sr_t('community::notification.message.title'),
                sr_t('community::notification.message.body', [
                    'account' => $senderLabel,
                ]),
                '/community/message?id=' . (string) $createdMessage['id'],
                (int) $account['id']
            );
        }
        $_SESSION['sr_community_message_notice'] = sr_t('community::action.notice.message_sent');
        sr_redirect('/community/messages?box=sent');
    }

    $_SESSION['sr_community_message_write_flash'] = [
        'errors' => $errors,
        'values' => $values,
    ];
    sr_redirect('/community/message/write');
}

include SR_ROOT . '/modules/community/views/message-write.php';
