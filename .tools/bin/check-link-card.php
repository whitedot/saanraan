#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';

$errors = [];

function sr_link_card_check_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

$fixture = '<p>앞</p><p>{{sr_link_card module="community" entity_type="post" entity_id="7" variant="compact" label="게시글" slot="body"}}</p><p>뒤</p>';
$tokens = sr_link_card_extract_tokens($fixture);
if (count($tokens) !== 1) {
    sr_link_card_check_error('Link card token extraction must find one token after HTML round-trip fixture.');
} elseif ((string) ($tokens[0]['module'] ?? '') !== 'community' || (string) ($tokens[0]['entity_type'] ?? '') !== 'post' || (string) ($tokens[0]['entity_id'] ?? '') !== '7') {
    sr_link_card_check_error('Link card token fields were not parsed correctly.');
}

$refs = sr_link_card_normalized_refs($fixture . $fixture);
if (count($refs) !== 1) {
    sr_link_card_check_error('Duplicate link card tokens must reconcile into one reference row.');
}

$sanitized = sr_sanitize_rich_text_html($fixture);
if (count(sr_link_card_extract_tokens($sanitized)) !== 1) {
    sr_link_card_check_error('Rich text sanitizer must preserve valid link card token text.');
}

$fakeWidgetHtml = '<aside class="sr-link-card" data-link-card-module="community" data-entity-id="7"><a href="/community/post?id=7">가짜 카드</a></aside>';
if (sr_link_card_extract_tokens($fakeWidgetHtml) !== []) {
    sr_link_card_check_error('Fake widget HTML must not be treated as a trusted link card token.');
}

$invalidToken = '{{sr_link_card module="community" entity_type="post" entity_id="0"}}';
if (sr_link_card_normalized_refs($invalidToken) !== []) {
    sr_link_card_check_error('Invalid link card tokens must not produce reference rows.');
}

$braceLabelToken = '{{sr_link_card module="community" entity_type="post" entity_id="7" variant="compact" label="중괄호 제목" slot="body"}}';
if (count(sr_link_card_extract_tokens($braceLabelToken)) !== 1) {
    sr_link_card_check_error('Sanitized picker labels must keep generated link card tokens parseable.');
}

$renderPdo = new PDO('sqlite::memory:');
$renderPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$renderPdo->exec('CREATE TABLE sr_modules (id INTEGER PRIMARY KEY, module_key TEXT NOT NULL, status TEXT NOT NULL)');
$blockRendered = sr_link_card_render_body($renderPdo, '<p>' . $braceLabelToken . '</p>');
if (str_contains($blockRendered, '<p><aside') || !str_contains($blockRendered, '<aside class="sr-link-card')) {
    sr_link_card_check_error('Block link card tokens wrapped by an editor paragraph must render without invalid p > aside markup.');
}

$mixedBlockRendered = sr_link_card_render_body($renderPdo, '<p>앞 ' . $braceLabelToken . ' 뒤</p>');
if (str_contains($mixedBlockRendered, '<p>앞 <aside') || !str_contains($mixedBlockRendered, '<p>앞 </p><aside') || !str_contains($mixedBlockRendered, '</aside><p> 뒤</p>')) {
    sr_link_card_check_error('Block link card tokens inserted in the middle of an editor paragraph must split into valid block markup.');
}

$formattedBlockRendered = sr_link_card_render_body($renderPdo, '<p id="dup"><strong>앞 ' . $braceLabelToken . ' 뒤</strong></p>');
if (str_contains($formattedBlockRendered, '<strong>앞 </p>') || str_contains($formattedBlockRendered, 'id="dup"') || !str_contains($formattedBlockRendered, '<p><strong>앞 </strong></p><aside')) {
    sr_link_card_check_error('Formatted paragraph fragments around block link cards must render as balanced plain paragraphs without duplicated attributes.');
}

$headingBlockRendered = sr_link_card_render_body($renderPdo, '<h2>앞 ' . $braceLabelToken . ' 뒤</h2>');
if (str_contains($headingBlockRendered, '<h2>앞 <aside') || !str_contains($headingBlockRendered, '<h2>앞 </h2><aside') || !str_contains($headingBlockRendered, '</aside><h2> 뒤</h2>')) {
    sr_link_card_check_error('Heading fragments around block link cards must render outside phrasing-only heading markup.');
}

$rootInlineBlockRendered = sr_link_card_render_body($renderPdo, '<strong>앞 ' . $braceLabelToken . ' 뒤</strong>');
if (str_contains($rootInlineBlockRendered, '<strong>앞 <aside') || !str_contains($rootInlineBlockRendered, '<p><strong>앞 </strong></p><aside') || !str_contains($rootInlineBlockRendered, '</aside><p><strong> 뒤</strong></p>')) {
    sr_link_card_check_error('Root inline wrappers around block link cards must split into valid block markup.');
}

$linkedBlockRendered = sr_link_card_render_body($renderPdo, '<p><a href="/x">앞 ' . $braceLabelToken . ' 뒤</a></p>');
if (str_contains($linkedBlockRendered, '<a href="/x" rel="nofollow noopener noreferrer">앞 <aside') || !str_contains($linkedBlockRendered, '<p><a href="/x" rel="nofollow noopener noreferrer">앞 </a></p><aside') || !str_contains($linkedBlockRendered, '</aside><p><a href="/x" rel="nofollow noopener noreferrer"> 뒤</a></p>')) {
    sr_link_card_check_error('Linked paragraph fragments around block link cards must preserve the anchor on both sides.');
}

$nestedLinkedBlockRendered = sr_link_card_render_body($renderPdo, '<p><a href="/x"><strong>앞 ' . $braceLabelToken . ' 뒤</strong></a></p>');
if (!str_contains($nestedLinkedBlockRendered, '<p><a href="/x" rel="nofollow noopener noreferrer"><strong>앞 </strong></a></p><aside') || !str_contains($nestedLinkedBlockRendered, '</aside><p><a href="/x" rel="nofollow noopener noreferrer"><strong> 뒤</strong></a></p>')) {
    sr_link_card_check_error('Nested linked formatting around block link cards must preserve balanced inline wrappers on both sides.');
}

$rootLinkedBlockRendered = sr_link_card_render_body($renderPdo, '<a href="/x">앞 ' . $braceLabelToken . ' 뒤</a>');
if (!str_contains($rootLinkedBlockRendered, '<p><a href="/x" rel="nofollow noopener noreferrer">앞 </a></p><aside') || !str_contains($rootLinkedBlockRendered, '</aside><p><a href="/x" rel="nofollow noopener noreferrer"> 뒤</a></p>')) {
    sr_link_card_check_error('Root anchor wrappers around block link cards must split into valid linked paragraphs.');
}

$unsafeLinkedBlockRendered = sr_link_card_render_body($renderPdo, '<p><a href="javascript:alert(1)" onclick="alert(1)">앞 ' . $braceLabelToken . ' 뒤</a><img src="http://example.test/x.png" onerror="alert(1)"></p>');
if (str_contains($unsafeLinkedBlockRendered, 'javascript:') || str_contains($unsafeLinkedBlockRendered, 'onclick=') || str_contains($unsafeLinkedBlockRendered, 'onerror=') || str_contains($unsafeLinkedBlockRendered, '<img')) {
    sr_link_card_check_error('Link card fragment rendering must not preserve unsafe link or image attributes when splitting block cards.');
}

$inlineToken = '{{sr_link_card module="community" entity_type="post" entity_id="7" variant="inline" label="인라인" slot="body"}}';
$inlineRendered = sr_link_card_render_body($renderPdo, '<p>앞 ' . $inlineToken . ' 뒤</p>');
if (!str_contains($inlineRendered, '<p>앞 <span class="sr-link-card sr-link-card-inline') || str_contains($inlineRendered, '<aside')) {
    sr_link_card_check_error('Inline link card tokens must render as inline markup inside paragraphs.');
}

if ($errors !== []) {
    fwrite(STDERR, "link card checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "link card checks completed.\n";
