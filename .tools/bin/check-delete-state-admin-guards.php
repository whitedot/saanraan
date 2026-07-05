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
    $quizAdminHelpers,
    'function sr_quiz_permanently_delete',
    'Quiz must have a permanent delete helper.'
);
$assertContains(
    $quizAdminHelpers,
    'DELETE FROM sr_quiz_comments WHERE quiz_id = :quiz_id',
    'Quiz permanent delete must remove quiz comments.'
);
$assertContains(
    $quizHelpers,
    'sr_quiz_record_storage_cleanup_pending',
    'Quiz cover image deletion must pre-record storage cleanup attempts.'
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
    $surveyAdminHelpers,
    'function sr_survey_permanently_delete',
    'Survey must have a permanent delete helper.'
);
$assertContains(
    $surveyAdminHelpers,
    'DELETE FROM sr_survey_comments WHERE survey_id = :survey_id',
    'Survey permanent delete must remove survey comments.'
);
$assertContains(
    $surveyHelpers,
    'sr_survey_record_storage_cleanup_pending',
    'Survey cover image deletion must pre-record storage cleanup attempts.'
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

if ($errors !== []) {
    fwrite(STDERR, "delete state admin guard checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "delete state admin guard checks completed.\n";
