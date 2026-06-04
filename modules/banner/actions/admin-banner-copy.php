<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/banner/helpers.php';

$account = sr_member_require_login($pdo);
$bannerId = sr_request_method() === 'POST' ? (int) sr_post_string('banner_id', 20) : (int) sr_get_string('id', 20);
if (sr_request_method() === 'POST') {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'edit');
    sr_require_csrf();
} else {
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/banners', 'view');
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
    sr_render_error(404, '복사할 배너를 찾을 수 없습니다.');
}

$values = [
    'title' => sr_request_method() === 'POST' ? sr_post_string('title', 160) : sr_banner_clean_single_line((string) $sourceBanner['title'] . ' 복사본', 160),
];
$errors = [];

if (sr_request_method() === 'POST') {
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
                    (title, body_text, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, created_at, updated_at)
                 VALUES
                    (:title, :body_text, :link_url, :image_url, :status, :skin_key, :starts_at, :ends_at, :sort_order, :created_at, :updated_at)'
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
            $pdo->commit();
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'banner.copied',
                'target_type' => 'banner',
                'target_id' => (string) $newBannerId,
                'result' => 'success',
                'message' => 'Banner copied.',
                'metadata' => ['source_banner_id' => $bannerId],
            ]);
            sr_admin_flash_result(sr_admin_action_result([], '배너 복사본을 만들었습니다.'));
            sr_redirect('/admin/banners/edit?id=' . (string) $newBannerId);
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = '배너 복사 중 오류가 발생했습니다.';
        }
    }
}

include SR_ROOT . '/modules/banner/views/admin-banner-copy.php';
