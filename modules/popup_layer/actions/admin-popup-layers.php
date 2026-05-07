<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/popup_layer/helpers.php';

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);

$allowedStatuses = ['draft', 'enabled', 'disabled'];
$allowedMatchTypes = ['all', 'exact'];
$errors = [];
$notice = '';
$availableTargets = toy_popup_layer_available_targets($pdo);

if (toy_request_method() === 'POST') {
    toy_require_csrf();

    $intent = toy_post_string('intent', 40);
    $popupId = (int) toy_post_string('popup_id', 20);

    if ($intent === 'delete') {
        $stmt = $pdo->prepare('SELECT id FROM toy_popup_layers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $popupId]);
        if (!is_array($stmt->fetch())) {
            $errors[] = '팝업을 찾을 수 없습니다.';
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('DELETE FROM toy_popup_layer_targets WHERE popup_layer_id = :popup_layer_id');
                $stmt->execute(['popup_layer_id' => $popupId]);

                $stmt = $pdo->prepare('DELETE FROM toy_popup_layers WHERE id = :id');
                $stmt->execute(['id' => $popupId]);

                $pdo->commit();

                toy_audit_log($pdo, [
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
        $title = toy_popup_layer_clean_single_line(toy_post_string('title', 120), 120);
        $bodyText = toy_popup_layer_clean_text(toy_post_string('body_text', 5000), 5000);
        $status = toy_post_string('status', 30);
        $startsAtInput = toy_post_string('starts_at', 30);
        $endsAtInput = toy_post_string('ends_at', 30);
        $startsAt = toy_popup_layer_clean_admin_datetime($startsAtInput);
        $endsAt = toy_popup_layer_clean_admin_datetime($endsAtInput);
        $dismissCookieDays = max(0, min(365, (int) toy_post_string('dismiss_cookie_days', 5)));
        $targetOption = toy_post_string('target_option', 300);
        $target = toy_popup_layer_find_target($availableTargets, $targetOption);
        $matchType = toy_post_string('match_type', 20);
        $subjectId = toy_popup_layer_clean_subject_id(toy_post_string('subject_id', 80));

        if ($title === '') {
            $errors[] = '제목을 입력해야 합니다.';
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $errors[] = '상태 값이 올바르지 않습니다.';
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

        if ($target === null) {
            $errors[] = '노출 대상을 선택해야 합니다.';
        }

        if (!in_array($matchType, $allowedMatchTypes, true)) {
            $errors[] = '대상 매칭 방식이 올바르지 않습니다.';
        }

        if ($matchType === 'exact' && $subjectId === '') {
            $errors[] = '특정 subject ID를 입력해야 합니다.';
        }

        if ($errors === [] && $popupId > 0) {
            $stmt = $pdo->prepare('SELECT id FROM toy_popup_layers WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $popupId]);
            if (!is_array($stmt->fetch())) {
                $errors[] = '수정할 팝업을 찾을 수 없습니다.';
            }
        }

        if ($errors === [] && $target !== null) {
            try {
                $now = toy_now();
                $pdo->beginTransaction();

                if ($popupId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE toy_popup_layers
                         SET title = :title, body_text = :body_text, status = :status, starts_at = :starts_at, ends_at = :ends_at, dismiss_cookie_days = :dismiss_cookie_days, updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'status' => $status,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'dismiss_cookie_days' => $dismissCookieDays,
                        'updated_at' => $now,
                        'id' => $popupId,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO toy_popup_layers
                            (title, body_text, status, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
                         VALUES
                            (:title, :body_text, :status, :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
                    );
                    $stmt->execute([
                        'title' => $title,
                        'body_text' => $bodyText,
                        'status' => $status,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'dismiss_cookie_days' => $dismissCookieDays,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $popupId = (int) $pdo->lastInsertId();
                }

                $stmt = $pdo->prepare('DELETE FROM toy_popup_layer_targets WHERE popup_layer_id = :popup_layer_id');
                $stmt->execute(['popup_layer_id' => $popupId]);

                $stmt = $pdo->prepare(
                    'INSERT INTO toy_popup_layer_targets
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

                $pdo->commit();

                toy_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'popup_layer.saved',
                    'target_type' => 'popup_layer',
                    'target_id' => (string) $popupId,
                    'result' => 'success',
                    'message' => 'Popup layer saved.',
                    'metadata' => [
                        'module_key' => (string) $target['module_key'],
                        'point_key' => (string) $target['point_key'],
                        'slot_key' => (string) $target['slot_key'],
                        'match_type' => $matchType,
                    ],
                ]);

                $notice = '팝업을 저장했습니다.';
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
$editId = (int) toy_get_string('edit_id', 20);
if ($editId > 0) {
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.body_text, p.status, p.starts_at, p.ends_at, p.dismiss_cookie_days,
                t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
         FROM toy_popup_layers p
         LEFT JOIN toy_popup_layer_targets t ON t.popup_layer_id = p.id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $editId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        $editPopup = $row;
    }
}

$popups = [];
$stmt = $pdo->query(
    'SELECT p.id, p.title, p.status, p.starts_at, p.ends_at, p.dismiss_cookie_days, p.updated_at,
            t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
     FROM toy_popup_layers p
     LEFT JOIN toy_popup_layer_targets t ON t.popup_layer_id = p.id
     ORDER BY p.id DESC'
);
foreach ($stmt->fetchAll() as $row) {
    $popups[] = $row;
}

include TOY_ROOT . '/modules/popup_layer/views/admin-popup-layers.php';
