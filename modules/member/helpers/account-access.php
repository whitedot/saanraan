<?php

declare(strict_types=1);

function sr_member_account_access_session_key(): string
{
    return 'sr_member_account_reauth';
}

function sr_member_account_access_state(int $accountId, string $sessionTokenHash): array
{
    $state = $_SESSION[sr_member_account_access_session_key()] ?? null;
    if (
        $accountId < 1
        || $sessionTokenHash === ''
        || !is_array($state)
        || (int) ($state['account_id'] ?? 0) !== $accountId
        || !isset($state['session_token_hash'])
        || !is_string($state['session_token_hash'])
        || !hash_equals($sessionTokenHash, $state['session_token_hash'])
    ) {
        unset($_SESSION[sr_member_account_access_session_key()]);
        return [];
    }

    return $state;
}

function sr_member_account_access_remember_credential(int $accountId, string $sessionTokenHash, string $method): void
{
    if ($accountId < 1 || $sessionTokenHash === '') {
        return;
    }

    $method = strtolower(trim($method));
    if (!in_array($method, ['password', 'totp', 'backup', 'authenticated_session'], true)) {
        $method = 'authenticated_session';
    }

    $_SESSION[sr_member_account_access_session_key()] = [
        'account_id' => $accountId,
        'session_token_hash' => $sessionTokenHash,
        'credential_method' => $method,
        'credential_verified_at' => time(),
        'completed_at' => 0,
    ];
}

function sr_member_account_access_credential_verified(array $state): bool
{
    return (int) ($state['credential_verified_at'] ?? 0) > 0;
}

function sr_member_account_access_complete(int $accountId, string $sessionTokenHash): void
{
    $state = sr_member_account_access_state($accountId, $sessionTokenHash);
    if (!sr_member_account_access_credential_verified($state)) {
        return;
    }

    $state['completed_at'] = time();
    $_SESSION[sr_member_account_access_session_key()] = $state;
}

function sr_member_account_access_completed(array $state): bool
{
    return sr_member_account_access_credential_verified($state)
        && (int) ($state['completed_at'] ?? 0) > 0;
}

function sr_member_account_access_path(string $path): string
{
    $path = trim($path);
    $allowedPaths = [
        '/account',
        '/mypage',
        '/mypage/account',
        '/mypage/profile',
        '/mypage/security',
        '/mypage/privacy',
    ];

    return in_array($path, $allowedPaths, true) ? $path : '/mypage';
}

function sr_member_account_access_remember_next_path(string $path): void
{
    $_SESSION['sr_member_account_reauth_next'] = sr_member_account_access_path($path);
}

function sr_member_account_access_take_next_path(): string
{
    $path = sr_member_account_access_path((string) ($_SESSION['sr_member_account_reauth_next'] ?? '/mypage'));
    unset($_SESSION['sr_member_account_reauth_next']);

    return $path;
}
