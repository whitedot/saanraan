#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/content/helpers.php';

$errors = [];

function sr_content_file_cleanup_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_content_file_cleanup_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_content_file_cleanup_error($message);
    }
}

function sr_content_file_cleanup_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_content_file_cleanup_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_content_file_cleanup_put(string $key, string $body): string
{
    if (!sr_storage_key_is_safe($key)) {
        throw new RuntimeException('fixture storage path unavailable: ' . $key);
    }
    $path = SR_ROOT . '/storage/' . str_replace('/', DIRECTORY_SEPARATOR, $key);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('fixture storage directory unavailable: ' . $directory);
    }
    file_put_contents($path, $body);

    return $path;
}

function sr_content_file_cleanup_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_site_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string")');
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL DEFAULT "", display_name TEXT NOT NULL DEFAULT "")');
    $pdo->exec('CREATE TABLE sr_content_items (
        id INTEGER PRIMARY KEY,
        title TEXT NOT NULL,
        slug TEXT NOT NULL,
        summary TEXT NOT NULL DEFAULT "",
        cover_image_url TEXT NOT NULL DEFAULT "",
        body_text TEXT NOT NULL DEFAULT "",
        body_format TEXT NOT NULL DEFAULT "plain",
        status TEXT NOT NULL,
        content_group_id INTEGER,
        layout_key TEXT NOT NULL DEFAULT "",
        asset_access_enabled INTEGER NOT NULL DEFAULT 0,
        asset_module TEXT NOT NULL DEFAULT "",
        asset_access_amount INTEGER NOT NULL DEFAULT 0,
        asset_access_settlement_currency TEXT NOT NULL DEFAULT "KRW",
        asset_access_amounts_json TEXT NOT NULL DEFAULT "{}",
        asset_access_group_policies_json TEXT NOT NULL DEFAULT "",
        asset_access_policy_set_id INTEGER NOT NULL DEFAULT 0,
        asset_charge_policy TEXT NOT NULL DEFAULT "once",
        asset_action_enabled INTEGER NOT NULL DEFAULT 0,
        asset_action_module TEXT NOT NULL DEFAULT "",
        asset_action_amount INTEGER NOT NULL DEFAULT 0,
        asset_action_settlement_currency TEXT NOT NULL DEFAULT "KRW",
        asset_action_amounts_json TEXT NOT NULL DEFAULT "{}",
        asset_action_group_policies_json TEXT NOT NULL DEFAULT "",
        asset_action_policy_set_id INTEGER NOT NULL DEFAULT 0,
        asset_action_direction TEXT NOT NULL DEFAULT "grant",
        asset_action_label TEXT NOT NULL DEFAULT "완료",
        banner_before_content_id INTEGER NOT NULL DEFAULT 0,
        banner_after_content_id INTEGER NOT NULL DEFAULT 0,
        popup_layer_id INTEGER NOT NULL DEFAULT 0,
        seo_title TEXT NOT NULL DEFAULT "",
        seo_description TEXT NOT NULL DEFAULT "",
        created_by INTEGER,
        updated_by INTEGER,
        published_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_revisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        content_group_id INTEGER,
        title TEXT NOT NULL,
        summary TEXT NOT NULL DEFAULT "",
        cover_image_url TEXT NOT NULL DEFAULT "",
        body_text TEXT NOT NULL DEFAULT "",
        body_format TEXT NOT NULL DEFAULT "plain",
        status TEXT NOT NULL,
        layout_key TEXT NOT NULL DEFAULT "",
        asset_access_enabled INTEGER NOT NULL DEFAULT 0,
        asset_module TEXT NOT NULL DEFAULT "",
        asset_access_amount INTEGER NOT NULL DEFAULT 0,
        asset_access_settlement_currency TEXT NOT NULL DEFAULT "KRW",
        asset_access_amounts_json TEXT NOT NULL DEFAULT "{}",
        asset_access_group_policies_json TEXT NOT NULL DEFAULT "",
        asset_access_policy_set_id INTEGER NOT NULL DEFAULT 0,
        asset_charge_policy TEXT NOT NULL DEFAULT "once",
        asset_action_enabled INTEGER NOT NULL DEFAULT 0,
        asset_action_module TEXT NOT NULL DEFAULT "",
        asset_action_amount INTEGER NOT NULL DEFAULT 0,
        asset_action_settlement_currency TEXT NOT NULL DEFAULT "KRW",
        asset_action_amounts_json TEXT NOT NULL DEFAULT "{}",
        asset_action_group_policies_json TEXT NOT NULL DEFAULT "",
        asset_action_policy_set_id INTEGER NOT NULL DEFAULT 0,
        asset_action_direction TEXT NOT NULL DEFAULT "grant",
        asset_action_label TEXT NOT NULL DEFAULT "완료",
        banner_before_content_id INTEGER NOT NULL DEFAULT 0,
        banner_after_content_id INTEGER NOT NULL DEFAULT 0,
        popup_layer_id INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_files (
        id INTEGER PRIMARY KEY,
        content_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        original_name TEXT NOT NULL,
        stored_name TEXT NOT NULL DEFAULT "",
        storage_path TEXT NOT NULL DEFAULT "",
        storage_driver TEXT NOT NULL DEFAULT "local",
        storage_key TEXT NOT NULL DEFAULT "",
        mime_type TEXT NOT NULL DEFAULT "text/plain",
        size_bytes INTEGER NOT NULL DEFAULT 0,
        checksum_sha256 TEXT NOT NULL DEFAULT "",
        status TEXT NOT NULL DEFAULT "active",
        asset_download_enabled INTEGER NOT NULL DEFAULT 0,
        asset_module TEXT NOT NULL DEFAULT "",
        asset_download_amount INTEGER NOT NULL DEFAULT 0,
        asset_download_amounts_json TEXT NOT NULL DEFAULT "{}",
        asset_download_group_policies_json TEXT NOT NULL DEFAULT "",
        asset_download_policy_set_id INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_file_links (id INTEGER PRIMARY KEY AUTOINCREMENT, content_id INTEGER NOT NULL, file_id INTEGER NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT "active", updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_file_download_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        content_title_snapshot TEXT NOT NULL DEFAULT "",
        content_slug_snapshot TEXT NOT NULL DEFAULT "",
        file_id INTEGER NOT NULL,
        file_title_snapshot TEXT NOT NULL DEFAULT "",
        file_original_name_snapshot TEXT NOT NULL DEFAULT "",
        account_id INTEGER,
        download_type TEXT NOT NULL DEFAULT "free",
        charge_policy TEXT NOT NULL DEFAULT "once",
        asset_module TEXT NOT NULL DEFAULT "",
        amount INTEGER NOT NULL DEFAULT 0,
        asset_access_log_ids_json TEXT,
        refund_status TEXT NOT NULL DEFAULT "",
        refund_transaction_ids_json TEXT,
        refund_note TEXT NOT NULL DEFAULT "",
        refunded_by_account_id INTEGER,
        refunded_at TEXT,
        access_revoked_at TEXT,
        created_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_series (
        id INTEGER PRIMARY KEY,
        series_key TEXT NOT NULL,
        title TEXT NOT NULL,
        description TEXT,
        status TEXT NOT NULL DEFAULT "active",
        visibility TEXT NOT NULL DEFAULT "public",
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER,
        updated_by INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_content_series_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        series_id INTEGER NOT NULL,
        content_id INTEGER NOT NULL,
        active_content_id INTEGER,
        episode_label TEXT NOT NULL DEFAULT "",
        item_status TEXT NOT NULL DEFAULT "active",
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_by INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec('CREATE TABLE sr_module_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string", created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(module_id, setting_key))');
    $pdo->exec('CREATE TABLE sr_url_embed_cache (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        owner_module TEXT NOT NULL,
        owner_type TEXT NOT NULL,
        owner_id INTEGER NOT NULL,
        owner_field TEXT NOT NULL DEFAULT "body",
        source_url TEXT NOT NULL,
        canonical_url TEXT NOT NULL,
        canonical_url_hash TEXT NOT NULL,
        embed_kind TEXT NOT NULL DEFAULT "internal_url",
        provider_key TEXT NOT NULL DEFAULT "",
        render_variant TEXT NOT NULL DEFAULT "summary",
        target_module TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        target_cache_version TEXT NOT NULL DEFAULT "",
        label_snapshot TEXT NOT NULL DEFAULT "",
        image_snapshot TEXT NOT NULL DEFAULT "",
        image_snapshot_policy TEXT NOT NULL DEFAULT "none",
        summary_snapshot TEXT,
        target_state TEXT NOT NULL DEFAULT "",
        resolver_state TEXT NOT NULL DEFAULT "",
        cache_status TEXT NOT NULL DEFAULT "fresh",
        resolved_payload_json TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_by_account_id INTEGER,
        last_resolved_at TEXT,
        last_render_checked_at TEXT,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(owner_module, owner_type, owner_id, owner_field, canonical_url_hash)
    )');
    $pdo->exec('CREATE TABLE sr_content_storage_cleanup_failures (id INTEGER PRIMARY KEY AUTOINCREMENT, source_type TEXT NOT NULL, source_id INTEGER NOT NULL, storage_driver TEXT NOT NULL, storage_key TEXT NOT NULL, error_message TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending", created_at TEXT NOT NULL, resolved_at TEXT)');
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'content', 'enabled')");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_content_file_cleanup_schema($pdo);

$contentId = 97001;
$downloadFileId = 97011;
$sharedFileId = 97012;
$now = '2026-06-11 00:00:00';
$canWriteDownloadStorage = is_writable($root . '/storage/content/files');
$canWriteBodyStorage = is_dir($root . '/storage/content/body') && is_writable($root . '/storage/content/body');
$downloadKey = $canWriteDownloadStorage ? 'content/files/runtime-cleanup/delete-me.txt' : '';
$sharedKey = $canWriteDownloadStorage ? 'content/files/runtime-cleanup/shared.txt' : '';
$bodyKey = $canWriteBodyStorage ? 'content/body/' . (string) $contentId . '/body-delete.png' : '';
$downloadPath = $downloadKey !== '' ? sr_content_file_cleanup_put($downloadKey, 'paid download file') : '';
$sharedPath = $sharedKey !== '' ? sr_content_file_cleanup_put($sharedKey, 'shared file') : '';
$bodyPath = $bodyKey !== '' ? sr_content_file_cleanup_put($bodyKey, 'body image') : '';

$pdo->prepare('INSERT INTO sr_content_items (id, title, slug, summary, body_text, body_format, status, created_by, updated_by, created_at, updated_at) VALUES (:id, :title, :slug, :summary, :body_text, :body_format, :status, 1, 1, :created_at, :updated_at)')->execute([
    'id' => $contentId,
    'title' => 'Runtime cleanup content',
    'slug' => 'runtime-cleanup-content',
    'summary' => 'summary',
    'body_text' => '<p><img src="/content/body-file?content_id=' . (string) $contentId . '&file=body-delete.png"></p>',
    'body_format' => 'html',
    'status' => 'published',
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_revisions (content_id, title, summary, body_text, body_format, status, created_by, created_at) VALUES (:content_id, :title, :summary, :body_text, :body_format, :status, 1, :created_at)')->execute([
    'content_id' => $contentId,
    'title' => 'Runtime cleanup content',
    'summary' => 'summary',
    'body_text' => 'revision body',
    'body_format' => 'html',
    'status' => 'published',
    'created_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_files (id, content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount, created_at, updated_at) VALUES (:id, :content_id, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, :status, 1, "point", 100, :created_at, :updated_at)')->execute([
    'id' => $downloadFileId,
    'content_id' => $contentId,
    'title' => 'Download file',
    'original_name' => 'download.txt',
    'stored_name' => 'download.txt',
    'storage_path' => $downloadKey !== '' ? 'storage/' . $downloadKey : '',
    'storage_driver' => 'local',
    'storage_key' => $downloadKey,
    'mime_type' => 'text/plain',
    'size_bytes' => $downloadPath !== '' ? filesize($downloadPath) : 12,
    'checksum_sha256' => $downloadPath !== '' ? hash_file('sha256', $downloadPath) : str_repeat('a', 64),
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_files (id, content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, created_at, updated_at) VALUES (:id, 0, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, :status, :created_at, :updated_at)')->execute([
    'id' => $sharedFileId,
    'title' => 'Shared file',
    'original_name' => 'shared.txt',
    'stored_name' => 'shared.txt',
    'storage_path' => $sharedKey !== '' ? 'storage/' . $sharedKey : '',
    'storage_driver' => 'local',
    'storage_key' => $sharedKey,
    'mime_type' => 'text/plain',
    'size_bytes' => $sharedPath !== '' ? filesize($sharedPath) : 11,
    'checksum_sha256' => $sharedPath !== '' ? hash_file('sha256', $sharedPath) : str_repeat('b', 64),
    'status' => 'active',
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_file_links (content_id, file_id, sort_order, status, updated_at) VALUES (:content_id, :file_id, 1, "active", :updated_at)')->execute(['content_id' => $contentId, 'file_id' => $downloadFileId, 'updated_at' => $now]);
$pdo->prepare('INSERT INTO sr_content_file_links (content_id, file_id, sort_order, status, updated_at) VALUES (:content_id, :file_id, 2, "active", :updated_at)')->execute(['content_id' => $contentId, 'file_id' => $sharedFileId, 'updated_at' => $now]);
$pdo->prepare('INSERT INTO sr_content_file_download_logs (content_id, content_title_snapshot, content_slug_snapshot, file_id, file_title_snapshot, file_original_name_snapshot, download_type, charge_policy, asset_module, amount, asset_access_log_ids_json, refund_status, refund_transaction_ids_json, refund_note, created_at) VALUES (:content_id, "Runtime cleanup content", "runtime-cleanup-content", :file_id, "Download file", "download.txt", "free", "once", "", 0, "[]", "", "[]", "", :created_at)')->execute(['content_id' => $contentId, 'file_id' => $downloadFileId, 'created_at' => $now]);
$downloadLogFilters = [
    'content_id' => 0,
    'file_id' => 0,
    'account_id' => 0,
    'download_type' => ['free'],
    'refund_status' => ['none'],
    'date_from' => '',
    'date_to' => '',
    'q' => '',
];
sr_content_file_cleanup_assert(sr_content_admin_file_download_log_count($pdo, $downloadLogFilters) === 1, 'content file download admin list should count current download log rows.');
$downloadLogs = sr_content_admin_file_download_logs($pdo, $downloadLogFilters, 20, 0, sr_admin_sort_default('amount', 'desc'));
sr_content_file_cleanup_assert(count($downloadLogs) === 1, 'content file download admin list should read current download log rows.');
sr_content_file_cleanup_assert((string) ($downloadLogs[0]['download_type'] ?? '') === 'free', 'content file download admin list should read download type.');
sr_content_file_cleanup_assert((string) ($downloadLogs[0]['refund_status'] ?? '') === '', 'content file download admin list should read empty refund status.');
sr_content_file_cleanup_assert(sr_content_admin_file_download_log_count($pdo, ['download_type' => ['paid'], 'refund_status' => ['refunded']]) === 0, 'content file download admin list should not match paid/refunded filters on current free rows.');
$pdo->prepare('INSERT INTO sr_content_series (id, series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at) VALUES (1, "runtime_series", "Runtime series", "", "active", "public", 1, 1, 1, :created_at, :updated_at)')->execute(['created_at' => $now, 'updated_at' => $now]);
$pdo->prepare('INSERT INTO sr_content_series_items (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at) VALUES (1, :content_id, :active_content_id, "1화", "active", 1, 1, :created_at, :updated_at)')->execute([
    'content_id' => $contentId,
    'active_content_id' => $contentId,
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_url_embed_cache (owner_module, owner_type, owner_id, owner_field, source_url, canonical_url, canonical_url_hash, target_module, target_type, target_id, label_snapshot, target_state, cache_status, created_by_account_id, created_at, updated_at) VALUES ("content", "content", :owner_id, "body", "/content/embedded", "/content/embedded", :hash, "content", "content", "123", "Embedded content", "public", "fresh", 1, :created_at, :updated_at)')->execute([
    'owner_id' => $contentId,
    'hash' => hash('sha256', '/content/embedded'),
    'created_at' => $now,
    'updated_at' => $now,
]);

$result = sr_content_delete_redacted($pdo, $contentId, 1);
sr_content_file_cleanup_assert(!empty($result['deleted']), 'content file cleanup fixture should delete the content row.');
if ($canWriteDownloadStorage) {
    sr_content_file_cleanup_assert((int) ($result['files_deleted'] ?? -1) === 2, 'content file cleanup fixture should delete owned storage files.');
    sr_content_file_cleanup_assert(!is_file($downloadPath), 'content file cleanup fixture should remove the download storage file.');
    sr_content_file_cleanup_assert(!is_file($sharedPath), 'content file cleanup fixture should remove the exclusively linked shared storage file.');
} else {
    sr_content_file_cleanup_assert((int) ($result['files_deleted'] ?? -1) === 0, 'content file cleanup fixture should skip physical file deletion when storage is not writable.');
}
if ($canWriteBodyStorage) {
    sr_content_file_cleanup_assert((int) ($result['body_files_deleted'] ?? -1) === 1, 'content file cleanup fixture should delete stored body files.');
    sr_content_file_cleanup_assert(!is_file($bodyPath), 'content file cleanup fixture should remove the body image file.');
} else {
    sr_content_file_cleanup_assert((int) ($result['body_files_deleted'] ?? -1) === 0, 'content file cleanup fixture should skip physical body file deletion when storage is not writable.');
}

$content = sr_content_file_cleanup_row($pdo, 'SELECT title, summary, body_text, body_format, status, asset_access_enabled, asset_module, asset_access_amount FROM sr_content_items WHERE id = :id', ['id' => $contentId]);
sr_content_file_cleanup_assert((string) ($content['status'] ?? '') === 'deleted', 'content file cleanup fixture should mark content deleted.');
sr_content_file_cleanup_assert((string) ($content['summary'] ?? 'x') === '', 'content file cleanup fixture should clear content summary.');
sr_content_file_cleanup_assert((string) ($content['body_format'] ?? '') === 'plain', 'content file cleanup fixture should downgrade redacted body to plain.');
sr_content_file_cleanup_assert((int) ($content['asset_access_enabled'] ?? -1) === 0 && (string) ($content['asset_module'] ?? 'x') === '' && (int) ($content['asset_access_amount'] ?? -1) === 0, 'content file cleanup fixture should disable paid content settings.');

$file = sr_content_file_cleanup_row($pdo, 'SELECT title, original_name, storage_key, storage_path, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount FROM sr_content_files WHERE id = :id', ['id' => $downloadFileId]);
sr_content_file_cleanup_assert((string) ($file['status'] ?? '') === 'deleted', 'content file cleanup fixture should mark download file deleted.');
sr_content_file_cleanup_assert((string) ($file['storage_key'] ?? 'x') === '' && (string) ($file['storage_path'] ?? 'x') === '', 'content file cleanup fixture should clear download file storage references.');
sr_content_file_cleanup_assert((int) ($file['asset_download_enabled'] ?? -1) === 0 && (string) ($file['asset_module'] ?? 'x') === '' && (int) ($file['asset_download_amount'] ?? -1) === 0, 'content file cleanup fixture should disable paid file settings.');
sr_content_file_cleanup_assert((string) ($file['mime_type'] ?? '') === 'application/octet-stream' && (int) ($file['size_bytes'] ?? -1) === 0, 'content file cleanup fixture should redact download file metadata.');

$linkStatus = (string) sr_content_file_cleanup_scalar($pdo, 'SELECT status FROM sr_content_file_links WHERE content_id = :content_id AND file_id = :file_id', ['content_id' => $contentId, 'file_id' => $downloadFileId]);
sr_content_file_cleanup_assert($linkStatus === 'hidden', 'content file cleanup fixture should hide file links.');
$downloadLog = sr_content_file_cleanup_row($pdo, 'SELECT content_title_snapshot, content_slug_snapshot, file_title_snapshot, file_original_name_snapshot FROM sr_content_file_download_logs WHERE file_id = :file_id', ['file_id' => $downloadFileId]);
sr_content_file_cleanup_assert((string) ($downloadLog['content_slug_snapshot'] ?? 'x') === '', 'content file cleanup fixture should clear download log content slug snapshot.');
sr_content_file_cleanup_assert((string) ($downloadLog['content_title_snapshot'] ?? '') !== 'Runtime cleanup content', 'content file cleanup fixture should redact download log content title snapshot.');
sr_content_file_cleanup_assert((string) ($downloadLog['file_original_name_snapshot'] ?? '') !== 'download.txt', 'content file cleanup fixture should redact download log file original name snapshot.');
sr_content_file_cleanup_assert((int) sr_content_file_cleanup_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_series_items WHERE content_id = :content_id OR active_content_id = :content_id', ['content_id' => $contentId]) === 0, 'content file cleanup fixture should remove deleted content from series items.');
sr_content_file_cleanup_assert((string) sr_content_file_cleanup_scalar($pdo, 'SELECT cache_status FROM sr_url_embed_cache WHERE owner_id = :owner_id', ['owner_id' => $contentId]) === 'stale', 'content file cleanup fixture should mark URL embed cache stale.');
sr_content_file_cleanup_assert((int) sr_content_file_cleanup_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_storage_cleanup_failures') === 0, 'content file cleanup fixture should not leave cleanup failures.');

if ($canWriteDownloadStorage) {
    @rmdir($root . '/storage/content/files/runtime-cleanup');
}

if ($errors !== []) {
    fwrite(STDERR, "content file cleanup runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "content file cleanup runtime checks completed.\n";
