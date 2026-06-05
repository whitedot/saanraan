<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'view');

$allowedStatuses = ['draft', 'enabled', 'disabled'];
$allowedMatchTypes = ['all', 'exact'];
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$popupLayerAdminPage = isset($popupLayerAdminPage) ? (string) $popupLayerAdminPage : 'list';
if (!in_array($popupLayerAdminPage, ['list', 'form'], true)) {
    $popupLayerAdminPage = 'list';
}
if (sr_request_method() === 'GET' && $popupLayerAdminPage === 'form') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'edit');
}
$availableTargets = sr_popup_layer_available_targets($pdo);
$popupLayerSettings = sr_popup_layer_settings($pdo);
$popupLayerSkinOptions = sr_popup_layer_skin_options();
$popupLayerSkinKey = sr_popup_layer_skin_key($popupLayerSettings);
$popupLayerDefaultStatus = sr_popup_layer_default_status($popupLayerSettings);
$popupLayerDefaultTargetOption = sr_popup_layer_default_target_option($popupLayerSettings, $availableTargets);
$popupLayerDefaultMatchType = sr_popup_layer_default_match_type($popupLayerSettings);
$popupLayerDefaultDismissCookieDays = sr_popup_layer_default_dismiss_cookie_days($popupLayerSettings);
$popupLayerEditorKey = sr_admin_editor_key($pdo);
$popupLayerEditorAttributes = sr_editor_textarea_attributes($pdo, $popupLayerEditorKey, 'admin_basic');
if ($popupLayerEditorAttributes !== '' && $popupLayerEditorKey === 'ckeditor') {
    $popupLayerEditorAttributes .= ' data-sr-editor-upload-url="' . sr_e(sr_popup_layer_body_file_upload_url()) . '" data-sr-editor-upload-field="upload" data-sr-editor-upload-csrf="' . sr_e(sr_csrf_token()) . '" data-sr-editor-upload-token="' . sr_e(sr_popup_layer_body_file_upload_token()) . '"';
}
$allowedTargetOptions = [sr_popup_layer_public_target_option_value()];
foreach ($availableTargets as $availableTarget) {
    $allowedTargetOptions[] = sr_popup_layer_target_option_value($availableTarget);
}
$filters = [
    'status' => sr_admin_get_allowed_array('status', $allowedStatuses, 30),
    'target' => sr_admin_get_allowed_single_array('target', $allowedTargetOptions, 300),
    'field' => sr_get_string('field', 20),
    'q' => sr_popup_layer_clean_single_line(sr_get_string('q', 120), 120),
];
if (!in_array($filters['field'], ['all', 'title', 'subject'], true)) {
    $filters['field'] = 'all';
}

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers', $intent === 'delete' ? 'delete' : 'edit');
    $popupId = (int) sr_post_string('popup_id', 20);

    if ($intent === 'delete') {
        $stmt = $pdo->prepare('SELECT id FROM sr_popup_layers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $popupId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = '팝업을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            $referenceResult = sr_read_reference_collect($pdo, 'popup-layer-references.php', [
                'owner_module_key' => 'popup_layer',
                'target_type' => 'popup_layer',
                'target_id' => $popupId,
                'target_key' => '',
            ]);
            if (($referenceResult['errors'] ?? []) !== []) {
                $errors[] = '팝업레이어 참조 계약 오류가 있어 삭제할 수 없습니다.';
            } elseif (($referenceResult['rows'] ?? []) !== []) {
                $errors[] = '다른 모듈에서 이 팝업레이어를 참조하고 있어 삭제할 수 없습니다. 참조 현황을 먼저 확인하세요.';
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM sr_popup_layer_targets WHERE popup_layer_id = :popup_layer_id');
                $stmt->execute(['popup_layer_id' => $popupId]);

                $stmt = $pdo->prepare('DELETE FROM sr_popup_layers WHERE id = :id');
                $stmt->execute(['id' => $popupId]);

                $pdo->commit();
                sr_popup_layer_cleanup_body_files_for_deleted_layers([$popupId]);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'popup_layer.deleted',
                    'target_type' => 'popup_layer',
                    'target_id' => (string) $popupId,
                    'result' => 'success',
                    'message' => 'Popup layer deleted.',
                ]);

                $notice = '팝업을 삭제했습니다.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = '팝업 삭제 중 오류가 발생했습니다.';
            }
        }
    } elseif ($intent === 'save') {
        $isCreate = $popupId <= 0;
        $title = sr_popup_layer_clean_single_line(sr_post_string('title', 120), 120);
        $bodyFormat = $popupLayerEditorKey === 'ckeditor' && sr_post_string('body_format', 20) === 'html' ? 'html' : 'plain';
        $rawBodyText = sr_post_string('body_text', 5000);
        $bodyText = $bodyFormat === 'html'
            ? sr_sanitize_rich_text_html(sr_popup_layer_clean_text($rawBodyText, 5000))
            : sr_popup_layer_clean_text($rawBodyText, 5000);
        $status = sr_post_string('status', 30);
        $skinKey = sr_post_string('skin_key', 40);
        $startsAtInput = sr_post_string('starts_at', 30);
        $endsAtInput = sr_post_string('ends_at', 30);
        $startsAt = sr_popup_layer_clean_admin_datetime($startsAtInput);
        $endsAt = sr_popup_layer_clean_admin_datetime($endsAtInput);
        $dismissCookieDays = max(0, min(365, (int) sr_post_string('dismiss_cookie_days', 5)));
        $targetOption = sr_post_string('target_option', 300);
        $isPublicPopupLayer = sr_popup_layer_is_public_target_option($targetOption);
        $target = $isPublicPopupLayer ? null : sr_popup_layer_find_target($availableTargets, $targetOption);
        $matchType = sr_post_string('match_type', 20);
        $subjectId = sr_popup_layer_clean_subject_id(sr_post_string('subject_id', 80));

        if ($title === '') {
            $errors[] = '제목을 입력해야 합니다.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '상태 값이 올바르지 않습니다.';
        }

        if (!isset($popupLayerSkinOptions[$skinKey])) {
            $errors[] = '팝업 스킨 값이 올바르지 않습니다.';
            $skinKey = $popupLayerSkinKey;
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

        if (!$isPublicPopupLayer && $target === null) {
            $errors[] = '공용 팝업레이어 또는 노출 대상을 선택해야 합니다.';
        }

        if (!in_array($matchType, $allowedMatchTypes, true)) {
            $errors[] = '대상 매칭 방식이 올바르지 않습니다.';
        }

        if (!$isPublicPopupLayer && $matchType === 'exact' && $subjectId === '') {
            $errors[] = '특정 subject ID를 입력해야 합니다.';
        }

        if ($errors === [] && $popupId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM sr_popup_layers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $popupId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '수정할 팝업을 찾을 수 없습니다.';
            }
        }

        if ($errors === [] && ($isPublicPopupLayer || $target !== null)) {
            try {
                $now = sr_now();
                $pdo->beginTransaction();

                if ($popupId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE sr_popup_layers
                         SET title = :title, body_text = :body_text, body_format = :body_format, status = :status, skin_key = :skin_key, starts_at = :starts_at, ends_at = :ends_at, dismiss_cookie_days = :dismiss_cookie_days, updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'body_format' => $bodyFormat,
                        'status' => $status,
                        'skin_key' => $skinKey,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'dismiss_cookie_days' => $dismissCookieDays,
                        'updated_at' => $now,
                        'id' => $popupId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO sr_popup_layers
                            (title, body_text, body_format, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
                         VALUES
                            (:title, :body_text, :body_format, :status, :skin_key, :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'body_format' => $bodyFormat,
                        'status' => $status,
                        'skin_key' => $skinKey,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'dismiss_cookie_days' => $dismissCookieDays,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $popupId = (int) $pdo->lastInsertId();
                }

                if ($bodyFormat === 'html') {
                    $finalBodyText = sr_popup_layer_finalize_body_files($popupId, $bodyText);
                    if ($finalBodyText !== $bodyText) {
                        $bodyText = $finalBodyText;
                        $pdo->prepare('UPDATE sr_popup_layers SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                            'body_text' => $bodyText,
                            'updated_at' => $now,
                            'id' => $popupId,
                        ]);
                    }
                } else {
                    sr_popup_layer_cleanup_unreferenced_body_files($popupId, '');
                }

                $stmt = $pdo->prepare('DELETE FROM sr_popup_layer_targets WHERE popup_layer_id = :popup_layer_id');
                $stmt->execute(['popup_layer_id' => $popupId]);

                if (!$isPublicPopupLayer && $target !== null) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO sr_popup_layer_targets
                            (popup_layer_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
                         VALUES
                            (:popup_layer_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
                    );
                    $stmt->execute([
                        'popup_layer_id' => $popupId,
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
                    'event_type' => 'popup_layer.saved',
                    'target_type' => 'popup_layer',
                    'target_id' => (string) $popupId,
                    'result' => 'success',
                    'message' => 'Popup layer saved.',
                    'metadata' => [
                        'scope' => $isPublicPopupLayer ? 'public' : 'targeted',
                        'module_key' => $target !== null ? (string) $target['module_key'] : '',
                        'point_key' => $target !== null ? (string) $target['point_key'] : '',
                        'slot_key' => $target !== null ? (string) $target['slot_key'] : '',
                        'match_type' => $matchType,
                        'skin_key' => $skinKey,
                    ],
                ]);

                $notice = '팝업을 저장했습니다.';
                if ($isCreate) {
                    sr_admin_flash_result(sr_admin_action_result([], $notice));
                    sr_redirect('/admin/popup-layers');
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = '팝업 저장 중 오류가 발생했습니다.';
            }
        }
    } else {
        $errors[] = '요청한 작업을 처리할 수 없습니다.';
    }
}

$editPopup = null;
$editId = (int) sr_get_string('edit_id', 20);
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, p.body_format, p.status, p.skin_key, p.starts_at, p.ends_at, p.dismiss_cookie_days,
                t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
         FROM sr_popup_layers p
         LEFT JOIN sr_popup_layer_targets t ON t.popup_layer_id = p.id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $editPopup = $row;
    }
}

$popupStatusCounts = [
    'total' => 0,
    'draft' => 0,
    'enabled' => 0,
    'disabled' => 0,
];
$stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_popup_layers GROUP BY status');
foreach ($stmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    $count = (int) ($row['count_value'] ?? 0);
    if (array_key_exists($status, $popupStatusCounts)) {
        $popupStatusCounts[$status] = $count;
    }
    $popupStatusCounts['total'] += $count;
}

$popups = [];
$popupLayerReadReferencesById = [];
$popupSql = 'SELECT p.id, p.title, p.status, p.skin_key, p.starts_at, p.ends_at, p.dismiss_cookie_days, p.updated_at,
                    t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
             FROM sr_popup_layers p
             LEFT JOIN sr_popup_layer_targets t ON t.popup_layer_id = p.id';
$popupParams = [];
$popupWhere = [];
if (($filters['status'] ?? []) !== []) {
    [$statusCondition, $statusParams] = sr_admin_sql_in_condition('p.status', 'status', $filters['status']);
    $popupWhere[] = $statusCondition;
    $popupParams = array_merge($popupParams, $statusParams);
}
if (($filters['target'] ?? []) !== []) {
    $targetWhere = [];
    foreach ($filters['target'] as $targetIndex => $targetOption) {
        if (sr_popup_layer_is_public_target_option((string) $targetOption)) {
            $targetWhere[] = 't.id IS NULL';
            continue;
        }
        $filterTarget = sr_popup_layer_find_target($availableTargets, (string) $targetOption);
        if ($filterTarget === null) {
            continue;
        }
        $targetWhere[] = '(t.module_key = :filter_module_key_' . $targetIndex . ' AND t.point_key = :filter_point_key_' . $targetIndex . ' AND t.slot_key = :filter_slot_key_' . $targetIndex . ')';
        $popupParams['filter_module_key_' . $targetIndex] = (string) $filterTarget['module_key'];
        $popupParams['filter_point_key_' . $targetIndex] = (string) $filterTarget['point_key'];
        $popupParams['filter_slot_key_' . $targetIndex] = (string) $filterTarget['slot_key'];
    }
    if ($targetWhere !== []) {
        $popupWhere[] = '(' . implode(' OR ', $targetWhere) . ')';
    }
}
if ($filters['q'] !== '') {
    if ($filters['field'] === 'title') {
        $popupWhere[] = 'p.title LIKE :keyword';
        $popupParams['keyword'] = '%' . $filters['q'] . '%';
    } elseif ($filters['field'] === 'subject') {
        $popupWhere[] = 't.subject_id LIKE :keyword';
        $popupParams['keyword'] = '%' . $filters['q'] . '%';
    } else {
        $popupWhere[] = '(p.title LIKE :title_keyword OR t.subject_id LIKE :subject_keyword)';
        $popupParams['title_keyword'] = '%' . $filters['q'] . '%';
        $popupParams['subject_keyword'] = '%' . $filters['q'] . '%';
    }
}
if ($popupWhere !== []) {
    $popupSql .= ' WHERE ' . implode(' AND ', $popupWhere);
}
$popupPagination = sr_admin_pagination_from_total($pdo, 0);
$popupSortOptions = [
    'title' => ['columns' => ['p.title', 'p.id']],
    'status' => ['columns' => ['p.status', 'p.id']],
    'skin_key' => ['columns' => ['p.skin_key', 'p.id']],
    'target' => ['columns' => ['t.module_key', 't.point_key', 't.slot_key', 't.match_type', 't.subject_id', 'p.id']],
    'starts_at' => ['columns' => ['p.starts_at', 'p.id']],
    'ends_at' => ['columns' => ['p.ends_at', 'p.id']],
    'dismiss_cookie_days' => ['columns' => ['p.dismiss_cookie_days', 'p.id']],
    'updated_at' => ['columns' => ['p.updated_at', 'p.id']],
];
$popupDefaultSort = sr_admin_sort_default('updated_at', 'desc');
$popupSort = sr_admin_sort_from_request($popupSortOptions, $popupDefaultSort);
if ($popupLayerAdminPage === 'list') {
    $popupCountSql = 'SELECT COUNT(*) AS count_value
                      FROM sr_popup_layers p
                      LEFT JOIN sr_popup_layer_targets t ON t.popup_layer_id = p.id'
        . ($popupWhere !== [] ? ' WHERE ' . implode(' AND ', $popupWhere) : '');
    $stmt = $pdo->prepare($popupCountSql);
    $stmt->execute($popupParams);
    $popupCountRow = $stmt->fetch();
    $popupPagination = sr_admin_pagination_from_total($pdo, is_array($popupCountRow) ? (int) ($popupCountRow['count_value'] ?? 0) : 0);
    $popupSql .= sr_admin_sort_order_sql($popupSortOptions, $popupSort, $popupDefaultSort) . ' LIMIT :limit_value OFFSET :offset_value';
    $stmt = $pdo->prepare($popupSql);
    $stmt->bindValue('limit_value', (int) $popupPagination['per_page'], PDO::PARAM_INT);
    $stmt->bindValue('offset_value', sr_admin_pagination_offset($popupPagination), PDO::PARAM_INT);
    foreach ($popupParams as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, PDO::PARAM_STR);
    }
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $popups[] = $row;
    }
    foreach ($popups as $popup) {
        $popupId = (int) ($popup['id'] ?? 0);
        if ($popupId < 1) {
            continue;
        }
        $popupLayerReadReferencesById[$popupId] = sr_read_reference_collect($pdo, 'popup-layer-references.php', [
            'owner_module_key' => 'popup_layer',
            'target_type' => 'popup_layer',
            'target_id' => $popupId,
            'target_key' => '',
        ]);
    }
}

include SR_ROOT . '/modules/popup_layer/views/admin-popup-layers.php';
