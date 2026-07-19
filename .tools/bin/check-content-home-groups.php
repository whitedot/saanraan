#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/content/helpers.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$sourceContains = static function (string $path, array $markers) use ($root, $assert): void {
    $source = file_get_contents($root . '/' . $path);
    $assert(is_string($source), $path . ' must be readable.');
    if (!is_string($source)) {
        return;
    }

    foreach ($markers as $marker) {
        $assert(str_contains($source, $marker), $path . ' must contain: ' . $marker);
    }
};
$sourceNotContains = static function (string $path, array $markers) use ($root, $assert): void {
    $source = file_get_contents($root . '/' . $path);
    $assert(is_string($source), $path . ' must be readable.');
    if (!is_string($source)) {
        return;
    }

    foreach ($markers as $marker) {
        $assert(!str_contains($source, $marker), $path . ' must not contain: ' . $marker);
    }
};

$fixtureSections = sr_content_home_sections_from_rows([
    ['id' => 10, 'group_key' => 'news', 'title' => '소식', 'status' => 'enabled'],
    ['id' => 11, 'group_key' => 'guide', 'title' => '소식', 'status' => 'enabled'],
    ['id' => 12, 'group_key' => 'hidden', 'title' => '숨김', 'status' => 'disabled'],
], [
    10 => [['id' => 101, 'slug' => 'news-one']],
    11 => [['id' => 102, 'slug' => 'guide-one']],
    12 => [['id' => 103, 'slug' => 'hidden-one']],
], [
    ['id' => 104, 'slug' => 'without-group'],
]);
$assert(count($fixtureSections) === 3, 'home section grouping must keep two public groups and one ungrouped section.');
$assert((int) ($fixtureSections[0]['group_id'] ?? 0) === 10, 'home sections must preserve enabled group order.');
$assert((int) ($fixtureSections[1]['group_id'] ?? 0) === 11, 'same-title content groups must remain separate by id.');
$assert((string) ($fixtureSections[1]['group_key'] ?? '') === 'guide', 'same-title content groups must preserve their own detail key.');
$assert(empty($fixtureSections[2]['is_grouped']), 'the last fixture section must represent contents without a public group.');
$assert((string) ($fixtureSections[2]['group_title'] ?? '') === '최근 콘텐츠', 'ungrouped content section must use the neutral recent-content label.');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $errors[] = 'SQLite PDO driver is required for the content home group runtime fixture.';
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT NOT NULL)");
    $pdo->exec("CREATE TABLE sr_module_settings (module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL, value_type TEXT NOT NULL)");
    $pdo->exec("INSERT INTO sr_modules (id, module_key) VALUES (1, 'member')");
    $pdo->exec("INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type) VALUES (1, 'profile_image_enabled', '1', 'bool')");
    $pdo->exec(
        "CREATE TABLE sr_member_accounts (
            id INTEGER PRIMARY KEY,
            display_name TEXT NOT NULL,
            status TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE sr_member_nicknames (
            account_id INTEGER PRIMARY KEY,
            nickname TEXT NOT NULL
        )"
    );
    $pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (21, '작성자 이름', 'active')");
    $pdo->exec("INSERT INTO sr_member_nicknames (account_id, nickname) VALUES (21, '공개 닉네임')");
    $pdo->exec("CREATE TABLE sr_member_profiles (account_id INTEGER PRIMARY KEY, profile_image_path TEXT NOT NULL)");
    $profileImageReference = 'local:member/profile-images/2026/07/' . str_repeat('b', 32) . '.jpg';
    $profileImageInsert = $pdo->prepare('INSERT INTO sr_member_profiles (account_id, profile_image_path) VALUES (21, :profile_image_path)');
    $profileImageInsert->execute(['profile_image_path' => $profileImageReference]);
    $pdo->exec(
        "CREATE TABLE sr_content_items (
            id INTEGER PRIMARY KEY,
            content_group_id INTEGER NULL,
            slug TEXT NOT NULL,
            title TEXT NOT NULL,
            summary TEXT NULL,
            status TEXT NOT NULL,
            asset_access_enabled INTEGER NOT NULL DEFAULT 0,
            asset_module TEXT NOT NULL DEFAULT '',
            asset_access_amount INTEGER NOT NULL DEFAULT 0,
            asset_charge_policy TEXT NOT NULL DEFAULT 'once',
            created_by INTEGER NULL,
            published_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )"
    );
    $insert = $pdo->prepare(
        'INSERT INTO sr_content_items
            (id, content_group_id, slug, title, summary, status, created_by, published_at, created_at, updated_at)
         VALUES
            (:id, :content_group_id, :slug, :title, :summary, :status, :created_by, :published_at, :created_at, :updated_at)'
    );
    $rows = [
        [1, 1, 'grouped-old', '그룹 이전', '요약', 'published', '2026-07-17 12:00:00'],
        [2, 1, 'grouped-new', '그룹 최신', '요약', 'published', '2026-07-19 12:00:00'],
        [3, 1, 'grouped-middle', '그룹 중간', '요약', 'published', '2026-07-18 12:00:00'],
        [4, 2, 'draft-only', '초안', '요약', 'draft', '2026-07-19 13:00:00'],
        [5, null, 'without-group', '그룹 없음', '요약', 'published', '2026-07-19 14:00:00'],
        [6, 3, 'disabled-group', '비공개 그룹 소속', '요약', 'published', '2026-07-19 15:00:00'],
    ];
    foreach ($rows as $row) {
        $insert->execute([
            'id' => $row[0],
            'content_group_id' => $row[1],
            'slug' => $row[2],
            'title' => $row[3],
            'summary' => $row[4],
            'status' => $row[5],
            'created_by' => 21,
            'published_at' => $row[6],
            'created_at' => $row[6],
            'updated_at' => $row[6],
        ]);
    }

    $runtimeSections = sr_content_home_sections($pdo, [
        ['id' => 1, 'group_key' => 'news', 'title' => '소식', 'status' => 'enabled'],
        ['id' => 2, 'group_key' => 'empty', 'title' => '빈 그룹', 'status' => 'enabled'],
    ], 2);
    $assert(count($runtimeSections) === 2, 'runtime home sections must omit enabled groups without published contents.');
    $assert(array_column($runtimeSections[0]['contents'] ?? [], 'id') === [2, 3], 'group section query must be bounded and newest-first.');
    $assert(array_column($runtimeSections[1]['contents'] ?? [], 'id') === [6, 5], 'contents without an enabled public group must be listed newest-first in the ungrouped section.');
    $assert((string) ($runtimeSections[0]['contents'][0]['author_display_name'] ?? '') === '작성자 이름', 'home content rows must include the author account display name.');
    $assert((string) ($runtimeSections[0]['contents'][0]['author_nickname'] ?? '') === '공개 닉네임', 'home content rows must include the public author nickname.');
    $assert(sr_content_public_author_name((array) ($runtimeSections[0]['contents'][0] ?? []), ['nickname_enabled' => true]) === '공개 닉네임', 'content author labels must follow the public nickname setting.');

    $groupRows = sr_content_published_contents_for_group($pdo, 1);
    $assert(array_column($groupRows, 'id') === [2, 3, 1], 'group content query must remain newest-first.');
    $assert((int) ($groupRows[0]['author_account_id'] ?? 0) === 21, 'group content rows must include the author account id.');
    $publicIdentityContext = sr_member_public_identity_context($pdo, null, array_column($groupRows, 'author_account_id'));
    $publicIdentityParts = sr_member_public_identity_parts($pdo, $publicIdentityContext, 21, '공개 닉네임', [
        'size' => 'small',
        'image_class' => 'content-list-author-profile-image',
        'menu' => false,
    ]);
    $assert(str_contains((string) ($publicIdentityParts['profile_image_html'] ?? ''), '<img') && str_contains((string) ($publicIdentityParts['profile_image_html'] ?? ''), 'content-list-author-profile-image'), 'content list identity contract must render the uploaded profile image.');
    $fallbackIdentityParts = sr_member_public_identity_parts($pdo, [
        'viewer_account' => null,
        'profile_image_sources' => [],
        'follow_statuses' => [],
        'size_pixels' => ['small' => 24, 'medium' => 32, 'large' => 40],
    ], 21, '공개 닉네임', ['size' => 'small', 'menu' => false]);
    $assert(str_contains((string) ($fallbackIdentityParts['profile_image_html'] ?? ''), 'member-profile-image-fallback') && str_contains((string) ($fallbackIdentityParts['profile_image_html'] ?? ''), '>공</span>'), 'content list identity contract must render a visible name-initial fallback when an uploaded profile image is unavailable.');
}

$sourceContains('modules/content/actions/home.php', [
    'sr_content_home_sections($pdo, $contentHomeGroups, 6)',
    "require_once SR_ROOT . '/modules/member/public-identity.php'",
    'sr_member_public_identity_context(',
    'sr_member_public_identity_assets()',
]);
foreach (['modules/content/views/home.php', 'modules/content/theme/basic/home.php'] as $viewFile) {
    $sourceContains($viewFile, [
        'content-home-section-grouped',
        'content-home-section-ungrouped',
        'sr_url(sr_content_group_path($contentHomeSectionGroupKey))',
        'class="content-home-latest-item card"',
        'content-home-latest-image card-img-top',
        '<div class="content-home-latest-meta">',
        'content-list-author-profile-image',
        'sr_content_public_author_name(',
        'sr_member_public_identity_parts(',
        '$contentHomePublicIdentityAssets',
        'class="content-home-latest-group-link"',
        "include SR_ROOT . '/modules/content/theme/basic/sidebar.php'",
        '최근 콘텐츠',
    ]);
    $sourceNotContains($viewFile, [
        '<p class="content-home-latest-meta">',
    ]);
}
foreach (['modules/content/views/group.php', 'modules/content/theme/basic/group.php'] as $viewFile) {
    $sourceContains($viewFile, [
        'class="content-group content-home-screen"',
        'content-home-layout-main-only',
        'content-home-latest content-group-card-grid',
        'class="content-home-latest-item card"',
        '<div class="content-home-latest-meta">',
        'content-list-author-profile-image',
        'sr_content_public_author_name(',
        'sr_member_public_identity_parts(',
        '$contentGroupPublicIdentityAssets',
        "include SR_ROOT . '/modules/content/theme/basic/sidebar.php'",
    ]);
    $sourceNotContains($viewFile, [
        'content-group-tabs',
        'content-group-list',
        '$contentPublisherName',
        '<p class="content-home-latest-meta">',
    ]);
}
$sourceContains('modules/content/theme/basic/assets/module.css', [
    '.content-home-layout',
    '.content-home-section-header',
    '.content-home-section-title',
    '.content-home-latest-group-link',
    '.content-group-card-grid',
    '.content-list-author',
    '.content-list-author-profile-image',
    'var(--content-text, var(--sr-text',
    'var(--content-muted, var(--sr-muted',
]);
$sourceContains('modules/member/assets/profile-menu.js', [
    ".member-profile-menu[open]",
    "target.closest('.member-profile-menu')",
    "event.key === 'Escape'",
]);
$sourceContains('modules/member/assets/public-identity.css', [
    '.member-profile-menu',
    '.member-profile-image-fallback',
    '.member-profile-image.member-profile-image-size-small',
]);
$sourceContains('modules/member/helpers/public-identity.php', [
    'function sr_member_public_identity_context(',
    'function sr_member_public_identity_parts(',
    "'/modules/member/assets/public-identity.css'",
    "'/modules/member/assets/profile-menu.js'",
]);
$sourceNotContains('assets/common-ui.js', [
    ".member-profile-menu[open]",
]);
$sourceNotContains('modules/community/assets/module.js', [
    'initMemberProfileMenus',
]);
foreach ([
    'modules/content/theme/basic/content.php',
    'modules/content/views/content.php',
    'modules/community/theme/basic/list.php',
    'modules/community/skins/basic/list.php',
    'modules/community/theme/basic/post.php',
    'modules/community/skins/basic/view.php',
    'modules/quiz/theme/basic/view.php',
    'modules/quiz/skins/basic/view.php',
    'modules/survey/theme/basic/view.php',
    'modules/survey/skins/basic/view.php',
] as $memberProfileMenuConsumerView) {
    $sourceContains($memberProfileMenuConsumerView, [
        'sr_member_public_identity_parts(',
        'PublicIdentityAssets',
    ]);
    $sourceNotContains($memberProfileMenuConsumerView, [
        'sr_member_public_profile_image_sources(',
        'sr_member_public_profile_image_html(',
        'sr_member_public_name_menu_html(',
        '/modules/member/assets/profile-menu.js',
    ]);
}
$sourceContains('docs/module-guide.md', [
    '콘텐츠 메인은 사용 상태 그룹별 최신 콘텐츠를 그룹 섹션으로 나누고',
]);

if ($errors !== []) {
    fwrite(STDERR, "Content home group check failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Content home group check passed.\n");
