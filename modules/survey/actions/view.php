<?php

require_once __DIR__ . '/../helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

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
if (!is_array($survey) || (string) ($survey['status'] ?? '') !== 'active' || !sr_survey_public_window_is_open($survey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}

$questions = sr_survey_questions_with_choices($pdo, (int) $survey['id']);
$currentAccount = sr_member_current_account($pdo);
$access = is_array($currentAccount)
    ? sr_survey_account_can_respond($pdo, $survey, (int) ($currentAccount['id'] ?? 0))
    : ['allowed' => false, 'message' => '로그인 후 설문에 참여할 수 있습니다.'];
$submitResult = null;
$errors = [];

if (sr_request_method() === 'POST') {
    $account = sr_member_require_login($pdo);
    sr_require_csrf();
    try {
        $submitResult = sr_survey_submit_response(
            $pdo,
            $survey,
            $questions,
            (int) ($account['id'] ?? 0),
            sr_survey_selected_answers_from_post($questions),
            sr_survey_asset_options($pdo)
        );
        $currentAccount = $account;
        $access = sr_survey_account_can_respond($pdo, $survey, (int) ($account['id'] ?? 0));
    } catch (RuntimeException $exception) {
        $message = (string) $exception->getMessage();
        if (!in_array($message, ['현재 참여할 수 없는 설문입니다.', '이미 참여한 설문입니다.', '필수 문항에 답변해 주세요.', '응답 가능한 문항이 없습니다.'], true)) {
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
    'canonical' => '/survey/' . (string) $survey['survey_key'],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => ['/modules/survey/assets/public.css'],
]);
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1><?php echo sr_e((string) $survey['title']); ?></h1>
            <?php if ((string) ($survey['description'] ?? '') !== ''): ?>
                <p><?php echo sr_e((string) $survey['description']); ?></p>
            <?php endif; ?>
            <?php if ($submitResult !== null): ?>
                <section class="sr-survey-result">
                    <h2>참여 완료</h2>
                    <p>설문 응답을 저장했습니다.</p>
                    <?php $grant = is_array($submitResult['reward_grant'] ?? null) ? $submitResult['reward_grant'] : null; ?>
                    <?php if (is_array($grant) && (string) ($grant['status'] ?? '') === 'granted'): ?>
                        <p>보상이 지급되었습니다.</p>
                    <?php elseif (is_array($grant) && (string) ($grant['status'] ?? '') === 'failed'): ?>
                        <p>보상 지급을 확인해야 합니다.</p>
                    <?php endif; ?>
                </section>
            <?php elseif ($errors !== []): ?>
                <div class="sr-form-errors">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo sr_e((string) $error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($submitResult === null): ?>
                <?php if (!is_array($currentAccount)): ?>
                    <p>로그인 후 설문에 참여할 수 있습니다.</p>
                    <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode('/survey/' . (string) $survey['survey_key']))); ?>">로그인</a></p>
                <?php elseif (empty($access['allowed'])): ?>
                    <p><?php echo sr_e((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.')); ?></p>
                <?php elseif ($questions === []): ?>
                    <p>응답 가능한 문항이 없습니다.</p>
                <?php else: ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'])); ?>" class="sr-survey-form">
                        <?php echo sr_csrf_field(); ?>
                        <?php foreach ($questions as $index => $question): ?>
                            <?php $type = (string) ($question['question_type'] ?? 'single_choice'); ?>
                            <fieldset class="sr-survey-question">
                                <legend><?php echo sr_e((string) ($index + 1) . '. ' . (string) ($question['prompt'] ?? '')); ?></legend>
                                <?php if ($type === 'text'): ?>
                                    <textarea name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]" rows="4"></textarea>
                                <?php else: ?>
                                    <?php foreach ((array) ($question['choices'] ?? []) as $choice): ?>
                                        <label class="sr-survey-choice">
                                            <input type="<?php echo $type === 'multiple_choice' ? 'checkbox' : 'radio'; ?>" name="answers[<?php echo sr_e((string) (int) ($question['id'] ?? 0)); ?>]<?php echo $type === 'multiple_choice' ? '[]' : ''; ?>" value="<?php echo sr_e((string) (int) ($choice['id'] ?? 0)); ?>">
                                            <span><?php echo sr_e((string) ($choice['label'] ?? '')); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
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
