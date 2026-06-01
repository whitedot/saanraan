<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/series', 'view');
$errors = [];
$notice = '';
$seriesSupported = sr_community_series_supported($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/community/series', 'edit');
    $seriesId = (int) sr_post_string('series_id', 20);
    $series = sr_community_series_by_id($pdo, $seriesId);
    $status = sr_post_string('status', 30);
    $visibility = sr_post_string('visibility', 30);
    $adminNote = sr_post_string_without_truncation('admin_note', 2000);
    if (!$seriesSupported) {
        $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
    } elseif (!is_array($series)) {
        $errors[] = '시리즈를 찾을 수 없습니다.';
    } elseif (!in_array($status, sr_community_series_statuses(), true) || !in_array($visibility, sr_community_series_visibility_values(), true)) {
        $errors[] = '상태 또는 공개 범위가 올바르지 않습니다.';
    } elseif (!is_string($adminNote)) {
        $errors[] = '운영 메모가 너무 깁니다.';
    } else {
        sr_community_update_series($pdo, $seriesId, [
            'title' => (string) $series['title'],
            'description' => (string) ($series['description'] ?? ''),
            'status' => $status,
            'visibility' => $visibility,
            'admin_note' => $adminNote,
        ], (int) $account['id']);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'community.series.moderated',
            'target_type' => 'community_series',
            'target_id' => (string) $seriesId,
            'result' => 'success',
            'message' => 'Community series moderated.',
            'metadata' => ['before_status' => (string) $series['status'], 'after_status' => $status],
        ]);
        $notice = '시리즈 상태를 저장했습니다.';
    }
}

$seriesList = [];
if ($seriesSupported) {
    $seriesList = $pdo->query(
        'SELECT s.*, b.title AS board_title, a.display_name AS owner_display_name
         FROM sr_community_series s
         INNER JOIN sr_community_boards b ON b.id = s.board_id
         LEFT JOIN sr_member_accounts a ON a.id = s.owner_account_id
         ORDER BY s.id DESC
         LIMIT 200'
    )->fetchAll();
} elseif (sr_request_method() !== 'POST') {
    $errors[] = '커뮤니티 시리즈 스키마 업데이트가 아직 적용되지 않았습니다.';
}

include SR_ROOT . '/modules/community/views/admin-series.php';
