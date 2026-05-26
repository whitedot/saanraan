<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/logo_manager/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/logo-manager', 'view');

$usageOptions = sr_logo_manager_usage_options();
$assetStatuses = ['active', 'archived'];
$assignmentStatuses = ['active', 'disabled'];
$logoManagerDefaultAltText = is_array($site ?? null) ? trim((string) (($site['site_name'] ?? '') !== '' ? $site['site_name'] : ($site['name'] ?? ''))) : '';
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/logo-manager', 'edit');

    $intent = sr_post_string('intent', 40);
    $now = sr_now();

    if ($intent === 'upload_asset') {
        $usageKey = sr_logo_manager_usage_key(sr_post_string('usage_key', 40));
        $title = sr_logo_manager_clean_single_line(sr_post_string('title', 120), 120);
        $altText = sr_logo_manager_clean_single_line(sr_post_string('alt_text', 160), 160);
        $linkUrlRaw = sr_post_string('link_url', 255);
        $linkUrl = sr_logo_manager_clean_url($linkUrlRaw);
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;

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
                $uploadedImage = sr_logo_manager_upload_image($uploadFile, $usageKey);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($errors === [] && is_array($uploadedImage)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_logo_manager_assets
                        (usage_key, title, alt_text, original_name, storage_driver, storage_key, public_url, mime_type,
                         size_bytes, width, height, checksum_sha256, status, created_by_account_id, created_at, updated_at)
                     VALUES
                        (:usage_key, :title, :alt_text, :original_name, :storage_driver, :storage_key, :public_url, :mime_type,
                         :size_bytes, :width, :height, :checksum_sha256, :status, :created_by_account_id, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'usage_key' => $usageKey,
                    'title' => $title,
                    'alt_text' => $altText,
                    'original_name' => (string) ($uploadedImage['original_name'] ?? ''),
                    'storage_driver' => (string) $uploadedImage['driver'],
                    'storage_key' => (string) $uploadedImage['storage_key'],
                    'public_url' => (string) $uploadedImage['public_url'],
                    'mime_type' => (string) $uploadedImage['mime_type'],
                    'size_bytes' => (int) $uploadedImage['size_bytes'],
                    'width' => (int) $uploadedImage['width'],
                    'height' => (int) $uploadedImage['height'],
                    'checksum_sha256' => (string) $uploadedImage['checksum_sha256'],
                    'status' => 'active',
                    'created_by_account_id' => (int) $account['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $assetId = (int) $pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO sr_logo_manager_assignments
                        (usage_key, asset_id, alt_text, link_url, status, starts_at, ends_at, sort_order, created_by_account_id, created_at, updated_at)
                     VALUES
                        (:usage_key, :asset_id, :alt_text, :link_url, :status, :starts_at, :ends_at, :sort_order, :created_by_account_id, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'usage_key' => $usageKey,
                    'asset_id' => $assetId,
                    'alt_text' => $altText,
                    'link_url' => $linkUrl,
                    'status' => 'active',
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'sort_order' => $sortOrder,
                    'created_by_account_id' => (int) $account['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $assignmentId = (int) $pdo->lastInsertId();
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.asset.uploaded',
                    'target_type' => 'logo_manager_asset',
                    'target_id' => (string) $assetId,
                    'result' => 'success',
                    'message' => 'Logo asset uploaded and assigned.',
                    'metadata' => [
                        'usage_key' => $usageKey,
                        'assignment_id' => $assignmentId,
                        'reencoded' => !empty($uploadedImage['reencoded']),
                    ],
                ]);

                $notice = '로고를 업로드하고 기간별 적용 항목을 추가했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '로고 저장 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'save_assignment') {
        $assetId = (int) sr_post_string('asset_id', 20);
        $usageKey = sr_logo_manager_usage_key(sr_post_string('usage_key', 40));
        $altText = sr_logo_manager_clean_single_line(sr_post_string('alt_text', 160), 160);
        $linkUrlRaw = sr_post_string('link_url', 255);
        $linkUrl = sr_logo_manager_clean_url($linkUrlRaw);
        $status = sr_post_string('status_enabled', 10) === '1' ? 'active' : 'disabled';
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));

        if ($assetId <= 0) {
            $errors[] = '적용할 로고 자산을 선택하세요.';
        }
        if (!in_array($status, $assignmentStatuses, true)) {
            $errors[] = '적용 상태 값이 올바르지 않습니다.';
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

        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id FROM sr_logo_manager_assets WHERE id = :id AND status = :status LIMIT 1');
            $stmt->execute(['id' => $assetId, 'status' => 'active']);
            if (!is_array($stmt->fetch())) {
                $errors[] = '사용 가능한 로고 자산을 찾을 수 없습니다.';
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_logo_manager_assignments
                    (usage_key, asset_id, alt_text, link_url, status, starts_at, ends_at, sort_order, created_by_account_id, created_at, updated_at)
                 VALUES
                    (:usage_key, :asset_id, :alt_text, :link_url, :status, :starts_at, :ends_at, :sort_order, :created_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'usage_key' => $usageKey,
                'asset_id' => $assetId,
                'alt_text' => $altText,
                'link_url' => $linkUrl,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'sort_order' => $sortOrder,
                'created_by_account_id' => (int) $account['id'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $assignmentId = (int) $pdo->lastInsertId();

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'logo_manager.assignment.created',
                'target_type' => 'logo_manager_assignment',
                'target_id' => (string) $assignmentId,
                'result' => 'success',
                'message' => 'Logo assignment created.',
                'metadata' => [
                    'usage_key' => $usageKey,
                    'asset_id' => $assetId,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ],
            ]);

            $notice = '기간별 적용 항목을 추가했습니다.';
        }
    } elseif ($intent === 'asset_status') {
        $assetId = (int) sr_post_string('asset_id', 20);
        $status = sr_post_string('status', 30);
        if ($assetId <= 0 || !in_array($status, $assetStatuses, true)) {
            $errors[] = '자산 상태 변경 값이 올바르지 않습니다.';
        } else {
            $stmt = $pdo->prepare('UPDATE sr_logo_manager_assets SET status = :status, updated_at = :updated_at WHERE id = :id');
            $stmt->execute(['status' => $status, 'updated_at' => $now, 'id' => $assetId]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'logo_manager.asset.status_changed',
                'target_type' => 'logo_manager_asset',
                'target_id' => (string) $assetId,
                'result' => 'success',
                'message' => 'Logo asset status changed.',
                'metadata' => ['status' => $status],
            ]);
            $notice = '로고 자산 상태를 변경했습니다.';
        }
    } elseif ($intent === 'assignment_status') {
        $assignmentId = (int) sr_post_string('assignment_id', 20);
        $status = sr_post_string('status', 30);
        if ($assignmentId <= 0 || !in_array($status, $assignmentStatuses, true)) {
            $errors[] = '적용 항목 상태 변경 값이 올바르지 않습니다.';
        } else {
            $stmt = $pdo->prepare('UPDATE sr_logo_manager_assignments SET status = :status, updated_at = :updated_at WHERE id = :id');
            $stmt->execute(['status' => $status, 'updated_at' => $now, 'id' => $assignmentId]);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'logo_manager.assignment.status_changed',
                'target_type' => 'logo_manager_assignment',
                'target_id' => (string) $assignmentId,
                'result' => 'success',
                'message' => 'Logo assignment status changed.',
                'metadata' => ['status' => $status],
            ]);
            $notice = '기간별 적용 항목 상태를 변경했습니다.';
        }
    }
}

$activeAssignments = [];
foreach (array_keys($usageOptions) as $usageKey) {
    $activeAssignments[$usageKey] = sr_logo_manager_active_assignment($pdo, (string) $usageKey);
}

$stmt = $pdo->query('SELECT COUNT(*) AS count_value FROM sr_logo_manager_assets');
$assetCountRow = $stmt->fetch();
$assetPagination = sr_admin_pagination_from_total($pdo, is_array($assetCountRow) ? (int) ($assetCountRow['count_value'] ?? 0) : 0, 'asset_page');
$assetSortOptions = sr_admin_logo_asset_sort_options();
$assetDefaultSort = sr_admin_logo_asset_default_sort();
$assetSort = sr_admin_sort_from_request($assetSortOptions, $assetDefaultSort, 'asset_sort', 'asset_dir');
$stmt = $pdo->query(
    'SELECT id, usage_key, title, alt_text, storage_driver, storage_key, public_url, mime_type,
            size_bytes, width, height, status, created_at, updated_at
     FROM sr_logo_manager_assets
     ' . sr_admin_sort_order_sql($assetSortOptions, $assetSort, $assetDefaultSort) . '
     LIMIT ' . (int) $assetPagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($assetPagination)
);
$assets = $stmt->fetchAll();

$stmt = $pdo->query(
    'SELECT COUNT(*) AS count_value
     FROM sr_logo_manager_assignments a
     INNER JOIN sr_logo_manager_assets asset ON asset.id = a.asset_id'
);
$assignmentCountRow = $stmt->fetch();
$assignmentPagination = sr_admin_pagination_from_total($pdo, is_array($assignmentCountRow) ? (int) ($assignmentCountRow['count_value'] ?? 0) : 0, 'assignment_page');
$assignmentSortOptions = sr_admin_logo_assignment_sort_options();
$assignmentDefaultSort = sr_admin_logo_assignment_default_sort();
$assignmentSort = sr_admin_sort_from_request($assignmentSortOptions, $assignmentDefaultSort, 'assignment_sort', 'assignment_dir');
$stmt = $pdo->query(
    'SELECT a.id, a.usage_key, a.asset_id, a.alt_text, a.link_url, a.status, a.starts_at, a.ends_at,
            a.sort_order, a.created_at, asset.title, asset.storage_driver, asset.storage_key, asset.public_url
     FROM sr_logo_manager_assignments a
     INNER JOIN sr_logo_manager_assets asset ON asset.id = a.asset_id
     ' . sr_admin_sort_order_sql($assignmentSortOptions, $assignmentSort, $assignmentDefaultSort) . '
     LIMIT ' . (int) $assignmentPagination['per_page'] . ' OFFSET ' . sr_admin_pagination_offset($assignmentPagination)
);
$assignments = $stmt->fetchAll();

include SR_ROOT . '/modules/logo_manager/views/admin-logo-manager.php';
