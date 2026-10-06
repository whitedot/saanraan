#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

function sr_member_nicknames_table_exists(PDO $pdo): bool
{
    return false;
}

$fixtureMemberSettings = ['profile_image_enabled' => true];

function sr_member_settings(PDO $pdo): array
{
    global $fixtureMemberSettings;

    return is_array($fixtureMemberSettings) ? $fixtureMemberSettings : [];
}

function sr_member_profile_field_policies(array $settings): array
{
    return [
        'profile_image_path' => [
            'visible' => !empty($settings['profile_image_enabled']),
            'required' => false,
        ],
    ];
}

function sr_member_public_name(array $account, array $settings = [], string $fallback = '회원'): string
{
    $name = trim((string) ($account['display_name'] ?? ''));
    return $name !== '' ? $name : $fallback;
}

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/core/helpers/storage.php';
require_once $root . '/modules/member/helpers/profile.php';
require_once $root . '/modules/member/helpers/follows.php';
require_once $root . '/modules/member/helpers/public-identity.php';
require_once $root . '/modules/content/helpers/comments.php';
require_once $root . '/.tools/lib/basic-theme-delegates.php';

$errors = [];
$assert = static function (bool $condition, string $message) use (&$errors): void {
    if (!$condition) {
        $errors[] = $message;
    }
};
$readViewSource = static fn (string $file): string|false => sr_check_source_with_basic_theme_delegate($root, $file);

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec("CREATE TABLE sr_member_accounts (id INTEGER PRIMARY KEY, display_name TEXT NOT NULL, status TEXT NOT NULL)");
$pdo->exec("INSERT INTO sr_member_accounts (id, display_name, status) VALUES (1, '작성자', 'active'), (2, '탈퇴회원', 'withdrawn')");
$pdo->exec("CREATE TABLE sr_member_profiles (account_id INTEGER PRIMARY KEY, profile_image_path TEXT NOT NULL)");
$avatarReference = 'local:member/profile-images/2026/07/' . str_repeat('a', 32) . '.jpg';
$avatarInsert = $pdo->prepare('INSERT INTO sr_member_profiles (account_id, profile_image_path) VALUES (:account_id, :profile_image_path)');
$avatarInsert->execute(['account_id' => 1, 'profile_image_path' => $avatarReference]);
$avatarInsert->execute(['account_id' => 2, 'profile_image_path' => $avatarReference]);
$avatarSources = sr_member_public_profile_image_sources($pdo, [1, 2, 1, 0]);
$assert(isset($avatarSources[1]) && str_contains((string) $avatarSources[1], '/member/profile-image?file='), 'Public avatar lookup must return a valid uploaded avatar for an active account.');
$assert(!isset($avatarSources[2]), 'Public avatar lookup must exclude withdrawn accounts.');
$avatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar invalid"class');
$assert(str_contains($avatarHtml, 'member-profile-image') && str_contains($avatarHtml, 'fixture-avatar'), 'Public avatar markup must keep its base and valid module class.');
$assert(str_contains($avatarHtml, 'member-profile-image-size-medium') && str_contains($avatarHtml, 'width="32" height="32"'), 'Public avatar markup must use the medium size contract by default.');
$assert(!str_contains($avatarHtml, 'invalid"class'), 'Public avatar markup must discard unsafe module classes.');
$assert(sr_member_public_profile_image_html('', 'fixture-avatar') === '', 'Public avatar markup must stay absent when neither an uploaded avatar nor a fallback label exists.');
$fallbackAvatarHtml = sr_member_public_profile_image_html('', 'fixture-avatar', 'medium', ' 작성자 ');
$assert(
    str_contains($fallbackAvatarHtml, 'member-profile-image-fallback')
        && str_contains($fallbackAvatarHtml, 'member-default-avatar')
        && str_contains($fallbackAvatarHtml, '>작</span>')
        && str_contains($fallbackAvatarHtml, 'member-avatar-color-'),
    'Public avatar markup must render the escaped first display-name character in a stable colored fallback circle.'
);
$smallAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'small');
$largeAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'large');
$customSmallAvatarHtml = sr_member_public_profile_image_html((string) ($avatarSources[1] ?? ''), 'fixture-avatar', 'small', '', 29);
$assert(str_contains($smallAvatarHtml, 'member-profile-image-size-small') && str_contains($smallAvatarHtml, 'width="24" height="24"'), 'Small public avatar markup must use the 24px size contract.');
$assert(str_contains($largeAvatarHtml, 'member-profile-image-size-large') && str_contains($largeAvatarHtml, 'width="40" height="40"'), 'Large public avatar markup must use the 40px size contract.');
$assert(str_contains($customSmallAvatarHtml, 'width="29" height="29"') && str_contains($customSmallAvatarHtml, '--member-profile-image-size: 29px'), 'Public avatar markup must apply the configured usage-tier pixels to attributes and CSS.');
$adminIdentityContext = sr_member_public_identity_context($pdo, null, [1], ['include_follow_statuses' => false]);
$assert(
    !empty($adminIdentityContext['profile_images_enabled'])
        && isset($adminIdentityContext['profile_image_sources'][1])
        && ($adminIdentityContext['follow_statuses'] ?? null) === [],
    'Public identity context must expose profile-image policy and allow non-social placements to skip follow lookup.'
);
$fixtureMemberSettings = ['profile_image_enabled' => false];
$disabledAvatarContext = sr_member_public_identity_context($pdo, null, [1], ['include_follow_statuses' => false]);
$assert(
    empty($disabledAvatarContext['profile_images_enabled'])
        && ($disabledAvatarContext['profile_image_sources'] ?? null) === [],
    'Public identity context must expose disabled profile-image policy without returning uploaded sources.'
);
$fixtureMemberSettings = ['profile_image_enabled' => true];
$identityParts = sr_member_public_identity_parts($pdo, [
    'viewer_account' => null,
    'profile_image_sources' => [1 => (string) ($avatarSources[1] ?? '')],
    'follow_statuses' => [],
    'size_pixels' => ['small' => 29, 'medium' => 32, 'large' => 40],
], 1, '작성자', [
    'size' => 'small',
    'image_class' => 'fixture-identity-image',
    'menu' => false,
]);
$assert(
    str_contains((string) ($identityParts['profile_image_html'] ?? ''), 'fixture-identity-image')
        && str_contains((string) ($identityParts['profile_image_html'] ?? ''), 'width="29" height="29"')
        && (string) ($identityParts['name_html'] ?? '') === '작성자',
    'Public identity contract must return profile-image and name parts from one normalized context.'
);
$fixedPlacementIdentityParts = sr_member_public_identity_parts($pdo, [
    'viewer_account' => null,
    'profile_image_sources' => [1 => (string) ($avatarSources[1] ?? '')],
    'follow_statuses' => [],
    'size_pixels' => ['small' => 29, 'medium' => 32, 'large' => 40],
], 1, '작성자', [
    'size' => 'small',
    'size_pixels' => 24,
    'image_class' => 'fixture-fixed-placement-image',
    'menu' => false,
]);
$assert(
    str_contains((string) ($fixedPlacementIdentityParts['profile_image_html'] ?? ''), 'fixture-fixed-placement-image')
        && str_contains((string) ($fixedPlacementIdentityParts['profile_image_html'] ?? ''), 'width="24" height="24"'),
    'Public identity contract must allow a bounded placement-specific image size without changing shared tier settings.'
);
$identityFallbackParts = sr_member_public_identity_parts($pdo, [
    'viewer_account' => null,
    'profile_image_sources' => [],
    'follow_statuses' => [],
    'size_pixels' => ['small' => 24, 'medium' => 32, 'large' => 40],
], 2, '대체 이름', ['size' => 'small', 'menu' => false]);
$assert(
    str_contains((string) ($identityFallbackParts['profile_image_html'] ?? ''), 'member-profile-image-fallback')
        && str_contains((string) ($identityFallbackParts['profile_image_html'] ?? ''), '>대</span>'),
    'Public identity contract must preserve the name-initial fallback when no public image source exists.'
);
$identityAssets = sr_member_public_identity_assets();
$assert(
    in_array('/modules/member/assets/public-identity.css', (array) ($identityAssets['stylesheets'] ?? []), true)
        && in_array('/modules/member/assets/profile-menu.js', (array) ($identityAssets['scripts'] ?? []), true),
    'Public identity contract must expose its member-owned stylesheet and script.'
);
foreach ([
    'content' => ['table' => 'sr_content_comments', 'foreign_key' => 'content_id'],
] as $moduleKey => $definition) {
    $pdo->exec(
        'CREATE TABLE ' . $definition['table'] . ' (
            id INTEGER PRIMARY KEY,
            ' . $definition['foreign_key'] . ' INTEGER NOT NULL,
            parent_comment_id INTEGER NULL,
            thread_root_id INTEGER NULL,
            depth INTEGER NOT NULL,
            author_account_id INTEGER NULL,
            author_public_name_snapshot TEXT NOT NULL DEFAULT "",
            body_text TEXT NOT NULL,
            is_secret INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );
    $insert = $pdo->prepare(
        'INSERT INTO ' . $definition['table'] . '
            (id, ' . $definition['foreign_key'] . ', parent_comment_id, thread_root_id, depth, author_account_id, author_public_name_snapshot, body_text, status, created_at, updated_at)
         VALUES (:id, 1, NULL, :thread_root_id, 1, 1, "작성자", :body_text, :status, "2026-07-14 00:00:00", "2026-07-14 00:00:00")'
    );
    for ($id = 1; $id <= 46; $id++) {
        $insert->execute([
            'id' => $id,
            'thread_root_id' => $id,
            'body_text' => $moduleKey . ' comment ' . (string) $id,
            'status' => $id === 46 ? 'hidden' : 'published',
        ]);
    }
}

foreach (['content'] as $moduleKey) {
    $pageFunction = 'sr_' . $moduleKey . '_comment_page';
    $positionFunction = 'sr_' . $moduleKey . '_comment_page_for_comment';
    $page = $pageFunction($pdo, 1, 3, 20);
    $ids = array_map(static fn (array $comment): int => (int) ($comment['id'] ?? 0), (array) ($page['comments'] ?? []));
    $assert($ids === [41, 42, 43, 44, 45], $moduleKey . ' comment page must return rows after the first forty.');
    $assert((int) ($page['total'] ?? 0) === 45, $moduleKey . ' comment page must expose the full published count.');
    $assert((int) ($page['total_pages'] ?? 0) === 3, $moduleKey . ' comment page must expose all numeric pages.');
    $assert($positionFunction($pdo, 1, 41, 20) === 3, $moduleKey . ' new comment redirect must resolve its containing page.');
    $overflow = $pageFunction($pdo, 1, 999, 20);
    $assert((int) ($overflow['page'] ?? 0) === 3, $moduleKey . ' comment page must clamp an overflow request to the final page.');
}

$sourceChecks = [
    'modules/community/theme/basic/list.php#list-profile-image' => 'community-list-author-profile-image',
    'modules/community/skins/basic/list.php#list-profile-image' => 'community-list-author-profile-image',
    'modules/community/theme/basic/post.php#post-avatar' => 'community-post-author-avatar',
    'modules/community/theme/basic/post.php#comment-avatar' => 'community-comment-author-avatar',
    'modules/community/skins/basic/view.php#post-avatar' => 'community-post-author-avatar',
    'modules/community/skins/basic/view.php#comment-avatar' => 'community-comment-author-avatar',
    'modules/content/actions/view.php' => 'sr_content_comment_page(',
    'modules/content/theme/basic/content.php#post-avatar' => 'content-post-author-avatar',
    'modules/content/theme/basic/content.php#comment-avatar' => 'content-comment-author-avatar',
    'modules/content/views/content.php#post-avatar' => 'content-post-author-avatar',
    'modules/content/views/content.php#comment-avatar' => 'content-comment-author-avatar',
    'modules/content/theme/basic/content.php#pagination' => 'sr_public_pagination_html($contentCommentPage',
    'modules/content/views/content.php#pagination' => 'sr_public_pagination_html($contentCommentPage',
];
foreach ($sourceChecks as $sourceKey => $marker) {
    $file = explode('#', $sourceKey, 2)[0];
    $contents = $readViewSource($file);
    $assert(is_string($contents) && str_contains($contents, $marker), $file . ' must use public comment pagination marker: ' . $marker);
}

foreach (['modules/community/theme/basic/list.php', 'modules/community/skins/basic/list.php'] as $communityListViewPath) {
    $contents = $readViewSource($communityListViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, 'sr_member_public_identity_parts(')
            && str_contains($contents, "'size' => 'small'")
            && str_contains($contents, '$postAuthorLabel')
            && str_contains($contents, 'community-board-table-author-content')
            && str_contains($contents, 'community-board-table-mobile-author'),
        $communityListViewPath . ' must render desktop and mobile author metadata through the public identity contract.'
    );
}

foreach ([
    'modules/community/theme/basic/post.php' => ['$communityPostAvatarSizePixels', '$communityCommentAvatarSizePixels'],
    'modules/community/skins/basic/view.php' => ['$communityPostAvatarSizePixels', '$communityCommentAvatarSizePixels'],
    'modules/content/theme/basic/content.php' => ['$contentPostAvatarSizePixels', '$contentCommentAvatarSizePixels'],
    'modules/content/views/content.php' => ['$contentPostAvatarSizePixels', '$contentCommentAvatarSizePixels'],
] as $avatarViewPath => $_avatarSizeMarkers) {
    $contents = $readViewSource($avatarViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, 'sr_member_public_identity_parts(')
            && str_contains($contents, "'size' => 'medium'")
            && str_contains($contents, "'size' => 'small'")
            && (
                str_contains($contents, 'AuthorLabel')
                || str_contains($contents, 'OwnerPublicName')
                || str_contains($contents, 'PublisherName')
            ),
        $avatarViewPath . ' must request medium post identities and small comment identities from the public identity contract.'
    );
}

foreach ([
    'modules/community/theme/basic/post.php' => ['$communityPostAuthorLabel', '$communityCommentAuthorLabel'],
    'modules/community/skins/basic/view.php' => ['$communityPostAuthorLabel', '$communityCommentAuthorLabel'],
    'modules/content/theme/basic/content.php' => ['$contentPublisherName', '$contentCommentAuthorLabel'],
    'modules/content/views/content.php' => ['$contentPublisherName', '$contentCommentAuthorLabel'],
] as $fallbackProfileImageViewPath => $fallbackLabelMarkers) {
    $contents = $readViewSource($fallbackProfileImageViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, 'sr_member_public_identity_parts(')
            && str_contains($contents, $fallbackLabelMarkers[0])
            && str_contains($contents, $fallbackLabelMarkers[1]),
        $fallbackProfileImageViewPath . ' must pass author labels to the public identity contract so image fallbacks remain visible.'
    );
}

foreach ([
    'modules/content/theme/basic/content.php',
    'modules/content/views/content.php',
] as $commentViewPath) {
    $contents = $readViewSource($commentViewPath);
    $assert(
        is_string($contents)
            && str_contains($contents, "'compact_edges' => true")
            && str_contains($contents, "'link_class' => 'btn btn-ghost-default'")
            && str_contains($contents, "'current_class' => 'btn btn-solid-primary'"),
        $commentViewPath . ' must render the community-style compact numeric pagination surface.'
    );
}

foreach ([
    'modules/content/theme/basic/content.php' => ['content-comments-panel', 'content-comment-list', 'content-comment-meta-item', 'content-comment-unavailable', '$contentReactionCommentSummaries'],
    'modules/content/views/content.php' => ['content-comments-panel', 'content-comment-list', 'content-comment-meta-item', 'content-comment-unavailable', '$contentReactionCommentSummaries'],
] as $commentViewPath => $commentStructureMarkers) {
    $contents = $readViewSource($commentViewPath);
    foreach ($commentStructureMarkers as $marker) {
        $assert(
            is_string($contents) && str_contains($contents, $marker),
            $commentViewPath . ' must keep the complete comment structure marker: ' . $marker
        );
    }
    $assert(
        is_string($contents)
            && str_contains($contents, "['counts']")
            && str_contains($contents, "['my_record']"),
        $commentViewPath . ' must pass batch reaction summaries into comment widgets.'
    );
    $assert(
        is_string($contents)
            && str_contains($contents, '로그인하면 댓글을 작성할 수 있습니다.')
            && !str_contains($contents, '로그인 후 댓글 작성'),
        $commentViewPath . ' must render the community-style guest comment notice instead of a login button.'
    );
}

foreach (['modules/content/theme/basic/content.php', 'modules/content/views/content.php'] as $contentCommentViewPath) {
    $contents = $readViewSource($contentCommentViewPath);
    $articleClosePosition = is_string($contents) ? strpos($contents, '</article>') : false;
    $commentPanelMatch = [];
    $hasCommentPanel = is_string($contents) && preg_match(
        '/<section\b(?=[^>]*\bid="content-comments")(?=[^>]*\bclass="[^"]*\bcontent-comments-panel\b[^"]*")[^>]*>/',
        $contents,
        $commentPanelMatch,
        PREG_OFFSET_CAPTURE
    ) === 1;
    $commentPanelPosition = $hasCommentPanel ? $commentPanelMatch[0][1] : false;
    $assert(
        $articleClosePosition !== false
            && $commentPanelPosition !== false
            && $articleClosePosition < $commentPanelPosition,
        $contentCommentViewPath . ' must render the comment panel as a sibling after the content article.'
    );
}

$commentViewStateChecks = [
    'modules/community/theme/basic/post.php' => [
        'required' => [
            '$comments === []',
            'community-comments-empty',
            '$canComment',
            'community-comment-form',
            '$commentUnavailableMessage !== \'\'',
            'community-comment-unavailable',
            'guest_author_name',
            'guest_password',
        ],
        'forbidden' => ['로그인 후 댓글 작성'],
    ],
    'modules/community/skins/basic/view.php' => [
        'required' => [
            '$comments === []',
            'community-comments-empty',
            '$canComment',
            'community-comment-form',
            '$commentUnavailableMessage !== \'\'',
            'community-comment-unavailable',
            'guest_author_name',
            'guest_password',
        ],
        'forbidden' => ['로그인 후 댓글 작성'],
    ],
    'modules/content/theme/basic/content.php' => [
        'required' => [
            '$contentComments !== []',
            'content-comments-empty',
            '$contentAdminPreview',
            '관리자 미리보기에서는 댓글을 작성할 수 없습니다.',
            'elseif (is_array($account ?? null))',
            'content-comment-form',
            '로그인하면 댓글을 작성할 수 있습니다.',
        ],
        'forbidden' => ['로그인 후 댓글 작성'],
    ],
    'modules/content/views/content.php' => [
        'required' => [
            '$contentComments !== []',
            'content-comments-empty',
            '$contentAdminPreview',
            '관리자 미리보기에서는 댓글을 작성할 수 없습니다.',
            'elseif (is_array($account ?? null))',
            'content-comment-form',
            '로그인하면 댓글을 작성할 수 있습니다.',
        ],
        'forbidden' => ['로그인 후 댓글 작성'],
    ],
];

foreach (['modules/community/theme/basic/post.php', 'modules/community/skins/basic/view.php'] as $communityCommentFormViewPath) {
    $contents = $readViewSource($communityCommentFormViewPath);
    $formStart = is_string($contents) ? strpos($contents, 'id="community-comment-form"') : false;
    $formEnd = $formStart !== false ? strpos($contents, '</form>', $formStart) : false;
    $formSource = $formStart !== false && $formEnd !== false
        ? substr($contents, $formStart, $formEnd - $formStart)
        : '';
    $secretTogglePosition = strpos($formSource, 'class="community-comment-secret-toggle"');
    $submitPosition = strpos($formSource, '<button type="submit" class="btn btn-solid-primary">');

    $assert(
        str_contains($formSource, 'class="form-textarea form-control-full"'),
        $communityCommentFormViewPath . ' must render the new-comment textarea at the full form width.'
    );
    $assert(
        str_contains($formSource, 'class="community-comment-form-actions"')
            && $secretTogglePosition !== false
            && $submitPosition !== false
            && $secretTogglePosition < $submitPosition,
        $communityCommentFormViewPath . ' must place the secret-comment option immediately before the submit control group.'
    );
}
foreach ($commentViewStateChecks as $commentViewPath => $stateChecks) {
    $contents = $readViewSource($commentViewPath);
    foreach ((array) ($stateChecks['required'] ?? []) as $marker) {
        $assert(
            is_string($contents) && str_contains($contents, (string) $marker),
            $commentViewPath . ' must keep the comment request-flow state marker: ' . (string) $marker
        );
    }
    foreach ((array) ($stateChecks['forbidden'] ?? []) as $marker) {
        $assert(
            is_string($contents) && !str_contains($contents, (string) $marker),
            $commentViewPath . ' must not restore the forbidden comment state output: ' . (string) $marker
        );
    }
}

foreach (sr_check_basic_theme_delegates() as $themeViewPath => $delegate) {
    $themeContents = file_get_contents($root . '/' . $themeViewPath);
    $delegateMarker = (string) ($delegate['marker'] ?? '');
    $assert(
        $delegateMarker !== '' && is_string($themeContents) && str_contains($themeContents, $delegateMarker),
        $themeViewPath . ' must delegate to the canonical selected skin/view.'
    );
}

foreach ([
    'modules/community/theme/basic/assets/module.css' => [
        '.community-comments-pagination',
        '.community-list-author-profile-image',
        '.community-board-table-author-content',
        '.community-board-table-mobile-author',
        '.community-post-author-avatar',
        '.community-comment-author-avatar',
        'border-bottom: 1px solid var(--community-divider',
    ],
    'modules/content/theme/basic/assets/module.css' => [
        '.content-comments-pagination',
        '.content-comments-panel',
        '.content-comment-list',
        '.content-comment-unavailable',
        '.content-post-author-avatar',
        '.content-comment-author-avatar',
        'border-bottom: 1px solid var(--content-divider',
    ],
] as $commentStylesheetPath => $markers) {
    $contents = file_get_contents($root . '/' . $commentStylesheetPath);
    foreach ($markers as $marker) {
        $assert(
            is_string($contents) && str_contains($contents, $marker),
            $commentStylesheetPath . ' must keep the community comment surface marker: ' . $marker
        );
    }
}

$memberIdentityStylesheet = file_get_contents($root . '/modules/member/assets/public-identity.css');
foreach ([
    '.member-profile-menu',
    '.member-profile-image.member-profile-image-size-small',
    '.member-profile-image.member-profile-image-size-medium',
    '.member-profile-image.member-profile-image-size-large',
    '.member-profile-image-fallback',
    '.member-profile-image.member-avatar-color-0',
    '.member-profile-image.member-avatar-color-11',
] as $memberIdentityStyleMarker) {
    $assert(
        is_string($memberIdentityStylesheet) && str_contains($memberIdentityStylesheet, $memberIdentityStyleMarker),
        'Member public identity stylesheet must own the shared marker: ' . $memberIdentityStyleMarker
    );
}
$assert(
    is_string($memberIdentityStylesheet)
        && preg_match(
            '/\.member-profile-image\.member-profile-image-fallback\s*\{[^}]*aspect-ratio:\s*1\s*\/\s*1;[^}]*height:\s*var\(--member-profile-image-size,\s*2rem\);[^}]*width:\s*var\(--member-profile-image-size,\s*2rem\);/s',
            $memberIdentityStylesheet
        ) === 1,
    'Member fallback avatars must keep equal contract-owned dimensions even when a later UI kit member-default-avatar rule is loaded.'
);

foreach (['content'] as $moduleKey) {
    $action = file_get_contents($root . '/modules/' . $moduleKey . '/actions/comment.php');
    $assert(
        is_string($action)
            && str_contains($action, "sr_{$moduleKey}_comment_page_for_comment("),
        $moduleKey . ' comment action must resolve the saved comment page before redirecting.'
    );
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . PHP_EOL);
    }
    exit(1);
}

echo "Public comment pagination checks completed.\n";
