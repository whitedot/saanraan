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
    'SELECT p.id, p.title, p.body_text, p.body_format, p.status, p.skin_key, p.starts_at, p.ends_at, p.dismiss_cookie_days
     FROM sr_popup_layers p
     WHERE p.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $popupId]);
$sourcePopup = $stmt->fetch();
if (!is_array($sourcePopup)) {
    sr_admin_redirect_with_result(sr_admin_action_result(['복사할 팝업레이어를 찾을 수 없습니다.'], ''), $returnTo);
}

$values = [
    'title' => sr_post_string('title', 120),
];
$errors = [];

$title = sr_popup_layer_clean_single_line((string) $values['title'], 120);
if ($title === '') {
    $errors[] = '새 팝업레이어 제목을 입력하세요.';
}

if ($errors === []) {
    $now = sr_now();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_popup_layers
                (title, body_text, body_format, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
             VALUES
                (:title, :body_text, :body_format, :status, :skin_key, :starts_at, :ends_at, :dismiss_cookie_days, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $title,
            'body_text' => (string) ($sourcePopup['body_text'] ?? ''),
            'body_format' => (string) ($sourcePopup['body_format'] ?? 'plain') === 'html' ? 'html' : 'plain',
            'status' => 'draft',
            'skin_key' => (string) ($sourcePopup['skin_key'] ?? 'basic'),
            'starts_at' => $sourcePopup['starts_at'] ?? null,
            'ends_at' => $sourcePopup['ends_at'] ?? null,
            'dismiss_cookie_days' => (int) ($sourcePopup['dismiss_cookie_days'] ?? 1),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newPopupId = (int) $pdo->lastInsertId();
        if ((string) ($sourcePopup['body_format'] ?? 'plain') === 'html') {
            $bodyText = sr_popup_layer_clone_body_files($popupId, $newPopupId, (string) ($sourcePopup['body_text'] ?? ''));
            if ($bodyText !== (string) ($sourcePopup['body_text'] ?? '')) {
                $pdo->prepare('UPDATE sr_popup_layers SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $bodyText,
                    'updated_at' => $now,
                    'id' => $newPopupId,
                ]);
            }
        }
        $stmt = $pdo->prepare(
            'INSERT INTO sr_popup_layer_targets
                (popup_layer_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
             SELECT :new_popup_id, module_key, point_key, slot_key, subject_id, match_type, :created_at
             FROM sr_popup_layer_targets
             WHERE popup_layer_id = :source_popup_id'
        );
        $stmt->execute([
            'new_popup_id' => $newPopupId,
            'created_at' => $now,
            'source_popup_id' => $popupId,
        ]);
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
