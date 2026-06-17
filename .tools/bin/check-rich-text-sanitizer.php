#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers/posts.php';

$errors = [];

function sr_sanitizer_check_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

function sr_sanitizer_check_not_contains(string $html, array $needles, string $label): void
{
    foreach ($needles as $needle) {
        sr_sanitizer_check_assert(!str_contains(strtolower($html), strtolower($needle)), $label . ' must not contain: ' . $needle . ' in ' . $html);
    }
}

function sr_sanitizer_check_contains(string $html, array $needles, string $label): void
{
    foreach ($needles as $needle) {
        sr_sanitizer_check_assert(str_contains($html, $needle), $label . ' must contain: ' . $needle . ' in ' . $html);
    }
}

function sr_sanitizer_check_file_contains(string $file, array $needles, string $label): void
{
    if (!is_file($file)) {
        sr_sanitizer_check_assert(false, $label . ' file is missing: ' . $file);
        return;
    }

    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_sanitizer_check_assert(false, $label . ' file cannot be read: ' . $file);
        return;
    }

    foreach ($needles as $needle) {
        sr_sanitizer_check_assert(str_contains($contents, $needle), $label . ' marker missing in ' . $file . ': ' . $needle);
    }
}

function sr_sanitizer_check_case(callable $sanitize, string $label): void
{
    $dangerousHtml = '<p onclick="alert(1)" style="color:red">Hello <script>alert(1)</script><strong onmouseover="bad()">world</strong></p>'
        . '<iframe src="https://example.com/embed"></iframe>'
        . '<form action="/x"><input name="q"></form>'
        . '<a href="javascript:alert(1)" onclick="bad()">bad link</a>'
        . '<a href="https://example.com/path?q=1" target="_blank">good link</a>'
        . '<img src="data:image/svg+xml;base64,PHN2Zy8+" alt="bad">'
        . '<img src="http://example.com/a.png" alt="bad http">'
        . '<img src="https://example.com/a.png" alt="good" width="640" height="480" onerror="bad()">'
        . '<svg><a href="javascript:alert(1)">x</a></svg>';

    $output = $sanitize($dangerousHtml);
    sr_sanitizer_check_not_contains($output, [
        '<script',
        '</script',
        '<iframe',
        '<form',
        '<input',
        '<svg',
        'onclick',
        'onmouseover',
        'onerror',
        'style=',
        'javascript:',
        'data:image',
        'src="http://',
        'target=',
    ], $label . ' dangerous payload');
    sr_sanitizer_check_contains($output, [
        '<p>Hello <strong>world</strong></p>',
        '<a href="https://example.com/path?q=1" rel="nofollow noopener noreferrer">good link</a>',
        '<img src="https://example.com/a.png" alt="good" width="640" height="480">',
    ], $label . ' safe payload');

    $relativeOutput = $sanitize('<a href="/content/example?x=1">internal</a><img src="/storage/proxy/body.png" alt="internal image">');
    sr_sanitizer_check_contains($relativeOutput, [
        '<a href="/content/example?x=1" rel="nofollow noopener noreferrer">internal</a>',
        '<img src="/storage/proxy/body.png" alt="internal image">',
    ], $label . ' relative URLs');

    $badMarker = $sanitize('<span class="sr-embed-manager-marker unknown" data-sr-embed-manager-ref="bad" data-sr-embed-manager-target-module="Content" data-sr-embed-manager-target-type="../post" data-sr-embed-manager-target-id="0" data-sr-embed-manager-variant="card" data-sr-embed-manager-label="' . str_repeat('x', 140) . '">ignored</span>');
    sr_sanitizer_check_contains($badMarker, [
        '<span class="sr-embed-manager-marker"',
        'data-sr-embed-manager-variant="card"',
    ], $label . ' bad marker class filtering');
    sr_sanitizer_check_not_contains($badMarker, [
        'unknown',
        'data-sr-embed-manager-ref=',
        'data-sr-embed-manager-target-module=',
        'data-sr-embed-manager-target-type=',
        'data-sr-embed-manager-target-id=',
    ], $label . ' bad marker invalid attributes');

    $goodMarker = $sanitize('<span class="foo sr-embed-manager-marker" data-sr-embed-manager-ref="em_abc1234" data-sr-embed-manager-target-module="content" data-sr-embed-manager-target-type="content" data-sr-embed-manager-target-id="12" data-sr-embed-manager-variant="card" data-sr-embed-manager-label="퀴즈  자세히 보기">ignored</span>');
    sr_sanitizer_check_contains($goodMarker, [
        '<span class="sr-embed-manager-marker"',
        'data-sr-embed-manager-ref="em_abc1234"',
        'data-sr-embed-manager-target-module="content"',
        'data-sr-embed-manager-target-type="content"',
        'data-sr-embed-manager-target-id="12"',
        'data-sr-embed-manager-variant="card"',
        'data-sr-embed-manager-label="퀴즈 자세히 보기"',
    ], $label . ' good marker');
    sr_sanitizer_check_not_contains($goodMarker, ['foo'], $label . ' good marker class filtering');
}

function sr_sanitizer_check_ckeditor_case(callable $sanitize, string $label): void
{
    $ckeditorHtml = '<h2 class="ck-heading_heading2">제목</h2>'
        . '<p class="ck-paragraph"><strong>굵게</strong> <em>기울임</em> <u>밑줄</u> <s>취소</s></p>'
        . '<blockquote class="ck-blockquote"><p>인용</p></blockquote>'
        . '<ul class="ck-list"><li data-list-item-id="a">하나</li><li>둘</li></ul>'
        . '<ol><li>첫째</li></ol>'
        . '<p><a href="https://example.com" target="_blank" rel="bookmark">링크</a></p>'
        . '<p><img class="image" src="https://example.com/body.png" alt="이미지" width="640" height="480" style="width:100%"></p>';

    $output = $sanitize($ckeditorHtml);
    $expected = '<h2>제목</h2>'
        . '<p><strong>굵게</strong> <em>기울임</em> <u>밑줄</u> <s>취소</s></p>'
        . '<blockquote><p>인용</p></blockquote>'
        . '<ul><li>하나</li><li>둘</li></ul>'
        . '<ol><li>첫째</li></ol>'
        . '<p><a href="https://example.com" rel="nofollow noopener noreferrer">링크</a></p>'
        . '<p><img src="https://example.com/body.png" alt="이미지" width="640" height="480"></p>';

    sr_sanitizer_check_assert($output === $expected, $label . ' CKEditor fixture output mismatch: ' . $output);
    sr_sanitizer_check_not_contains($output, [
        'ck-heading_heading2',
        'ck-paragraph',
        'ck-blockquote',
        'ck-list',
        'data-list-item-id',
        'target=',
        'rel="bookmark"',
        'class=',
        'style=',
    ], $label . ' CKEditor fixture attribute filtering');
}

function sr_sanitizer_check_namespace_url_payload_case(callable $sanitize, string $label): void
{
    $payload = '<math><mi xlink:href="javascript:alert(1)">M</mi></math>'
        . '<svg><animate xlink:href="javascript:alert(1)" attributeName="x"></animate><a xlink:href="javascript:alert(1)">S</a></svg>'
        . '<object data="https://example.com/x"></object>'
        . '<embed src="https://example.com/x">'
        . '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">'
        . '<meta name="description">hidden meta text</meta>'
        . '<p><a href="JaVaScRiPt:alert(1)">mixed</a></p>'
        . '<p><a href="java&#x0A;script:alert(1)">encoded</a></p>'
        . '<p><img src="JaVaScRiPt:alert(1)" alt="bad"></p>'
        . '<p><iframe srcdoc="<script>alert(1)</script>"></iframe></p>';

    $output = $sanitize($payload);
    sr_sanitizer_check_not_contains($output, [
        '<math',
        '<mi',
        '<svg',
        '<animate',
        '<object',
        '<embed',
        '<meta',
        '<iframe',
        'xlink:href',
        'srcdoc',
        'http-equiv',
        'hidden meta text',
        'javascript:',
        'java&#',
        'alert(1)',
        'data=',
        '<img',
    ], $label . ' namespace and encoded URL payload');
}

function sr_sanitizer_check_body_text_helper_case(): void
{
    $htmlOutput = sr_body_text_html([
        'body_format' => 'html',
        'body_text' => '<p onclick="bad()">Hello <script>alert(1)</script><a href="javascript:alert(1)">bad</a><a href="https://example.com/good">good</a></p>',
    ]);
    sr_sanitizer_check_not_contains($htmlOutput, [
        '<script',
        'onclick',
        'javascript:',
    ], 'common body_text html renderer');
    sr_sanitizer_check_contains($htmlOutput, [
        '<p>Hello bad<a href="https://example.com/good" rel="nofollow noopener noreferrer">good</a></p>',
    ], 'common body_text html renderer');

    $plainOutput = sr_body_text_html([
        'body_format' => 'plain',
        'body_text' => '<strong>plain</strong> https://example.com/path',
    ], true);
    sr_sanitizer_check_not_contains($plainOutput, [
        '<strong>',
    ], 'common body_text plain renderer');
    sr_sanitizer_check_contains($plainOutput, [
        '&lt;strong&gt;plain&lt;/strong&gt;',
        '<a href="https://example.com/path" rel="nofollow noopener noreferrer">https://example.com/path</a>',
    ], 'common body_text plain renderer');
}

function sr_sanitizer_check_rich_text_module_flow_markers(): void
{
    $contracts = [
        'content rich text flow' => [
            'modules/content/helpers.php' => [
                'function sr_content_body_html(',
                'sr_body_text_html($page, $linkPlainUrls)',
                'sr_embed_manager_render_body_html($pdo, $html, \'content\', \'content\'',
            ],
            'modules/content/helpers/records.php' => [
                'function sr_content_input_values(',
                '? sr_sanitize_rich_text_html($bodyText)',
                ': sr_content_clean_text($bodyText, 100000)',
                'function sr_content_copy(PDO $pdo, int $sourceContentId, array $values, int $accountId): int',
                '$copy[\'body_text\'] = sr_sanitize_rich_text_html((string) ($copy[\'body_text\'] ?? \'\'));',
                '$rewrittenBodyText = sr_sanitize_rich_text_html($rewrittenBodyText);',
            ],
        ],
        'community rich text flow' => [
            'modules/community/helpers/posts.php' => [
                'function sr_community_allowed_post_html_tags(): array',
                'return sr_rich_text_allowed_html_tags();',
                'function sr_community_sanitize_post_html(string $html): string',
                'return sr_sanitize_rich_text_html($html);',
                'function sr_community_post_body_html(',
                'sr_community_sanitize_post_html($bodyText)',
                'sr_embed_manager_render_body_html($pdo, $html, \'community\', \'post\'',
            ],
            'modules/community/helpers/posts-writing.php' => [
                'function sr_community_post_input_values(',
                'if ($bodyFormat === \'html\' && is_string($bodyText))',
                '$bodyText = sr_community_sanitize_post_html($bodyText);',
            ],
            'modules/community/helpers/board-copy.php' => [
                '$params[\'body_text\'] = sr_community_sanitize_post_html((string) $params[\'body_text\']);',
                '$bodyText = sr_community_clone_body_files($pdo, (int) $post[\'id\'], $newPostId, (string) $params[\'body_text\'], $createdFiles);',
                '$bodyText = sr_community_sanitize_post_html($bodyText);',
            ],
            'modules/community/helpers/board-copy-jobs.php' => [
                '$params[\'body_text\'] = sr_community_sanitize_post_html((string) $params[\'body_text\']);',
                '$bodyText = sr_community_clone_body_files($pdo, (int) $post[\'id\'], $newPostId, (string) $params[\'body_text\'], $createdBodyFiles);',
                '$bodyText = sr_community_sanitize_post_html($bodyText);',
            ],
        ],
        'popup layer rich text flow' => [
            'modules/popup_layer/helpers.php' => [
                '$bodyHtml = (string) ($popup[\'body_format\'] ?? \'plain\') === \'html\' ? sr_sanitize_rich_text_html($bodyText) : nl2br(sr_e($bodyText), false);',
            ],
            'modules/popup_layer/actions/admin-popup-layers.php' => [
                '$bodyFormat = $popupLayerEditorKey === \'ckeditor\' && sr_post_string(\'body_format\', 20) === \'html\' ? \'html\' : \'plain\';',
                '? sr_sanitize_rich_text_html(sr_popup_layer_clean_text($rawBodyText, 5000))',
                ': sr_popup_layer_clean_text($rawBodyText, 5000);',
            ],
            'modules/popup_layer/actions/admin-popup-layer-copy.php' => [
                '$sourceBodyText = sr_sanitize_rich_text_html($sourceBodyText);',
                '\'body_text\' => $sourceBodyText,',
                '$bodyText = sr_sanitize_rich_text_html(sr_popup_layer_clone_body_files($popupId, $newPopupId, $sourceBodyText));',
            ],
        ],
        'notification rich text flow' => [
            'modules/notification/helpers.php' => [
                'function sr_notification_body_html(array $notification): string',
                'return sr_body_text_html($notification);',
                '$bodyFormat = sr_notification_body_format((string) ($data[\'body_format\'] ?? \'plain\'));',
                '? sr_sanitize_rich_text_html(sr_notification_clean_text((string) ($data[\'body_text\'] ?? \'\'), 5000))',
                ': sr_notification_clean_text((string) ($data[\'body_text\'] ?? \'\'), 5000);',
            ],
            'modules/notification/actions/admin-notifications.php' => [
                '$bodyFormat = \'plain\';',
                '? sr_sanitize_rich_text_html(sr_notification_clean_text($rawBodyText, 5000))',
            ],
        ],
    ];

    foreach ($contracts as $label => $files) {
        foreach ($files as $file => $markers) {
            sr_sanitizer_check_file_contains($file, $markers, $label);
        }
    }
}

function sr_sanitizer_check_purifier_direct_case(): void
{
    $input = '<p style="color:red">Purifier <script>alert(1)</script><a href="javascript:alert(1)" target="_blank" onclick="bad()">bad</a>'
        . '<a href="/content/example?x=1" rel="bookmark" target="_blank">internal</a>'
        . '<a href="https://example.com/body" rel="bookmark" target="_blank">external</a>'
        . '<img src="data:image/svg+xml;base64,PHN2Zy8+" alt="bad">'
        . '<span class="foo sr-embed-manager-marker" data-sr-embed-manager-ref="em_abc1234" data-sr-embed-manager-target-module="content" data-sr-embed-manager-target-type="content" data-sr-embed-manager-target-id="12" data-sr-embed-manager-variant="card" data-sr-embed-manager-label="본문">marker</span>'
        . '</p>';

    $output = sr_sanitize_rich_text_html_with_purifier($input);
    sr_sanitizer_check_assert(is_string($output), 'HTML Purifier direct fixture should return sanitized HTML.');
    if (!is_string($output)) {
        return;
    }

    sr_sanitizer_check_not_contains($output, [
        '<script',
        'javascript:',
        'onclick',
        'style=',
        'target=',
        'data:image',
        'rel="bookmark"',
    ], 'HTML Purifier direct fixture');
    sr_sanitizer_check_contains($output, [
        '<a href="/content/example?x=1">internal</a>',
        '<a href="https://example.com/body" rel="nofollow">external</a>',
        '<span class="sr-embed-manager-marker"',
        'data-sr-embed-manager-ref="em_abc1234"',
        'data-sr-embed-manager-target-module="content"',
        'data-sr-embed-manager-target-type="content"',
        'data-sr-embed-manager-target-id="12"',
        'data-sr-embed-manager-variant="card"',
        'data-sr-embed-manager-label="본문"',
    ], 'HTML Purifier direct fixture');
}

sr_sanitizer_check_assert(
    sr_rich_text_allowed_html_tags() === sr_community_allowed_post_html_tags(),
    'Common rich text and community post sanitizer allowlists should stay aligned.'
);

sr_sanitizer_check_case('sr_sanitize_rich_text_html', 'common rich text sanitizer');
sr_sanitizer_check_case('sr_community_sanitize_post_html', 'community post sanitizer');
sr_sanitizer_check_case('sr_sanitize_rich_text_html_fallback', 'common rich text sanitizer fallback');
sr_sanitizer_check_namespace_url_payload_case('sr_sanitize_rich_text_html', 'common rich text sanitizer');
sr_sanitizer_check_namespace_url_payload_case('sr_community_sanitize_post_html', 'community post sanitizer');
sr_sanitizer_check_namespace_url_payload_case('sr_sanitize_rich_text_html_fallback', 'common rich text sanitizer fallback');
sr_sanitizer_check_ckeditor_case('sr_sanitize_rich_text_html', 'common rich text sanitizer');
sr_sanitizer_check_ckeditor_case('sr_community_sanitize_post_html', 'community post sanitizer');
sr_sanitizer_check_ckeditor_case('sr_sanitize_rich_text_html_fallback', 'common rich text sanitizer fallback');
sr_sanitizer_check_body_text_helper_case();
sr_sanitizer_check_rich_text_module_flow_markers();

sr_sanitizer_check_assert(
    sr_rich_text_purifier_available(),
    'HTML Purifier should be available in the bundled dependency test environment.'
);

sr_sanitizer_check_purifier_direct_case();

$purifierCacheDir = sr_rich_text_purifier_cache_dir();
if ($purifierCacheDir !== '') {
    sr_sanitizer_check_assert(
        str_starts_with(str_replace('\\', '/', $purifierCacheDir), str_replace('\\', '/', SR_ROOT . '/storage/cache/htmlpurifier')),
        'HTML Purifier cache directory must stay under storage/cache/htmlpurifier.'
    );
    sr_sanitizer_check_assert(
        !str_contains(str_replace('\\', '/', $purifierCacheDir), '/vendor/'),
        'HTML Purifier cache directory must not be inside vendor.'
    );
}

if ($errors !== []) {
    fwrite(STDERR, "rich text sanitizer checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "rich text sanitizer checks completed.\n";
