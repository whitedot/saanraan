<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/markdown_editor/helpers.php';

$css = sr_markdown_editor_css($pdo);
$hash = sr_markdown_editor_profile_hash($pdo);
$requestedHash = trim((string) ($_GET['v'] ?? ''));

header('Content-Type: text/css; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: ' . ($requestedHash === $hash ? 'public, max-age=31536000, immutable' : 'no-cache'));
echo $css;
