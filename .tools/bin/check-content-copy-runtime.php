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

function sr_content_copy_runtime_schema(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE sr_site_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string")');
    $pdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, status TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE sr_module_settings (id INTEGER PRIMARY KEY AUTOINCREMENT, module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL DEFAULT "string", created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(module_id, setting_key))');
    $pdo->exec('CREATE TABLE sr_content_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        content_group_id INTEGER,
        slug TEXT NOT NULL,
        title TEXT NOT NULL,
        summary TEXT NOT NULL DEFAULT "",
        cover_image_url TEXT NOT NULL DEFAULT "",
        body_text TEXT NOT NULL DEFAULT "",
        body_format TEXT NOT NULL DEFAULT "plain",
        editor_key TEXT NOT NULL DEFAULT "textarea",
        status TEXT NOT NULL,
        view_count INTEGER NOT NULL DEFAULT 0,
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
        comment_extra_fields_json TEXT,
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
        editor_key TEXT NOT NULL DEFAULT "textarea",
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
    $pdo->exec("INSERT INTO sr_site_settings (setting_key, setting_value, value_type) VALUES ('site.default_currency', 'KRW', 'string')");
    $pdo->exec("INSERT INTO sr_modules (id, module_key, status) VALUES (1, 'content', 'enabled')");
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
sr_content_copy_runtime_schema($pdo);

$sourceContentId = 98101;
$embeddedContentId = 98100;
$now = '2026-06-11 00:00:00';
$sourceUrl = '/content/runtime-copy-embedded';
$sourceBody = '<p>원본 본문</p><p><a href="' . $sourceUrl . '">' . $sourceUrl . '</a></p>';

$pdo->prepare('INSERT INTO sr_content_items (id, slug, title, summary, body_text, body_format, editor_key, status, view_count, created_by, updated_by, published_at, created_at, updated_at) VALUES (:id, :slug, :title, :summary, :body_text, "html", "html", "published", 12, 1, 1, :published_at, :created_at, :updated_at)')->execute([
    'id' => $embeddedContentId,
    'slug' => 'runtime-copy-embedded',
    'title' => 'Runtime copy embedded',
    'summary' => 'embedded summary',
    'body_text' => '<p>임베드 대상</p>',
    'published_at' => $now,
    'created_at' => $now,
    'updated_at' => $now,
]);

$pdo->prepare('INSERT INTO sr_content_items (id, slug, title, summary, body_text, body_format, editor_key, status, view_count, comment_extra_fields_json, created_by, updated_by, published_at, created_at, updated_at) VALUES (:id, :slug, :title, :summary, :body_text, "html", "html", "published", 987, :comment_extra_fields_json, 1, 1, :published_at, :created_at, :updated_at)')->execute([
    'id' => $sourceContentId,
    'slug' => 'runtime-copy-source',
    'title' => 'Runtime copy source',
    'summary' => 'copy summary',
    'body_text' => $sourceBody,
    'comment_extra_fields_json' => '[{"key":"field_name","label":"이름","type":"text","required":false,"options":[],"privacy_purpose":"","show_privacy_purpose":true,"export_policy":"include","cleanup_policy":"anonymize"}]',
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

sr_url_embed_sync_body_url_cache($pdo, 'content', 'content', $sourceContentId, 'body', $sourceBody, 1);
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = :owner_id AND cache_status = "fresh"', ['owner_id' => $sourceContentId]) === 1, 'content copy fixture should create a source URL cache row before copying.');

$newContentId = sr_content_copy($pdo, $sourceContentId, [
    'title' => 'Runtime copy target',
    'slug' => 'runtime-copy-target',
    'copy_series' => true,
    'series_keys' => ['1' => 'runtime_copied_series'],
    'series_titles' => ['1' => 'Copied runtime series'],
], 2);

$newContent = sr_content_copy_runtime_row($pdo, 'SELECT slug, title, body_text, body_format, editor_key, status, view_count, comment_extra_fields_json, created_by, updated_by FROM sr_content_items WHERE id = :id', ['id' => $newContentId]);
sr_content_copy_runtime_assert((string) ($newContent['slug'] ?? '') === 'runtime-copy-target', 'content copy fixture should create the copied content slug.');
sr_content_copy_runtime_assert((string) ($newContent['status'] ?? '') === 'draft', 'content copy fixture should keep copied content as draft.');
sr_content_copy_runtime_assert((int) ($newContent['view_count'] ?? -1) === 0, 'content copy fixture should not copy source view count.');
sr_content_copy_runtime_assert((string) ($newContent['body_format'] ?? '') === 'html', 'content copy fixture should preserve html body format.');
sr_content_copy_runtime_assert((string) ($newContent['editor_key'] ?? '') === 'html', 'content copy fixture should preserve explicit editor key.');
sr_content_copy_runtime_assert(str_contains((string) ($newContent['comment_extra_fields_json'] ?? ''), 'field_name'), 'content copy fixture should preserve the entity-specific comment field definition.');
sr_content_copy_runtime_assert(str_contains((string) ($newContent['body_text'] ?? ''), $sourceUrl), 'content copy fixture should preserve embedded source URL.');

$newCache = sr_content_copy_runtime_row($pdo, 'SELECT owner_id, source_url, canonical_url, target_module, target_type, target_id, cache_status, created_by_account_id FROM sr_url_embed_cache WHERE owner_id = :owner_id LIMIT 1', ['owner_id' => $newContentId]);
sr_content_copy_runtime_assert($newCache !== [], 'content copy fixture should create a URL cache row for the copied content.');
sr_content_copy_runtime_assert((string) ($newCache['source_url'] ?? '') === $sourceUrl && (string) ($newCache['canonical_url'] ?? '') === $sourceUrl, 'content copy fixture should preserve source and canonical URL.');
sr_content_copy_runtime_assert((string) ($newCache['target_module'] ?? '') === 'content' && (string) ($newCache['target_type'] ?? '') === 'content' && (int) ($newCache['target_id'] ?? 0) === $embeddedContentId, 'content copy fixture should preserve URL target metadata.');
sr_content_copy_runtime_assert((string) ($newCache['cache_status'] ?? '') === 'fresh' && (int) ($newCache['created_by_account_id'] ?? 0) === 2, 'content copy fixture should keep copied URL cache fresh with copier account id.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_url_embed_cache WHERE owner_id = :owner_id AND canonical_url = :canonical_url', ['owner_id' => $sourceContentId, 'canonical_url' => $sourceUrl]) === 1, 'content copy fixture should keep the source URL cache on the source content.');

sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_file_links WHERE content_id = :content_id AND status = "active"', ['content_id' => $newContentId]) === 2, 'content copy fixture should copy explicit and legacy file links.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT sort_order FROM sr_content_file_links WHERE content_id = :content_id AND file_id = 1', ['content_id' => $newContentId]) === 3, 'content copy fixture should preserve explicit file link sort order.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT sort_order FROM sr_content_file_links WHERE content_id = :content_id AND file_id = 2', ['content_id' => $newContentId]) === 0, 'content copy fixture should link legacy content-owned files with default sort order.');

$series = sr_content_copy_runtime_row($pdo, 'SELECT s.series_key, s.title, si.content_id, si.active_content_id, si.episode_label, si.sort_order FROM sr_content_series s INNER JOIN sr_content_series_items si ON si.series_id = s.id WHERE si.active_content_id = :content_id LIMIT 1', ['content_id' => $newContentId]);
sr_content_copy_runtime_assert((string) ($series['series_key'] ?? '') === 'runtime_copied_series', 'content copy fixture should create the requested copied series key.');
sr_content_copy_runtime_assert((string) ($series['title'] ?? '') === 'Copied runtime series', 'content copy fixture should create the requested copied series title.');
sr_content_copy_runtime_assert((int) ($series['content_id'] ?? 0) === $newContentId && (int) ($series['active_content_id'] ?? 0) === $newContentId, 'content copy fixture should attach the copied content to the copied series.');
sr_content_copy_runtime_assert((string) ($series['episode_label'] ?? '') === '1화' && (int) ($series['sort_order'] ?? 0) === 5, 'content copy fixture should preserve copied series item metadata.');
sr_content_copy_runtime_assert((int) sr_content_copy_runtime_scalar($pdo, 'SELECT COUNT(*) FROM sr_content_revisions WHERE content_id = :content_id AND status = "draft"', ['content_id' => $newContentId]) === 1, 'content copy fixture should record a draft revision for the copied content.');
sr_content_copy_runtime_assert((string) sr_content_copy_runtime_scalar($pdo, 'SELECT editor_key FROM sr_content_revisions WHERE content_id = :content_id LIMIT 1', ['content_id' => $newContentId]) === 'html', 'content copy fixture should record the copied editor key in the revision.');

if ($errors !== []) {
    fwrite(STDERR, "content copy runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "content copy runtime checks completed.\n";
