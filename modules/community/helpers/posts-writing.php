<?php

declare(strict_types=1);

function sr_community_board_post_edit_lock_comment_count(PDO $pdo, array $board): int
{
    return sr_community_effective_board_int_setting($pdo, $board, 'post_edit_lock_comment_count', 0, 0, 1000000);
}

function sr_community_board_post_delete_lock_comment_count(PDO $pdo, array $board): int
{
    return sr_community_effective_board_int_setting($pdo, $board, 'post_delete_lock_comment_count', 0, 0, 1000000);
}

function sr_community_board_post_body_min_length(PDO $pdo, array $board, ?array $settings = null): int
{
    $default = array_key_exists('post_body_min_length', $board)
        ? (int) $board['post_body_min_length']
        : sr_community_post_body_length_setting($settings['post_body_min_length'] ?? 0);

    return sr_community_effective_board_int_setting($pdo, $board, 'post_body_min_length', $default, 0, sr_community_post_body_setting_max_length());
}

function sr_community_board_post_body_max_length(PDO $pdo, array $board, ?array $settings = null): int
{
    $default = array_key_exists('post_body_max_length', $board)
        ? (int) $board['post_body_max_length']
        : sr_community_post_body_length_setting($settings['post_body_max_length'] ?? 0);

    return sr_community_effective_board_int_setting($pdo, $board, 'post_body_max_length', $default, 0, sr_community_post_body_setting_max_length());
}

function sr_community_mark_post_embed_target_stale(PDO $pdo, int $postId): void
{
    if ($postId > 0 && function_exists('sr_url_embed_mark_target_url_cache_stale')) {
        sr_url_embed_mark_target_url_cache_stale($pdo, 'community', 'post', $postId);
    }
}

function sr_community_mark_board_post_embed_targets_stale(PDO $pdo, int $boardId): void
{
    if ($boardId < 1 || !function_exists('sr_url_embed_cache_table_exists') || !sr_url_embed_cache_table_exists($pdo)) {
        return;
    }

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }
    $targetMatchSql = $driver === 'sqlite'
        ? 'target_id IN (SELECT CAST(id AS TEXT) FROM sr_community_posts WHERE board_id = :board_id)'
        : 'EXISTS (
               SELECT 1
               FROM sr_community_posts p
               WHERE p.board_id = :board_id
                 AND p.id = CAST(sr_url_embed_cache.target_id AS UNSIGNED)
           )';
    $stmt = $pdo->prepare(
        'UPDATE sr_url_embed_cache
         SET cache_status = \'stale\',
             updated_at = :updated_at
         WHERE target_module = \'community\'
           AND target_type = \'post\'
           AND cache_status = \'fresh\'
           AND ' . $targetMatchSql
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'board_id' => $boardId,
    ]);
}

function sr_community_validate_post_body_length(PDO $pdo, array $board, array $values, ?array $settings = null): array
{
    $bodyText = $values['body_text'] ?? '';
    if (!is_string($bodyText)) {
        return [];
    }

    $length = sr_community_body_plain_length($bodyText, (string) ($values['body_format'] ?? 'plain'));
    $minLength = sr_community_board_post_body_min_length($pdo, $board, $settings);
    $maxLength = sr_community_board_post_body_max_length($pdo, $board, $settings);
    $errors = [];
    if ($minLength > 0 && $length < $minLength) {
        $errors[] = '게시글 본문은 최소 ' . number_format($minLength) . '자 이상 입력해 주세요.';
    }
    if ($maxLength > 0 && $length > $maxLength) {
        $errors[] = '게시글 본문은 최대 ' . number_format($maxLength) . '자까지 입력할 수 있습니다.';
    }

    return $errors;
}

function sr_community_post_locked_by_comments(PDO $pdo, array $board, int $postId, string $action): bool
{
    $threshold = $action === 'delete'
        ? sr_community_board_post_delete_lock_comment_count($pdo, $board)
        : sr_community_board_post_edit_lock_comment_count($pdo, $board);

    return $threshold > 0 && sr_community_post_published_comment_count($pdo, $postId) >= $threshold;
}

function sr_community_update_post_status(PDO $pdo, int $postId, string $status, array $options = []): void
{
    if ($status === 'deleted') {
        $bodyTextBeforeDelete = '';
        try {
            $stmt = $pdo->prepare('SELECT body_text FROM sr_community_posts WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $postId]);
            $bodyTextBeforeDelete = (string) ($stmt->fetchColumn() ?: '');
        } catch (Throwable) {
            $bodyTextBeforeDelete = '';
        }
        sr_community_redact_deleted_post($pdo, $postId);
        sr_community_mark_post_embed_target_stale($pdo, $postId);
        if (function_exists('sr_community_feed_cache_mark_all_stale')) {
            sr_community_feed_cache_mark_all_stale($pdo, 'post_status_changed');
        }
        if (empty($options['defer_file_cleanup'])) {
            sr_community_cleanup_body_file_refs_for_deleted_post($pdo, $postId, $bodyTextBeforeDelete);
            sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
        }
        return;
    }

    sr_community_update_status_with_hidden_metadata($pdo, 'sr_community_posts', $postId, $status, $options);
    sr_community_mark_post_embed_target_stale($pdo, $postId);
    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'post_status_changed');
    }
}

function sr_community_hidden_target_type_for_table(string $tableName): string
{
    if ($tableName === 'sr_community_posts') {
        return 'post';
    }
    if ($tableName === 'sr_community_comments') {
        return 'comment';
    }

    return '';
}

function sr_community_hidden_target_select_columns(string $alias = 'hidden_meta'): string
{
    $prefix = preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $alias) === 1 ? $alias : 'hidden_meta';

    return $prefix . '.hidden_at,
                    ' . $prefix . '.hidden_until,
                    COALESCE(' . $prefix . '.hidden_reason, \'\') AS hidden_reason,
                    ' . $prefix . '.hidden_note,
                    ' . $prefix . '.hidden_by_account_id,
                    COALESCE(' . $prefix . '.hidden_before_status, \'\') AS hidden_before_status';
}

function sr_community_hidden_target_join_sql(string $targetType, string $targetAlias, string $joinAlias = 'hidden_meta'): string
{
    if (!in_array($targetType, ['post', 'comment'], true)) {
        return '';
    }
    if (preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $targetAlias) !== 1) {
        return '';
    }
    if (preg_match('/\A[a-zA-Z_][a-zA-Z0-9_]*\z/', $joinAlias) !== 1) {
        $joinAlias = 'hidden_meta';
    }

    return "LEFT JOIN sr_community_hidden_targets " . $joinAlias . " ON " . $joinAlias . ".target_type = '" . $targetType . "' AND " . $joinAlias . ".target_id = " . $targetAlias . ".id";
}

function sr_community_save_hidden_target(PDO $pdo, string $targetType, int $targetId, string $beforeStatus, array $options = []): void
{
    if ($targetId < 1 || !in_array($targetType, ['post', 'comment'], true)) {
        return;
    }

    $existingStmt = $pdo->prepare(
        'SELECT hidden_before_status, created_at
         FROM sr_community_hidden_targets
         WHERE target_type = :target_type
           AND target_id = :target_id
         LIMIT 1'
    );
    $existingStmt->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $existing = $existingStmt->fetch();
    $existingBeforeStatus = is_array($existing) ? (string) ($existing['hidden_before_status'] ?? '') : '';
    $hiddenBeforeStatus = $beforeStatus !== '' && $beforeStatus !== 'hidden'
        ? $beforeStatus
        : $existingBeforeStatus;
    if ($hiddenBeforeStatus === '') {
        $hiddenBeforeStatus = 'published';
    }

    $now = sr_now();
    $createdAt = is_array($existing) && (string) ($existing['created_at'] ?? '') !== ''
        ? (string) $existing['created_at']
        : $now;
    $pdo->prepare(
        'DELETE FROM sr_community_hidden_targets
         WHERE target_type = :target_type
           AND target_id = :target_id'
    )->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_hidden_targets
            (target_type, target_id, hidden_at, hidden_until, hidden_reason, hidden_note, hidden_by_account_id, hidden_before_status, created_at, updated_at)
         VALUES
            (:target_type, :target_id, :hidden_at, :hidden_until, :hidden_reason, :hidden_note, :hidden_by_account_id, :hidden_before_status, :created_at, :updated_at)'
    );
    $stmt->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
        'hidden_at' => $now,
        'hidden_until' => $options['hidden_until'] ?? null,
        'hidden_reason' => (string) ($options['hidden_reason'] ?? ''),
        'hidden_note' => (string) ($options['hidden_note'] ?? ''),
        'hidden_by_account_id' => isset($options['hidden_by_account_id']) && (int) $options['hidden_by_account_id'] > 0 ? (int) $options['hidden_by_account_id'] : null,
        'hidden_before_status' => $hiddenBeforeStatus,
        'created_at' => $createdAt,
        'updated_at' => $now,
    ]);
}

function sr_community_clear_hidden_target(PDO $pdo, string $targetType, int $targetId): void
{
    if ($targetId < 1 || !in_array($targetType, ['post', 'comment'], true)) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_hidden_targets
         WHERE target_type = :target_type
           AND target_id = :target_id'
    );
    $stmt->execute([
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);
}

function sr_community_update_status_with_hidden_metadata(PDO $pdo, string $tableName, int $id, string $status, array $options = []): void
{
    if ($id < 1 || !in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return;
    }

    $now = sr_now();
    $targetType = sr_community_hidden_target_type_for_table($tableName);
    if ($status === 'hidden') {
        $beforeStmt = $pdo->prepare('SELECT status FROM ' . $tableName . ' WHERE id = :id LIMIT 1');
        $beforeStmt->execute(['id' => $id]);
        $beforeStatus = (string) ($beforeStmt->fetchColumn() ?: '');
        $stmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET status = :status,
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> \'deleted\''
        );
        $stmt->execute([
            'status' => $status,
            'updated_at' => $now,
            'id' => $id,
        ]);
        if ($stmt->rowCount() > 0) {
            sr_community_save_hidden_target($pdo, $targetType, $id, $beforeStatus, $options);
        }
        if ($tableName === 'sr_community_posts') {
            sr_community_mark_post_embed_target_stale($pdo, $id);
        }
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . $tableName . '
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => $now,
        'id' => $id,
    ]);
    if ($stmt->rowCount() > 0) {
        sr_community_clear_hidden_target($pdo, $targetType, $id);
    }
    if ($tableName === 'sr_community_posts') {
        sr_community_mark_post_embed_target_stale($pdo, $id);
    }
}

function sr_community_redact_deleted_post(PDO $pdo, int $postId): void
{
    if ($postId < 1) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_community_posts
         SET status = 'deleted',
             title = :title,
             body_text = '',
             author_public_name_snapshot = '',
             guest_author_name = '',
             guest_password_hash = NULL,
             guest_ip_hash = NULL,
             guest_user_agent_hash = NULL,
             extra_values_json = '[]',
             seo_title = '',
             seo_description = '',
             og_title = '',
             og_description = '',
             og_image_attachment_id = NULL,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'title' => sr_t('community::redaction.deleted_post_title'),
        'updated_at' => $now,
        'id' => $postId,
    ]);
    sr_community_clear_hidden_target($pdo, 'post', $postId);
    sr_community_redact_post_field_values($pdo, $postId);

    if (function_exists('sr_url_embed_sync_body_url_cache')) {
        sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', null);
    }
    sr_community_mark_post_embed_target_stale($pdo, $postId);
}

function sr_community_update_post_og_image(PDO $pdo, int $postId, ?int $attachmentId): void
{
    if ($postId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET og_image_attachment_id = :og_image_attachment_id,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'og_image_attachment_id' => is_int($attachmentId) && $attachmentId > 0 ? $attachmentId : 0,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
    sr_community_mark_post_embed_target_stale($pdo, $postId);
}

function sr_community_update_post_notice(PDO $pdo, int $postId, bool $isNotice): void
{
    if ($postId < 1 || !sr_community_post_notice_supported($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET is_notice = :is_notice,
             updated_at = :updated_at
         WHERE id = :id
           AND status = \'published\''
    );
    $stmt->execute([
        'is_notice' => $isNotice ? 1 : 0,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'post_notice_changed');
    }
}

function sr_community_update_post_content(PDO $pdo, int $postId, array $values, int $accountId = 0): void
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 수정은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $createdBodyFiles = [];
    $finalizedTmpFiles = [];
    $previousBodyText = '';
    $pdo->beginTransaction();

    try {
        $previousStmt = $pdo->prepare('SELECT body_text FROM sr_community_posts WHERE id = :id LIMIT 1');
        $previousStmt->execute(['id' => $postId]);
        $previousBodyText = (string) ($previousStmt->fetchColumn() ?: '');

        $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html', 'markdown'], true)
            ? (string) $values['body_format']
            : 'plain';
        $bodyText = trim((string) $values['body_text']);

        if ($bodyFormat === 'html') {
            $bodyText = sr_community_finalize_body_files($pdo, $postId, $bodyText, $accountId, false, $createdBodyFiles, $finalizedTmpFiles);
        }
        $categorySupported = sr_community_categories_supported($pdo);
        $categorySetSql = $categorySupported ? 'category_id = :category_id,' : '';
        $noticeSupported = sr_community_post_notice_supported($pdo);
        $noticeSetSql = $noticeSupported ? 'is_notice = :is_notice,' : '';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_posts
             SET ' . $categorySetSql . '
                 extra_values_json = :extra_values_json,
                 title = :title,
                 body_text = :body_text,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 og_title = :og_title,
                 og_description = :og_description,
                 is_secret = :is_secret,
                 ' . $noticeSetSql . '
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $params = [
            'extra_values_json' => (string) ($values['extra_values_json'] ?? '[]'),
            'title' => trim((string) $values['title']),
            'body_text' => $bodyText,
            'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
            'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
            'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
            'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
            'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
            'updated_at' => sr_now(),
            'id' => $postId,
        ];
        if ($noticeSupported) {
            $params['is_notice'] = (int) ($values['is_notice'] ?? 0) === 1 ? 1 : 0;
        }
        if ($categorySupported) {
            $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
        }
        $stmt->execute($params);
        if ($bodyFormat === 'html') {
            sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', $bodyText, $accountId > 0 ? $accountId : null);
        } else {
            sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', $accountId > 0 ? $accountId : null);
        }
        sr_community_mark_post_embed_target_stale($pdo, $postId);
        if (function_exists('sr_community_feed_cache_mark_all_stale')) {
            sr_community_feed_cache_mark_all_stale($pdo, 'post_content_changed');
        }
        sr_community_save_post_field_values(
            $pdo,
            $postId,
            is_array($values['extra_field_definitions'] ?? null) ? $values['extra_field_definitions'] : [],
            is_array($values['extra_field_values'] ?? null) ? $values['extra_field_values'] : []
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_community_cleanup_storage_file_refs($pdo, $createdBodyFiles, 'body_file_update_rollback', $postId, '게시글 수정 실패 후 본문 이미지 저장소 정리에 실패했습니다.');
        throw $exception;
    }

    if ($bodyFormat === 'html') {
        sr_community_cleanup_storage_file_refs($pdo, $finalizedTmpFiles, 'body_file_tmp_finalized', $postId, '게시글 수정 후 임시 본문 이미지 정리에 실패했습니다.');
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, $bodyText, $previousBodyText);
    } else {
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, '', $previousBodyText);
    }
}

function sr_community_account_can_edit_post(array $post, array $account): bool
{
    return (int) ($account['id'] ?? 0) > 0
        && (int) $post['author_account_id'] === (int) $account['id']
        && (string) $post['status'] === 'published';
}

function sr_community_account_can_delete_post(array $post, array $account, ?PDO $pdo = null): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1 || (string) ($post['status'] ?? '') !== 'published') {
        return false;
    }

    if ((int) ($post['author_account_id'] ?? 0) === $accountId) {
        return true;
    }

    if (!$pdo instanceof PDO) {
        return false;
    }

    if (function_exists('sr_admin_has_permission') && sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')) {
        return true;
    }

    return sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_post');
}

function sr_community_account_can_hide_post(PDO $pdo, array $post, ?array $account): bool
{
    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1 || (string) ($post['status'] ?? '') !== 'published') {
        return false;
    }

    return (function_exists('sr_admin_has_permission')
            && (sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit')
                || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')))
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'hide_post')
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'delete_post');
}

function sr_community_guest_can_edit_post(array $post, string $password): bool
{
    return (int) ($post['author_account_id'] ?? 0) < 1
        && (string) ($post['status'] ?? '') === 'published'
        && sr_community_guest_password_verified($post, $password);
}

function sr_community_guest_can_delete_post(array $post, string $password): bool
{
    return sr_community_guest_can_edit_post($post, $password);
}

function sr_community_account_can_write_board(PDO $pdo, array $board, ?array $account, bool $isAdminWriter = false): bool
{
    $accountId = (int) ($account['id'] ?? 0);
    if ((string) ($board['status'] ?? '') !== 'enabled') {
        return false;
    }

    $policy = sr_community_effective_board_policy($pdo, $board, 'write_policy');
    if ($policy === 'guest') {
        return true;
    }

    if ($accountId < 1) {
        return false;
    }

    if ($policy === 'member') {
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'group') {
        $groupKeys = sr_community_board_group_keys($pdo, (int) $board['id'], 'write_group_keys');
        $minLevel = sr_community_board_min_level($pdo, (int) $board['id'], 'write_min_level');
        return !empty(sr_community_account_satisfies_access($pdo, $accountId, [
            'group_keys' => $groupKeys,
            'min_level' => $minLevel,
        ])['allowed']);
    }

    if ($policy === 'admin') {
        return $isAdminWriter;
    }

    return false;
}

function sr_community_account_can_write_notice(PDO $pdo, array $board, ?array $account, bool $isAdminWriter = false): bool
{
    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ((string) ($board['status'] ?? '') !== 'enabled' || $accountId < 1) {
        return false;
    }

    return $isAdminWriter
        || sr_community_account_has_board_management_permission($pdo, (int) ($board['id'] ?? 0), $accountId, 'write_notice');
}

function sr_community_post_input_values(?PDO $pdo = null, ?array $board = null, ?array $settings = null): array
{
    $bodyFormat = 'plain';
    $postEditorKey = 'textarea';
    if ($pdo instanceof PDO && is_array($board)) {
        $postEditorKey = sr_community_effective_post_editor($pdo, $board, $settings);
    } elseif ($pdo instanceof PDO) {
        $normalizedSettings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
        $postEditorKey = sr_editor_effective_key($pdo, (string) ($normalizedSettings['post_editor'] ?? 'textarea'));
    }
    $bodyFormat = $pdo instanceof PDO ? sr_community_body_format_for_editor_key($pdo, $postEditorKey) : 'plain';

    $bodyText = sr_post_string_without_truncation('body_text', sr_community_post_body_storage_max_bytes());
    if ($bodyFormat === 'html' && is_string($bodyText)) {
        $bodyText = sr_community_sanitize_post_html($bodyText);
    }

    return [
        'title' => sr_post_string_without_truncation('title', 160),
        'category_id' => preg_match('/\A[1-9][0-9]*\z/', sr_post_string('category_id', 20)) === 1 ? (int) sr_post_string('category_id', 20) : 0,
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'seo_title' => '',
        'seo_description' => '',
        'og_title' => '',
        'og_description' => '',
        'is_secret' => sr_post_string('is_secret', 10) === '1'
            && $pdo instanceof PDO
            && is_array($board)
            && sr_community_effective_board_secret_posts_enabled($pdo, $board, $settings) ? 1 : 0,
        'is_notice' => sr_post_string('is_notice', 10) === '1' ? 1 : 0,
    ];
}

function sr_community_validate_post_input(array $values, ?PDO $pdo = null): array
{
    $errors = [];
    $title = $values['title'];
    $bodyText = $values['body_text'];

    if (!is_string($title)) {
        $errors[] = sr_t('community::action.error.post_title_too_long');
    } elseif (trim($title) === '') {
        $errors[] = sr_t('community::action.error.post_title_required');
    }

    if (!is_string($bodyText)) {
        $errors[] = sr_t('community::action.error.post_body_too_long');
    } elseif (sr_community_body_text_is_empty($bodyText, (string) ($values['body_format'] ?? 'plain'))) {
        $errors[] = sr_t('community::action.error.post_body_required');
    }
    if ((string) ($values['body_format'] ?? 'plain') === 'markdown' && $pdo instanceof PDO && !sr_markdown_renderer_available($pdo)) {
        $errors[] = 'Markdown 본문을 저장하려면 Markdown Editor 플러그인을 활성화하세요.';
    }

    return $errors;
}

function sr_community_create_post(PDO $pdo, int $boardId, int $authorAccountId, array $values): int
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 작성은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $initialStatusInput = (string) ($values['initial_status'] ?? 'published');
    $initialStatus = in_array($initialStatusInput, ['published', 'pending'], true)
        ? $initialStatusInput
        : 'published';
    $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html', 'markdown'], true)
        ? (string) ($values['body_format'] ?? 'plain')
        : 'plain';
    $bodyText = trim((string) ($values['body_text'] ?? ''));

    $now = sr_now();
    $categorySupported = sr_community_categories_supported($pdo);
    $categoryColumnSql = $categorySupported ? 'category_id, ' : '';
    $categoryValueSql = $categorySupported ? ':category_id, ' : '';
    $noticeSupported = sr_community_post_notice_supported($pdo);
    $noticeColumnSql = $noticeSupported ? 'is_notice, ' : '';
    $noticeValueSql = $noticeSupported ? ':is_notice, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, author_public_name_snapshot, guest_author_name, guest_password_hash, guest_ip_hash, guest_user_agent_hash, extra_values_json, title, body_text, seo_title, seo_description, og_title, og_description, is_secret, ' . $noticeColumnSql . 'summary_feed_candidate, status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, :author_public_name_snapshot, :guest_author_name, :guest_password_hash, :guest_ip_hash, :guest_user_agent_hash, :extra_values_json, :title, :body_text, :seo_title, :seo_description, :og_title, :og_description, :is_secret, ' . $noticeValueSql . ':summary_feed_candidate, :status, 0, NULL, :created_at, :updated_at)'
    );
    $guestValues = sr_community_guest_author_values_for_storage($values);
    $params = [
        'board_id' => $boardId,
        'author_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
        'author_public_name_snapshot' => $authorAccountId > 0
            ? sr_community_author_public_name_snapshot($pdo, $authorAccountId)
            : sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? '')),
        'guest_author_name' => $authorAccountId > 0 ? '' : (string) $guestValues['guest_author_name'],
        'guest_password_hash' => $authorAccountId > 0 ? null : $guestValues['guest_password_hash'],
        'guest_ip_hash' => $authorAccountId > 0 ? null : $guestValues['guest_ip_hash'],
        'guest_user_agent_hash' => $authorAccountId > 0 ? null : $guestValues['guest_user_agent_hash'],
        'extra_values_json' => (string) ($values['extra_values_json'] ?? '[]'),
        'title' => trim((string) $values['title']),
        'body_text' => $bodyText,
        'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
        'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
        'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
        'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
        'is_secret' => (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0,
        'summary_feed_candidate' => sr_community_summary_feed_candidate_value_for_board($pdo, $boardId),
        'status' => $initialStatus,
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($noticeSupported) {
        $params['is_notice'] = (int) ($values['is_notice'] ?? 0) === 1 ? 1 : 0;
    }
    if ($categorySupported) {
        $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
    }
    $pdo->beginTransaction();

    $createdBodyFiles = [];
    $finalizedTmpFiles = [];
    try {
        $stmt->execute($params);
        $postId = (int) $pdo->lastInsertId();
        if ($bodyFormat === 'html') {
            $finalBodyText = sr_community_finalize_body_files($pdo, $postId, $bodyText, $authorAccountId, true, $createdBodyFiles, $finalizedTmpFiles);
            if ($finalBodyText !== $bodyText) {
                $bodyText = $finalBodyText;
                $pdo->prepare('UPDATE sr_community_posts SET body_text = :body_text, updated_at = :updated_at WHERE id = :id')->execute([
                    'body_text' => $finalBodyText,
                    'updated_at' => $now,
                    'id' => $postId,
                ]);
            }
            sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', $bodyText, $authorAccountId);
        } else {
            sr_url_embed_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', $authorAccountId);
        }
        sr_community_save_post_field_values(
            $pdo,
            $postId,
            is_array($values['extra_field_definitions'] ?? null) ? $values['extra_field_definitions'] : [],
            is_array($values['extra_field_values'] ?? null) ? $values['extra_field_values'] : []
        );
        $pdo->commit();
        sr_community_cleanup_storage_file_refs($pdo, $finalizedTmpFiles, 'body_file_tmp_finalized', $postId, '게시글 작성 후 임시 본문 이미지 정리에 실패했습니다.');
        if (function_exists('sr_community_feed_cache_mark_all_stale')) {
            sr_community_feed_cache_mark_all_stale($pdo, 'post_created');
        }

        return $postId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sr_community_cleanup_storage_file_refs($pdo, $createdBodyFiles, 'body_file_create_rollback', isset($postId) ? (int) $postId : 0, '게시글 작성 실패 후 본문 이미지 저장소 정리에 실패했습니다.');
        throw $exception;
    }
}

function sr_community_post_rate_limited(PDO $pdo, int $accountId, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $limit = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.post.account', (string) $accountId, $windowSeconds) >= $limit;
}

function sr_community_record_post_rate_limit(PDO $pdo, int $accountId, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.post.account', (string) $accountId, $windowSeconds);
}

function sr_community_guest_post_rate_limited(PDO $pdo, array $settings): bool
{
    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $limit = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));

    return sr_community_rate_limits_table_exists($pdo)
        && sr_rate_limit_count($pdo, 'community.post.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds) >= $limit;
}

function sr_community_record_guest_post_rate_limit(PDO $pdo, array $settings): void
{
    if (!sr_community_rate_limits_table_exists($pdo)) {
        return;
    }

    $windowSeconds = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    sr_rate_limit_increment($pdo, 'community.post.guest', sr_community_guest_rate_limit_identifier(), $windowSeconds);
}

function sr_community_rate_limits_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_rate_limits LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}
