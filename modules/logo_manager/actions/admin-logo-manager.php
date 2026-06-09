<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/logo_manager/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/logo-manager', 'view');

$positionOptions = sr_logo_manager_position_options($pdo);
$logoStatuses = ['active', 'disabled', 'archived'];
$logoManagerDefaultAltText = is_array($site ?? null) ? trim((string) (($site['site_name'] ?? '') !== '' ? $site['site_name'] : ($site['name'] ?? ''))) : '';
$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$logoTableExists = sr_logo_manager_table_exists($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/logo-manager', 'edit');

    $intent = sr_post_string('intent', 40);
    $now = sr_now();

    if ($intent === 'create_logo') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $positionKey = sr_logo_manager_clean_position_key(sr_post_string('position_key', 120));
        $title = sr_logo_manager_clean_single_line(sr_post_string('title', 120), 120);
        $altText = sr_logo_manager_clean_single_line(sr_post_string('alt_text', 160), 160);
        $linkUrlRaw = sr_post_string('link_url', 255);
        $linkUrl = sr_logo_manager_clean_url($linkUrlRaw);
        $useAsPublicSymbol = sr_logo_manager_use_as_public_symbol_value($positionKey, sr_post_string('use_as_public_symbol', 1));
        $status = sr_post_string('status_enabled', 10) === '1' ? 'active' : 'disabled';
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;

        if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
            $errors[] = '로고 용도 값이 올바르지 않습니다.';
        }
        if ($title === '') {
            $errors[] = '로고 이름을 입력하세요.';
        }
        if ($linkUrlRaw !== '' && $linkUrl === '') {
            $errors[] = '링크 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
        }
        if ($startsAtInput !== '' && $startsAt === null) {
            $errors[] = '시작 시각 형식이 올바르지 않습니다.';
        }
        if ($endsAtInput !== '' && $endsAt === null) {
            $errors[] = '종료 시각 형식이 올바르지 않습니다.';
        }
        if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
            $errors[] = '종료 시각은 시작 시각 이후여야 합니다.';
        }
        if (!sr_logo_manager_upload_was_provided($uploadFile)) {
            $errors[] = '업로드할 로고 이미지를 선택하세요.';
        }

        if ($errors === [] && is_array($uploadFile)) {
            try {
                $uploadedImage = sr_logo_manager_upload_image($uploadFile, $positionKey, $pdo);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors === [] && is_array($uploadedImage)) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_logo_manager_logos
                        (position_key, title, alt_text, link_url, use_as_public_symbol, original_name, storage_driver, storage_key, public_url, mime_type,
                         size_bytes, width, height, checksum_sha256, status, starts_at, ends_at, sort_order, created_by_account_id, created_at, updated_at)
                     VALUES
                        (:position_key, :title, :alt_text, :link_url, :use_as_public_symbol, :original_name, :storage_driver, :storage_key, :public_url, :mime_type,
                         :size_bytes, :width, :height, :checksum_sha256, :status, :starts_at, :ends_at, :sort_order, :created_by_account_id, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'position_key' => $positionKey,
                    'title' => $title,
                    'alt_text' => $altText,
                    'link_url' => $linkUrl,
                    'use_as_public_symbol' => $useAsPublicSymbol,
                    'original_name' => (string) ($uploadedImage['original_name'] ?? ''),
                    'storage_driver' => (string) $uploadedImage['driver'],
                    'storage_key' => (string) $uploadedImage['storage_key'],
                    'public_url' => (string) $uploadedImage['public_url'],
                    'mime_type' => (string) $uploadedImage['mime_type'],
                    'size_bytes' => (int) $uploadedImage['size_bytes'],
                    'width' => (int) $uploadedImage['width'],
                    'height' => (int) $uploadedImage['height'],
                    'checksum_sha256' => (string) $uploadedImage['checksum_sha256'],
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'sort_order' => $sortOrder,
                    'created_by_account_id' => (int) $account['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $logoId = (int) $pdo->lastInsertId();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.logo.created',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => (string) $logoId,
                    'result' => 'success',
                    'message' => 'Logo placement created.',
                    'metadata' => [
                        'position_key' => $positionKey,
                        'use_as_public_symbol' => $useAsPublicSymbol,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'reencoded' => !empty($uploadedImage['reencoded']),
                    ],
                ]);

                $notice = '로고 배치를 저장했습니다.';
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'logo_manager_logo_create_failed');
                $errors[] = '로고 저장 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'update_logo') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = (int) sr_post_string('logo_id', 20);
        $logo = null;
        if ($logoId > 0 && $logoTableExists) {
            $stmt = $pdo->prepare('SELECT * FROM sr_logo_manager_logos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $logoId]);
            $logo = $stmt->fetch();
            if (!is_array($logo)) {
                $logo = null;
            }
        }
        if ($logo === null) {
            $errors[] = '수정할 로고 배치를 찾을 수 없습니다.';
        }

        $positionKey = sr_logo_manager_clean_position_key(sr_post_string('position_key', 120));
        $title = sr_logo_manager_clean_single_line(sr_post_string('title', 120), 120);
        $altText = sr_logo_manager_clean_single_line(sr_post_string('alt_text', 160), 160);
        $linkUrlRaw = sr_post_string('link_url', 255);
        $linkUrl = sr_logo_manager_clean_url($linkUrlRaw);
        $useAsPublicSymbol = sr_logo_manager_use_as_public_symbol_value($positionKey, sr_post_string('use_as_public_symbol', 1));
        $status = sr_post_string('status_enabled', 10) === '1' ? 'active' : 'disabled';
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;

        if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
            $errors[] = '로고 용도 값이 올바르지 않습니다.';
        }
        if ($title === '') {
            $errors[] = '로고 이름을 입력하세요.';
        }
        if ($linkUrlRaw !== '' && $linkUrl === '') {
            $errors[] = '링크 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
        }
        if ($startsAtInput !== '' && $startsAt === null) {
            $errors[] = '시작 시각 형식이 올바르지 않습니다.';
        }
        if ($endsAtInput !== '' && $endsAt === null) {
            $errors[] = '종료 시각 형식이 올바르지 않습니다.';
        }
        if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
            $errors[] = '종료 시각은 시작 시각 이후여야 합니다.';
        }

        if ($errors === [] && sr_logo_manager_upload_was_provided($uploadFile) && is_array($uploadFile)) {
            try {
                $uploadedImage = sr_logo_manager_upload_image($uploadFile, $positionKey, $pdo);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors === [] && is_array($logo)) {
            $imageReplaced = is_array($uploadedImage);
            $before = [
                'position_key' => (string) $logo['position_key'],
                'title' => (string) $logo['title'],
                'alt_text' => (string) $logo['alt_text'],
                'link_url' => (string) $logo['link_url'],
                'use_as_public_symbol' => (int) $logo['use_as_public_symbol'],
                'status' => (string) $logo['status'],
                'starts_at' => $logo['starts_at'],
                'ends_at' => $logo['ends_at'],
                'sort_order' => (int) $logo['sort_order'],
                'storage_driver' => (string) $logo['storage_driver'],
                'storage_key' => (string) $logo['storage_key'],
            ];
            $after = [
                'position_key' => $positionKey,
                'title' => $title,
                'alt_text' => $altText,
                'link_url' => $linkUrl,
                'use_as_public_symbol' => $useAsPublicSymbol,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'sort_order' => $sortOrder,
                'storage_driver' => $imageReplaced ? (string) $uploadedImage['driver'] : (string) $logo['storage_driver'],
                'storage_key' => $imageReplaced ? (string) $uploadedImage['storage_key'] : (string) $logo['storage_key'],
            ];

            try {
                $sql = 'UPDATE sr_logo_manager_logos
                        SET position_key = :position_key,
                            title = :title,
                            alt_text = :alt_text,
                            link_url = :link_url,
                            use_as_public_symbol = :use_as_public_symbol,
                            status = :status,
                            starts_at = :starts_at,
                            ends_at = :ends_at,
                            sort_order = :sort_order,
                            updated_at = :updated_at';
                $params = [
                    'position_key' => $positionKey,
                    'title' => $title,
                    'alt_text' => $altText,
                    'link_url' => $linkUrl,
                    'use_as_public_symbol' => $useAsPublicSymbol,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                    'id' => $logoId,
                ];
                if ($imageReplaced) {
                    $sql .= ',
                            original_name = :original_name,
                            storage_driver = :storage_driver,
                            storage_key = :storage_key,
                            public_url = :public_url,
                            mime_type = :mime_type,
                            size_bytes = :size_bytes,
                            width = :width,
                            height = :height,
                            checksum_sha256 = :checksum_sha256';
                    $params['original_name'] = (string) ($uploadedImage['original_name'] ?? '');
                    $params['storage_driver'] = (string) $uploadedImage['driver'];
                    $params['storage_key'] = (string) $uploadedImage['storage_key'];
                    $params['public_url'] = (string) $uploadedImage['public_url'];
                    $params['mime_type'] = (string) $uploadedImage['mime_type'];
                    $params['size_bytes'] = (int) $uploadedImage['size_bytes'];
                    $params['width'] = (int) $uploadedImage['width'];
                    $params['height'] = (int) $uploadedImage['height'];
                    $params['checksum_sha256'] = (string) $uploadedImage['checksum_sha256'];
                }
                $sql .= ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.logo.updated',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => (string) $logoId,
                    'result' => 'success',
                    'message' => 'Logo placement updated.',
                    'metadata' => [
                        'before' => $before,
                        'after' => $after,
                        'image_replaced' => $imageReplaced,
                        'old_storage_driver' => (string) $logo['storage_driver'],
                        'old_storage_key' => (string) $logo['storage_key'],
                        'new_storage_driver' => $after['storage_driver'],
                        'new_storage_key' => $after['storage_key'],
                        'reencoded' => $imageReplaced && !empty($uploadedImage['reencoded']),
                    ],
                ]);

                $notice = '로고 배치를 수정했습니다.';
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'logo_manager_logo_update_failed');
                $errors[] = '로고 수정 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'logo_status') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = (int) sr_post_string('logo_id', 20);
        $status = sr_post_string('status', 30);
        if ($logoId <= 0 || !in_array($status, $logoStatuses, true)) {
            $errors[] = '로고 상태 변경 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('UPDATE sr_logo_manager_logos SET status = :status, updated_at = :updated_at WHERE id = :id');
            $stmt->execute(['status' => $status, 'updated_at' => $now, 'id' => $logoId]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'logo_manager.logo.status_changed',
                'target_type' => 'logo_manager_logo',
                'target_id' => (string) $logoId,
                'result' => 'success',
                'message' => 'Logo placement status changed.',
                'metadata' => ['status' => $status],
            ]);
            $notice = '로고 배치 상태를 변경했습니다.';
        }
    } elseif ($intent === 'batch_status') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_logo_ids'] ?? [];
        $selectedIds = [];
        if (is_array($rawSelectedIds)) {
            foreach ($rawSelectedIds as $rawSelectedId) {
                $selectedId = (int) $rawSelectedId;
                if ($selectedId > 0) {
                    $selectedIds[$selectedId] = $selectedId;
                }
            }
        }
        $selectedIds = array_values($selectedIds);

        if ($operationKey !== 'logo_manager.set_status') {
            $errors[] = '허용되지 않은 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['active', 'disabled'], true)) {
            $errors[] = '변경할 로고 배치 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 로고 배치를 선택하세요.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '로고 배치 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'logo_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id
                 FROM sr_logo_manager_logos
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            if (count($stmt->fetchAll()) !== count($selectedIds)) {
                $errors[] = '선택한 로고 배치 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        if ($errors === []) {
            $changedCount = 0;
            $skippedCount = 0;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_logo_manager_logos
                     SET status = :status, updated_at = :updated_at
                     WHERE id = :id AND status <> :status_compare'
                );
                foreach ($selectedIds as $selectedId) {
                    $stmt->execute([
                        'status' => $targetStatus,
                        'status_compare' => $targetStatus,
                        'updated_at' => $now,
                        'id' => $selectedId,
                    ]);
                    if ($stmt->rowCount() > 0) {
                        $changedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.logo.bulk_status_changed',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Logo placement statuses changed in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '로고 배치 ' . number_format($changedCount) . '건의 상태를 ' . sr_logo_manager_status_label($targetStatus) . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '로고 배치 상태 일괄 변경 중 오류가 발생했습니다.';
            }
        }
    }

    $redirectQuery = (string) ($_SERVER['QUERY_STRING'] ?? '');
    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/logo-manager' . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
}

$activeLogos = [];
foreach (array_keys($positionOptions) as $positionKey) {
    $activeLogos[$positionKey] = sr_logo_manager_active_logo($pdo, (string) $positionKey);
}

$logoSortOptions = sr_admin_logo_sort_options();
$logoDefaultSort = sr_admin_logo_default_sort();
$logoSort = sr_admin_sort_from_request($logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir');
$logos = [];
if ($logoTableExists) {
    $stmt = $pdo->query('SELECT COUNT(*) AS count_value FROM sr_logo_manager_logos');
    $logoCountRow = $stmt->fetch();
    $logoPagination = sr_admin_pagination_from_total($pdo, is_array($logoCountRow) ? (int) ($logoCountRow['count_value'] ?? 0) : 0, 'logo_page');
    $stmt = $pdo->query(
        'SELECT id, position_key, title, alt_text, link_url, use_as_public_symbol, status, starts_at, ends_at,
                CASE WHEN starts_at IS NOT NULL AND ends_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, starts_at, ends_at) ELSE NULL END AS duration_seconds,
                sort_order,
                storage_driver, storage_key, public_url, mime_type, width, height, size_bytes, original_name, created_at
         FROM sr_logo_manager_logos
         ' . sr_admin_sort_order_sql($logoSortOptions, $logoSort, $logoDefaultSort) . '
         LIMIT ' . (int) $logoPagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($logoPagination)
    );
    $logos = $stmt->fetchAll();
} else {
    $logoPagination = sr_admin_pagination_from_total($pdo, 0, 'logo_page');
    if ($notice === '') {
        $notice = '로고 매니저 DB 업데이트 적용 후 로고 배치를 등록할 수 있습니다.';
    }
}

include SR_ROOT . '/modules/logo_manager/views/admin-logo-manager.php';
