#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';

$errors = [];

function sr_content_copy_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_content_copy_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_content_copy_runtime_error($message);
    }
}

function sr_content_copy_runtime_scalar(PDO $pdo, string $sql, array $params = []): mixed
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchColumn();
}

function sr_content_copy_runtime_row(PDO $pdo, string $sql, array $params = []): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_content_copy_runtime_marker(string $refKey, int $targetId): string
{
    return '<span class="sr-embed-manager-marker"'
        . ' data-sr-embed-manager-ref="' . sr_e($refKey) . '"'
        . ' data-sr-embed-manager-target-module="content"'
        . ' data-sr-embed-manager-target-type="content"'
        . ' data-sr-embed-manager-target-id="' . sr_e((string) $targetId) . '"'
        . ' data-sr-embed-manager-variant="card"'
        . ' data-sr-embed-manager-label="복사 원본"></span>';
}

function sr_content_copy_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_site_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string")');
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_group_id INTEGER,
        slug TEXT NOT NULL,
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
    $pdo->exec('CREATE TABLE sr_content_setting_sources (id INTEGER PRIMARY KEY AUTOINCREMENT, content_id INTEGER NOT NULL, setting_key TEXT NOT NULL, source TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_content_files (id INTEGER PRIMARY KEY AUTOINCREMENT, content_id INTEGER NOT NULL, status TEXT NOT NULL DEFAULT "active")');
    $pdo->exec('CREATE TABLE sr_content_file_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_id INTEGER NOT NULL,
        file_id INTEGER NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "active",
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        UNIQUE(content_id, file_id)
    )');
    $pdo->exec('CREATE TABLE sr_content_series (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        series_key TEXT NOT NULL UNIQUE,
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
        updated_at TEXT NOT NULL,
        UNIQUE(series_id, content_id),
        UNIQUE(active_content_id)
    )');
    $pdo->exec('CREATE TABLE sr_embed_manager_refs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ref_key TEXT NOT NULL UNIQUE,
        owner_module TEXT NOT NULL,
        owner_type TEXT NOT NULL,
        owner_id INTEGER NOT NULL,
        owner_field TEXT NOT NULL DEFAULT "body",
        target_module TEXT NOT NULL,
        target_type TEXT NOT NULL,
        target_id TEXT NOT NULL,
        variant TEXT NOT NULL DEFAULT "card",
        label_snapshot TEXT NOT NULL DEFAULT "",
        image_snapshot TEXT NOT NULL DEFAULT "",
        sort_order INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT "active",
        created_by_account_id INTEGER,
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )');
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_modules (module_key, status) VALUES ('content', 'enabled')");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_content_copy_runtime_schema($pdo);

$sourceContentId = 98101;
$now = '2026-06-11 00:00:00';
$sourceRefKey = 'em_content_copy_source';
$sourceMarker = sr_content_copy_runtime_marker($sourceRefKey, $sourceContentId);
$sourceBody = '<p>원본 본문</p>' . $sourceMarker;

$pdo->prepare('INSERT INTO sr_content_items (id, slug, title, summary, body_text, body_format, status, created_by, updated_by, published_at, created_at, updated_at) VALUES (:id, :slug, :title, :summary, :body_text, "html", "published", 1, 1, :published_at, :created_at, :updated_at)')->execute([
    'id' => $sourceContentId,
    'slug' => 'runtime-copy-source',
    'title' => 'Runtime copy source',
    'summary' => 'copy summary',
    'body_text' => $sourceBody,
    'published_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_setting_sources (content_id, setting_key, source, created_at, updated_at) VALUES (:content_id, "layout_key", "content", :created_at, :updated_at)')->execute([
    'content_id' => $sourceContentId,
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_files (id, content_id, status) VALUES (1, :content_id, "active")')->execute(['content_id' => $sourceContentId]);
$pdo->prepare('INSERT INTO sr_content_files (id, content_id, status) VALUES (2, :content_id, "active")')->execute(['content_id' => $sourceContentId]);
$pdo->prepare('INSERT INTO sr_content_file_links (content_id, file_id, sort_order, status, created_at, updated_at) VALUES (:content_id, 1, 3, "active", :created_at, :updated_at)')->execute([
    'content_id' => $sourceContentId,
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_series (id, series_key, title, description, status, visibility, sort_order, created_by, updated_by, created_at, updated_at) VALUES (1, "runtime_source_series", "Source series", "", "active", "public", 1, 1, 1, :created_at, :updated_at)')->execute([
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare('INSERT INTO sr_content_series_items (series_id, content_id, active_content_id, episode_label, item_status, sort_order, created_by, created_at, updated_at) VALUES (1, :content_id, :active_content_id, "1화", "active", 5, 1, :created_at, :updated_at)')->execute([
    'content_id' => $sourceContentId,
    'active_content_id' => $sourceContentId,
    'created_at' => $now,
    'updated_at' => $now,
]);

sr_embed_manager_sync_body_refs($pdo, 'content', 'content', $sourceContentId, 'body', $sourceBody, 1);
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_embed_manager_refs WHERE owner_id = :owner_id AND status = "active"', ['owner_id' => $sourceContentId]) === 1, 'content copy fixture should create a source embed ref before copying.');

$newContentId = sr_content_copy($pdo, $sourceContentId, [
    'title' => 'Runtime copy target',
    'slug' => 'runtime-copy-target',
    'copy_series' => true,
    'series_keys' => ['1' => 'runtime_copied_series'],
    'series_titles' => ['1' => 'Copied runtime series'],
], 2);

$newContent = sr_content_copy_runtime_row($pdo, 'SELECT slug, title, body_text, body_format, status, created_by, updated_by FROM sr_content_items WHERE id = :id', ['id' => $newContentId]);
sr_content_copy_runtime_assert((string) ($newContent['slug'] ?? '') === 'runtime-copy-target', 'content copy fixture should create the copied content slug.');
sr_content_copy_runtime_assert((string) ($newContent['status'] ?? '') === 'draft', 'content copy fixture should keep copied content as draft.');
sr_content_copy_runtime_assert((string) ($newContent['body_format'] ?? '') === 'html', 'content copy fixture should preserve html body format.');
sr_content_copy_runtime_assert(!str_contains((string) ($newContent['body_text'] ?? ''), $sourceRefKey), 'content copy fixture should rewrite embedded ref keys.');

$newRef = sr_content_copy_runtime_row($pdo, 'SELECT ref_key, owner_id, target_module, target_type, target_id, status, created_by_account_id FROM sr_embed_manager_refs WHERE owner_id = :owner_id LIMIT 1', ['owner_id' => $newContentId]);
sr_content_copy_runtime_assert($newRef !== [], 'content copy fixture should create an embed ref for the copied content.');
sr_content_copy_runtime_assert((string) ($newRef['ref_key'] ?? '') !== $sourceRefKey, 'content copy fixture should not reuse the source embed ref key.');
sr_content_copy_runtime_assert(str_contains((string) ($newContent['body_text'] ?? ''), (string) ($newRef['ref_key'] ?? '')), 'content copy fixture should store the rewritten ref key in copied body.');
sr_content_copy_runtime_assert((string) ($newRef['target_module'] ?? '') === 'content' && (string) ($newRef['target_type'] ?? '') === 'content' && (int) ($newRef['target_id'] ?? 0) === $sourceContentId, 'content copy fixture should preserve embed target metadata.');
sr_content_copy_runtime_assert((string) ($newRef['status'] ?? '') === 'active' && (int) ($newRef['created_by_account_id'] ?? 0) === 2, 'content copy fixture should keep copied embed ref active with copier account id.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_embed_manager_refs WHERE owner_id = :owner_id AND ref_key = :ref_key', ['owner_id' => $sourceContentId, 'ref_key' => $sourceRefKey]) === 1, 'content copy fixture should keep the source embed ref on the source content.');

sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_file_links WHERE content_id = :content_id AND status = "active"', ['content_id' => $newContentId]) === 2, 'content copy fixture should copy explicit and legacy file links.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT sort_order FROM sr_content_file_links WHERE content_id = :content_id AND file_id = 1', ['content_id' => $newContentId]) === 3, 'content copy fixture should preserve explicit file link sort order.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT sort_order FROM sr_content_file_links WHERE content_id = :content_id AND file_id = 2', ['content_id' => $newContentId]) === 0, 'content copy fixture should link legacy content-owned files with default sort order.');

$series = sr_content_copy_runtime_row($pdo, 'SELECT s.series_key, s.title, si.content_id, si.active_content_id, si.episode_label, si.sort_order FROM sr_content_series s INNER JOIN sr_content_series_items si ON si.series_id = s.id WHERE si.active_content_id = :content_id LIMIT 1', ['content_id' => $newContentId]);
sr_content_copy_runtime_assert((string) ($series['series_key'] ?? '') === 'runtime_copied_series', 'content copy fixture should create the requested copied series key.');
sr_content_copy_runtime_assert((string) ($series['title'] ?? '') === 'Copied runtime series', 'content copy fixture should create the requested copied series title.');
sr_content_copy_runtime_assert((int) ($series['content_id'] ?? 0) === $newContentId && (int) ($series['active_content_id'] ?? 0) === $newContentId, 'content copy fixture should attach the copied content to the copied series.');
sr_content_copy_runtime_assert((string) ($series['episode_label'] ?? '') === '1화' && (int) ($series['sort_order'] ?? 0) === 5, 'content copy fixture should preserve copied series item metadata.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_revisions WHERE content_id = :content_id AND status = "draft"', ['content_id' => $newContentId]) === 1, 'content copy fixture should record a draft revision for the copied content.');

if ($errors !== []) {
    fwrite(STDERR, "content copy runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "content copy runtime checks completed.\n";
