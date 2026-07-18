<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
if (sr_module_enabled($pdo, 'reaction') && is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'view');

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result();
$errors = (array) ($flashResult['errors'] ?? []);
$notice = (string) ($flashResult['notice'] ?? '');
$assetOptions = sr_quiz_asset_options($pdo);
$memberGroups = sr_quiz_member_groups_for_admin($pdo);
$reactionPresetOptions = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$commentEditorOptions = sr_editor_options($pdo, true);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    $intent = sr_post_string('intent', 40);
    $adminFormAction = sr_post_string('admin_form_action', 30);
    if ($intent === 'save' && in_array($adminFormAction, ['save_draft', 'discard_draft'], true)) {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'edit');
        $adminFormDraftQuizId = (int) sr_post_string('quiz_id', 20);
        $adminFormDraftKey = 'quiz.item';
        $adminFormDraftContext = $adminFormDraftQuizId > 0 ? 'edit:' . (string) $adminFormDraftQuizId : 'create';
        $adminFormDraftQuiz = $adminFormDraftQuizId > 0 ? sr_quiz_admin_quiz_by_id($pdo, $adminFormDraftQuizId) : null;
        if ($adminFormDraftQuizId > 0 && !is_array($adminFormDraftQuiz)) {
            sr_admin_redirect_with_result(sr_admin_action_result(['임시저장할 퀴즈를 찾을 수 없습니다.'], ''), '/admin/quiz');
        }
        $adminFormDraftBaseValues = is_array($adminFormDraftQuiz)
            ? sr_quiz_admin_values_from_row($adminFormDraftQuiz)
            : sr_quiz_default_admin_values(sr_quiz_settings($pdo));
        $adminFormDraftFingerprint = sr_admin_form_draft_fingerprint($adminFormDraftBaseValues);
        if ($adminFormAction === 'save_draft') {
            try {
                sr_admin_form_draft_save($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext, $_POST, $adminFormDraftFingerprint);
                sr_admin_flash_result(sr_admin_action_result([], '퀴즈 입력 내용을 임시저장했습니다.'));
            } catch (Throwable $exception) {
                sr_admin_flash_result(sr_admin_action_result([$exception->getMessage()], ''));
            }
        } else {
            sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
            sr_admin_flash_result(sr_admin_action_result([], '퀴즈 임시저장본을 삭제했습니다.'));
        }
        sr_redirect($adminFormDraftQuizId > 0 ? '/admin/quiz?mode=edit&id=' . (string) $adminFormDraftQuizId : '/admin/quiz?mode=new');
    }
    if ($intent === 'save') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'edit');
        $values = sr_quiz_admin_values_from_post();
        $beforeCoverImageUrl = '';
        if ((int) ($values['id'] ?? 0) > 0) {
            $existingQuizForCover = sr_quiz_by_id($pdo, (int) $values['id']);
            $beforeCoverImageUrl = is_array($existingQuizForCover) ? sr_quiz_clean_cover_image_url((string) ($existingQuizForCover['cover_image_url'] ?? '')) : '';
        }
        if (sr_post_string('cover_image_delete', 1) === '1') {
            $values['cover_image_url'] = '';
        }
        $uploadedCoverImage = null;
        $uploadErrors = [];
        $coverImageUploadFile = $_FILES['cover_image_upload'] ?? null;
        if (sr_quiz_cover_image_upload_was_provided($coverImageUploadFile)) {
            if (!is_array($coverImageUploadFile)) {
                $uploadErrors[] = '커버 이미지 업로드 값을 확인하세요.';
            } else {
                try {
                    $uploadedCoverImage = sr_quiz_upload_cover_image($coverImageUploadFile);
                    if (is_array($uploadedCoverImage)) {
                        $values['cover_image_url'] = (string) $uploadedCoverImage['url'];
                    }
                } catch (Throwable $exception) {
                    $uploadErrors[] = $exception instanceof RuntimeException ? (string) $exception->getMessage() : '커버 이미지 업로드 중 오류가 발생했습니다.';
                }
            }
        }
        $postErrors = array_merge($uploadErrors, sr_quiz_admin_validation_errors($pdo, $values, $assetOptions));
        if ($postErrors !== []) {
            if (is_array($uploadedCoverImage)) {
                sr_storage_delete((string) ($uploadedCoverImage['driver'] ?? 'local'), (string) ($uploadedCoverImage['key'] ?? ''));
            }
            $_SESSION['sr_quiz_form_errors'] = $postErrors;
            $_SESSION['sr_quiz_form_values'] = $values;
            $redirect = (int) ($values['id'] ?? 0) > 0
                ? '/admin/quiz?mode=edit&id=' . (string) (int) $values['id']
                : '/admin/quiz?mode=new';
            sr_redirect($redirect);
        }

        try {
            $savedId = sr_quiz_save_admin_quiz($pdo, $values, (int) ($account['id'] ?? 0));
            $afterCoverImageUrl = sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? ''));
            if ($beforeCoverImageUrl !== '' && $beforeCoverImageUrl !== $afterCoverImageUrl) {
                sr_quiz_delete_cover_image_storage($pdo, $beforeCoverImageUrl, $savedId);
            }
        } catch (RuntimeException $exception) {
            if (is_array($uploadedCoverImage)) {
                sr_storage_delete((string) ($uploadedCoverImage['driver'] ?? 'local'), (string) ($uploadedCoverImage['key'] ?? ''));
            }
            if ((string) $exception->getMessage() !== 'Quiz to update was not found.') {
                throw $exception;
            }
            sr_admin_redirect_with_result(sr_admin_action_result(['저장할 퀴즈를 찾을 수 없습니다.'], ''), '/admin/quiz');
        } catch (Throwable $exception) {
            if (is_array($uploadedCoverImage)) {
                sr_storage_delete((string) ($uploadedCoverImage['driver'] ?? 'local'), (string) ($uploadedCoverImage['key'] ?? ''));
            }
            throw $exception;
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
                'cover_image_changed' => $beforeCoverImageUrl !== sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')),
                'cover_image_uploaded' => sr_quiz_cover_image_upload_was_provided($coverImageUploadFile),
                'cover_image_deleted' => $beforeCoverImageUrl !== '' && sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')) === '',
                'content_source_ids' => (string) ($values['content_source_ids'] ?? ''),
                'question_count' => count((array) ($values['questions'] ?? [])),
            ],
        ]);
        $adminFormDraftKey = 'quiz.item';
        $adminFormDraftContext = (int) ($values['id'] ?? 0) > 0 ? 'edit:' . (string) (int) $values['id'] : 'create';
        sr_admin_form_draft_delete($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext);
        sr_admin_redirect_with_result(sr_admin_action_result([], '퀴즈를 저장했습니다.'), '/admin/quiz');
    } elseif ($intent === 'copy') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'edit');
        $copyOptions = sr_quiz_copy_options_from_post();
        $postErrors = sr_quiz_copy_validation_errors($pdo, $copyOptions);
        if ($postErrors !== []) {
            sr_admin_redirect_with_result(sr_admin_action_result($postErrors, ''), '/admin/quiz');
        }

        try {
            $copyResult = sr_quiz_copy_admin_quiz($pdo, (int) $copyOptions['source_quiz_id'], $copyOptions, (int) ($account['id'] ?? 0));
        } catch (RuntimeException $exception) {
            sr_admin_redirect_with_result(sr_admin_action_result(['퀴즈를 복사할 수 없습니다. 원본이나 새 key를 다시 확인하세요.'], ''), '/admin/quiz');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_copy_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['퀴즈 복사 중 오류가 발생했습니다.'], ''), '/admin/quiz');
        }
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'quiz.copied',
            'target_type' => 'quiz',
            'target_id' => (string) (int) ($copyResult['quiz_id'] ?? 0),
            'result' => 'success',
            'message' => 'Quiz copied.',
            'metadata' => [
                'source_quiz_id' => (int) ($copyOptions['source_quiz_id'] ?? 0),
                'new_quiz_id' => (int) ($copyResult['quiz_id'] ?? 0),
                'new_quiz_key' => (string) ($copyResult['quiz_key'] ?? ''),
                'copy_dates' => !empty($copyOptions['copy_dates']),
                'copy_member_groups' => !empty($copyOptions['copy_member_groups']),
                'copy_reward_policy' => !empty($copyOptions['copy_reward_policy']),
                'copy_sources' => !empty($copyOptions['copy_sources']),
                'copy_status' => !empty($copyOptions['copy_status']),
                'warnings' => (array) ($copyResult['warnings'] ?? []),
                'skipped_sources' => (array) ($copyResult['skipped_sources'] ?? []),
                'skipped_reward_policy' => (string) ($copyResult['skipped_reward_policy'] ?? ''),
                'skipped_member_group_keys' => (array) ($copyResult['skipped_member_group_keys'] ?? []),
            ],
        ]);
        $noticeMessage = '퀴즈를 복사했습니다.';
        if ((array) ($copyResult['warnings'] ?? []) !== []) {
            $noticeMessage .= ' ' . implode(' ', array_map('strval', (array) $copyResult['warnings']));
        }
        sr_admin_redirect_with_result(
            sr_admin_action_result([], $noticeMessage),
            '/admin/quiz?mode=edit&id=' . (string) (int) ($copyResult['quiz_id'] ?? 0)
        );
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
    } elseif ($intent === 'permanent_delete') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/quiz', 'delete');
        $quizId = (int) sr_post_string('quiz_id', 20);
        $confirmationPhrase = sr_post_string('confirmation_phrase', 80);
        try {
            $deleteResult = sr_quiz_permanently_delete($pdo, $quizId, $confirmationPhrase, (int) ($account['id'] ?? 0));
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'quiz_permanent_delete_failed');
            sr_admin_redirect_with_result(sr_admin_action_result(['퀴즈 영구 삭제 중 오류가 발생했습니다.'], ''), '/admin/quiz?deleted=1');
        }
        sr_admin_redirect_with_result(
            !empty($deleteResult['ok'])
                ? sr_admin_action_result([], (string) ($deleteResult['message'] ?? '퀴즈를 영구 삭제했습니다.'))
                : sr_admin_action_result([(string) ($deleteResult['message'] ?? '퀴즈를 영구 삭제할 수 없습니다.')], ''),
            '/admin/quiz?deleted=1'
        );
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

$adminFormDraftKey = 'quiz.item';
$adminFormDraftContext = is_array($editQuiz) ? 'edit:' . (string) (int) $editQuiz['id'] : 'create';
$adminFormDraftBaseValues = is_array($editQuiz) ? sr_quiz_admin_values_from_row($editQuiz) : sr_quiz_default_admin_values(sr_quiz_settings($pdo));
$adminFormDraftFingerprint = sr_admin_form_draft_fingerprint($adminFormDraftBaseValues);
$adminFormDraft = in_array($mode, ['new', 'edit'], true)
    ? sr_admin_form_draft_with_state(
        sr_admin_form_draft_get($pdo, (int) ($account['id'] ?? 0), $adminFormDraftKey, $adminFormDraftContext),
        $adminFormDraftFingerprint
    )
    : null;
$adminFormDraftForDisplay = is_array($sessionValues) && $sessionValues !== [] ? null : $adminFormDraft;
$values = is_array($sessionValues) && $sessionValues !== []
    ? $sessionValues
    : (is_array($adminFormDraftForDisplay) && is_array($adminFormDraftForDisplay['payload'] ?? null)
        ? sr_admin_form_draft_with_post($adminFormDraftForDisplay['payload'], static fn (): array => sr_quiz_admin_values_from_post())
        : $adminFormDraftBaseValues);
$quizGroups = sr_quiz_groups($pdo);
$quizScopeRadioHtml = static function (string $settingKey, string $selected): string {
    $selected = sr_quiz_setting_scope($selected);
    $html = '<div class="admin-setting-source-options">';
    foreach (['item' => '단독', 'group' => '그룹', 'all' => '전체'] as $scope => $label) {
        $id = 'quiz_setting_source_' . $settingKey . '_' . $scope;
        $toast = $scope === 'group' ? '저장하면 같은 퀴즈 그룹에 적용됩니다.' : ($scope === 'all' ? '저장하면 삭제되지 않은 전체 퀴즈에 적용됩니다.' : '');
        $html .= '<label class="form-check form-label" for="' . sr_e($id) . '"><input id="' . sr_e($id) . '" type="radio" name="source_' . sr_e($settingKey) . '" value="' . sr_e($scope) . '" class="form-radio"' . ($toast !== '' ? ' data-admin-scope-toast="' . sr_e($toast) . '"' : '') . ($selected === $scope ? ' checked' : '') . '>' . sr_e($label) . '<span class="sr-only"> 적용</span></label>';
    }
    return $html . '</div>';
};
$couponRewardDefinitions = sr_quiz_reward_coupon_definitions($pdo, (int) ($values['reward_coupon_definition_id'] ?? 0));

$adminPageTitle = $mode === 'list' ? '퀴즈 관리' : ($mode === 'edit' ? '퀴즈 수정' : '퀴즈 생성');
$adminPageTitleUrl = sr_admin_page_title_reset_url($mode === 'list', '/admin/quiz');
include SR_ROOT . '/modules/admin/views/layout-header.php';

if ($mode === 'list') {
    $quizFilters = sr_quiz_admin_quiz_filters_from_request();
    $quizDeletedView = !empty($quizFilters['deleted']);
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
        <?php if ($quizDeletedView) { ?>
            <input type="hidden" name="deleted" value="1">
        <?php } ?>
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
                    <?php echo sr_admin_filter_radio_toggle_group_html('quiz_reward_filter', 'reward_enabled', ['enabled' => '사용', 'disabled' => '사용안함'], [(string) ($quizFilters['reward_enabled'] ?? '')], '전체'); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $quizDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="quiz_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </form>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo $quizDeletedView ? '삭제한 퀴즈' : '퀴즈 목록'; ?></h2>
            <div class="card-actions">
                <a class="btn btn-sm <?php echo $quizDeletedView ? 'btn-outline-secondary' : 'btn-solid-light'; ?>" href="<?php echo sr_e(sr_url('/admin/quiz')); ?>">사용 중</a>
                <a class="btn btn-sm <?php echo $quizDeletedView ? 'btn-solid-light' : 'btn-outline-secondary'; ?>" href="<?php echo sr_e(sr_url('/admin/quiz?deleted=1')); ?>">삭제함</a>
                <?php if (!$quizDeletedView) { ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=new')); ?>">새 퀴즈</a>
                <?php } ?>
            </div>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($quizSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($quizSortOptions, $quizDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="퀴즈 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <?php echo sr_admin_pagination_summary_html($quizPagination); ?>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-quiz-table">
                <thead>
                    <tr>
                        <th<?php echo sr_admin_sort_aria('quiz_key', $quizSort); ?>><?php echo sr_admin_sort_header_html('Key', 'quiz_key', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('title', $quizSort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th>그룹</th>
                        <th<?php echo sr_admin_sort_aria('status', $quizSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('question_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('문제', 'question_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('source_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('연결', 'source_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('attempt_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('응시/통과', 'attempt_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('view_count', $quizSort); ?>><?php echo sr_admin_sort_header_html('조회수', 'view_count', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('reward_enabled', $quizSort); ?>><?php echo sr_admin_sort_header_html('보상', 'reward_enabled', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $quizSort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $quizSort, $quizSortOptions, $quizDefaultSort); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quizzes === []) { ?>
                        <tr>
                            <td colspan="11" class="admin-empty-state"><?php echo $quizDeletedView ? '삭제한 퀴즈가 없습니다.' : '조건에 맞는 퀴즈가 없습니다.'; ?></td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($quizzes as $quiz) { ?>
                        <?php
                        $quizStatus = (string) ($quiz['status'] ?? '');
                        $quizIsDeleted = !empty($quiz['deleted_at']);
                        $memberGroupCount = count(sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? ''));
                        $rewardEnabled = (int) ($quiz['reward_enabled'] ?? 0) === 1;
                        $copyModalId = 'quiz-copy-modal-' . (string) (int) $quiz['id'];
                        $publicQuizUrl = sr_url('/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')) . '?preview=admin');
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) $quiz['quiz_key']); ?></code></td>
                            <td class="admin-table-break">
                                <?php if ($quizIsDeleted) { ?>
                                    <strong><?php echo sr_e((string) $quiz['title']); ?></strong><br>
                                <?php } else { ?>
                                    <strong><a href="<?php echo sr_e($publicQuizUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e((string) $quiz['title']); ?></a></strong><br>
                                <?php } ?>
                                <span class="admin-summary-meta"><?php echo sr_e(sr_quiz_mode_label((string) ($quiz['quiz_mode'] ?? ''))); ?> · <?php echo sr_e(sr_quiz_scoring_model_label((string) ($quiz['scoring_model'] ?? ''))); ?></span>
                                <?php if ($quizIsDeleted) { ?>
                                    <br>
                                    <span class="admin-summary-meta">
                                        내부 ID #<?php echo sr_e((string) (int) $quiz['id']); ?>
                                        · 삭제 판정 deleted_at
                                        · 삭제 시각 <?php echo sr_quiz_time_html((string) ($quiz['deleted_at'] ?? '')); ?>
                                        · redaction 완료
                                        · 보존 로그 <?php echo sr_e(number_format((int) ($quiz['attempt_count'] ?? 0) + (int) ($quiz['reward_grant_count'] ?? 0))); ?>건
                                        · cleanup 대기 <?php echo sr_e(number_format((int) ($quiz['cleanup_pending_count'] ?? 0))); ?>건
                                        · 영구 삭제 가능
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="admin-table-break"><?php echo sr_e((string) ($quiz['quiz_group_title'] ?? '')); ?></td>
                            <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e($quizIsDeleted ? 'is-danger' : sr_quiz_admin_status_class($quizStatus)); ?>"><?php echo sr_e($quizIsDeleted ? '삭제됨' : sr_quiz_status_label($quizStatus)); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($quiz['question_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($quiz['source_count'] ?? 0))); ?></td>
                            <td class="admin-table-break">
                                응시 <?php echo sr_e(number_format((int) ($quiz['attempt_count'] ?? 0))); ?>건 · 통과 <?php echo sr_e(number_format((int) ($quiz['passed_count'] ?? 0))); ?>건<br>
                                <span class="admin-summary-meta">제한 <?php echo sr_e(sr_quiz_attempt_limit_policy_label((string) ($quiz['attempt_limit_policy'] ?? 'unlimited'))); ?> · 통과 점수 <?php echo sr_e((string) ($quiz['pass_score'] ?? '-')); ?> · 회원 조건 <?php echo sr_e(number_format($memberGroupCount)); ?>개</span>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($quiz['view_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap"><span class="badge-status <?php echo $rewardEnabled ? 'is-success' : 'is-warning'; ?>"><?php echo $rewardEnabled ? '사용' : '사용안함'; ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_quiz_time_html((string) $quiz['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php if (!$quizIsDeleted) { ?>
                                        <a class="btn btn-sm btn-icon btn-solid-light" href="<?php echo sr_e($publicQuizUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="사용자 화면 미리보기" title="사용자 화면 미리보기"><?php echo sr_material_icon_html('visibility'); ?></a>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="퀴즈 복사" title="복사" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($copyModalId); ?>" data-overlay="#<?php echo sr_e($copyModalId); ?>"><?php echo sr_material_icon_html('content_copy'); ?></button>
                                        <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/quiz?mode=edit&id=' . (string) (int) $quiz['id'])); ?>" aria-label="퀴즈 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="admin-inline-form">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete">
                                            <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) $quiz['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="퀴즈 삭제" title="삭제" data-confirm="<?php echo sr_e('퀴즈를 삭제할까요? 기존 기록은 보관됩니다.'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                        </form>
                                    <?php } else { ?>
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-danger" aria-label="퀴즈 영구 삭제" title="영구 삭제" aria-haspopup="dialog" aria-expanded="false" aria-controls="quiz-permanent-delete-modal-<?php echo sr_e((string) (int) $quiz['id']); ?>" data-overlay="#quiz-permanent-delete-modal-<?php echo sr_e((string) (int) $quiz['id']); ?>"><?php echo sr_material_icon_html('delete_forever'); ?></button>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('visibility'); ?> 사용자 화면 미리보기</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('content_copy'); ?> 복사</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 삭제</span>
        </div>
        <?php echo sr_admin_status_description_list_html('quiz_status', array_combine(sr_quiz_statuses(), array_map('sr_quiz_status_label', sr_quiz_statuses())) ?: [], [], '퀴즈 상태 설명'); ?>
        <?php echo sr_admin_status_description_list_html('quiz_reward_enabled', ['enabled' => '사용', 'disabled' => '사용안함'], [], '보상 사용 설명'); ?>
    </section>
    <?php foreach ($quizzes as $quiz) { ?>
        <?php if (!empty($quiz['deleted_at'])) { ?>
            <?php $permanentDeleteModalId = 'quiz-permanent-delete-modal-' . (string) (int) $quiz['id']; ?>
            <div id="<?php echo sr_e($permanentDeleteModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($permanentDeleteModalId); ?>-label" aria-hidden="true" inert>
                <div class="modal-dialog">
                    <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="modal-content admin-form ui-form-theme" data-sr-validate-form data-confirm-phrase-form>
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="permanent_delete">
                        <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) $quiz['id']); ?>">
                        <div class="modal-header">
                            <h3 id="<?php echo sr_e($permanentDeleteModalId); ?>-label" class="modal-title">퀴즈 영구 삭제</h3>
                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($permanentDeleteModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <p class="form-help">삭제된 퀴즈 본문, 문제, 선택지, 결과 규칙, 연결 정보를 물리 삭제합니다. 응시와 보상 이력은 보존됩니다.</p>
                            <div class="form-row">
                                <label class="form-label" for="<?php echo sr_e($permanentDeleteModalId); ?>-phrase">확인 문구 <span class="sr-required-label">(필수)</span></label>
                                <div class="form-field">
                                    <input id="<?php echo sr_e($permanentDeleteModalId); ?>-phrase" type="text" name="confirmation_phrase" class="form-input form-control-full" required data-confirm-phrase="<?php echo sr_e((string) ($quiz['quiz_key'] ?? '')); ?>" data-confirm-phrase-alt="<?php echo sr_e((string) (int) $quiz['id']); ?>" data-overlay-focus>
                                    <p class="form-help"><code><?php echo sr_e((string) ($quiz['quiz_key'] ?? '')); ?></code> 또는 <code><?php echo sr_e((string) (int) $quiz['id']); ?></code> 를 정확히 입력하세요.</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($permanentDeleteModalId); ?>">취소</button>
                            <button type="submit" class="btn btn-outline-danger modal-action">영구 삭제</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php continue; ?>
        <?php } ?>
        <?php
        $copyModalId = 'quiz-copy-modal-' . (string) (int) $quiz['id'];
        $sourceKey = (string) ($quiz['quiz_key'] ?? '');
        $copyKey = sr_quiz_clean_key($sourceKey . '_copy');
        if ($copyKey === '' || sr_quiz_key_exists($pdo, $copyKey, 0)) {
            $copyKey = sr_quiz_clean_key($sourceKey . '_copy_' . (string) (int) $quiz['id']);
        }
        $copyTitle = (string) ($quiz['title'] ?? '') . ' 복사본';
        ?>
        <div id="<?php echo sr_e($copyModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($copyModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="modal-content ui-form-theme">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="copy">
                    <input type="hidden" name="source_quiz_id" value="<?php echo sr_e((string) (int) $quiz['id']); ?>">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($copyModalId); ?>-label" class="modal-title">퀴즈 복사</h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($copyModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <div class="admin-summary-list">
                            <p class="form-help">
                                문제 <?php echo sr_e(number_format((int) ($quiz['question_count'] ?? 0))); ?>개,
                                결과 규칙 <?php echo sr_e(number_format((int) ($quiz['result_rule_count'] ?? 0))); ?>개,
                                연결 <?php echo sr_e(number_format((int) ($quiz['source_count'] ?? 0))); ?>개,
                                보상 정책 <?php echo (int) ($quiz['reward_policy_count'] ?? 0) > 0 ? '있음' : '없음'; ?>
                            </p>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="<?php echo sr_e($copyModalId); ?>-key">새 퀴즈 Key <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <input id="<?php echo sr_e($copyModalId); ?>-key" type="text" name="copy_quiz_key" value="<?php echo sr_e($copyKey); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input data-overlay-focus>
                                <p class="form-help">삭제된 퀴즈의 key까지 포함해 중복될 수 없습니다.</p>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="<?php echo sr_e($copyModalId); ?>-title">새 제목 <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <input id="<?php echo sr_e($copyModalId); ?>-title" type="text" name="copy_title" value="<?php echo sr_e($copyTitle); ?>" class="form-input form-control-full" maxlength="190" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <span class="form-label">복사 범위</span>
                            <div class="form-field">
                                <label class="form-check form-label">
                                    <input type="checkbox" class="form-checkbox" checked disabled>
                                    <?php echo sr_admin_choice_label_html('기본 정보, 채점, 문제, 결과 규칙'); ?>
                                </label>
                                <label class="form-check form-label">
                                    <input type="checkbox" name="copy_dates" value="1" class="form-checkbox">
                                    <?php echo sr_admin_choice_label_html('공개 기간 복사'); ?>
                                </label>
                                <label class="form-check form-label">
                                    <input type="checkbox" name="copy_member_groups" value="1" class="form-checkbox" checked>
                                    <?php echo sr_admin_choice_label_html('응시 가능 회원 그룹 복사'); ?>
                                </label>
                                <label class="form-check form-label">
                                    <input type="checkbox" name="copy_reward_policy" value="1" class="form-checkbox">
                                    <?php echo sr_admin_choice_label_html('보상 정책 복사'); ?>
                                </label>
                                <label class="form-check form-label">
                                    <input type="checkbox" name="copy_sources" value="1" class="form-checkbox">
                                    <?php echo sr_admin_choice_label_html('콘텐츠/커뮤니티 연결 복사'); ?>
                                </label>
                                <label class="form-check form-label">
                                    <input type="checkbox" name="copy_status" value="1" class="form-checkbox">
                                    <?php echo sr_admin_choice_label_html('원본 공개 상태 복사'); ?>
                                </label>
                                <p class="form-help">기본 생성 상태는 초안입니다. 보상 정책과 연결 대상은 선택한 경우에도 서버에서 현재 사용 가능 여부를 다시 확인합니다.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($copyModalId); ?>">취소</button>
                        <button type="submit" class="btn btn-solid-primary">복사</button>
                    </div>
                </form>
            </div>
        </div>
    <?php } ?>
    <?php echo sr_admin_pagination_html($quizPagination, '퀴즈 목록 페이지'); ?>
    <?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
    <?php
    return;
}

$questions = (array) ($values['questions'] ?? []);
if ($mode === 'new' && (!is_array($sessionValues) || $sessionValues === [])) {
    $questions = [];
}
$resultRules = sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? ''));

$quizHelpOpenLabel = '설명 보기';
$quizHelpButtonHtml = static function (string $label, string $modalId) use ($quizHelpOpenLabel): string {
    $modalId = trim($modalId);

    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $quizHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$quizHelpBodyHtml = static function (array $items): string {
    $html = '';
    foreach ($items as $item) {
        $html .= '<p>' . sr_e((string) $item) . '</p>';
    }
    return $html;
};
$quizHelp = [
    'quiz_key' => [
        'id' => 'quiz-help-key-modal',
        'title' => '퀴즈 Key',
        'body_html' => $quizHelpBodyHtml([
            '공개 URL과 내부 연결에 쓰는 고유 식별자입니다.',
            '소문자, 숫자, 밑줄만 사용하고 첫 글자는 소문자로 시작해야 합니다.',
            '삭제된 퀴즈의 key도 다시 사용할 수 없습니다.',
        ]),
    ],
    'status' => [
        'id' => 'quiz-help-status-modal',
        'title' => '상태',
        'body_html' => $quizHelpBodyHtml([
            '공개 상태는 목록, 연결된 콘텐츠/커뮤니티 CTA, 상세 진입 가능 여부에 영향을 줍니다.',
            '공개 기간과 회원 그룹 조건은 상태와 별개로 제출 직전에 서버에서 다시 확인합니다.',
        ]),
    ],
    'mode' => [
        'id' => 'quiz-help-mode-modal',
        'title' => '모드',
        'body_html' => $quizHelpBodyHtml([
            '퀴즈가 정답 점수 중심인지, 결과 프로필 산출 중심인지 결정합니다.',
            '선택한 모드와 채점 모델에 맞게 문제 점수, 카테고리 Key, 결과 규칙을 함께 설정합니다.',
        ]),
    ],
    'scoring_model' => [
        'id' => 'quiz-help-scoring-model-modal',
        'title' => '채점 모델',
        'body_html' => $quizHelpBodyHtml([
            '정답 채점, 총점 결과, 카테고리 진단처럼 제출 답안을 해석하는 기준입니다.',
            '카테고리 진단을 쓰려면 선택지의 카테고리 Key와 가중치를 입력해야 합니다.',
        ]),
    ],
    'result_rules' => [
        'id' => 'quiz-help-result-rules-modal',
        'title' => '결과 규칙',
        'body_html' => $quizHelpBodyHtml([
            '결과 화면에 표시할 결과 프로필을 한 줄에 하나씩 입력합니다.',
            '형식은 key|제목|최소점수|최대점수|카테고리key|기준값|요약 입니다.',
            '점수형 결과는 최소/최대 점수를, 카테고리 진단은 카테고리 Key와 기준값을 기준으로 매칭합니다.',
        ]),
    ],
    'attempt_limit' => [
        'id' => 'quiz-help-attempt-limit-modal',
        'title' => '응시 제한',
        'body_html' => $quizHelpBodyHtml([
            '회원이 같은 퀴즈를 다시 풀 수 있는 기준을 정합니다.',
            '기간당 1회를 선택하면 제한 기간을 초 단위로 반드시 입력해야 합니다.',
            '제한은 화면 표시와 별개로 제출 직전에 서버에서 다시 검증합니다.',
        ]),
    ],
    'member_groups' => [
        'id' => 'quiz-help-member-groups-modal',
        'title' => '응시 가능 회원 그룹',
        'body_html' => $quizHelpBodyHtml([
            '선택한 활성 회원 그룹에 속한 로그인 회원만 응시할 수 있습니다.',
            '그룹을 선택하지 않으면 로그인 회원 전체가 응시할 수 있습니다.',
            '회원 그룹 조건은 퀴즈 진입과 제출 직전에 다시 확인합니다.',
        ]),
    ],
    'comments' => [
        'id' => 'quiz-help-comments-modal',
        'title' => '댓글',
        'body_html' => $quizHelpBodyHtml([
            '공개 퀴즈 화면에서 로그인 회원이 댓글을 작성할 수 있게 합니다.',
            '비밀 댓글은 작성자와 댓글 관리 권한이 있는 관리자만 본문을 볼 수 있습니다.',
            '댓글 본문의 @닉네임 멘션은 회원 알림과 표시 스타일에 연결됩니다.',
        ]),
    ],
    'question_type' => [
        'id' => 'quiz-help-question-type-modal',
        'title' => '문제 유형',
        'body_html' => $quizHelpBodyHtml([
            '단일 선택은 정답을 정확히 1개, 복수 선택은 정답을 1개 이상 지정해야 합니다.',
            '서버 저장 검증에서도 문제 유형별 정답 개수를 다시 확인합니다.',
        ]),
    ],
    'question_key' => [
        'id' => 'quiz-help-question-key-modal',
        'title' => '문제 Key',
        'body_html' => $quizHelpBodyHtml([
            '한 퀴즈 안에서 문제를 구분하는 내부 식별자입니다.',
            '소문자, 숫자, 밑줄만 사용하고 같은 퀴즈 안에서 중복되지 않아야 합니다.',
        ]),
    ],
    'questions' => [
        'id' => 'quiz-help-questions-modal',
        'title' => '문제 관리',
        'body_html' => $quizHelpBodyHtml([
            '문제 목록에서 등록된 문제의 key, 내용, 유형, 점수, 선택지 수를 확인합니다.',
            '문제 추가와 수정은 모달에서 처리하고 저장 시 서버가 문제 내용, key, 유형, 선택지, 정답 조건을 다시 검증합니다.',
            '선택지는 최소 2개 이상 입력해야 하며 단일 선택은 정답 1개, 복수 선택은 정답 1개 이상이 필요합니다.',
        ]),
    ],
    'choices' => [
        'id' => 'quiz-help-choices-modal',
        'title' => '선택지와 카테고리',
        'body_html' => $quizHelpBodyHtml([
            '선택지 Key는 문제 안에서 선택지를 구분하는 내부 식별자입니다.',
            '카테고리 Key와 가중치는 카테고리 진단형 결과 규칙을 계산할 때 사용합니다.',
            '정답 채점만 쓰는 퀴즈라면 카테고리 Key와 가중치는 비워둘 수 있습니다.',
        ]),
    ],
    'display' => [
        'id' => 'quiz-help-display-modal',
        'title' => '공개 화면 표시',
        'body_html' => $quizHelpBodyHtml([
            '스킨은 목록, 상세, 결과 같은 공개 화면 본문의 출력 방식입니다.',
            '선택안함으로 두면 퀴즈 환경설정의 기본 스킨을 사용합니다.',
        ]),
    ],
    'reward' => [
        'id' => 'quiz-help-reward-modal',
        'title' => '보상 정책',
        'body_html' => $quizHelpBodyHtml([
            '보상 사용을 켜면 회원이 퀴즈를 제출하고 통과 조건을 만족했을 때 보상 지급을 시도합니다.',
            '보상은 포인트/금액 지급 또는 쿠폰 발급 중 하나로 처리합니다.',
            '화면 표시와 별개로 제출 처리 시점에 보상 종류, 자산, 쿠폰, 중복 지급 기준을 다시 검증합니다.',
        ]),
    ],
    'reward_provider' => [
        'id' => 'quiz-help-reward-provider-modal',
        'title' => '보상 종류',
        'body_html' => $quizHelpBodyHtml([
            '포인트/금액은 포인트, 적립금, 예치금처럼 회원 원장에 금액을 지급하는 방식입니다.',
            '쿠폰 발급은 쿠폰 모듈의 활성 쿠폰 정의를 회원에게 1장 지급하는 방식입니다.',
            '선택한 보상 종류에 따라 보상 자산, 쿠폰 정의 ID, 보상 금액 중 필요한 값이 달라집니다.',
        ]),
    ],
    'reward_module' => [
        'id' => 'quiz-help-reward-module-modal',
        'title' => '보상 자산',
        'body_html' => $quizHelpBodyHtml([
            '보상 종류가 포인트/금액일 때 지급할 항목을 선택합니다.',
            '목록에는 현재 활성화되어 있고 거래 조회 계약을 제공하는 자산 모듈만 표시됩니다.',
            '쿠폰 발급 보상에는 이 값이 사용되지 않습니다.',
        ]),
    ],
    'reward_coupon_definition' => [
        'id' => 'quiz-help-reward-coupon-definition-modal',
        'title' => '보상 쿠폰',
        'body_html' => $quizHelpBodyHtml([
            '보상 종류가 쿠폰 발급일 때 회원에게 지급할 쿠폰을 선택합니다.',
            '목록에는 쿠폰 모듈에 등록되어 있고 현재 사용 가능한 활성 쿠폰만 표시됩니다.',
            '쿠폰의 사용 기간이 아직 시작되지 않았거나 이미 종료된 경우 보상 후보에 표시하지 않습니다.',
            '저장과 지급 시점에 쿠폰 모듈 활성 여부와 쿠폰 사용 가능 상태를 다시 확인합니다.',
        ]),
    ],
    'reward_amount' => [
        'id' => 'quiz-help-reward-amount-modal',
        'title' => '보상 금액',
        'body_html' => $quizHelpBodyHtml([
            '보상 종류가 포인트/금액일 때 회원에게 지급할 금액입니다.',
            '예를 들어 보상 자산이 포인트이고 금액이 100이면 통과한 회원에게 포인트 100을 지급합니다.',
            '쿠폰 발급 보상에는 금액을 사용하지 않고 쿠폰 1장 발급으로 처리합니다.',
        ]),
    ],
    'reward_dedupe' => [
        'id' => 'quiz-help-reward-dedupe-modal',
        'title' => '중복 지급 기준',
        'body_html' => $quizHelpBodyHtml([
            '같은 회원에게 보상을 다시 지급할 수 있는 범위를 정합니다.',
            '퀴즈당 1회는 같은 퀴즈에서 보상을 한 번만 지급합니다.',
            '출처당 1회는 같은 퀴즈라도 연결 콘텐츠나 게시글 출처가 다르면 각각 지급할 수 있습니다.',
            '응시마다는 제한 조건을 통과한 응시마다 지급할 수 있어 가장 느슨한 기준입니다.',
            '중복 지급 방지는 보상 지급 transaction 안에서 다시 확인합니다.',
        ]),
    ],
    'sources' => [
        'id' => 'quiz-help-sources-modal',
        'title' => '연결 대상',
        'body_html' => $quizHelpBodyHtml([
            '퀴즈를 노출할 콘텐츠 ID 또는 커뮤니티 게시글 ID를 줄바꿈이나 쉼표로 입력합니다.',
            '콘텐츠와 커뮤니티는 퀴즈를 직접 소유하지 않고 시작 링크나 모달 호출 지점만 제공합니다.',
            '연결된 대상에서 시작한 응시는 시작 출처를 함께 기록합니다.',
        ]),
    ],
];
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<?php echo sr_admin_form_draft_status_html($adminFormDraftForDisplay ?? null, 'quiz-item-form', '파일 선택은 임시저장되지 않습니다. 필요한 파일은 최종 저장 전에 다시 선택하세요.'); ?>

<?php
$quizSectionNavItems = [
    'quiz-section-basic' => '기본 정보',
    'quiz-section-result' => '채점/결과',
    'quiz-section-result-rules' => '결과 규칙',
    'quiz-section-access' => '공개/응시',
    'quiz-item-comment-extra-fields-json-section' => '댓글 추가 입력',
    'quiz-section-questions' => '문제 목록',
    'quiz-section-reward' => '보상 정책',
    'quiz-section-links' => '연결 대상',
];
?>
<nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="퀴즈 설정 섹션">
    <?php $quizSectionNavIndex = 0; ?>
    <?php foreach ($quizSectionNavItems as $quizSectionId => $quizSectionLabel) { ?>
        <a href="#<?php echo sr_e((string) $quizSectionId); ?>" class="tab-trigger-underline-justified<?php echo $quizSectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $quizSectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
            <?php echo sr_e((string) $quizSectionLabel); ?>
        </a>
        <?php $quizSectionNavIndex++; ?>
    <?php } ?>
</nav>
<form id="quiz-item-form" method="post" action="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="admin-form ui-form-theme" enctype="multipart/form-data">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save">
    <input type="hidden" name="quiz_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">

    <section id="quiz-section-basic" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">기본 정보</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_key', 'Key', $quizHelp['quiz_key']['id'], $quizHelpOpenLabel, true); ?>
                <div class="form-field">
                    <input id="quiz_key" type="text" name="quiz_key" value="<?php echo sr_e((string) ($values['quiz_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_title">제목 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <input id="quiz_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="190" required>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_description">설명</label>
                <div class="form-field">
                    <textarea id="quiz_description" name="description" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_group_id">퀴즈 그룹</label>
                <div class="form-field">
                    <select id="quiz_group_id" name="quiz_group_id" class="form-select">
                        <option value="0">선택안함</option>
                        <?php foreach ($quizGroups as $quizGroup) { ?>
                            <option value="<?php echo sr_e((string) (int) $quizGroup['id']); ?>"<?php echo (int) ($values['quiz_group_id'] ?? 0) === (int) $quizGroup['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $quizGroup['title']); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">그룹 적용 범위를 사용하려면 그룹을 선택하세요.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_cover_image_url">대표/OG 이미지</label>
                <div class="form-field">
                    <input id="quiz_cover_image_url" type="text" name="cover_image_url" value="<?php echo sr_e(sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? ''))); ?>" class="form-input form-control-full" maxlength="255" placeholder="/storage/... 또는 https://...">
                    <p class="form-help">공개 퀴즈 목록, 상세 화면 상단, 공유 미리보기에서 사용합니다. 비워 두면 공유 미리보기에는 사이트 기본 OG 이미지를 사용합니다.</p>
                    <input id="quiz_cover_image_upload" type="file" name="cover_image_upload" class="form-input form-control-full" accept="image/jpeg,image/png,image/webp">
                    <p class="form-help">JPG, PNG, WebP 이미지를 업로드할 수 있습니다. 최대 <?php echo sr_e(sr_format_bytes(sr_quiz_cover_image_upload_max_bytes())); ?>.</p>
                    <?php if (sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')) !== '') { ?>
                        <?php echo sr_admin_checkbox_toggle_html('quiz_cover_image_delete', 'cover_image_delete', '1', false, '현재 대표/OG 이미지 삭제'); ?>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_skin_key', '스킨', $quizHelp['display']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="quiz_skin_key" name="skin_key" class="form-select">
                        <option value="">환경설정 기본값 사용</option>
                        <?php foreach (sr_quiz_skin_options() as $skinKey => $skinLabel) { ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($values['skin_key'] ?? '') === (string) $skinKey ? ' selected' : ''; ?>><?php echo sr_e((string) $skinLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">퀴즈 상세, 응시, 결과 화면에 사용할 출력 템플릿 묶음을 고릅니다.</p>
                    <?php echo $quizScopeRadioHtml('skin_key', (string) ($values['source_skin_key'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_status', '상태', $quizHelp['status']['id'], $quizHelpOpenLabel, true); ?>
                <div class="form-field">
                    <select id="quiz_status" name="status" class="form-select" required>
                        <?php foreach (sr_quiz_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_status_label($status)); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $quizScopeRadioHtml('status', (string) ($values['source_status'] ?? 'item')); ?>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-section-result" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">채점/결과</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_mode', '모드', $quizHelp['mode']['id'], $quizHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <select id="quiz_mode" name="quiz_mode" class="form-select" required>
                        <?php foreach (sr_quiz_modes() as $quizMode) { ?>
                            <option value="<?php echo sr_e($quizMode); ?>"<?php echo (string) ($values['quiz_mode'] ?? 'scored') === $quizMode ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_mode_label($quizMode)); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $quizScopeRadioHtml('quiz_mode', (string) ($values['source_quiz_mode'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_scoring_model', '채점 모델', $quizHelp['scoring_model']['id'], $quizHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <select id="quiz_scoring_model" name="scoring_model" class="form-select" required>
                        <?php foreach (sr_quiz_scoring_models() as $scoringModel) { ?>
                            <option value="<?php echo sr_e($scoringModel); ?>"<?php echo (string) ($values['scoring_model'] ?? 'correct_answer') === $scoringModel ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_scoring_model_label($scoringModel)); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $quizScopeRadioHtml('scoring_model', (string) ($values['source_scoring_model'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_pass_score">통과 점수</label>
                <div class="form-field">
                    <input id="quiz_pass_score" type="number" name="pass_score" value="<?php echo sr_e((string) ($values['pass_score'] ?? '')); ?>" class="form-input" min="0" step="1">
                    <?php echo $quizScopeRadioHtml('pass_score', (string) ($values['source_pass_score'] ?? 'item')); ?>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-section-result-rules" class="card admin-list-card admin-list-form" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">결과 규칙</h2>
            <div class="card-actions">
                <?php echo $quizHelpButtonHtml('결과 규칙', $quizHelp['result_rules']['id']); ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="quiz-result-rule-add-modal" data-overlay="#quiz-result-rule-add-modal">규칙 추가</button>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-quiz-result-rule-table">
                <thead>
                    <tr>
                        <th>순서</th>
                        <th>결과 Key</th>
                        <th>제목</th>
                        <th>점수 범위</th>
                        <th>카테고리</th>
                        <th>기준값</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultRules === []) { ?>
                        <tr>
                            <td colspan="7" class="admin-empty-state">등록된 결과 규칙이 없습니다.</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($resultRules as $ruleIndex => $rule) { ?>
                        <?php
                        $ruleModalId = 'quiz-result-rule-modal-' . (string) $ruleIndex;
                        $minScore = $rule['min_score'] ?? null;
                        $maxScore = $rule['max_score'] ?? null;
                        $scoreRange = ($minScore === null ? '-' : (string) $minScore) . ' ~ ' . ($maxScore === null ? '-' : (string) $maxScore);
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($ruleIndex + 1)); ?></td>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($rule['result_key'] ?? '')); ?></code></td>
                            <td class="admin-table-break"><strong><?php echo sr_e((string) (($rule['title'] ?? '') !== '' ? $rule['title'] : '제목 없음')); ?></strong></td>
                            <td class="admin-table-nowrap"><?php echo sr_e($scoreRange); ?></td>
                            <td class="admin-table-nowrap"><?php if ((string) ($rule['category_key'] ?? '') !== '') { ?><code><?php echo sr_e((string) $rule['category_key']); ?></code><?php } else { ?>-<?php } ?></td>
                            <td class="admin-table-nowrap"><?php echo ($rule['threshold_value'] ?? null) === null ? '-' : sr_e((string) $rule['threshold_value']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="결과 규칙 수정" title="수정" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($ruleModalId); ?>" data-overlay="#<?php echo sr_e($ruleModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php foreach ($resultRules as $ruleIndex => $rule) { ?>
        <?php $ruleModalId = 'quiz-result-rule-modal-' . (string) $ruleIndex; ?>
        <div id="<?php echo sr_e($ruleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($ruleModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($ruleModalId); ?>-label" class="modal-title form-label-help"><?php echo $quizHelpButtonHtml('결과 규칙', $quizHelp['result_rules']['id']); ?><span>결과 규칙 <?php echo sr_e((string) ($ruleIndex + 1)); ?> 수정</span></h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($ruleModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_key_<?php echo sr_e((string) $ruleIndex); ?>">결과 Key <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <input id="quiz_result_rule_key_<?php echo sr_e((string) $ruleIndex); ?>" type="text" name="result_rule_key[]" value="<?php echo sr_e((string) ($rule['result_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input data-overlay-focus>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_title_<?php echo sr_e((string) $ruleIndex); ?>">제목 <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <input id="quiz_result_rule_title_<?php echo sr_e((string) $ruleIndex); ?>" type="text" name="result_rule_title[]" value="<?php echo sr_e((string) ($rule['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="190" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_min_score_<?php echo sr_e((string) $ruleIndex); ?>">최소 점수</label>
                            <div class="form-field">
                                <input id="quiz_result_rule_min_score_<?php echo sr_e((string) $ruleIndex); ?>" type="number" name="result_rule_min_score[]" value="<?php echo ($rule['min_score'] ?? null) === null ? '' : sr_e((string) $rule['min_score']); ?>" class="form-input" step="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_max_score_<?php echo sr_e((string) $ruleIndex); ?>">최대 점수</label>
                            <div class="form-field">
                                <input id="quiz_result_rule_max_score_<?php echo sr_e((string) $ruleIndex); ?>" type="number" name="result_rule_max_score[]" value="<?php echo ($rule['max_score'] ?? null) === null ? '' : sr_e((string) $rule['max_score']); ?>" class="form-input" step="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_category_key_<?php echo sr_e((string) $ruleIndex); ?>">카테고리 Key</label>
                            <div class="form-field">
                                <input id="quiz_result_rule_category_key_<?php echo sr_e((string) $ruleIndex); ?>" type="text" name="result_rule_category_key[]" value="<?php echo sr_e((string) ($rule['category_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_threshold_value_<?php echo sr_e((string) $ruleIndex); ?>">기준값</label>
                            <div class="form-field">
                                <input id="quiz_result_rule_threshold_value_<?php echo sr_e((string) $ruleIndex); ?>" type="number" name="result_rule_threshold_value[]" value="<?php echo ($rule['threshold_value'] ?? null) === null ? '' : sr_e((string) $rule['threshold_value']); ?>" class="form-input" step="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_result_rule_summary_<?php echo sr_e((string) $ruleIndex); ?>">요약</label>
                            <div class="form-field">
                                <textarea id="quiz_result_rule_summary_<?php echo sr_e((string) $ruleIndex); ?>" name="result_rule_summary[]" class="form-textarea" rows="3"><?php echo sr_e((string) ($rule['summary'] ?? '')); ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#<?php echo sr_e($ruleModalId); ?>">확인</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <div id="quiz-result-rule-add-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="quiz-result-rule-add-modal-label" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="quiz-result-rule-add-modal-label" class="modal-title form-label-help"><?php echo $quizHelpButtonHtml('결과 규칙', $quizHelp['result_rules']['id']); ?><span>결과 규칙 추가</span></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#quiz-result-rule-add-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_key_new">결과 Key <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="quiz_result_rule_key_new" type="text" name="result_rule_key[]" value="" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input data-admin-key-suggest-source="#quiz_result_rule_title_new" data-admin-key-suggest-fallback="result_rule_<?php echo sr_e((string) (count($resultRules) + 1)); ?>" data-overlay-focus>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_title_new">제목 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <input id="quiz_result_rule_title_new" type="text" name="result_rule_title[]" value="" class="form-input form-control-full" maxlength="190">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_min_score_new">최소 점수</label>
                        <div class="form-field">
                            <input id="quiz_result_rule_min_score_new" type="number" name="result_rule_min_score[]" value="" class="form-input" step="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_max_score_new">최대 점수</label>
                        <div class="form-field">
                            <input id="quiz_result_rule_max_score_new" type="number" name="result_rule_max_score[]" value="" class="form-input" step="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_category_key_new">카테고리 Key</label>
                        <div class="form-field">
                            <input id="quiz_result_rule_category_key_new" type="text" name="result_rule_category_key[]" value="" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_threshold_value_new">기준값</label>
                        <div class="form-field">
                            <input id="quiz_result_rule_threshold_value_new" type="number" name="result_rule_threshold_value[]" value="" class="form-input" step="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_result_rule_summary_new">요약</label>
                        <div class="form-field">
                            <textarea id="quiz_result_rule_summary_new" name="result_rule_summary[]" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#quiz-result-rule-add-modal">추가</button>
                </div>
            </div>
        </div>
    </div>

    <section id="quiz-section-access" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">공개/응시 조건</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <label class="form-label" for="quiz_starts_at">공개 시작일시</label>
                <div class="form-field">
                    <input id="quiz_starts_at" type="datetime-local" name="starts_at" value="<?php echo sr_e(sr_quiz_datetime_local_value($values['starts_at'] ?? '')); ?>" class="form-input">
                    <?php echo $quizScopeRadioHtml('starts_at', (string) ($values['source_starts_at'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_ends_at">공개 종료일시</label>
                <div class="form-field">
                    <input id="quiz_ends_at" type="datetime-local" name="ends_at" value="<?php echo sr_e(sr_quiz_datetime_local_value($values['ends_at'] ?? '')); ?>" class="form-input">
                    <?php echo $quizScopeRadioHtml('ends_at', (string) ($values['source_ends_at'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_attempt_limit_policy', '응시 제한', $quizHelp['attempt_limit']['id'], $quizHelpOpenLabel, true, true); ?>
                <div class="form-field">
                    <select id="quiz_attempt_limit_policy" name="attempt_limit_policy" class="form-select" required>
                        <?php foreach (sr_quiz_attempt_limit_policies() as $policy) { ?>
                            <option value="<?php echo sr_e($policy); ?>"<?php echo (string) ($values['attempt_limit_policy'] ?? 'unlimited') === $policy ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_attempt_limit_policy_label($policy)); ?></option>
                        <?php } ?>
                    </select>
                    <?php echo $quizScopeRadioHtml('attempt_limit', (string) ($values['source_attempt_limit'] ?? 'item')); ?>
                    <p class="form-help">기간당 1회일 때는 제한 기간도 같은 범위로 적용합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_attempt_limit_period_seconds">제한 기간(초) <span class="sr-required-label" data-quiz-attempt-period-required hidden>(필수)</span></label>
                <div class="form-field">
                    <input id="quiz_attempt_limit_period_seconds" type="number" name="attempt_limit_period_seconds" value="<?php echo sr_e((string) ($values['attempt_limit_period_seconds'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-attempt-period>
                    <p class="form-help">응시 제한이 기간당 1회일 때만 사용합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_member_group_keys', '응시 가능 회원 그룹', $quizHelp['member_groups']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <?php echo sr_admin_member_group_key_badge_select_html('quiz_member_group_keys', 'member_group_keys', sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []), $memberGroups); ?>
                    <p class="form-help">선택하지 않으면 로그인 회원 전체가 응시할 수 있습니다.</p>
                    <?php echo $quizScopeRadioHtml('member_group_keys', (string) ($values['source_member_group_keys'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_comments_enabled', '댓글 사용', $quizHelp['comments']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <label class="form-check form-label" for="quiz_comments_enabled">
                        <input id="quiz_comments_enabled" type="checkbox" name="comments_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['comments_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                        사용
                    </label>
                    <p class="form-help">활성화하면 공개 퀴즈 화면에 댓글 목록과 작성 폼을 표시합니다.</p>
                    <?php echo $quizScopeRadioHtml('comments_enabled', (string) ($values['source_comments_enabled'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_secret_comments_enabled">비밀 댓글</label>
                <div class="form-field">
                    <label class="form-check form-label" for="quiz_secret_comments_enabled">
                        <input id="quiz_secret_comments_enabled" type="checkbox" name="secret_comments_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['secret_comments_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                        허용
                    </label>
                    <?php echo $quizScopeRadioHtml('secret_comments_enabled', (string) ($values['source_secret_comments_enabled'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_comment_editor_key">댓글 입력 방식 <span class="sr-required-label">(필수)</span></label>
                <div class="form-field">
                    <?php echo sr_admin_radio_toggle_group_html('quiz_comment_editor_key', 'comment_editor_key', $commentEditorOptions, sr_editor_normalize_key((string) ($values['comment_editor_key'] ?? 'inherit'), true), true); ?>
                    <p class="form-help">상위 설정 사용은 퀴즈 환경설정의 댓글 입력 방식을 따릅니다. 개별 방식을 선택하면 이 퀴즈의 댓글·답글·수정 화면과 기존 댓글 출력에 적용됩니다.</p>
                    <?php echo $quizScopeRadioHtml('comment_editor_key', (string) ($values['source_comment_editor_key'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_reaction_preset_key">퀴즈 리액션 프리셋</label>
                <div class="form-field">
                    <select id="quiz_reaction_preset_key" name="reaction_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($values['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">비워두면 퀴즈 환경설정의 프리셋을 사용합니다.</p>
                    <?php echo $quizScopeRadioHtml('reaction_preset_key', (string) ($values['source_reaction_preset_key'] ?? 'item')); ?>
                </div>
            </div>
            <div class="form-row">
                <label class="form-label" for="quiz_reaction_comment_preset_key">댓글 리액션 프리셋</label>
                <div class="form-field">
                    <select id="quiz_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                        <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel) { ?>
                            <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($values['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">비워두면 퀴즈 환경설정의 댓글 프리셋을 사용합니다.</p>
                    <?php echo $quizScopeRadioHtml('reaction_comment_preset_key', (string) ($values['source_reaction_comment_preset_key'] ?? 'item')); ?>
                </div>
            </div>
        </div>
    </section>

    <?php echo sr_admin_comment_extra_fields_editor_html(
        'quiz_item_comment_extra_fields_json',
        'comment_extra_fields_json',
        $values['comment_extra_fields_json'] ?? '[]',
        '댓글 추가 입력 항목',
        '이 퀴즈의 댓글과 답글 작성 시 받을 항목입니다.',
        '<div class="admin-setting-source-line admin-setting-source-line-end">' . $quizScopeRadioHtml('comment_extra_fields_json', (string) ($values['source_comment_extra_fields_json'] ?? 'item')) . '</div>'
    ); ?>

    <section id="quiz-section-questions" class="card admin-list-card admin-list-form" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">문제 목록</h2>
            <div class="card-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="quiz-question-add-modal" data-overlay="#quiz-question-add-modal">문제 추가</button>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table table-list admin-quiz-question-table">
                <thead>
                    <tr>
                        <th>순서</th>
                        <th>문제 Key</th>
                        <th>문제</th>
                        <th>유형</th>
                        <th>점수</th>
                        <th>선택지</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($questions === []) { ?>
                        <tr>
                            <td colspan="7" class="admin-empty-state">등록된 문제가 없습니다. 문제 추가 버튼으로 첫 문제를 입력하세요.</td>
                        </tr>
                    <?php } ?>
                    <?php foreach ($questions as $questionIndex => $question) { ?>
                        <?php
                        $questionModalId = 'quiz-question-modal-' . (string) $questionIndex;
                        $questionPrompt = (string) ($question['prompt'] ?? '');
                        $questionChoices = array_values((array) ($question['choices'] ?? []));
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($questionIndex + 1)); ?></td>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($question['question_key'] ?? '')); ?></code></td>
                            <td class="admin-table-break">
                                <strong><?php echo sr_e($questionPrompt !== '' ? $questionPrompt : '내용 없음'); ?></strong>
                            </td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_quiz_question_type_label((string) ($question['question_type'] ?? 'single_choice'))); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e((string) ($question['score_value'] ?? 1)); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format(count($questionChoices))); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="문제 수정" title="수정" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($questionModalId); ?>" data-overlay="#<?php echo sr_e($questionModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php foreach ($questions as $questionIndex => $question) { ?>
        <?php
        $questionUid = 'qrow_' . (string) $questionIndex;
        $questionModalId = 'quiz-question-modal-' . (string) $questionIndex;
        $choices = array_values((array) ($question['choices'] ?? []));
        $savedChoiceCount = count($choices);
        for ($choiceExtra = $savedChoiceCount; $choiceExtra < 4; $choiceExtra++) {
            $choices[] = ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0];
        }
        ?>
        <div id="<?php echo sr_e($questionModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($questionModalId); ?>-label" aria-hidden="true" inert>
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content ui-form-theme">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($questionModalId); ?>-label" class="modal-title form-label-help"><?php echo $quizHelpButtonHtml('문제 관리', $quizHelp['questions']['id']); ?><span>문제 <?php echo sr_e((string) ($questionIndex + 1)); ?> 수정</span></h3>
                        <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($questionModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="question_uid[]" value="<?php echo sr_e($questionUid); ?>">
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html('quiz_question_type_' . (string) $questionIndex, '문제 유형', $quizHelp['question_type']['id'], $quizHelpOpenLabel, true, true); ?>
                            <div class="form-field">
                                <select id="quiz_question_type_<?php echo sr_e((string) $questionIndex); ?>" name="question_type[]" class="form-select" required>
                                    <?php foreach (sr_quiz_question_types() as $questionType) { ?>
                                        <option value="<?php echo sr_e($questionType); ?>"<?php echo (string) ($question['question_type'] ?? 'single_choice') === $questionType ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_question_type_label($questionType)); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <?php echo sr_admin_form_label_help_html('quiz_question_key_' . (string) $questionIndex, '문제 Key', $quizHelp['question_key']['id'], $quizHelpOpenLabel, true); ?>
                            <div class="form-field">
                                <input id="quiz_question_key_<?php echo sr_e((string) $questionIndex); ?>" type="text" name="question_key[]" value="<?php echo sr_e((string) ($question['question_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input data-overlay-focus>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>">문제 <span class="sr-required-label">(필수)</span></label>
                            <div class="form-field">
                                <textarea id="quiz_question_prompt_<?php echo sr_e((string) $questionIndex); ?>" name="question_prompt[]" class="form-textarea" rows="3" required><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <label class="form-label" for="quiz_question_score_<?php echo sr_e((string) $questionIndex); ?>">점수</label>
                            <div class="form-field">
                                <input id="quiz_question_score_<?php echo sr_e((string) $questionIndex); ?>" type="number" name="question_score[]" value="<?php echo sr_e((string) ($question['score_value'] ?? 1)); ?>" class="form-input" min="0" step="1">
                            </div>
                        </div>
                        <div class="form-row">
                            <span class="form-label form-label-help"><?php echo $quizHelpButtonHtml('선택지와 카테고리', $quizHelp['choices']['id']); ?><span>선택지와 카테고리</span></span>
                            <div class="form-field">
                                <div class="table-wrapper">
                                    <table class="table">
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
                                                <?php $choiceRequired = $choiceIndex < $savedChoiceCount; ?>
                                                <tr>
                                                    <td class="admin-table-nowrap"><input type="checkbox" name="correct_choice[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) $choiceIndex); ?>" class="form-checkbox"<?php echo (int) ($choice['is_correct'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                                    <td><input type="text" name="choice_key[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['choice_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}"<?php echo $choiceRequired ? ' required' : ''; ?> data-admin-key-input data-admin-key-suggest-scope="tr" data-admin-key-suggest-source='input[name^="choice_label["]' data-admin-key-suggest-fallback="choice_<?php echo sr_e((string) ($choiceIndex + 1)); ?>"></td>
                                                    <td><input type="text" name="choice_label[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['label'] ?? '')); ?>" class="form-input form-control-full" maxlength="255"<?php echo $choiceRequired ? ' required' : ''; ?>></td>
                                                    <td><input type="text" name="choice_category_key[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) ($choice['category_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input></td>
                                                    <td><input type="number" name="choice_category_weight[<?php echo sr_e($questionUid); ?>][]" value="<?php echo sr_e((string) (int) ($choice['category_weight'] ?? 0)); ?>" class="form-input" step="1"></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#<?php echo sr_e($questionModalId); ?>">확인</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php
    $newQuestionIndex = count($questions);
    $newQuestionUid = 'qrow_' . (string) $newQuestionIndex;
    $newQuestionChoices = [
        ['choice_key' => '', 'label' => '', 'is_correct' => 1, 'category_key' => '', 'category_weight' => 0],
        ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
        ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
        ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
    ];
    ?>
    <div id="quiz-question-add-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="quiz-question-add-modal-label" aria-hidden="true" inert>
        <div class="modal-dialog modal-dialog-lg">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="quiz-question-add-modal-label" class="modal-title form-label-help"><?php echo $quizHelpButtonHtml('문제 관리', $quizHelp['questions']['id']); ?><span>문제 추가</span></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#quiz-question-add-modal"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="question_uid[]" value="<?php echo sr_e($newQuestionUid); ?>">
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('quiz_question_type_new', '문제 유형', $quizHelp['question_type']['id'], $quizHelpOpenLabel, true, true); ?>
                        <div class="form-field">
                            <select id="quiz_question_type_new" name="question_type[]" class="form-select">
                                <?php foreach (sr_quiz_question_types() as $questionType) { ?>
                                    <option value="<?php echo sr_e($questionType); ?>"<?php echo $questionType === 'single_choice' ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_question_type_label($questionType)); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <?php echo sr_admin_form_label_help_html('quiz_question_key_new', '문제 Key', $quizHelp['question_key']['id'], $quizHelpOpenLabel, true); ?>
                        <div class="form-field">
                            <input id="quiz_question_key_new" type="text" name="question_key[]" value="" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input data-admin-key-suggest-source="#quiz_question_prompt_new" data-admin-key-suggest-fallback="question_<?php echo sr_e((string) ($newQuestionIndex + 1)); ?>" data-overlay-focus>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_question_prompt_new">문제 <span class="sr-required-label">(필수)</span></label>
                        <div class="form-field">
                            <textarea id="quiz_question_prompt_new" name="question_prompt[]" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <label class="form-label" for="quiz_question_score_new">점수</label>
                        <div class="form-field">
                            <input id="quiz_question_score_new" type="number" name="question_score[]" value="1" class="form-input" min="0" step="1">
                        </div>
                    </div>
                    <div class="form-row">
                        <span class="form-label form-label-help"><?php echo $quizHelpButtonHtml('선택지와 카테고리', $quizHelp['choices']['id']); ?><span>선택지와 카테고리</span></span>
                        <div class="form-field">
                            <div class="table-wrapper">
                                <table class="table">
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
                                        <?php foreach ($newQuestionChoices as $choiceIndex => $choice) { ?>
                                            <tr>
                                                <td class="admin-table-nowrap"><input type="checkbox" name="correct_choice[<?php echo sr_e($newQuestionUid); ?>][]" value="<?php echo sr_e((string) $choiceIndex); ?>" class="form-checkbox"<?php echo (int) ($choice['is_correct'] ?? 0) === 1 ? ' checked' : ''; ?>></td>
                                                <td><input type="text" name="choice_key[<?php echo sr_e($newQuestionUid); ?>][]" value="<?php echo sr_e((string) ($choice['choice_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input data-admin-key-suggest-scope="tr" data-admin-key-suggest-source='input[name^="choice_label["]' data-admin-key-suggest-fallback="choice_<?php echo sr_e((string) ($choiceIndex + 1)); ?>"></td>
                                                <td><input type="text" name="choice_label[<?php echo sr_e($newQuestionUid); ?>][]" value="" class="form-input form-control-full" maxlength="255"></td>
                                                <td><input type="text" name="choice_category_key[<?php echo sr_e($newQuestionUid); ?>][]" value="" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input></td>
                                                <td><input type="number" name="choice_category_weight[<?php echo sr_e($newQuestionUid); ?>][]" value="0" class="form-input" step="1"></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#quiz-question-add-modal">추가</button>
                </div>
            </div>
        </div>
    </div>

    <section id="quiz-section-reward" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">보상 정책</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_reward_enabled', '보상 사용', $quizHelp['reward']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <label class="form-check form-label" for="quiz_reward_enabled">
                        <input id="quiz_reward_enabled" type="checkbox" name="reward_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['reward_enabled'] ?? 0) === 1 ? ' checked' : ''; ?> data-quiz-reward-enabled>
                        <?php echo sr_admin_choice_label_html('지급'); ?>
                    </label>
                    <p class="form-help">켜면 회원이 퀴즈 통과 조건을 만족했을 때 아래 보상 정책으로 지급을 시도합니다.</p>
                </div>
            </div>
            <div class="form-row" data-quiz-reward-policy-row>
                <?php echo sr_admin_form_label_help_html('quiz_reward_provider', '보상 종류', $quizHelp['reward_provider']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <select id="quiz_reward_provider" name="reward_provider" class="form-select" data-quiz-reward-provider>
                        <?php foreach (sr_quiz_reward_providers() as $provider) { ?>
                            <option value="<?php echo sr_e($provider); ?>"<?php echo (string) ($values['reward_provider'] ?? 'ledger_asset') === $provider ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_provider_label($provider)); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">포인트, 적립금, 예치금처럼 금액을 지급하려면 포인트/금액을 선택합니다. 쿠폰을 지급하려면 쿠폰 발급을 선택합니다.</p>
                </div>
            </div>
            <div class="form-row" data-quiz-reward-policy-row data-quiz-reward-ledger-row>
                <div class="form-label form-label-help">
                    <?php echo $quizHelpButtonHtml('보상 자산', $quizHelp['reward_module']['id']); ?>
                    <label for="quiz_reward_module">보상 자산 <span class="sr-required-label" data-quiz-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <select id="quiz_reward_module" name="reward_module" class="form-select" data-quiz-reward-ledger-control>
                        <option value="">선택안함</option>
                        <?php foreach ($assetOptions as $assetKey => $asset) { ?>
                            <option value="<?php echo sr_e((string) $assetKey); ?>"<?php echo (string) ($values['reward_module'] ?? '') === (string) $assetKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $assetKey)); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">보상 종류가 포인트/금액일 때 지급할 항목입니다. 쿠폰 발급에는 사용하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row" data-quiz-reward-policy-row data-quiz-reward-coupon-row>
                <div class="form-label form-label-help">
                    <?php echo $quizHelpButtonHtml('보상 쿠폰', $quizHelp['reward_coupon_definition']['id']); ?>
                    <label for="quiz_reward_coupon_definition_id">보상 쿠폰 <span class="sr-required-label" data-quiz-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <select id="quiz_reward_coupon_definition_id" name="reward_coupon_definition_id" class="form-select" data-quiz-reward-coupon-control>
                        <option value="">선택안함</option>
                        <?php foreach ($couponRewardDefinitions as $couponDefinition) { ?>
                            <?php
                            $definitionId = (int) ($couponDefinition['id'] ?? 0);
                            $targetLabel = (string) ($couponDefinition['target_type'] ?? 'all');
                            if ((string) ($couponDefinition['target_id'] ?? '') !== '') {
                                $targetLabel .= ':' . (string) $couponDefinition['target_id'];
                            }
                            $couponOptionLabel = (string) ($couponDefinition['title'] ?? '');
                            if ($couponOptionLabel === '') {
                                $couponOptionLabel = (string) ($couponDefinition['coupon_key'] ?? '');
                            }
                            $couponOptionLabel .= ' [' . (string) ($couponDefinition['coupon_key'] ?? '') . ']';
                            $couponOptionLabel .= ' · 사용처 ' . $targetLabel;
                            $couponOptionLabel .= ' · 사용 ' . (string) (int) ($couponDefinition['max_uses_per_issue'] ?? 1) . '회';
                            ?>
                            <option value="<?php echo sr_e((string) $definitionId); ?>"<?php echo (string) ($values['reward_coupon_definition_id'] ?? '') === (string) $definitionId ? ' selected' : ''; ?>><?php echo sr_e($couponOptionLabel); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">보상 종류가 쿠폰 발급일 때 지급할 사용 가능한 쿠폰을 선택합니다.</p>
                    <?php if ($couponRewardDefinitions === []) { ?>
                        <p class="form-help form-help-warning">현재 선택 가능한 활성 쿠폰이 없습니다. <a href="<?php echo sr_e(sr_url('/admin/coupons')); ?>" target="_blank" rel="noopener noreferrer">쿠폰 관리</a>에서 사용 가능한 쿠폰을 먼저 등록하거나 활성화하세요.</p>
                    <?php } ?>
                </div>
            </div>
            <div class="form-row" data-quiz-reward-policy-row data-quiz-reward-ledger-row>
                <div class="form-label form-label-help">
                    <?php echo $quizHelpButtonHtml('보상 금액', $quizHelp['reward_amount']['id']); ?>
                    <label for="quiz_reward_amount">보상 금액 <span class="sr-required-label" data-quiz-reward-required hidden>(필수)</span></label>
                </div>
                <div class="form-field">
                    <input id="quiz_reward_amount" type="number" name="reward_amount" value="<?php echo sr_e((string) ($values['reward_amount'] ?? '')); ?>" class="form-input" min="1" step="1" data-quiz-reward-ledger-control>
                    <p class="form-help">보상 종류가 포인트/금액일 때 지급할 금액입니다. 쿠폰 발급에는 사용하지 않습니다.</p>
                </div>
            </div>
            <div class="form-row" data-quiz-reward-policy-row>
                <?php echo sr_admin_form_label_help_html('quiz_reward_dedupe_scope', '중복 지급 기준', $quizHelp['reward_dedupe']['id'], $quizHelpOpenLabel, false, true); ?>
                <div class="form-field">
                    <select id="quiz_reward_dedupe_scope" name="reward_dedupe_scope" class="form-select" data-quiz-reward-policy-control>
                        <?php foreach (sr_quiz_reward_dedupe_scopes() as $scope) { ?>
                            <option value="<?php echo sr_e($scope); ?>"<?php echo (string) ($values['reward_dedupe_scope'] ?? 'per_quiz') === $scope ? ' selected' : ''; ?>><?php echo sr_e(sr_quiz_reward_dedupe_scope_label($scope)); ?></option>
                        <?php } ?>
                    </select>
                    <p class="form-help">같은 회원에게 같은 보상을 다시 지급할 수 있는 범위를 정합니다.</p>
                    <?php echo $quizScopeRadioHtml('reward', (string) ($values['source_reward'] ?? 'item')); ?>
                </div>
            </div>
        </div>
    </section>

    <section id="quiz-section-links" class="card" data-admin-section-anchor>
        <div class="card-header">
            <h2 class="card-title">연결 대상</h2>
        </div>
        <div class="form-grid">
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_content_source_ids', '콘텐츠 ID', $quizHelp['sources']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <textarea id="quiz_content_source_ids" name="content_source_ids" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['content_source_ids'] ?? '')); ?></textarea>
                    <p class="form-help">콘텐츠 ID를 줄바꿈 또는 쉼표로 입력해 연결합니다.</p>
                </div>
            </div>
            <div class="form-row">
                <?php echo sr_admin_form_label_help_html('quiz_community_source_ids', '커뮤니티 게시글 ID', $quizHelp['sources']['id'], $quizHelpOpenLabel); ?>
                <div class="form-field">
                    <textarea id="quiz_community_source_ids" name="community_source_ids" class="form-textarea" rows="3"><?php echo sr_e((string) ($values['community_source_ids'] ?? '')); ?></textarea>
                    <p class="form-help">커뮤니티 게시글 ID를 줄바꿈 또는 쉼표로 입력해 연결합니다.</p>
                </div>
            </div>
        </div>
    </section>

    <div class="form-sticky-actions form-actions form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/quiz')); ?>" class="btn btn-solid-light">목록</a>
        <div class="admin-form-secondary-actions admin-form-draft-actions">
            <button type="submit" class="btn btn-solid-primary admin-form-final-save">저장</button>
            <button type="submit" name="admin_form_action" value="save_draft" class="btn btn-solid-light admin-form-draft-save" formnovalidate>임시저장</button>
            <?php if (is_array($adminFormDraftForDisplay ?? null)) { ?>
                <button type="submit" name="admin_form_action" value="discard_draft" class="btn btn-outline-danger admin-form-draft-delete" formnovalidate>임시저장 삭제</button>
            <?php } ?>
        </div>
    </div>
</form>
<?php echo sr_admin_form_draft_restore_script($adminFormDraftForDisplay ?? null, 'quiz-item-form'); ?>
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

    var rewardEnabled = document.querySelector('[data-quiz-reward-enabled]');
    var rewardProvider = document.querySelector('[data-quiz-reward-provider]');
    var rewardPolicyRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-policy-row]'));
    var rewardLedgerRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-ledger-row]'));
    var rewardCouponRows = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-coupon-row]'));
    var rewardLedgerControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-ledger-control]'));
    var rewardCouponControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-coupon-control]'));
    var rewardPolicyControls = Array.prototype.slice.call(document.querySelectorAll('[data-quiz-reward-policy-control]'));

    function setRowsHidden(rows, hidden) {
        rows.forEach(function (row) {
            row.hidden = hidden;
        });
    }

    function setControlsEnabled(controls, enabled) {
        controls.forEach(function (control) {
            control.disabled = !enabled;
        });
    }

    function setControlsRequired(controls, required) {
        controls.forEach(function (control) {
            control.required = required;
            var row = control.closest ? control.closest('.form-row') : null;
            if (!row) {
                return;
            }
            Array.prototype.slice.call(row.querySelectorAll('[data-quiz-reward-required]')).forEach(function (label) {
                label.hidden = !required;
            });
        });
    }

    function syncRewardFields() {
        if (!rewardEnabled || !rewardProvider) {
            return;
        }

        var enabled = rewardEnabled.checked;
        var provider = rewardProvider.value;
        var ledgerSelected = enabled && provider === 'ledger_asset';
        var couponSelected = enabled && provider === 'coupon';

        setRowsHidden(rewardPolicyRows, !enabled);
        setRowsHidden(rewardLedgerRows, !ledgerSelected);
        setRowsHidden(rewardCouponRows, !couponSelected);
        rewardProvider.disabled = !enabled;
        setControlsEnabled(rewardPolicyControls, enabled);
        setControlsEnabled(rewardLedgerControls, ledgerSelected);
        setControlsEnabled(rewardCouponControls, couponSelected);
        setControlsRequired(rewardLedgerControls, ledgerSelected);
        setControlsRequired(rewardCouponControls, couponSelected);
    }

    if (rewardEnabled && rewardProvider) {
        rewardEnabled.addEventListener('change', syncRewardFields);
        rewardProvider.addEventListener('change', syncRewardFields);
        syncRewardFields();
    }
})();
</script>
<?php foreach ($quizHelp as $quizHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $quizHelpModal['id'], (string) $quizHelpModal['title'], (string) $quizHelpModal['body_html']); ?>
<?php } ?>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
