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
$contentRecords = $read('modules/content/helpers/records.php');
$contentView = $read('modules/content/views/admin-contents.php');
$quizHelpers = $read('modules/quiz/helpers.php');
$quizAdminHelpers = $read('modules/quiz/helpers/admin.php');
$quizAction = $read('modules/quiz/actions/admin-quiz.php');
$surveyAdminHelpers = $read('modules/survey/helpers/admin-surveys.php');
$surveyAction = $read('modules/survey/actions/admin-surveys.php');

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

if ($errors !== []) {
    fwrite(STDERR, "delete state admin guard checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "delete state admin guard checks completed.\n";
