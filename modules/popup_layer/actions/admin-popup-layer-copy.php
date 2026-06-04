<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/popup_layer/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/popup-layers', 'edit');
sr_require_csrf();

$popupId = (int) sr_post_string('popup_id', 20);
$returnTo = sr_post_string('return_to', 300);
if ($returnTo === '' || !sr_is_safe_relative_url($returnTo)) {
    $returnTo = '/admin/popup-layers';
}

$stmt = $pdo->prepare(
    'SELECT p.id, p.title, p.body_text, p.status, p.skin_key, p.starts_at, p.ends_at, p.dismiss_cookie_days,
            t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
     FROM sr_popup_layers p
     LEFT JOIN sr_popup_layer_targets t ON t.popup_layer_id = p.id
     WHERE p.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $popupId]);
$sourcePopup = $stmt->fetch();
if (!is_array($sourcePopup)) {
    sr_admin_redirect_with_result(sr_admin_action_result(['복사할 팝업레이어를 찾을 수 없습니다.'], ''), $returnTo);
}

$values = [
    'title' => sr_post_string('title', 160),
];
$errors = [];

$title = sr_popup_layer_clean_single_line((string) $values['title'], 160);
if ($title === '') {
    $errors[] = '새 팝업레이어 제목을 입력하세요.';
}

if ($errors === []) {
    $now = sr_now();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_popup_layers
                (title, body_text, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
             VALUES
                (:title, :body_text, :status, :skin_key, :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $title,
            'body_text' => (string) ($sourcePopup['body_text'] ?? ''),
            'status' => 'draft',
            'skin_key' => (string) ($sourcePopup['skin_key'] ?? 'basic'),
            'starts_at' => $sourcePopup['starts_at'] ?? null,
            'ends_at' => $sourcePopup['ends_at'] ?? null,
            'dismiss_cookie_days' => (int) ($sourcePopup['dismiss_cookie_days'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newPopupId = (int) $pdo->lastInsertId();
        if ((string) ($sourcePopup['module_key'] ?? '') !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_popup_layer_targets
                    (popup_layer_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
                 VALUES
                    (:popup_layer_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
            );
            $stmt->execute([
                'popup_layer_id' => $newPopupId,
                'module_key' => (string) $sourcePopup['module_key'],
                'point_key' => (string) $sourcePopup['point_key'],
                'slot_key' => (string) $sourcePopup['slot_key'],
                'subject_id' => (string) ($sourcePopup['subject_id'] ?? ''),
                'match_type' => (string) ($sourcePopup['match_type'] ?? 'all'),
                'created_at' => $now,
            ]);
        }
        $pdo->commit();
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'popup_layer.copied',
            'target_type' => 'popup_layer',
            'target_id' => (string) $newPopupId,
            'result' => 'success',
            'message' => 'Popup layer copied.',
            'metadata' => ['source_popup_layer_id' => $popupId],
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '팝업레이어 복사본을 만들었습니다.'), '/admin/popup-layers/edit?id=' . (string) $newPopupId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = '팝업레이어 복사 중 오류가 발생했습니다.';
    }
}

sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $returnTo);
