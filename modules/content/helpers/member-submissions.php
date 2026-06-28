<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_content_submission_statuses(): array
{
    return ['member_draft', 'pending_review', 'revision_requested', 'rejected', 'approved', 'cancelled'];
}

function sr_content_submission_status_label(string $status): string
{
    return match ($status) {
        'member_draft' => '임시저장',
        'pending_review' => '검수 대기',
        'revision_requested' => '수정 요청',
        'rejected' => '반려',
        'approved' => '승인',
        'cancelled' => '취소',
        default => $status,
    };
}

function sr_content_author_application_statuses(): array
{
    return ['pending', 'approved', 'rejected', 'cancelled'];
}

function sr_content_author_application_status_label(string $status): string
{
    return match ($status) {
        'pending' => '대기',
        'approved' => '승인',
        'rejected' => '반려',
        'cancelled' => '취소',
        default => $status,
    };
}

function sr_content_author_permission_status_label(string $status): string
{
    return match ($status) {
        'allowed' => '허용',
        'blocked' => '차단',
        default => $status,
    };
}

function sr_content_author_review_override_label(string $value): string
{
    return match ($value) {
        'inherit' => '기본 설정 따름',
        'required' => '항상 검수',
        'exempt' => '검수 면제',
        default => $value,
    };
}

function sr_content_author_permission(PDO $pdo, int $accountId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_author_permissions
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_author_application_by_account(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1 || !sr_content_optional_table_exists($pdo, 'sr_content_author_applications')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_author_applications
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_author_applications(PDO $pdo, $statuses = 'pending', int $applicationId = 0): array
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_applications')) {
        return [];
    }

    $params = [];
    $whereParts = [];
    if ($applicationId > 0) {
        $whereParts[] = 'a.id = :application_id';
        $params['application_id'] = $applicationId;
    }
    $statusValues = is_array($statuses) ? $statuses : [$statuses];
    $selectedStatuses = [];
    foreach ($statusValues as $status) {
        $status = (string) $status;
        if (in_array($status, sr_content_author_application_statuses(), true)) {
            $selectedStatuses[] = $status;
        }
    }
    $selectedStatuses = array_values(array_unique($selectedStatuses));
    if ($selectedStatuses !== []) {
        $statusPlaceholders = [];
        foreach ($selectedStatuses as $index => $status) {
            $placeholder = 'status_' . (string) $index;
            $statusPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $status;
        }
        $whereParts[] = 'a.status IN (' . implode(', ', $statusPlaceholders) . ')';
    }
    $where = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = $pdo->prepare(
        'SELECT a.*, m.email, m.display_name, m.status AS account_status
         FROM sr_content_author_applications a
         LEFT JOIN sr_member_accounts m ON m.id = a.account_id
         ' . $where . '
         ORDER BY a.id DESC
         LIMIT 200'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_author_permissions(PDO $pdo, array $statuses = [], array $reviewOverrides = []): array
{
    $params = [];
    $whereParts = [];

    $selectedStatuses = [];
    foreach ($statuses as $status) {
        $status = (string) $status;
        if (in_array($status, ['allowed', 'blocked'], true)) {
            $selectedStatuses[] = $status;
        }
    }
    $selectedStatuses = array_values(array_unique($selectedStatuses));
    if ($selectedStatuses !== []) {
        $statusPlaceholders = [];
        foreach ($selectedStatuses as $index => $status) {
            $placeholder = 'status_' . (string) $index;
            $statusPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $status;
        }
        $whereParts[] = 'p.status IN (' . implode(', ', $statusPlaceholders) . ')';
    }

    $selectedReviewOverrides = [];
    foreach ($reviewOverrides as $reviewOverride) {
        $reviewOverride = (string) $reviewOverride;
        if (in_array($reviewOverride, ['inherit', 'required', 'exempt'], true)) {
            $selectedReviewOverrides[] = $reviewOverride;
        }
    }
    $selectedReviewOverrides = array_values(array_unique($selectedReviewOverrides));
    if ($selectedReviewOverrides !== []) {
        $reviewPlaceholders = [];
        foreach ($selectedReviewOverrides as $index => $reviewOverride) {
            $placeholder = 'review_required_override_' . (string) $index;
            $reviewPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $reviewOverride;
        }
        $whereParts[] = 'p.review_required_override IN (' . implode(', ', $reviewPlaceholders) . ')';
    }

    $where = $whereParts !== [] ? 'WHERE ' . implode(' AND ', $whereParts) : '';
    $stmt = $pdo->prepare(
        'SELECT p.*, a.email, a.display_name, a.status AS account_status
         FROM sr_content_author_permissions p
         LEFT JOIN sr_member_accounts a ON a.id = p.account_id
         ' . $where . '
         ORDER BY p.id DESC
         LIMIT 200'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_admin_permission_tables_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_admin_account_roles LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_admin_account_permissions LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_content_author_application_admin_account_ids(PDO $pdo): array
{
    if (!sr_content_admin_permission_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->query(
        "SELECT DISTINCT a.id
         FROM sr_member_accounts a
         LEFT JOIN sr_admin_account_roles r ON r.account_id = a.id AND r.role_key = 'owner'
         LEFT JOIN sr_admin_account_permissions p ON p.account_id = a.id
         WHERE (r.id IS NOT NULL OR (p.menu_path = '/admin/content/author-applications' AND p.action_key = 'view'))
           AND a.status = 'active'
         ORDER BY a.id ASC"
    );

    $accountIds = [];
    foreach ($stmt->fetchAll() as $row) {
        $accountId = (int) ($row['id'] ?? 0);
        if ($accountId > 0) {
            $accountIds[] = $accountId;
        }
    }

    return $accountIds;
}

function sr_content_admin_notification_create_function(PDO $pdo): string
{
    return sr_module_contract_function($pdo, 'notification', 'admin-notification-events.php', 'create_function');
}

function sr_content_create_admin_author_application_notifications(PDO $pdo, int $applicationId, int $applicantAccountId): int
{
    $createAdminNotificationFunction = sr_content_admin_notification_create_function($pdo);
    if ($applicationId > 0 && $applicantAccountId > 0 && $createAdminNotificationFunction !== '') {
        try {
            $adminNotificationId = $createAdminNotificationFunction($pdo, [
                'title' => '새 콘텐츠 등록자 신청이 접수되었습니다.',
                'body_text' => '콘텐츠 등록자 신청을 검토해 주세요.',
                'severity' => 'warning',
                'source_module_key' => 'content',
                'event_key' => 'author_application.created',
                'target_type' => 'content_author_application',
                'target_id' => (string) $applicationId,
                'action_url' => '/admin/content/author-applications',
                'permission_path' => '/admin/content/author-applications',
                'permission_action' => 'view',
                'dedupe_key' => 'content.author_application.' . (string) $applicationId,
                'created_by_account_id' => $applicantAccountId,
            ]);
            if ($adminNotificationId !== null) {
                return 1;
            }
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'content_author_application_admin_notification_create');
        }
    }

    $createNotificationFunction = sr_content_notification_create_function($pdo);
    if ($applicationId < 1 || $applicantAccountId < 1 || $createNotificationFunction === '') {
        return 0;
    }

    $sentCount = 0;
    $applicantPublicHash = function_exists('sr_admin_member_public_hash')
        ? sr_admin_member_public_hash(sr_runtime_config(), $applicantAccountId)
        : '';
    $applicantLabel = $applicantPublicHash !== '' ? '회원 ' . $applicantPublicHash : '새 회원';
    foreach (sr_content_author_application_admin_account_ids($pdo) as $adminAccountId) {
        try {
            $createNotificationFunction($pdo, [
                'audience' => 'account',
                'account_id' => $adminAccountId,
                'title' => '새 콘텐츠 등록자 신청이 접수되었습니다.',
                'body_text' => $applicantLabel . '의 콘텐츠 등록자 신청을 검토해 주세요.',
                'link_url' => '/admin/content/author-applications',
                'channels' => ['site'],
                'created_by_account_id' => $applicantAccountId,
            ]);
            $sentCount++;
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'content_author_application_admin_notification_create');
        }
    }

    return $sentCount;
}

function sr_content_save_author_application(PDO $pdo, int $accountId, string $applicationNote): int
{
    if ($accountId < 1) {
        throw new InvalidArgumentException('회원 정보가 올바르지 않습니다.');
    }
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_applications')) {
        throw new RuntimeException('콘텐츠 작성자 신청 테이블이 준비되어 있지 않습니다.');
    }

    $note = sr_content_clean_text($applicationNote, 2000);
    if ($note === '') {
        throw new InvalidArgumentException('신청 사유를 입력하세요.');
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_content_author_applications
            (account_id, application_note, status, review_note, reviewed_by, reviewed_at, created_at, updated_at)
         VALUES
            (:account_id, :application_note, \'pending\', NULL, NULL, NULL, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            application_note = VALUES(application_note),
            status = \'pending\',
            review_note = NULL,
            reviewed_by = NULL,
            reviewed_at = NULL,
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'application_note' => $note,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $application = sr_content_author_application_by_account($pdo, $accountId);
    return is_array($application) ? (int) $application['id'] : 0;
}

function sr_content_review_author_application(PDO $pdo, int $applicationId, string $status, int $reviewerAccountId, string $reviewNote = ''): void
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_applications')) {
        throw new RuntimeException('콘텐츠 작성자 신청 테이블이 준비되어 있지 않습니다.');
    }
    if (!in_array($status, ['approved', 'rejected'], true)) {
        throw new InvalidArgumentException('처리 상태가 올바르지 않습니다.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_content_author_applications WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $applicationId]);
        $application = $stmt->fetch();
        if (!is_array($application)) {
            throw new InvalidArgumentException('작성자 신청을 찾을 수 없습니다.');
        }
        if ((string) ($application['status'] ?? '') !== 'pending') {
            throw new InvalidArgumentException('대기 중인 신청만 처리할 수 있습니다.');
        }

        $now = sr_now();
        $note = sr_content_clean_text($reviewNote, 2000);
        $stmt = $pdo->prepare(
            'UPDATE sr_content_author_applications
             SET status = :status,
                 review_note = :review_note,
                 reviewed_by = :reviewed_by,
                 reviewed_at = :reviewed_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = \'pending\''
        );
        $stmt->execute([
            'status' => $status,
            'review_note' => $note,
            'reviewed_by' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $applicationId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('작성자 신청 상태를 저장하지 못했습니다.');
        }

        if ($status === 'approved') {
            $applicationAccountId = (int) ($application['account_id'] ?? 0);
            if ($applicationAccountId < 1) {
                throw new InvalidArgumentException('회원 정보가 없는 신청은 승인할 수 없습니다.');
            }

            $permissionNote = $note !== '' ? $note : (string) ($application['application_note'] ?? '');
            $stmt = $pdo->prepare(
                'INSERT INTO sr_content_author_permissions
                    (account_id, status, review_required_override, note, created_by, updated_by, created_at, updated_at)
                 VALUES
                    (:account_id, \'allowed\', \'inherit\', :note, :created_by, :updated_by, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    status = \'allowed\',
                    review_required_override = VALUES(review_required_override),
                    note = VALUES(note),
                    updated_by = VALUES(updated_by),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'account_id' => $applicationAccountId,
                'note' => $permissionNote,
                'created_by' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
                'updated_by' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_content_group_submission_group_keys(string $value): array
{
    $decoded = json_decode($value, true);
    $rawValues = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value);
    $keys = [];
    foreach (is_array($rawValues) ? $rawValues : [] as $rawValue) {
        $key = strtolower(trim((string) $rawValue));
        if (preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $key) === 1) {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

function sr_content_member_submission_allowed_groups(PDO $pdo, int $accountId): array
{
    $settings = sr_content_settings($pdo);
    if (empty($settings['member_submission_enabled'])) {
        return [];
    }

    $permission = sr_content_author_permission($pdo, $accountId);
    $individualAllowed = is_array($permission) && (string) ($permission['status'] ?? '') === 'allowed';
    $accountGroupKeys = [];
    if (is_file(SR_ROOT . '/modules/member/helpers/groups.php')) {
        require_once SR_ROOT . '/modules/member/helpers/groups.php';
        if (function_exists('sr_member_account_group_keys')) {
            $accountGroupKeys = sr_member_account_group_keys($pdo, $accountId);
        }
    }

    $groups = [];
    foreach (sr_content_groups($pdo) as $group) {
        if ((string) ($group['status'] ?? '') !== 'enabled') {
            continue;
        }
        $groupSettings = sr_content_group_settings($pdo, (int) $group['id']);
        if (!sr_content_bool_setting($groupSettings['member_submission_enabled'] ?? false)) {
            continue;
        }
        $allowedGroupKeys = sr_content_group_submission_group_keys((string) ($groupSettings['member_submission_allowed_group_keys'] ?? '[]'));
        if ($individualAllowed || ($allowedGroupKeys !== [] && array_intersect($accountGroupKeys, $allowedGroupKeys) !== [])) {
            $group['member_submission_review_required'] = (string) ($groupSettings['member_submission_review_required'] ?? 'inherit');
            $groups[] = $group;
        }
    }

    return $groups;
}

function sr_content_member_can_submit_to_group(PDO $pdo, int $accountId, int $groupId): bool
{
    foreach (sr_content_member_submission_allowed_groups($pdo, $accountId) as $group) {
        if ((int) ($group['id'] ?? 0) === $groupId) {
            return true;
        }
    }

    return false;
}

function sr_content_submission_review_required(PDO $pdo, int $accountId, int $groupId): bool
{
    $settings = sr_content_settings($pdo);
    $reviewRequired = !empty($settings['member_submission_default_review_required']);
    $groupSettings = sr_content_group_settings($pdo, $groupId);
    $groupReview = (string) ($groupSettings['member_submission_review_required'] ?? 'inherit');
    if ($groupReview === 'always') {
        $reviewRequired = true;
    } elseif ($groupReview === 'none') {
        $reviewRequired = false;
    }

    $permission = sr_content_author_permission($pdo, $accountId);
    $override = is_array($permission) ? (string) ($permission['review_required_override'] ?? 'inherit') : 'inherit';
    if ($override === 'required') {
        $reviewRequired = true;
    } elseif ($override === 'exempt') {
        $reviewRequired = false;
    }

    return $reviewRequired;
}

function sr_content_submission_by_id(PDO $pdo, int $submissionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, g.title AS group_title, a.email AS author_email, a.display_name AS author_display_name
         FROM sr_content_submissions s
         LEFT JOIN sr_content_groups g ON g.id = s.content_group_id
         LEFT JOIN sr_member_accounts a ON a.id = s.author_account_id
         WHERE s.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $submissionId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_member_submissions(PDO $pdo, int $accountId): array
{
    $stmt = $pdo->prepare(
        'SELECT s.*, g.title AS group_title
         FROM sr_content_submissions s
         LEFT JOIN sr_content_groups g ON g.id = s.content_group_id
         WHERE s.author_account_id = :account_id
         ORDER BY s.id DESC
         LIMIT 200'
    );
    $stmt->execute(['account_id' => $accountId]);

    return $stmt->fetchAll();
}

function sr_content_admin_submissions(PDO $pdo, $statuses = ''): array
{
    $params = [];
    $where = '';
    $statusValues = is_array($statuses) ? $statuses : [$statuses];
    $selectedStatuses = [];
    foreach ($statusValues as $status) {
        $status = (string) $status;
        if (in_array($status, sr_content_submission_statuses(), true)) {
            $selectedStatuses[] = $status;
        }
    }
    $selectedStatuses = array_values(array_unique($selectedStatuses));
    if ($selectedStatuses !== []) {
        $statusPlaceholders = [];
        foreach ($selectedStatuses as $index => $status) {
            $placeholder = 'status_' . (string) $index;
            $statusPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $status;
        }
        $where = 'WHERE s.review_status IN (' . implode(', ', $statusPlaceholders) . ')';
    }
    $stmt = $pdo->prepare(
        'SELECT s.*, g.title AS group_title, a.email AS author_email, a.display_name AS author_display_name
         FROM sr_content_submissions s
         LEFT JOIN sr_content_groups g ON g.id = s.content_group_id
         LEFT JOIN sr_member_accounts a ON a.id = s.author_account_id
         ' . $where . '
         ORDER BY s.id DESC
         LIMIT 200'
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_content_clean_submission_values(array $input): array
{
    return [
        'content_group_id' => max(0, (int) ($input['content_group_id'] ?? 0)),
        'title' => sr_content_clean_single_line((string) ($input['title'] ?? ''), 160),
        'summary' => sr_content_clean_text((string) ($input['summary'] ?? ''), 2000),
        'body_text' => sr_content_clean_text((string) ($input['body_text'] ?? ''), 200000),
        'body_format' => 'plain',
    ];
}

function sr_content_save_member_submission(PDO $pdo, int $accountId, array $values, int $submissionId = 0, bool $submit = false): int
{
    $values = sr_content_clean_submission_values($values);
    if ($values['content_group_id'] < 1 || !sr_content_member_can_submit_to_group($pdo, $accountId, (int) $values['content_group_id'])) {
        throw new InvalidArgumentException('이 콘텐츠 그룹에는 제출할 수 없습니다.');
    }
    if ($values['title'] === '') {
        throw new InvalidArgumentException('제목을 입력하세요.');
    }
    if ($values['body_text'] === '') {
        throw new InvalidArgumentException('본문을 입력하세요.');
    }

    $existing = $submissionId > 0 ? sr_content_submission_by_id($pdo, $submissionId) : null;
    if ($submissionId > 0 && (!is_array($existing) || (int) ($existing['author_account_id'] ?? 0) !== $accountId || !in_array((string) ($existing['review_status'] ?? ''), ['member_draft', 'revision_requested', 'rejected'], true))) {
        throw new InvalidArgumentException('수정할 수 없는 제출본입니다.');
    }

    $reviewRequired = sr_content_submission_review_required($pdo, $accountId, (int) $values['content_group_id']);
    $reviewStatus = $submit ? 'pending_review' : 'member_draft';
    $now = sr_now();
    if (is_array($existing)) {
        $stmt = $pdo->prepare(
            'UPDATE sr_content_submissions
             SET content_group_id = :content_group_id,
                 title = :title,
                 summary = :summary,
                 body_text = :body_text,
                 body_format = :body_format,
                 review_status = :review_status,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            'content_group_id' => (int) $values['content_group_id'],
            'title' => (string) $values['title'],
            'summary' => (string) $values['summary'],
            'body_text' => (string) $values['body_text'],
            'body_format' => (string) $values['body_format'],
            'review_status' => $reviewStatus,
            'updated_at' => $now,
            'id' => $submissionId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_submissions
                (content_id, content_group_id, author_account_id, slug, title, summary, body_text, body_format, review_status, publish_target_status, review_note, created_at, updated_at)
             VALUES
                (NULL, :content_group_id, :author_account_id, \'\', :title, :summary, :body_text, :body_format, :review_status, \'published\', NULL, :created_at, :updated_at)'
        );
        $stmt->execute([
            'content_group_id' => (int) $values['content_group_id'],
            'author_account_id' => $accountId,
            'title' => (string) $values['title'],
            'summary' => (string) $values['summary'],
            'body_text' => (string) $values['body_text'],
            'body_format' => (string) $values['body_format'],
            'review_status' => $reviewStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $submissionId = (int) $pdo->lastInsertId();
    }

    if ($submit && !$reviewRequired) {
        sr_content_approve_submission($pdo, $submissionId, 0, '검수 면제 자동 승인');
    }

    return $submissionId;
}

function sr_content_unique_member_submission_slug(PDO $pdo, string $title, int $submissionId): string
{
    $base = sr_content_clean_slug($title);
    if ($base === '' || strlen($base) < 3) {
        $base = 'member-content';
    }
    $base = substr($base, 0, 100);
    $slug = $base . '-' . (string) $submissionId;
    $index = 2;
    while (sr_content_slug_exists($pdo, $slug, 0)) {
        $slug = $base . '-' . (string) $submissionId . '-' . (string) $index;
        $index++;
    }

    return $slug;
}

function sr_content_approve_submission(PDO $pdo, int $submissionId, int $reviewerAccountId, string $note = ''): int
{
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM sr_content_submissions WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $submissionId]);
        $submission = $stmt->fetch();
        if (!is_array($submission)) {
            throw new InvalidArgumentException('제출본을 찾을 수 없습니다.');
        }
        if (!in_array((string) ($submission['review_status'] ?? ''), ['pending_review', 'revision_requested', 'rejected'], true)) {
            throw new InvalidArgumentException('승인할 수 없는 제출 상태입니다.');
        }

        $slug = (string) ($submission['slug'] ?? '');
        if ($slug === '' || sr_content_slug_exists($pdo, $slug, 0)) {
            $slug = sr_content_unique_member_submission_slug($pdo, (string) $submission['title'], $submissionId);
        }

        $contentId = (int) ($submission['content_id'] ?? 0);
        $values = array_merge(sr_content_default_values($pdo, null, sr_content_group_settings($pdo, (int) ($submission['content_group_id'] ?? 0))), [
            'content_group_id' => (int) ($submission['content_group_id'] ?? 0),
            'content_group_scope' => 'here_only',
            'slug' => $slug,
            'title' => (string) $submission['title'],
            'summary' => (string) ($submission['summary'] ?? ''),
            'body_text' => (string) ($submission['body_text'] ?? ''),
            'body_format' => (string) ($submission['body_format'] ?? 'plain'),
            'status' => 'published',
            'seo_title' => '',
            'seo_description' => '',
        ]);
        $authorAccountId = (int) ($submission['author_account_id'] ?? 0);
        $savedContentId = sr_content_save($pdo, $values, $authorAccountId, $contentId);
        $now = sr_now();
        $stmt = $pdo->prepare(
            "UPDATE sr_content_submissions
             SET content_id = :content_id,
                 slug = :slug,
                 review_status = 'approved',
                 review_note = :review_note,
                 reviewed_by = :reviewed_by,
                 reviewed_at = :reviewed_at,
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'content_id' => $savedContentId,
            'slug' => $slug,
            'review_note' => $note,
            'reviewed_by' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
            'reviewed_at' => $now,
            'updated_at' => $now,
            'id' => $submissionId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('제출본 승인 상태를 저장하지 못했습니다.');
        }

        sr_content_grant_submission_author_reward($pdo, $submissionId, $savedContentId, $authorAccountId, $reviewerAccountId);
        sr_content_create_follow_notifications($pdo, sr_content_by_id($pdo, $savedContentId) ?: [], $reviewerAccountId > 0 ? $reviewerAccountId : null);

        if ($startedTransaction) {
            $pdo->commit();
        }

        return $savedContentId;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_content_author_reward_statuses(): array
{
    return ['pending', 'granted', 'failed'];
}

function sr_content_author_reward_status_label(string $status): string
{
    return match ($status) {
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
        default => $status,
    };
}

function sr_content_author_reward_filters_from_request(): array
{
    $status = sr_get_string('status', 20);
    return [
        'status' => in_array($status, sr_content_author_reward_statuses(), true) ? $status : '',
        'q' => trim(sr_get_string('q', 120)),
    ];
}

function sr_content_author_reward_where_sql(array $filters, array &$params): string
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
            $where[] = '(r.submission_id = :q_id OR r.content_id = :q_id OR r.transaction_id = :q_id)';
            $params['q_id'] = (int) $q;
        } else {
            $keywordWhere = ['c.title LIKE :q_like', 's.title LIKE :q_like', 'author.email LIKE :q_like', 'author.display_name LIKE :q_like'];
            $params['q_like'] = '%' . $q . '%';
            if ($qAccountId > 0) {
                $keywordWhere[] = 'r.author_account_id = :q_account_id';
                $keywordWhere[] = 'r.created_by_account_id = :q_account_id';
                $params['q_account_id'] = $qAccountId;
            }
            $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
        }
    }

    return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
}

function sr_content_author_reward_count(PDO $pdo, array $filters = []): int
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_reward_logs')) {
        return 0;
    }

    $params = [];
    $whereSql = sr_content_author_reward_where_sql($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS count_value
         FROM sr_content_author_reward_logs r
         LEFT JOIN sr_content_items c ON c.id = r.content_id
         LEFT JOIN sr_content_submissions s ON s.id = r.submission_id
         LEFT JOIN sr_member_accounts author ON author.id = r.author_account_id'
        . $whereSql
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_content_author_reward_logs(PDO $pdo, int $limit = 50, int $offset = 0, array $filters = []): array
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_reward_logs')) {
        return [];
    }

    $params = [];
    $whereSql = sr_content_author_reward_where_sql($filters, $params);
    $params['limit_value'] = max(1, min(200, $limit));
    $params['offset_value'] = max(0, $offset);
    $stmt = $pdo->prepare(
        'SELECT r.*, c.title AS content_title, c.slug AS content_slug, s.title AS submission_title,
                author.email AS author_email, author.display_name AS author_display_name,
                reviewer.email AS reviewer_email, reviewer.display_name AS reviewer_display_name
         FROM sr_content_author_reward_logs r
         LEFT JOIN sr_content_items c ON c.id = r.content_id
         LEFT JOIN sr_content_submissions s ON s.id = r.submission_id
         LEFT JOIN sr_member_accounts author ON author.id = r.author_account_id
         LEFT JOIN sr_member_accounts reviewer ON reviewer.id = r.created_by_account_id'
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

function sr_content_author_reward_log_by_dedupe_key(PDO $pdo, string $dedupeKey): ?array
{
    if ($dedupeKey === '' || !sr_content_optional_table_exists($pdo, 'sr_content_author_reward_logs')) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_content_author_reward_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_grant_submission_author_reward(PDO $pdo, int $submissionId, int $contentId, int $authorAccountId, int $reviewerAccountId = 0): void
{
    $settings = sr_content_settings($pdo);
    $assetModule = (string) ($settings['member_submission_author_reward_asset_module'] ?? '');
    $amount = (int) ($settings['member_submission_author_reward_amount'] ?? 0);
    if (empty($settings['member_submission_author_reward_enabled']) || $assetModule === '' || $amount <= 0 || $submissionId < 1 || $contentId < 1 || $authorAccountId < 1) {
        return;
    }
    if (!sr_content_asset_module_is_available($pdo, $assetModule)) {
        return;
    }
    if (!sr_content_optional_table_exists($pdo, 'sr_content_author_reward_logs')) {
        return;
    }

    $dedupeKey = 'content.submission.author_reward:' . (string) $submissionId;
    if (sr_content_author_reward_log_by_dedupe_key($pdo, $dedupeKey) !== null) {
        return;
    }

    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_author_reward_logs
                (submission_id, content_id, author_account_id, asset_module, amount, transaction_id, status, failure_reason, dedupe_key, created_by_account_id, created_at, updated_at)
             VALUES
                (:submission_id, :content_id, :author_account_id, :asset_module, :amount, NULL, \'pending\', NULL, :dedupe_key, :created_by_account_id, :created_at, :updated_at)'
        );
        $stmt->execute([
            'submission_id' => $submissionId,
            'content_id' => $contentId,
            'author_account_id' => $authorAccountId,
            'asset_module' => $assetModule,
            'amount' => $amount,
            'dedupe_key' => $dedupeKey,
            'created_by_account_id' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'content_author_reward_log_insert_failed');
        return;
    }

    $transactionFunction = (string) (sr_content_asset_modules($pdo)[$assetModule]['transaction_function'] ?? '');
    try {
        if (!function_exists($transactionFunction)) {
            throw new RuntimeException('Author reward transaction function is unavailable.');
        }
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $authorAccountId,
            'amount' => $amount,
            'transaction_type' => 'grant',
            'reason' => 'content.submission.author_reward',
            'reference_type' => 'content.submission.author_reward',
            'reference_id' => 'submission:' . (string) $submissionId,
            'created_by_account_id' => $reviewerAccountId > 0 ? $reviewerAccountId : null,
        ]);
        $stmt = $pdo->prepare(
            "UPDATE sr_content_author_reward_logs
             SET transaction_id = :transaction_id,
                 status = 'granted',
                 failure_reason = NULL,
                 updated_at = :updated_at
             WHERE dedupe_key = :dedupe_key"
        );
        $stmt->execute([
            'transaction_id' => $transactionId,
            'updated_at' => sr_now(),
            'dedupe_key' => $dedupeKey,
        ]);
    } catch (Throwable $exception) {
        $stmt = $pdo->prepare(
            "UPDATE sr_content_author_reward_logs
             SET status = 'failed',
                 failure_reason = :failure_reason,
                 updated_at = :updated_at
             WHERE dedupe_key = :dedupe_key"
        );
        $stmt->execute([
            'failure_reason' => mb_substr($exception->getMessage(), 0, 2000),
            'updated_at' => sr_now(),
            'dedupe_key' => $dedupeKey,
        ]);
        sr_log_exception($exception, 'content_author_reward_grant_failed');
    }
}
