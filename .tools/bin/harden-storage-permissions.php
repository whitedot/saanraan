#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$storageRoot = $root . '/storage';
$options = getopt('', ['apply', 'owner:', 'group:', 'dir-mode:', 'file-mode:']);
$apply = array_key_exists('apply', $options);
$ownerName = trim((string) ($options['owner'] ?? 'www-data'));
$groupName = trim((string) ($options['group'] ?? 'www-data'));
$directoryModeText = trim((string) ($options['dir-mode'] ?? '0750'));
$fileModeText = trim((string) ($options['file-mode'] ?? '0640'));

function sr_storage_permission_mode(string $value, string $label): int
{
    if (preg_match('/\A[0-7]{3,4}\z/', $value) !== 1) {
        throw new InvalidArgumentException($label . ' must be a three or four digit octal mode.');
    }

    $mode = intval($value, 8);
    if (($mode & 0002) !== 0) {
        throw new InvalidArgumentException($label . ' must not grant world write permission.');
    }

    return $mode;
}

function sr_storage_permission_entries(string $storageRoot): array
{
    if (!is_dir($storageRoot) || is_link($storageRoot)) {
        throw new RuntimeException('storage root must be a real directory: ' . $storageRoot);
    }

    $directories = [$storageRoot];
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $entry) {
        $path = $entry->getPathname();
        if ($entry->isLink()) {
            throw new RuntimeException('storage permission hardening refuses symbolic links: ' . $path);
        }
        if ($entry->isDir()) {
            $directories[] = $path;
        } elseif ($entry->isFile()) {
            $files[] = $path;
        }
    }

    return [$directories, $files];
}

function sr_storage_permission_identity(string $ownerName, string $groupName): array
{
    if (!function_exists('posix_getpwnam') || !function_exists('posix_getgrnam')) {
        throw new RuntimeException('POSIX user/group lookup functions are required.');
    }
    $owner = posix_getpwnam($ownerName);
    $group = posix_getgrnam($groupName);
    if (!is_array($owner) || !isset($owner['uid'])) {
        throw new RuntimeException('storage owner does not exist: ' . $ownerName);
    }
    if (!is_array($group) || !isset($group['gid'])) {
        throw new RuntimeException('storage group does not exist: ' . $groupName);
    }

    return [(int) $owner['uid'], (int) $group['gid']];
}

try {
    $directoryMode = sr_storage_permission_mode($directoryModeText, 'dir-mode');
    $fileMode = sr_storage_permission_mode($fileModeText, 'file-mode');
    [$ownerId, $groupId] = sr_storage_permission_identity($ownerName, $groupName);
    [$directories, $files] = sr_storage_permission_entries($storageRoot);

    echo 'storage-root: ' . $storageRoot . "\n";
    echo 'target-owner-group: ' . $ownerName . ':' . $groupName . "\n";
    echo 'target-modes: directories=' . sprintf('%04o', $directoryMode) . ' files=' . sprintf('%04o', $fileMode) . "\n";
    echo 'targets: directories=' . count($directories) . ' files=' . count($files) . "\n";

    if (!$apply) {
        echo "dry-run: no permissions changed; pass --apply with sufficient privileges to apply the plan.\n";
        exit(0);
    }

    foreach ($files as $path) {
        if (!chown($path, $ownerId) || !chgrp($path, $groupId) || !chmod($path, $fileMode)) {
            throw new RuntimeException('failed to harden storage file: ' . $path);
        }
    }
    foreach (array_reverse($directories) as $path) {
        if (!chown($path, $ownerId) || !chgrp($path, $groupId) || !chmod($path, $directoryMode)) {
            throw new RuntimeException('failed to harden storage directory: ' . $path);
        }
    }

    clearstatcache(true, $storageRoot);
    [$verifiedDirectories, $verifiedFiles] = sr_storage_permission_entries($storageRoot);
    foreach ($verifiedDirectories as $path) {
        $mode = fileperms($path);
        if ($mode === false
            || ($mode & 07777) !== $directoryMode
            || fileowner($path) !== $ownerId
            || filegroup($path) !== $groupId
        ) {
            throw new RuntimeException('storage directory verification failed: ' . $path);
        }
    }
    foreach ($verifiedFiles as $path) {
        $mode = fileperms($path);
        if ($mode === false
            || ($mode & 0777) !== $fileMode
            || fileowner($path) !== $ownerId
            || filegroup($path) !== $groupId
        ) {
            throw new RuntimeException('storage file verification failed: ' . $path);
        }
    }

    echo "storage permission hardening completed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
