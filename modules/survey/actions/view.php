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
$currentAccountId = is_array($currentAccount) ? (int) ($currentAccount['id'] ?? 0) : 0;
$access = sr_survey_account_can_respond($pdo, $survey, $currentAccountId);
$submitResult = null;
$errors = [];

if (sr_request_method() === 'POST') {
    $account = (int) ($survey['login_required'] ?? 1) === 1 ? sr_member_require_login($pdo) : $currentAccount;
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
            false
        );
        $currentAccount = $account;
        $access = sr_survey_account_can_respond($pdo, $survey, $accountId);
    } catch (RuntimeException $exception) {
        $message = (string) $exception->getMessage();
        if (!in_array($message, ['현재 참여할 수 없는 설문입니다.', '이미 참여한 설문입니다.', '필수 문항에 답변해 주세요.', '응답 가능한 문항이 없습니다.', '참여 동의가 필요합니다.', '응답 제한 기간 설정을 확인해야 합니다.', '선택지 값을 확인해 주세요.', '복수 선택 문항의 최소 선택 수를 확인해 주세요.', '복수 선택 문항의 최대 선택 수를 확인해 주세요.', '숫자 문항에는 숫자만 입력해 주세요.', '정수만 입력할 수 있는 문항입니다.', '숫자 문항의 최소값을 확인해 주세요.', '숫자 문항의 최대값을 확인해 주세요.', '별점 범위를 확인해 주세요.'], true)) {
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
if ((int) ($survey['login_required'] ?? 1) === 1 || (int) ($survey['public_listed'] ?? 1) !== 1 || (string) ($survey['robots_policy'] ?? 'auto') === 'noindex') {
    $seo['robots'] = 'noindex, nofollow';
}
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
            <?php if ((string) ($survey['research_purpose'] ?? '') !== '' || (int) ($survey['estimated_minutes'] ?? 0) > 0 || (string) ($survey['organizer_name'] ?? '') !== ''): ?>
                <section class="sr-survey-info" aria-labelledby="survey_info_title">
                    <h2 id="survey_info_title">참여 안내</h2>
                    <?php if ((string) ($survey['research_purpose'] ?? '') !== ''): ?>
                        <p><?php echo sr_e((string) $survey['research_purpose']); ?></p>
                    <?php endif; ?>
                    <?php if ((string) ($survey['target_population'] ?? '') !== ''): ?>
                        <p>참여 대상: <?php echo sr_e((string) $survey['target_population']); ?></p>
                    <?php endif; ?>
                    <?php if ((int) ($survey['estimated_minutes'] ?? 0) > 0): ?>
                        <p>예상 소요 시간: <?php echo sr_e((string) (int) $survey['estimated_minutes']); ?>분</p>
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
                    <?php if ((int) ($survey['reward_enabled'] ?? 0) === 1): ?>
                        <p>참여 완료 후 보상 지급 조건을 다시 확인한 뒤 보상 지급을 시도합니다.</p>
                    <?php endif; ?>
                </section>
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
                <?php if ((int) ($survey['login_required'] ?? 1) === 1 && !is_array($currentAccount)): ?>
                    <p>로그인 후 설문에 참여할 수 있습니다.</p>
                    <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/login?next=' . rawurlencode('/survey/' . (string) $survey['survey_key']))); ?>">로그인</a></p>
                <?php elseif (empty($access['allowed'])): ?>
                    <p><?php echo sr_e((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.')); ?></p>
                <?php elseif ($questions === []): ?>
                    <p>응답 가능한 문항이 없습니다.</p>
                <?php else: ?>
                    <form method="post" action="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'])); ?>" class="sr-survey-form">
                        <?php echo sr_csrf_field(); ?>
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
