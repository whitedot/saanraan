#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);
if (!defined('SR_ROOT')) {
    define('SR_ROOT', $root);
}

$errors = [];

function sr_i18n_contract_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_i18n_contract_php_files(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current): bool {
                if (!$current->isDir()) {
                    return true;
                }

                $path = str_replace(DIRECTORY_SEPARATOR, '/', $current->getPathname());
                return !str_contains($path, '/vendor/');
            }
        )
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'php') {
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        }
    }

    sort($files);
    return $files;
}

function sr_i18n_contract_include_lang_file(string $file): array
{
    $translations = include $file;
    if (!is_array($translations)) {
        sr_i18n_contract_error('Translation file must return an array: ' . $file);
        return [];
    }

    foreach ($translations as $key => $value) {
        if (!is_string($key) || $key === '') {
            sr_i18n_contract_error('Translation key must be a non-empty string: ' . $file);
            continue;
        }
        if (!is_string($value)) {
            sr_i18n_contract_error('Translation value must be a string: ' . $file . ' key=' . $key);
        }
        if (preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/', $key) !== 1) {
            sr_i18n_contract_error('Translation key must use lowercase dot hierarchy: ' . $file . ' key=' . $key);
        }
    }

    return $translations;
}

function sr_i18n_contract_ko_catalogs(): array
{
    $catalogs = [];
    $coreFile = 'lang/ko/core.php';
    if (is_file($coreFile)) {
        $catalogs[''] = sr_i18n_contract_include_lang_file($coreFile);
    } else {
        sr_i18n_contract_error('Core fallback locale file is missing: ' . $coreFile);
        $catalogs[''] = [];
    }

    if (is_dir('modules')) {
        foreach (new DirectoryIterator('modules') as $entry) {
            if ($entry->isDot() || !$entry->isDir()) {
                continue;
            }

            $moduleKey = $entry->getFilename();
            if (preg_match('/\A[a-z0-9_]+\z/', $moduleKey) !== 1) {
                continue;
            }

            $langFile = 'modules/' . $moduleKey . '/lang/ko.php';
            if (is_file($langFile)) {
                $catalogs[$moduleKey] = sr_i18n_contract_include_lang_file($langFile);
            }
        }
    }

    return $catalogs;
}

function sr_i18n_contract_string_literal_value(string $literal): ?string
{
    $value = @eval('return ' . $literal . ';');
    return is_string($value) ? $value : null;
}

function sr_i18n_contract_static_sr_t_keys(string $file): array
{
    $source = file_get_contents($file);
    if (!is_string($source)) {
        sr_i18n_contract_error('Could not read PHP file: ' . $file);
        return [];
    }

    $tokens = token_get_all($source);
    $keys = [];
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (!is_array($token) || $token[0] !== T_STRING || strtolower($token[1]) !== 'sr_t') {
            continue;
        }

        $line = (int) $token[2];
        $j = $i + 1;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }
        if (($tokens[$j] ?? null) !== '(') {
            continue;
        }

        $j++;
        while ($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $j++;
        }

        $argument = $tokens[$j] ?? null;
        if (!is_array($argument) || $argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $afterArgument = $j + 1;
        while ($afterArgument < $count && is_array($tokens[$afterArgument]) && in_array($tokens[$afterArgument][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $afterArgument++;
        }
        if (($tokens[$afterArgument] ?? null) === '.') {
            continue;
        }

        $key = sr_i18n_contract_string_literal_value($argument[1]);
        if (!is_string($key) || $key === '') {
            sr_i18n_contract_error('Static sr_t key must be a non-empty string: ' . $file . ':' . (string) $line);
            continue;
        }

        $keys[] = ['key' => $key, 'line' => $line];
    }

    return $keys;
}

function sr_i18n_contract_key_parts(string $key): ?array
{
    $moduleKey = '';
    $translationKey = $key;
    if (str_contains($key, '::')) {
        [$moduleKey, $translationKey] = explode('::', $key, 2);
        if (preg_match('/\A[a-z0-9_]+\z/', $moduleKey) !== 1) {
            return null;
        }
    }

    if ($translationKey === '' || preg_match('/\A[a-z0-9][a-z0-9_.-]*\z/', $translationKey) !== 1) {
        return null;
    }

    return [$moduleKey, $translationKey];
}

$catalogs = sr_i18n_contract_ko_catalogs();
$seen = [];
foreach (['core', 'modules'] as $sourceRoot) {
    foreach (sr_i18n_contract_php_files($sourceRoot) as $file) {
        foreach (sr_i18n_contract_static_sr_t_keys($file) as $call) {
            $fullKey = (string) $call['key'];
            $line = (int) $call['line'];
            $parts = sr_i18n_contract_key_parts($fullKey);
            if ($parts === null) {
                sr_i18n_contract_error('Static sr_t key must use module::key or core dot hierarchy: ' . $file . ':' . (string) $line . ' key=' . $fullKey);
                continue;
            }

            [$moduleKey, $translationKey] = $parts;
            if (!array_key_exists($moduleKey, $catalogs)) {
                sr_i18n_contract_error('Static sr_t key references missing ko catalog: ' . $file . ':' . (string) $line . ' key=' . $fullKey);
                continue;
            }
            if (!array_key_exists($translationKey, $catalogs[$moduleKey])) {
                sr_i18n_contract_error('Static sr_t key is not declared in ko catalog: ' . $file . ':' . (string) $line . ' key=' . $fullKey);
                continue;
            }

            $seen[$fullKey] = true;
        }
    }
}

require_once 'core/helpers/output.php';
sr_translation_clear_fallback_events();
$fallbackMessage = sr_t('saanraan.name', [], 'en-US');
$fallbackEvents = sr_translation_fallback_events();
if ($fallbackMessage !== 'Saanraan') {
    sr_i18n_contract_error('sr_t should return fallback locale message for missing requested locale.');
}
if (count($fallbackEvents) !== 1 || (string) ($fallbackEvents[0]['key'] ?? '') !== 'saanraan.name' || (string) ($fallbackEvents[0]['locale'] ?? '') !== 'en-US') {
    sr_i18n_contract_error('sr_t should record fallback locale events when requested locale misses.');
}

if ($seen === []) {
    sr_i18n_contract_error('No static sr_t keys were found for declaration check.');
}

if ($errors !== []) {
    fwrite(STDERR, "i18n contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "i18n contract checks completed.\n";
