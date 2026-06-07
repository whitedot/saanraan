#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/version.php';
require_once $root . '/core/helpers/runtime.php';
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
    '/modules/community/themes/basic/layout.php',
    '/modules/content/layouts/basic/layout.php',
] as $layoutPath) {
    $layout = file_get_contents($root . $layoutPath);
    sr_mention_check_assert(is_string($layout) && str_contains($layout, '/assets/mention-input.js'), 'public layout should load mention input asset: ' . $layoutPath);
}

foreach ([
    '/modules/community/skins/basic/view.php',
    '/modules/content/views/content.php',
] as $viewPath) {
    $view = file_get_contents($root . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, 'data-sr-mention-input'), 'comment view should enable mention input: ' . $viewPath);
    sr_mention_check_assert(is_string($view) && str_contains($view, '/member/mention-search'), 'comment view should point to member mention search: ' . $viewPath);
}

if ($errors !== []) {
    fwrite(STDERR, "saanraan mention UX checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan mention UX checks completed.\n";
