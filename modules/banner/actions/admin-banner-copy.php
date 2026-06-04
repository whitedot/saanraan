<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'edit');
sr_require_csrf();

$bannerId = (int) sr_post_string('banner_id', 20);
$returnTo = sr_post_string('return_to', 300);
if ($returnTo === '' || !sr_is_safe_relative_url($returnTo)) {
    $returnTo = '/admin/banners';
}

$stmt = $pdo->prepare(
    'SELECT b.id, b.title, b.body_text, b.link_url, b.image_url, b.status, b.skin_key, b.starts_at, b.ends_at, b.sort_order, b.click_count,
            t.module_key, t.point_key, t.slot_key, t.subject_id, t.match_type
     FROM sr_banners b
     LEFT JOIN sr_banner_targets t ON t.banner_id = b.id
     WHERE b.id = :id
     LIMIT 1'
);
$stmt->execute(['id' => $bannerId]);
$sourceBanner = $stmt->fetch();
if (!is_array($sourceBanner)) {
    sr_admin_redirect_with_result(sr_admin_action_result(['복사할 배너를 찾을 수 없습니다.'], ''), $returnTo);
}

$values = [
    'title' => sr_post_string('title', 160),
];
$copyClicks = ($_POST['copy_clicks'] ?? '') === '1';
$errors = [];

$title = sr_banner_clean_single_line((string) $values['title'], 160);
if ($title === '') {
    $errors[] = '새 배너 제목을 입력하세요.';
}

if ($errors === []) {
    $now = sr_now();
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO sr_banners
                (title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, click_count, created_at, updated_at)
             VALUES
                (:title, :body_text, :link_url, :image_url, :status, :skin_key, :starts_at, :ends_at, :sort_order, :click_count, :created_at, :updated_at)'
        );
        $stmt->execute([
            'title' => $title,
            'body_text' => (string) ($sourceBanner['body_text'] ?? ''),
            'link_url' => (string) ($sourceBanner['link_url'] ?? ''),
            'image_url' => (string) ($sourceBanner['image_url'] ?? ''),
            'status' => 'draft',
            'skin_key' => (string) ($sourceBanner['skin_key'] ?? 'basic'),
            'starts_at' => $sourceBanner['starts_at'] ?? null,
            'ends_at' => $sourceBanner['ends_at'] ?? null,
            'sort_order' => (int) ($sourceBanner['sort_order'] ?? 0),
            'click_count' => $copyClicks ? (int) ($sourceBanner['click_count'] ?? 0) : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newBannerId = (int) $pdo->lastInsertId();
        if ((string) ($sourceBanner['module_key'] ?? '') !== '') {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_banner_targets
                    (banner_id, module_key, point_key, slot_key, subject_id, match_type, created_at)
                 VALUES
                    (:banner_id, :module_key, :point_key, :slot_key, :subject_id, :match_type, :created_at)'
            );
            $stmt->execute([
                'banner_id' => $newBannerId,
                'module_key' => (string) $sourceBanner['module_key'],
                'point_key' => (string) $sourceBanner['point_key'],
                'slot_key' => (string) $sourceBanner['slot_key'],
                'subject_id' => (string) ($sourceBanner['subject_id'] ?? ''),
                'match_type' => (string) ($sourceBanner['match_type'] ?? 'all'),
                'created_at' => $now,
            ]);
        }
        if ($copyClicks) {
            $stmt = $pdo->prepare(
                'INSERT IGNORE INTO sr_banner_clicks
                    (banner_id, click_key_hash, clicked_at)
                 SELECT :new_banner_id, click_key_hash, clicked_at
                 FROM sr_banner_clicks
                 WHERE banner_id = :source_banner_id'
            );
            $stmt->execute([
                'new_banner_id' => $newBannerId,
                'source_banner_id' => $bannerId,
            ]);
        }
        $pdo->commit();
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'banner.copied',
            'target_type' => 'banner',
            'target_id' => (string) $newBannerId,
            'result' => 'success',
            'message' => 'Banner copied.',
            'metadata' => [
                'source_banner_id' => $bannerId,
                'copy_clicks' => $copyClicks,
            ],
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '배너 복사본을 만들었습니다.'), '/admin/banners/edit?id=' . (string) $newBannerId);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = '배너 복사 중 오류가 발생했습니다.';
    }
}

sr_admin_redirect_with_result(sr_admin_action_result($errors, ''), $returnTo);
