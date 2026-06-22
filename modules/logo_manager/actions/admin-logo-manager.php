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
$iconVariantTableExists = sr_logo_manager_icon_variants_table_exists($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/logo-manager', in_array($intent, ['delete_logo', 'purge_favicon_icons'], true) ? 'delete' : 'edit');

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
        $sortOrder = sr_logo_manager_clean_sort_order(sr_post_string('sort_order', 20));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;
        $copyPositionKey = '';

        if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
            $errors[] = '로고 용도 값이 올바르지 않습니다.';
        }
        if ($positionKey === sr_logo_manager_favicon_position_key() && sr_post_string('also_use_as_app_icon', 1) === '1') {
            $copyPositionKey = sr_logo_manager_app_icon_position_key();
        } elseif ($positionKey === sr_logo_manager_app_icon_position_key() && sr_post_string('also_use_as_favicon', 1) === '1') {
            $copyPositionKey = sr_logo_manager_favicon_position_key();
        }
        if ($copyPositionKey !== '' && !isset($positionOptions[$copyPositionKey])) {
            $errors[] = '함께 등록할 로고 용도 값이 올바르지 않습니다.';
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
        if ($sortOrder === null) {
            $errors[] = '정렬 값은 숫자로 입력하세요.';
            $sortOrder = 0;
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

        $uploadedImagesByPosition = [];
        if ($errors === [] && is_array($uploadedImage)) {
            $uploadedImagesByPosition[$positionKey] = $uploadedImage;
            if ($copyPositionKey !== '' && is_array($uploadFile)) {
                try {
                    $uploadedImagesByPosition[$copyPositionKey] = sr_logo_manager_upload_image($uploadFile, $copyPositionKey, $pdo);
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }
        if ($errors !== [] && $uploadedImagesByPosition !== []) {
            $uploadedStorageReferences = [];
            foreach ($uploadedImagesByPosition as $uploadedPositionImage) {
                $uploadedStorageReferences[] = [
                    'storage_driver' => (string) ($uploadedPositionImage['driver'] ?? 'local'),
                    'storage_key' => (string) ($uploadedPositionImage['storage_key'] ?? ''),
                ];
            }
            sr_logo_manager_delete_storage_references($uploadedStorageReferences);
        }

        if ($errors === [] && $uploadedImagesByPosition !== []) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_logo_manager_logos
                        (position_key, title, alt_text, link_url, use_as_public_symbol, original_name, storage_driver, storage_key, public_url, mime_type,
                         size_bytes, width, height, checksum_sha256, status, starts_at, ends_at, sort_order, created_by_account_id, created_at, updated_at)
                     VALUES
                         (:position_key, :title, :alt_text, :link_url, :use_as_public_symbol, :original_name, :storage_driver, :storage_key, :public_url, :mime_type,
                         :size_bytes, :width, :height, :checksum_sha256, :status, :starts_at, :ends_at, :sort_order, :created_by_account_id, :created_at, :updated_at)'
                );
                $createdCount = 0;
                foreach ($uploadedImagesByPosition as $insertPositionKey => $insertedImage) {
                    $insertUseAsPublicSymbol = $insertPositionKey === $positionKey ? $useAsPublicSymbol : 0;
                    $stmt->execute([
                        'position_key' => $insertPositionKey,
                        'title' => $title,
                        'alt_text' => $altText,
                        'link_url' => $linkUrl,
                        'use_as_public_symbol' => $insertUseAsPublicSymbol,
                        'original_name' => (string) ($insertedImage['original_name'] ?? ''),
                        'storage_driver' => (string) $insertedImage['driver'],
                        'storage_key' => (string) $insertedImage['storage_key'],
                        'public_url' => (string) $insertedImage['public_url'],
                        'mime_type' => (string) $insertedImage['mime_type'],
                        'size_bytes' => (int) $insertedImage['size_bytes'],
                        'width' => (int) $insertedImage['width'],
                        'height' => (int) $insertedImage['height'],
                        'checksum_sha256' => (string) $insertedImage['checksum_sha256'],
                        'status' => $status,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'sort_order' => $sortOrder,
                        'created_by_account_id' => (int) $account['id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $logoId = (int) $pdo->lastInsertId();
                    $createdCount++;

                    sr_audit_log($pdo, [
                        'actor_account_id' => (int) $account['id'],
                        'actor_type' => 'admin',
                        'event_type' => 'logo_manager.logo.created',
                        'target_type' => 'logo_manager_logo',
                        'target_id' => (string) $logoId,
                        'result' => 'success',
                        'message' => 'Logo placement created.',
                        'metadata' => [
                            'position_key' => $insertPositionKey,
                            'copied_from_position_key' => $insertPositionKey === $positionKey ? '' : $positionKey,
                            'use_as_public_symbol' => $insertUseAsPublicSymbol,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'reencoded' => !empty($insertedImage['reencoded']),
                        ],
                    ]);
                }
                $pdo->commit();

                $notice = $createdCount > 1 ? '로고 배치를 함께 저장했습니다.' : '로고 배치를 저장했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $uploadedStorageReferences = [];
                foreach ($uploadedImagesByPosition as $uploadedPositionImage) {
                    $uploadedStorageReferences[] = [
                        'storage_driver' => (string) ($uploadedPositionImage['driver'] ?? 'local'),
                        'storage_key' => (string) ($uploadedPositionImage['storage_key'] ?? ''),
                    ];
                }
                sr_logo_manager_delete_storage_references($uploadedStorageReferences);
                sr_log_exception($exception, 'logo_manager_logo_create_failed');
                $errors[] = '로고 저장 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'update_logo') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = sr_admin_post_positive_int('logo_id');
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
        $status = sr_post_string('status', 30);
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_logo_manager_clean_admin_datetime($startsAtInput);
        $endsAt = sr_logo_manager_clean_admin_datetime($endsAtInput);
        $sortOrder = sr_logo_manager_clean_sort_order(sr_post_string('sort_order', 20));
        $uploadFile = $_FILES['logo_file'] ?? null;
        $uploadedImage = null;

        if ($positionKey === '' || !isset($positionOptions[$positionKey])) {
            $errors[] = '로고 용도 값이 올바르지 않습니다.';
        }
        if ($title === '') {
            $errors[] = '로고 이름을 입력하세요.';
        }
        if (!in_array($status, $logoStatuses, true)) {
            $errors[] = '로고 상태 값이 올바르지 않습니다.';
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
        if ($sortOrder === null) {
            $errors[] = '정렬 값은 숫자로 입력하세요.';
            $sortOrder = 0;
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
                if ($imageReplaced && $iconVariantTableExists) {
                    $stale = $pdo->prepare("UPDATE sr_logo_manager_icon_variants SET status = 'stale', updated_at = :updated_at WHERE logo_id = :logo_id AND status = 'active'");
                    $stale->execute(['updated_at' => $now, 'logo_id' => $logoId]);
                }

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
    } elseif ($intent === 'generate_icon_set') {
        if (!$logoTableExists || !$iconVariantTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = sr_admin_post_positive_int('logo_id');
        $logo = null;
        if ($logoId > 0 && $logoTableExists) {
            $stmt = $pdo->prepare('SELECT * FROM sr_logo_manager_logos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $logoId]);
            $row = $stmt->fetch();
            $logo = is_array($row) ? $row : null;
        }
        if (!is_array($logo)) {
            $errors[] = '아이콘 세트를 생성할 로고를 찾을 수 없습니다.';
        } elseif ((string) ($logo['position_key'] ?? '') !== sr_logo_manager_favicon_position_key()) {
            $errors[] = '파비콘 용도 로고에서만 아이콘 세트를 생성할 수 있습니다.';
        }

        $variantKeys = sr_logo_manager_icon_variant_keys_from_post($_POST['icon_variant_keys'] ?? []);
        $fitMode = sr_post_string('fit_mode', 20);
        $backgroundColor = sr_post_string('background_color', 20);
        $normalizedBackgroundColor = sr_logo_manager_icon_background_color($backgroundColor);
        $activate = sr_post_string('activate_icon_set', 1) === '1';
        if ($variantKeys === []) {
            $errors[] = '생성할 아이콘 크기를 선택하세요.';
        }
        if (!in_array($fitMode, ['contain', 'cover'], true)) {
            $errors[] = '아이콘 맞춤 방식이 올바르지 않습니다.';
        }
        if ($normalizedBackgroundColor !== strtolower(trim($backgroundColor)) && strtolower(trim($backgroundColor)) !== 'transparent') {
            $errors[] = '배경색은 transparent 또는 #RRGGBB 형식으로 입력하세요.';
        }

        if ($errors === [] && is_array($logo)) {
            try {
                $generated = sr_logo_manager_generate_icon_variants($pdo, $logo, $variantKeys, [
                    'fit_mode' => $fitMode,
                    'background_color' => $normalizedBackgroundColor,
                ]);
                $createdCount = sr_logo_manager_save_icon_variants($pdo, (int) $account['id'], $logoId, $generated, $activate);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => $activate ? 'logo_manager.icon_set.activated' : 'logo_manager.icon_set.generated',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => (string) $logoId,
                    'result' => 'success',
                    'message' => 'Logo icon set generated.',
                    'metadata' => [
                        'variant_keys' => $variantKeys,
                        'variant_count' => $createdCount,
                        'fit_mode' => $fitMode,
                        'background_color' => $normalizedBackgroundColor,
                        'activated' => $activate,
                    ],
                ]);

                $notice = '아이콘 세트 ' . number_format($createdCount) . '개를 생성했습니다.';
                if ($activate) {
                    $notice .= ' 새 세트를 사용하도록 적용했습니다.';
                }
            } catch (Throwable $exception) {
                sr_log_exception($exception, 'logo_manager_icon_set_generate_failed');
                $errors[] = $exception->getMessage();
            }
        }
    } elseif ($intent === 'purge_favicon_icons') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $faviconLogos = [];
        $iconVariants = [];
        if ($logoTableExists) {
            $stmt = $pdo->prepare('SELECT * FROM sr_logo_manager_logos WHERE position_key = :position_key ORDER BY id ASC');
            $stmt->execute(['position_key' => sr_logo_manager_favicon_position_key()]);
            $faviconLogos = $stmt->fetchAll();

            if ($iconVariantTableExists && $faviconLogos !== []) {
                $faviconLogoIds = array_values(array_map(static fn (array $logo): int => (int) ($logo['id'] ?? 0), $faviconLogos));
                $placeholders = implode(', ', array_fill(0, count($faviconLogoIds), '?'));
                $stmt = $pdo->prepare('SELECT storage_driver, storage_key FROM sr_logo_manager_icon_variants WHERE logo_id IN (' . $placeholders . ')');
                $stmt->execute($faviconLogoIds);
                $iconVariants = $stmt->fetchAll();
            }
        }

        if ($errors === []) {
            $storageReferences = $iconVariants;
            foreach ($faviconLogos as $faviconLogo) {
                $storageReferences[] = [
                    'storage_driver' => (string) ($faviconLogo['storage_driver'] ?? 'local'),
                    'storage_key' => (string) ($faviconLogo['storage_key'] ?? ''),
                ];
            }

            try {
                $pdo->beginTransaction();

                if ($faviconLogos !== []) {
                    $faviconLogoIds = array_values(array_map(static fn (array $logo): int => (int) ($logo['id'] ?? 0), $faviconLogos));
                    $placeholders = implode(', ', array_fill(0, count($faviconLogoIds), '?'));
                    if ($iconVariantTableExists) {
                        $stmt = $pdo->prepare('DELETE FROM sr_logo_manager_icon_variants WHERE logo_id IN (' . $placeholders . ')');
                        $stmt->execute($faviconLogoIds);
                    }
                    $stmt = $pdo->prepare('DELETE FROM sr_logo_manager_logos WHERE id IN (' . $placeholders . ')');
                    $stmt->execute($faviconLogoIds);
                }

                sr_logo_manager_mark_favicon_reset($pdo, $now);
                $pdo->commit();

                $storageDeleteResult = sr_logo_manager_delete_storage_references($storageReferences);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.favicon.purged',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => sr_logo_manager_favicon_position_key(),
                    'result' => 'success',
                    'message' => 'Logo manager favicon icons purged.',
                    'metadata' => [
                        'logo_count' => count($faviconLogos),
                        'icon_variant_count' => count($iconVariants),
                        'deleted_storage_count' => (int) ($storageDeleteResult['deleted_count'] ?? 0),
                        'failed_storage_refs' => array_slice(array_map('strval', is_array($storageDeleteResult['failed'] ?? null) ? $storageDeleteResult['failed'] : []), 0, 20),
                        'reset_at' => $now,
                    ],
                ]);

                $notice = '파비콘을 완전 삭제했습니다. 활성 파비콘 후보가 없으면 icon/apple-touch-icon link를 출력하지 않습니다.';
                if (($storageDeleteResult['failed'] ?? []) !== []) {
                    $notice .= ' 일부 저장소 파일은 정리하지 못했습니다.';
                }
                header('Clear-Site-Data: "cache"');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'logo_manager_favicon_purge_failed');
                $errors[] = '파비콘 완전 삭제 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'delete_logo') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = sr_admin_post_positive_int('logo_id');
        $logo = null;
        $iconVariants = [];
        if ($logoId > 0 && $logoTableExists) {
            $stmt = $pdo->prepare('SELECT * FROM sr_logo_manager_logos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $logoId]);
            $row = $stmt->fetch();
            $logo = is_array($row) ? $row : null;
            if ($iconVariantTableExists) {
                $stmt = $pdo->prepare('SELECT storage_driver, storage_key FROM sr_logo_manager_icon_variants WHERE logo_id = :logo_id');
                $stmt->execute(['logo_id' => $logoId]);
                $iconVariants = $stmt->fetchAll();
            }
        }
        if (!is_array($logo)) {
            $errors[] = '삭제할 로고 배치를 찾을 수 없습니다.';
        }

        if ($errors === [] && is_array($logo)) {
            $storageReferences = array_merge([
                [
                    'storage_driver' => (string) ($logo['storage_driver'] ?? 'local'),
                    'storage_key' => (string) ($logo['storage_key'] ?? ''),
                ],
            ], $iconVariants);

            try {
                $pdo->beginTransaction();

                if ($iconVariantTableExists) {
                    $stmt = $pdo->prepare('DELETE FROM sr_logo_manager_icon_variants WHERE logo_id = :logo_id');
                    $stmt->execute(['logo_id' => $logoId]);
                }

                $stmt = $pdo->prepare('DELETE FROM sr_logo_manager_logos WHERE id = :id');
                $stmt->execute(['id' => $logoId]);

                $pdo->commit();

                $storageDeleteResult = sr_logo_manager_delete_storage_references($storageReferences);
                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'logo_manager.logo.deleted',
                    'target_type' => 'logo_manager_logo',
                    'target_id' => (string) $logoId,
                    'result' => 'success',
                    'message' => 'Logo placement deleted.',
                    'metadata' => [
                        'position_key' => (string) ($logo['position_key'] ?? ''),
                        'title' => (string) ($logo['title'] ?? ''),
                        'icon_variant_count' => count($iconVariants),
                        'deleted_storage_count' => (int) ($storageDeleteResult['deleted_count'] ?? 0),
                        'failed_storage_refs' => array_slice(array_map('strval', is_array($storageDeleteResult['failed'] ?? null) ? $storageDeleteResult['failed'] : []), 0, 20),
                    ],
                ]);

                $notice = '로고 배치를 삭제했습니다.';
                if (($storageDeleteResult['failed'] ?? []) !== []) {
                    $notice .= ' 일부 저장소 파일은 정리하지 못했습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                sr_log_exception($exception, 'logo_manager_logo_delete_failed');
                $errors[] = '로고 삭제 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'logo_status') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $logoId = sr_admin_post_positive_int('logo_id');
        $status = sr_post_string('status', 30);
        if ($logoId <= 0 || !in_array($status, $logoStatuses, true)) {
            $errors[] = '로고 상태 변경 값이 올바르지 않습니다.';
        }

        $logo = null;
        if ($errors === []) {
            $stmt = $pdo->prepare('SELECT id, position_key, status FROM sr_logo_manager_logos WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $logoId]);
            $row = $stmt->fetch();
            if (!is_array($row)) {
                $errors[] = '상태를 변경할 로고 배치를 찾을 수 없습니다. 목록을 새로고침한 뒤 다시 시도하세요.';
            } else {
                $logo = $row;
            }
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
            if (
                $status === 'disabled'
                && is_array($logo)
                && (string) ($logo['position_key'] ?? '') === sr_logo_manager_favicon_position_key()
            ) {
                $notice .= ' 이 파비콘 로고와 아이콘 세트는 head link 후보에서 제외됩니다. 같은 용도의 다른 활성 후보가 있으면 그 후보가 적용될 수 있습니다.';
            }
        }
    } elseif ($intent === 'batch_status') {
        if (!$logoTableExists) {
            $errors[] = '로고 매니저 DB 업데이트를 먼저 적용하세요.';
        }

        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_logo_ids'] ?? [];
        $selectedIds = sr_admin_positive_int_list_from_input($rawSelectedIds, $hasInvalidSelectedId);

        if ($operationKey !== 'logo_manager.set_status') {
            $errors[] = '허용되지 않은 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['active', 'disabled'], true)) {
            $errors[] = '변경할 로고 배치 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 로고 배치를 선택하세요.';
        }
        if ($hasInvalidSelectedId) {
            $errors[] = '선택한 로고 배치 ID 값이 올바르지 않습니다.';
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
                'SELECT id, position_key
                 FROM sr_logo_manager_logos
                 WHERE id IN (' . implode(', ', $placeholders) . ')'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $selectedLogoRows = $stmt->fetchAll();
            if (count($selectedLogoRows) !== count($selectedIds)) {
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
                $selectedFaviconCount = 0;
                foreach ($selectedLogoRows ?? [] as $selectedLogoRow) {
                    if ((string) ($selectedLogoRow['position_key'] ?? '') === sr_logo_manager_favicon_position_key()) {
                        $selectedFaviconCount++;
                    }
                }
                if ($targetStatus === 'disabled' && $selectedFaviconCount > 0) {
                    $notice .= ' 파비콘 로고를 중지하면 해당 로고와 아이콘 세트는 head link 후보에서 제외되며, 같은 용도의 다른 활성 후보가 있으면 그 후보가 적용될 수 있습니다.';
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
$activeLogoIdsByPosition = [];
foreach ($activeLogos as $positionKey => $activeLogo) {
    if (is_array($activeLogo) && (int) ($activeLogo['id'] ?? 0) > 0) {
        $activeLogoIdsByPosition[(string) $positionKey] = (int) $activeLogo['id'];
    }
}
$logoManagerFaviconResetMarker = sr_logo_manager_favicon_reset_marker($pdo);
$logoManagerNow = sr_now();

$logoSortOptions = sr_admin_logo_sort_options();
$logoDefaultSort = sr_admin_logo_default_sort();
$logoSort = sr_admin_sort_from_request($logoSortOptions, $logoDefaultSort, 'logo_sort', 'logo_dir');
$logos = [];
$iconVariantsByLogoId = [];
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
    if ($iconVariantTableExists && $logos !== []) {
        $logoIds = array_values(array_filter(array_map(static fn (array $logo): int => (int) ($logo['id'] ?? 0), $logos)));
        if ($logoIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($logoIds), '?'));
            $stmt = $pdo->prepare(
                'SELECT *
                 FROM sr_logo_manager_icon_variants
                 WHERE logo_id IN (' . $placeholders . ')
                   AND status = \'active\'
                 ORDER BY logo_id ASC, purpose ASC, width ASC, id DESC'
            );
            $stmt->execute($logoIds);
            foreach ($stmt->fetchAll() as $variant) {
                $iconVariantsByLogoId[(int) ($variant['logo_id'] ?? 0)][] = $variant;
            }
        }
    }
} else {
    $logoPagination = sr_admin_pagination_from_total($pdo, 0, 'logo_page');
    if ($notice === '') {
        $notice = '로고 매니저 DB 업데이트 적용 후 로고 배치를 등록할 수 있습니다.';
    }
}

include SR_ROOT . '/modules/logo_manager/views/admin-logo-manager.php';
