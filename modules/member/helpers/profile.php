<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_member_empty_profile(): array
{
    return [
        'phone' => '',
        'birth_date' => '',
        'avatar_path' => '',
        'profile_text' => '',
    ];
}

function sr_member_profile(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT phone, birth_date, avatar_path, profile_text
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
            (account_id, phone, birth_date, avatar_path, profile_text, created_at, updated_at)
         VALUES
            (:account_id, :phone, :birth_date, :avatar_path, :profile_text, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            phone = VALUES(phone),
            birth_date = VALUES(birth_date),
            avatar_path = VALUES(avatar_path),
            profile_text = VALUES(profile_text),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
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

function sr_member_profile_values_from_post(array $policies, array $baseProfile): array
{
    $profile = array_merge(sr_member_empty_profile(), $baseProfile);
    if (!empty($policies['phone']['visible'])) {
        $profile['phone'] = sr_post_string('phone', 40);
    }
    if (!empty($policies['birth_date']['visible'])) {
        $profile['birth_date'] = sr_post_string('birth_date', 10);
    }
    if (!empty($policies['profile_text']['visible'])) {
        $profile['profile_text'] = sr_post_string('profile_text', 1000);
    }

    return $profile;
}

function sr_member_profile_validation_errors(array $profile, array $policies, array $options = []): array
{
    $errors = [];
    $validateAvatar = (bool) ($options['validate_avatar'] ?? true);
    foreach ($policies as $field => $policy) {
        if (empty($policy['visible']) || empty($policy['required'])) {
            continue;
        }

        if ($field === 'avatar_path') {
            if ($validateAvatar && !sr_member_avatar_reference_is_valid((string) ($profile['avatar_path'] ?? ''))) {
                $errors[] = sr_t('member::profile.error.avatar_required');
            }
            continue;
        }

        if (trim((string) ($profile[$field] ?? '')) === '') {
            $errors[] = sr_member_profile_required_message((string) $field);
        }
    }

    if (!empty($policies['birth_date']['visible'])) {
        $birthDate = (string) ($profile['birth_date'] ?? '');
        if ($birthDate !== '' && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $birthDate) !== 1) {
            $errors[] = sr_t('member::profile.error.birth_date_format');
        } elseif ($birthDate !== '') {
            $birthParts = explode('-', $birthDate);
            if (!checkdate((int) $birthParts[1], (int) $birthParts[2], (int) $birthParts[0])) {
                $errors[] = sr_t('member::profile.error.birth_date_invalid');
            }
        }
    }

    if ($validateAvatar && !empty($policies['avatar_path']['visible'])) {
        $avatarPath = (string) ($profile['avatar_path'] ?? '');
        if ($avatarPath !== '' && !sr_member_avatar_reference_is_valid($avatarPath)) {
            $errors[] = sr_t('member::profile.error.avatar_reupload');
        }
    }

    return array_values(array_unique($errors));
}

function sr_member_profile_required_message(string $field): string
{
    return match ($field) {
        'phone' => sr_t('member::profile.error.phone_required'),
        'birth_date' => sr_t('member::profile.error.birth_date_required'),
        'profile_text' => sr_t('member::profile.error.profile_text_required'),
        default => sr_t('member::profile.error.required'),
    };
}

function sr_member_avatar_upload_max_bytes(): int
{
    return 2097152;
}

function sr_member_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_member_avatar_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_member_avatar_format_for_mime(string $mimeType): string
{
    return sr_image_format_for_mime($mimeType);
}

function sr_member_avatar_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), ['image/jpeg', 'image/png', 'image/webp'], true);
}

function sr_member_upload_avatar(array $file): ?array
{
    if (!sr_member_avatar_upload_was_provided($file)) {
        return null;
    }

    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_member_avatar_upload_max_bytes(),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ]);

    $targetFormat = sr_member_avatar_format_for_mime((string) $validated['mime_type']);
    if ($targetFormat === '') {
        throw new RuntimeException(sr_t('member::profile.error.avatar_type_disallowed'));
    }

    $datePath = date('Y/m');
    $directory = SR_ROOT . '/storage/tmp/member-avatars/' . $datePath;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException(sr_t('member::profile.error.avatar_temp_dir_failed'));
    }

    $storedName = sr_upload_random_filename($targetFormat);
    $targetPath = sr_upload_safe_target_path($directory, $storedName);
    sr_upload_assert_target_path_writable($targetPath);

    try {
        if (!sr_upload_reencode_image((string) $validated['tmp_name'], $targetPath, $targetFormat, [
            'max_pixels' => 12000000,
            'quality' => 85,
        ])) {
            throw new RuntimeException(sr_t('member::profile.error.avatar_reencode_failed'));
        }

        $imageInfo = @getimagesize($targetPath);
        $storedMimeType = sr_upload_detect_mime($targetPath);
        $checksum = hash_file('sha256', $targetPath);
        $sizeBytes = filesize($targetPath);
        if (!is_array($imageInfo) || !sr_member_avatar_mime_is_allowed($storedMimeType) || !is_string($checksum) || !is_int($sizeBytes)) {
            throw new RuntimeException(sr_t('member::profile.error.avatar_metadata_failed'));
        }

        $storageKey = 'member/avatars/' . $datePath . '/' . $storedName;
        $stored = sr_storage_put_file($targetPath, $storageKey, [
            'content_type' => $storedMimeType,
        ]);

        return [
            'driver' => (string) $stored['driver'],
            'key' => $storageKey,
            'reference' => sr_storage_reference((string) $stored['driver'], $storageKey),
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => $checksum,
            'width' => (int) $imageInfo[0],
            'height' => (int) $imageInfo[1],
        ];
    } finally {
        if (is_file($targetPath)) {
            @unlink($targetPath);
        }
    }
}

function sr_member_avatar_storage_key_is_valid(string $key): bool
{
    return preg_match('#\Amember/avatars/\d{4}/\d{2}/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $key) === 1;
}

function sr_member_avatar_storage_reference(string $reference): ?array
{
    $storage = sr_storage_parse_reference($reference);
    if (!is_array($storage) || !sr_member_avatar_storage_key_is_valid((string) $storage['key'])) {
        return null;
    }

    return $storage;
}

function sr_member_avatar_reference_is_valid(string $reference): bool
{
    return is_array(sr_member_avatar_storage_reference($reference));
}

function sr_member_avatar_url(string $reference): string
{
    $storage = sr_member_avatar_storage_reference($reference);
    if (!is_array($storage)) {
        return '';
    }

    return '/member/avatar?file=' . rawurlencode(sr_storage_reference((string) $storage['driver'], (string) $storage['key']));
}

function sr_member_avatar_src(string $reference): string
{
    $url = sr_member_avatar_url($reference);
    if ($url === '') {
        return '';
    }

    return sr_is_http_url($url) ? $url : sr_url($url);
}

function sr_member_default_avatar_color(string $publicHash): string
{
    $palette = sr_member_default_avatar_color_palette();
    return $palette[sr_member_default_avatar_color_index($publicHash)] ?? '#4f46e5';
}

function sr_member_default_avatar_color_class(string $publicHash): string
{
    return 'member-avatar-color-' . (string) sr_member_default_avatar_color_index($publicHash);
}

function sr_member_default_avatar_color_palette(): array
{
    return [
        '#b91c1c',
        '#c2410c',
        '#a16207',
        '#4d7c0f',
        '#047857',
        '#0f766e',
        '#0369a1',
        '#1d4ed8',
        '#4f46e5',
        '#7e22ce',
        '#be185d',
        '#9f1239',
    ];
}

function sr_member_default_avatar_color_index(string $publicHash): int
{
    $hashPrefix = strtolower(substr(trim($publicHash), 0, 6));
    if (preg_match('/\A[a-f0-9]{6}\z/', $hashPrefix) !== 1) {
        return 8;
    }

    $target = [
        hexdec(substr($hashPrefix, 0, 2)),
        hexdec(substr($hashPrefix, 2, 2)),
        hexdec(substr($hashPrefix, 4, 2)),
    ];
    $palette = sr_member_default_avatar_color_palette();

    $closestIndex = 0;
    $closestDistance = PHP_INT_MAX;
    foreach ($palette as $index => $color) {
        $colorPrefix = substr($color, 1);
        $candidate = [
            hexdec(substr($colorPrefix, 0, 2)),
            hexdec(substr($colorPrefix, 2, 2)),
            hexdec(substr($colorPrefix, 4, 2)),
        ];
        $distance = ($target[0] - $candidate[0]) ** 2
            + ($target[1] - $candidate[1]) ** 2
            + ($target[2] - $candidate[2]) ** 2;
        if ($distance < $closestDistance) {
            $closestDistance = $distance;
            $closestIndex = (int) $index;
        }
    }

    return $closestIndex;
}

function sr_member_delete_avatar_reference(string $reference): void
{
    $storage = sr_member_avatar_storage_reference($reference);
    if (is_array($storage)) {
        sr_storage_delete((string) $storage['driver'], (string) $storage['key']);
    }
}
