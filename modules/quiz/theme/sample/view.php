<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$basePath = rtrim(sr_base_path(), '/');
$quizPath = (string) $path;
if ($basePath !== '' && str_starts_with($quizPath, $basePath . '/')) {
    $quizPath = substr($quizPath, strlen($basePath));
}
$quizKey = rawurldecode(trim(substr($quizPath, strlen('/quiz')), '/'));
if (!sr_quiz_key_is_valid($quizKey)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}

$quiz = sr_quiz_by_key($pdo, $quizKey);
$currentAccount = sr_member_current_account($pdo);
$previewMode = sr_get_string('preview', 20) === 'admin';
$adminHasQuizViewPermission = is_array($currentAccount)
    && sr_admin_has_permission($pdo, (int) ($currentAccount['id'] ?? 0), '/admin/quiz', 'view');
$isPubliclyOpen = is_array($quiz) && (string) ($quiz['status'] ?? '') === 'active' && sr_quiz_public_window_is_open($quiz);
$canPreviewAsAdmin = $adminHasQuizViewPermission && ($previewMode || !$isPubliclyOpen);
if (!is_array($quiz) || (!$isPubliclyOpen && !$canPreviewAsAdmin)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
if (!$canPreviewAsAdmin && sr_quiz_should_count_view((int) ($quiz['id'] ?? 0))) {
    sr_quiz_increment_view_count($pdo, (int) ($quiz['id'] ?? 0));
    $quiz['view_count'] = (int) ($quiz['view_count'] ?? 0) + 1;
}

$questions = sr_quiz_questions_with_choices($pdo, (int) ($quiz['id'] ?? 0));
$quizSettings = isset($quizSettings) && is_array($quizSettings) ? $quizSettings : sr_quiz_settings($pdo);
$quizSettings = sr_quiz_display_settings_for_quiz($quizSettings, $quiz);
$attemptAccess = $canPreviewAsAdmin
    ? ['allowed' => false, 'message' => '관리자 미리보기에서는 제출할 수 없습니다.']
    : (is_array($currentAccount)
        ? sr_quiz_account_can_attempt($pdo, $quiz, (int) ($currentAccount['id'] ?? 0))
        : ['allowed' => false, 'message' => '로그인 후 퀴즈를 풀 수 있습니다.']);
$submitResult = null;
$submitErrors = [];
$quizResultScreenRequested = sr_get_string('result', 5) === '1';
$quizEmbedded = sr_get_string('embed', 5) === '1';
$returnTo = sr_quiz_internal_return_path(sr_get_string('return_to', 255));
$sourceModule = sr_quiz_clean_key(sr_get_string('source_module', 40), 40);
$sourceType = sr_quiz_clean_key(sr_get_string('source_type', 40), 40);
$sourceId = (int) sr_get_string('source_id', 20);
$quizFormUrl = '/quiz/' . (string) $quiz['quiz_key'];
$quizNextUrl = $quizFormUrl;
$quizQuery = [];
if ($returnTo !== '') {
    $quizQuery['return_to'] = $returnTo;
}
if ($sourceModule !== '' && $sourceType !== '' && $sourceId > 0) {
    $quizQuery['source_module'] = $sourceModule;
    $quizQuery['source_type'] = $sourceType;
    $quizQuery['source_id'] = (string) $sourceId;
}
if ($quizEmbedded) {
    $quizQuery['embed'] = '1';
}
if ($quizQuery !== []) {
    $quizNextUrl .= '?' . http_build_query($quizQuery);
}

if (sr_request_method() === 'POST' && !$canPreviewAsAdmin) {
    $account = sr_member_require_login($pdo);
    sr_require_csrf();
    if ($questions === []) {
        $submitErrors[] = '응시 가능한 문제가 없습니다.';
    } else {
        $attemptAccess = sr_quiz_account_can_attempt($pdo, $quiz, (int) ($account['id'] ?? 0));
        if (empty($attemptAccess['allowed'])) {
            $submitErrors[] = (string) ($attemptAccess['message'] ?? '현재 응시할 수 없는 퀴즈입니다.');
        }
    }
    if ($submitErrors === []) {
        try {
            $submitResult = sr_quiz_submit_attempt(
                $pdo,
                $quiz,
                $questions,
                (int) ($account['id'] ?? 0),
                sr_quiz_selected_choice_ids_from_post(),
                sr_quiz_asset_options($pdo)
            );
            $currentAccount = $account;
            $attemptAccess = sr_quiz_account_can_attempt($pdo, $quiz, (int) ($account['id'] ?? 0));
        } catch (RuntimeException $exception) {
            $message = (string) $exception->getMessage();
            if (!in_array($message, [
                '현재 응시할 수 없는 퀴즈입니다.',
                '응시 권한이 없는 퀴즈입니다.',
                '응시 제한 기간 설정을 확인해야 합니다.',
                '응시 제한에 따라 다시 제출할 수 없습니다.',
            ], true)) {
                sr_log_exception($exception, 'quiz_submit_failed');
                $message = '퀴즈 제출 중 오류가 발생했습니다.';
            }
            $submitErrors[] = $message;
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_submit_failed');
            $submitErrors[] = '퀴즈 제출 중 오류가 발생했습니다.';
        }
    }
}

if ($submitResult === null && $quizResultScreenRequested && is_array($currentAccount) && !$canPreviewAsAdmin) {
    $submitResult = sr_quiz_latest_attempt_result($pdo, (int) ($quiz['id'] ?? 0), (int) ($currentAccount['id'] ?? 0));
}

$seo = [
    'title' => (string) $quiz['title'],
    'description' => sr_quiz_clean_single_line((string) ($quiz['description'] ?? ''), 160),
    'canonical' => '/quiz/' . (string) $quiz['quiz_key'],
    'og' => [
        'title' => (string) $quiz['title'],
        'description' => sr_quiz_clean_single_line((string) ($quiz['description'] ?? ''), 160),
        'type' => 'article',
    ],
];
$quizCoverImageUrl = sr_quiz_clean_cover_image_url((string) ($quiz['cover_image_url'] ?? ''));
if ($quizCoverImageUrl !== '') {
    $seo['og']['image'] = $quizCoverImageUrl;
}
if ($canPreviewAsAdmin || $quizEmbedded) {
    $seo['robots'] = 'noindex, nofollow';
}
$quizConsumerTarget = $submitResult !== null ? 'quiz.result' : 'quiz.view';
$quizLayoutContext = sr_quiz_public_layout_context($quizSettings, [
    'consumer_target' => $quizConsumerTarget,
    'body_class' => $quizEmbedded ? 'example-quiz-body example-quiz-embed-body' : 'example-quiz-body',
    'include_skin_assets' => false,
    'stylesheets' => ['/modules/popup_layer/assets/module.css', '/modules/reaction/assets/module.css'],
]);

if ($quizEmbedded) {
    $quizEmbedStylesheets = is_array($quizLayoutContext['stylesheets'] ?? null) ? $quizLayoutContext['stylesheets'] : [];
    $quizEmbedScripts = array_merge(['/assets/common-ui.js'], is_array($quizLayoutContext['scripts'] ?? null) ? $quizLayoutContext['scripts'] : []);
    $quizEmbedSeo = $pdo instanceof PDO ? sr_site_apply_public_meta_defaults($pdo, $seo) : $seo;
    ?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="<?php echo sr_e(sr_color_scheme(is_array($site ?? null) ? $site : null)); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php echo sr_seo_tags($quizEmbedSeo, is_array($site ?? null) ? $site : null); ?>
    <script>(function(){try{var s=localStorage.getItem("sr_public_color_scheme");if(s==="light"||s==="dark"||s==="system"){document.documentElement.setAttribute("data-color-scheme",s);}}catch(e){}})();</script>
    <?php echo sr_stylesheet_tag($quizEmbedStylesheets, $pdo, ['style_profile' => (string) ($quizLayoutContext['style_profile'] ?? 'minimal')]); ?>
    <?php echo sr_icon_bootstrap_script(); ?>
</head>
<body class="<?php echo sr_e(sr_ui_icon_class_attr((string) ($quizLayoutContext['body_class'] ?? 'example-quiz-body'))); ?>">
    <?php
} else {
    sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, $quizLayoutContext);
}
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'quiz',
    'point_key' => 'quiz.view',
    'slot_key' => 'screen',
    'subject_id' => (string) (int) ($quiz['id'] ?? 0),
]); ?>

<main class="example-quiz-theme example-quiz-player" data-example-theme-view="quiz.view">
    <section class="example-quiz-hero">
        <p class="example-content-kicker">QUIZ DETAIL FROM THEME</p>
        <h1><?php echo sr_e((string) $quiz['title']); ?></h1>
        <?php echo sr_quiz_cover_image_html($quiz, 'example-quiz-cover-image', (string) ($quiz['title'] ?? '')); ?>
        <?php if ((string) ($quiz['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $quiz['description']); ?></p>
        <?php } ?>
        <?php if ($canPreviewAsAdmin) { ?>
            <p class="example-quiz-panel">관리자 미리보기입니다. 제출은 저장되지 않습니다.</p>
        <?php } ?>
    </section>

    <section class="example-quiz-panel">
        <?php if (function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin && $submitResult !== null) { ?>
            <?php echo sr_reaction_render_widget($pdo, 'quiz', 'quiz_set', (string) (int) ($quiz['id'] ?? 0), is_array($currentAccount) ? $currentAccount : null); ?>
        <?php } ?>
        <?php if ($submitResult !== null) { ?>
            <?php include sr_quiz_public_view_file($pdo, $quizSettings, 'result'); ?>
        <?php } elseif ($questions === []) { ?>
            <p>응시 가능한 문제가 없습니다.</p>
        <?php } else { ?>
            <?php if ($submitErrors !== []) { ?>
                <div class="sr-form-errors">
                    <?php foreach ($submitErrors as $error) { ?>
                        <p><?php echo sr_e((string) $error); ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($canPreviewAsAdmin) { ?>
                <form class="example-quiz-question-stack">
                    <?php foreach ($questions as $questionIndex => $question) { ?>
                        <fieldset class="example-quiz-question">
                            <legend><?php echo sr_e((string) ($questionIndex + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                            <?php foreach ((array) ($question['choices'] ?? []) as $choice) { ?>
                                <label><input type="radio" disabled> <?php echo sr_e((string) ($choice['label'] ?? '')); ?></label>
                            <?php } ?>
                        </fieldset>
                    <?php } ?>
                </form>
            <?php } elseif (!is_array($currentAccount)) { ?>
                <p>로그인 후 퀴즈를 풀 수 있습니다.</p>
                <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($quizNextUrl))); ?>" target="_top">로그인</a></p>
            <?php } elseif (empty($attemptAccess['allowed'])) { ?>
                <p><?php echo sr_e((string) ($attemptAccess['message'] ?? '현재 응시할 수 없는 퀴즈입니다.')); ?></p>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url($quizNextUrl)); ?>" class="example-quiz-question-stack">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                    <input type="hidden" name="source_module" value="<?php echo sr_e($sourceModule); ?>">
                    <input type="hidden" name="source_type" value="<?php echo sr_e($sourceType); ?>">
                    <input type="hidden" name="source_id" value="<?php echo sr_e((string) $sourceId); ?>">
                    <?php foreach ($questions as $questionIndex => $question) { ?>
                        <?php $questionType = (string) ($question['question_type'] ?? 'single_choice'); ?>
                        <fieldset class="example-quiz-question">
                            <legend><?php echo sr_e((string) ($questionIndex + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                            <?php foreach ((array) ($question['choices'] ?? []) as $choice) { ?>
                                <label>
                                    <?php if ($questionType === 'multiple_choice') { ?>
                                        <input type="checkbox" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>][]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                    <?php } else { ?>
                                        <input type="radio" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                    <?php } ?>
                                    <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                </label>
                            <?php } ?>
                        </fieldset>
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary">제출</button>
                </form>
            <?php } ?>
        <?php } ?>
    </section>

    <?php if ($submitResult !== null) { ?>
        <p><a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/quiz')); ?>" target="_top">메인으로</a></p>
    <?php } ?>
</main>

<?php if (function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php if ($quizEmbedded) { ?>
    <?php echo sr_script_tags($quizEmbedScripts); ?>
</body>
</html>
<?php } else { ?>
    <?php sr_public_layout_end(); ?>
<?php } ?>
