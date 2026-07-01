<?php

declare(strict_types=1);

function sr_community_optional_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return false;
    }
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        $cache[$tableName] = true;
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function sr_community_optional_count(PDO $pdo, string $tableName, string $whereSql, array $params = []): int
{
    if (!sr_community_optional_table_exists($pdo, $tableName)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_community_count(PDO $pdo, string $tableName, string $whereSql, array $params = []): int
{
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_community_board_reference_counts(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return ['posts' => 0, 'series' => 0, 'attachments' => 0, 'comments' => 0];
    }

    return [
        'posts' => sr_community_count($pdo, 'sr_community_posts', 'board_id = :board_id', ['board_id' => $boardId]),
        'series' => sr_community_count($pdo, 'sr_community_series', 'board_id = :board_id', ['board_id' => $boardId]),
        'attachments' => sr_community_count($pdo, 'sr_community_attachments', 'post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)', ['board_id' => $boardId]),
        'comments' => sr_community_count($pdo, 'sr_community_comments', 'post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)', ['board_id' => $boardId]),
    ];
}

function sr_community_board_external_reference_counts(PDO $pdo, int $boardId): array
{
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return ['site_menu' => 0, 'banner_targets' => 0, 'popup_layer_targets' => 0, 'coupon_targets' => 0];
    }

    $boardKey = (string) ($board['board_key'] ?? '');
    return [
        'site_menu' => $boardKey !== ''
            ? sr_community_optional_count($pdo, 'sr_site_menu_items', 'url = :url', ['url' => '/community/board?key=' . $boardKey])
            : 0,
        'banner_targets' => sr_community_optional_count(
            $pdo,
            'sr_banner_targets',
            "module_key = 'community' AND point_key IN ('community.board.list', 'community.post.form') AND match_type = 'exact' AND subject_id = :subject_id",
            ['subject_id' => $boardId]
        ),
        'popup_layer_targets' => sr_community_optional_count(
            $pdo,
            'sr_popup_layer_targets',
            "module_key = 'community' AND point_key IN ('community.board.list', 'community.post.form') AND match_type = 'exact' AND subject_id = :subject_id",
            ['subject_id' => $boardId]
        ),
        'coupon_targets' => sr_community_optional_count(
            $pdo,
            'sr_coupon_definitions',
            "target_type = 'community_board' AND target_id = :target_id",
            ['target_id' => (string) $boardId]
        ),
    ];
}

function sr_community_board_delete_block_messages(array $references, array $externalReferences): array
{
    $messages = [];
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $messages[] = '사이트 메뉴, 배너/팝업, 쿠폰 등 외부 운영 참조가 있어 삭제할 수 없습니다.';
    }

    return $messages;
}

function sr_community_can_delete_board(PDO $pdo, int $boardId): array
{
    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return ['can_delete' => false, 'errors' => ['게시판을 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_community_board_reference_counts($pdo, $boardId);
    $externalReferences = sr_community_board_external_reference_counts($pdo, $boardId);
    $errors = sr_community_board_delete_block_messages($references, $externalReferences);

    return [
        'can_delete' => $errors === [],
        'errors' => $errors,
        'references' => $references,
        'external_references' => $externalReferences,
        'board' => $board,
    ];
}

function sr_community_delete_board(PDO $pdo, int $boardId): array
{
    $check = sr_community_can_delete_board($pdo, $boardId);
    if (empty($check['can_delete']) || !is_array($check['board'] ?? null)) {
        return $check;
    }

    $attachmentFiles = sr_community_board_attachment_storage_refs($pdo, $boardId);
    $stmt = $pdo->prepare('SELECT id, body_text FROM sr_community_posts WHERE board_id = :board_id');
    $stmt->execute(['board_id' => $boardId]);
    $bodyFileRows = $stmt->fetchAll();
    $bodyFilePostIds = [];
    foreach ($bodyFileRows as $bodyFileRow) {
        if (is_array($bodyFileRow) && (int) ($bodyFileRow['id'] ?? 0) > 0) {
            $bodyFilePostIds[] = (int) $bodyFileRow['id'];
        }
    }
    $pdo->beginTransaction();
    try {
        $deletedSettingSources = sr_community_count($pdo, 'sr_community_board_setting_sources', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedSettings = sr_community_count($pdo, 'sr_community_board_settings', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedBoardManagers = sr_community_count($pdo, 'sr_community_board_managers', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedCategories = sr_community_count($pdo, 'sr_community_categories', 'board_id = :board_id', ['board_id' => $boardId]);
        $deletedPosts = (int) ($check['references']['posts'] ?? 0);
        $deletedComments = (int) ($check['references']['comments'] ?? 0);
        $deletedAttachments = (int) ($check['references']['attachments'] ?? 0);
        $deletedSeries = (int) ($check['references']['series'] ?? 0);

        $pdo->prepare('DELETE FROM sr_community_series_scraps WHERE series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_series_items WHERE series_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        $pdo->prepare("DELETE FROM sr_community_access_entitlements WHERE subject_type IN ('community_post', 'community.post') AND subject_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
        $pdo->prepare("DELETE FROM sr_community_access_entitlements WHERE subject_type IN ('community.attachment', 'community_attachment') AND subject_id IN (SELECT a.id FROM sr_community_attachments a INNER JOIN sr_community_posts p ON p.id = a.post_id WHERE p.board_id = :board_id)")->execute(['board_id' => $boardId]);
        $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'post' AND target_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
        $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'series' AND target_id IN (SELECT id FROM sr_community_series WHERE board_id = :board_id)")->execute(['board_id' => $boardId]);
        $pdo->prepare("DELETE FROM sr_community_reports WHERE target_type = 'comment' AND target_id IN (SELECT c.id FROM sr_community_comments c INNER JOIN sr_community_posts p ON p.id = c.post_id WHERE p.board_id = :board_id)")->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_series WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_scraps WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_attachments WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_comments WHERE post_id IN (SELECT id FROM sr_community_posts WHERE board_id = :board_id)')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_posts WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_board_setting_sources WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_board_settings WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_board_managers WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_categories WHERE board_id = :board_id')->execute(['board_id' => $boardId]);
        $pdo->prepare('DELETE FROM sr_community_boards WHERE id = :id')->execute(['id' => $boardId]);
        $pdo->commit();
        if (function_exists('sr_community_enabled_boards_file_cache_mark_stale')) {
            sr_community_enabled_boards_file_cache_mark_stale();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $failedAttachmentFiles = 0;
    $failedAttachmentFileRefs = [];
    foreach ($attachmentFiles as $attachmentFile) {
        $driver = (string) $attachmentFile['driver'];
        $key = (string) $attachmentFile['key'];
        sr_thumbnail_delete_variants([
            'module_key' => 'community',
            'storage_driver' => $driver,
            'storage_key' => $key,
        ]);
        if (!sr_storage_delete($driver, $key)) {
            $failedAttachmentFiles++;
            $failedAttachmentFileRefs[] = $driver . ':' . $key;
            sr_community_record_storage_cleanup_failure($pdo, 'board_delete_attachment', $boardId, $driver, $key, '게시판 삭제 후 첨부 파일 저장소 정리에 실패했습니다.');
        }
    }
    $deletedBodyFiles = 0;
    foreach ($bodyFileRows as $bodyFileRow) {
        if (is_array($bodyFileRow) && (int) ($bodyFileRow['id'] ?? 0) > 0) {
            $deletedBodyFiles += sr_community_cleanup_body_file_refs_for_deleted_post($pdo, (int) $bodyFileRow['id'], (string) ($bodyFileRow['body_text'] ?? ''));
        }
    }
    $deletedBodyFiles += sr_community_cleanup_body_files_for_deleted_posts($pdo, $bodyFilePostIds);

    $check['deleted_settings'] = $deletedSettings;
    $check['deleted_setting_sources'] = $deletedSettingSources;
    $check['deleted_board_managers'] = $deletedBoardManagers;
    $check['deleted_categories'] = $deletedCategories;
    $check['deleted_posts'] = $deletedPosts;
    $check['deleted_comments'] = $deletedComments;
    $check['deleted_attachments'] = $deletedAttachments;
    $check['deleted_attachment_files'] = count($attachmentFiles) - $failedAttachmentFiles;
    $check['deleted_body_files'] = $deletedBodyFiles;
    $check['failed_attachment_files'] = $failedAttachmentFiles;
    $check['failed_attachment_file_refs'] = $failedAttachmentFileRefs;
    $check['deleted_series'] = $deletedSeries;
    return $check;
}

function sr_community_record_storage_cleanup_failure(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key, string $errorMessage): void
{
    if (!sr_storage_key_is_safe($key)) {
        return;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_community_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, \'pending\', 1, :last_error, :created_at, :updated_at)'
        );
        $stmt->execute([
            'source_type' => sr_community_clean_key($sourceType),
            'source_id' => $sourceId,
            'storage_driver' => $driver,
            'storage_key' => $key,
            'last_error' => sr_community_clean_cleanup_error($errorMessage),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'community_storage_cleanup_failure_record_failed');
    }
}

function sr_community_storage_cleanup_failures(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_storage_cleanup_failures
         WHERE status = 'pending'
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_community_retry_storage_cleanup_failure(PDO $pdo, int $failureId): array
{
    if ($failureId < 1) {
        return ['ok' => false, 'message' => '저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM sr_community_storage_cleanup_failures WHERE id = :id AND status = 'pending' LIMIT 1");
    $stmt->execute(['id' => $failureId]);
    $failure = $stmt->fetch();
    if (!is_array($failure)) {
        return ['ok' => false, 'message' => '재시도할 저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $driver = (string) ($failure['storage_driver'] ?? 'local');
    $key = (string) ($failure['storage_key'] ?? '');
    $now = sr_now();
    if ($key !== '' && sr_storage_delete($driver, $key)) {
        $stmt = $pdo->prepare(
            "UPDATE sr_community_storage_cleanup_failures
             SET status = 'cleaned',
                 attempt_count = attempt_count + 1,
                 last_error = '',
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute(['updated_at' => $now, 'id' => $failureId]);

        return ['ok' => true, 'message' => '저장소 파일 정리를 완료했습니다.'];
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_storage_cleanup_failures
         SET attempt_count = attempt_count + 1,
             last_error = :last_error,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'last_error' => '저장소 파일 정리 재시도에 실패했습니다.',
        'updated_at' => $now,
        'id' => $failureId,
    ]);

    return ['ok' => false, 'message' => '저장소 파일 정리 재시도에 실패했습니다. 저장소 권한 또는 S3 설정을 확인해 주세요.'];
}

function sr_community_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? substr($value, 0, 60) : 'unknown';
}

function sr_community_clean_cleanup_error(string $value): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, 1000);
    }

    return substr($value, 0, 1000);
}

function sr_community_board_attachment_storage_refs(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT a.*
         FROM sr_community_attachments a
         INNER JOIN sr_community_posts p ON p.id = a.post_id
         WHERE p.board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);
    $refs = [];
    foreach ($stmt->fetchAll() as $attachment) {
        $driver = strtolower((string) ($attachment['storage_driver'] ?? 'local'));
        $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
        $key = function_exists('sr_community_attachment_storage_key')
            ? sr_community_attachment_storage_key($attachment)
            : (string) ($attachment['storage_key'] ?? '');
        if ($key !== '' && sr_storage_key_is_safe($key)) {
            $refs[$driver . ':' . $key] = ['driver' => $driver, 'key' => $key];
        }
    }

    return array_values($refs);
}

function sr_community_board_group_reference_counts(PDO $pdo, int $groupId): array
{
    return [
        'boards' => $groupId > 0 ? sr_community_count($pdo, 'sr_community_boards', 'board_group_id = :group_id', ['group_id' => $groupId]) : 0,
    ];
}

function sr_community_board_group_external_reference_counts(PDO $pdo, int $groupId): array
{
    $group = sr_community_board_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['site_menu' => 0];
    }

    $groupKey = (string) ($group['group_key'] ?? '');
    return [
        'site_menu' => $groupKey !== ''
            ? sr_community_optional_count($pdo, 'sr_site_menu_items', 'url IN (:url, :legacy_url)', [
                'url' => sr_community_board_group_path($groupKey),
                'legacy_url' => '/community#group-' . $groupKey,
            ])
            : 0,
    ];
}

function sr_community_can_delete_board_group(PDO $pdo, int $groupId): array
{
    $group = sr_community_board_group_by_id($pdo, $groupId);
    if (!is_array($group)) {
        return ['can_delete' => false, 'errors' => ['게시판 그룹을 찾을 수 없습니다.'], 'references' => [], 'external_references' => []];
    }

    $references = sr_community_board_group_reference_counts($pdo, $groupId);
    $externalReferences = sr_community_board_group_external_reference_counts($pdo, $groupId);
    $errors = [];
    if (array_sum(array_map('intval', $externalReferences)) > 0) {
        $errors[] = '외부 운영 참조가 있어 게시판 그룹을 삭제할 수 없습니다.';
    }

    return ['can_delete' => $errors === [], 'errors' => $errors, 'references' => $references, 'external_references' => $externalReferences, 'group' => $group];
}

function sr_community_delete_board_group(PDO $pdo, int $groupId): array
{
    $check = sr_community_can_delete_board_group($pdo, $groupId);
    if (empty($check['can_delete']) || !is_array($check['group'] ?? null)) {
        return $check;
    }

    $pdo->beginTransaction();
    try {
        $deletedSettings = sr_community_count($pdo, 'sr_community_board_group_settings', 'group_id = :group_id', ['group_id' => $groupId]);
        $detachedBoards = (int) ($check['references']['boards'] ?? 0);
        $pdo->prepare('UPDATE sr_community_boards SET board_group_id = NULL, updated_at = :updated_at WHERE board_group_id = :group_id')->execute([
            'updated_at' => sr_now(),
            'group_id' => $groupId,
        ]);
        $pdo->prepare('DELETE FROM sr_community_board_group_settings WHERE group_id = :group_id')->execute(['group_id' => $groupId]);
        $pdo->prepare('DELETE FROM sr_community_board_groups WHERE id = :id')->execute(['id' => $groupId]);
        $pdo->commit();
        if (function_exists('sr_community_enabled_boards_file_cache_mark_stale')) {
            sr_community_enabled_boards_file_cache_mark_stale();
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $check['deleted_settings'] = $deletedSettings;
    $check['detached_boards'] = $detachedBoards;
    return $check;
}
