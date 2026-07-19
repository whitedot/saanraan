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
$quizHelpers = $read('modules/quiz/helpers.php');
$quizAdminHelpers = $read('modules/quiz/helpers/admin.php');
$quizAction = $read('modules/quiz/actions/admin-quiz.php');
$quizInstall = $read('modules/quiz/install.sql');
$quizUpdate = $read('modules/quiz/updates/2026.07.001.sql');
$adminShell = $read('modules/admin/assets/admin-shell.js');
$surveyHelpers = $read('modules/survey/helpers.php');
$surveyAdminHelpers = $read('modules/survey/helpers/admin-surveys.php');
$surveyAction = $read('modules/survey/actions/admin-surveys.php');
$surveyComments = $read('modules/survey/helpers/comments.php');
$surveyInstall = $read('modules/survey/install.sql');
$surveyUpdate = $read('modules/survey/updates/2026.07.001.sql');

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
    $quizHelpers,
    "'deleted' => sr_get_string('deleted', 1) === '1'",
    'Quiz admin filters must expose deleted view state.'
);
$assertContains(
    $quizHelpers,
    "\$where = !empty(\$filters['deleted']) ? ['q.deleted_at IS NOT NULL'] : ['q.deleted_at IS NULL'];",
    'Quiz admin list must use deleted_at for active/deleted views.'
);
$assertContains(
    $quizAdminHelpers,
    'SELECT id FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1',
    'Quiz copy validation must reject deleted source rows.'
);
$assertContains(
    $quizAction,
    "\$quizIsDeleted = !empty(\$quiz['deleted_at']);",
    'Quiz admin list must detect deleted rows.'
);
$assertContains(
    $quizAction,
    "if (!\$quizIsDeleted)",
    'Quiz admin list must hide row actions for deleted rows.'
);
$assertContains(
    $quizAction,
    "name=\"intent\" value=\"permanent_delete\"",
    'Quiz admin deleted view must expose permanent delete POST intent.'
);
$assertContains(
    $quizAction,
    'reward_grant_count',
    'Quiz deleted view must show preserved reward grant count.'
);
$assertContains(
    $quizAction,
    'cleanup_pending_count',
    'Quiz deleted view must show cleanup pending count.'
);
$assertContains(
    $quizAction,
    'data-confirm-phrase-alt',
    'Quiz permanent delete modal must allow ID confirmation as an alternate phrase.'
);
$assertContains(
    $quizAdminHelpers,
    'function sr_quiz_permanently_delete',
    'Quiz must have a permanent delete helper.'
);
$assertContains(
    $quizAdminHelpers,
    '$confirmationPhrase !== $quizKey && $confirmationPhrase !== (string) $quizId',
    'Quiz permanent delete server validation must accept quiz ID or key.'
);
$assertContains(
    $quizAdminHelpers,
    'DELETE FROM sr_quiz_comments WHERE quiz_id = :quiz_id',
    'Quiz permanent delete must remove quiz comments.'
);
$assertContains(
    $quizAdminHelpers,
    'sr_url_embed_delete_owner_or_target_url_cache($pdo, \'quiz\', \'quiz_set\', $quizId)',
    'Quiz permanent delete must remove owner/target URL embed cache rows.'
);
$assertContains(
    $quizAdminHelpers,
    'sr_reaction_delete_target_records($pdo, \'quiz\', \'comment\', $commentIds)',
    'Quiz permanent delete must remove quiz/comment reaction records.'
);
$assertContains(
    $quizAdminHelpers,
    'sr_reaction_delete_target_records($pdo, \'quiz\', \'quiz_set\', [$quizId])',
    'Quiz permanent delete must remove quiz target reaction records.'
);
$assertContains(
    $quizHelpers,
    'sr_quiz_record_storage_cleanup_pending',
    'Quiz cover image deletion must pre-record storage cleanup attempts.'
);
$assertContains(
    $quizAdminHelpers,
    "\$coverImageCleanupFailureId = sr_quiz_record_storage_cleanup_pending",
    'Quiz soft delete must record cleanup pending before commit.'
);
$assertContains(
    $quizInstall,
    'CREATE TABLE IF NOT EXISTS sr_quiz_storage_cleanup_failures',
    'Quiz install schema must include storage cleanup failures.'
);
$assertContains(
    $quizUpdate,
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}quiz_storage_cleanup_failures',
    'Quiz update schema must add storage cleanup failures.'
);

$assertContains(
    $surveyAdminHelpers,
    "\$deletedView = sr_get_string('deleted', 1) === '1';",
    'Survey admin filters must expose deleted view state.'
);
$assertContains(
    $surveyAdminHelpers,
    "\$listWhere = [\$deletedView ? 's.deleted_at IS NOT NULL' : 's.deleted_at IS NULL'];",
    'Survey admin list must use deleted_at for active/deleted views.'
);
$assertContains(
    $surveyAction,
    "\$surveyIsDeleted = !empty(\$survey['deleted_at']);",
    'Survey admin list must detect deleted rows.'
);
$assertContains(
    $surveyAction,
    "if (!\$surveyIsDeleted):",
    'Survey admin list must hide row actions for deleted rows.'
);
$assertContains(
    $surveyAction,
    "name=\"intent\" value=\"permanent_delete\"",
    'Survey admin deleted view must expose permanent delete POST intent.'
);
$assertContains(
    $surveyAction,
    'reward_grant_count',
    'Survey deleted view must show preserved reward grant count.'
);
$assertContains(
    $surveyAction,
    'cleanup_pending_count',
    'Survey deleted view must show cleanup pending count.'
);
$assertContains(
    $surveyAction,
    'data-confirm-phrase-alt',
    'Survey permanent delete modal must allow ID confirmation as an alternate phrase.'
);
$assertContains(
    $surveyAdminHelpers,
    'function sr_survey_permanently_delete',
    'Survey must have a permanent delete helper.'
);
$assertContains(
    $surveyAdminHelpers,
    '$confirmationPhrase !== $surveyKey && $confirmationPhrase !== (string) $surveyId',
    'Survey permanent delete server validation must accept survey ID or key.'
);
$assertContains(
    $surveyAdminHelpers,
    'DELETE FROM sr_survey_comments WHERE survey_id = :survey_id',
    'Survey permanent delete must remove survey comments.'
);
$assertContains(
    $surveyAdminHelpers,
    'sr_url_embed_delete_owner_or_target_url_cache($pdo, \'survey\', \'survey_form\', $surveyId)',
    'Survey permanent delete must remove owner/target URL embed cache rows.'
);
$assertContains(
    $surveyAdminHelpers,
    'sr_reaction_delete_target_records($pdo, \'survey\', \'comment\', $commentIds)',
    'Survey permanent delete must remove survey/comment reaction records.'
);
$assertContains(
    $surveyAdminHelpers,
    'sr_reaction_delete_target_records($pdo, \'survey\', \'survey_form\', [$surveyId])',
    'Survey permanent delete must remove survey target reaction records.'
);
$assertContains(
    $surveyHelpers,
    'sr_survey_record_storage_cleanup_pending',
    'Survey cover image deletion must pre-record storage cleanup attempts.'
);
$assertContains(
    $surveyComments,
    "\$coverImageCleanupFailureId = sr_survey_record_storage_cleanup_pending",
    'Survey soft delete must record cleanup pending before commit.'
);
$assertContains(
    $surveyInstall,
    'CREATE TABLE IF NOT EXISTS sr_survey_storage_cleanup_failures',
    'Survey install schema must include storage cleanup failures.'
);
$assertContains(
    $surveyUpdate,
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}survey_storage_cleanup_failures',
    'Survey update schema must add storage cleanup failures.'
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
