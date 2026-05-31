<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers/boards.php';
require_once SR_ROOT . '/modules/community/helpers/members.php';
require_once SR_ROOT . '/modules/community/helpers/posts.php';
require_once SR_ROOT . '/modules/community/helpers/attachments.php';
require_once SR_ROOT . '/modules/community/helpers/assets.php';
require_once SR_ROOT . '/modules/community/helpers/reports.php';
require_once SR_ROOT . '/modules/community/helpers/scraps.php';
require_once SR_ROOT . '/modules/community/helpers/themes.php';
require_once SR_ROOT . '/modules/community/helpers/levels.php';
require_once SR_ROOT . '/modules/community/helpers/member-groups.php';
require_once SR_ROOT . '/modules/community/helpers/notifications.php';
require_once SR_ROOT . '/modules/community/helpers/messages.php';

function sr_community_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
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
                'member_name' => 'key: ' . (string) ($row['board_key'] ?? ''),
                'member_email' => trim('그룹: ' . (string) ($row['group_title'] ?? '')),
                'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    if ($targetType === 'community_post') {
        $where = $keyword === '' ? '1 = 1' : "(p.id = :id OR p.title LIKE :keyword_like ESCAPE '\\\\' OR b.title LIKE :keyword_like ESCAPE '\\\\' OR b.board_key LIKE :keyword_like ESCAPE '\\\\')";
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
                'member_email' => 'key: ' . (string) ($row['board_key'] ?? ''),
                'created_at' => '상태: ' . (string) ($row['status'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    return [];
}

function sr_community_coupon_revoke_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    require_once SR_ROOT . '/modules/community/helpers/assets.php';
    return sr_community_revoke_coupon_access_entitlements($pdo, $accountId, $dedupeKey);
}
