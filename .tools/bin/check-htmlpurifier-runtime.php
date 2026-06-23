#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';

$errors = [];

function sr_htmlpurifier_runtime_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_htmlpurifier_runtime_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_htmlpurifier_runtime_error($message);
    }
}

sr_htmlpurifier_runtime_assert(sr_rich_text_purifier_available(), 'HTML Purifier must be available from the bundled module autoload.');
sr_htmlpurifier_runtime_assert(class_exists('HTMLPurifier_Config'), 'HTMLPurifier_Config must be loadable.');

$status = sr_rich_text_purifier_status();
sr_htmlpurifier_runtime_assert(($status['available'] ?? false) === true, 'Purifier status must report available=true.');
sr_htmlpurifier_runtime_assert((string) ($status['version'] ?? '') === '4.19.0', 'Purifier status must report version 4.19.0.');
sr_htmlpurifier_runtime_assert((string) ($status['autoload_path'] ?? '') === 'modules/htmlpurifier/vendor/autoload.php', 'Purifier status must prefer the bundled module autoload path.');
sr_htmlpurifier_runtime_assert((string) ($status['cache_dir'] ?? '') === 'storage/cache/htmlpurifier', 'Purifier cache must use storage/cache/htmlpurifier.');
sr_htmlpurifier_runtime_assert(($status['cache_writable'] ?? false) === true, 'Purifier cache directory must be writable when reported.');

$cacheDir = $root . '/storage/cache/htmlpurifier';
sr_htmlpurifier_runtime_assert(is_dir($cacheDir), 'Purifier cache directory must exist after status/config lookup.');
sr_htmlpurifier_runtime_assert(is_writable($cacheDir), 'Purifier cache directory must be writable.');
sr_htmlpurifier_runtime_assert(!is_dir($root . '/modules/htmlpurifier/vendor/cache'), 'Purifier must not create a vendor-local cache directory.');

$config = sr_rich_text_purifier_config();
sr_htmlpurifier_runtime_assert(strtolower((string) $config->get('Core.Encoding')) === 'utf-8', 'Purifier config must use UTF-8.');
sr_htmlpurifier_runtime_assert($config->get('HTML.Doctype') === 'HTML 4.01 Transitional', 'Purifier config doctype must remain explicit.');
sr_htmlpurifier_runtime_assert(!str_contains((string) $config->get('HTML.Allowed'), 'data-sr-embed-manager'), 'Purifier config must not allow embed marker attributes.');
sr_htmlpurifier_runtime_assert($config->get('URI.AllowedSchemes') === ['http' => true, 'https' => true], 'Purifier config must allow only http and https schemes.');
sr_htmlpurifier_runtime_assert($config->get('HTML.Nofollow') === true, 'Purifier config must enable HTML.Nofollow.');
sr_htmlpurifier_runtime_assert($config->get('HTML.TargetBlank') === false, 'Purifier config must not add target blank.');
sr_htmlpurifier_runtime_assert((string) $config->get('Cache.SerializerPath') === $cacheDir, 'Purifier serializer cache path must be the storage cache directory.');

$payload = '<p onclick="bad()">Hi <a href="javascript:alert(1)" target="_blank">bad</a>'
    . '<a href="https://example.com/safe" rel="bookmark">safe</a>'
    . '<span class="foo sr-embed-manager-marker" data-sr-embed-manager-ref="em_abc1234" data-sr-embed-manager-target-module="content" data-sr-embed-manager-target-type="content" data-sr-embed-manager-target-id="12" data-sr-embed-manager-variant="card" data-sr-embed-manager-label="  Label  ">embed</span>'
    . '<img src="http://example.com/a.png" alt="bad">'
    . '<img src="https://example.com/a.png" alt="good" width="640" height="480" style="width:100%">'
    . '<svg><a xlink:href="javascript:alert(1)">x</a></svg></p>';
$sanitized = sr_sanitize_rich_text_html($payload);
sr_htmlpurifier_runtime_assert(!str_contains(strtolower($sanitized), 'javascript:'), 'Purifier-backed sanitizer output must remove javascript URLs.');
sr_htmlpurifier_runtime_assert(!str_contains(strtolower($sanitized), '<svg'), 'Purifier-backed sanitizer output must remove SVG payloads.');
sr_htmlpurifier_runtime_assert(!str_contains(strtolower($sanitized), 'onclick'), 'Purifier-backed sanitizer output must remove event handlers.');
sr_htmlpurifier_runtime_assert(!str_contains(strtolower($sanitized), 'target='), 'Purifier-backed sanitizer output must remove target attributes.');
sr_htmlpurifier_runtime_assert(!str_contains(strtolower($sanitized), 'src="http://'), 'Final canonicalizer must remove insecure external image URLs after Purifier.');
sr_htmlpurifier_runtime_assert(str_contains($sanitized, '<a href="https://example.com/safe" rel="nofollow noopener noreferrer">safe</a>'), 'Purifier-backed sanitizer output must keep safe links with server rel policy.');
sr_htmlpurifier_runtime_assert(!str_contains($sanitized, 'sr-embed-manager-marker') && !str_contains($sanitized, 'data-sr-embed-manager'), 'Purifier-backed sanitizer output must remove embed marker attributes.');
sr_htmlpurifier_runtime_assert(str_contains($sanitized, '<img src="https://example.com/a.png" alt="good" width="640" height="480">'), 'Purifier-backed sanitizer output must keep safe HTTPS images.');

if ($errors !== []) {
    fwrite(STDERR, "HTML Purifier runtime checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "HTML Purifier runtime checks completed.\n";
