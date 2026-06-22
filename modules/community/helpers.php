<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers/boards.php';
require_once SR_ROOT . '/modules/community/helpers/body-files.php';
require_once SR_ROOT . '/modules/community/helpers/categories.php';
require_once SR_ROOT . '/modules/community/helpers/members.php';
require_once SR_ROOT . '/modules/community/helpers/posts.php';
require_once SR_ROOT . '/modules/community/helpers/attachments.php';
require_once SR_ROOT . '/modules/community/helpers/assets.php';
require_once SR_ROOT . '/modules/community/helpers/publisher-rewards.php';
require_once SR_ROOT . '/modules/community/helpers/reports.php';
require_once SR_ROOT . '/modules/community/helpers/scraps.php';
require_once SR_ROOT . '/modules/community/helpers/series.php';
require_once SR_ROOT . '/modules/community/helpers/presentation.php';
require_once SR_ROOT . '/modules/community/helpers/levels.php';
require_once SR_ROOT . '/modules/community/helpers/member-groups.php';
require_once SR_ROOT . '/modules/community/helpers/notifications.php';
require_once SR_ROOT . '/modules/community/helpers/messages.php';
require_once SR_ROOT . '/modules/community/helpers/board-copy.php';
require_once SR_ROOT . '/modules/community/helpers/board-copy-jobs.php';
require_once SR_ROOT . '/modules/community/helpers/seo.php';
require_once SR_ROOT . '/modules/community/helpers/board-managers.php';
require_once SR_ROOT . '/modules/community/helpers/privacy-consents.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

function sr_community_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20, array $options = []): array
{
    $keyword = trim(preg_replace('/\s+/', ' ', $keyword) ?? '');
    $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
    $limit = max(1, min(30, $limit));
    $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    $idValue = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;

    if ($targetType === 'community_board') {
        $where = $keyword === '' ? '1 = 1' : "(b.id = :id OR b.title LIKE :keyword_like ESCAPE '\\\\' OR b.board_key LIKE :keyword_like ESCAPE '\\\\')";
        $stmt = $pdo->prepare(
            'SELECT b.id, b.title, b.board_key, b.status, b.updated_at, g.title AS group_title
             FROM sr_community_boards b
             LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
             WHERE ' . $where . '
             ORDER BY b.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($keyword === '' ? [] : ['id' => $idValue, 'keyword_like' => $keywordLike]);

        return array_map(static function (array $row): array {
            return [
                'reference_type' => 'community_board',
                'reference_id' => (string) (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'reason' => '게시판 #' . (string) (int) ($row['id'] ?? 0),
                'member_name' => '(' . (string) ($row['board_key'] ?? '') . ')',
                'member_email' => trim('그룹: ' . (string) ($row['group_title'] ?? '')),
                'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    if ($targetType === 'community_post') {
        if (($options['cursor'] ?? null) !== null || ($options['board_id'] ?? null) !== null || ($options['status'] ?? null) !== null || array_key_exists('response', $options)) {
            return sr_community_post_target_lookup($pdo, $keyword, $limit, $options);
        }

        if ($keyword !== '' && preg_match('/\A[1-9][0-9]*\z/', $keyword) !== 1) {
            $textLength = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
            if ($textLength < 2) {
                return [];
            }
        }

        $where = $keyword === '' ? "p.status IN ('published', 'hidden', 'pending')" : "(p.id = :id OR (p.status IN ('published', 'hidden', 'pending') AND p.title LIKE :keyword_like ESCAPE '\\\\') OR (p.status IN ('published', 'hidden', 'pending') AND b.title LIKE :keyword_like ESCAPE '\\\\') OR (p.status IN ('published', 'hidden', 'pending') AND b.board_key LIKE :keyword_like ESCAPE '\\\\'))";
        $stmt = $pdo->prepare(
            'SELECT p.id, p.title, p.status, p.updated_at, b.title AS board_title, b.board_key
             FROM sr_community_posts p
             INNER JOIN sr_community_boards b ON b.id = p.board_id
             WHERE ' . $where . '
             ORDER BY p.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($keyword === '' ? [] : ['id' => $idValue, 'keyword_like' => $keywordLike]);

        return array_map(static function (array $row): array {
            return [
                'reference_type' => 'community_post',
                'reference_id' => (string) (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'reason' => '게시글 #' . (string) (int) ($row['id'] ?? 0),
                'member_name' => '게시판: ' . (string) ($row['board_title'] ?? ''),
                'member_email' => '(' . (string) ($row['board_key'] ?? '') . ')',
                'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    return [];
}

function sr_community_post_target_lookup(PDO $pdo, string $keyword, int $limit = 20, array $options = []): array
{
    $keyword = trim(preg_replace('/\s+/', ' ', $keyword) ?? '');
    $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
    $limit = max(1, min(30, $limit));
    $cursor = max(0, (int) ($options['cursor'] ?? 0));
    $boardId = max(0, (int) ($options['board_id'] ?? 0));
    $status = (string) ($options['status'] ?? '');
    $allowedStatuses = function_exists('sr_community_post_statuses') ? sr_community_post_statuses() : ['published', 'hidden', 'deleted', 'pending'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = '';
    }

    $conditions = [];
    $params = [];
    $notice = '';
    $idValue = preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0;

    if ($idValue > 0) {
        $conditions[] = 'p.id = :id';
        $params['id'] = $idValue;
        if ($boardId > 0) {
            $conditions[] = 'p.board_id = :board_id';
            $params['board_id'] = $boardId;
        }
        if ($status !== '') {
            $conditions[] = 'p.status = :status';
            $params['status'] = $status;
        } else {
            $conditions[] = "p.status IN ('published', 'hidden', 'pending')";
        }
    } else {
        if ($cursor > 0) {
            $conditions[] = 'p.id < :cursor';
            $params['cursor'] = $cursor;
        }
        if ($boardId > 0) {
            $conditions[] = 'p.board_id = :board_id';
            $params['board_id'] = $boardId;
        }
        if ($status !== '') {
            $conditions[] = 'p.status = :status';
            $params['status'] = $status;
        } else {
            $conditions[] = "p.status IN ('published', 'hidden', 'pending')";
        }
        if ($keyword !== '') {
            $textLength = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
            if ($textLength < 2) {
                return [
                    'items' => [],
                    'next_cursor' => null,
                    'has_more' => false,
                    'limit' => $limit,
                    'notice' => '텍스트 검색은 2자 이상 입력해 주세요.',
                ];
            }
            $conditions[] = "p.title LIKE :keyword_like ESCAPE '\\\\'";
            $params['keyword_like'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            if ($boardId <= 0) {
                $notice = '게시판을 선택하면 더 좁은 범위에서 검색할 수 있습니다.';
            }
        }
    }

    $fetchLimit = $limit + 1;
    $stmt = $pdo->prepare(
        'SELECT p.id, p.title, p.status, p.updated_at, b.title AS board_title, b.board_key
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY p.id DESC
         LIMIT ' . $fetchLimit
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $hasMore = count($rows) > $limit;
    if ($hasMore) {
        array_pop($rows);
    }

    $items = array_map(static function (array $row): array {
        return [
            'reference_type' => 'community_post',
            'reference_id' => (string) (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'reason' => '게시글 #' . (string) (int) ($row['id'] ?? 0),
            'member_name' => '게시판: ' . (string) ($row['board_title'] ?? ''),
            'member_email' => '(' . (string) ($row['board_key'] ?? '') . ')',
            'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
        ];
    }, $rows);

    $lastItem = $items[count($items) - 1] ?? null;

    return [
        'items' => $items,
        'next_cursor' => $hasMore && is_array($lastItem) ? (string) ($lastItem['reference_id'] ?? '') : null,
        'has_more' => $hasMore,
        'limit' => $limit,
        'notice' => $notice,
    ];
}

function sr_community_coupon_revoke_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    require_once SR_ROOT . '/modules/community/helpers/assets.php';
    return sr_community_revoke_coupon_access_entitlements($pdo, $accountId, $dedupeKey);
}

function sr_community_coupon_target_health(PDO $pdo, string $targetType, string $targetId): array
{
    if (!in_array($targetType, ['community_board', 'community_post'], true) || preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return ['status' => 'unknown', 'message' => '커뮤니티 대상 형식이 올바르지 않습니다.'];
    }

    if ($targetType === 'community_board') {
        $board = sr_community_board_by_id($pdo, (int) $targetId);
        if (!is_array($board)) {
            return ['status' => 'missing_target', 'message' => '게시판을 찾을 수 없습니다.'];
        }

        $status = (string) ($board['status'] ?? '');
        return $status === 'enabled'
            ? ['status' => 'ok', 'policy_status' => $status]
            : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '게시판이 사용 상태가 아닙니다.'];
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_community_posts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $targetId]);
    $post = $stmt->fetch();
    if (!is_array($post)) {
        return ['status' => 'missing_target', 'message' => '게시글을 찾을 수 없습니다.'];
    }

    $status = (string) ($post['status'] ?? '');
    return $status === 'published'
        ? ['status' => 'ok', 'policy_status' => $status]
        : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '게시글이 공개 상태가 아닙니다.'];
}

function sr_community_coupon_target_admin_url(string $targetType, string $targetId): string
{
    if (preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return '';
    }

    if ($targetType === 'community_board') {
        return '/admin/community/boards/edit?id=' . rawurlencode($targetId);
    }

    if ($targetType === 'community_post') {
        return '/admin/community/posts?field=id&q=' . rawurlencode($targetId);
    }

    return '';
}

function sr_community_banner_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_community_banner_reference_rows($pdo, $target, $context));
}

function sr_community_banner_reference_rows(PDO $pdo, array $target, array $context): array
{
    return sr_community_display_reference_rows($pdo, 'banner', (int) ($target['target_id'] ?? 0));
}

function sr_community_popup_layer_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_community_popup_layer_reference_rows($pdo, $target, $context));
}

function sr_community_popup_layer_reference_rows(PDO $pdo, array $target, array $context): array
{
    return sr_community_display_reference_rows($pdo, 'popup_layer', (int) ($target['target_id'] ?? 0));
}

function sr_community_display_reference_rows(PDO $pdo, string $kind, int $targetId): array
{
    if ($targetId <= 0) {
        return [];
    }

    $settingKeys = $kind === 'banner'
        ? ['banner_before_list_id', 'banner_after_list_id', 'banner_before_view_id', 'banner_after_view_id', 'banner_before_form_id', 'banner_after_form_id']
        : ['popup_layer_list_id', 'popup_layer_view_id', 'popup_layer_form_id'];
    $labels = [
        'banner_before_list_id' => '목록 상단 배너',
        'banner_after_list_id' => '목록 하단 배너',
        'banner_before_view_id' => '본문 상단 배너',
        'banner_after_view_id' => '본문 하단 배너',
        'banner_before_form_id' => '글쓰기 상단 배너',
        'banner_after_form_id' => '글쓰기 하단 배너',
        'popup_layer_list_id' => '목록 팝업레이어',
        'popup_layer_view_id' => '본문 팝업레이어',
        'popup_layer_form_id' => '글쓰기 팝업레이어',
    ];

    $rows = [];
    foreach ([
        ['table' => 'sr_community_board_settings', 'id_column' => 'board_id', 'join_table' => 'sr_community_boards', 'join_id' => 'id', 'type' => 'community_board'],
        ['table' => 'sr_community_board_group_settings', 'id_column' => 'group_id', 'join_table' => 'sr_community_board_groups', 'join_id' => 'id', 'type' => 'community_board_group'],
    ] as $source) {
        $placeholders = [];
        $params = ['target_id' => (string) $targetId];
        foreach ($settingKeys as $index => $settingKey) {
            $paramKey = 'setting_key_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $settingKey;
        }

        $stmt = $pdo->prepare(
            'SELECT s.' . $source['id_column'] . ' AS owner_id, s.setting_key, s.updated_at, o.title, o.status
             FROM ' . $source['table'] . ' s
             INNER JOIN ' . $source['join_table'] . ' o ON o.' . $source['join_id'] . ' = s.' . $source['id_column'] . '
             WHERE s.setting_key IN (' . implode(', ', $placeholders) . ')
               AND s.setting_value = :target_id
             ORDER BY s.' . $source['id_column'] . ' DESC'
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $row) {
            $settingKey = (string) ($row['setting_key'] ?? '');
            $rows[] = [
                'consumer_module_key' => 'community',
                'reference_type' => $kind === 'banner' ? 'community_banner' : 'community_popup_layer',
                'reference_id' => $source['type'] . ':' . (string) (int) ($row['owner_id'] ?? 0) . ':' . $settingKey,
                'title' => (string) ($row['title'] ?? '') . ' / ' . (string) ($labels[$settingKey] ?? $settingKey),
                'target_type' => $kind,
                'target_id' => (string) $targetId,
                'policy_status' => (string) ($row['status'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'metadata' => ['type' => $source['type'], 'id' => (int) ($row['owner_id'] ?? 0), 'setting_key' => $settingKey],
            ];
        }
    }

    return $rows;
}

function sr_community_member_group_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_community_member_group_reference_rows($pdo, $target, $context));
}

function sr_community_member_group_reference_rows(PDO $pdo, array $target, array $context): array
{
    $groupKey = (string) ($target['target_key'] ?? '');
    if ($groupKey === '') {
        return [];
    }

    $rows = [];
    $plainLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $groupKey) . '%';
    $jsonLike = '%"group_key":"' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $groupKey) . '"%';

    $settings = sr_community_settings($pdo);
    $messageGroups = $settings['message_write_group_keys'] ?? [];
    if (is_array($messageGroups) && in_array($groupKey, array_map('strval', $messageGroups), true)) {
        $rows[] = [
            'consumer_module_key' => 'community',
            'reference_type' => 'community_message_group_policy',
            'reference_id' => 'community_settings:message_write_group_keys',
            'title' => '커뮤니티 쪽지 작성 대상 그룹',
            'target_type' => 'member_group',
            'target_id' => (string) (int) ($target['target_id'] ?? 0),
            'target_key' => $groupKey,
            'policy_status' => (string) ($settings['message_write_policy'] ?? ''),
            'admin_url' => '/admin/community/settings',
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT b.id, b.title, b.status, s.setting_key, s.updated_at
         FROM sr_community_board_settings s
         INNER JOIN sr_community_boards b ON b.id = s.board_id
         WHERE s.setting_key IN (\'read_group_keys\', \'write_group_keys\', \'comment_group_keys\')
           AND s.setting_value LIKE :group_key ESCAPE \'\\\\\'
         ORDER BY b.id DESC'
    );
    $stmt->execute(['group_key' => $plainLike]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'community',
            'reference_type' => 'community_board_group_policy',
            'reference_id' => 'community_board:' . (string) (int) ($row['id'] ?? 0) . ':' . (string) ($row['setting_key'] ?? ''),
            'title' => (string) ($row['title'] ?? '') . ' / ' . (string) ($row['setting_key'] ?? ''),
            'target_type' => 'member_group',
            'target_id' => (string) (int) ($target['target_id'] ?? 0),
            'target_key' => $groupKey,
            'policy_status' => (string) ($row['status'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => ['type' => 'community_board', 'id' => (int) ($row['id'] ?? 0)],
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT b.id, b.title, b.status, s.setting_key, s.updated_at
         FROM sr_community_board_settings s
         INNER JOIN sr_community_boards b ON b.id = s.board_id
         WHERE s.setting_key IN (
            \'paid_read_group_policies_json\',
            \'write_charge_group_policies_json\',
            \'comment_charge_group_policies_json\',
            \'post_reward_group_policies_json\',
            \'comment_reward_group_policies_json\'
         )
           AND s.setting_value LIKE :group_key ESCAPE \'\\\\\'
         ORDER BY b.id DESC'
    );
    $stmt->execute(['group_key' => $jsonLike]);
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'community',
            'reference_type' => 'community_board_asset_group_policy',
            'reference_id' => 'community_board_asset:' . (string) (int) ($row['id'] ?? 0) . ':' . (string) ($row['setting_key'] ?? ''),
            'title' => (string) ($row['title'] ?? '') . ' / ' . (string) ($row['setting_key'] ?? ''),
            'target_type' => 'member_group',
            'target_id' => (string) (int) ($target['target_id'] ?? 0),
            'target_key' => $groupKey,
            'policy_status' => (string) ($row['status'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => ['type' => 'community_board', 'id' => (int) ($row['id'] ?? 0)],
        ];
    }

    return $rows;
}

function sr_community_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $status = (string) ($row['policy_status'] ?? '');
    if (in_array($status, ['enabled', 'published'], true)) {
        return ['status' => 'ok', 'policy_status' => $status];
    }
    if ($status !== '') {
        return ['status' => 'disabled_target', 'policy_status' => $status];
    }

    return ['status' => 'unknown'];
}

function sr_community_reference_admin_url(array $row, array $context): string
{
    if (isset($row['admin_url'])) {
        return (string) $row['admin_url'];
    }

    $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
    $type = (string) ($metadata['type'] ?? '');
    $id = (int) ($metadata['id'] ?? 0);
    if ($type === 'community_board' && $id > 0) {
        return '/admin/community/boards/edit?id=' . rawurlencode((string) $id);
    }
    if ($type === 'community_board_group' && $id > 0) {
        return '/admin/community/board-groups/edit?id=' . rawurlencode((string) $id);
    }

    return '/admin/community/settings';
}
