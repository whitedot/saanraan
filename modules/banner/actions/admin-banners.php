<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'view');

$allowedStatuses = ['draft', 'enabled', 'disabled'];
$allowedMatchTypes = ['all', 'exact'];
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
if (!in_array($bannerAdminPage, ['list', 'form'], true)) {
    $bannerAdminPage = 'list';
}
if (sr_request_method() === 'GET' && $bannerAdminPage === 'form') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'edit');
}
$availableTargets = sr_banner_available_targets($pdo);
$stmt = $pdo->query(
    "SELECT DISTINCT module_key, point_key, slot_key
     FROM sr_banner_targets
     WHERE module_key <> '' AND point_key <> '' AND slot_key <> ''
     ORDER BY module_key ASC, point_key ASC, slot_key ASC"
);
foreach ($stmt->fetchAll() as $storedTargetRow) {
    $storedTarget = sr_banner_target_from_row($storedTargetRow, '선언이 사라진 저장 위치');
    if ($storedTarget !== null && sr_banner_find_target($availableTargets, sr_banner_target_option_value($storedTarget)) === null) {
        $availableTargets[] = $storedTarget;
    }
}
$bannerSettings = sr_banner_settings($pdo);
$bannerSkinOptions = sr_banner_skin_options();
$bannerSkinKey = sr_banner_skin_key($bannerSettings);
$bannerDefaultStatus = sr_banner_default_status($bannerSettings);
$bannerDefaultTargetOption = sr_banner_default_target_option($bannerSettings, $availableTargets);
$bannerDefaultSortOrder = sr_banner_default_sort_order($bannerSettings);
$allowedTargetOptions = [sr_banner_public_target_option_value()];
foreach ($availableTargets as $availableTarget) {
    $allowedTargetOptions[] = sr_banner_target_option_value($availableTarget);
}
$filters = [
    'status' => sr_admin_get_allowed_array('status', $allowedStatuses, 30),
    'target' => sr_admin_get_allowed_single_array('target', $allowedTargetOptions, 300),
    'target_service' => sr_get_string('target_service', 120),
    'field' => sr_get_string('field', 20),
    'q' => sr_banner_clean_single_line(sr_get_string('q', 120), 120),
];
if (!in_array($filters['field'], ['all', 'title', 'link'], true)) {
    $filters['field'] = 'all';
}
$bannerTargetServiceOptions = sr_banner_target_service_options($availableTargets, true);
if (!isset($bannerTargetServiceOptions[(string) $filters['target_service']])) {
    $filters['target_service'] = '';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', $intent === 'delete' ? 'delete' : 'edit');
    $bannerId = (int) sr_post_string('banner_id', 20);
    $returnTo = sr_admin_safe_get_url(sr_post_string('return_to', 500), '/admin/banners');

    if ($intent === 'delete') {
        if ($bannerId <= 0) {
            $errors[] = '삭제할 배너를 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $referenceResult = sr_read_reference_collect($pdo, 'banner-references.php', [
                'owner_module_key' => 'banner',
                'target_type' => 'banner',
                'target_id' => $bannerId,
                'target_key' => '',
            ]);
            if (($referenceResult['errors'] ?? []) !== []) {
                $errors[] = '배너 참조 계약 오류가 있어 삭제할 수 없습니다.';
            } elseif (($referenceResult['rows'] ?? []) !== []) {
                $errors[] = '다른 모듈에서 이 배너를 참조하고 있어 삭제할 수 없습니다. 참조 현황을 먼저 확인하세요.';
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM sr_banner_targets WHERE banner_id = :banner_id');
                $stmt->execute(['banner_id' => $bannerId]);
                $stmt = $pdo->prepare('DELETE FROM sr_banner_clicks WHERE banner_id = :banner_id');
                $stmt->execute(['banner_id' => $bannerId]);
                $stmt = $pdo->prepare('DELETE FROM sr_banners WHERE id = :id');
                $stmt->execute(['id' => $bannerId]);
                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'banner.deleted',
                    'target_type' => 'banner',
                    'target_id' => (string) $bannerId,
                    'result' => 'success',
                    'message' => 'Banner deleted.',
                ]);

                $notice = '배너를 삭제했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '배너 삭제 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'batch_status') {
        $operationKey = sr_post_string('operation_key', 80);
        $targetStatus = sr_post_string('target_status', 30);
        $rawSelectedIds = $_POST['selected_banner_ids'] ?? [];
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

        if ($operationKey !== 'banner.set_status') {
            $errors[] = '허용되지 않은 일괄 작업입니다.';
        }
        if (!in_array($targetStatus, ['enabled', 'disabled'], true)) {
            $errors[] = '변경할 배너 상태가 올바르지 않습니다.';
        }
        if ($selectedIds === []) {
            $errors[] = '상태를 변경할 배너를 선택하세요.';
        }
        if (count($selectedIds) > 100) {
            $errors[] = '배너 상태 일괄 변경은 한 번에 100건 이하로 실행하세요.';
        }

        $selectedBanners = [];
        if ($errors === []) {
            $placeholders = [];
            $params = [];
            foreach ($selectedIds as $index => $selectedId) {
                $paramKey = 'banner_id_' . (string) $index;
                $placeholders[] = ':' . $paramKey;
                $params[$paramKey] = $selectedId;
            }
            $stmt = $pdo->prepare(
                'SELECT id, title, status
                 FROM sr_banners
                 WHERE id IN (' . implode(', ', $placeholders) . ')
                 ORDER BY id ASC'
            );
            foreach ($params as $paramKey => $selectedId) {
                $stmt->bindValue($paramKey, $selectedId, PDO::PARAM_INT);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll() as $row) {
                $selectedBanners[(int) ($row['id'] ?? 0)] = $row;
            }
            if (count($selectedBanners) !== count($selectedIds)) {
                $errors[] = '선택한 배너 중 찾을 수 없는 항목이 있습니다. 목록을 새로고침한 뒤 다시 선택하세요.';
            }
        }

        $blockedReferenceIds = [];
        if ($errors === [] && $targetStatus === 'disabled') {
            foreach ($selectedBanners as $selectedBanner) {
                $selectedId = (int) ($selectedBanner['id'] ?? 0);
                if ($selectedId < 1 || (string) ($selectedBanner['status'] ?? '') !== 'enabled') {
                    continue;
                }
                $referenceResult = sr_read_reference_collect($pdo, 'banner-references.php', [
                    'owner_module_key' => 'banner',
                    'target_type' => 'banner',
                    'target_id' => $selectedId,
                    'target_key' => '',
                ]);
                if (($referenceResult['errors'] ?? []) !== []) {
                    $errors[] = '배너 참조 계약 오류가 있어 일괄 비활성화할 수 없습니다.';
                    break;
                }
                if (($referenceResult['rows'] ?? []) !== []) {
                    $blockedReferenceIds[] = $selectedId;
                }
            }
            if ($errors === [] && $blockedReferenceIds !== []) {
                $errors[] = '다른 모듈에서 참조 중인 배너가 있어 비활성화하지 않았습니다: ' . implode(', ', array_map('strval', $blockedReferenceIds));
            }
        }

        if ($errors === []) {
            $changedCount = 0;
            $skippedCount = 0;
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE sr_banners
                     SET status = :status, updated_at = :updated_at
                     WHERE id = :id AND status <> :status_compare'
                );
                $now = sr_now();
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
                    'event_type' => 'banner.bulk_status_updated',
                    'target_type' => 'banner',
                    'target_id' => '',
                    'result' => 'success',
                    'message' => 'Banner statuses updated in bulk.',
                    'metadata' => [
                        'operation_key' => $operationKey,
                        'target_status' => $targetStatus,
                        'requested_count' => count($selectedIds),
                        'changed_count' => $changedCount,
                        'skipped_count' => $skippedCount,
                        'selected_ids' => $selectedIds,
                    ],
                ]);

                $notice = '배너 ' . number_format($changedCount) . '건의 상태를 ' . sr_admin_code_label($targetStatus, 'content_status') . '(으)로 변경했습니다.';
                if ($skippedCount > 0) {
                    $notice .= ' 이미 같은 상태인 ' . number_format($skippedCount) . '건은 건너뛰었습니다.';
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = '배너 상태 일괄 변경 중 오류가 발생했습니다.';
            }
        }

        sr_admin_flash_result(sr_admin_action_result($errors, $notice));
        sr_redirect($returnTo);
    } elseif ($intent === 'save') {
        $isCreate = $bannerId <= 0;
        $title = sr_banner_clean_single_line(sr_post_string('title', 120), 120);
        $bodyText = sr_banner_clean_text(sr_post_string('body_text', 3000), 3000);
        $useImage = sr_post_string('use_image', 10) === '1';
        $rawLinkUrl = sr_post_string('link_url', 255);
        $linkUrl = sr_banner_clean_url($rawLinkUrl);
        $rawImageUrl = sr_post_string('image_url', 255);
        $imageUrl = sr_banner_clean_image_url($rawImageUrl);
        $imageUploadFile = $_FILES['image_upload'] ?? null;
        $imageUploadProvided = sr_banner_image_upload_was_provided($imageUploadFile);
        $uploadedImage = null;
        $status = sr_post_string('status', 30);
        $skinKey = sr_post_string('skin_key', 40);
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_banner_clean_admin_datetime($startsAtInput);
        $endsAt = sr_banner_clean_admin_datetime($endsAtInput);
        $sortOrder = max(-100000, min(100000, (int) sr_post_string('sort_order', 20)));
        $postedTargetResult = sr_banner_normalize_posted_target_option(
            $availableTargets,
            sr_post_string('target_service_key', 120),
            sr_post_string('target_detail_option', 300),
            sr_post_string('target_option', 300)
        );
        $targetOption = (string) $postedTargetResult['option'];
        $isPublicBanner = (bool) $postedTargetResult['is_public'];
        $target = is_array($postedTargetResult['target']) ? $postedTargetResult['target'] : null;
        $matchType = sr_post_string('match_type', 20);
        $subjectId = sr_banner_clean_single_line(sr_post_string('subject_id', 80), 80);

        if ($title === '') {
            $errors[] = '제목을 입력하세요.';
        }
        if ($bodyText === '') {
            $errors[] = $useImage ? '이미지 대체텍스트를 입력하세요.' : '표시할 텍스트를 입력하세요.';
        }
        if ($rawLinkUrl !== '' && $linkUrl === '') {
            $errors[] = '링크 URL은 /로 시작하는 내부 URL 또는 http/https URL이어야 합니다.';
        }
        if (!$useImage) {
            $rawImageUrl = '';
            $imageUrl = '';
            $imageUploadProvided = false;
            $imageUploadFile = null;
        }
        if ($useImage && !$imageUploadProvided && $rawImageUrl === '') {
            $errors[] = '이미지 URL을 입력하거나 이미지 파일을 업로드하세요.';
        }
        if ($useImage && !$imageUploadProvided && $rawImageUrl !== '' && $imageUrl === '') {
            $errors[] = '이미지 URL은 /로 시작하는 내부 경로 또는 http/https URL이어야 합니다.';
        }
        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '상태 값이 올바르지 않습니다.';
        }
        if (!isset($bannerSkinOptions[$skinKey])) {
            $errors[] = '배너 스킨 값이 올바르지 않습니다.';
            $skinKey = $bannerSkinKey;
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
        if (!$isPublicBanner && $target === null) {
            if ($bannerId > 0) {
                $stmt = $pdo->prepare(
                    'SELECT module_key, point_key, slot_key
                     FROM sr_banner_targets
                     WHERE banner_id = :banner_id
                     LIMIT 1'
                );
                $stmt->execute(['banner_id' => $bannerId]);
                $storedTarget = $stmt->fetch();
                if (is_array($storedTarget)) {
                    $storedTargetData = sr_banner_target_from_row($storedTarget);
                    if ($storedTargetData !== null && sr_banner_target_option_value($storedTargetData) === $targetOption) {
                        $target = $storedTargetData;
                    }
                }
            }

            if ($target === null) {
                $errors[] = (string) $postedTargetResult['error'] !== '' ? (string) $postedTargetResult['error'] : '공용 배너 또는 모듈이 선언한 노출 위치를 선택하세요.';
            }
        }
        if ($isPublicBanner) {
            $matchType = 'all';
            $subjectId = '';
        }
        if (!$isPublicBanner) {
            $subjectTargetTypeMap = sr_banner_subject_target_type_map($pdo, $availableTargets);
            if (!isset($subjectTargetTypeMap[$targetOption])) {
                $matchType = 'all';
                $subjectId = '';
            }
        }
        if (!in_array($matchType, $allowedMatchTypes, true)) {
            $errors[] = '매칭 방식이 올바르지 않습니다.';
        }
        if (!$isPublicBanner && $matchType === 'exact' && $subjectId === '') {
            $errors[] = '노출 대상 번호를 입력하세요.';
        }
        if (($isPublicBanner || $target !== null) && !sr_banner_skin_supports($skinKey, sr_banner_target_placement_kind($target, $isPublicBanner))) {
            $errors[] = '선택한 배너 스킨은 노출 위치와 호환되지 않습니다.';
        }

        $existingBanner = null;
        if ($errors === [] && $bannerId > 0) {
            $stmt = $pdo->prepare('SELECT id, status FROM sr_banners WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $bannerId]);
            $existingBanner = $stmt->fetch();
            if (!is_array($existingBanner)) {
                $errors[] = '수정할 배너를 찾을 수 없습니다.';
            }
        }

        if ($errors === [] && is_array($existingBanner) && (string) ($existingBanner['status'] ?? '') === 'enabled' && $status !== 'enabled') {
            $referenceResult = sr_read_reference_collect($pdo, 'banner-references.php', [
                'owner_module_key' => 'banner',
                'target_type' => 'banner',
                'target_id' => $bannerId,
                'target_key' => '',
            ]);
            if (($referenceResult['errors'] ?? []) !== []) {
                $errors[] = '배너 참조 계약 오류가 있어 상태를 변경할 수 없습니다.';
            } elseif (($referenceResult['rows'] ?? []) !== []) {
                $errors[] = '다른 모듈에서 이 배너를 참조하고 있어 비활성화할 수 없습니다. 참조 현황을 먼저 확인하세요.';
            }
        }

        if ($errors === [] && $imageUploadProvided) {
            if (!is_array($imageUploadFile)) {
                $errors[] = '업로드할 배너 이미지를 확인할 수 없습니다.';
            } else {
                try {
                    $uploadedImage = sr_banner_upload_image($imageUploadFile);
                    if (is_array($uploadedImage)) {
                        $imageUrl = (string) $uploadedImage['url'];
                    }
                } catch (Throwable $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        if ($errors === [] && ($isPublicBanner || $target !== null)) {
            try {
                $now = sr_now();
                $pdo->beginTransaction();

                if ($bannerId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE sr_banners
                         SET title = :title, body_text = :body_text, link_url = :link_url, image_url = :image_url,
                             status = :status, skin_key = :skin_key, starts_at = :starts_at, ends_at = :ends_at, sort_order = :sort_order, updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'link_url' => $linkUrl,
                        'image_url' => $imageUrl,
                        'status' => $status,
                        'skin_key' => $skinKey,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'sort_order' => $sortOrder,
                        'updated_at' => $now,
                        'id' => $bannerId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO sr_banners
                            (title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, created_at, updated_at)
                         VALUES
                            (:title, :body_text, :link_url, :image_url, :status, :skin_key, :starts_at, :ends_at, :sort_order, :created_at, :updated_at)'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'link_url' => $linkUrl,
                        'image_url' => $imageUrl,
                        'status' => $status,
                        'skin_key' => $skinKey,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'sort_order' => $sortOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $bannerId = (int) $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare('DELETE FROM sr_banner_targets WHERE banner_id = :banner_id');
                $stmt->execute(['banner_id' => $bannerId]);

                if (!$isPublicBanner && $target !== null) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO sr_banner_targets
                            (banner_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
                         VALUES
                            (:banner_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
                    );
                    $stmt->execute([
                        'banner_id' => $bannerId,
                        'module_key' => (string) $target['module_key'],
                        'point_key' => (string) $target['point_key'],
                        'slot_key' => (string) $target['slot_key'],
                        'subject_id' => $matchType === 'exact' ? $subjectId : '',
                        'match_type' => $matchType,
                        'created_at' => $now,
                    ]);
                }

                $pdo->commit();

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'banner.saved',
                    'target_type' => 'banner',
                    'target_id' => (string) $bannerId,
                    'result' => 'success',
                    'message' => 'Banner saved.',
                    'metadata' => [
                        'scope' => $isPublicBanner ? 'public' : 'targeted',
                        'module_key' => $target !== null ? (string) $target['module_key'] : '',
                        'point_key' => $target !== null ? (string) $target['point_key'] : '',
                        'slot_key' => $target !== null ? (string) $target['slot_key'] : '',
                        'skin_key' => $skinKey,
                    ],
                ]);

                $notice = '배너를 저장했습니다.';
                if ($isCreate) {
                    sr_admin_flash_result(sr_admin_action_result([], $notice));
                    sr_redirect('/admin/banners');
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if (is_array($uploadedImage)) {
                    sr_banner_delete_uploaded_image($uploadedImage);
                }
                $errors[] = '배너 저장 중 오류가 발생했습니다.';
            }
        }
    } else {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }

    sr_admin_flash_result(sr_admin_action_result($errors, $notice));
    sr_redirect($returnTo);
}

$editBanner = null;
$editId = (int) sr_get_string('edit_id', 20);
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT b.id, b.title, b.body_text, b.link_url, b.image_url, b.status, b.skin_key, b.starts_at, b.ends_at, b.sort_order,
                t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
         FROM sr_banners b
         LEFT JOIN sr_banner_targets t ON t.banner_id = b.id
         WHERE b.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $editBanner = $row;
        $editTarget = sr_banner_target_from_row($row, '선언이 사라진 저장 위치');
        if ($editTarget !== null && sr_banner_find_target($availableTargets, sr_banner_target_option_value($editTarget)) === null) {
            $availableTargets[] = $editTarget;
        }
    }
}

$targetLabels = sr_banner_target_labels($availableTargets);

$bannerStatusCounts = [
    'total' => 0,
    'draft' => 0,
    'enabled' => 0,
    'disabled' => 0,
];
$stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_banners GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $bannerStatusCounts)) {
        $bannerStatusCounts[$status] = $count;
    }
    $bannerStatusCounts['total'] += $count;
}

$banners = [];
$bannerReadReferencesById = [];
$bannerSql = 'SELECT b.id, b.title, b.link_url, b.status, b.skin_key, b.starts_at, b.ends_at, b.sort_order, b.click_count, b.updated_at,
                     t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
              FROM sr_banners b
              LEFT JOIN sr_banner_targets t ON t.banner_id = b.id';
$bannerParams = [];
$bannerWhere = [];
$bannerSortOptions = [
    'title' => ['columns' => ['b.title', 'b.id']],
    'status' => ['columns' => ['b.status', 'b.id']],
    'skin_key' => ['columns' => ['b.skin_key', 'b.id']],
    'click_count' => ['columns' => ['b.click_count', 'b.id']],
    'target' => ['columns' => ['t.module_key', 't.point_key', 't.slot_key', 't.match_type', 't.subject_id', 'b.id']],
    'starts_at' => ['columns' => ['b.starts_at', 'b.id']],
    'ends_at' => ['columns' => ['b.ends_at', 'b.id']],
    'sort_order' => ['columns' => ['b.sort_order', 'b.id']],
];
$bannerDefaultSort = sr_admin_sort_default('sort_order', 'asc');
$bannerSort = sr_admin_sort_from_request($bannerSortOptions, $bannerDefaultSort);
if (($filters['status'] ?? []) !== []) {
    [$statusCondition, $statusParams] = sr_admin_sql_in_condition('b.status', 'status', $filters['status']);
    $bannerWhere[] = $statusCondition;
    $bannerParams = array_merge($bannerParams, $statusParams);
}
if (($filters['target'] ?? []) !== []) {
    $targetWhere = [];
    foreach ($filters['target'] as $targetIndex => $targetOption) {
        if (sr_banner_is_public_target_option((string) $targetOption)) {
            $targetWhere[] = 't.id IS NULL';
            continue;
        }
        $filterTarget = sr_banner_target_from_option((string) $targetOption);
        if ($filterTarget === null) {
            continue;
        }
        $targetWhere[] = '(t.module_key = :filter_module_key_' . $targetIndex . ' AND t.point_key = :filter_point_key_' . $targetIndex . ' AND t.slot_key = :filter_slot_key_' . $targetIndex . ')';
        $bannerParams['filter_module_key_' . $targetIndex] = (string) $filterTarget['module_key'];
        $bannerParams['filter_point_key_' . $targetIndex] = (string) $filterTarget['point_key'];
        $bannerParams['filter_slot_key_' . $targetIndex] = (string) $filterTarget['slot_key'];
    }
    if ($targetWhere !== []) {
        $bannerWhere[] = '(' . implode(' OR ', $targetWhere) . ')';
    }
} elseif (($filters['target_service'] ?? '') !== '') {
    if ((string) $filters['target_service'] === sr_banner_public_target_option_value()) {
        $bannerWhere[] = 't.id IS NULL';
    } else {
        $bannerWhere[] = 't.module_key = :filter_target_service';
        $bannerParams['filter_target_service'] = (string) $filters['target_service'];
    }
}
if ($filters['q'] !== '') {
    if ($filters['field'] === 'title') {
        $bannerWhere[] = 'b.title LIKE :keyword';
        $bannerParams['keyword'] = '%' . $filters['q'] . '%';
    } elseif ($filters['field'] === 'link') {
        $bannerWhere[] = 'b.link_url LIKE :keyword';
        $bannerParams['keyword'] = '%' . $filters['q'] . '%';
    } else {
        $bannerWhere[] = '(b.title LIKE :title_keyword OR b.link_url LIKE :link_keyword)';
        $bannerParams['title_keyword'] = '%' . $filters['q'] . '%';
        $bannerParams['link_keyword'] = '%' . $filters['q'] . '%';
    }
}
if ($bannerWhere !== []) {
    $bannerSql .= ' WHERE ' . implode(' AND ', $bannerWhere);
}
$bannerPagination = sr_admin_pagination_from_total($pdo, 0);
if ($bannerAdminPage === 'list') {
    $bannerCountSql = 'SELECT COUNT(*) AS count_value
                       FROM sr_banners b
                       LEFT JOIN sr_banner_targets t ON t.banner_id = b.id'
        . ($bannerWhere !== [] ? ' WHERE ' . implode(' AND ', $bannerWhere) : '');
    $stmt = $pdo->prepare($bannerCountSql);
    $stmt->execute($bannerParams);
    $bannerCountRow = $stmt->fetch();
    $bannerPagination = sr_admin_pagination_from_total($pdo, is_array($bannerCountRow) ? (int) ($bannerCountRow['count_value'] ?? 0) : 0);
    $bannerSql .= sr_admin_sort_order_sql($bannerSortOptions, $bannerSort, $bannerDefaultSort) . ' LIMIT :limit_value OFFSET :offset_value';
    $stmt = $pdo->prepare($bannerSql);
    $stmt->bindValue('limit_value', (int) $bannerPagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue('offset_value', sr_admin_pagination_offset($bannerPagination), PDO::PARAM_INT);
    foreach ($bannerParams as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $banners[] = $row;
    }
    foreach ($banners as $banner) {
        $bannerId = (int) ($banner['id'] ?? 0);
        if ($bannerId < 1) {
            continue;
        }
        $bannerReadReferencesById[$bannerId] = sr_read_reference_collect($pdo, 'banner-references.php', [
            'owner_module_key' => 'banner',
            'target_type' => 'banner',
            'target_id' => $bannerId,
            'target_key' => '',
        ]);
    }
}

include SR_ROOT . '/modules/banner/views/admin-banners.php';
