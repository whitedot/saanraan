#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/output.php';
require_once $root . '/modules/site_menu/helpers.php';

$errors = [];

function sr_output_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

sr_output_helper_assert(
    sr_is_safe_relative_url('/account'),
    'Normal absolute relative path should be allowed.'
);
sr_output_helper_assert(
    sr_is_safe_relative_url('/login?next=%2Fadmin'),
    'Relative path with query should be allowed.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url('//example.com'),
    'Protocol-relative URL should be rejected.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url('/\\example.com'),
    'Backslash URL should be rejected.'
);
sr_output_helper_assert(
    !sr_is_safe_relative_url("/account\nSet-Cookie: bad=1"),
    'Control characters should be rejected.'
);
sr_output_helper_assert(
    sr_url('/\\example.com') === '/',
    'Unsafe relative URL should fall back to the site root.'
);
$_SERVER['SCRIPT_NAME'] = '/saanraan/index.php';
sr_output_helper_assert(
    sr_url('/') === '/saanraan/',
    'Root URL should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('/') === '/saanraan/',
    'Site menu root item should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('/login') === '/saanraan/login',
    'Site menu internal item should include the installed base path.'
);
sr_output_helper_assert(
    sr_site_menu_item_href('https://example.com/') === 'https://example.com/',
    'Site menu external item should keep the original URL.'
);
$_SERVER['SCRIPT_NAME'] = '/index.php';
sr_output_helper_assert(
    sr_absolute_url(['base_url' => 'https://example.com/base?bad=1'], '/login') === '/login',
    'Absolute URL should reject site base URLs with query strings.'
);
sr_output_helper_assert(
    sr_absolute_url(['base_url' => 'https://example.com/base'], '/\\evil.test') === 'https://example.com/base/',
    'Absolute URL should replace unsafe paths with the site root path.'
);
sr_output_helper_assert(
    sr_load_translations('ko', '0module') === [],
    'Translation loader should reject module keys outside the shared module key policy.'
);
sr_output_helper_assert(
    sr_download_content_type("application/json; charset=UTF-8\r\nX-Bad: 1") === 'application/octet-stream',
    'Download content type should reject header control characters.'
);
sr_output_helper_assert(
    sr_download_content_type('application/json; charset=UTF-8') === 'application/json; charset=UTF-8',
    'Download content type should allow normal MIME values with charset.'
);
sr_output_helper_assert(
    sr_download_filename("../report\r\nInjected: yes.json") === 'report-Injected-yes.json',
    'Download filename should remove path and header separator characters.'
);
sr_output_helper_assert(
    sr_download_filename("\r\n") === 'download.bin',
    'Download filename should fall back when no safe characters remain.'
);
sr_output_helper_assert(
    sr_public_layout_key(['public_layout_key' => 'basic']) === 'basic',
    'Known public layout key should be accepted.'
);
sr_output_helper_assert(
    sr_public_layout_key(['public_layout_key' => '../basic']) === 'basic',
    'Unknown public layout key should fall back to basic.'
);
sr_output_helper_assert(
    sr_public_layout_file('basic') === $root . '/layouts/public/basic/layout.php',
    'Basic public layout should resolve to the layouts directory.'
);
sr_output_helper_assert(
    sr_color_scheme(['ui_color_scheme' => 'dark']) === 'dark',
    'Known color scheme should be accepted.'
);
sr_output_helper_assert(
    sr_color_scheme(['ui_color_scheme' => 'unknown']) === 'light',
    'Unknown color scheme should fall back to light.'
);
$_POST = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 256),
    'array_value' => ['abc'],
];
sr_output_helper_assert(
    sr_post_string_without_truncation('short_value', 255) === 'abc',
    'Untruncated POST helper should return values within the limit.'
);
sr_output_helper_assert(
    sr_post_string_without_truncation('long_value', 255) === null,
    'Untruncated POST helper should reject overlong values.'
);
sr_output_helper_assert(
    sr_post_string_without_truncation('array_value', 255) === null,
    'Untruncated POST helper should reject array values.'
);
$_GET = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 65),
    'array_value' => ['abc'],
];
sr_output_helper_assert(
    sr_get_string_without_truncation('short_value', 64) === 'abc',
    'Untruncated GET helper should return values within the limit.'
);
sr_output_helper_assert(
    sr_get_string_without_truncation('long_value', 64) === null,
    'Untruncated GET helper should reject overlong values.'
);
sr_output_helper_assert(
    sr_get_string_without_truncation('array_value', 64) === null,
    'Untruncated GET helper should reject array values.'
);

$outputHelper = file_get_contents($root . '/core/helpers/output.php');
if (is_string($outputHelper)) {
    sr_output_helper_assert(
        strpos($outputHelper, 'sr_load_module_contract_file($rendererModuleKey, $file)') !== false
            && strpos($outputHelper, 'catch (Throwable $exception)') !== false
            && strpos($outputHelper, "sr_log_exception(\$exception, 'module_output_slot_failed_' . \$rendererModuleKey)") !== false,
        'Output slot rendering should fail closed for broken module contract files and renderers.'
    );
}

if ($errors !== []) {
    fwrite(STDERR, "output helper checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "output helper checks completed.\n";
