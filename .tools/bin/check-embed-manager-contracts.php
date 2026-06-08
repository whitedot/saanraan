<?php

declare(strict_types=1);

$errors = [];

function sr_embed_contract_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_embed_contract_read(string $path): string
{
    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        sr_embed_contract_error('file cannot be read: ' . $path);
        return '';
    }

    return $contents;
}

function sr_embed_contract_contains(string $path, string $needle): void
{
    $contents = sr_embed_contract_read($path);
    if ($contents !== '' && !str_contains($contents, $needle)) {
        sr_embed_contract_error($path . ' missing marker: ' . $needle);
    }
}

foreach (['content', 'community', 'quiz', 'survey'] as $moduleKey) {
    $contractPath = 'modules/' . $moduleKey . '/embed-manager-targets.php';
    $modulePath = 'modules/' . $moduleKey . '/module.php';
    if (!is_file($contractPath)) {
        sr_embed_contract_error('embed manager contract is missing: ' . $contractPath);
        continue;
    }

    sr_embed_contract_contains($modulePath, "'embed-manager-targets.php'");
    foreach (["'target_module' => '" . $moduleKey . "'", "'allowed_variants'", "'search'", "'resolve'", "'public_url'", "'admin_url'", "'status'"] as $needle) {
        sr_embed_contract_contains($contractPath, $needle);
    }
}

sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_contract_targets');
sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_search_targets');
sr_embed_contract_contains('modules/embed_manager/helpers.php', 'function sr_embed_manager_render_body_html');
sr_embed_contract_contains('modules/embed_manager/helpers.php', '본문 임베드 표시 방식이 지원되지 않습니다.');
sr_embed_contract_contains('modules/content/helpers.php', 'sr_embed_manager_render_body_html($pdo');
sr_embed_contract_contains('modules/community/helpers/posts.php', 'sr_embed_manager_render_body_html($pdo');
sr_embed_contract_contains('modules/survey/actions/view.php', 'return_to');

if ($errors !== []) {
    fwrite(STDERR, "embed manager contract checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo 'embed manager contract checks completed.' . PHP_EOL;
