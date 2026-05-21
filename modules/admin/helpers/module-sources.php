<?php

declare(strict_types=1);

function sr_admin_parse_upload_size(string $value): int
{
    return sr_parse_upload_size($value);
}

function sr_admin_module_upload_limit_bytes(): int
{
    return sr_module_source_upload_limit_bytes();
}

function sr_admin_module_sources_enabled(PDO $pdo, ?array $config = null): bool
{
    return sr_module_sources_enabled($pdo, $config);
}

function sr_admin_module_uncompressed_limit_bytes(): int
{
    return sr_module_source_uncompressed_limit_bytes();
}

function sr_admin_format_bytes(int $bytes): string
{
    return sr_format_bytes($bytes);
}

function sr_admin_module_source_root(): string
{
    return sr_module_source_root();
}

function sr_admin_module_work_dir(string $type): string
{
    return sr_module_work_dir($type);
}

function sr_admin_runtime_is_production(?array $config = null): bool
{
    return sr_runtime_is_production($config);
}

function sr_admin_random_suffix(): string
{
    return sr_random_suffix();
}

function sr_admin_remove_directory(string $directory): void
{
    sr_remove_directory($directory);
}

function sr_admin_copy_directory(string $source, string $target): void
{
    sr_copy_directory($source, $target);
}

function sr_admin_zip_entry_is_safe(string $name): bool
{
    return sr_module_zip_entry_is_safe($name);
}

function sr_admin_zip_entry_is_symlink(ZipArchive $zip, int $index): bool
{
    return sr_module_zip_entry_is_symlink($zip, $index);
}

function sr_admin_zip_upload_stats(ZipArchive $zip): array
{
    return sr_module_zip_upload_stats($zip);
}

function sr_admin_path_is_inside(string $path, string $root): bool
{
    return sr_path_is_inside($path, $root);
}

function sr_admin_validate_extracted_module_tree(string $extractDir): void
{
    sr_validate_extracted_module_tree($extractDir);
}

function sr_admin_infer_module_key_from_filename(string $filename): string
{
    return sr_infer_module_key_from_filename($filename);
}

function sr_admin_php_string_array_value(string $content, string $key): string
{
    return sr_php_string_array_value($content, $key);
}

function sr_admin_php_array_block(string $content, string $key): string
{
    return sr_php_array_block($content, $key);
}

function sr_admin_php_balanced_block(string $content, int $openOffset, string $openChar, string $closeChar): string
{
    return sr_php_balanced_block($content, $openOffset, $openChar, $closeChar);
}

function sr_admin_php_string_list_array_value(string $content, string $key): array
{
    return sr_php_string_list_array_value($content, $key);
}

function sr_admin_php_saanraan_metadata(string $content): array
{
    return sr_php_saanraan_metadata($content);
}

function sr_admin_load_module_metadata_from_file(string $file): array
{
    return sr_load_module_metadata_from_file($file);
}

function sr_admin_module_source_candidate(array $candidate): ?array
{
    return sr_module_source_candidate($candidate);
}

function sr_admin_find_module_source(string $extractDir, string $requestedModuleKey, string $filename): array
{
    return sr_find_module_source($extractDir, $requestedModuleKey, $filename);
}

function sr_admin_validate_module_source(string $moduleKey, string $sourceDir, array $metadata): array
{
    return sr_validate_module_source($moduleKey, $sourceDir, $metadata);
}

function sr_admin_module_metadata_errors(array $metadata): array
{
    return sr_module_metadata_errors($metadata);
}

function sr_admin_module_upload_version_errors(PDO $pdo, string $moduleKey, array $metadata, bool $allowDowngrade): array
{
    return sr_module_upload_version_errors($pdo, $moduleKey, $metadata, $allowDowngrade);
}

function sr_admin_module_replace_errors(string $moduleKey, bool $replaceConfirmed): array
{
    return sr_module_replace_errors($moduleKey, $replaceConfirmed);
}

function sr_admin_extract_module_upload(array $file, string $requestedModuleKey): array
{
    return sr_extract_module_upload($file, $requestedModuleKey);
}

function sr_admin_install_module_source_files(string $moduleKey, string $sourceDir): array
{
    return sr_install_module_source_files($moduleKey, $sourceDir);
}

function sr_admin_module_pending_update_counts(array $pendingUpdates): array
{
    return sr_module_pending_update_counts($pendingUpdates);
}

function sr_admin_sync_module_version(PDO $pdo, string $moduleKey, string $newVersion): void
{
    sr_sync_module_version($pdo, $moduleKey, $newVersion);
}

function sr_admin_sync_file_only_module_versions(PDO $pdo, array $pendingUpdateCounts): array
{
    return sr_sync_file_only_module_versions($pdo, $pendingUpdateCounts);
}
