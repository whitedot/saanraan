<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
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
$quizSettings = isset($quizSettings) && is_array($quizSettings) ? $quizSettings : sr_quiz_settings($pdo);
sr_quiz_enforce_identity_view_policy($pdo, $quiz, $quizSettings, $currentAccount, $canPreviewAsAdmin);
if (!$canPreviewAsAdmin && sr_quiz_should_count_view((int) ($quiz['id'] ?? 0))) {
    sr_quiz_increment_view_count($pdo, (int) ($quiz['id'] ?? 0));
    $quiz['view_count'] = (int) ($quiz['view_count'] ?? 0) + 1;
}

$questions = sr_quiz_questions_with_choices($pdo, (int) ($quiz['id'] ?? 0));
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
$quizResultQuery = $quizQuery;
$quizResultQuery['result'] = '1';
$quizResultUrl = $quizFormUrl . '?' . http_build_query($quizResultQuery, '', '&', PHP_QUERY_RFC3986);
$quizSubmissionFlash = sr_quiz_submission_flash_take((int) ($quiz['id'] ?? 0));
$submitErrors = (array) ($quizSubmissionFlash['errors'] ?? []);
$quizSelectedChoiceIds = (array) ($quizSubmissionFlash['selected_choice_ids'] ?? []);
$quizCommentsEnabled = (int) ($quiz['comments_enabled'] ?? 0) === 1 && sr_quiz_comments_table_exists($pdo);
$quizSecretCommentsEnabled = (int) ($quiz['secret_comments_enabled'] ?? 0) === 1;
$quizCommentPageValue = sr_get_string('comment_page', 20);
$quizRequestedCommentPage = preg_match('/\A[1-9][0-9]*\z/', $quizCommentPageValue) === 1 ? (int) $quizCommentPageValue : 1;
$quizCommentPage = $quizCommentsEnabled
    ? sr_quiz_comment_page($pdo, (int) ($quiz['id'] ?? 0), $quizRequestedCommentPage, 20)
    : ['comments' => [], 'page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1, 'has_previous' => false, 'has_next' => false];
$quizComments = is_array($quizCommentPage['comments'] ?? null) ? $quizCommentPage['comments'] : [];
$quizCommentNotice = (string) ($_SESSION['sr_quiz_comment_notice'] ?? '');
$quizCommentErrors = (array) ($_SESSION['sr_quiz_comment_errors'] ?? []);
$quizCommentBody = (string) ($_SESSION['sr_quiz_comment_body'] ?? '');
$quizCommentIsSecret = !empty($_SESSION['sr_quiz_comment_is_secret']);
$quizCommentParentId = isset($_SESSION['sr_quiz_comment_parent_id']) ? (int) $_SESSION['sr_quiz_comment_parent_id'] : 0;
$quizCommentExtraFieldDefinitions = sr_comment_extra_field_definitions($quiz['comment_extra_fields_json'] ?? '[]');
$quizCommentExtraFieldValues = isset($_SESSION['sr_quiz_comment_extra_field_values']) && is_array($_SESSION['sr_quiz_comment_extra_field_values']) ? $_SESSION['sr_quiz_comment_extra_field_values'] : [];
unset($_SESSION['sr_quiz_comment_notice'], $_SESSION['sr_quiz_comment_errors'], $_SESSION['sr_quiz_comment_body'], $_SESSION['sr_quiz_comment_is_secret'], $_SESSION['sr_quiz_comment_parent_id'], $_SESSION['sr_quiz_comment_extra_field_values']);

if (sr_request_method() === 'POST' && !$canPreviewAsAdmin) {
    $account = sr_member_require_login($pdo);
    sr_require_csrf();
    $quizSelectedChoiceIds = sr_quiz_selected_choice_ids_from_post();
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
                $quizSelectedChoiceIds,
                sr_quiz_asset_options($pdo)
            );
            $currentAccount = $account;
            $attemptAccess = sr_quiz_account_can_attempt($pdo, $quiz, (int) ($account['id'] ?? 0));
            sr_redirect($quizResultUrl);
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
    sr_quiz_submission_flash_store((int) ($quiz['id'] ?? 0), $submitErrors, $quizSelectedChoiceIds);
    sr_redirect($quizNextUrl);
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
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
        'reaction' => '/modules/reaction/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'quiz', 'point_key' => 'quiz.view', 'slot_key' => 'screen'],
    ],
]);
if ($pdo instanceof PDO) {
    $quizLayoutContext = sr_public_layout_context_with_output_slot_assets($pdo, $quizLayoutContext, (array) ($quizLayoutContext['output_slots'] ?? []));
}

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
        <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin && $submitResult !== null) { ?>
            <?php echo sr_reaction_render_widget($pdo, 'quiz', 'quiz_set', (string) (int) ($quiz['id'] ?? 0), is_array($currentAccount) ? $currentAccount : null); ?>
        <?php } ?>
        <?php if ($submitResult !== null) { ?>
            <?php include sr_quiz_public_view_file($pdo, $quizSettings, 'result'); ?>
        <?php } elseif ($questions === []) { ?>
            <p>응시 가능한 문제가 없습니다.</p>
        <?php } else { ?>
            <?php echo sr_public_feedback_toasts('quiz', '', $submitErrors); ?>
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
                                        <input type="checkbox" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>][]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>"<?php echo in_array((int) ($choice['id'] ?? 0), (array) ($quizSelectedChoiceIds[(int) ($question['id'] ?? 0)] ?? []), true) ? ' checked' : ''; ?>>
                                    <?php } else { ?>
                                        <input type="radio" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>"<?php echo in_array((int) ($choice['id'] ?? 0), (array) ($quizSelectedChoiceIds[(int) ($question['id'] ?? 0)] ?? []), true) ? ' checked' : ''; ?>>
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

    <?php if ($quizCommentsEnabled && $submitResult !== null && !$quizEmbedded) { ?>
        <section id="quiz-comments" class="example-quiz-panel example-quiz-comments">
            <h2>댓글 <?php echo sr_e(number_format((int) ($quizCommentPage['total'] ?? 0))); ?></h2>
            <?php echo sr_public_feedback_toasts('quiz', $quizCommentNotice, $quizCommentErrors); ?>
            <?php if ($quizComments === []) { ?>
                <p>아직 작성된 댓글이 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($quizComments as $quizComment) { ?>
                        <li id="quiz-comment-<?php echo sr_e((string) (int) ($quizComment['id'] ?? 0)); ?>">
                            <strong><?php echo sr_e((string) ($quizComment['author_public_name'] ?? '회원')); ?></strong>
                            <?php if (sr_quiz_account_can_view_comment_body($quizComment, is_array($currentAccount) ? $currentAccount : null, $pdo)) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) ($quizComment['body_text'] ?? '')); ?></p>
                                <?php echo sr_comment_extra_fields_display_html((string) ($quizComment['extra_values_json'] ?? '')); ?>
                            <?php } else { ?>
                                <p>비밀 댓글입니다.</p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
                <?php echo sr_public_pagination_html($quizCommentPage, '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?result=1', '퀴즈 댓글 페이지', 'comment_page', 'quiz-comments', 'quiz-comments-pagination'); ?>
            <?php } ?>
            <?php if (!$canPreviewAsAdmin && is_array($currentAccount)) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/quiz/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) ($quiz['id'] ?? 0)); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($quizCommentPage['page'] ?? 1)); ?>">
                    <label for="example_quiz_comment_body">댓글</label>
                    <textarea id="example_quiz_comment_body" name="body_text" rows="4" required><?php echo $quizCommentParentId < 1 ? sr_e($quizCommentBody) : ''; ?></textarea>
                    <?php echo sr_comment_extra_fields_form_html($quizCommentExtraFieldDefinitions, $quizCommentParentId < 1 ? $quizCommentExtraFieldValues : [], 'comment_extra_fields', 'example_quiz_comment'); ?>
                    <?php if ($quizSecretCommentsEnabled) { ?>
                        <label><input type="checkbox" name="is_secret" value="1"<?php echo $quizCommentIsSecret ? ' checked' : ''; ?>> 비밀 댓글</label>
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary">댓글 작성</button>
                </form>
            <?php } ?>
        </section>
    <?php } ?>
</main>

<?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_public_script_html')) { ?>
    <?php echo sr_reaction_public_script_html(); ?>
<?php } ?>
<?php if ($quizEmbedded) { ?>
    <?php echo sr_script_tags($quizEmbedScripts); ?>
</body>
</html>
<?php } else { ?>
    <?php sr_public_layout_end(); ?>
<?php } ?>
