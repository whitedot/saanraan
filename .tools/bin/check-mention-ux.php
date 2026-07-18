#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/modules/member/helpers/nicknames.php';

$errors = [];

function sr_mention_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$tokens = sr_member_mention_token_rows('안녕하세요 @홍길동#a18f3c 님');
sr_mention_check_assert(count($tokens) === 1, 'hash prefix mention token should be parsed.');
sr_mention_check_assert(($tokens[0]['public_name'] ?? '') === '홍길동', 'hash prefix mention should expose public name.');
sr_mention_check_assert(($tokens[0]['hash_prefix'] ?? '') === 'a18f3c', 'hash prefix mention should expose prefix.');

$tokens = sr_member_mention_token_rows('@foo#bar#a18f3c');
sr_mention_check_assert(($tokens[0]['public_name'] ?? '') === 'foo#bar', 'mention parser should split on the last hex hash suffix.');
sr_mention_check_assert(($tokens[0]['hash_prefix'] ?? '') === 'a18f3c', 'mention parser should keep the last hex hash suffix.');

$tokens = sr_member_mention_token_rows('@홍길동');
sr_mention_check_assert(count($tokens) === 1 && empty($tokens[0]['has_hash_prefix']), 'bare mention should remain parseable for unique nickname/name fallback.');

$longName = str_repeat('가', 60);
$tokens = sr_member_mention_token_rows('@' . $longName . '#abcdef');
sr_mention_check_assert(($tokens[0]['public_name'] ?? '') === $longName, 'mention parser should accept names longer than the previous 40 character limit.');

$hashes = ['abcdef11111111111111111111111111', 'abcdef22222222222222222222222222', '12345611111111111111111111111111'];
sr_mention_check_assert(
    sr_member_mention_prefix_length_for_hashes($hashes, $hashes[0], 6) === 8,
    'prefix length should grow when the default 6 characters collide.'
);

$mentionHtml = sr_member_mention_plain_text_html("Hello <b> @foo#bar#a18f3c\n@홍길동");
sr_mention_check_assert(str_contains($mentionHtml, '<span class="sr-mention">'), 'mention renderer should wrap hash prefix tokens.');
sr_mention_check_assert(str_contains($mentionHtml, '@foo#bar'), 'mention renderer should keep names with hash characters visible.');
sr_mention_check_assert(str_contains($mentionHtml, '#a18f3c'), 'mention renderer should keep hash prefixes visible.');
sr_mention_check_assert(str_contains($mentionHtml, '&lt;b&gt;'), 'mention renderer should escape non-mention HTML.');
sr_mention_check_assert(str_contains($mentionHtml, '<br>'), 'mention renderer should preserve plain text line breaks.');
sr_mention_check_assert(!str_contains($mentionHtml, '<span class="sr-mention"><span class="sr-mention-name">@홍길동'), 'mention renderer should not style ambiguous bare mentions.');

$memberPaths = include $root . '/modules/member/paths.php';
sr_mention_check_assert(isset($memberPaths['GET /member/mention-search']), 'member mention search route should be registered.');

$mentionAction = file_get_contents($root . '/modules/member/actions/mention-search.php');
sr_mention_check_assert(is_string($mentionAction) && str_contains($mentionAction, 'sr_member_require_login($pdo)'), 'mention search API should require login.');
sr_mention_check_assert(is_string($mentionAction) && !str_contains($mentionAction, "'email'"), 'mention search API should not expose email fields.');
sr_mention_check_assert(is_string($mentionAction) && str_contains($mentionAction, 'sr_rate_limit_count'), 'mention search API should use rate limiting.');

$mentionJs = file_get_contents($root . '/assets/mention-input.js');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, 'data-sr-mention-input'), 'mention input asset should bind data-sr-mention-input textareas.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, 'name="is_secret"'), 'mention input asset should close search for secret comments.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, 'function caretPosition(textarea, token)'), 'mention dropdown should be positioned near the caret.');
sr_mention_check_assert(is_string($mentionJs) && !str_contains($mentionJs, 'rect.bottom + 6) + \'px\''), 'mention dropdown should not always anchor to textarea bottom.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, 'Math.min(360, rect.width'), 'mention dropdown should stay compact instead of spanning the full textarea.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, "event.key === 'ArrowDown'"), 'mention dropdown should support ArrowDown navigation.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, "event.key === 'ArrowUp'"), 'mention dropdown should support ArrowUp navigation.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, 'aria-activedescendant'), 'mention dropdown should expose the active keyboard option.');
sr_mention_check_assert(is_string($mentionJs) && str_contains($mentionJs, "scrollIntoView({ block: 'nearest' })"), 'mention dropdown should keep the active keyboard option visible.');

foreach ([
    '/layouts/public/basic/layout.php',
    '/modules/content/theme/basic/layout.php',
    '/modules/community/theme/basic/layout.php',
    '/modules/quiz/theme/basic/layout.php',
    '/modules/survey/theme/basic/layout.php',
] as $layoutPath) {
    $layout = file_get_contents($root . $layoutPath);
    sr_mention_check_assert(is_string($layout) && !str_contains($layout, '/assets/mention-input.js'), 'public layout should not load mention input on every screen: ' . $layoutPath);
    sr_mention_check_assert(is_string($layout) && !str_contains($layout, '/assets/member-recipient-picker.js'), 'public layout should not load the message recipient picker: ' . $layoutPath);
    sr_mention_check_assert(is_string($layout) && str_contains($layout, '$layoutPrivacyCookieConsentHtml !=='), 'public layout should load privacy consent CSS only when consent markup exists: ' . $layoutPath);
    sr_mention_check_assert(is_string($layout) && str_contains($layout, '$layoutRenderedAssetMarkup'), 'public layout should inspect rendered markup before adding optional display assets: ' . $layoutPath);
    sr_mention_check_assert(is_string($layout) && str_contains($layout, "str_contains(\$layoutRenderedAssetMarkup, 'class=\"sr-banner')"), 'public layout should load banner CSS only when banner markup exists: ' . $layoutPath);
    sr_mention_check_assert(is_string($layout) && str_contains($layout, "str_contains(\$layoutRenderedAssetMarkup, 'data-sr-popup-layer')"), 'public layout should load popup CSS only when popup markup exists: ' . $layoutPath);
}

foreach ([
    '/modules/community/skins/basic/list.php',
    '/modules/community/theme/basic/list.php',
] as $listViewPath) {
    $listView = file_get_contents($root . $listViewPath);
    sr_mention_check_assert(is_string($listView) && !str_contains($listView, '/assets/mention-input.js'), 'community list should not request mention input: ' . $listViewPath);
    sr_mention_check_assert(is_string($listView) && !str_contains($listView, '/assets/member-recipient-picker.js'), 'community list should not request the message recipient picker: ' . $listViewPath);
    sr_mention_check_assert(is_string($listView) && !str_contains($listView, 'sr_enabled_module_asset_paths('), 'community list should not load optional module assets solely because their modules are enabled: ' . $listViewPath);
}

foreach ([
    '/modules/community/skins/basic/view.php',
    '/modules/community/theme/basic/post.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, "'scripts' => is_array(\$account ?? null) ? ['/assets/mention-input.js'] : []"), 'community post view should request mention input only for a signed-in account: ' . $viewPath);
}

foreach ([
    '/modules/content/views/content.php',
    '/modules/content/theme/basic/content.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, "'scripts' => is_array(\$account ?? null) && !empty(\$pageAccess['allowed']) ? ['/assets/mention-input.js'] : []"), 'content view should request mention input only when a signed-in account can view the content: ' . $viewPath);
}

foreach ([
    '/modules/quiz/skins/basic/view.php',
    '/modules/quiz/theme/basic/view.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, "'scripts' => \$quizCommentsEnabled && is_array(\$currentAccount) ? ['/assets/mention-input.js'] : []"), 'quiz view should request mention input only when a signed-in account can use comments: ' . $viewPath);
}

foreach ([
    '/modules/survey/skins/basic/view.php',
    '/modules/survey/theme/basic/view.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, "'scripts' => \$surveyCommentsEnabled && is_array(\$currentAccount) ? ['/assets/mention-input.js'] : []"), 'survey view should request mention input only when a signed-in account can use comments: ' . $viewPath);
}

$messageWriteView = file_get_contents($root . '/modules/message/views/message-write.php');
sr_mention_check_assert(
    is_string($messageWriteView) && str_contains($messageWriteView, "'scripts' => ['/assets/member-recipient-picker.js']"),
    'message write view should request the recipient picker asset it owns.'
);

foreach ([
    '/modules/community/skins/basic/view.php',
    '/modules/content/views/content.php',
    '/modules/quiz/skins/basic/view.php',
    '/modules/survey/skins/basic/view.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, 'data-sr-mention-input'), 'comment view should enable mention input: ' . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, '/member/mention-search'), 'comment view should point to member mention search: ' . $viewPath);
    $mentionRenderer = match (true) {
        str_starts_with($viewPath, '/modules/community/') => 'sr_community_comment_body_html',
        str_starts_with($viewPath, '/modules/content/') => 'sr_content_comment_body_html',
        str_starts_with($viewPath, '/modules/quiz/') => 'sr_quiz_comment_body_html',
        str_starts_with($viewPath, '/modules/survey/') => 'sr_survey_comment_body_html',
        default => 'sr_member_mention_plain_text_html',
    };
    sr_mention_check_assert(is_string($view) && str_contains($view, $mentionRenderer), 'comment view should render mention tokens through the format-aware renderer: ' . $viewPath);
}

$notificationInstallSql = file_get_contents($root . '/modules/notification/install.sql');
sr_mention_check_assert(is_string($notificationInstallSql) && str_contains($notificationInstallSql, "('quiz', 'comment.mention'"), 'notification install SQL should seed quiz comment mention template.');
sr_mention_check_assert(is_string($notificationInstallSql) && str_contains($notificationInstallSql, "('survey', 'comment.mention'"), 'notification install SQL should seed survey comment mention template.');

$notificationUpdateSql = file_get_contents($root . '/modules/notification/updates/2026.06.004.sql');
sr_mention_check_assert(is_string($notificationUpdateSql) && str_contains($notificationUpdateSql, "('quiz', 'comment.mention'"), 'notification update SQL should seed quiz comment mention template.');
sr_mention_check_assert(is_string($notificationUpdateSql) && str_contains($notificationUpdateSql, "('survey', 'comment.mention'"), 'notification update SQL should seed survey comment mention template.');

foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    $moduleCss = file_get_contents($root . '/modules/' . $moduleKey . '/theme/basic/assets/module.css');
    sr_mention_check_assert(is_string($moduleCss) && str_contains($moduleCss, '.sr-mention'), 'module CSS should define rendered mention styles: ' . $moduleKey);
}

if ($errors !== []) {
    fwrite(STDERR, "saanraan mention UX checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan mention UX checks completed.\n";
