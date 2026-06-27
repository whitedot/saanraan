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
    if ($postId > 0 && function_exists('sr_embed_manager_mark_target_url_cache_stale')) {
        sr_embed_manager_mark_target_url_cache_stale($pdo, 'community', 'post', $postId);
    }
}

function sr_community_mark_board_post_embed_targets_stale(PDO $pdo, int $boardId): void
{
    if ($boardId < 1 || !function_exists('sr_embed_manager_url_cache_table_exists') || !sr_embed_manager_url_cache_table_exists($pdo)) {
        return;
    }

    $driver = '';
    try {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable) {
        $driver = '';
    }
    $targetIdSql = $driver === 'sqlite' ? 'CAST(id AS TEXT)' : 'CAST(id AS CHAR)';
    $stmt = $pdo->prepare(
        'UPDATE sr_embed_manager_url_cache
         SET cache_status = \'stale\',
             updated_at = :updated_at
         WHERE target_module = \'community\'
           AND target_type = \'post\'
           AND cache_status = \'fresh\'
           AND target_id IN (SELECT ' . $targetIdSql . ' FROM sr_community_posts WHERE board_id = :board_id)'
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
        sr_community_redact_deleted_post($pdo, $postId);
        sr_community_mark_post_embed_target_stale($pdo, $postId);
        if (function_exists('sr_community_feed_cache_mark_all_stale')) {
            sr_community_feed_cache_mark_all_stale($pdo, 'post_status_changed');
        }
        if (empty($options['defer_file_cleanup'])) {
            sr_community_cleanup_body_files_for_deleted_posts($pdo, [$postId]);
        }
        return;
    }

    if (sr_community_hidden_columns_exist($pdo, 'sr_community_posts')) {
        sr_community_update_status_with_hidden_metadata($pdo, 'sr_community_posts', $postId, $status, $options);
        sr_community_mark_post_embed_target_stale($pdo, $postId);
        if (function_exists('sr_community_feed_cache_mark_all_stale')) {
            sr_community_feed_cache_mark_all_stale($pdo, 'post_status_changed');
        }
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $postId,
    ]);
    sr_community_mark_post_embed_target_stale($pdo, $postId);
    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'post_status_changed');
    }
}

function sr_community_update_status_with_hidden_metadata(PDO $pdo, string $tableName, int $id, string $status, array $options = []): void
{
    if ($id < 1 || !in_array($tableName, ['sr_community_posts', 'sr_community_comments'], true)) {
        return;
    }

    $now = sr_now();
    if ($status === 'hidden') {
        $stmt = $pdo->prepare(
            'UPDATE ' . $tableName . '
             SET status = :status,
                 hidden_at = :hidden_at,
                 hidden_until = :hidden_until,
                 hidden_reason = :hidden_reason,
                 hidden_note = :hidden_note,
                 hidden_by_account_id = :hidden_by_account_id,
                 hidden_before_status = CASE WHEN status <> \'hidden\' THEN status ELSE hidden_before_status END,
                 updated_at = :updated_at
             WHERE id = :id
               AND status <> \'deleted\''
        );
        $stmt->execute([
            'status' => $status,
            'hidden_at' => $now,
            'hidden_until' => $options['hidden_until'] ?? null,
            'hidden_reason' => (string) ($options['hidden_reason'] ?? ''),
            'hidden_note' => (string) ($options['hidden_note'] ?? ''),
            'hidden_by_account_id' => isset($options['hidden_by_account_id']) && (int) $options['hidden_by_account_id'] > 0 ? (int) $options['hidden_by_account_id'] : null,
            'updated_at' => $now,
            'id' => $id,
        ]);
        if ($tableName === 'sr_community_posts') {
            sr_community_mark_post_embed_target_stale($pdo, $id);
        }
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE ' . $tableName . '
         SET status = :status,
             hidden_at = NULL,
             hidden_until = NULL,
             hidden_reason = \'\',
             hidden_note = NULL,
             hidden_by_account_id = NULL,
             hidden_before_status = \'\',
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'deleted\''
    );
    $stmt->execute([
        'status' => $status,
        'updated_at' => $now,
        'id' => $id,
    ]);
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
    $guestRedactionSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_posts')
        ? "guest_author_name = '',
             guest_password_hash = NULL,
             guest_ip_hash = NULL,
             guest_user_agent_hash = NULL,"
        : '';
    $extraValuesRedactionSql = sr_community_post_extra_values_column_exists($pdo) ? "extra_values_json = '[]'," : '';
    $stmt = $pdo->prepare(
        "UPDATE sr_community_posts
         SET status = 'deleted',
             title = :title,
             body_text = '',
             body_format = 'plain',
             author_public_name_snapshot = '',
             " . $guestRedactionSql . "
             " . $extraValuesRedactionSql . "
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
    sr_community_redact_post_field_values($pdo, $postId);

    if (function_exists('sr_embed_manager_sync_body_url_cache')) {
        sr_embed_manager_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', null);
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

function sr_community_post_reaction_preset_columns_exist(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT reaction_preset_key, reaction_comment_preset_key FROM sr_community_posts LIMIT 0');
        $exists = true;
    } catch (Throwable) {
        $exists = false;
    }

    return $exists;
}

function sr_community_update_post_content(PDO $pdo, int $postId, array $values, int $accountId = 0): void
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 수정은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $createdBodyFiles = [];
    $finalizedTmpFiles = [];
    $pdo->beginTransaction();

    try {
        $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html'], true)
            ? (string) $values['body_format']
            : 'plain';
        $bodyText = trim((string) $values['body_text']);
        if (sr_link_card_token_rejection_errors($bodyText) !== []) {
            throw new InvalidArgumentException('링크 카드 토큰은 게시글 본문에 저장할 수 없습니다.');
        }

        if ($bodyFormat === 'html') {
            $bodyText = sr_community_finalize_body_files($pdo, $postId, $bodyText, $accountId, false, $createdBodyFiles, $finalizedTmpFiles);
        }
        $categorySupported = sr_community_categories_supported($pdo);
        $categorySetSql = $categorySupported ? 'category_id = :category_id,' : '';
        $extraValuesSetSql = sr_community_post_extra_values_column_exists($pdo) ? 'extra_values_json = :extra_values_json,' : '';
        $reactionSetSql = sr_community_post_reaction_preset_columns_exist($pdo) ? 'reaction_preset_key = :reaction_preset_key, reaction_comment_preset_key = :reaction_comment_preset_key,' : '';
        $secretSetSql = sr_community_post_secret_column_exists($pdo) ? 'is_secret = :is_secret,' : '';
        $stmt = $pdo->prepare(
            'UPDATE sr_community_posts
             SET ' . $categorySetSql . '
                 ' . $extraValuesSetSql . '
                 title = :title,
                 body_text = :body_text,
                 body_format = :body_format,
                 seo_title = :seo_title,
                 seo_description = :seo_description,
                 og_title = :og_title,
                 og_description = :og_description,
                 ' . $reactionSetSql . '
                 ' . $secretSetSql . '
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $params = [
            'title' => trim((string) $values['title']),
            'body_text' => $bodyText,
            'body_format' => $bodyFormat,
            'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
            'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
            'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
            'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
            'updated_at' => sr_now(),
            'id' => $postId,
        ];
        if ($categorySupported) {
            $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
        }
        if ($extraValuesSetSql !== '') {
            $params['extra_values_json'] = (string) ($values['extra_values_json'] ?? '[]');
        }
        if ($reactionSetSql !== '') {
            $params['reaction_preset_key'] = '';
            $params['reaction_comment_preset_key'] = '';
        }
        if ($secretSetSql !== '') {
            $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
        }
        $stmt->execute($params);
        if ($bodyFormat === 'html') {
            sr_embed_manager_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', $bodyText, $accountId > 0 ? $accountId : null);
        } else {
            sr_embed_manager_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', $accountId > 0 ? $accountId : null);
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
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, $bodyText);
    } else {
        sr_community_cleanup_unreferenced_body_files($pdo, $postId, '');
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

function sr_community_post_input_values(?PDO $pdo = null, ?array $board = null, ?array $settings = null): array
{
    $bodyFormat = 'plain';
    if ($pdo instanceof PDO && sr_post_string('body_format', 20) === 'html' && sr_community_html_post_body_enabled($pdo, $board, $settings)) {
        $bodyFormat = 'html';
    }

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
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'is_secret' => sr_post_string('is_secret', 10) === '1'
            && $pdo instanceof PDO
            && is_array($board)
            && sr_community_effective_board_secret_posts_enabled($pdo, $board, $settings) ? 1 : 0,
    ];
}

function sr_community_validate_post_input(array $values): array
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
    if (is_string($bodyText)) {
        $errors = array_merge($errors, sr_link_card_token_rejection_errors($bodyText));
    }

    return $errors;
}

function sr_community_create_post(PDO $pdo, int $boardId, int $authorAccountId, array $values): int
{
    if ($pdo->inTransaction()) {
        throw new RuntimeException('게시글 본문 이미지를 포함한 작성은 외부 트랜잭션에서 처리할 수 없습니다.');
    }

    $bodyFormat = in_array((string) ($values['body_format'] ?? 'plain'), ['plain', 'html'], true)
        ? (string) $values['body_format']
        : 'plain';
    $bodyText = trim((string) ($values['body_text'] ?? ''));
    if (sr_link_card_token_rejection_errors($bodyText) !== []) {
        throw new InvalidArgumentException('링크 카드 토큰은 게시글 본문에 저장할 수 없습니다.');
    }

    $now = sr_now();
    $categorySupported = sr_community_categories_supported($pdo);
    $categoryColumnSql = $categorySupported ? 'category_id, ' : '';
    $categoryValueSql = $categorySupported ? ':category_id, ' : '';
    $authorSnapshotColumnSql = sr_community_author_public_name_snapshot_column_exists($pdo, 'sr_community_posts') ? 'author_public_name_snapshot, ' : '';
    $authorSnapshotValueSql = $authorSnapshotColumnSql !== '' ? ':author_public_name_snapshot, ' : '';
    $guestAuthorColumnSql = sr_community_guest_author_columns_exist($pdo, 'sr_community_posts') ? 'guest_author_name, guest_password_hash, guest_ip_hash, guest_user_agent_hash, ' : '';
    $guestAuthorValueSql = $guestAuthorColumnSql !== '' ? ':guest_author_name, :guest_password_hash, :guest_ip_hash, :guest_user_agent_hash, ' : '';
    $extraValuesColumnSql = sr_community_post_extra_values_column_exists($pdo) ? 'extra_values_json, ' : '';
    $extraValuesValueSql = $extraValuesColumnSql !== '' ? ':extra_values_json, ' : '';
    $reactionColumnSql = sr_community_post_reaction_preset_columns_exist($pdo) ? 'reaction_preset_key, reaction_comment_preset_key, ' : '';
    $reactionValueSql = $reactionColumnSql !== '' ? ':reaction_preset_key, :reaction_comment_preset_key, ' : '';
    $secretColumnSql = sr_community_post_secret_column_exists($pdo) ? 'is_secret, ' : '';
    $secretValueSql = $secretColumnSql !== '' ? ':is_secret, ' : '';
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_posts
            (board_id, ' . $categoryColumnSql . 'author_account_id, ' . $authorSnapshotColumnSql . $guestAuthorColumnSql . $extraValuesColumnSql . 'title, body_text, body_format, ' . $reactionColumnSql . 'seo_title, seo_description, og_title, og_description, ' . $secretColumnSql . 'status, view_count, last_commented_at, created_at, updated_at)
         VALUES
            (:board_id, ' . $categoryValueSql . ':author_account_id, ' . $authorSnapshotValueSql . $guestAuthorValueSql . $extraValuesValueSql . ':title, :body_text, :body_format, ' . $reactionValueSql . ':seo_title, :seo_description, :og_title, :og_description, ' . $secretValueSql . ':status, 0, NULL, :created_at, :updated_at)'
    );
    $params = [
        'board_id' => $boardId,
        'author_account_id' => $authorAccountId > 0 ? $authorAccountId : null,
        'title' => trim((string) $values['title']),
        'body_text' => $bodyText,
        'body_format' => $bodyFormat,
        'seo_title' => sr_community_seo_text((string) ($values['seo_title'] ?? ''), 160),
        'seo_description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 255),
        'og_title' => sr_community_seo_text((string) ($values['og_title'] ?? ''), 160),
        'og_description' => sr_community_seo_text((string) ($values['og_description'] ?? ''), 255),
        'status' => 'published',
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($categorySupported) {
        $params['category_id'] = (int) ($values['category_id'] ?? 0) > 0 ? (int) $values['category_id'] : null;
    }
    if ($authorSnapshotColumnSql !== '') {
        $params['author_public_name_snapshot'] = $authorAccountId > 0
            ? sr_community_author_public_name_snapshot($pdo, $authorAccountId)
            : sr_community_guest_author_snapshot((string) ($values['guest_author_name'] ?? ''));
    }
    if ($guestAuthorColumnSql !== '') {
        $guestValues = sr_community_guest_author_values_for_storage($values);
        $params['guest_author_name'] = $authorAccountId > 0 ? '' : (string) $guestValues['guest_author_name'];
        $params['guest_password_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_password_hash'];
        $params['guest_ip_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_ip_hash'];
        $params['guest_user_agent_hash'] = $authorAccountId > 0 ? null : $guestValues['guest_user_agent_hash'];
    }
    if ($extraValuesColumnSql !== '') {
        $params['extra_values_json'] = (string) ($values['extra_values_json'] ?? '[]');
    }
    if ($reactionColumnSql !== '') {
        $params['reaction_preset_key'] = '';
        $params['reaction_comment_preset_key'] = '';
    }
    if ($secretColumnSql !== '') {
        $params['is_secret'] = (int) ($values['is_secret'] ?? 0) === 1 ? 1 : 0;
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
            sr_embed_manager_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', $bodyText, $authorAccountId);
        } else {
            sr_embed_manager_sync_body_url_cache($pdo, 'community', 'post', $postId, 'body', '', $authorAccountId);
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
