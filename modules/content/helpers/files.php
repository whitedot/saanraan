<?php

declare(strict_types=1);

function sr_content_file_extension_mime_map(): array
{
    return [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'hwp' => ['application/x-hwp', 'application/haansofthwp'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

function sr_content_file_allowed_extensions(): array
{
    return array_keys(sr_content_file_extension_mime_map());
}

function sr_content_file_mime_types_for_extensions(array $extensions): array
{
    $map = sr_content_file_extension_mime_map();
    $mimeTypes = [];
    foreach (sr_upload_normalize_extensions($extensions) as $extension) {
        foreach ($map[$extension] ?? [] as $mimeType) {
            $mimeTypes[$mimeType] = true;
        }
    }

    return array_keys($mimeTypes);
}

function sr_content_file_upload_max_bytes(): int
{
    return 20971520;
}

function sr_content_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_content_file_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_content_file_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()), true);
}

function sr_content_file_storage_driver(array $file): string
{
    $driver = strtolower((string) ($file['storage_driver'] ?? 'local'));
    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_content_file_storage_key(array $file): string
{
    $key = (string) ($file['storage_key'] ?? '');
    if ($key !== '' && sr_storage_key_is_safe($key)) {
        return $key;
    }

    $storagePath = (string) ($file['storage_path'] ?? '');
    if (str_starts_with($storagePath, SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $storagePath = substr($storagePath, strlen(SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR));
    } elseif (str_starts_with($storagePath, 'storage/')) {
        $storagePath = substr($storagePath, strlen('storage/'));
    }

    $storagePath = str_replace('\\', '/', ltrim($storagePath, '/'));
    return sr_storage_key_is_safe($storagePath) ? $storagePath : '';
}

function sr_content_file_path(array $file): ?string
{
    $driver = sr_content_file_storage_driver($file);
    $key = sr_content_file_storage_key($file);
    if ($driver === 'local' && $key !== '') {
        return sr_storage_local_path($key);
    }

    return null;
}

function sr_content_files_for_content(PDO $pdo, int $pageId): array
{
    if ($pageId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_files
         WHERE content_id = :content_id
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 50"
    );
    $stmt->execute(['content_id' => $pageId]);

    return $stmt->fetchAll();
}

function sr_content_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.*, p.slug, p.title AS content_title, p.status AS content_status
         FROM sr_content_files f
         INNER JOIN sr_content_items p ON p.id = f.content_id
         WHERE f.id = :id
           AND f.status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_published_file_by_id(PDO $pdo, int $fileId): ?array
{
    $file = sr_content_file_by_id($pdo, $fileId);
    if (!is_array($file) || (string) ($file['content_status'] ?? '') !== 'published') {
        return null;
    }

    return $file;
}

function sr_content_normalize_file_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = sr_content_asset_module_value_from_keys(sr_content_asset_module_keys_from_value($values['asset_module'] ?? ''));
    $downloadAmounts = sr_content_asset_amounts_from_value($values['asset_download_amounts_json'] ?? '', sr_content_asset_module_keys_from_value($assetModule), (int) ($values['asset_download_amount'] ?? 0));

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_content_asset_download_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_download_enabled'] = (int) ($values['asset_download_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_download_amount'] = sr_content_asset_amount_total($downloadAmounts, max(0, (int) ($values['asset_download_amount'] ?? 0)));
    $values['asset_download_amounts_json'] = sr_content_asset_amounts_json_from_map($downloadAmounts);
    $values['asset_download_group_policies_json'] = sr_content_asset_group_policy_json_from_value($values['asset_download_group_policies_json'] ?? '');
    $values['asset_download_policy_set_id'] = max(0, (int) ($values['asset_download_policy_set_id'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    return $values;
}

function sr_content_file_asset_validation_errors(PDO $pdo, array $values, string $labelPrefix = '파일 다운로드'): array
{
    $errors = [];
    if ((int) ($values['asset_download_enabled'] ?? 0) !== 1) {
        return [];
    }

    $assetModules = sr_content_asset_module_keys_from_value($values['asset_module'] ?? '');
    if ($assetModules === []) {
        $errors[] = $labelPrefix . ' 자산이 올바르지 않습니다.';
    } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
        $errors[] = '선택한 자산 모듈이 모두 활성 상태일 때만 ' . $labelPrefix . ' 자산으로 사용할 수 있습니다.';
    }

    $amount = (int) ($values['asset_download_amount'] ?? 0);
    if ($amount < 1 || $amount > 999999999) {
        $errors[] = $labelPrefix . ' 금액은 1부터 999999999 사이로 입력하세요.';
    }
    $amounts = sr_content_asset_amounts_from_value($values['asset_download_amounts_json'] ?? '', $assetModules);
    if (count($amounts) < count($assetModules)) {
        $errors[] = $labelPrefix . ' 자산별 금액은 선택한 자산마다 1 이상으로 입력하세요.';
    }

    if (!isset(sr_content_asset_download_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
        $errors[] = $labelPrefix . ' 과금 방식이 올바르지 않습니다.';
    }
    $assetDownloadPolicySetIds = sr_content_asset_policy_set_ids_with_legacy($values['asset_download_group_policies_json'] ?? '', (int) ($values['asset_download_policy_set_id'] ?? 0));
    $errors = array_merge($errors, sr_content_asset_policy_set_ids_validation_errors($pdo, $assetDownloadPolicySetIds, $labelPrefix));
    $errors = array_merge($errors, sr_content_asset_policy_set_asset_match_errors($pdo, $assetDownloadPolicySetIds, $assetModules, $labelPrefix));

    $errors = array_merge($errors, sr_admin_asset_group_policy_validation_errors($pdo, sr_content_asset_group_policies_from_value($values['asset_download_group_policies_json'] ?? ''), $labelPrefix));

    return $errors;
}

function sr_content_validate_file_request(PDO $pdo, int $pageId, array $pageValues = []): array
{
    $errors = [];
    $existingIds = $_POST['content_file_ids'] ?? [];
    if (is_array($existingIds)) {
        foreach ($existingIds as $rawFileId) {
            $fileId = (int) $rawFileId;
            if ($fileId < 1) {
                continue;
            }

            $file = sr_content_file_by_id($pdo, $fileId);
            if (!is_array($file) || (int) $file['content_id'] !== $pageId) {
                $errors[] = '수정할 콘텐츠 파일을 확인할 수 없습니다.';
                continue;
            }

            $values = sr_content_file_values_from_post($fileId);
            $errors = array_merge($errors, sr_content_file_asset_validation_errors($pdo, $values));
        }
    }

    $upload = $_FILES['content_file_upload'] ?? null;
    if (sr_content_file_upload_was_provided($upload)) {
        try {
            sr_upload_validate_file($upload, [
                'max_bytes' => sr_content_file_upload_max_bytes(),
                'allowed_extensions' => sr_content_file_allowed_extensions(),
                'allowed_mime_types' => sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()),
            ]);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $values = sr_content_new_file_values_from_post($pdo, $pageValues);
        $errors = array_merge($errors, sr_content_file_asset_validation_errors($pdo, $values, '새 파일 다운로드'));
    }

    return $errors;
}

function sr_content_file_values_from_post(int $fileId): array
{
    $titleValues = is_array($_POST['content_file_title'] ?? null) ? $_POST['content_file_title'] : [];
    $enabledValues = is_array($_POST['content_file_asset_download_enabled'] ?? null) ? $_POST['content_file_asset_download_enabled'] : [];
    $moduleValues = is_array($_POST['content_file_asset_module'] ?? null) ? $_POST['content_file_asset_module'] : [];
    $amountValues = is_array($_POST['content_file_asset_download_amount'] ?? null) ? $_POST['content_file_asset_download_amount'] : [];
    $amountsValues = is_array($_POST['content_file_asset_download_amounts'] ?? null) ? $_POST['content_file_asset_download_amounts'] : [];
    $groupPolicyValues = is_array($_POST['content_file_asset_download_group_policies'] ?? null) ? $_POST['content_file_asset_download_group_policies'] : [];
    $policySetValues = is_array($_POST['content_file_asset_download_policy_set_ids'] ?? null) ? $_POST['content_file_asset_download_policy_set_ids'] : [];
    $policyValues = is_array($_POST['content_file_asset_charge_policy'] ?? null) ? $_POST['content_file_asset_charge_policy'] : [];
    $assetModules = sr_content_asset_module_keys_from_value($moduleValues[$fileId] ?? '');
    $fallbackAmount = (int) ($amountValues[$fileId] ?? 0);
    $postedAmounts = is_array($amountsValues[$fileId] ?? null) ? $amountsValues[$fileId] : null;
    $policySetIds = sr_content_asset_policy_set_ids_from_value($policySetValues[$fileId] ?? []);

    return sr_content_normalize_file_asset_values([
        'title' => sr_content_clean_single_line((string) ($titleValues[$fileId] ?? ''), 160),
        'asset_download_enabled' => (string) ($enabledValues[$fileId] ?? '') === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys($assetModules),
        'asset_download_amount' => $fallbackAmount,
        'asset_download_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_value(is_array($postedAmounts) ? $postedAmounts : [], $assetModules, is_array($postedAmounts) ? 0 : $fallbackAmount)),
        'asset_download_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($policySetIds),
        'asset_download_policy_set_id' => sr_content_asset_policy_set_first_id($policySetIds),
        'asset_charge_policy' => sr_content_clean_slug((string) ($policyValues[$fileId] ?? '')),
    ], false);
}

function sr_content_file_asset_values_from_group(PDO $pdo, int $groupId): array
{
    return sr_content_normalize_file_asset_values([
        'asset_download_enabled' => (int) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_enabled') ?? 0),
        'asset_module' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_module') ?? ''),
        'asset_download_amount' => (int) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_amount') ?? 0),
        'asset_download_amounts_json' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_amounts_json') ?? ''),
        'asset_download_group_policies_json' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_group_policies_json') ?? ''),
        'asset_download_policy_set_id' => (int) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_download_policy_set_id') ?? 0),
        'asset_charge_policy' => (string) (sr_content_group_setting_value($pdo, $groupId, 'file_asset_charge_policy') ?? 'once'),
    ]);
}

function sr_content_new_file_values_from_post(?PDO $pdo = null, array $pageValues = []): array
{
    $assetModules = sr_content_asset_module_keys_from_value($_POST['new_content_file_asset_module'] ?? '');
    $fallbackAmount = sr_admin_post_int_in_range('new_content_file_asset_download_amount', 0, 999999999) ?? 0;
    $policySetIds = sr_content_asset_policy_set_ids_from_value($_POST['new_content_file_asset_download_policy_set_ids'] ?? []);
    $values = sr_content_normalize_file_asset_values([
        'title' => sr_content_clean_single_line(sr_post_string('new_content_file_title', 160), 160),
        'asset_download_enabled' => sr_post_string('new_content_file_asset_download_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_content_asset_module_value_from_keys($assetModules),
        'asset_download_amount' => $fallbackAmount,
        'asset_download_amounts_json' => sr_content_asset_amounts_json_from_map(sr_content_asset_amounts_from_post('new_content_file_asset_download_amounts', $assetModules, $fallbackAmount)),
        'asset_download_group_policies_json' => sr_content_asset_policy_set_selection_json_from_ids($policySetIds),
        'asset_download_policy_set_id' => sr_content_asset_policy_set_first_id($policySetIds),
        'asset_charge_policy' => sr_content_clean_slug(sr_post_string('new_content_file_asset_charge_policy', 20)),
    ], false);

    return $values;
}

function sr_content_save_files_from_request(PDO $pdo, int $pageId, int $accountId, array $pageValues = []): void
{
    if ($pageId < 1) {
        return;
    }

    $deleteValues = is_array($_POST['content_file_delete'] ?? null) ? $_POST['content_file_delete'] : [];
    $existingIds = is_array($_POST['content_file_ids'] ?? null) ? $_POST['content_file_ids'] : [];
    foreach ($existingIds as $rawFileId) {
        $fileId = (int) $rawFileId;
        if ($fileId < 1) {
            continue;
        }

        $file = sr_content_file_by_id($pdo, $fileId);
        if (!is_array($file) || (int) $file['content_id'] !== $pageId) {
            continue;
        }

        if ((string) ($deleteValues[$fileId] ?? '') === '1') {
            sr_content_hide_file($pdo, $fileId);
            continue;
        }

        sr_content_update_file($pdo, $fileId, sr_content_file_values_from_post($fileId));
    }

    $upload = $_FILES['content_file_upload'] ?? null;
    if (sr_content_file_upload_was_provided($upload)) {
        sr_content_upload_file($pdo, $pageId, $accountId, $upload, sr_content_new_file_values_from_post($pdo, $pageValues));
    }
}

function sr_content_update_file(PDO $pdo, int $fileId, array $values): void
{
    $values = sr_content_normalize_file_asset_values($values);
    $title = (string) ($values['title'] ?? '');
    if ($title === '') {
        $title = '첨부 파일';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_content_files
         SET title = :title,
             asset_download_enabled = :asset_download_enabled,
             asset_module = :asset_module,
             asset_download_amount = :asset_download_amount,
             asset_download_amounts_json = :asset_download_amounts_json,
             asset_download_group_policies_json = :asset_download_group_policies_json,
             asset_download_policy_set_id = :asset_download_policy_set_id,
             asset_charge_policy = :asset_charge_policy,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => $title,
        'asset_download_enabled' => (int) $values['asset_download_enabled'],
        'asset_module' => (string) $values['asset_module'],
        'asset_download_amount' => (int) $values['asset_download_amount'],
        'asset_download_amounts_json' => (string) ($values['asset_download_amounts_json'] ?? '{}'),
        'asset_download_group_policies_json' => (string) ($values['asset_download_group_policies_json'] ?? ''),
        'asset_download_policy_set_id' => (int) ($values['asset_download_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) $values['asset_charge_policy'],
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_content_hide_file(PDO $pdo, int $fileId): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_content_files
         SET status = 'hidden', updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_content_upload_file(PDO $pdo, int $pageId, int $accountId, array $file, array $values): int
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_content_file_upload_max_bytes(),
        'allowed_extensions' => sr_content_file_allowed_extensions(),
        'allowed_mime_types' => sr_content_file_mime_types_for_extensions(sr_content_file_allowed_extensions()),
    ]);
    $values = sr_content_normalize_file_asset_values($values);

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    $sizeBytes = filesize((string) $validated['tmp_name']);
    if (!sr_content_file_mime_is_allowed($storedMimeType) || !is_int($sizeBytes)) {
        throw new RuntimeException('저장된 콘텐츠 파일 metadata를 확인할 수 없습니다.');
    }

    $storageKey = 'content/files/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'content_type' => $storedMimeType,
    ]);

    try {
        $title = (string) ($values['title'] ?? '');
        if ($title === '') {
            $title = (string) $validated['original_name'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO sr_content_files
                (content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount, asset_download_amounts_json, asset_download_group_policies_json, asset_download_policy_set_id, asset_charge_policy, created_by, created_at, updated_at)
             VALUES
                (:content_id, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, 'active', :asset_download_enabled, :asset_module, :asset_download_amount, :asset_download_amounts_json, :asset_download_group_policies_json, :asset_download_policy_set_id, :asset_charge_policy, :created_by, :created_at, :updated_at)"
        );
        $now = sr_now();
        $stmt->execute([
            'content_id' => $pageId,
            'title' => $title,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => (string) $validated['checksum'],
            'asset_download_enabled' => (int) $values['asset_download_enabled'],
            'asset_module' => (string) $values['asset_module'],
            'asset_download_amount' => (int) $values['asset_download_amount'],
            'asset_download_amounts_json' => (string) ($values['asset_download_amounts_json'] ?? '{}'),
            'asset_download_group_policies_json' => (string) ($values['asset_download_group_policies_json'] ?? ''),
            'asset_download_policy_set_id' => (int) ($values['asset_download_policy_set_id'] ?? 0),
            'asset_charge_policy' => (string) $values['asset_charge_policy'],
            'created_by' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        sr_storage_delete((string) $stored['driver'], $storageKey);
        throw $exception;
    }
}
