<?php

declare(strict_types=1);

function sr_community_publisher_reward_statuses(): array
{
    return ['pending', 'granted', 'failed', 'held', 'reversed', 'cancelled'];
}

function sr_community_publisher_reward_status_label(string $status): string
{
    return match ($status) {
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
        'held' => '보류',
        'reversed' => '회수',
        'cancelled' => '취소',
        default => $status,
    };
}

function sr_community_publisher_reward_filters_from_request(): array
{
    $status = sr_get_string('status', 20);
    return [
        'status' => in_array($status, sr_community_publisher_reward_statuses(), true) ? $status : '',
        'q' => trim(sr_get_string('q', 120)),
    ];
}

function sr_community_publisher_reward_where_sql(array $filters, array &$params): string
{
    $where = [];
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '') {
        $where[] = 'r.status = :status';
        $params['status'] = $status;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $qAccountId = (int) ($filters['q_account_id'] ?? 0);
        if (preg_match('/\A[1-9][0-9]*\z/', $q) === 1) {
            $where[] = '(r.post_id = :q_id OR r.attachment_id = :q_id OR r.charge_transaction_id = :q_id OR r.reward_transaction_id = :q_id)';
            $params['q_id'] = (int) $q;
        } else {
            $keywordWhere = ['p.title LIKE :q_like', 'a.original_name LIKE :q_like'];
            $params['q_like'] = '%' . $q . '%';
            if ($qAccountId > 0) {
                $keywordWhere[] = 'r.publisher_account_id = :q_account_id';
                $keywordWhere[] = 'r.downloader_account_id = :q_account_id';
                $params['q_account_id'] = $qAccountId;
            }
            $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
        }
    }

    return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
}

function sr_community_publisher_reward_count(PDO $pdo, array $filters = []): int
{
    $params = [];
    $whereSql = sr_community_publisher_reward_where_sql($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_community_publisher_reward_logs r'
        . sr_community_publisher_reward_count_join_sql($filters)
        . $whereSql
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_community_publisher_reward_count_join_sql(array $filters): string
{
    $q = trim((string) ($filters['q'] ?? ''));
    if ($q === '' || preg_match('/\A[1-9][0-9]*\z/', $q) === 1) {
        return '';
    }

    return "\n         LEFT JOIN sr_community_posts p ON p.id = r.post_id"
        . "\n         LEFT JOIN sr_community_attachments a ON a.id = r.attachment_id";
}

function sr_community_publisher_reward_logs(PDO $pdo, int $limit = 50, int $offset = 0, array $filters = []): array
{
    $params = [];
    $whereSql = sr_community_publisher_reward_where_sql($filters, $params);
    $params['limit_value'] = max(1, min(200, $limit));
    $params['offset_value'] = max(0, $offset);
    $stmt = $pdo->prepare(
        'SELECT r.*, p.title AS post_title, a.original_name AS attachment_name,
                publisher.email AS publisher_email, publisher.display_name AS publisher_display_name,
                downloader.email AS downloader_email, downloader.display_name AS downloader_display_name
         FROM sr_community_publisher_reward_logs r
         LEFT JOIN sr_community_posts p ON p.id = r.post_id
         LEFT JOIN sr_community_attachments a ON a.id = r.attachment_id
         LEFT JOIN sr_member_accounts publisher ON publisher.id = r.publisher_account_id
         LEFT JOIN sr_member_accounts downloader ON downloader.id = r.downloader_account_id'
        . $whereSql .
        ' ORDER BY r.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, in_array($key, ['limit_value', 'offset_value', 'q_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}
