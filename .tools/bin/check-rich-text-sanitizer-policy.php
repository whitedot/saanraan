#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers/posts.php';

$errors = [];

function sr_rich_text_policy_add_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_rich_text_policy_contains(string $file, array $markers): void
{
    if (!is_file($file)) {
        sr_rich_text_policy_add_error('Rich text sanitizer policy file is missing: ' . $file);
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_rich_text_policy_add_error('Rich text sanitizer policy file cannot be read: ' . $file);
        return;
    }

    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            sr_rich_text_policy_add_error('Rich text sanitizer policy marker missing in ' . $file . ': ' . $marker);
        }
    }
}

function sr_rich_text_policy_expected_allowed_tags(): array
{
    return [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'u' => [],
        's' => [],
        'blockquote' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href'],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'img' => ['src', 'alt', 'width', 'height'],
    ];
}

$expectedAllowedTags = sr_rich_text_policy_expected_allowed_tags();
if (sr_rich_text_allowed_html_tags() !== $expectedAllowedTags) {
    sr_rich_text_policy_add_error('Common rich text sanitizer allowlist differs from documented policy.');
}

if (sr_community_allowed_post_html_tags() !== $expectedAllowedTags) {
    sr_rich_text_policy_add_error('Community post sanitizer allowlist differs from documented policy.');
}

sr_rich_text_policy_contains('docs/rich-text-sanitizer-policy.md', [
    'sr_sanitize_rich_text_html()',
    'sr_community_sanitize_post_html()',
    'sr_body_text_html()',
    'HTML Purifier',
    'sr_rich_text_allowed_html_tags()',
    'sr_community_allowed_post_html_tags()',
    'check-rich-text-sanitizer.php',
    'p',
    'br',
    'strong',
    'em',
    'u',
    's',
    'blockquote',
    'ul',
    'ol',
    'li',
    'a',
    'href',
    'h1',
    'h2',
    'h3',
    'img',
    'src',
    'alt',
    'width',
    'height',
    'javascript:',
    'data:image',
    '전용 marker',
    '콘텐츠, 커뮤니티, 팝업 레이어, 알림 모듈',
    'rich text flow marker',
    'security-response-policy.md',
]);

sr_rich_text_policy_contains('docs/README.md', [
    'rich-text-sanitizer-policy.md',
    'Rich Text Sanitizer 정책',
]);

sr_rich_text_policy_contains('docs/verification-status.md', [
    'rich-text-sanitizer-policy.md',
    '허용 목록',
    'hard-drop 컨테이너 제거',
    'Purifier 1차 정화',
]);

sr_rich_text_policy_contains('docs/security-model.md', [
    'hard-drop 컨테이너를 먼저 제거',
    'Purifier를 1차 정화 엔진',
    '같은 hard-drop 제거 뒤 내부 DOM 기반 sanitizer',
]);

sr_rich_text_policy_contains('docs/risk-register.md', [
    'rich-text-sanitizer-policy.md',
    'R-02',
]);

sr_rich_text_policy_contains('core/helpers/output.php', [
    'sr_strip_rich_text_dropped_containers($html)',
    'HTML.Allowed',
    'HTML.DefinitionID',
    'HTML.DefinitionRev',
    'URI.AllowedSchemes',
    'HTML.Nofollow',
    'HTML.TargetBlank',
    'Cache.SerializerPath',
    'Cache.DefinitionImpl',
    'storage/cache/htmlpurifier',
    'sr_sanitize_rich_text_html_fallback($html)',
]);

sr_rich_text_policy_contains('docs/rich-text-sanitizer-policy.md', [
    'HTML Purifier 설정 경계',
    'HTML.Allowed',
    'HTML.DefinitionID',
    'HTML.DefinitionRev',
    'URI.AllowedSchemes',
    'HTML.Nofollow',
    'HTML.TargetBlank',
    'Cache.SerializerPath',
    'storage/cache/htmlpurifier',
    'Cache.DefinitionImpl',
    'vendor 내부 쓰기',
]);

if ($errors !== []) {
    fwrite(STDERR, "rich text sanitizer policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "rich text sanitizer policy checks completed.\n";
