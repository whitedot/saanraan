<?php

declare(strict_types=1);

function sr_community_post_drafts_table_exists(PDO $pdo): bool
{
    try {
        return $pdo->query('SELECT 1 FROM sr_community_post_drafts LIMIT 1') !== false;
    } catch (Throwable) {
        return false;
    }
}

function sr_community_draft_autosave_enabled(array $settings): bool
{
    return !empty($settings['draft_autosave_enabled']);
}

function sr_community_draft_autosave_interval_seconds(array $settings): int
{
    return min(600, max(30, (int) ($settings['draft_autosave_interval_seconds'] ?? 60)));
}

function sr_community_draft_retention_days(array $settings): int
{
    return min(30, max(1, (int) ($settings['draft_retention_days'] ?? 7)));
}

function sr_community_draft_max_count_per_account(array $settings): int
{
    return min(100, max(1, (int) ($settings['draft_max_count_per_account'] ?? 20)));
}

function sr_community_draft_mode(string $mode): string
{
    return $mode === 'edit' ? 'edit' : 'create';
}

function sr_community_draft_context_hash(int $accountId, int $boardId, string $mode, int $postId = 0): string
{
    $mode = sr_community_draft_mode($mode);
    $postId = $mode === 'edit' ? max(0, $postId) : 0;

    return hash('sha256', 'community.post_draft|' . (string) max(0, $accountId) . '|' . (string) max(0, $boardId) . '|' . $mode . '|' . (string) $postId);
}

function sr_community_draft_content_hash(array $values): string
{
    $payload = [
        'title' => (string) ($values['title'] ?? ''),
        'body_format' => (string) ($values['body_format'] ?? 'plain'),
        'body_text' => (string) ($values['body_text'] ?? ''),
        'category_id' => (int) ($values['category_id'] ?? 0),
        'extra_values_json' => (string) ($values['extra_values_json'] ?? '[]'),
    ];

    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
}

function sr_community_draft_content_hash_for_post(PDO $pdo, array $post): string
{
    $settings = function_exists('sr_community_settings') ? sr_community_settings($pdo) : [];
    return sr_community_draft_content_hash([
        'title' => (string) ($post['title'] ?? ''),
        'body_format' => function_exists('sr_community_post_body_format') ? sr_community_post_body_format($pdo, $post, $settings) : 'plain',
        'body_text' => (string) ($post['body_text'] ?? ''),
        'category_id' => (int) ($post['category_id'] ?? 0),
        'extra_values_json' => (string) ($post['extra_values_json'] ?? '[]'),
    ]);
}

function sr_community_draft_body_tmp_ref_count(string $bodyText): int
{
    if (!function_exists('sr_community_body_file_refs_from_html')) {
        return 0;
    }

    $count = 0;
    foreach (sr_community_body_file_refs_from_html($bodyText) as $ref) {
        if ((string) ($ref['type'] ?? '') === 'tmp') {
            $count++;
        }
    }

    return $count;
}

function sr_community_draft_body_text_for_restore(string $bodyText, string $bodyFormat): array
{
    if ($bodyText === '' || $bodyFormat !== 'html' || !class_exists('DOMDocument') || !function_exists('sr_community_body_file_ref_from_url')) {
        return ['body_text' => $bodyText, 'tmp_refs_removed' => 0];
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div>' . $bodyText . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return ['body_text' => $bodyText, 'tmp_refs_removed' => 0];
    }

    $removed = 0;
    $images = [];
    foreach ($document->getElementsByTagName('img') as $image) {
        if ($image instanceof DOMElement) {
            $images[] = $image;
        }
    }
    foreach ($images as $image) {
        $ref = sr_community_body_file_ref_from_url($image->getAttribute('src'));
        if (!is_array($ref) || (string) ($ref['type'] ?? '') !== 'tmp') {
            continue;
        }
        if (function_exists('sr_community_body_file_token_is_valid') && sr_community_body_file_token_is_valid((string) ($ref['token'] ?? ''))) {
            continue;
        }
        $placeholder = $document->createElement('p', '[임시 이미지는 다시 업로드해 주세요.]');
        $image->parentNode?->replaceChild($placeholder, $image);
        $removed++;
    }
    if ($removed < 1) {
        return ['body_text' => $bodyText, 'tmp_refs_removed' => 0];
    }

    $root = $document->getElementsByTagName('div')->item(0);
    $html = '';
    if ($root instanceof DOMElement) {
        foreach ($root->childNodes as $childNode) {
            $childHtml = $document->saveHTML($childNode);
            if (is_string($childHtml)) {
                $html .= $childHtml;
            }
        }
    } else {
        $saved = $document->saveHTML();
        $html = is_string($saved) ? (string) preg_replace('/^<\?xml encoding="UTF-8">\s*/', '', $saved) : $bodyText;
    }

    return ['body_text' => $html !== '' ? $html : $bodyText, 'tmp_refs_removed' => $removed];
}

function sr_community_draft_form_state_json(array $state): string
{
    $payload = [
        'category_id' => (int) ($state['category_id'] ?? 0),
        'is_secret' => !empty($state['is_secret']) ? 1 : 0,
        'extra_field_values' => is_array($state['extra_field_values'] ?? null) ? $state['extra_field_values'] : [],
        'series_values' => is_array($state['series_values'] ?? null) ? $state['series_values'] : [],
    ];

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
}

function sr_community_draft_form_state(string $json): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }

    return [
        'category_id' => (int) ($decoded['category_id'] ?? 0),
        'is_secret' => !empty($decoded['is_secret']) ? 1 : 0,
        'extra_field_values' => is_array($decoded['extra_field_values'] ?? null) ? $decoded['extra_field_values'] : [],
        'series_values' => is_array($decoded['series_values'] ?? null) ? $decoded['series_values'] : [],
    ];
}

function sr_community_draft_upsert(PDO $pdo, array $draft, array $settings): array
{
    if (!sr_community_post_drafts_table_exists($pdo)) {
        return ['saved' => false, 'reason' => 'schema_missing'];
    }

    $accountId = (int) ($draft['account_id'] ?? 0);
    $boardId = (int) ($draft['board_id'] ?? 0);
    $mode = sr_community_draft_mode((string) ($draft['draft_mode'] ?? 'create'));
    $postId = $mode === 'edit' ? max(1, (int) ($draft['post_id'] ?? 0)) : 0;
    if ($accountId < 1 || $boardId < 1) {
        return ['saved' => false, 'reason' => 'invalid_context'];
    }

    $now = sr_now();
    $contextHash = sr_community_draft_context_hash($accountId, $boardId, $mode, $postId);
    $isSqlite = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    $sql = $isSqlite
        ? 'INSERT INTO sr_community_post_drafts
              (account_id, board_id, draft_mode, post_id, context_hash, base_content_hash, title, body_format, body_text, form_state_json, body_tmp_ref_count, last_saved_at, created_at, updated_at)
           VALUES
              (:account_id, :board_id, :draft_mode, :post_id, :context_hash, :base_content_hash, :title, :body_format, :body_text, :form_state_json, :body_tmp_ref_count, :last_saved_at, :created_at, :updated_at)
           ON CONFLICT(context_hash) DO UPDATE SET
              base_content_hash = excluded.base_content_hash,
              title = excluded.title,
              body_format = excluded.body_format,
              body_text = excluded.body_text,
              form_state_json = excluded.form_state_json,
              body_tmp_ref_count = excluded.body_tmp_ref_count,
              last_saved_at = excluded.last_saved_at,
              updated_at = excluded.updated_at'
        : 'INSERT INTO sr_community_post_drafts
              (account_id, board_id, draft_mode, post_id, context_hash, base_content_hash, title, body_format, body_text, form_state_json, body_tmp_ref_count, last_saved_at, created_at, updated_at)
           VALUES
              (:account_id, :board_id, :draft_mode, :post_id, :context_hash, :base_content_hash, :title, :body_format, :body_text, :form_state_json, :body_tmp_ref_count, :last_saved_at, :created_at, :updated_at)
           ON DUPLICATE KEY UPDATE
              base_content_hash = VALUES(base_content_hash),
              title = VALUES(title),
              body_format = VALUES(body_format),
              body_text = VALUES(body_text),
              form_state_json = VALUES(form_state_json),
              body_tmp_ref_count = VALUES(body_tmp_ref_count),
              last_saved_at = VALUES(last_saved_at),
              updated_at = VALUES(updated_at)';
    $stmt = $pdo->prepare($sql);
    $bodyFormat = (string) ($draft['body_format'] ?? 'plain');
    $bodyFormat = in_array($bodyFormat, ['plain', 'html', 'markdown'], true) ? $bodyFormat : 'plain';
    $stmt->execute([
        'account_id' => $accountId,
        'board_id' => $boardId,
        'draft_mode' => $mode,
        'post_id' => $postId > 0 ? $postId : null,
        'context_hash' => $contextHash,
        'base_content_hash' => (string) ($draft['base_content_hash'] ?? ''),
        'title' => (string) ($draft['title'] ?? ''),
        'body_format' => $bodyFormat,
        'body_text' => (string) ($draft['body_text'] ?? ''),
        'form_state_json' => (string) ($draft['form_state_json'] ?? '{}'),
        'body_tmp_ref_count' => max(0, (int) ($draft['body_tmp_ref_count'] ?? 0)),
        'last_saved_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_community_draft_trim_account($pdo, $accountId, sr_community_draft_max_count_per_account($settings));

    return ['saved' => true, 'context_hash' => $contextHash, 'last_saved_at' => $now];
}

function sr_community_draft_trim_account(PDO $pdo, int $accountId, int $maxCount): int
{
    if ($accountId < 1 || $maxCount < 1 || !sr_community_post_drafts_table_exists($pdo)) {
        return 0;
    }

    $limitSql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? 'LIMIT -1 OFFSET ' . (int) $maxCount
        : 'LIMIT 18446744073709551615 OFFSET ' . (int) $maxCount;
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_post_drafts
         WHERE account_id = :account_id
           AND id IN (
               SELECT id FROM (
                   SELECT id
                   FROM sr_community_post_drafts
                   WHERE account_id = :trim_account_id
                   ORDER BY updated_at DESC, id DESC
                   ' . $limitSql . '
               ) AS trim_targets
           )'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'trim_account_id' => $accountId,
    ]);

    return $stmt->rowCount();
}

function sr_community_draft_fetch(PDO $pdo, int $accountId, int $boardId, string $mode, int $postId = 0): ?array
{
    if ($accountId < 1 || $boardId < 1 || !sr_community_post_drafts_table_exists($pdo)) {
        return null;
    }

    $contextHash = sr_community_draft_context_hash($accountId, $boardId, $mode, $postId);
    $stmt = $pdo->prepare('SELECT * FROM sr_community_post_drafts WHERE context_hash = :context_hash AND account_id = :account_id LIMIT 1');
    $stmt->execute([
        'context_hash' => $contextHash,
        'account_id' => $accountId,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

function sr_community_draft_delete(PDO $pdo, int $accountId, int $boardId, string $mode, int $postId = 0): int
{
    if ($accountId < 1 || $boardId < 1 || !sr_community_post_drafts_table_exists($pdo)) {
        return 0;
    }

    $stmt = $pdo->prepare('DELETE FROM sr_community_post_drafts WHERE context_hash = :context_hash AND account_id = :account_id');
    $stmt->execute([
        'context_hash' => sr_community_draft_context_hash($accountId, $boardId, $mode, $postId),
        'account_id' => $accountId,
    ]);

    return $stmt->rowCount();
}

function sr_community_draft_cleanup(PDO $pdo, array $settings, int $limit = 50): int
{
    if (!sr_community_post_drafts_table_exists($pdo)) {
        return 0;
    }

    $limit = max(1, min(200, $limit));
    $cutoff = date('Y-m-d H:i:s', time() - sr_community_draft_retention_days($settings) * 86400);
    $stmt = $pdo->prepare(
        'DELETE FROM sr_community_post_drafts
         WHERE id IN (
             SELECT id FROM (
                 SELECT id
                 FROM sr_community_post_drafts
                 WHERE updated_at < :cutoff
                 ORDER BY updated_at ASC, id ASC
                 LIMIT ' . $limit . '
             ) AS cleanup_targets
         )'
    );
    $stmt->execute(['cutoff' => $cutoff]);

    return $stmt->rowCount();
}

function sr_community_draft_restore_payload(?array $draft, ?string $currentContentHash = null): array
{
    if (!is_array($draft)) {
        return [];
    }

    $conflict = $currentContentHash !== null
        && (string) ($draft['base_content_hash'] ?? '') !== ''
        && !hash_equals((string) ($draft['base_content_hash'] ?? ''), $currentContentHash);
    $bodyFormat = (string) ($draft['body_format'] ?? 'plain');
    $bodyRestore = sr_community_draft_body_text_for_restore((string) ($draft['body_text'] ?? ''), $bodyFormat);
    $state = sr_community_draft_form_state((string) ($draft['form_state_json'] ?? '{}'));

    return [
        'id' => (int) ($draft['id'] ?? 0),
        'conflict' => $conflict,
        'title' => (string) ($draft['title'] ?? ''),
        'body_format' => $bodyFormat,
        'body_text' => (string) ($bodyRestore['body_text'] ?? ''),
        'category_id' => (int) ($state['category_id'] ?? 0),
        'is_secret' => (int) ($state['is_secret'] ?? 0),
        'extra_field_values' => is_array($state['extra_field_values'] ?? null) ? $state['extra_field_values'] : [],
        'series_values' => is_array($state['series_values'] ?? null) ? $state['series_values'] : [],
        'body_tmp_ref_count' => (int) ($draft['body_tmp_ref_count'] ?? 0),
        'body_tmp_refs_removed' => (int) ($bodyRestore['tmp_refs_removed'] ?? 0),
        'last_saved_at' => (string) ($draft['last_saved_at'] ?? ''),
    ];
}
