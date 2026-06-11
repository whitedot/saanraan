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
        "SELECT DISTINCT f.*, :content_id_result AS content_id, l.sort_order AS link_sort_order
         FROM sr_content_files f
         LEFT JOIN sr_content_file_links l
           ON l.file_id = f.id
          AND l.content_id = :linked_content_id
          AND l.status = 'active'
         WHERE f.status = 'active'
           AND (l.id IS NOT NULL OR f.content_id = :legacy_content_id)
         ORDER BY COALESCE(l.sort_order, 0) ASC, f.id ASC
         LIMIT 50"
    );
    $stmt->execute([
        'content_id_result' => $pageId,
        'linked_content_id' => $pageId,
        'legacy_content_id' => $pageId,
    ]);

    return $stmt->fetchAll();
}

function sr_content_file_by_id(PDO $pdo, int $fileId, int $contentId = 0): ?array
{
    if ($fileId < 1) {
        return null;
    }

    if ($contentId > 0) {
        $stmt = $pdo->prepare(
            "SELECT f.*,
                    :content_id_result AS content_id,
                    p.slug,
                    p.title AS content_title,
                    p.status AS content_status
             FROM sr_content_files f
             INNER JOIN sr_content_items p ON p.id = :content_id
             LEFT JOIN sr_content_file_links l
               ON l.file_id = f.id
              AND l.content_id = p.id
              AND l.status = 'active'
             WHERE f.id = :id
               AND f.status = 'active'
               AND (l.id IS NOT NULL OR f.content_id = p.id)
             LIMIT 1"
        );
        $stmt->execute([
            'content_id_result' => $contentId,
            'content_id' => $contentId,
            'id' => $fileId,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.*,
                COALESCE(lp.first_content_id, l.first_content_id, f.content_id) AS content_id,
                p.slug,
                p.title AS content_title,
                p.status AS content_status
         FROM sr_content_files f
         LEFT JOIN (
            SELECT file_id, MIN(content_id) AS first_content_id
            FROM sr_content_file_links
            WHERE status = 'active'
            GROUP BY file_id
         ) l ON l.file_id = f.id
         LEFT JOIN (
            SELECT fl.file_id, MIN(fl.content_id) AS first_content_id
            FROM sr_content_file_links fl
            INNER JOIN sr_content_items ci ON ci.id = fl.content_id AND ci.status = 'published'
            WHERE fl.status = 'active'
            GROUP BY fl.file_id
         ) lp ON lp.file_id = f.id
         LEFT JOIN sr_content_items p ON p.id = COALESCE(lp.first_content_id, l.first_content_id, f.content_id)
         WHERE f.id = :id
           AND f.status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_published_file_by_id(PDO $pdo, int $fileId, int $contentId = 0): ?array
{
    $file = sr_content_file_by_id($pdo, $fileId, $contentId);
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
        $errors[] = $labelPrefix . ' 항목이 올바르지 않습니다.';
    } elseif (!sr_content_asset_modules_available($pdo, $assetModules)) {
        $errors[] = '선택한 포인트/금액 항목이 모두 활성 상태일 때만 ' . $labelPrefix . ' 항목으로 사용할 수 있습니다.';
    }

    $amount = (int) ($values['asset_download_amount'] ?? 0);
    if ($amount < 1 || $amount > 999999999) {
        $errors[] = $labelPrefix . ' 금액은 1부터 999999999 사이로 입력하세요.';
    }
    $amounts = sr_content_asset_amounts_from_value($values['asset_download_amounts_json'] ?? '', $assetModules);
    if (count($amounts) < count($assetModules)) {
        $errors[] = $labelPrefix . ' 항목별 금액은 선택한 항목마다 1 이상으로 입력하세요.';
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
    foreach (sr_content_file_link_ids_from_post('content_file_link_ids') as $fileId) {
        $file = sr_content_file_by_id($pdo, $fileId);
        if (!is_array($file)) {
            $errors[] = '연결할 다운로드 파일을 확인할 수 없습니다.';
        }
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

    sr_content_replace_file_links($pdo, $pageId, sr_content_file_link_ids_from_post('content_file_link_ids'));
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
                (content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount, asset_download_settlement_currency, asset_download_amounts_json, asset_download_group_policies_json, asset_download_policy_set_id, asset_charge_policy, created_by, created_at, updated_at)
             VALUES
                (:content_id, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, 'active', :asset_download_enabled, :asset_module, :asset_download_amount, :asset_download_settlement_currency, :asset_download_amounts_json, :asset_download_group_policies_json, :asset_download_policy_set_id, :asset_charge_policy, :created_by, :created_at, :updated_at)"
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
            'asset_download_settlement_currency' => sr_site_default_currency($pdo),
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

function sr_content_file_link_ids_from_post(string $field): array
{
    $rawValues = $_POST[$field] ?? [];
    if (!is_array($rawValues)) {
        return [];
    }

    $ids = [];
    foreach ($rawValues as $rawValue) {
        $fileId = (int) $rawValue;
        if ($fileId > 0) {
            $ids[$fileId] = $fileId;
        }
    }

    return array_values($ids);
}

function sr_content_replace_file_links(PDO $pdo, int $pageId, array $fileIds): void
{
    if ($pageId < 1) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_content_file_links
         SET status = 'hidden', updated_at = :updated_at
         WHERE content_id = :content_id"
    );
    $stmt->execute([
        'updated_at' => $now,
        'content_id' => $pageId,
    ]);

    $stmt = $pdo->prepare(
        "INSERT INTO sr_content_file_links
            (content_id, file_id, sort_order, status, created_at, updated_at)
         VALUES
            (:content_id, :file_id, :sort_order, 'active', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            sort_order = VALUES(sort_order),
            status = 'active',
            updated_at = VALUES(updated_at)"
    );
    $sortOrder = 0;
    foreach ($fileIds as $fileId) {
        if (!is_array(sr_content_file_by_id($pdo, (int) $fileId))) {
            continue;
        }
        $stmt->execute([
            'content_id' => $pageId,
            'file_id' => (int) $fileId,
            'sort_order' => $sortOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sortOrder += 10;
    }
}

function sr_content_admin_download_file_count(PDO $pdo, array $filters): int
{
    $where = sr_content_admin_download_file_where_sql($filters);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS count_value FROM sr_content_files f ' . $where['sql']);
    $stmt->execute($where['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_download_file_sort_options(): array
{
    return [
        'title' => [
            'label' => '파일 제목',
            'columns' => ['f.title', 'f.id'],
        ],
        'original_name' => [
            'label' => '원본 파일명',
            'columns' => ['f.original_name', 'f.id'],
        ],
        'status' => [
            'label' => '상태',
            'columns' => ['f.status', 'f.id'],
        ],
        'size_bytes' => [
            'label' => '크기',
            'columns' => ['f.size_bytes', 'f.id'],
        ],
        'linked_content_count' => [
            'label' => '연결',
            'columns' => ['linked_content_count', 'f.id'],
        ],
        'updated_at' => [
            'label' => '수정일',
            'columns' => ['f.updated_at', 'f.id'],
        ],
    ];
}

function sr_content_admin_download_file_default_sort(): array
{
    return sr_admin_sort_default('updated_at', 'desc');
}

function sr_content_admin_download_file_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'active' => 0,
        'hidden' => 0,
    ];
    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_content_files GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        $counts['total'] += $count;
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
    }

    return $counts;
}

function sr_content_admin_download_files(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    $where = sr_content_admin_download_file_where_sql($filters);
    $stmt = $pdo->prepare(
        'SELECT f.*,
                COUNT(DISTINCT CASE WHEN l.status = \'active\' THEN l.content_id END) AS linked_content_count
         FROM sr_content_files f
         LEFT JOIN sr_content_file_links l ON l.file_id = f.id
         ' . $where['sql'] . '
         GROUP BY f.id
         ' . sr_admin_sort_order_sql(sr_content_admin_download_file_sort_options(), $sort, sr_content_admin_download_file_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_admin_download_file_where_sql(array $filters): array
{
    $conditions = [];
    $params = [];
    $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    if ($statuses !== []) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $paramKey = 'status_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $status;
        }
        $conditions[] = 'f.status IN (' . implode(', ', $placeholders) . ')';
    }
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $conditions[] = '(f.title LIKE :q OR f.original_name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    return [
        'sql' => $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
    ];
}

function sr_content_admin_download_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_files WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_file_download_logs_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM sr_content_file_download_logs LIMIT 1');
        $exists = $stmt !== false;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_file_download_log_snapshot_columns_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS column_count
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'content_title_snapshot\', \'content_slug_snapshot\', \'file_title_snapshot\', \'file_original_name_snapshot\')'
        );
        $stmt->execute(['table_name' => 'sr_content_file_download_logs']);
        $row = $stmt->fetch();
        $exists = is_array($row) && (int) ($row['column_count'] ?? 0) === 4;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_record_file_download(PDO $pdo, array $file, ?int $accountId, array $accessResult = []): void
{
    if (!sr_content_file_download_logs_table_exists($pdo)) {
        return;
    }

    $accessLogIds = [];
    foreach ((array) ($accessResult['access_log_ids'] ?? []) as $accessLogId) {
        $accessLogId = (int) $accessLogId;
        if ($accessLogId > 0) {
            $accessLogIds[$accessLogId] = $accessLogId;
        }
    }
    $accessLogIdsJson = json_encode(array_values($accessLogIds), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $downloadType = (int) ($file['asset_download_enabled'] ?? 0) === 1 ? 'paid' : 'free';
    $amount = $downloadType === 'paid' && $accessLogIds !== [] ? (int) ($accessResult['amount'] ?? 0) : 0;
    $hasSnapshots = sr_content_file_download_log_snapshot_columns_exist($pdo);
    $snapshotColumnsSql = $hasSnapshots ? ', content_title_snapshot, content_slug_snapshot, file_title_snapshot, file_original_name_snapshot' : '';
    $snapshotValuesSql = $hasSnapshots ? ', :content_title_snapshot, :content_slug_snapshot, :file_title_snapshot, :file_original_name_snapshot' : '';

    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_file_download_logs
            (content_id, file_id, account_id, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, created_at' . $snapshotColumnsSql . ')
         VALUES
            (:content_id, :file_id, :account_id, :download_type, :charge_policy, :asset_module, :amount, :asset_access_log_ids_json, :created_at' . $snapshotValuesSql . ')'
    );
    $params = [
        'content_id' => (int) ($file['content_id'] ?? 0),
        'file_id' => (int) ($file['id'] ?? 0),
        'account_id' => $accountId !== null && $accountId > 0 ? $accountId : null,
        'download_type' => $downloadType,
        'charge_policy' => (string) ($file['asset_charge_policy'] ?? 'once'),
        'asset_module' => (string) ($accessResult['asset_module'] ?? $file['asset_module'] ?? ''),
        'amount' => $amount,
        'asset_access_log_ids_json' => is_string($accessLogIdsJson) ? $accessLogIdsJson : '[]',
        'created_at' => sr_now(),
    ];
    if ($hasSnapshots) {
        $params['content_title_snapshot'] = sr_content_clean_single_line((string) ($file['content_title'] ?? ''), 160);
        $params['content_slug_snapshot'] = sr_content_clean_slug((string) ($file['slug'] ?? ''));
        $params['file_title_snapshot'] = sr_content_clean_single_line((string) ($file['title'] ?? ''), 160);
        $params['file_original_name_snapshot'] = sr_content_clean_single_line((string) ($file['original_name'] ?? ''), 160);
    }
    $stmt->execute($params);
}

function sr_content_admin_file_download_log_sort_options(): array
{
    return [
        'created_at' => ['label' => '다운로드 시각', 'columns' => ['d.created_at', 'd.id']],
        'content_title' => ['label' => '콘텐츠', 'columns' => ['content_title', 'd.id']],
        'file_title' => ['label' => '파일', 'columns' => ['file_title', 'd.id']],
        'account_id' => ['label' => '회원', 'columns' => ['d.account_id', 'd.id']],
        'download_type' => ['label' => '구분', 'columns' => ['d.download_type', 'd.id']],
        'amount' => ['label' => '금액', 'columns' => ['d.amount', 'd.id']],
    ];
}

function sr_content_admin_file_download_log_default_sort(): array
{
    return sr_admin_sort_default('created_at', 'desc');
}

function sr_content_admin_file_download_log_where_sql(PDO $pdo, array $filters): array
{
    $conditions = [];
    $params = [];

    $contentId = (int) ($filters['content_id'] ?? 0);
    if ($contentId > 0) {
        $conditions[] = 'd.content_id = :content_id';
        $params['content_id'] = $contentId;
    }

    $fileId = (int) ($filters['file_id'] ?? 0);
    if ($fileId > 0) {
        $conditions[] = 'd.file_id = :file_id';
        $params['file_id'] = $fileId;
    }

    $accountId = (int) ($filters['account_id'] ?? 0);
    if ($accountId > 0) {
        $conditions[] = 'd.account_id = :account_id';
        $params['account_id'] = $accountId;
    }

    $downloadTypes = is_array($filters['download_type'] ?? null) ? $filters['download_type'] : [];
    if ($downloadTypes !== []) {
        $placeholders = [];
        foreach (array_values($downloadTypes) as $index => $downloadType) {
            $paramKey = 'download_type_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $downloadType;
        }
        $conditions[] = 'd.download_type IN (' . implode(', ', $placeholders) . ')';
    }

    $refundStatuses = is_array($filters['refund_status'] ?? null) ? $filters['refund_status'] : [];
    if ($refundStatuses !== []) {
        $refundConditions = [];
        foreach (array_values($refundStatuses) as $index => $refundStatus) {
            if ((string) $refundStatus === 'none') {
                $refundConditions[] = "d.refund_status = ''";
                continue;
            }
            $paramKey = 'refund_status_' . (string) $index;
            $refundConditions[] = 'd.refund_status = :' . $paramKey;
            $params[$paramKey] = (string) $refundStatus;
        }
        if ($refundConditions !== []) {
            $conditions[] = '(' . implode(' OR ', $refundConditions) . ')';
        }
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateFrom) === 1) {
        $conditions[] = 'd.created_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = (string) ($filters['date_to'] ?? '');
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateTo) === 1) {
        $conditions[] = 'd.created_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $snapshotSearchSql = sr_content_file_download_log_snapshot_columns_exist($pdo)
            ? ' OR d.content_title_snapshot LIKE :q OR d.content_slug_snapshot LIKE :q OR d.file_title_snapshot LIKE :q OR d.file_original_name_snapshot LIKE :q'
            : '';
        $conditions[] = '(p.title LIKE :q OR p.slug LIKE :q OR f.title LIKE :q OR f.original_name LIKE :q' . $snapshotSearchSql . ' OR a.email LIKE :q OR a.display_name LIKE :q)';
        $params['q'] = '%' . $q . '%';
    }

    return [
        'sql' => $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
    ];
}

function sr_content_admin_file_download_log_count(PDO $pdo, array $filters): int
{
    if (!sr_content_file_download_logs_table_exists($pdo)) {
        return 0;
    }

    $where = sr_content_admin_file_download_log_where_sql($pdo, $filters);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_content_file_download_logs d
         LEFT JOIN sr_content_items p ON p.id = d.content_id
         LEFT JOIN sr_content_files f ON f.id = d.file_id
         LEFT JOIN sr_member_accounts a ON a.id = d.account_id
         ' . $where['sql']
    );
    $stmt->execute($where['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_file_download_logs(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    if (!sr_content_file_download_logs_table_exists($pdo)) {
        return [];
    }

    $where = sr_content_admin_file_download_log_where_sql($pdo, $filters);
    $hasSnapshots = sr_content_file_download_log_snapshot_columns_exist($pdo);
    $contentTitleSelect = $hasSnapshots ? "COALESCE(NULLIF(p.title, ''), NULLIF(d.content_title_snapshot, ''))" : 'p.title';
    $contentSlugSelect = $hasSnapshots ? "COALESCE(NULLIF(p.slug, ''), NULLIF(d.content_slug_snapshot, ''))" : 'p.slug';
    $fileTitleSelect = $hasSnapshots ? "COALESCE(NULLIF(f.title, ''), NULLIF(d.file_title_snapshot, ''))" : 'f.title';
    $fileOriginalNameSelect = $hasSnapshots ? "COALESCE(NULLIF(f.original_name, ''), NULLIF(d.file_original_name_snapshot, ''))" : 'f.original_name';
    $stmt = $pdo->prepare(
        'SELECT d.*,
                ' . $contentTitleSelect . ' AS content_title,
                ' . $contentSlugSelect . ' AS content_slug,
                p.status AS content_status,
                ' . $fileTitleSelect . ' AS file_title,
                ' . $fileOriginalNameSelect . ' AS original_name,
                f.status AS file_status,
                a.email,
                a.display_name,
                rb.display_name AS refunded_by_display_name,
                GROUP_CONCAT(CONCAT(al.asset_module, ":", al.transaction_id, ":", al.amount, ":", al.settlement_amount, ":", al.settlement_currency, ":", al.settlement_kind, ":", al.snapshot_schema_version, ":", al.rounding_policy_version, ":", COALESCE(al.group_policy_snapshot_json, "")) ORDER BY al.id ASC SEPARATOR "\n") AS access_log_summary
         FROM sr_content_file_download_logs d
         LEFT JOIN sr_content_items p ON p.id = d.content_id
         LEFT JOIN sr_content_files f ON f.id = d.file_id
         LEFT JOIN sr_member_accounts a ON a.id = d.account_id
         LEFT JOIN sr_member_accounts rb ON rb.id = d.refunded_by_account_id
         LEFT JOIN sr_content_asset_access_logs al
           ON al.access_kind = \'download\'
          AND al.reference_type = \'content.download\'
          AND al.reference_id = CAST(d.file_id AS CHAR)
          AND al.account_id = d.account_id
          AND al.content_id = d.content_id
          AND (
                d.asset_access_log_ids_json = CONCAT(\'[\', al.id, \']\')
             OR d.asset_access_log_ids_json LIKE CONCAT(\'[\', al.id, \',%\')
             OR d.asset_access_log_ids_json LIKE CONCAT(\'%,\', al.id, \',%\')
             OR d.asset_access_log_ids_json LIKE CONCAT(\'%,\', al.id, \']\')
          )
         ' . $where['sql'] . '
         GROUP BY d.id
         ' . sr_admin_sort_order_sql(sr_content_admin_file_download_log_sort_options(), $sort, sr_content_admin_file_download_log_default_sort()) . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($where['params'] as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue('offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_admin_file_download_log_by_id_for_update(PDO $pdo, int $downloadLogId): ?array
{
    if ($downloadLogId <= 0 || !sr_content_file_download_logs_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_file_download_logs WHERE id = :id LIMIT 1 FOR UPDATE');
    $stmt->execute(['id' => $downloadLogId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_file_download_log_access_log_ids(array $downloadLog): array
{
    $decoded = json_decode((string) ($downloadLog['asset_access_log_ids_json'] ?? '[]'), true);
    if (!is_array($decoded)) {
        return [];
    }

    $ids = [];
    foreach ($decoded as $value) {
        $id = (int) $value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function sr_content_file_download_access_logs_for_refund(PDO $pdo, array $downloadLog): array
{
    $ids = sr_content_file_download_log_access_log_ids($downloadLog);
    if ($ids === []) {
        return [];
    }

    $placeholders = [];
    $params = [
        'content_id' => (int) ($downloadLog['content_id'] ?? 0),
        'file_id' => (string) (int) ($downloadLog['file_id'] ?? 0),
        'account_id' => (int) ($downloadLog['account_id'] ?? 0),
    ];
    foreach ($ids as $index => $id) {
        $key = 'id_' . (string) $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_asset_access_logs
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND content_id = :content_id
           AND reference_type = \'content.download\'
           AND reference_id = :file_id
           AND access_kind = \'download\'
           AND account_id = :account_id
         ORDER BY id ASC'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_refund_file_download(PDO $pdo, int $downloadLogId, int $adminAccountId, string $refundNote, string $refundExpirationPolicy = 'original'): array
{
    $refundNote = sr_content_clean_single_line($refundNote, 255);
    $refundExpirationPolicy = in_array($refundExpirationPolicy, ['original', 'reset'], true) ? $refundExpirationPolicy : 'original';
    if ($downloadLogId <= 0) {
        return ['ok' => false, 'message' => '환불할 다운로드 내역을 선택하세요.'];
    }
    if ($adminAccountId <= 0) {
        return ['ok' => false, 'message' => '처리 관리자 정보를 확인할 수 없습니다.'];
    }
    if ($refundNote === '') {
        return ['ok' => false, 'message' => '환불 사유를 입력하세요.'];
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $downloadLog = sr_content_admin_file_download_log_by_id_for_update($pdo, $downloadLogId);
        if (!is_array($downloadLog)) {
            throw new RuntimeException('환불할 다운로드 내역을 찾을 수 없습니다.');
        }
        if ((string) ($downloadLog['download_type'] ?? '') !== 'paid') {
            throw new RuntimeException('무료 다운로드는 환불 대상이 아닙니다.');
        }
        if ((string) ($downloadLog['refund_status'] ?? '') !== '') {
            throw new RuntimeException('이미 환불 또는 접근권 회수 처리된 다운로드입니다.');
        }

        $accountId = (int) ($downloadLog['account_id'] ?? 0);
        $contentId = (int) ($downloadLog['content_id'] ?? 0);
        $fileId = (int) ($downloadLog['file_id'] ?? 0);
        if ($accountId <= 0 || $contentId <= 0 || $fileId <= 0) {
            throw new RuntimeException('환불 대상 회원 또는 콘텐츠 파일 정보를 확인할 수 없습니다.');
        }

        if (sr_content_file_download_log_access_log_ids($downloadLog) === []) {
            throw new RuntimeException('연결된 차감 또는 접근권 로그가 없어 환불/회수할 수 없습니다.');
        }

        $accessLogs = sr_content_file_download_access_logs_for_refund($pdo, $downloadLog);
        if ($accessLogs === []) {
            throw new RuntimeException('연결된 차감 또는 접근권 로그를 찾을 수 없습니다.');
        }

        $refundTransactionIds = [];
        foreach ($accessLogs as $accessLog) {
            $amount = (int) ($accessLog['amount'] ?? 0);
            $transactionId = (int) ($accessLog['transaction_id'] ?? 0);
            if ($amount <= 0 || $transactionId <= 0) {
                continue;
            }

            $assetModule = (string) ($accessLog['asset_module'] ?? '');
            if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
                throw new RuntimeException('환불할 차감 항목을 사용할 수 없습니다: ' . $assetModule);
            }

            $assetOption = sr_content_asset_modules($pdo)[$assetModule];
            $transactionData = [
                'account_id' => $accountId,
                'amount' => $amount,
                'transaction_type' => (string) ($assetOption['refund_type'] ?? 'refund'),
                'reason' => '콘텐츠 파일 다운로드 환불: ' . $refundNote,
                'reference_type' => 'refund',
                'reference_id' => $assetModule . '_transaction:' . (string) $transactionId,
                'created_by_account_id' => $adminAccountId,
            ];
            if ($assetModule === 'point') {
                $transactionData['refund_expiration_policy'] = $refundExpirationPolicy;
            }
            if ($assetModule === 'point' && function_exists('sr_point_create_refund_transactions')) {
                foreach (sr_point_create_refund_transactions($pdo, $transactionData) as $refundTransactionId) {
                    $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
                }
                continue;
            }

            $refundTransactionId = sr_content_create_asset_transaction($pdo, $assetModule, $transactionData);
            $refundTransactionIds[] = $assetModule . ':' . (string) $refundTransactionId;
        }

        $accessRevoked = false;
        if ((string) ($downloadLog['charge_policy'] ?? '') === 'once' || (int) ($downloadLog['amount'] ?? 0) <= 0) {
            $accessRevoked = sr_content_revoke_file_download_access_entitlement($pdo, $accountId, $contentId, $fileId) > 0;
        }

        if ($refundTransactionIds === [] && !$accessRevoked) {
            throw new RuntimeException('환불할 원장 거래나 회수할 접근권을 찾을 수 없습니다.');
        }

        $refundTransactionIdsJson = json_encode($refundTransactionIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $now = sr_now();
        $refundStatus = $refundTransactionIds !== [] ? 'refunded' : 'access_revoked';
        $stmt = $pdo->prepare(
            'UPDATE sr_content_file_download_logs
             SET refund_status = :refund_status,
                 refund_transaction_ids_json = :refund_transaction_ids_json,
                 refund_note = :refund_note,
                 refunded_by_account_id = :refunded_by_account_id,
                 refunded_at = :refunded_at,
                 access_revoked_at = :access_revoked_at
             WHERE id = :id
               AND refund_status = \'\''
        );
        $stmt->execute([
            'refund_status' => $refundStatus,
            'refund_transaction_ids_json' => is_string($refundTransactionIdsJson) ? $refundTransactionIdsJson : '[]',
            'refund_note' => $refundNote,
            'refunded_by_account_id' => $adminAccountId,
            'refunded_at' => $now,
            'access_revoked_at' => $accessRevoked ? $now : null,
            'id' => $downloadLogId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('이미 처리된 다운로드입니다.');
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        try {
            sr_audit_log($pdo, [
                'actor_account_id' => $adminAccountId,
                'actor_type' => 'admin',
                'event_type' => 'content_file_download.refunded',
                'target_type' => 'content_file_download',
                'target_id' => (string) $downloadLogId,
                'result' => 'success',
                'message' => 'Content file download refunded.',
                'metadata' => [
                    'content_id' => $contentId,
                    'file_id' => $fileId,
                    'account_id' => $accountId,
                    'refund_status' => $refundStatus,
                    'refund_expiration_policy' => $refundExpirationPolicy,
                    'refund_transaction_ids' => $refundTransactionIds,
                    'access_revoked' => $accessRevoked,
                ],
            ]);
        } catch (Throwable $auditException) {
            if (function_exists('sr_log_exception')) {
                sr_log_exception($auditException, 'content_file_download_refund_audit_failed');
            }
        }

        return ['ok' => true, 'message' => $refundStatus === 'refunded' ? '다운로드 차감을 환불 처리했습니다.' : '다운로드 접근권을 회수했습니다.'];
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'message' => $exception->getMessage()];
    }
}

function sr_content_all_active_download_files(PDO $pdo, int $limit = 200): array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_files
         WHERE status = 'active'
         ORDER BY title ASC, id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_linked_file_ids(PDO $pdo, int $pageId): array
{
    $ids = [];
    foreach (sr_content_files_for_content($pdo, $pageId) as $file) {
        $ids[(int) $file['id']] = true;
    }

    return $ids;
}

function sr_content_download_file_link_badge_select_html(string $id, string $name, array $files, array $selectedIds, ?PDO $pdo = null): string
{
    $options = [];
    foreach ($files as $file) {
        $fileId = (int) ($file['id'] ?? 0);
        if ($fileId < 1) {
            continue;
        }

        $title = trim((string) ($file['title'] ?? ''));
        $originalName = trim((string) ($file['original_name'] ?? ''));
        $label = $title !== '' ? $title : ($originalName !== '' ? $originalName : '다운로드 파일 #' . (string) $fileId);
        $summaryParts = [];
        if ($originalName !== '' && $originalName !== $label) {
            $summaryParts[] = $originalName;
        }
        $summaryParts[] = sr_content_format_bytes((int) ($file['size_bytes'] ?? 0));
        if ((int) ($file['asset_download_enabled'] ?? 0) === 1) {
            $assetLabel = $pdo instanceof PDO ? sr_content_asset_module_labels((string) ($file['asset_module'] ?? ''), $pdo) : (string) ($file['asset_module'] ?? '');
            $summaryParts[] = trim($assetLabel . ' ' . number_format((int) ($file['asset_download_amount'] ?? 0)));
        } else {
            $summaryParts[] = '무료';
        }

        $options[(string) $fileId] = [
            'label' => $label,
            'summary' => implode(' · ', array_filter($summaryParts, static fn (string $part): bool => $part !== '')),
        ];
    }

    return sr_admin_select_badge_list_html($id, $name, $options, array_map('strval', $selectedIds), '연결할 다운로드 파일이 없습니다.', '파일 선택');
}
