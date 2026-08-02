#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];
$files = [
    'output-localization.php',
    'output-response.php',
    'output-content.php',
    'output-editor.php',
    'output-assets.php',
    'output-layout.php',
    'output-pwa.php',
    'output-member.php',
    'output-slots.php',
    'output-http.php',
];

$entryFile = 'core/helpers/output.php';
$entrySource = file_get_contents($entryFile);
if (!is_string($entrySource)) {
    $errors[] = '코어 출력 helper 집계 파일을 읽을 수 없습니다.';
    $entrySource = '';
}
if (substr_count($entrySource, "\n") + 1 > 40 || preg_match('/^function\s+/m', $entrySource) === 1) {
    $errors[] = 'core/helpers/output.php는 구현 없이 명시적 include 순서만 유지해야 합니다.';
}

$lastIndex = -1;
$functions = [];
foreach ($files as $file) {
    $path = 'core/helpers/' . $file;
    $source = file_get_contents($path);
    if (!is_string($source)) {
        $errors[] = '코어 출력 helper 구현 파일을 읽을 수 없습니다: ' . $path;
        continue;
    }

    $marker = "require_once __DIR__ . '/" . $file . "';";
    $index = strpos($entrySource, $marker);
    if ($index === false || $index <= $lastIndex) {
        $errors[] = '코어 출력 helper include 순서가 올바르지 않습니다: ' . $file;
    }
    $lastIndex = is_int($index) ? $index : $lastIndex;

    if (substr_count($source, "\n") + 1 > 1250) {
        $errors[] = '코어 출력 helper 구현 파일이 다시 과대해졌습니다: ' . $path;
    }

    preg_match_all('/^function\s+(sr_[a-z0-9_]+)\s*\(/m', $source, $matches);
    foreach ($matches[1] ?? [] as $function) {
        if (isset($functions[$function])) {
            $errors[] = '코어 출력 helper 함수가 여러 파일에 중복 선언되었습니다: ' . $function;
        }
        $functions[$function] = $path;
    }
}

require_once $root . '/' . $entryFile;
foreach ([
    'sr_t',
    'sr_json_response',
    'sr_sanitize_rich_text_html',
    'sr_editor_options',
    'sr_stylesheet_tag',
    'sr_public_layout_begin',
    'sr_pwa_manifest_payload',
    'sr_public_layout_member_asset_rows',
    'sr_render_output_slot',
    'sr_redirect',
] as $function) {
    if (!function_exists($function)) {
        $errors[] = '코어 출력 helper 대표 함수를 불러올 수 없습니다: ' . $function;
    }
}

if ($errors !== []) {
    fwrite(STDERR, "core output structure checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'core output structure checks completed. files=' . count($files) . ' functions=' . count($functions) . "\n";
