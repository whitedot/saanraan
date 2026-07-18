<?php

require_once __DIR__ . '/../../helpers.php';
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
$previewMode = sr_get_string('preview', 20) === 'admin';
$currentAccount = sr_member_current_account($pdo);
$adminHasSurveyViewPermission = is_array($currentAccount)
    && sr_admin_has_permission($pdo, (int) ($currentAccount['id'] ?? 0), '/admin/surveys', 'view');
$isPubliclyOpen = is_array($survey) && (string) ($survey['status'] ?? '') === 'active' && sr_survey_public_window_is_open($survey);
$canPreviewAsAdmin = $adminHasSurveyViewPermission && ($previewMode || !$isPubliclyOpen);
$previewMode = $canPreviewAsAdmin;
if (!is_array($survey) || (!$isPubliclyOpen && !$canPreviewAsAdmin)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
$settings = sr_survey_display_settings_for_survey($settings, $survey);
sr_survey_enforce_identity_view_policy($pdo, $survey, $settings, $currentAccount, $canPreviewAsAdmin);
if (!$canPreviewAsAdmin && sr_survey_should_count_view((int) ($survey['id'] ?? 0))) {
    sr_survey_increment_view_count($pdo, (int) ($survey['id'] ?? 0));
    $survey['view_count'] = (int) ($survey['view_count'] ?? 0) + 1;
}

$questions = sr_survey_questions_with_choices($pdo, (int) $survey['id']);
$currentAccountId = is_array($currentAccount) ? (int) ($currentAccount['id'] ?? 0) : 0;
$access = $canPreviewAsAdmin
    ? ['allowed' => true, 'message' => '']
    : sr_survey_account_can_respond($pdo, $survey, $currentAccountId);
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
$surveyCommentExtraFieldDefinitions = sr_comment_extra_field_definitions($survey['comment_extra_fields_json'] ?? '[]');
$surveyCommentEditorKey = sr_survey_comment_editor_key($pdo, $settings);
$surveyCommentToolbarPreset = 'standard';
$surveyCommentEditorAttributes = sr_editor_textarea_attributes($pdo, $surveyCommentEditorKey, $surveyCommentToolbarPreset, 'comment_body_format');
$surveyCommentEditorRequiredAttribute = $surveyCommentEditorKey === 'ckeditor' ? '' : ' required';
if ($surveyCommentEditorKey === 'ckeditor') {
    $surveyCommentEditorAttributes .= ' data-sr-editor-body-theme="survey.' . sr_e(sr_survey_theme_key((string) ($settings['theme_key'] ?? 'basic'))) . '"';
}
$surveyCommentExtraFieldValues = isset($_SESSION['sr_survey_comment_extra_field_values']) && is_array($_SESSION['sr_survey_comment_extra_field_values']) ? $_SESSION['sr_survey_comment_extra_field_values'] : [];
unset($_SESSION['sr_survey_comment_notice'], $_SESSION['sr_survey_comment_errors'], $_SESSION['sr_survey_comment_body'], $_SESSION['sr_survey_comment_is_secret'], $_SESSION['sr_survey_comment_parent_id'], $_SESSION['sr_survey_comment_extra_field_values']);
$surveyCommentLoginUrl = '/login?next=' . rawurlencode('/survey/' . (string) ($survey['survey_key'] ?? '') . '?submitted=1#survey-comments');

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

$surveyCanWriteComment = is_array($currentAccount)
    && sr_survey_account_has_submitted_response($pdo, (int) ($survey['id'] ?? 0), (int) ($currentAccount['id'] ?? 0));

$surveyReactionCommentTargets = [];
if (
    sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_resolve_targets')
    && !$canPreviewAsAdmin
    && is_array($surveyComments ?? null)
    && $surveyComments !== []
) {
    $surveyReactionCommentIds = [];
    foreach ($surveyComments as $surveyReactionComment) {
        $surveyReactionCommentId = (int) ($surveyReactionComment['id'] ?? 0);
        if ($surveyReactionCommentId > 0) {
            $surveyReactionCommentIds[] = (string) $surveyReactionCommentId;
        }
    }
    $surveyReactionCommentTargets = sr_reaction_resolve_targets(
        $pdo,
        'survey',
        'comment',
        $surveyReactionCommentIds,
        is_array($currentAccount) ? (int) ($currentAccount['id'] ?? 0) : 0
    );
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
$hasSurveyInfo = (int) ($survey['reward_enabled'] ?? 0) === 1;
foreach (['research_purpose', 'methodology_disclosure', 'target_population', 'fieldwork_method', 'sample_method', 'sponsor_name', 'organizer_name', 'contact_text', 'privacy_notice', 'withdrawal_policy', 'sensitive_data_policy'] as $surveyInfoField) {
    if (trim((string) ($survey[$surveyInfoField] ?? '')) !== '') {
        $hasSurveyInfo = true;
        break;
    }
}
if ((int) ($survey['estimated_minutes'] ?? 0) > 0 || (int) ($survey['target_sample_size'] ?? 0) > 0) {
    $hasSurveyInfo = true;
}
$surveyShareUrl = sr_absolute_url($site ?? null, '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')));
$surveyConsumerTarget = ($submittedScreen || $submitResult !== null) ? 'survey.complete' : 'survey.view';
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'consumer_target' => $surveyConsumerTarget,
    'body_class' => 'sr-survey-page',
    'scripts' => $surveyCommentsEnabled && is_array($currentAccount) ? ['/assets/mention-input.js'] : [],
    'stylesheets' => array_merge(sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
        'reaction' => '/modules/reaction/assets/module.css',
    ]), sr_survey_comment_body_stylesheets($pdo, $settings)),
    'output_slots' => [
        ['module_key' => 'survey', 'point_key' => 'survey.view', 'slot_key' => 'screen'],
        ['module_key' => 'survey', 'point_key' => 'survey.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
]));
?>
<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'survey',
    'point_key' => 'survey.view',
    'slot_key' => 'screen',
    'subject_id' => (string) (int) ($survey['id'] ?? 0),
]); ?>

<main class="survey-page-main">
    <section class="survey-page-section">
        <div class="survey-page-container">
            <div class="survey-screen-frame">
                <div class="survey-screen-main">
            <h1><?php echo sr_e((string) $survey['title']); ?></h1>
            <?php echo sr_survey_cover_image_html($survey, 'sr-survey-cover-image', (string) ($survey['title'] ?? '')); ?>
            <?php if ((string) ($survey['description'] ?? '') !== ''): ?>
                <p><?php echo sr_e((string) $survey['description']); ?></p>
            <?php endif; ?>
            <?php if ($canPreviewAsAdmin): ?>
                <div class="sr-survey-info">
                    <p>관리자 미리보기입니다. 초안, 중지, 기간 외 설문도 확인할 수 있으며 제출은 테스트 응답으로 저장되고 보상은 지급되지 않습니다.</p>
                </div>
            <?php endif; ?>
            <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin && ($submittedScreen || $submitResult !== null)): ?>
                <?php echo sr_reaction_render_widget($pdo, 'survey', 'survey_form', (string) (int) ($survey['id'] ?? 0), is_array($currentAccount) ? $currentAccount : null); ?>
            <?php endif; ?>
            <?php if ($hasSurveyInfo): ?>
                <section class="sr-survey-info" aria-labelledby="survey_info_title">
                    <h2 id="survey_info_title">참여 안내</h2>
                    <?php if ((string) ($survey['research_purpose'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['research_purpose']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['methodology_disclosure'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['methodology_disclosure']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['target_population'] ?? '') !== ''): ?>
                        <p>참여 대상: <?php echo sr_e((string) $survey['target_population']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['fieldwork_method'] ?? '') !== ''): ?>
                        <p>조사 방식: <?php echo sr_e((string) $survey['fieldwork_method']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['sample_method'] ?? '') !== ''): ?>
                        <p>표본 추출: <?php echo sr_e((string) $survey['sample_method']); ?></p>
                    <?php endif; ?>
                    <?php if ((int) ($survey['target_sample_size'] ?? 0) > 0): ?>
                        <p>목표 표본 수: <?php echo sr_e((string) (int) $survey['target_sample_size']); ?>명</p>
                    <?php endif; ?>
                    <?php if ((int) ($survey['estimated_minutes'] ?? 0) > 0): ?>
                        <p>예상 소요 시간: <?php echo sr_e((string) (int) $survey['estimated_minutes']); ?>분</p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['sponsor_name'] ?? '') !== ''): ?>
                        <p>의뢰/후원: <?php echo sr_e((string) $survey['sponsor_name']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['organizer_name'] ?? '') !== ''): ?>
                        <p>주관: <?php echo sr_e((string) $survey['organizer_name']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['contact_text'] ?? '') !== ''): ?>
                        <p>문의: <?php echo sr_e((string) $survey['contact_text']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['privacy_notice'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['privacy_notice']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['withdrawal_policy'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['withdrawal_policy']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['sensitive_data_policy'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['sensitive_data_policy']); ?></p>
                    <?php endif; ?>
                    <?php if ((int) ($survey['reward_enabled'] ?? 0) === 1): ?>
                        <p>참여 완료 후 보상 지급 조건을 다시 확인한 뒤 보상 지급을 시도합니다.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <?php if ($submittedScreen || $submitResult !== null): ?>
                <?php include sr_survey_skin_view_file($settings, 'complete'); ?>
            <?php elseif ($errors !== []): ?>
                <div class="sr-form-errors">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo sr_e((string) $error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($submitResult === null && !$submittedScreen): ?>
                <?php if ((int) ($survey['login_required'] ?? 1) === 1 && !is_array($currentAccount)): ?>
                    <p>로그인 후 설문에 참여할 수 있습니다.</p>
                    <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($surveyNextUrl))); ?>">로그인</a></p>
                <?php elseif (empty($access['allowed'])): ?>
                    <p><?php echo sr_e((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.')); ?></p>
                <?php elseif ($questions === []): ?>
                    <p>응답 가능한 문항이 없습니다.</p>
                <?php else: ?>
                    <?php
                    $surveyFormQuery = $canPreviewAsAdmin ? ['preview' => 'admin'] : [];
                    if ($returnTo !== '') {
                        $surveyFormQuery['return_to'] = $returnTo;
                    }
                    ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'] . ($surveyFormQuery === [] ? '' : '?' . http_build_query($surveyFormQuery, '', '&', PHP_QUERY_RFC3986)))); ?>" class="sr-survey-form">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <?php if ($canPreviewAsAdmin): ?>
                            <input type="hidden" name="test_submit" value="1">
                        <?php endif; ?>
                        <?php if ((int) ($survey['consent_required'] ?? 0) === 1): ?>
                            <fieldset class="sr-survey-question">
                                <legend>참여 동의</legend>
                                <?php if ((string) ($survey['consent_text'] ?? '') !== ''): ?>
                                    <p><?php echo sr_e((string) $survey['consent_text']); ?></p>
                                <?php endif; ?>
                                <label class="sr-survey-choice">
                                    <input type="checkbox" name="consent_accepted" value="1">
                                    <span>위 안내를 확인했고 설문 참여에 동의합니다.</span>
                                </label>
                            </fieldset>
                        <?php endif; ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <?php $type = (string) ($question['question_type'] ?? 'single_choice'); ?>
                            <fieldset class="sr-survey-question">
                                <legend><?php echo sr_e((string) ($index + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                                <?php if ($type === 'text' || $type === 'short_text'): ?>
                                    <input type="text" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" maxlength="500">
                                <?php elseif ($type === 'long_text'): ?>
                                    <textarea name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" rows="4"></textarea>
                                <?php elseif (in_array($type, ['number', 'rating', 'scale'], true)): ?>
                                    <input type="number" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]"<?php echo (int) ($question['allow_decimal'] ?? 0) === 1 ? ' step="any"' : ' step="1"'; ?><?php echo $question['number_min'] !== null ? ' min="' . sr_e((string) $question['number_min']) . '"' : ''; ?><?php echo $question['number_max'] !== null ? ' max="' . sr_e((string) $question['number_max']) . '"' : ''; ?>>
                                    <?php if ((string) ($question['number_unit'] ?? '') !== ''): ?>
                                        <p class="sr-survey-help"><?php echo sr_e((string) $question['number_unit']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                        <label class="sr-survey-choice">
                                            <input type="<?php echo $type === 'multiple_choice' ? 'checkbox' : 'radio'; ?>" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]<?php echo $type === 'multiple_choice' ? '[]' : ''; ?>" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                            <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                        </label>
                                        <?php if ((int) ($choice['is_other'] ?? 0) === 1): ?>
                                            <input type="text" name="other_answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>][<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>]" class="sr-survey-other-input" maxlength="500">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-solid-primary">제출</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($submittedScreen || $submitResult !== null): ?>
                <p>
                    <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/survey')); ?>">메인으로</a>
                    <label class="sr-only" for="survey_share_url">공유 주소</label>
                    <input id="survey_share_url" type="url" value="<?php echo sr_e($surveyShareUrl); ?>" readonly data-sr-share-url>
                    <button type="button" class="btn btn-solid-light" data-sr-share-copy="<?php echo sr_e($surveyShareUrl); ?>">공유 주소 복사</button>
                </p>
                <script>
                (function () {
                    var buttons = document.querySelectorAll('[data-sr-share-copy]');
                    if (!buttons.length) {
                        return;
                    }
                    document.querySelectorAll('[data-sr-share-url]').forEach(function (input) {
                        input.addEventListener('focus', function () {
                            input.select();
                        });
                        input.addEventListener('click', function () {
                            input.select();
                        });
                    });
                    function fallbackCopy(text) {
                        var input = document.createElement('textarea');
                        input.value = text;
                        input.setAttribute('readonly', 'readonly');
                        input.style.position = 'fixed';
                        input.style.left = '-9999px';
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                    }
                    buttons.forEach(function (button) {
                        button.addEventListener('click', function () {
                            var shareUrl = button.getAttribute('data-sr-share-copy') || '';
                            var originalText = button.getAttribute('data-sr-copy-original') || button.textContent;
                            if (shareUrl === '') {
                                return;
                            }
                            button.setAttribute('data-sr-copy-original', originalText);
                            var done = function () {
                                button.textContent = '복사됨';
                                window.setTimeout(function () {
                                    button.textContent = originalText;
                                }, 1800);
                            };
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(shareUrl).then(done).catch(function () {
                                    fallbackCopy(shareUrl);
                                    done();
                                });
                                return;
                            }
                            fallbackCopy(shareUrl);
                            done();
                        });
                    });
                }());
                </script>
            <?php endif; ?>
            <?php if ($surveyCommentsEnabled && ($submittedScreen || $submitResult !== null)): ?>
                <section id="survey-comments" class="sr-survey-comments">
                    <div class="sr-survey-comments-panel-header">
                        <h2>댓글 <span class="sr-survey-comments-count"><?php echo sr_e(number_format((int) ($surveyCommentPage['total'] ?? 0))); ?></span></h2>
                    </div>
                    <?php echo sr_public_feedback_toasts('survey', $surveyCommentNotice, $surveyCommentErrors); ?>
                    <?php if ($surveyComments === []): ?>
                        <p class="sr-survey-comments-empty">아직 작성된 댓글이 없습니다.</p>
                    <?php else: ?>
                        <ul class="sr-survey-comment-list">
                            <?php foreach ($surveyComments as $surveyComment): ?>
                                <?php
                                $surveyCommentId = (int) ($surveyComment['id'] ?? 0);
                                $surveyCommentEditId = 'survey_comment_edit_' . (string) $surveyCommentId;
                                $surveyCommentEditModalId = 'survey_comment_edit_modal_' . (string) $surveyCommentId;
                                $surveyCommentCanViewBody = sr_survey_account_can_view_comment_body($surveyComment, is_array($currentAccount) ? $currentAccount : null, $pdo);
                                $surveyCommentCanEdit = is_array($currentAccount) && sr_survey_account_can_edit_comment($surveyComment, $currentAccount);
                                $surveyCommentCanDelete = is_array($currentAccount) && sr_survey_account_can_delete_comment($surveyComment, $currentAccount, $pdo);
                                $surveyCommentDepth = min(3, max(1, (int) ($surveyComment['depth'] ?? 1)));
                                $surveyCommentCanReply = $surveyCanWriteComment && !$canPreviewAsAdmin && $surveyCommentCanViewBody && $surveyCommentDepth < 3;
                                $surveyCommentReplyId = 'survey_comment_reply_' . (string) $surveyCommentId;
                                $surveyCommentReplyModalId = 'survey_comment_reply_modal_' . (string) $surveyCommentId;
                                $surveyCommentPageNumber = (int) ($surveyCommentPage['page'] ?? 1);
                                $surveyCommentUrl = '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?submitted=1'
                                    . ($surveyCommentPageNumber > 1 ? '&comment_page=' . rawurlencode((string) $surveyCommentPageNumber) : '')
                                    . '#survey-comment-' . rawurlencode((string) $surveyCommentId);
                                ?>
                                <li id="survey-comment-<?php echo sr_e((string) $surveyCommentId); ?>" class="sr-survey-comment-item sr-survey-comment-depth-<?php echo sr_e((string) $surveyCommentDepth); ?>">
                                    <div class="sr-survey-comment-meta">
                                        <?php $surveyCommentAuthorLabel = (string) ($surveyComment['author_public_name'] ?? $surveyComment['author_public_name_snapshot'] ?? '회원'); ?>
                                        <div class="sr-survey-comment-author"><?php echo sr_member_public_name_menu_html($pdo, is_array($currentAccount) ? $currentAccount : null, (int) ($surveyComment['author_account_id'] ?? 0), $surveyCommentAuthorLabel, [
                                            'return_to' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
                                        ]); ?></div>
                                        <span class="sr-survey-comment-date">
                                            <span class="sr-survey-comment-date-content">
                                                <?php echo sr_survey_time_html((string) ($surveyComment['created_at'] ?? '')); ?>
                                                <a class="sr-survey-comment-permalink" href="<?php echo sr_e(sr_url($surveyCommentUrl)); ?>" aria-label="댓글 고유주소로 이동" title="댓글 고유주소"><?php echo sr_material_icon_html('link'); ?></a>
                                            </span>
                                        </span>
                                        <?php if ((int) ($surveyComment['is_secret'] ?? 0) === 1): ?>
                                            <span class="sr-survey-comment-meta-status">비밀 댓글</span>
                                        <?php endif; ?>
                                        <?php if ($surveyCommentDepth > 1): ?>
                                            <span>답글 <?php echo sr_e((string) $surveyCommentDepth); ?>단계</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($surveyCommentCanViewBody): ?>
                                        <div class="sr-survey-comment-body"><?php echo sr_survey_comment_body_html($pdo, $surveyComment, $settings); ?></div>
                                        <?php echo sr_comment_extra_fields_display_html((string) ($surveyComment['extra_values_json'] ?? '')); ?>
                                        <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin): ?>
                                            <?php
                                            $surveyCommentReactionOptions = ['label' => '댓글 리액션'];
                                            if (isset($surveyReactionCommentTargets[(string) $surveyCommentId]) && is_array($surveyReactionCommentTargets[(string) $surveyCommentId])) {
                                                $surveyCommentReactionOptions['resolved_target'] = $surveyReactionCommentTargets[(string) $surveyCommentId];
                                            }
                                            ?>
                                            <?php echo sr_reaction_render_widget($pdo, 'survey', 'comment', (string) $surveyCommentId, is_array($currentAccount) ? $currentAccount : null, $surveyCommentReactionOptions); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="sr-survey-comment-secret">비밀 댓글입니다.</p>
                                    <?php endif; ?>
                                    <?php if ($surveyCommentCanEdit || $surveyCommentCanDelete || $surveyCommentCanReply): ?>
                                        <div class="sr-survey-comment-actions">
                                            <div class="sr-survey-comment-action-group sr-survey-comment-action-group-leading">
                                            <?php if ($surveyCommentCanReply): ?>
                                                <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($surveyCommentReplyModalId); ?>" data-overlay="#<?php echo sr_e($surveyCommentReplyModalId); ?>">답글</button>
                                                <div id="<?php echo sr_e($surveyCommentReplyModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($surveyCommentReplyModalId . '_title'); ?>" aria-hidden="true" inert>
                                                    <div class="modal-dialog sr-survey-comment-editor-dialog">
                                                        <form method="post" action="<?php echo sr_e(sr_url('/survey/comment')); ?>" class="modal-content">
                                                            <?php echo sr_csrf_field(); ?>
                                                            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($survey['id'] ?? 0)); ?>">
                                                            <input type="hidden" name="parent_comment_id" value="<?php echo sr_e((string) $surveyCommentId); ?>">
                                                            <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($surveyCommentPage['page'] ?? 1)); ?>">
                                                            <div class="modal-header">
                                                                <h3 id="<?php echo sr_e($surveyCommentReplyModalId . '_title'); ?>" class="modal-title">답글 작성</h3>
                                                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($surveyCommentReplyModalId); ?>">
                                                                    <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <strong class="sr-survey-comment-reply-source-label">댓글</strong>
                                                                <p class="sr-survey-comment-reply-source" tabindex="0" aria-label="답글 대상 댓글"><?php echo sr_e(sr_survey_comment_body_plain_text($pdo, $surveyComment, $settings)); ?></p>
                                                                <p class="sr-survey-comment-editor-field">
                                                                    <label for="<?php echo sr_e($surveyCommentReplyId); ?>">
                                                                        <span>답글 <span class="sr-required-label">(필수)</span></span>
                                                                        <textarea id="<?php echo sr_e($surveyCommentReplyId); ?>" name="body_text" rows="3" cols="60"<?php echo $surveyCommentEditorRequiredAttribute; ?> class="form-textarea" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"<?php echo $surveyCommentEditorAttributes; ?>><?php echo (int) ($surveyCommentParentId ?? 0) === $surveyCommentId ? sr_e($surveyCommentBody) : ''; ?></textarea>
                                                                    </label>
                                                                </p>
                                                                <?php echo sr_comment_extra_fields_form_html($surveyCommentExtraFieldDefinitions, (int) ($surveyCommentParentId ?? 0) === $surveyCommentId ? $surveyCommentExtraFieldValues : [], 'comment_extra_fields', 'survey_comment_reply_' . (string) $surveyCommentId); ?>
                                                                <?php if (!empty($surveySecretCommentsEnabled)): ?>
                                                                    <label class="sr-survey-comment-secret-toggle">
                                                                        <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo (int) ($surveyCommentParentId ?? 0) === $surveyCommentId && $surveyCommentIsSecret ? ' checked' : ''; ?>>
                                                                        비밀 댓글
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($surveyCommentReplyModalId); ?>">닫기</button>
                                                                <button type="submit" class="btn btn-solid-primary modal-action">답글 작성</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            </div>
                                            <div class="sr-survey-comment-action-group sr-survey-comment-action-group-trailing">
                                            <?php if ($surveyCommentCanEdit): ?>
                                                <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($surveyCommentEditModalId); ?>" data-overlay="#<?php echo sr_e($surveyCommentEditModalId); ?>">수정</button>
                                                <div id="<?php echo sr_e($surveyCommentEditModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($surveyCommentEditModalId . '_title'); ?>" aria-hidden="true" inert>
                                                    <div class="modal-dialog sr-survey-comment-editor-dialog">
                                                        <form method="post" action="<?php echo sr_e(sr_url('/survey/comment/edit')); ?>" class="modal-content">
                                                            <?php echo sr_csrf_field(); ?>
                                                            <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $surveyCommentId); ?>">
                                                            <div class="modal-header">
                                                                <h3 id="<?php echo sr_e($surveyCommentEditModalId . '_title'); ?>" class="modal-title">댓글 수정</h3>
                                                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($surveyCommentEditModalId); ?>">
                                                                    <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p class="sr-survey-comment-editor-field">
                                                                    <label for="<?php echo sr_e($surveyCommentEditId); ?>">
                                                                        <span>내용 <span class="sr-required-label">(필수)</span></span>
                                                                        <textarea id="<?php echo sr_e($surveyCommentEditId); ?>" name="body_text" rows="3" cols="60"<?php echo $surveyCommentEditorRequiredAttribute; ?> class="form-textarea" data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"<?php echo $surveyCommentEditorAttributes; ?>><?php echo sr_e((string) ($surveyComment['body_text'] ?? '')); ?></textarea>
                                                                    </label>
                                                                </p>
                                                                <?php if (!empty($surveySecretCommentsEnabled) || (int) ($surveyComment['is_secret'] ?? 0) === 1): ?>
                                                                    <label class="sr-survey-comment-secret-toggle">
                                                                        <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo (int) ($surveyComment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                                        비밀 댓글
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($surveyCommentEditModalId); ?>">닫기</button>
                                                                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($surveyCommentCanDelete): ?>
                                                <form method="post" action="<?php echo sr_e(sr_url('/survey/comment/delete')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $surveyCommentId); ?>">
                                                    <button type="submit" class="btn btn-ghost-danger">삭제</button>
                                                </form>
                                            <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php echo sr_public_pagination_html($surveyCommentPage, '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?submitted=1', '설문 댓글 페이지', 'comment_page', 'survey-comments', 'survey-comments-pagination', ['compact_edges' => true, 'link_class' => 'btn btn-ghost-default', 'current_class' => 'btn btn-solid-primary']); ?>
                    <?php endif; ?>
                    <?php if ($canPreviewAsAdmin): ?>
                        <p>관리자 미리보기에서는 댓글을 작성할 수 없습니다.</p>
                    <?php elseif ($surveyCanWriteComment): ?>
                        <form id="survey-comment-form" method="post" action="<?php echo sr_e(sr_url('/survey/comment')); ?>" class="sr-survey-comment-form">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($survey['id'] ?? 0)); ?>">
                            <input type="hidden" name="parent_comment_id" value="0">
                            <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($surveyCommentPage['page'] ?? 1)); ?>">
                            <p>
                                <label for="survey_comment_body">
                                    <span>댓글 <span class="sr-required-label">(필수)</span></span>
                                    <textarea id="survey_comment_body" name="body_text" rows="5" cols="80"<?php echo $surveyCommentEditorRequiredAttribute; ?> class="form-textarea" data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"<?php echo $surveyCommentEditorAttributes; ?>><?php echo (int) ($surveyCommentParentId ?? 0) < 1 ? sr_e($surveyCommentBody) : ''; ?></textarea>
                                </label>
                            </p>
                            <?php echo sr_comment_extra_fields_form_html($surveyCommentExtraFieldDefinitions, (int) ($surveyCommentParentId ?? 0) < 1 ? $surveyCommentExtraFieldValues : [], 'comment_extra_fields', 'survey_comment'); ?>
                            <?php if (!empty($surveySecretCommentsEnabled)): ?>
                                <label class="sr-survey-comment-secret-toggle">
                                    <input type="checkbox" name="is_secret" value="1" class="form-checkbox"<?php echo $surveyCommentIsSecret ? ' checked' : ''; ?>>
                                    비밀 댓글
                                </label>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-solid-primary">댓글 작성</button>
                        </form>
                    <?php elseif (is_array($currentAccount)): ?>
                        <p>설문 참여 완료 후 댓글을 작성할 수 있습니다.</p>
                    <?php else: ?>
                        <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url($surveyCommentLoginUrl)); ?>" target="_top">로그인 후 댓글 작성</a></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
                </div>
                <?php $surveySidebarSubject = $survey; ?>
                <?php include SR_ROOT . '/modules/survey/views/sidebar.php'; ?>
            </div>
        </div>
    </section>
</main>
<?php
if ($surveyCommentsEnabled && is_array($currentAccount)) {
    echo sr_editor_assets_html($pdo, $surveyCommentEditorKey, $surveyCommentToolbarPreset);
}
if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_public_script_html')) {
    echo sr_reaction_public_script_html();
}
sr_public_layout_end();
