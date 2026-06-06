<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$assetOptions = sr_quiz_asset_options($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    if ($intent === 'save') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'edit');
        $values = sr_quiz_admin_values_from_post();
        $postErrors = sr_quiz_admin_validation_errors($pdo, $values, $assetOptions);
        if ($postErrors !== []) {
            $_SESSION['sr_quiz_form_errors'] = $postErrors;
            $_SESSION['sr_quiz_form_values'] = $values;
            $redirect = (int) ($values['id'] ?? 0) > 0
                ? '/admin/quiz?mode=edit&id=' . (string) (int) $values['id']
                : '/admin/quiz?mode=new';
            sr_redirect($redirect);
        }

        $savedId = sr_quiz_save_admin_quiz($pdo, $values, (int) ($account['id'] ?? 0));
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => (int) ($values['id'] ?? 0) > 0 ? 'quiz.updated' : 'quiz.created',
            'target_type' => 'quiz',
            'target_id' => (string) $savedId,
            'result' => 'success',
            'message' => 'Quiz saved.',
            'metadata' => [
                'quiz_key' => (string) ($values['quiz_key'] ?? ''),
                'status' => (string) ($values['status'] ?? ''),
                'reward_enabled' => (int) ($values['reward_enabled'] ?? 0),
                'reward_module' => (string) ($values['reward_module'] ?? ''),
                'content_source_ids' => (string) ($values['content_source_ids'] ?? ''),
                'question_count' => count((array) ($values['questions'] ?? [])),
            ],
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈를 저장했습니다.'), '/admin/quiz');
    } elseif ($intent === 'delete') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'delete');
        $quizId = (int) sr_post_string('quiz_id', 20);
        if (!sr_quiz_soft_delete($pdo, $quizId, (int) ($account['id'] ?? 0))) {
            sr_admin_redirect_with_result(sr_admin_action_result(['삭제할 퀴즈를 찾을 수 없습니다.'], ''), '/admin/quiz');
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'quiz.deleted',
            'target_type' => 'quiz',
            'target_id' => (string) $quizId,
            'result' => 'success',
            'message' => 'Quiz soft deleted.',
            'metadata' => [],
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈를 삭제했습니다.'), '/admin/quiz');
    }

    sr_admin_redirect_with_result(sr_admin_action_result(['지원하지 않는 퀴즈 관리 요청입니다.'], ''), '/admin/quiz');
}

$sessionErrors = $_SESSION['sr_quiz_form_errors'] ?? [];
$sessionValues = $_SESSION['sr_quiz_form_values'] ?? [];
unset($_SESSION['sr_quiz_form_errors'], $_SESSION['sr_quiz_form_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}

$mode = sr_get_string('mode', 20);
if (!in_array($mode, ['new', 'edit'], true)) {
    $mode = 'list';
}

$editQuiz = null;
if ($mode === 'edit') {
    $editQuiz = sr_quiz_admin_quiz_by_id($pdo, (int) sr_get_string('id', 20));
    if (!is_array($editQuiz)) {
        sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
    }
}

$values = is_array($sessionValues) && $sessionValues !== []
    ? $sessionValues
    : (is_array($editQuiz) ? sr_quiz_admin_values_from_row($editQuiz) : sr_quiz_default_admin_values());

if ($mode === 'list') {
    $quizzes = sr_quiz_admin_quizzes($pdo);
    ?>
    <div class="admin-card">
        <div class="admin-card-header">
            <h2>퀴즈 관리</h2>
            <div class="card-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=new')); ?>">새 퀴즈</a>
            </div>
        </div>
        <div class="admin-card-body">
            <?php if ($notice !== '') { ?>
                <div class="admin-notice"><?php echo sr_e($notice); ?></div>
            <?php } ?>
            <?php if ($errors !== []) { ?>
                <div class="admin-error">
                    <?php foreach ($errors as $error) { ?>
                        <p><?php echo sr_e((string) $error); ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <?php if ($quizzes === []) { ?>
                <p class="admin-empty">등록된 퀴즈가 없습니다.</p>
            <?php } else { ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>제목</th>
                            <th>상태</th>
                            <th>문제</th>
                            <th>연결</th>
                            <th>통과</th>
                            <th>보상</th>
                            <th>수정일</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quizzes as $quiz) { ?>
                            <tr>
                                <td><code><?php echo sr_e((string) $quiz['quiz_key']); ?></code></td>
                                <td><?php echo sr_e((string) $quiz['title']); ?></td>
                                <td><?php echo sr_e(sr_quiz_status_label((string) $quiz['status'])); ?></td>
                                <td><?php echo sr_e((string) (int) ($quiz['question_count'] ?? 0)); ?></td>
                                <td><?php echo sr_e((string) (int) ($quiz['source_count'] ?? 0)); ?></td>
                                <td><?php echo sr_e((string) ($quiz['pass_score'] ?? '')); ?></td>
                                <td><?php echo ((int) ($quiz['reward_enabled'] ?? 0) === 1) ? '사용' : '미사용'; ?></td>
                                <td><?php echo sr_e((string) $quiz['updated_at']); ?></td>
                                <td class="admin-table-actions">
                                    <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=edit&id=' . (string) (int) $quiz['id'])); ?>" aria-label="퀴즈 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="delete">
                                        <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) $quiz['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="퀴즈 삭제" title="삭제" data-confirm="<?php echo sr_e('퀴즈를 삭제할까요? 기존 기록은 보관됩니다.'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>
    </div>
    <?php
    return;
}

$questions = (array) ($values['questions'] ?? []);
for ($extraIndex = 0; $extraIndex < 2; $extraIndex++) {
    $nextNumber = count($questions) + 1;
    $questions[] = [
        'question_key' => '',
        'prompt' => '',
        'score_value' => 1,
        'choices' => [
                    ['choice_key' => '', 'label' => '', 'is_correct' => 1],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                ],
            ];
}
?>
<form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save">
    <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
    <div class="admin-card">
        <div class="admin-card-header">
            <h2><?php echo $mode === 'edit' ? '퀴즈 수정' : '퀴즈 생성'; ?></h2>
        </div>
        <div class="admin-card-body">
            <?php if ($errors !== []) { ?>
                <div class="admin-error">
                    <?php foreach ($errors as $error) { ?>
                        <p><?php echo sr_e((string) $error); ?></p>
                    <?php } ?>
                </div>
            <?php } ?>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_key">Key <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_key" type="text" name="quiz_key" value="<?php echo sr_e((string) ($values['quiz_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_title">제목 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="190" required>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_description">설명</label>
                <div class="admin-form-field">
                    <textarea id="quiz_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_status">상태 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_status" name="status" class="form-select" required>
                        <?php foreach (sr_quiz_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_status_label($status)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_pass_score">통과 점수</label>
                <div class="admin-form-field">
                    <input id="quiz_pass_score" type="number" name="pass_score" value="<?php echo sr_e((string) ($values['pass_score'] ?? '')); ?>" class="form-input" min="0" step="1">
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h2>문제/선택지</h2>
        </div>
        <div class="admin-card-body">
            <?php foreach ($questions as $questionIndex => $question) { ?>
                <?php
                $questionUid = 'qrow_' . (string) $questionIndex;
                $choices = (array) ($question['choices'] ?? []);
                for ($choiceExtra = count($choices); $choiceExtra < 4; $choiceExtra++) {
                    $choices[] = ['choice_key' => chr(97 + $choiceExtra), 'label' => '', 'is_correct' => 0];
                }
                ?>
                <section class="admin-quiz-question-block">
                    <div class="admin-section-header">
                        <h3>문제 <?php echo sr_e((string) ($questionIndex + 1)); ?></h3>
                    </div>
                    <div class="admin-section-body">
                        <input type="hidden" name="question_uid[]" value="<?php echo sr_e($questionUid); ?>">
                        <div class="admin-form-row">
                            <label class="form-label" for="quiz_question_key_<?php echo sr_e((string) $questionIndex); ?>">문제 Key</label>
                            <div class="admin-form-field">
                                <input id="quiz_question_key_<?php echo sr_e((string) $questionIndex); ?>" type="text" name="question_key[]" value="<?php echo sr_e((string) ($question['question_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>">문제</label>
                            <div class="admin-form-field">
                                <textarea id="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>" name="question_prompt[]" class="form-textarea" rows="3"><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></textarea>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="quiz_question_score_<?php echo sr_e((string) $questionIndex); ?>">점수</label>
                            <div class="admin-form-field">
                                <input id="quiz_question_score_<?php echo sr_e((string) $questionIndex); ?>" type="number" name="question_score[]" value="<?php echo sr_e((string) ($question['score_value'] ?? 1)); ?>" class="form-input" min="0" step="1">
                            </div>
                        </div>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>정답</th>
                                    <th>선택지 Key</th>
                                    <th>선택지</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($choices as $choiceIndex => $choice) { ?>
                                    <tr>
                                        <td><input type="radio" name="correct_choice[<?php echo sr_e($questionUid); ?>]" value="<?php echo sr_e((string) $choiceIndex); ?>"<?php echo (int) ($choice['is_correct'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                        <td><input type="text" name="choice_key[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['choice_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input></td>
                                        <td><input type="text" name="choice_label[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['label'] ?? '')); ?>" class="form-input form-control-full" maxlength="255"></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php } ?>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h2>보상</h2>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_reward_enabled">보상 사용</label>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="quiz_reward_enabled">
                        <input id="quiz_reward_enabled" type="checkbox" name="reward_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['reward_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('자동 채점 통과 시 지급'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_reward_module">보상 자산</label>
                <div class="admin-form-field">
                    <select id="quiz_reward_module" name="reward_module" class="form-select">
                        <option value="">선택안함</option>
                        <?php foreach ($assetOptions as $assetKey => $asset) { ?>
                            <option value="<?php echo sr_e((string) $assetKey); ?>"<?php echo (string) ($values['reward_module'] ?? '') === (string) $assetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $assetKey)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_reward_amount">보상 금액</label>
                <div class="admin-form-field">
                    <input id="quiz_reward_amount" type="number" name="reward_amount" value="<?php echo sr_e((string) ($values['reward_amount'] ?? '')); ?>" class="form-input" min="1" step="1">
                </div>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card-header">
            <h2>콘텐츠 연결</h2>
        </div>
        <div class="admin-card-body">
            <div class="admin-form-row">
                <label class="form-label" for="quiz_content_source_ids">콘텐츠 ID</label>
                <div class="admin-form-field">
                    <textarea id="quiz_content_source_ids" name="content_source_ids" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['content_source_ids'] ?? '')); ?></textarea>
                    <p class="admin-form-help">MVP는 콘텐츠 ID를 줄바꿈 또는 쉼표로 입력해 연결합니다.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
