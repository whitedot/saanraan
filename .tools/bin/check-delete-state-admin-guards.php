<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$read = static function (string $path) use ($root, &$errors): string {
    $fullPath = $root . '/' . ltrim($path, '/');
    $content = @file_get_contents($fullPath);
    if (!is_string($content)) {
        $errors[] = 'Cannot read ' . $path . '.';
        return '';
    }

    return $content;
};

$assertContains = static function (string $content, string $needle, string $message) use (&$errors): void {
    if ($content === '' || strpos($content, $needle) === false) {
        $errors[] = $message;
    }
};

$contentAdmin = $read('modules/content/actions/admin-contents.php');
$contentCopyAction = $read('modules/content/actions/admin-content-copy.php');
$contentDeleteAction = $read('modules/content/actions/admin-content-delete.php');
$contentRecords = $read('modules/content/helpers/records.php');
$contentView = $read('modules/content/views/admin-contents.php');
$adminShell = $read('modules/admin/assets/admin-shell.js');

$assertContains(
    $contentAdmin,
    "|| (string) (\$editPage['status'] ?? '') === 'deleted'",
    'Content edit GET must reject deleted content.'
);
$assertContains(
    $contentCopyAction,
    "삭제된 콘텐츠는 복사할 수 없습니다.",
    'Content copy POST must reject deleted content.'
);
$assertContains(
    $contentRecords,
    "삭제된 콘텐츠는 복사할 수 없습니다.",
    'Content copy helper must reject deleted content.'
);
$assertContains(
    $contentDeleteAction,
    "\$intent === 'permanent_delete'",
    'Content delete action must expose permanent delete intent.'
);
$assertContains(
    $contentDeleteAction,
    "require_once SR_ROOT . '/modules/reaction/public-reaction.php'",
    'Content permanent delete action must load the reaction public contract when reaction is enabled.'
);
$assertContains(
    $contentRecords,
    'function sr_content_permanently_delete',
    'Content must have a permanent delete helper.'
);
$assertContains(
    $contentRecords,
    'DELETE FROM sr_content_access_entitlements WHERE content_id = :content_id',
    'Content permanent delete must remove live access entitlements.'
);
$assertContains(
    $contentRecords,
    'DELETE FROM sr_content_comments WHERE content_id = :content_id',
    'Content permanent delete must remove content comments.'
);
$assertContains(
    $contentView,
    'preserved_log_count',
    'Content deleted view must show preserved log count.'
);
$assertContains(
    $contentView,
    'cleanup_pending_count',
    'Content deleted view must show cleanup pending count.'
);
$assertContains(
    $contentView,
    'data-confirm-phrase-alt',
    'Content permanent delete modal must allow ID confirmation as an alternate phrase.'
);
$assertContains(
    $contentRecords,
    '$confirmationPhrase !== $slug && $confirmationPhrase !== (string) $pageId',
    'Content permanent delete server validation must accept content ID or slug.'
);
$assertContains(
    $contentRecords,
    'sr_url_embed_delete_owner_or_target_url_cache($pdo, \'content\', \'content\', $pageId)',
    'Content permanent delete must remove owner/target URL embed cache rows.'
);
$assertContains(
    $contentRecords,
    'sr_reaction_delete_target_records($pdo, \'content\', \'comment\', $commentIds)',
    'Content permanent delete must remove content/comment reaction records.'
);
$assertContains(
    $contentRecords,
    'sr_reaction_delete_target_records($pdo, \'content\', \'content\', [$pageId])',
    'Content permanent delete must remove content target reaction records.'
);
$assertContains(
    $contentView,
    "\$pageIsDeleted = \$pageStatus === 'deleted';",
    'Content admin list must detect deleted rows.'
);
$assertContains(
    $contentView,
    'sr_content_admin_status_filter_options()',
    'Content admin status filter must include deleted without adding it to save statuses.'
);
$assertContains(
    $contentView,
    "\$pageIsDeleted ? ' disabled' : ''",
    'Content admin list must disable bulk controls for deleted rows.'
);


$assertContains(
    $adminShell,
    'validateConfirmPhraseFields',
    'Admin shell must validate destructive confirmation phrase inputs.'
);
$assertContains(
    $adminShell,
    'data-confirm-phrase-alt',
    'Admin shell must validate alternate destructive confirmation phrases.'
);

if ($errors !== []) {
    fwrite(STDERR, "delete state admin guard checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "delete state admin guard checks completed.\n";
