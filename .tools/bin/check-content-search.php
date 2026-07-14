#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';

$errors = [];

function sr_check_content_search_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_check_content_search_contains(string $path, array $needles): void
{
    $content = file_get_contents($path);
    if (!is_string($content)) {
        sr_check_content_search_error('file cannot be read: ' . $path);
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, (string) $needle)) {
            sr_check_content_search_error($path . ' must contain: ' . (string) $needle);
        }
    }
}

function sr_check_content_search_runtime(): void
{
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        sr_check_content_search_error('SQLite PDO driver is required for content search runtime fixture.');
        return;
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec(
        "CREATE TABLE sr_content_groups (
            id INTEGER PRIMARY KEY,
            group_key TEXT NOT NULL,
            title TEXT NOT NULL,
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_content_items (
            id INTEGER PRIMARY KEY,
            content_group_id INTEGER NULL,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NULL,
            body_text TEXT NOT NULL,
            body_format TEXT NOT NULL DEFAULT 'plain',
            editor_key TEXT NOT NULL DEFAULT 'textarea',
            status TEXT NOT NULL,
            asset_access_enabled INTEGER NOT NULL DEFAULT 0,
            asset_module TEXT NOT NULL DEFAULT '',
            asset_access_amount INTEGER NOT NULL DEFAULT 0,
            asset_access_settlement_currency TEXT NOT NULL DEFAULT 'KRW',
            asset_access_amounts_json TEXT NULL,
            asset_access_group_policies_json TEXT NULL,
            asset_access_policy_set_id INTEGER NOT NULL DEFAULT 0,
            asset_charge_policy TEXT NOT NULL DEFAULT 'once',
            view_count INTEGER NOT NULL DEFAULT 0,
            created_by INTEGER NULL,
            published_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $pdo->exec("INSERT INTO sr_content_groups (id, group_key, title, status) VALUES (1, 'fixture', '픽스처', 'enabled')");
    $insert = $pdo->prepare(
        'INSERT INTO sr_content_items
            (id, content_group_id, slug, title, summary, body_text, body_format, status, asset_access_enabled, asset_module, asset_access_amount, asset_charge_policy, published_at, created_at, updated_at)
         VALUES
            (:id, 1, :slug, :title, :summary, :body_text, :body_format, :status, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_charge_policy, :published_at, :created_at, :updated_at)'
    );
    $rows = [
        [1, 'title-hit', '바늘 제목', '공개 요약', '다른 본문', 'plain', 'published', 0, '', 0, 'once'],
        [2, 'body-hit', '본문 결과', '', '<p>숨은 바늘 문장</p>', 'html', 'published', 0, '', 0, 'once'],
        [3, 'locked-body', '잠긴 결과', '', '잠긴 바늘 본문', 'plain', 'published', 1, 'point', 100, 'once'],
        [4, 'draft-hit', '바늘 초안', '', '바늘 초안 본문', 'plain', 'draft', 0, '', 0, 'once'],
        [5, 'percent-hit', '퍼센트 결과', '달성률 100% 기록', '다른 본문', 'plain', 'published', 0, '', 0, 'once'],
    ];
    foreach ($rows as $row) {
        $insert->execute([
            'id' => $row[0],
            'slug' => $row[1],
            'title' => $row[2],
            'summary' => $row[3],
            'body_text' => $row[4],
            'body_format' => $row[5],
            'status' => $row[6],
            'asset_access_enabled' => $row[7],
            'asset_module' => $row[8],
            'asset_access_amount' => $row[9],
            'asset_charge_policy' => $row[10],
            'published_at' => '2026-07-14 12:00:00',
            'created_at' => '2026-07-14 12:00:00',
            'updated_at' => '2026-07-14 12:00:00',
        ]);
    }

    $results = sr_content_search_items($pdo, '바늘', [], 20, 0);
    $ids = array_map(static fn (array $row): int => (int) $row['id'], $results);
    sort($ids);
    if ($ids !== [1, 2]) {
        sr_check_content_search_error('Search must include published title/body matches and exclude locked-body and draft matches.');
    }

    $identityRestrictedResults = sr_content_search_items($pdo, '바늘', [], 20, 0, false);
    $identityRestrictedIds = array_map(static fn (array $row): int => (int) $row['id'], $identityRestrictedResults);
    if ($identityRestrictedIds !== [1]) {
        sr_check_content_search_error('Identity-restricted search must exclude body-only matches.');
    }

    $literalPercentResults = sr_content_search_items($pdo, '100%', [1, 2, 5], 20, 0);
    $literalPercentIds = array_map(static fn (array $row): int => (int) $row['id'], $literalPercentResults);
    if ($literalPercentIds !== [5]) {
        sr_check_content_search_error('Search LIKE escaping must treat percent as a literal character.');
    }

    if (sr_content_body_excerpt('<p>첫 문장<br>둘째 문장</p>', 'html', 100) !== '첫 문장 둘째 문장') {
        sr_check_content_search_error('HTML search excerpt must be converted to normalized plain text.');
    }
}

sr_check_content_search_contains($root . '/modules/content/paths.php', [
    "'GET /content/search' => 'actions/search.php'",
]);
sr_check_content_search_contains($root . '/modules/content/actions/search.php', [
    'sr_content_search_items(',
    'sr_content_once_access_already_granted(',
    "'search_body_excerpt_allowed'",
]);
sr_check_content_search_contains($root . '/modules/content/theme/basic/layout.php', [
    "sr_url('/content/search')",
    'data-content-layout-search-form',
    'data-content-scroll-nav',
]);
sr_check_content_search_contains($root . '/modules/content/theme/basic/assets/layout.css', [
    '.content-layout-topbar',
    '.content-layout-search',
    '[data-color-scheme="dark"] .content-layout-search',
    '.content-layout-nav.is-content-layout-nav-stuck::before',
]);
sr_check_content_search_runtime();

if ($errors !== []) {
    fwrite(STDERR, "Content search check failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Content search check passed.\n");
