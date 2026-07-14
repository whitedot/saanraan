<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$basePath = rtrim(sr_base_path(), '/');
$surveyPath = (string) $path;
if ($basePath !== '' && str_starts_with($surveyPath, $basePath . '/')) {
    $surveyPath = substr($surveyPath, strlen($basePath));
}
$surveyKey = rawurldecode(trim(substr($surveyPath, strlen('/survey')), '/'));
if (!sr_survey_key_is_valid($surveyKey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}

$survey = sr_survey_by_key($pdo, $surveyKey);
$currentAccount = sr_member_current_account($pdo);
$previewMode = sr_get_string('preview', 20) === 'admin';
$adminHasSurveyViewPermission = is_array($currentAccount)
    && sr_admin_has_permission($pdo, (int) ($currentAccount['id'] ?? 0), '/admin/surveys', 'view');
$isPubliclyOpen = is_array($survey) && (string) ($survey['status'] ?? '') === 'active' && sr_survey_public_window_is_open($survey);
$canPreviewAsAdmin = $adminHasSurveyViewPermission && ($previewMode || !$isPubliclyOpen);
if (!is_array($survey) || (!$isPubliclyOpen && !$canPreviewAsAdmin)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
$settings = isset($settings) && is_array($settings) ? $settings : sr_survey_settings($pdo);
$settings = sr_survey_display_settings_for_survey($settings, $survey);
sr_survey_enforce_identity_view_policy($pdo, $survey, $settings, $currentAccount, $canPreviewAsAdmin);
if (!$canPreviewAsAdmin && sr_survey_should_count_view((int) ($survey['id'] ?? 0))) {
    sr_survey_increment_view_count($pdo, (int) ($survey['id'] ?? 0));
    $survey['view_count'] = (int) ($survey['view_count'] ?? 0) + 1;
}
$questions = sr_survey_questions_with_choices($pdo, (int) $survey['id']);
$currentAccountId = is_array($currentAccount) ? (int) ($currentAccount['id'] ?? 0) : 0;
$access = $canPreviewAsAdmin ? ['allowed' => true, 'message' => ''] : sr_survey_account_can_respond($pdo, $survey, $currentAccountId);
$submitResult = null;
$errors = [];
$submittedScreen = sr_get_string('submitted', 5) === '1';
$returnTo = sr_survey_internal_return_path(sr_get_string('return_to', 255));
if ($returnTo === '' && sr_request_method() === 'POST') {
    $returnTo = sr_survey_internal_return_path(sr_post_string('return_to', 255));
}
$surveySelfUrl = '/survey/' . (string) ($survey['survey_key'] ?? '');
$surveyQuery = [];
if ($returnTo !== '') {
    $surveyQuery['return_to'] = $returnTo;
}
$surveyNextUrl = $surveySelfUrl . ($surveyQuery === [] ? '' : '?' . http_build_query($surveyQuery, '', '&', PHP_QUERY_RFC3986));
$surveyCommentsEnabled = (int) ($survey['comments_enabled'] ?? 0) === 1 && sr_survey_comments_table_exists($pdo);
$surveySecretCommentsEnabled = (int) ($survey['secret_comments_enabled'] ?? 0) === 1;
$surveyCommentPageValue = sr_get_string('comment_page', 20);
$surveyRequestedCommentPage = preg_match('/\A[1-9][0-9]*\z/', $surveyCommentPageValue) === 1 ? (int) $surveyCommentPageValue : 1;
$surveyCommentPage = $surveyCommentsEnabled
    ? sr_survey_comment_page($pdo, (int) ($survey['id'] ?? 0), $surveyRequestedCommentPage, 20)
    : ['comments' => [], 'page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1, 'has_previous' => false, 'has_next' => false];
$surveyComments = is_array($surveyCommentPage['comments'] ?? null) ? $surveyCommentPage['comments'] : [];
$surveyCommentNotice = (string) ($_SESSION['sr_survey_comment_notice'] ?? '');
$surveyCommentErrors = (array) ($_SESSION['sr_survey_comment_errors'] ?? []);
$surveyCommentBody = (string) ($_SESSION['sr_survey_comment_body'] ?? '');
$surveyCommentIsSecret = !empty($_SESSION['sr_survey_comment_is_secret']);
$surveyCommentParentId = isset($_SESSION['sr_survey_comment_parent_id']) ? (int) $_SESSION['sr_survey_comment_parent_id'] : 0;
unset($_SESSION['sr_survey_comment_notice'], $_SESSION['sr_survey_comment_errors'], $_SESSION['sr_survey_comment_body'], $_SESSION['sr_survey_comment_is_secret'], $_SESSION['sr_survey_comment_parent_id']);

if (sr_request_method() === 'POST') {
    $isPreviewTestSubmit = $canPreviewAsAdmin && ($_POST['test_submit'] ?? '') === '1';
    $account = ((int) ($survey['login_required'] ?? 1) === 1 || $isPreviewTestSubmit) ? sr_member_require_login($pdo) : $currentAccount;
    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    sr_require_csrf();
    try {
        $submitResult = sr_survey_submit_response(
            $pdo,
            $survey,
            $questions,
            $accountId,
            sr_survey_selected_answers_from_post($questions),
            sr_survey_asset_options($pdo),
            ($_POST['consent_accepted'] ?? '') === '1',
            $isPreviewTestSubmit,
            sr_survey_other_answers_from_post($questions)
        );
        $currentAccount = $account;
        if (!$isPreviewTestSubmit) {
            $redirectQuery = ['submitted' => '1'];
            if ($returnTo !== '') {
                $redirectQuery['return_to'] = $returnTo;
            }
            sr_redirect('/survey/' . (string) $survey['survey_key'] . '?' . http_build_query($redirectQuery, '', '&', PHP_QUERY_RFC3986));
        }
        $access = ['allowed' => true, 'message' => ''];
    } catch (RuntimeException $exception) {
        $message = (string) $exception->getMessage();
        if (!in_array($message, ['현재 참여할 수 없는 설문입니다.', '이미 참여한 설문입니다.', '필수 문항에 답변해 주세요.', '응답 가능한 문항이 없습니다.', '참여 동의가 필요합니다.', '응답 제한 기간 설정을 확인해야 합니다.', '선택지 값을 확인해 주세요.', '무응답 선택지는 다른 선택지와 함께 고를 수 없습니다.', '기타 답변을 입력해 주세요.', '복수 선택 문항의 최소 선택 수를 확인해 주세요.', '복수 선택 문항의 최대 선택 수를 확인해 주세요.', '숫자 문항에는 숫자만 입력해 주세요.', '정수만 입력할 수 있는 문항입니다.', '숫자 문항의 최소값을 확인해 주세요.', '숫자 문항의 최대값을 확인해 주세요.', '별점 범위를 확인해 주세요.', '관리자 테스트 제출은 로그인 계정이 필요합니다.'], true)) {
            sr_log_exception($exception, 'survey_submit_failed');
            $message = '설문 제출 중 오류가 발생했습니다.';
        }
        $errors[] = $message;
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'survey_submit_failed');
        $errors[] = '설문 제출 중 오류가 발생했습니다.';
    }
}

$seo = [
    'title' => (string) $survey['title'],
    'description' => sr_survey_clean_single_line((string) ($survey['description'] ?? ''), 160),
    'canonical' => '/survey/' . (string) $survey['survey_key'],
];
$surveyCoverImageUrl = sr_survey_clean_cover_image_url((string) ($survey['cover_image_url'] ?? ''));
if ($surveyCoverImageUrl !== '') {
    $seo['og'] = [
        'title' => (string) $survey['title'],
        'description' => sr_survey_clean_single_line((string) ($survey['description'] ?? ''), 160),
        'type' => 'article',
        'image' => $surveyCoverImageUrl,
    ];
}
$memberGroupKeys = sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]');
if ($canPreviewAsAdmin || $submittedScreen || (int) ($survey['login_required'] ?? 1) === 1 || $memberGroupKeys !== [] || (int) ($survey['public_listed'] ?? 1) !== 1 || (string) ($survey['robots_policy'] ?? 'auto') === 'noindex') {
    $seo['robots'] = 'noindex, nofollow';
}

$surveyConsumerTarget = ($submittedScreen || $submitResult !== null) ? 'survey.complete' : 'survey.view';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'consumer_target' => $surveyConsumerTarget,
    'body_class' => 'example-survey-body',
    'include_skin_assets' => false,
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
        'reaction' => '/modules/reaction/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'survey', 'point_key' => 'survey.view', 'slot_key' => 'screen'],
    ],
]));
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'survey',
    'point_key' => 'survey.view',
    'slot_key' => 'screen',
    'subject_id' => (string) (int) ($survey['id'] ?? 0),
]); ?>

<main class="example-survey-theme example-survey-detail" data-example-theme-view="survey.view">
    <section class="example-survey-hero">
        <p class="example-content-kicker">SURVEY DETAIL FROM THEME</p>
        <h1><?php echo sr_e((string) $survey['title']); ?></h1>
        <?php echo sr_survey_cover_image_html($survey, 'example-survey-cover-image', (string) ($survey['title'] ?? '')); ?>
        <?php if ((string) ($survey['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $survey['description']); ?></p>
        <?php } ?>
        <?php if ($canPreviewAsAdmin) { ?>
            <p class="example-survey-panel">관리자 미리보기입니다. 테스트 응답으로 저장되고 보상은 지급되지 않습니다.</p>
        <?php } ?>
    </section>

    <section class="example-survey-panel">
        <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin && ($submittedScreen || $submitResult !== null)) { ?>
            <?php echo sr_reaction_render_widget($pdo, 'survey', 'survey_form', (string) (int) ($survey['id'] ?? 0), is_array($currentAccount) ? $currentAccount : null); ?>
        <?php } ?>
        <?php if ($submittedScreen || $submitResult !== null) { ?>
            <?php include sr_survey_public_view_file($pdo, $settings, 'complete'); ?>
        <?php } elseif ($errors !== []) { ?>
            <div class="sr-form-errors">
                <?php foreach ($errors as $error) { ?>
                    <p><?php echo sr_e((string) $error); ?></p>
                <?php } ?>
            </div>
        <?php } ?>

        <?php if ($submitResult === null && !$submittedScreen) { ?>
            <?php if ((int) ($survey['login_required'] ?? 1) === 1 && !is_array($currentAccount)) { ?>
                <p>로그인 후 설문에 참여할 수 있습니다.</p>
                <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($surveyNextUrl))); ?>">로그인</a></p>
            <?php } elseif (empty($access['allowed'])) { ?>
                <p><?php echo sr_e((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.')); ?></p>
            <?php } elseif ($questions === []) { ?>
                <p>응답 가능한 문항이 없습니다.</p>
            <?php } else { ?>
                <?php
                $surveyFormQuery = $canPreviewAsAdmin ? ['preview' => 'admin'] : [];
                if ($returnTo !== '') {
                    $surveyFormQuery['return_to'] = $returnTo;
                }
                ?>
                <form method="post" action="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'] . ($surveyFormQuery === [] ? '' : '?' . http_build_query($surveyFormQuery, '', '&', PHP_QUERY_RFC3986)))); ?>" class="example-survey-question-stack">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                    <?php if ($canPreviewAsAdmin) { ?>
                        <input type="hidden" name="test_submit" value="1">
                    <?php } ?>
                    <?php if ((int) ($survey['consent_required'] ?? 0) === 1) { ?>
                        <fieldset class="example-survey-question">
                            <legend>참여 동의</legend>
                            <?php if ((string) ($survey['consent_text'] ?? '') !== '') { ?>
                                <p><?php echo sr_e((string) $survey['consent_text']); ?></p>
                            <?php } ?>
                            <label><input type="checkbox" name="consent_accepted" value="1"> 위 안내를 확인했고 설문 참여에 동의합니다.</label>
                        </fieldset>
                    <?php } ?>
                    <?php foreach ($questions as $index => $question) { ?>
                        <?php $type = (string) ($question['question_type'] ?? 'single_choice'); ?>
                        <fieldset class="example-survey-question">
                            <legend><?php echo sr_e((string) ($index + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                            <?php if ($type === 'text' || $type === 'short_text') { ?>
                                <input type="text" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" maxlength="500">
                            <?php } elseif ($type === 'long_text') { ?>
                                <textarea name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" rows="4"></textarea>
                            <?php } elseif (in_array($type, ['number', 'rating', 'scale'], true)) { ?>
                                <input type="number" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]"<?php echo (int) ($question['allow_decimal'] ?? 0) === 1 ? ' step="any"' : ' step="1"'; ?><?php echo $question['number_min'] !== null ? ' min="' . sr_e((string) $question['number_min']) . '"' : ''; ?><?php echo $question['number_max'] !== null ? ' max="' . sr_e((string) $question['number_max']) . '"' : ''; ?>>
                            <?php } else { ?>
                                <?php foreach ((array) ($question['choices'] ?? []) as $choice) { ?>
                                    <label>
                                        <input type="<?php echo $type === 'multiple_choice' ? 'checkbox' : 'radio'; ?>" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]<?php echo $type === 'multiple_choice' ? '[]' : ''; ?>" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                        <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                    </label>
                                    <?php if ((int) ($choice['is_other'] ?? 0) === 1) { ?>
                                        <input type="text" name="other_answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>][<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>]" class="example-survey-other-input" maxlength="500">
                                    <?php } ?>
                                <?php } ?>
                            <?php } ?>
                        </fieldset>
                    <?php } ?>
                    <button type="submit" class="btn btn-solid-primary">제출</button>
                </form>
            <?php } ?>
        <?php } ?>
    </section>

    <?php if ($surveyCommentsEnabled && ($submittedScreen || $submitResult !== null)) { ?>
        <section id="survey-comments" class="example-survey-panel example-survey-comments">
            <h2>댓글 <?php echo sr_e(number_format((int) ($surveyCommentPage['total'] ?? 0))); ?></h2>
            <?php echo sr_public_feedback_toasts('survey', $surveyCommentNotice, $surveyCommentErrors); ?>
            <?php if ($surveyComments === []) { ?>
                <p>아직 작성된 댓글이 없습니다.</p>
            <?php } else { ?>
                <ol>
                    <?php foreach ($surveyComments as $surveyComment) { ?>
                        <li id="survey-comment-<?php echo sr_e((string) (int) ($surveyComment['id'] ?? 0)); ?>">
                            <strong><?php echo sr_e((string) ($surveyComment['author_public_name'] ?? '회원')); ?></strong>
                            <?php if (sr_survey_account_can_view_comment_body($surveyComment, is_array($currentAccount) ? $currentAccount : null, $pdo)) { ?>
                                <p><?php echo sr_member_mention_plain_text_html((string) ($surveyComment['body_text'] ?? '')); ?></p>
                            <?php } else { ?>
                                <p>비밀 댓글입니다.</p>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
                <?php echo sr_public_pagination_html($surveyCommentPage, '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?submitted=1', '설문 댓글 페이지', 'comment_page', 'survey-comments', 'survey-comments-pagination'); ?>
            <?php } ?>
            <?php if (!$canPreviewAsAdmin && is_array($currentAccount) && sr_survey_account_has_submitted_response($pdo, (int) ($survey['id'] ?? 0), (int) ($currentAccount['id'] ?? 0))) { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/survey/comment')); ?>">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($survey['id'] ?? 0)); ?>">
                    <input type="hidden" name="parent_comment_id" value="0">
                    <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($surveyCommentPage['page'] ?? 1)); ?>">
                    <label for="example_survey_comment_body">댓글</label>
                    <textarea id="example_survey_comment_body" name="body_text" rows="4" required><?php echo $surveyCommentParentId < 1 ? sr_e($surveyCommentBody) : ''; ?></textarea>
                    <?php if ($surveySecretCommentsEnabled) { ?>
                        <label><input type="checkbox" name="is_secret" value="1"<?php echo $surveyCommentIsSecret ? ' checked' : ''; ?>> 비밀 댓글</label>
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
<?php sr_public_layout_end(); ?>
