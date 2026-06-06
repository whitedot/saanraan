<?php

require_once __DIR__ . '/../helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

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

if (!is_array($quiz) || (string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
$questions = sr_quiz_questions_with_choices($pdo, (int) ($quiz['id'] ?? 0));
$currentAccount = sr_member_current_account($pdo);
$attemptAccess = is_array($currentAccount)
    ? sr_quiz_account_can_attempt($pdo, $quiz, (int) ($currentAccount['id'] ?? 0))
    : ['allowed' => false, 'message' => '로그인 후 퀴즈를 풀 수 있습니다.'];
$submitResult = null;
$submitErrors = [];
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
if ($quizQuery !== []) {
    $quizNextUrl .= '?' . http_build_query($quizQuery);
}

if (sr_request_method() === 'POST') {
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

$seo = [
    'title' => (string) $quiz['title'],
    'canonical' => '/quiz/' . (string) $quiz['quiz_key'],
    'og' => [
        'title' => (string) $quiz['title'],
        'type' => 'article',
    ],
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'body_class' => 'sr-quiz-page',
]);
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1><?php echo sr_e((string) $quiz['title']); ?></h1>
            <?php if ((string) ($quiz['description'] ?? '') !== ''): ?>
                <p><?php echo sr_e((string) $quiz['description']); ?></p>
            <?php endif; ?>
            <?php if ($submitResult !== null): ?>
                <section class="sr-quiz-result">
                    <h2>퀴즈 결과</h2>
                    <p>점수: <?php echo sr_e((string) (int) ($submitResult['total_score'] ?? 0)); ?></p>
                    <p><?php echo !empty($submitResult['passed']) ? '통과했습니다.' : '통과하지 못했습니다.'; ?></p>
                    <?php $rewardGrant = is_array($submitResult['reward_grant'] ?? null) ? $submitResult['reward_grant'] : null; ?>
                    <?php if (is_array($rewardGrant) && (string) ($rewardGrant['status'] ?? '') === 'granted'): ?>
                        <p>보상이 지급되었습니다.</p>
                    <?php elseif (is_array($rewardGrant) && (string) ($rewardGrant['status'] ?? '') === 'failed'): ?>
                        <p>보상 지급을 확인해야 합니다.</p>
                    <?php endif; ?>
                    <?php $returnTo = sr_quiz_internal_return_path(sr_post_string('return_to', 255)); ?>
                    <?php if ($returnTo !== ''): ?>
                        <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url($returnTo)); ?>" target="_top">돌아가기</a></p>
                    <?php endif; ?>
                </section>
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
                <?php if (!is_array($currentAccount)): ?>
                    <p>로그인 후 퀴즈를 풀 수 있습니다.</p>
                    <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode($quizNextUrl))); ?>" target="_top">로그인</a></p>
                <?php elseif (empty($attemptAccess['allowed'])): ?>
                    <p><?php echo sr_e((string) ($attemptAccess['message'] ?? '현재 응시할 수 없는 퀴즈입니다.')); ?></p>
                <?php else: ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/quiz/' . (string) $quiz['quiz_key'])); ?>" class="sr-quiz-form">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <input type="hidden" name="source_module" value="<?php echo sr_e($sourceModule); ?>">
                        <input type="hidden" name="source_type" value="<?php echo sr_e($sourceType); ?>">
                        <input type="hidden" name="source_id" value="<?php echo sr_e((string) $sourceId); ?>">
                        <?php foreach ($questions as $questionIndex => $question): ?>
                            <fieldset class="sr-quiz-question">
                                <legend><?php echo sr_e((string) ($questionIndex + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                                <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                    <label class="sr-quiz-choice" for="<?php echo sr_e('quiz_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>">
                                        <input id="<?php echo sr_e('quiz_choice_' . (string) (int) ($choice['id'] ?? 0)); ?>" type="radio" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                        <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <button type="submit" class="btn btn-solid-primary">제출</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
