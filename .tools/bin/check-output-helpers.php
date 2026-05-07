#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('TOY_ROOT', $root);

require_once $root . '/core/helpers/runtime.php';
require_once $root . '/core/helpers/settings.php';
require_once $root . '/core/helpers/output.php';

$errors = [];

function toy_output_helper_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$_SERVER['SCRIPT_NAME'] = '/index.php';

toy_output_helper_assert(
    toy_is_safe_relative_url('/account'),
    'Normal absolute relative path should be allowed.'
);
toy_output_helper_assert(
    toy_is_safe_relative_url('/login?next=%2Fadmin'),
    'Relative path with query should be allowed.'
);
toy_output_helper_assert(
    !toy_is_safe_relative_url('//example.com'),
    'Protocol-relative URL should be rejected.'
);
toy_output_helper_assert(
    !toy_is_safe_relative_url('/\\example.com'),
    'Backslash URL should be rejected.'
);
toy_output_helper_assert(
    !toy_is_safe_relative_url("/account\nSet-Cookie: bad=1"),
    'Control characters should be rejected.'
);
toy_output_helper_assert(
    toy_url('/\\example.com') === '/',
    'Unsafe relative URL should fall back to the site root.'
);
toy_output_helper_assert(
    toy_absolute_url(['base_url' => 'https://example.com/base?bad=1'], '/login') === '/login',
    'Absolute URL should reject site base URLs with query strings.'
);
toy_output_helper_assert(
    toy_absolute_url(['base_url' => 'https://example.com/base'], '/\\evil.test') === 'https://example.com/base/',
    'Absolute URL should replace unsafe paths with the site root path.'
);
toy_output_helper_assert(
    toy_load_translations('ko', '0module') === [],
    'Translation loader should reject module keys outside the shared module key policy.'
);
toy_output_helper_assert(
    toy_download_content_type("application/json; charset=UTF-8\r\nX-Bad: 1") === 'application/octet-stream',
    'Download content type should reject header control characters.'
);
toy_output_helper_assert(
    toy_download_content_type('application/json; charset=UTF-8') === 'application/json; charset=UTF-8',
    'Download content type should allow normal MIME values with charset.'
);
toy_output_helper_assert(
    toy_download_filename("../report\r\nInjected: yes.json") === 'report-Injected-yes.json',
    'Download filename should remove path and header separator characters.'
);
toy_output_helper_assert(
    toy_download_filename("\r\n") === 'download.bin',
    'Download filename should fall back when no safe characters remain.'
);
$_POST = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 256),
    'array_value' => ['abc'],
];
toy_output_helper_assert(
    toy_post_string_without_truncation('short_value', 255) === 'abc',
    'Untruncated POST helper should return values within the limit.'
);
toy_output_helper_assert(
    toy_post_string_without_truncation('long_value', 255) === null,
    'Untruncated POST helper should reject overlong values.'
);
toy_output_helper_assert(
    toy_post_string_without_truncation('array_value', 255) === null,
    'Untruncated POST helper should reject array values.'
);
$_GET = [
    'short_value' => 'abc',
    'long_value' => str_repeat('a', 65),
    'array_value' => ['abc'],
];
toy_output_helper_assert(
    toy_get_string_without_truncation('short_value', 64) === 'abc',
    'Untruncated GET helper should return values within the limit.'
);
toy_output_helper_assert(
    toy_get_string_without_truncation('long_value', 64) === null,
    'Untruncated GET helper should reject overlong values.'
);
toy_output_helper_assert(
    toy_get_string_without_truncation('array_value', 64) === null,
    'Untruncated GET helper should reject array values.'
);

$outputHelper = file_get_contents($root . '/core/helpers/output.php');
if (is_string($outputHelper)) {
    toy_output_helper_assert(
        strpos($outputHelper, 'toy_load_module_contract_file($rendererModuleKey, $file)') !== false
            && strpos($outputHelper, 'catch (Throwable $exception)') !== false
            && strpos($outputHelper, "toy_log_exception(\$exception, 'module_output_slot_failed_' . \$rendererModuleKey)") !== false,
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
