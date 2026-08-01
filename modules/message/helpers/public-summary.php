<?php

declare(strict_types=1);

function sr_message_public_summary_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_message_public_summary_settings(PDO $pdo): array
{
    if (function_exists('sr_message_settings')) {
        return sr_message_settings($pdo);
    }

    $metadata = sr_module_metadata('message');
    $defaults = is_array($metadata['settings'] ?? null) ? $metadata['settings'] : [];

    return array_merge($defaults, sr_module_settings($pdo, 'message'));
}

function sr_message_enabled(PDO $pdo, ?array $settings = null): bool
{
    $settings = is_array($settings) ? $settings : sr_message_public_summary_settings($pdo);

    return sr_message_public_summary_bool($settings['message_enabled'] ?? true);
}

function sr_message_unread_count(PDO $pdo, int $accountId): int
{
    if ($accountId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_messages
         WHERE recipient_account_id = :account_id
           AND recipient_deleted_at IS NULL
           AND read_at IS NULL'
    );
    $stmt->execute(['account_id' => $accountId]);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_message_public_summary_context(PDO $pdo, int $accountId): array
{
    $enabled = sr_message_enabled($pdo);

    return [
        'enabled' => $enabled,
        'unread_count' => $enabled ? sr_message_unread_count($pdo, $accountId) : 0,
    ];
}
