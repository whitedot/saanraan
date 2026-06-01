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
