#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/community/helpers.php';

$errors = [];

function sr_link_card_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

$token = '{{sr_link_card module="community" entity_type="post" entity_id="7" variant="compact" label="게시글" slot="body"}}';
$tokens = sr_link_card_extract_tokens($token);
if (count($tokens) !== 1) {
    sr_link_card_check_error('Legacy link card token detection must still find stored tokens for rejection.');
}

if (sr_link_card_token_rejection_errors($token) === []) {
    sr_link_card_check_error('New content saves must reject link card tokens in body text.');
}

unset($GLOBALS['pdo']);
$communityValidationErrors = sr_community_validate_post_input([
    'title' => '링크 카드 토큰 검증',
    'body_text' => $token,
    'body_format' => 'plain',
]);
if ($communityValidationErrors === []) {
    sr_link_card_check_error('Community post validation must reject legacy link card tokens without requiring a global PDO.');
}

$plainText = "관련 게시글\n/community/post?id=7\n게시글 요약";
if (sr_link_card_token_rejection_errors($plainText) !== []) {
    sr_link_card_check_error('Plain text link insertion must not be rejected as a link card token.');
}

$html = '<blockquote><p><strong><a href="/community/post?id=7">관련 게시글</a></strong></p><p>게시글 요약</p></blockquote>';
$sanitized = sr_sanitize_rich_text_html($html);
if (!str_contains($sanitized, '<blockquote>') || !str_contains($sanitized, '<a href="/community/post?id=7"')) {
    sr_link_card_check_error('HTML link insertion must survive the rich text sanitizer as ordinary user-authored HTML.');
}

$fakeWidgetHtml = '<aside class="sr-link-card" data-link-card-module="community" data-entity-id="7"><a href="/community/post?id=7">가짜 카드</a></aside>';
if (sr_link_card_extract_tokens($fakeWidgetHtml) !== []) {
    sr_link_card_check_error('Rendered or pasted widget HTML must not be treated as a trusted link card reference.');
}

$contentHelper = file_get_contents($root . '/modules/content/helpers.php');
if (!is_string($contentHelper) || strpos($contentHelper, 'legacy 링크 카드 토큰이 남아 있는 콘텐츠는 복사할 수 없습니다.') === false) {
    sr_link_card_check_error('Content copy must not create new content rows from source bodies that still contain legacy link card tokens.');
}

$communityBoardCopyHelper = file_get_contents($root . '/modules/community/helpers/board-copy.php');
$communityBoardCopyJobsHelper = file_get_contents($root . '/modules/community/helpers/board-copy-jobs.php');
if (!is_string($communityBoardCopyHelper)
    || strpos($communityBoardCopyHelper, "'legacy_link_card_tokens' => 0") === false
    || strpos($communityBoardCopyHelper, 'legacy 링크 카드 토큰이 남아 있는 게시글이 있어 게시판 복사를 시작하지 않았습니다.') === false
    || !is_string($communityBoardCopyJobsHelper)
    || strpos($communityBoardCopyJobsHelper, 'sr_community_board_copy_batch_block_errors($counts)') === false
) {
    sr_link_card_check_error('Community board copy and batch copy must block source posts that still contain legacy link card tokens.');
}

if ($errors !== []) {
    fwrite(STDERR, "link card checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "link card insertion policy checks completed.\n";
