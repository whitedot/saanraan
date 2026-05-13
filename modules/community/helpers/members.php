<?php

declare(strict_types=1);

function sr_community_admin_can_view_member_identifiers(PDO $pdo, ?array $account): bool
{
    if (!is_array($account) || !function_exists('sr_admin_has_role')) {
        return false;
    }

    return sr_admin_has_role($pdo, (int) $account['id'], ['owner', 'admin', 'manager']);
}

function sr_community_member_identifier_suffix(array $config, int $accountId, bool $showIdentifier): string
{
    if (!$showIdentifier || $accountId < 1) {
        return '';
    }

    $publicHash = sr_member_public_account_hash($config, $accountId);
    if ($publicHash === '') {
        return ' (회원 ID #' . (string) $accountId . ')';
    }

    return ' (회원 ID #' . (string) $accountId . ' / 해시 ' . $publicHash . ')';
}

function sr_community_member_label_with_identifier(string $label, array $config, int $accountId, bool $showIdentifier): string
{
    return $label . sr_community_member_identifier_suffix($config, $accountId, $showIdentifier);
}
