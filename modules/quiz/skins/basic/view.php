<?php

require_once __DIR__ . '/../../helpers.php';
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
$quizKey = trim(substr($quizPath, strlen('/quiz')), '/');
$quizKey = rawurldecode($quizKey);
if (!sr_quiz_key_is_valid($quizKey)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
$quiz = sr_quiz_by_key($pdo, $quizKey);
$previewMode = sr_get_string('preview', 20) === 'admin';
$currentAccount = sr_member_current_account($pdo);
$adminHasQuizViewPermission = is_array($currentAccount)
    && sr_admin_has_permission($pdo, (int) ($currentAccount['id'] ?? 0), '/admin/quiz', 'view');
$isPubliclyOpen = is_array($quiz) && (string) ($quiz['status'] ?? '') === 'active' && sr_quiz_public_window_is_open($quiz);
$canPreviewAsAdmin = $adminHasQuizViewPermission
    && ($previewMode || !$isPubliclyOpen);
$previewMode = $canPreviewAsAdmin;

if (!is_array($quiz) || (!$isPubliclyOpen && !$canPreviewAsAdmin)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
$quizSettings = sr_quiz_settings($pdo);
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
unset($_SESSION['sr_quiz_comment_notice'], $_SESSION['sr_quiz_comment_errors'], $_SESSION['sr_quiz_comment_body'], $_SESSION['sr_quiz_comment_is_secret'], $_SESSION['sr_quiz_comment_parent_id']);
$quizCommentLoginUrl = '/login?next=' . rawurlencode($quizFormUrl . '?result=1#quiz-comments');

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

$quizReactionCommentTargets = [];
if (
    sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_resolve_targets')
    && !$canPreviewAsAdmin
    && is_array($quizComments ?? null)
    && $quizComments !== []
) {
    $quizReactionCommentIds = [];
    foreach ($quizComments as $quizReactionComment) {
        $quizReactionCommentId = (int) ($quizReactionComment['id'] ?? 0);
        if ($quizReactionCommentId > 0) {
            $quizReactionCommentIds[] = (string) $quizReactionCommentId;
        }
    }
    $quizReactionCommentTargets = sr_reaction_resolve_targets(
        $pdo,
        'quiz',
        'comment',
        $quizReactionCommentIds,
        is_array($currentAccount) ? (int) ($currentAccount['id'] ?? 0) : 0
    );
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
if ($canPreviewAsAdmin) {
    $seo['robots'] = 'noindex, nofollow';
}
$quizShareUrl = sr_absolute_url($site ?? null, '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')));
$quizConsumerTarget = $submitResult !== null ? 'quiz.result' : 'quiz.view';

$quizLayoutContext = sr_quiz_public_layout_context($quizSettings, [
    'consumer_target' => $quizConsumerTarget,
    'body_class' => $quizEmbedded ? 'sr-quiz-page sr-quiz-embed-page' : 'sr-quiz-page',
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
    $quizEmbedBodyClass = sr_ui_icon_class_attr((string) ($quizLayoutContext['body_class'] ?? 'sr-quiz-page sr-quiz-embed-page'));
    $quizEmbedSeo = array_merge($seo, ['robots' => 'noindex, nofollow']);
    if ($pdo instanceof PDO) {
        $quizEmbedSeo = sr_site_apply_public_meta_defaults($pdo, $quizEmbedSeo);
    }
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
<body class="<?php echo sr_e($quizEmbedBodyClass); ?>">
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

<main class="quiz-page-main">
    <section class="quiz-page-section">
        <div class="quiz-page-container">
            <h1><?php echo sr_e((string) $quiz['title']); ?></h1>
            <?php echo sr_quiz_cover_image_html($quiz, 'sr-quiz-cover-image', (string) ($quiz['title'] ?? '')); ?>
            <?php if ((string) ($quiz['description'] ?? '') !== ''): ?>
                <p><?php echo sr_e((string) $quiz['description']); ?></p>
            <?php endif; ?>
            <?php if ($canPreviewAsAdmin): ?>
                <div class="sr-quiz-preview-notice">
                    <p>관리자 미리보기입니다. 초안, 중지, 기간 외 퀴즈도 확인할 수 있으며 제출은 저장되지 않습니다.</p>
                </div>
            <?php endif; ?>
            <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin && $submitResult !== null): ?>
                <?php echo sr_reaction_render_widget($pdo, 'quiz', 'quiz_set', (string) (int) ($quiz['id'] ?? 0), is_array($currentAccount) ? $currentAccount : null); ?>
            <?php endif; ?>
            <?php if ($submitResult !== null): ?>
                <?php include sr_quiz_skin_view_file($quizSettings, 'result'); ?>
            <?php elseif ($questions === []): ?>
                <p>응시 가능한 문제가 없습니다.</p>
            <?php else: ?>
                <?php if ($submitErrors !== []): ?>
                    <div class="sr-form-errors">
                        <?php foreach ($submitErrors as $error): ?>
                            <p><?php echo sr_e((string) $error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($canPreviewAsAdmin): ?>
                    <form class="sr-quiz-form">
                        <?php foreach ($questions as $questionIndex => $question): ?>
                            <?php $questionType = (string) ($question['question_type'] ?? 'single_choice'); ?>
                            <fieldset class="sr-quiz-question">
                                <legend><?php echo sr_e((string) ($questionIndex + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                                <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                    <label class="sr-quiz-choice" for="<?php echo sr_e('quiz_preview_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>">
                                        <?php if ($questionType === 'multiple_choice'): ?>
                                            <input id="<?php echo sr_e('quiz_preview_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>" type="checkbox" disabled>
                                        <?php else: ?>
                                            <input id="<?php echo sr_e('quiz_preview_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>" type="radio" disabled>
                                        <?php endif; ?>
                                        <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                    </form>
                <?php elseif (!is_array($currentAccount)): ?>
                    <p>로그인 후 퀴즈를 풀 수 있습니다.</p>
                    <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($quizNextUrl))); ?>" target="_top">로그인</a></p>
                <?php elseif (empty($attemptAccess['allowed'])): ?>
                    <p><?php echo sr_e((string) ($attemptAccess['message'] ?? '현재 응시할 수 없는 퀴즈입니다.')); ?></p>
                <?php else: ?>
                    <form method="post" action="<?php echo sr_e(sr_url($quizNextUrl)); ?>" class="sr-quiz-form">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <input type="hidden" name="source_module" value="<?php echo sr_e($sourceModule); ?>">
                        <input type="hidden" name="source_type" value="<?php echo sr_e($sourceType); ?>">
                        <input type="hidden" name="source_id" value="<?php echo sr_e((string) $sourceId); ?>">
                        <?php foreach ($questions as $questionIndex => $question): ?>
                            <?php $questionType = (string) ($question['question_type'] ?? 'single_choice'); ?>
                            <fieldset class="sr-quiz-question">
                                <legend><?php echo sr_e((string) ($questionIndex + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                                <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                    <label class="sr-quiz-choice" for="<?php echo sr_e('quiz_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>">
                                        <?php if ($questionType === 'multiple_choice'): ?>
                                            <input id="<?php echo sr_e('quiz_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>" type="checkbox" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>][]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                        <?php else: ?>
                                            <input id="<?php echo sr_e('quiz_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>" type="radio" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                        <?php endif; ?>
                                        <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-solid-primary">제출</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($submitResult !== null): ?>
                <p>
                    <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/quiz')); ?>" target="_top">메인으로</a>
                    <label class="sr-only" for="quiz_share_url">공유 주소</label>
                    <input id="quiz_share_url" type="url" value="<?php echo sr_e($quizShareUrl); ?>" readonly data-sr-share-url>
                    <button type="button" class="btn btn-solid-light" data-sr-share-copy="<?php echo sr_e($quizShareUrl); ?>">공유 주소 복사</button>
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
            <?php if ($quizCommentsEnabled && $submitResult !== null): ?>
                <section id="quiz-comments" class="sr-quiz-comments">
                    <div class="sr-quiz-comments-panel-header">
                        <h2>댓글 <span><?php echo sr_e(number_format((int) ($quizCommentPage['total'] ?? 0))); ?></span></h2>
                    </div>
                    <?php echo sr_public_feedback_toasts('quiz', $quizCommentNotice, $quizCommentErrors); ?>
                    <?php if ($quizComments === []): ?>
                        <p>아직 작성된 댓글이 없습니다.</p>
                    <?php else: ?>
                        <ul class="sr-quiz-comment-list">
                            <?php foreach ($quizComments as $quizComment): ?>
                                <?php
                                $quizCommentId = (int) ($quizComment['id'] ?? 0);
                                $quizCommentEditId = 'quiz_comment_edit_' . (string) $quizCommentId;
                                $quizCommentEditModalId = 'quiz_comment_edit_modal_' . (string) $quizCommentId;
                                $quizCommentCanViewBody = sr_quiz_account_can_view_comment_body($quizComment, is_array($currentAccount) ? $currentAccount : null, $pdo);
                                $quizCommentCanEdit = is_array($currentAccount) && sr_quiz_account_can_edit_comment($quizComment, $currentAccount);
                                $quizCommentCanDelete = is_array($currentAccount) && sr_quiz_account_can_delete_comment($quizComment, $currentAccount, $pdo);
                                $quizCommentDepth = min(3, max(1, (int) ($quizComment['depth'] ?? 1)));
                                $quizCommentCanReply = is_array($currentAccount) && !$canPreviewAsAdmin && $quizCommentCanViewBody && $quizCommentDepth < 3;
                                $quizCommentReplyId = 'quiz_comment_reply_' . (string) $quizCommentId;
                                $quizCommentReplyModalId = 'quiz_comment_reply_modal_' . (string) $quizCommentId;
                                ?>
                                <li id="quiz-comment-<?php echo sr_e((string) $quizCommentId); ?>" class="sr-quiz-comment-depth-<?php echo sr_e((string) $quizCommentDepth); ?>">
                                    <div class="sr-quiz-comment-meta">
                                        <strong><?php echo sr_e((string) ($quizComment['author_public_name'] ?? $quizComment['author_public_name_snapshot'] ?? '회원')); ?></strong>
                                        <?php echo sr_quiz_time_html((string) ($quizComment['created_at'] ?? '')); ?>
                                        <?php if ((int) ($quizComment['is_secret'] ?? 0) === 1): ?>
                                            <span class="sr-quiz-comment-secret">비밀 댓글</span>
                                        <?php endif; ?>
                                        <?php if ($quizCommentDepth > 1): ?>
                                            <span>답글 <?php echo sr_e((string) $quizCommentDepth); ?>단계</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($quizCommentCanViewBody): ?>
                                        <p><?php echo sr_member_mention_plain_text_html((string) ($quizComment['body_text'] ?? '')); ?></p>
                                        <?php if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_render_widget') && !$canPreviewAsAdmin): ?>
                                            <?php
                                            $quizCommentReactionOptions = ['label' => '댓글 리액션'];
                                            if (isset($quizReactionCommentTargets[(string) $quizCommentId]) && is_array($quizReactionCommentTargets[(string) $quizCommentId])) {
                                                $quizCommentReactionOptions['resolved_target'] = $quizReactionCommentTargets[(string) $quizCommentId];
                                            }
                                            ?>
                                            <?php echo sr_reaction_render_widget($pdo, 'quiz', 'comment', (string) $quizCommentId, is_array($currentAccount) ? $currentAccount : null, $quizCommentReactionOptions); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p>비밀 댓글입니다.</p>
                                    <?php endif; ?>
                                    <?php if ($quizCommentCanEdit || $quizCommentCanDelete || $quizCommentCanReply): ?>
                                        <div class="sr-quiz-comment-actions">
                                            <?php if ($quizCommentCanReply): ?>
                                                <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizCommentReplyModalId); ?>" data-overlay="#<?php echo sr_e($quizCommentReplyModalId); ?>">답글</button>
                                                <div id="<?php echo sr_e($quizCommentReplyModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($quizCommentReplyModalId . '_title'); ?>" aria-hidden="true" inert>
                                                    <div class="modal-dialog">
                                                        <form method="post" action="<?php echo sr_e(sr_url('/quiz/comment')); ?>" class="modal-content">
                                                            <?php echo sr_csrf_field(); ?>
                                                            <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) ($quiz['id'] ?? 0)); ?>">
                                                            <input type="hidden" name="parent_comment_id" value="<?php echo sr_e((string) $quizCommentId); ?>">
                                                            <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($quizCommentPage['page'] ?? 1)); ?>">
                                                            <div class="modal-header">
                                                                <h3 id="<?php echo sr_e($quizCommentReplyModalId . '_title'); ?>" class="modal-title">답글 작성</h3>
                                                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($quizCommentReplyModalId); ?>">
                                                                    <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <strong class="sr-quiz-comment-reply-source-label">댓글</strong>
                                                                <p class="sr-quiz-comment-reply-source"><?php echo sr_member_mention_plain_text_html((string) ($quizComment['body_text'] ?? '')); ?></p>
                                                                <label class="sr-only" for="<?php echo sr_e($quizCommentReplyId); ?>">답글 본문</label>
                                                                <textarea id="<?php echo sr_e($quizCommentReplyId); ?>" name="body_text" rows="3" cols="60" required data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo (int) ($quizCommentParentId ?? 0) === $quizCommentId ? sr_e($quizCommentBody) : ''; ?></textarea>
                                                                <?php if (!empty($quizSecretCommentsEnabled)): ?>
                                                                    <label class="sr-quiz-comment-secret-toggle">
                                                                        <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($quizCommentParentId ?? 0) === $quizCommentId && $quizCommentIsSecret ? ' checked' : ''; ?>>
                                                                        비밀 댓글
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($quizCommentReplyModalId); ?>">닫기</button>
                                                                <button type="submit" class="btn btn-solid-primary modal-action">답글 작성</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($quizCommentCanEdit): ?>
                                                <button type="button" class="btn btn-ghost-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($quizCommentEditModalId); ?>" data-overlay="#<?php echo sr_e($quizCommentEditModalId); ?>">수정</button>
                                                <div id="<?php echo sr_e($quizCommentEditModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($quizCommentEditModalId . '_title'); ?>" aria-hidden="true" inert>
                                                    <div class="modal-dialog">
                                                        <form method="post" action="<?php echo sr_e(sr_url('/quiz/comment/edit')); ?>" class="modal-content">
                                                            <?php echo sr_csrf_field(); ?>
                                                            <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $quizCommentId); ?>">
                                                            <div class="modal-header">
                                                                <h3 id="<?php echo sr_e($quizCommentEditModalId . '_title'); ?>" class="modal-title">댓글 수정</h3>
                                                                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($quizCommentEditModalId); ?>">
                                                                    <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <label class="sr-only" for="<?php echo sr_e($quizCommentEditId); ?>">댓글 본문</label>
                                                                <textarea id="<?php echo sr_e($quizCommentEditId); ?>" name="body_text" rows="3" cols="60" required data-overlay-focus data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo sr_e((string) ($quizComment['body_text'] ?? '')); ?></textarea>
                                                                <?php if (!empty($quizSecretCommentsEnabled) || (int) ($quizComment['is_secret'] ?? 0) === 1): ?>
                                                                    <label class="sr-quiz-comment-secret-toggle">
                                                                        <input type="checkbox" name="is_secret" value="1"<?php echo (int) ($quizComment['is_secret'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                                                        비밀 댓글
                                                                    </label>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($quizCommentEditModalId); ?>">닫기</button>
                                                                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($quizCommentCanDelete): ?>
                                                <form method="post" action="<?php echo sr_e(sr_url('/quiz/comment/delete')); ?>">
                                                    <?php echo sr_csrf_field(); ?>
                                                    <input type="hidden" name="comment_id" value="<?php echo sr_e((string) $quizCommentId); ?>">
                                                    <button type="submit" class="btn btn-ghost-danger">삭제</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php echo sr_public_pagination_html($quizCommentPage, '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?result=1', '퀴즈 댓글 페이지', 'comment_page', 'quiz-comments', 'quiz-comments-pagination'); ?>
                    <?php endif; ?>
                    <?php if ($canPreviewAsAdmin): ?>
                        <p>관리자 미리보기에서는 댓글을 작성할 수 없습니다.</p>
                    <?php elseif (is_array($currentAccount)): ?>
                        <form method="post" action="<?php echo sr_e(sr_url('/quiz/comment')); ?>" class="sr-quiz-comment-form">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) ($quiz['id'] ?? 0)); ?>">
                            <input type="hidden" name="parent_comment_id" value="0">
                            <input type="hidden" name="comment_page" value="<?php echo sr_e((string) (int) ($quizCommentPage['page'] ?? 1)); ?>">
                            <label class="sr-only" for="quiz_comment_body">댓글 본문</label>
                            <textarea id="quiz_comment_body" name="body_text" rows="4" cols="60" required data-sr-mention-input data-sr-mention-endpoint="<?php echo sr_e(sr_url('/member/mention-search')); ?>"><?php echo (int) ($quizCommentParentId ?? 0) < 1 ? sr_e($quizCommentBody) : ''; ?></textarea>
                            <?php if (!empty($quizSecretCommentsEnabled)): ?>
                                <label class="sr-quiz-comment-secret-toggle">
                                    <input type="checkbox" name="is_secret" value="1"<?php echo $quizCommentIsSecret ? ' checked' : ''; ?>>
                                    비밀 댓글
                                </label>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-solid-primary">댓글 작성</button>
                        </form>
                    <?php else: ?>
                        <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url($quizCommentLoginUrl)); ?>" target="_top">로그인 후 댓글 작성</a></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
if (sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_public_script_html')) {
    echo sr_reaction_public_script_html();
}
if ($quizEmbedded) {
    echo sr_script_tags($quizEmbedScripts ?? []);
    ?>
</body>
</html>
    <?php
} else {
    sr_public_layout_end();
}
