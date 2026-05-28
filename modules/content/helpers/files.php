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

function sr_content_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
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
    $status = (string) ($filters['status'] ?? 'active');
    if ($status !== '') {
        $conditions[] = 'f.status = :status';
        $params['status'] = $status;
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
