<?php

declare(strict_types=1);

function sr_survey_comments_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_survey_comments LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_survey_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_survey_comment_status_label(string $status): string
{
    return [
        'published' => '게시',
        'hidden' => '숨김',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_survey_comment_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, '회원'));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_survey_comments(PDO $pdo, int $surveyId, int $limit = 100): array
{
    if ($surveyId < 1 || !sr_survey_comments_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_survey_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.survey_id = :survey_id
           AND c.status = 'published'
         ORDER BY COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('survey_id', $surveyId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], $settings, '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_survey_comment_input_values(): array
{
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_survey_clean_text(sr_post_string('body_text', 4000), 4000),
        'is_secret' => ($_POST['is_secret'] ?? '') === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
    ];
}

function sr_survey_validate_comment_input(array $values): array
{
    $errors = [];
    $bodyText = trim((string) ($values['body_text'] ?? ''));
    if ($bodyText === '') {
        $errors[] = '댓글 내용을 입력하세요.';
    }
    if ((function_exists('mb_strlen') ? mb_strlen($bodyText) : strlen($bodyText)) > 4000) {
        $errors[] = '댓글은 4000자 이내로 입력하세요.';
    }

    return $errors;
}

function sr_survey_validate_comment_parent(PDO $pdo, int $surveyId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }

    $parentComment = sr_survey_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['survey_id'] ?? 0) !== $surveyId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_survey_create_comment(PDO $pdo, int $surveyId, int $accountId, array $values): int
{
    if ($surveyId < 1 || $accountId < 1 || !sr_survey_comments_table_exists($pdo)) {
        throw new RuntimeException('Survey comment cannot be created.');
    }

    $now = sr_now();
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_survey_comments
            (survey_id, parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:survey_id, :parent_comment_id, :thread_root_id, :depth, :author_account_id, :author_public_name_snapshot, :body_text, :is_secret, \'published\', :created_at, :updated_at)'
    );
    $params = [
        'survey_id' => $surveyId,
        'parent_comment_id' => $parentCommentId > 0 ? $parentCommentId : null,
        'thread_root_id' => $threadRootId,
        'depth' => $depth,
        'author_account_id' => $accountId,
        'author_public_name_snapshot' => sr_survey_comment_author_public_name_snapshot($pdo, $accountId),
        'body_text' => (string) ($values['body_text'] ?? ''),
        'is_secret' => (int) ($values['is_secret'] ?? 0),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    $stmt->execute($params);

    $commentId = (int) $pdo->lastInsertId();
    if ($parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_survey_comments
             SET thread_root_id = :thread_root_id
             WHERE id = :id'
        );
        $stmt->execute([
            'thread_root_id' => $commentId,
            'id' => $commentId,
        ]);
    }

    return $commentId;
}

function sr_survey_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_survey_comments_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_survey_comments WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_survey_account_can_edit_comment(array $comment, array $account): bool
{
    return (string) ($comment['status'] ?? '') === 'published'
        && (int) ($comment['author_account_id'] ?? 0) > 0
        && (int) ($comment['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
}

function sr_survey_account_has_submitted_response(PDO $pdo, int $surveyId, int $accountId): bool
{
    if ($surveyId < 1 || $accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_survey_responses
         WHERE survey_id = :survey_id
           AND account_id = :account_id
           AND submitted_at IS NOT NULL'
    );
    $stmt->execute([
        'survey_id' => $surveyId,
        'account_id' => $accountId,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function sr_survey_account_can_manage_comments(PDO $pdo, int $accountId): bool
{
    return $accountId > 0
        && function_exists('sr_admin_has_permission')
        && (
            sr_admin_has_permission($pdo, $accountId, '/admin/surveys/comments', 'view')
            || sr_admin_has_permission($pdo, $accountId, '/admin/surveys', 'edit')
        );
}

function sr_survey_account_can_view_comment_body(array $comment, ?array $account, PDO $pdo): bool
{
    if ((int) ($comment['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }
    $accountId = (int) ($account['id'] ?? 0);

    return $accountId > 0
        && (
            $accountId === (int) ($comment['author_account_id'] ?? 0)
            || sr_survey_account_owns_comment_target($pdo, $comment, $accountId)
            || sr_survey_account_can_manage_comments($pdo, $accountId)
        );
}

function sr_survey_account_owns_comment_target(PDO $pdo, array $comment, int $accountId): bool
{
    $surveyId = (int) ($comment['survey_id'] ?? 0);
    if ($surveyId < 1 || $accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT created_by_account_id
         FROM sr_survey_forms
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $surveyId]);

    return (int) $stmt->fetchColumn() === $accountId;
}

function sr_survey_account_can_delete_comment(array $comment, array $account, PDO $pdo): bool
{
    if (!in_array((string) ($comment['status'] ?? ''), ['published', 'hidden'], true)) {
        return false;
    }
    if (sr_survey_account_can_edit_comment($comment, $account)) {
        return true;
    }

    return sr_survey_account_can_manage_comments($pdo, (int) ($account['id'] ?? 0));
}

function sr_survey_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_survey_comments
         SET body_text = :body_text,
             is_secret = :is_secret,
             updated_at = :updated_at
         WHERE id = :id
           AND status = \'published\''
    );
    $stmt->execute([
        'body_text' => (string) ($values['body_text'] ?? ''),
        'is_secret' => (int) ($values['is_secret'] ?? 0),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_survey_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    if (!in_array($status, sr_survey_comment_statuses(), true)) {
        throw new RuntimeException('Invalid survey comment status.');
    }
    if ($status === 'deleted') {
        sr_survey_delete_comment_redacted($pdo, $commentId);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_survey_comments
         SET status = :status,
             deleted_at = CASE WHEN :deleted_status = \'deleted\' THEN :deleted_at ELSE deleted_at END,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    $stmt->execute([
        'status' => $status,
        'deleted_status' => $status,
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_survey_delete_comment_redacted(PDO $pdo, int $commentId): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_survey_comments
         SET author_public_name_snapshot = '',
             body_text = :body_text,
             status = 'deleted',
             deleted_at = :deleted_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => '삭제된 댓글입니다.',
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_survey_soft_delete_redacted(PDO $pdo, int $surveyId, int $accountId): bool
{
    if ($surveyId < 1) {
        return false;
    }

    $now = sr_now();
    $deletedTitle = '삭제된 설문';
    $deletedBody = '삭제된 설문입니다.';
    $oldStmt = $pdo->prepare('SELECT cover_image_url FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $oldStmt->execute(['id' => $surveyId]);
    $oldRow = $oldStmt->fetch();
    $oldCoverImageUrl = is_array($oldRow) ? sr_survey_clean_cover_image_url((string) ($oldRow['cover_image_url'] ?? '')) : '';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_survey_forms
             SET title = :title,
                 description = :description,
                 cover_image_url = '',
                 research_purpose = '',
                 target_population = '',
                 recruitment_method = '',
                 project_brief = '',
                 sponsor_name = '',
                 research_region = '',
                 research_language = '',
                 fieldwork_method = '',
                 sample_frame = '',
                 sample_method = '',
                 quota_policy = '',
                 response_rate_basis = '',
                 analysis_plan = '',
                 weighting_policy = '',
                 margin_error_note = '',
                 methodology_disclosure = '',
                 ethics_note = '',
                 sensitive_data_policy = '',
                 recontact_policy = '',
                 withdrawal_policy = '',
                 vendor_name = '',
                 external_channel_policy = '',
                 invite_token_policy = '',
                 qa_note = '',
                 organizer_name = '',
                 contact_text = '',
                 consent_text = '',
                 privacy_notice = '',
                 comments_enabled = 0,
                 secret_comments_enabled = 0,
                 reward_enabled = 0,
                 updated_by_account_id = :account_id,
                 updated_at = :updated_at,
                 deleted_at = :deleted_at
             WHERE id = :id
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'description' => $deletedBody,
            'account_id' => $accountId,
            'updated_at' => $now,
            'deleted_at' => $now,
            'id' => $surveyId,
        ]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            "UPDATE sr_survey_questions
             SET prompt = :prompt,
                 analysis_note = '',
                 scale_min_label = '',
                 scale_max_label = '',
                 number_unit = '',
                 settings_json = NULL,
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'prompt' => $deletedBody,
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            'UPDATE sr_survey_choices c
             INNER JOIN sr_survey_questions q ON q.id = c.question_id
             SET c.label = :label,
                 c.settings_json = NULL,
                 c.updated_at = :updated_at
             WHERE q.survey_id = :survey_id'
        )->execute([
            'label' => '삭제된 선택지',
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_comments
             SET author_public_name_snapshot = '',
                 body_text = :body_text,
                 status = 'deleted',
                 deleted_at = COALESCE(deleted_at, :deleted_at),
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'body_text' => '삭제된 댓글입니다.',
            'deleted_at' => $now,
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_responses
             SET quality_note = '',
                 consent_snapshot_json = '{}',
                 metadata_snapshot_json = '{}',
                 answer_snapshot_json = '{}',
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_response_answers ra
             INNER JOIN sr_survey_responses r ON r.id = ra.response_id
             SET ra.answer_text = NULL,
                 ra.answer_number = NULL,
                 ra.other_text = NULL,
                 ra.answer_snapshot_json = '{}'
             WHERE r.survey_id = :survey_id"
        )->execute(['survey_id' => $surveyId]);
        $pdo->prepare(
            "UPDATE sr_survey_reward_grants
             SET request_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 error_message = ''
             WHERE survey_id = :survey_id"
        )->execute(['survey_id' => $surveyId]);

        $pdo->commit();
        if ($oldCoverImageUrl !== '') {
            sr_survey_delete_cover_image_storage($pdo, $oldCoverImageUrl, $surveyId);
        }
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_survey_notification_event_function(PDO $pdo): ?string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_survey_create_account_event_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata, ?int $createdByAccountId = null): bool
{
    if ($accountId < 1) {
        return false;
    }
    $function = sr_survey_notification_event_function($pdo);
    if ($function === null || !function_exists($function)) {
        return false;
    }

    $notificationId = $function($pdo, [
        'account_id' => $accountId,
        'module_key' => 'survey',
        'event_key' => $eventKey,
        'metadata' => $metadata,
        'created_by_account_id' => $createdByAccountId,
    ]);

    return (int) $notificationId > 0;
}

function sr_survey_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    if (!function_exists('sr_member_mention_account_ids')) {
        return [];
    }

    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_survey_create_comment_mention_notifications(
    PDO $pdo,
    array $survey,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = [],
    string $previousBodyText = ''
): array {
    $surveyId = (int) ($survey['id'] ?? 0);
    $mentionedAccountIds = sr_survey_mentioned_account_ids($pdo, $bodyText, $excludeAccountIds);
    if ($previousBodyText !== '') {
        $previousAccountIds = sr_survey_mentioned_account_ids($pdo, $previousBodyText, $excludeAccountIds);
        $mentionedAccountIds = array_values(array_diff($mentionedAccountIds, $previousAccountIds));
    }
    $result = [
        'mention_candidate_count' => count($mentionedAccountIds),
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    if ($mentionedAccountIds === []) {
        return $result;
    }

    $config = sr_runtime_config();
    $metadata = [
        'survey_id' => $surveyId,
        'comment_id' => $commentId,
        'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원'),
        'link_url' => '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?submitted=1#survey-comments',
        'created_at' => sr_now(),
    ];
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_survey_create_account_event_notification($pdo, (int) $accountId, 'comment.mention', $metadata, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}

function sr_survey_admin_comment_filters_from_request(): array
{
    return [
        'q' => sr_survey_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_survey_clean_key(sr_get_string('status', 20), 20),
        'secret' => sr_survey_clean_key(sr_get_string('secret', 10), 10),
    ];
}

function sr_survey_admin_comments(PDO $pdo, array $filters = [], int $limit = 100): array
{
    if (!sr_survey_comments_table_exists($pdo)) {
        return [];
    }

    $where = ['1 = 1'];
    $params = [];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $where[] = '(s.survey_key LIKE :keyword OR s.title LIKE :keyword OR c.body_text LIKE :keyword OR c.author_public_name_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, sr_survey_comment_statuses(), true)) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }
    $secret = (string) ($filters['secret'] ?? '');
    if ($secret === 'yes' || $secret === 'no') {
        $where[] = 'c.is_secret = :is_secret';
        $params['is_secret'] = $secret === 'yes' ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, s.survey_key, s.title AS survey_title
         FROM sr_survey_comments c
         INNER JOIN sr_survey_forms s ON s.id = c.survey_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT ' . (string) max(1, min(200, $limit))
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}
