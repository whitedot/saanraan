#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_doc_links_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_doc_links_markdown_files(string $root): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $entry): bool {
                $path = str_replace('\\', '/', $entry->getPathname());
                if ($entry->isDir()) {
                    foreach ([
                        './.git',
                        './.tools',
                        './vendor',
                        './modules/ckeditor/vendor',
                    ] as $skipDir) {
                        if ($path === $skipDir || str_starts_with($path, $skipDir . '/')) {
                            return false;
                        }
                    }
                }

                return true;
            }
        )
    );

    foreach ($iterator as $entry) {
        if (!$entry instanceof SplFileInfo || !$entry->isFile()) {
            continue;
        }
        if (strtolower($entry->getExtension()) !== 'md') {
            continue;
        }

        $files[] = $entry->getPathname();
    }

    sort($files);
    return $files;
}

function sr_doc_links_is_external_or_special(string $target): bool
{
    if ($target === '' || str_starts_with($target, '#')) {
        return true;
    }

    if (preg_match('#\A(?:https?:|mailto:|tel:)#i', $target) === 1) {
        return true;
    }

    if (preg_match('#\A[a-z][a-z0-9+.-]*:#i', $target) === 1) {
        return true;
    }

    return false;
}

function sr_doc_links_normalize_target(string $target): string
{
    $target = trim($target);
    if (str_starts_with($target, '<') && str_ends_with($target, '>')) {
        $target = substr($target, 1, -1);
    }

    $hashPosition = strpos($target, '#');
    if ($hashPosition !== false) {
        $target = substr($target, 0, $hashPosition);
    }

    $queryPosition = strpos($target, '?');
    if ($queryPosition !== false) {
        $target = substr($target, 0, $queryPosition);
    }

    return rawurldecode($target);
}

function sr_doc_links_resolve_path(string $sourceFile, string $target): string
{
    $target = sr_doc_links_normalize_target($target);
    if ($target === '') {
        return '';
    }

    if (str_starts_with($target, '/')) {
        return '.' . $target;
    }

    return dirname($sourceFile) . '/' . $target;
}

function sr_doc_links_is_existing_path(string $path): bool
{
    if ($path === '') {
        return true;
    }

    $realRoot = realpath('.');
    $realTarget = realpath($path);
    if (!is_string($realRoot) || !is_string($realTarget)) {
        return false;
    }

    return $realTarget === $realRoot || str_starts_with($realTarget, $realRoot . DIRECTORY_SEPARATOR);
}

function sr_doc_links_check_tool_references(string $file, string $contents): void
{
    if (preg_match_all('#(?<![\w/.-])(?:php\s+)?(\.tools/bin/[A-Za-z0-9_.-]+\.php)#', $contents, $matches, PREG_SET_ORDER) === false) {
        return;
    }

    foreach ($matches as $match) {
        $toolPath = (string) ($match[1] ?? '');
        if ($toolPath === '') {
            continue;
        }

        if (!is_file($toolPath)) {
            sr_doc_links_error('missing documented tool reference in ' . $file . ': ' . $toolPath);
        }
    }
}

foreach (sr_doc_links_markdown_files('.') as $file) {
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        sr_doc_links_error('cannot read markdown file: ' . $file);
        continue;
    }

    if (preg_match_all('/(?<!!)\[[^\]\n]+\]\(([^)\n]+)\)/', $contents, $matches, PREG_SET_ORDER) !== false) {
        foreach ($matches as $match) {
            $target = trim((string) ($match[1] ?? ''));
            if (sr_doc_links_is_external_or_special($target)) {
                continue;
            }

            $resolved = sr_doc_links_resolve_path($file, $target);
            if (!sr_doc_links_is_existing_path($resolved)) {
                sr_doc_links_error('broken markdown link in ' . $file . ': ' . $target . ' -> ' . $resolved);
            }
        }
    }

    sr_doc_links_check_tool_references($file, $contents);
}

if ($errors !== []) {
    fwrite(STDERR, "documentation link checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "documentation link checks completed.\n";
