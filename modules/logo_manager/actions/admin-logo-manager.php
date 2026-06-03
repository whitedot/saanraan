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
        $status = sr_post_string('status_enabled', 10) === '1' ? 'active' : 'disabled';
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;

        if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
            $errors[] = '출력 위치 값이 올바르지 않습니다.';
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
                        (position_key, title, alt_text, link_url, original_name, storage_driver, storage_key, public_url, mime_type,
                         size_bytes, width, height, checksum_sha256, status, starts_at, ends_at, sort_order, created_by_account_id, created_at, updated_at)
                     VALUES
                        (:position_key, :title, :alt_text, :link_url, :original_name, :storage_driver, :storage_key, :public_url, :mime_type,
                         :size_bytes, :width, :height, :checksum_sha256, :status, :starts_at, :ends_at, :sort_order, :created_by_account_id, :created_at, :updated_at)'
                );
                $stmt->execute([
                    'position_key' => $positionKey,
                    'title' => $title,
                    'alt_text' => $altText,
                    'link_url' => $linkUrl,
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
    }

    sr_admin_redirect_with_result(sr_admin_action_result($errors, $notice), '/admin/logo-manager');
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
        'SELECT id, position_key, title, alt_text, link_url, status, starts_at, ends_at, sort_order,
                storage_driver, storage_key, public_url, mime_type, width, height, size_bytes, created_at
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
