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
$memberGroups = sr_quiz_member_groups_for_admin($pdo);

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

        try {
            $savedId = sr_quiz_save_admin_quiz($pdo, $values, (int) ($account['id'] ?? 0));
        } catch (RuntimeException $exception) {
            if ((string) $exception->getMessage() !== 'Quiz to update was not found.') {
                throw $exception;
            }
            sr_admin_redirect_with_result(sr_admin_action_result(['저장할 퀴즈를 찾을 수 없습니다.'], ''), '/admin/quiz');
        }
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

$adminPageTitle = $mode === 'list' ? '퀴즈 관리' : ($mode === 'edit' ? '퀴즈 수정' : '퀴즈 생성');
include SR_ROOT . '/modules/admin/views/layout-header.php';

if ($mode === 'list') {
    $quizFilters = sr_quiz_admin_quiz_filters_from_request();
    $quizSortOptions = sr_quiz_admin_quiz_sort_options();
    $quizDefaultSort = sr_quiz_admin_quiz_default_sort();
    $quizSort = sr_admin_sort_from_request($quizSortOptions, $quizDefaultSort);
    $quizTotal = sr_quiz_admin_quiz_count($pdo, $quizFilters);
    $quizPagination = sr_admin_pagination_from_total($pdo, $quizTotal);
    $quizzes = sr_quiz_admin_quizzes($pdo, $quizFilters, (int) $quizPagination['per_page'], sr_admin_pagination_offset($quizPagination), $quizSort);
    $quizDetailFilterOpen = (array) ($quizFilters['status'] ?? []) !== [] || (array) ($quizFilters['quiz_mode'] ?? []) !== [] || (string) ($quizFilters['reward_enabled'] ?? '') !== '';
    $quizStatusOptions = [];
    foreach (sr_quiz_statuses() as $status) {
        $quizStatusOptions[$status] = sr_quiz_status_label($status);
    }
    $quizModeOptions = [];
    foreach (sr_quiz_modes() as $quizMode) {
        $quizModeOptions[$quizMode] = sr_quiz_mode_label($quizMode);
    }
    ?>
    <?php echo sr_admin_feedback_toasts($notice, $errors); ?>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="filtering-form admin-quiz-filter ui-form-theme">
        <div class="filtering filtering-card<?php echo $quizDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields">
                <div class="filtering-field filtering-field-fill admin-quiz-filter-keyword">
                    <label for="quiz_keyword_filter" class="filtering-label">검색어</label>
                    <input id="quiz_keyword_filter" type="text" name="q" value="<?php echo sr_e((string) ($quizFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="퀴즈 키, 제목, 설명">
                </div>
            </div>
            <div id="quiz_detail_filters" class="filtering-body" data-filtering-body<?php echo $quizDetailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field">
                    <span class="filtering-label">상태</span>
                    <?php echo sr_admin_filter_toggle_group_html('quiz_status_filter', 'status', $quizStatusOptions, (array) ($quizFilters['status'] ?? []), '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">모드</span>
                    <?php echo sr_admin_filter_toggle_group_html('quiz_mode_filter', 'quiz_mode', $quizModeOptions, (array) ($quizFilters['quiz_mode'] ?? []), '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">보상</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('quiz_reward_filter', 'reward_enabled', ['enabled' => '사용', 'disabled' => '미사용'], [(string) ($quizFilters['reward_enabled'] ?? '')], '전체'); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $quizDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="quiz_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">퀴즈 목록</h2>
            <div class="card-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=new')); ?>">새 퀴즈</a>
            </div>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($quizSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($quizSortOptions, $quizDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="퀴즈 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($quizPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table admin-quiz-table">
                <thead class="ui-table-head">
                    <tr>
                        <th<?php echo sr_admin_sort_aria('quiz_key', $quizSort); ?>><?php echo sr_admin_sort_header_html('Key', 'quiz_key', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('title', $quizSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $quizSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('question_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('문제', 'question_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('source_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('연결', 'source_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th>응시</th>
                        <th<?php echo sr_admin_sort_aria('reward_enabled', $quizSort); ?>><?php echo sr_admin_sort_header_html('보상', 'reward_enabled', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $quizSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quizzes === []) { ?>
                        <tr>
                            <td colspan="9" class="admin-empty-state">조건에 맞는 퀴즈가 없습니다.</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($quizzes as $quiz) { ?>
                        <?php
                        $quizStatus = (string) ($quiz['status'] ?? '');
                        $memberGroupCount = count(sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? ''));
                        $rewardEnabled = (int) ($quiz['reward_enabled'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) $quiz['quiz_key']); ?></code></td>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e((string) $quiz['title']); ?></strong><br>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_quiz_mode_label((string) ($quiz['quiz_mode'] ?? ''))); ?> · <?php echo sr_e(sr_quiz_scoring_model_label((string) ($quiz['scoring_model'] ?? ''))); ?></span>
                            </td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_quiz_admin_status_class($quizStatus)); ?>"><?php echo sr_e(sr_quiz_status_label($quizStatus)); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($quiz['question_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($quiz['source_count'] ?? 0))); ?></td>
                            <td class="admin-table-break">
                                <?php echo sr_e(sr_quiz_attempt_limit_policy_label((string) ($quiz['attempt_limit_policy'] ?? 'unlimited'))); ?><br>
                                <span class="admin-summary-meta">통과 <?php echo sr_e((string) ($quiz['pass_score'] ?? '-')); ?> · 회원 조건 <?php echo sr_e(number_format($memberGroupCount)); ?>개</span>
                            </td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo $rewardEnabled ? 'is-normal' : 'is-blocked'; ?>"><?php echo $rewardEnabled ? '사용' : '미사용'; ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) $quiz['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=edit&id=' . (string) (int) $quiz['id'])); ?>" aria-label="퀴즈 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="admin-inline-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="delete">
                                        <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) $quiz['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="퀴즈 삭제" title="삭제" data-confirm="<?php echo sr_e('퀴즈를 삭제할까요? 기존 기록은 보관됩니다.'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php echo sr_admin_pagination_html($quizPagination, '퀴즈 목록 페이지'); ?>
    <?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
    <?php
    return;
}

$questions = (array) ($values['questions'] ?? []);
for ($extraIndex = 0; $extraIndex < 2; $extraIndex++) {
    $nextNumber = count($questions) + 1;
    $questions[] = [
        'question_key' => '',
        'prompt' => '',
        'question_type' => 'single_choice',
        'score_value' => 1,
        'choices' => [
                    ['choice_key' => '', 'label' => '', 'is_correct' => 1, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
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
            <h2><?php echo sr_e($mode === 'edit' ? '퀴즈 수정' : '퀴즈 생성'); ?></h2>
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
                <label class="form-label" for="quiz_mode">모드 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_mode" name="quiz_mode" class="form-select" required>
                        <?php foreach (sr_quiz_modes() as $quizMode) { ?>
                            <option value="<?php echo sr_e($quizMode); ?>"<?php echo (string) ($values['quiz_mode'] ?? 'scored') === $quizMode ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_mode_label($quizMode)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_scoring_model">채점 모델 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_scoring_model" name="scoring_model" class="form-select" required>
                        <?php foreach (sr_quiz_scoring_models() as $scoringModel) { ?>
                            <option value="<?php echo sr_e($scoringModel); ?>"<?php echo (string) ($values['scoring_model'] ?? 'correct_answer') === $scoringModel ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_scoring_model_label($scoringModel)); ?></option>
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
            <div class="admin-form-row">
                <label class="form-label" for="quiz_starts_at">공개 시작일시</label>
                <div class="admin-form-field">
                    <input id="quiz_starts_at" type="datetime-local" name="starts_at" value="<?php echo sr_e(sr_quiz_datetime_local_value($values['starts_at'] ?? '')); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_ends_at">공개 종료일시</label>
                <div class="admin-form-field">
                    <input id="quiz_ends_at" type="datetime-local" name="ends_at" value="<?php echo sr_e(sr_quiz_datetime_local_value($values['ends_at'] ?? '')); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_attempt_limit_policy">응시 제한 <span class="sr-required-label">(필수)</span></label>
                <div class="admin-form-field">
                    <select id="quiz_attempt_limit_policy" name="attempt_limit_policy" class="form-select" required>
                        <?php foreach (sr_quiz_attempt_limit_policies() as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo (string) ($values['attempt_limit_policy'] ?? 'unlimited') === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_attempt_limit_policy_label($policy)); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_attempt_limit_period_seconds">제한 기간(초) <span class="sr-required-label" data-quiz-attempt-period-required hidden>(필수)</span></label>
                <div class="admin-form-field">
                    <input id="quiz_attempt_limit_period_seconds" type="number" name="attempt_limit_period_seconds" value="<?php echo sr_e((string) ($values['attempt_limit_period_seconds'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-attempt-period>
                    <p class="admin-form-help">응시 제한이 기간당 1회일 때만 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_member_group_keys">응시 가능 회원 그룹</label>
                <div class="admin-form-field">
                    <?php echo sr_admin_member_group_key_select_html('quiz_member_group_keys', 'member_group_keys', sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []), $memberGroups); ?>
                    <p class="admin-form-help">선택하지 않으면 로그인 회원 전체가 응시할 수 있습니다.</p>
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
                    $choices[] = ['choice_key' => chr(97 + $choiceExtra), 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0];
                }
                ?>
                <section class="admin-quiz-question-block">
                    <div class="admin-section-header">
                        <h3>문제 <?php echo sr_e((string) ($questionIndex + 1)); ?></h3>
                    </div>
                    <div class="admin-section-body">
                        <input type="hidden" name="question_uid[]" value="<?php echo sr_e($questionUid); ?>">
                        <div class="admin-form-row">
	                            <label class="form-label" for="quiz_question_type_<?php echo sr_e((string) $questionIndex); ?>">문제 유형 <span class="sr-required-label">(필수)</span></label>
	                            <div class="admin-form-field">
	                                <select id="quiz_question_type_<?php echo sr_e((string) $questionIndex); ?>" name="question_type[]" class="form-select" required>
                                    <?php foreach (sr_quiz_question_types() as $questionType) { ?>
                                        <option value="<?php echo sr_e($questionType); ?>"<?php echo (string) ($question['question_type'] ?? 'single_choice') === $questionType ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_question_type_label($questionType)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="admin-form-row">
	                            <label class="form-label" for="quiz_question_key_<?php echo sr_e((string) $questionIndex); ?>">문제 Key <span class="sr-required-label">(필수)</span></label>
	                            <div class="admin-form-field">
	                                <input id="quiz_question_key_<?php echo sr_e((string) $questionIndex); ?>" type="text" name="question_key[]" value="<?php echo sr_e((string) ($question['question_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input>
                            </div>
                        </div>
                        <div class="admin-form-row">
	                            <label class="form-label" for="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>">문제 <span class="sr-required-label">(필수)</span></label>
	                            <div class="admin-form-field">
	                                <textarea id="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>" name="question_prompt[]" class="form-textarea" rows="3" required><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></textarea>
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
                                    <th>카테고리 Key</th>
                                    <th>가중치</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($choices as $choiceIndex => $choice) { ?>
                                    <tr>
                                        <td><input type="checkbox" name="correct_choice[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) $choiceIndex); ?>"<?php echo (int) ($choice['is_correct'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                        <td><input type="text" name="choice_key[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['choice_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input></td>
                                        <td><input type="text" name="choice_label[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['label'] ?? '')); ?>" class="form-input form-control-full" maxlength="255" required></td>
                                        <td><input type="text" name="choice_category_key[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['category_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input></td>
                                        <td><input type="number" name="choice_category_weight[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) (int) ($choice['category_weight'] ?? 0)); ?>" class="form-input" step="1"></td>
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
                <label class="form-label" for="quiz_reward_provider">보상 공급자</label>
                <div class="admin-form-field">
                    <select id="quiz_reward_provider" name="reward_provider" class="form-select">
                        <?php foreach (sr_quiz_reward_providers() as $provider) { ?>
                            <option value="<?php echo sr_e($provider); ?>"<?php echo (string) ($values['reward_provider'] ?? 'ledger_asset') === $provider ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_provider_label($provider)); ?></option>
                        <?php } ?>
                    </select>
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
                <label class="form-label" for="quiz_reward_coupon_definition_id">보상 쿠폰 정의 ID</label>
                <div class="admin-form-field">
                    <input id="quiz_reward_coupon_definition_id" type="number" name="reward_coupon_definition_id" value="<?php echo sr_e((string) ($values['reward_coupon_definition_id'] ?? '')); ?>" class="form-input" min="1" step="1">
                    <p class="admin-form-help">보상 공급자가 쿠폰 발급일 때 사용합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_reward_amount">보상 금액</label>
                <div class="admin-form-field">
                    <input id="quiz_reward_amount" type="number" name="reward_amount" value="<?php echo sr_e((string) ($values['reward_amount'] ?? '')); ?>" class="form-input" min="1" step="1">
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_reward_dedupe_scope">보상 중복 제한</label>
                <div class="admin-form-field">
                    <select id="quiz_reward_dedupe_scope" name="reward_dedupe_scope" class="form-select">
                        <?php foreach (sr_quiz_reward_dedupe_scopes() as $scope) { ?>
                            <option value="<?php echo sr_e($scope); ?>"<?php echo (string) ($values['reward_dedupe_scope'] ?? 'per_quiz') === $scope ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_dedupe_scope_label($scope)); ?></option>
                        <?php } ?>
                    </select>
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
                    <p class="admin-form-help">콘텐츠 ID를 줄바꿈 또는 쉼표로 입력해 연결합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_community_source_ids">커뮤니티 게시글 ID</label>
                <div class="admin-form-field">
                    <textarea id="quiz_community_source_ids" name="community_source_ids" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['community_source_ids'] ?? '')); ?></textarea>
                    <p class="admin-form-help">커뮤니티 게시글 ID를 줄바꿈 또는 쉼표로 입력해 연결합니다.</p>
                </div>
            </div>
            <div class="admin-form-row">
                <label class="form-label" for="quiz_result_rules">결과 규칙</label>
                <div class="admin-form-field">
                    <textarea id="quiz_result_rules" name="result_rules" class="form-textarea" rows="5"><?php echo sr_e((string) ($values['result_rules'] ?? '')); ?></textarea>
                    <p class="admin-form-help">한 줄에 key|제목|최소점수|최대점수|카테고리key|기준값|요약 순서로 입력합니다.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">목록</a>
        <button type="submit" class="btn btn-solid-primary">저장</button>
    </div>
</form>
<script>
(function () {
    var policy = document.getElementById('quiz_attempt_limit_policy');
    var period = document.querySelector('[data-quiz-attempt-period]');
    var periodLabel = document.querySelector('[data-quiz-attempt-period-required]');
    if (!policy || !period) {
        return;
    }

    function syncAttemptPeriodRequired() {
        var required = policy.value === 'per_period';
        period.required = required;
        if (periodLabel) {
            periodLabel.hidden = !required;
        }
    }

    policy.addEventListener('change', syncAttemptPeriodRequired);
    syncAttemptPeriodRequired();
})();
</script>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
