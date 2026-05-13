<?php

declare(strict_types=1);

function sr_member_empty_profile(): array
{
    return [
        'nickname' => '',
        'phone' => '',
        'birth_date' => '',
        'avatar_path' => '',
        'profile_text' => '',
    ];
}

function sr_member_profile(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT nickname, phone, birth_date, avatar_path, profile_text
         FROM sr_member_profiles
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $profile = $stmt->fetch();

    if (!is_array($profile)) {
        return sr_member_empty_profile();
    }

    return [
        'nickname' => (string) $profile['nickname'],
        'phone' => (string) $profile['phone'],
        'birth_date' => is_string($profile['birth_date']) ? $profile['birth_date'] : '',
        'avatar_path' => (string) $profile['avatar_path'],
        'profile_text' => (string) ($profile['profile_text'] ?? ''),
    ];
}

function sr_member_save_profile(PDO $pdo, int $accountId, array $profile): void
{
    $now = sr_now();
    $birthDate = trim((string) ($profile['birth_date'] ?? ''));
    if ($birthDate === '') {
        $birthDate = null;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_member_profiles
            (account_id, nickname, phone, birth_date, avatar_path, profile_text, created_at, updated_at)
         VALUES
            (:account_id, :nickname, :phone, :birth_date, :avatar_path, :profile_text, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            nickname = VALUES(nickname),
            phone = VALUES(phone),
            birth_date = VALUES(birth_date),
            avatar_path = VALUES(avatar_path),
            profile_text = VALUES(profile_text),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'nickname' => trim((string) ($profile['nickname'] ?? '')),
        'phone' => trim((string) ($profile['phone'] ?? '')),
        'birth_date' => $birthDate,
        'avatar_path' => trim((string) ($profile['avatar_path'] ?? '')),
        'profile_text' => trim((string) ($profile['profile_text'] ?? '')),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function sr_member_delete_profile(PDO $pdo, int $accountId): void
{
    $stmt = $pdo->prepare('DELETE FROM sr_member_profiles WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
}
