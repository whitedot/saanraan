<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';
require_once SR_ROOT . '/modules/survey/helpers/admin-surveys.php';
require_once SR_ROOT . '/core/helpers/url-embed.php';
if (is_file(SR_ROOT . '/modules/reaction/helpers.php')) {
    require_once SR_ROOT . '/modules/reaction/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys', 'view');

$assetOptions = sr_survey_asset_options($pdo);
$couponDefinitions = sr_survey_coupon_definitions($pdo);
$memberGroups = sr_member_groups($pdo);
$surveyMemberGroupsForAdmin = array_values(array_filter($memberGroups, static function (array $memberGroup): bool {
    return (string) ($memberGroup['status'] ?? '') === 'enabled';
}));
$reactionPresetOptions = function_exists('sr_reaction_preset_options') ? sr_reaction_preset_options($pdo, true) : ['' => '리액션 기본값'];
$enabledMemberGroupKeys = [];
foreach ($memberGroups as $memberGroup) {
    if ((string) ($memberGroup['status'] ?? '') === 'enabled') {
        $enabledMemberGroupKeys[(string) ($memberGroup['group_key'] ?? '')] = true;
    }
}
$errors = [];
$notice = '';

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $intent = sr_post_string('intent', 40);
    if ($intent === 'delete') {
        sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys', 'delete');
        $surveyId = (int) sr_post_string('survey_id', 20);
        if ($surveyId < 1 || !is_array(sr_survey_by_id($pdo, $surveyId))) {
            sr_admin_redirect_with_result(sr_admin_action_result(['삭제할 설문을 찾을 수 없습니다.'], ''), '/admin/surveys');
        }
        if (!sr_survey_soft_delete_redacted($pdo, $surveyId, (int) ($account['id'] ?? 0))) {
            sr_admin_redirect_with_result(sr_admin_action_result(['삭제할 설문을 찾을 수 없습니다.'], ''), '/admin/surveys');
        }
        sr_url_embed_mark_target_url_cache_stale($pdo, 'survey', 'survey_form', $surveyId);
        sr_audit_log($pdo, [
            'actor_account_id' => (int) ($account['id'] ?? 0),
            'actor_type' => 'admin',
            'event_type' => 'survey.form.deleted',
            'target_type' => 'survey.form',
            'target_id' => (string) $surveyId,
            'result' => 'success',
            'message' => 'Survey form deleted.',
            'metadata' => [],
        ]);
        sr_admin_redirect_with_result(sr_admin_action_result([], '설문을 삭제했습니다.'), '/admin/surveys');
    }

    sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys', 'edit');
    $surveyId = (int) sr_post_string('survey_id', 20);
    $surveyKey = sr_survey_clean_key(sr_post_string('survey_key', 64), 64);
    $title = sr_survey_clean_single_line(sr_post_string('title', 190), 190);
    $description = sr_survey_clean_text(sr_post_string('description', 2000), 2000);
    $coverImageUrl = sr_survey_clean_cover_image_url(sr_post_string('cover_image_url', 255));
    $skinKey = sr_survey_optional_option_key_from_post(sr_post_string('skin_key', 40), sr_survey_skin_options());
    $researchPurpose = sr_survey_clean_text(sr_post_string('research_purpose', 4000), 4000);
    $targetPopulation = sr_survey_clean_text(sr_post_string('target_population', 2000), 2000);
    $recruitmentMethod = sr_survey_clean_text(sr_post_string('recruitment_method', 2000), 2000);
    $projectBrief = sr_survey_clean_text(sr_post_string('project_brief', 4000), 4000);
    $sponsorName = sr_survey_clean_single_line(sr_post_string('sponsor_name', 190), 190);
    $researchRegion = sr_survey_clean_single_line(sr_post_string('research_region', 120), 120);
    $researchLanguage = sr_survey_clean_single_line(sr_post_string('research_language', 60), 60);
    $fieldworkMethod = sr_survey_clean_single_line(sr_post_string('fieldwork_method', 120), 120);
    $sampleFrame = sr_survey_clean_text(sr_post_string('sample_frame', 3000), 3000);
    $sampleMethod = sr_survey_clean_single_line(sr_post_string('sample_method', 190), 190);
    $targetSampleSizeInput = trim(sr_post_string('target_sample_size', 20));
    $targetSampleSize = $targetSampleSizeInput === '' ? null : max(0, (int) $targetSampleSizeInput);
    $quotaPolicy = sr_survey_clean_text(sr_post_string('quota_policy', 3000), 3000);
    $responseRateBasis = sr_survey_clean_text(sr_post_string('response_rate_basis', 3000), 3000);
    $analysisPlan = sr_survey_clean_text(sr_post_string('analysis_plan', 4000), 4000);
    $weightingPolicy = sr_survey_clean_text(sr_post_string('weighting_policy', 3000), 3000);
    $marginErrorNote = sr_survey_clean_text(sr_post_string('margin_error_note', 2000), 2000);
    $methodologyDisclosure = sr_survey_clean_text(sr_post_string('methodology_disclosure', 4000), 4000);
    $ethicsNote = sr_survey_clean_text(sr_post_string('ethics_note', 4000), 4000);
    $sensitiveDataPolicy = sr_survey_clean_text(sr_post_string('sensitive_data_policy', 3000), 3000);
    $recontactPolicy = sr_survey_clean_text(sr_post_string('recontact_policy', 3000), 3000);
    $withdrawalPolicy = sr_survey_clean_text(sr_post_string('withdrawal_policy', 3000), 3000);
    $vendorName = sr_survey_clean_single_line(sr_post_string('vendor_name', 190), 190);
    $externalChannelPolicy = sr_survey_clean_text(sr_post_string('external_channel_policy', 3000), 3000);
    $inviteTokenPolicy = sr_survey_clean_text(sr_post_string('invite_token_policy', 3000), 3000);
    $qaStatus = sr_survey_clean_key(sr_post_string('qa_status', 30), 30);
    if (!in_array($qaStatus, sr_survey_qa_statuses(), true)) {
        $qaStatus = 'unchecked';
    }
    $qaNote = sr_survey_clean_text(sr_post_string('qa_note', 3000), 3000);
    $revisionLocked = ($_POST['revision_locked'] ?? '') === '1';
    $estimatedMinutes = max(0, min(10080, (int) sr_post_string('estimated_minutes', 20)));
    $organizerName = sr_survey_clean_single_line(sr_post_string('organizer_name', 120), 120);
    $contactText = sr_survey_clean_single_line(sr_post_string('contact_text', 190), 190);
    $consentRequired = ($_POST['consent_required'] ?? '') === '1';
    $consentText = sr_survey_clean_text(sr_post_string('consent_text', 4000), 4000);
    $privacyNotice = sr_survey_clean_text(sr_post_string('privacy_notice', 4000), 4000);
    $anonymousAllowed = ($_POST['anonymous_allowed'] ?? '') === '1';
    $loginRequired = ($_POST['login_required'] ?? '') === '1';
    $publicListed = ($_POST['public_listed'] ?? '') === '1';
    $robotsPolicy = sr_survey_clean_key(sr_post_string('robots_policy', 30), 30);
    if (!in_array($robotsPolicy, ['auto', 'index', 'noindex'], true)) {
        $robotsPolicy = 'auto';
    }
    $status = sr_post_string('status', 20);
    $startsAtInput = sr_post_string('starts_at', 30);
    $endsAtInput = sr_post_string('ends_at', 30);
    $startsAt = sr_survey_clean_admin_datetime($startsAtInput);
    $endsAt = sr_survey_clean_admin_datetime($endsAtInput);
    $responseLimitPolicy = sr_survey_clean_key(sr_post_string('response_limit_policy', 30), 30);
    if (!in_array($responseLimitPolicy, sr_survey_response_limit_policies(), true)) {
        $responseLimitPolicy = 'per_survey_once';
    }
    $responseLimitPeriodSeconds = max(0, (int) sr_post_string('response_limit_period_seconds', 20));
    $memberGroupKeys = sr_survey_normalize_member_group_keys($_POST['member_group_keys'] ?? []);
    $commentsEnabled = ($_POST['comments_enabled'] ?? '') === '1';
    $secretCommentsEnabled = ($_POST['secret_comments_enabled'] ?? '') === '1';
    $reactionPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_preset_key', 80)) : '';
    $reactionCommentPresetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_comment_preset_key', 80)) : '';
    $rewardEnabled = ($_POST['reward_enabled'] ?? '') === '1';
    $rewardProvider = sr_survey_clean_key(sr_post_string('reward_provider', 30), 30);
    $rewardModule = sr_survey_clean_key(sr_post_string('reward_module', 40), 40);
    $rewardCouponDefinitionId = (int) sr_post_string('reward_coupon_definition_id', 20);
    $rewardAmount = (int) sr_post_string('reward_amount', 20);
    $rewardDedupeScope = sr_survey_clean_key(sr_post_string('reward_dedupe_scope', 20), 20);
    $existingSurveyForSave = $surveyId > 0 ? sr_survey_by_id($pdo, $surveyId) : null;
    $beforeCoverImageUrl = is_array($existingSurveyForSave) ? sr_survey_clean_cover_image_url((string) ($existingSurveyForSave['cover_image_url'] ?? '')) : '';
    if (sr_post_string('cover_image_delete', 1) === '1') {
        $coverImageUrl = '';
    }
    $uploadedCoverImage = null;
    $coverImageUploadFile = $_FILES['cover_image_upload'] ?? null;
    if (sr_survey_cover_image_upload_was_provided($coverImageUploadFile)) {
        if (!is_array($coverImageUploadFile)) {
            $errors[] = '커버 이미지 업로드 값을 확인하세요.';
        } else {
            try {
                $uploadedCoverImage = sr_survey_upload_cover_image($coverImageUploadFile);
                if (is_array($uploadedCoverImage)) {
                    $coverImageUrl = (string) $uploadedCoverImage['url'];
                }
            } catch (Throwable $exception) {
                $errors[] = $exception instanceof RuntimeException ? (string) $exception->getMessage() : '커버 이미지 업로드 중 오류가 발생했습니다.';
            }
        }
    }

    if (!sr_survey_key_is_valid($surveyKey)) {
        $errors[] = '설문 Key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_survey_key_is_reserved($surveyKey)) {
        $errors[] = '예약된 설문 Key입니다.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM sr_survey_forms WHERE survey_key = :survey_key AND id <> :id LIMIT 1');
        $stmt->execute(['survey_key' => $surveyKey, 'id' => $surveyId]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 설문 Key입니다.';
        }
    }
    if ($title === '') {
        $errors[] = '설문 제목을 입력하세요.';
    }
    if ($skinKey !== '' && !isset(sr_survey_skin_options()[$skinKey])) {
        $errors[] = '설문 스킨 값이 올바르지 않습니다.';
    }
    if ($surveyId > 0 && !is_array($existingSurveyForSave)) {
        $errors[] = '수정할 설문을 찾을 수 없습니다.';
    }
    if (!in_array($status, sr_survey_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    if (trim($startsAtInput) !== '' && $startsAt === null) {
        $errors[] = '공개 시작일시 형식이 올바르지 않습니다.';
    }
    if (trim($endsAtInput) !== '' && $endsAt === null) {
        $errors[] = '공개 종료일시 형식이 올바르지 않습니다.';
    }
    if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
        $errors[] = '공개 종료일시는 시작일시 이후여야 합니다.';
    }
    if ($consentRequired && $consentText === '') {
        $errors[] = '참여 동의가 필수이면 동의 문구를 입력해야 합니다.';
    }
    if (!$loginRequired && !$anonymousAllowed) {
        $errors[] = '로그인이 필요 없으면 익명 응답 허용을 함께 켜야 합니다.';
    }
    if ($rewardEnabled && !$loginRequired) {
        $errors[] = '참여 보상은 로그인 필요 설문에서만 사용할 수 있습니다.';
    }
    if ($memberGroupKeys !== [] && !$loginRequired) {
        $errors[] = '회원 그룹 제한 설문은 로그인 필요 상태에서만 저장할 수 있습니다.';
    }
    foreach ($memberGroupKeys as $memberGroupKey) {
        if (empty($enabledMemberGroupKeys[$memberGroupKey])) {
            $errors[] = '참여 대상 회원 그룹을 확인하세요: ' . $memberGroupKey;
        }
    }
    if ($responseLimitPolicy === 'per_period' && $responseLimitPeriodSeconds < 1) {
        $errors[] = '기간당 1회 제한은 제한 기간을 1초 이상 입력해야 합니다.';
    }
    if ($rewardEnabled) {
        if (!in_array($rewardProvider, sr_survey_reward_providers(), true)) {
            $errors[] = '보상 공급자 값이 올바르지 않습니다.';
        }
        if (!in_array($rewardDedupeScope, sr_survey_reward_dedupe_scopes(), true)) {
            $errors[] = '보상 중복 기준 값이 올바르지 않습니다.';
        }
        if ($rewardProvider === 'ledger_asset') {
            if ($rewardModule === '' || !isset($assetOptions[$rewardModule])) {
                $errors[] = '보상 자산을 선택하세요.';
            }
            if ($rewardAmount < 1) {
                $errors[] = '보상 금액은 1 이상이어야 합니다.';
            }
        } elseif ($rewardProvider === 'coupon' && !sr_survey_coupon_definition_is_available($pdo, $rewardCouponDefinitionId)) {
            $errors[] = '보상으로 사용할 수 있는 쿠폰을 선택하세요.';
        }
    }

    $questions = [];
    $questionKeys = $_POST['question_key'] ?? [];
    $questionTypes = $_POST['question_type'] ?? [];
    $questionPrompts = $_POST['question_prompt'] ?? [];
    $questionAnalysisNotes = $_POST['question_analysis_note'] ?? [];
    $questionRequired = $_POST['question_required'] ?? [];
    $questionMinChoices = $_POST['question_min_choices'] ?? [];
    $questionMaxChoices = $_POST['question_max_choices'] ?? [];
    $questionScalePoints = $_POST['question_scale_points'] ?? [];
    $questionScaleMinLabels = $_POST['question_scale_min_label'] ?? [];
    $questionScaleMaxLabels = $_POST['question_scale_max_label'] ?? [];
    $questionNumberUnits = $_POST['question_number_unit'] ?? [];
    $questionNumberMins = $_POST['question_number_min'] ?? [];
    $questionNumberMaxes = $_POST['question_number_max'] ?? [];
    $questionAllowDecimal = $_POST['question_allow_decimal'] ?? [];
    $questionAllowOther = $_POST['question_allow_other'] ?? [];
    $questionNonresponsePolicies = $_POST['question_nonresponse_policy'] ?? [];
    $choiceLabels = $_POST['choice_labels'] ?? [];
    if (is_array($questionKeys)) {
        foreach ($questionKeys as $index => $questionKeyValue) {
            $questionKey = sr_survey_clean_key((string) $questionKeyValue, 64);
            $questionType = is_array($questionTypes) ? (string) ($questionTypes[$index] ?? 'single_choice') : 'single_choice';
            $prompt = is_array($questionPrompts) ? sr_survey_clean_text((string) ($questionPrompts[$index] ?? ''), 2000) : '';
            $analysisNote = is_array($questionAnalysisNotes) ? sr_survey_clean_text((string) ($questionAnalysisNotes[$index] ?? ''), 2000) : '';
            if ($questionKey === '' && $prompt === '') {
                continue;
            }
            if ($questionKey === '') {
                $questionKey = 'q' . (string) ((int) $index + 1);
            }
            $labelsText = is_array($choiceLabels) ? (string) ($choiceLabels[$index] ?? '') : '';
            $choices = [];
            foreach (preg_split('/\r\n|\r|\n/', $labelsText) ?: [] as $choiceIndex => $label) {
                $label = sr_survey_clean_single_line((string) $label, 255);
                if ($label !== '') {
                    $choices[] = ['choice_key' => 'c' . (string) ((int) $choiceIndex + 1), 'label' => $label];
                }
            }
            $questions[] = [
                'question_key' => $questionKey,
                'question_type' => in_array($questionType, sr_survey_question_types(), true) ? $questionType : 'single_choice',
                'prompt' => $prompt,
                'analysis_note' => $analysisNote,
                'required' => is_array($questionRequired) && in_array((string) $index, array_map('strval', $questionRequired), true) ? 1 : 0,
                'min_choices' => is_array($questionMinChoices) && trim((string) ($questionMinChoices[$index] ?? '')) !== '' ? max(0, (int) $questionMinChoices[$index]) : null,
                'max_choices' => is_array($questionMaxChoices) && trim((string) ($questionMaxChoices[$index] ?? '')) !== '' ? max(0, (int) $questionMaxChoices[$index]) : null,
                'scale_points' => is_array($questionScalePoints) && trim((string) ($questionScalePoints[$index] ?? '')) !== '' ? max(2, min(11, (int) $questionScalePoints[$index])) : null,
                'scale_min_label' => is_array($questionScaleMinLabels) ? sr_survey_clean_single_line((string) ($questionScaleMinLabels[$index] ?? ''), 120) : '',
                'scale_max_label' => is_array($questionScaleMaxLabels) ? sr_survey_clean_single_line((string) ($questionScaleMaxLabels[$index] ?? ''), 120) : '',
                'number_unit' => is_array($questionNumberUnits) ? sr_survey_clean_single_line((string) ($questionNumberUnits[$index] ?? ''), 60) : '',
                'number_min' => is_array($questionNumberMins) && trim((string) ($questionNumberMins[$index] ?? '')) !== '' && is_numeric((string) $questionNumberMins[$index]) ? (string) $questionNumberMins[$index] : null,
                'number_max' => is_array($questionNumberMaxes) && trim((string) ($questionNumberMaxes[$index] ?? '')) !== '' && is_numeric((string) $questionNumberMaxes[$index]) ? (string) $questionNumberMaxes[$index] : null,
                'allow_decimal' => is_array($questionAllowDecimal) && in_array((string) $index, array_map('strval', $questionAllowDecimal), true) ? 1 : 0,
                'allow_other' => is_array($questionAllowOther) && in_array((string) $index, array_map('strval', $questionAllowOther), true) ? 1 : 0,
                'nonresponse_policy' => is_array($questionNonresponsePolicies) ? sr_survey_clean_key((string) ($questionNonresponsePolicies[$index] ?? 'none'), 30) : 'none',
                'choices' => $choices,
            ];
        }
    }
    if ($questions === []) {
        $errors[] = '문항을 1개 이상 입력하세요.';
    }
    foreach ($questions as $index => $question) {
        if (!sr_survey_key_is_valid((string) $question['question_key'])) {
            $errors[] = '문항 ' . (string) ($index + 1) . '의 Key가 올바르지 않습니다.';
        }
        if ((string) $question['prompt'] === '') {
            $errors[] = '문항 ' . (string) ($index + 1) . '의 내용을 입력하세요.';
        }
        if (in_array((string) $question['question_type'], ['single_choice', 'multiple_choice'], true) && count((array) $question['choices']) < 2) {
            $errors[] = '선택형 문항 ' . (string) ($index + 1) . '에는 선택지를 2개 이상 입력하세요.';
        }
        if ((string) $question['question_type'] === 'multiple_choice' && $question['min_choices'] !== null && $question['max_choices'] !== null && (int) $question['min_choices'] > (int) $question['max_choices']) {
            $errors[] = '복수 선택 문항 ' . (string) ($index + 1) . '의 최대 선택 수는 최소 선택 수 이상이어야 합니다.';
        }
        if (in_array((string) $question['question_type'], ['number', 'rating', 'scale'], true) && $question['number_min'] !== null && $question['number_max'] !== null && (float) $question['number_min'] > (float) $question['number_max']) {
            $errors[] = '숫자/척도 문항 ' . (string) ($index + 1) . '의 최대값은 최소값 이상이어야 합니다.';
        }
    }
    if (is_array($existingSurveyForSave) && (int) ($existingSurveyForSave['revision_locked'] ?? 0) === 1) {
        $existingQuestionSignature = sr_survey_admin_question_signature(sr_survey_questions_with_choices($pdo, $surveyId));
        $postedQuestionSignature = sr_survey_admin_question_signature($questions);
        if ($existingQuestionSignature !== $postedQuestionSignature) {
            $errors[] = '설문지 잠금 상태에서는 문항을 수정할 수 없습니다.';
        }
    }

    if ($errors === []) {
        $now = sr_now();
        $pdo->beginTransaction();
        try {
            if ($surveyId > 0) {
                $existingSurvey = is_array($existingSurveyForSave) ? $existingSurveyForSave : sr_survey_by_id($pdo, $surveyId);
                $nextQuestionnaireVersion = max(1, (int) ($existingSurvey['questionnaire_version'] ?? 1)) + 1;
                $pdo->prepare(
                    'UPDATE sr_survey_forms
                     SET survey_key = :survey_key, title = :title, description = :description,
                         cover_image_url = :cover_image_url,
                         skin_key = :skin_key,
                         research_purpose = :research_purpose, target_population = :target_population, recruitment_method = :recruitment_method,
                         project_brief = :project_brief, sponsor_name = :sponsor_name, research_region = :research_region, research_language = :research_language,
                         fieldwork_method = :fieldwork_method, sample_frame = :sample_frame, sample_method = :sample_method, target_sample_size = :target_sample_size,
                         quota_policy = :quota_policy, response_rate_basis = :response_rate_basis, analysis_plan = :analysis_plan, weighting_policy = :weighting_policy,
                         margin_error_note = :margin_error_note, methodology_disclosure = :methodology_disclosure, ethics_note = :ethics_note,
                         sensitive_data_policy = :sensitive_data_policy, recontact_policy = :recontact_policy, withdrawal_policy = :withdrawal_policy,
                         vendor_name = :vendor_name, external_channel_policy = :external_channel_policy, invite_token_policy = :invite_token_policy,
                         qa_status = :qa_status, qa_note = :qa_note, questionnaire_version = :questionnaire_version, revision_locked = :revision_locked,
                         estimated_minutes = :estimated_minutes, organizer_name = :organizer_name, contact_text = :contact_text,
                         consent_required = :consent_required, consent_text = :consent_text, privacy_notice = :privacy_notice,
                         anonymous_allowed = :anonymous_allowed, login_required = :login_required, public_listed = :public_listed, robots_policy = :robots_policy,
                         status = :status, starts_at = :starts_at, ends_at = :ends_at,
                         response_limit_policy = :response_limit_policy, response_limit_period_seconds = :response_limit_period_seconds, member_group_keys_json = :member_group_keys_json,
                         comments_enabled = :comments_enabled, secret_comments_enabled = :secret_comments_enabled,
                         reaction_preset_key = :reaction_preset_key, reaction_comment_preset_key = :reaction_comment_preset_key,
                         reward_enabled = :reward_enabled,
                         updated_by_account_id = :updated_by_account_id, updated_at = :updated_at
                     WHERE id = :id AND deleted_at IS NULL'
                )->execute([
                    'survey_key' => $surveyKey,
                    'title' => $title,
                    'description' => $description,
                    'cover_image_url' => $coverImageUrl,
                    'skin_key' => $skinKey,
                    'research_purpose' => $researchPurpose,
                    'target_population' => $targetPopulation,
                    'recruitment_method' => $recruitmentMethod,
                    'project_brief' => $projectBrief,
                    'sponsor_name' => $sponsorName,
                    'research_region' => $researchRegion,
                    'research_language' => $researchLanguage,
                    'fieldwork_method' => $fieldworkMethod,
                    'sample_frame' => $sampleFrame,
                    'sample_method' => $sampleMethod,
                    'target_sample_size' => $targetSampleSize,
                    'quota_policy' => $quotaPolicy,
                    'response_rate_basis' => $responseRateBasis,
                    'analysis_plan' => $analysisPlan,
                    'weighting_policy' => $weightingPolicy,
                    'margin_error_note' => $marginErrorNote,
                    'methodology_disclosure' => $methodologyDisclosure,
                    'ethics_note' => $ethicsNote,
                    'sensitive_data_policy' => $sensitiveDataPolicy,
                    'recontact_policy' => $recontactPolicy,
                    'withdrawal_policy' => $withdrawalPolicy,
                    'vendor_name' => $vendorName,
                    'external_channel_policy' => $externalChannelPolicy,
                    'invite_token_policy' => $inviteTokenPolicy,
                    'qa_status' => $qaStatus,
                    'qa_note' => $qaNote,
                    'questionnaire_version' => $nextQuestionnaireVersion,
                    'revision_locked' => $revisionLocked ? 1 : 0,
                    'estimated_minutes' => $estimatedMinutes > 0 ? $estimatedMinutes : null,
                    'organizer_name' => $organizerName,
                    'contact_text' => $contactText,
                    'consent_required' => $consentRequired ? 1 : 0,
                    'consent_text' => $consentText,
                    'privacy_notice' => $privacyNotice,
                    'anonymous_allowed' => $anonymousAllowed ? 1 : 0,
                    'login_required' => $loginRequired ? 1 : 0,
                    'public_listed' => $publicListed ? 1 : 0,
                    'robots_policy' => $robotsPolicy,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'response_limit_policy' => $responseLimitPolicy,
                    'response_limit_period_seconds' => $responseLimitPolicy === 'per_period' ? $responseLimitPeriodSeconds : null,
                    'member_group_keys_json' => sr_survey_member_group_keys_json($memberGroupKeys),
                    'comments_enabled' => $commentsEnabled ? 1 : 0,
                    'secret_comments_enabled' => $secretCommentsEnabled ? 1 : 0,
                    'reaction_preset_key' => $reactionPresetKey,
                    'reaction_comment_preset_key' => $reactionCommentPresetKey,
                    'reward_enabled' => $rewardEnabled ? 1 : 0,
                    'updated_by_account_id' => (int) ($account['id'] ?? 0),
                    'updated_at' => $now,
                    'id' => $surveyId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sr_survey_forms
                        (survey_key, title, description, cover_image_url, skin_key, research_purpose, target_population, recruitment_method, estimated_minutes,
                         project_brief, sponsor_name, research_region, research_language, fieldwork_method, sample_frame, sample_method, target_sample_size,
                         quota_policy, response_rate_basis, analysis_plan, weighting_policy, margin_error_note, methodology_disclosure, ethics_note,
                         sensitive_data_policy, recontact_policy, withdrawal_policy, vendor_name, external_channel_policy, invite_token_policy,
                         qa_status, qa_note, questionnaire_version, revision_locked,
                         organizer_name, contact_text, consent_required, consent_text, privacy_notice, anonymous_allowed, login_required,
                         public_listed, robots_policy, status, starts_at, ends_at, response_limit_policy, response_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, reaction_preset_key, reaction_comment_preset_key, reward_enabled,
                         created_by_account_id, updated_by_account_id, created_at, updated_at)
                     VALUES
                        (:survey_key, :title, :description, :cover_image_url, :skin_key, :research_purpose, :target_population, :recruitment_method, :estimated_minutes,
                         :project_brief, :sponsor_name, :research_region, :research_language, :fieldwork_method, :sample_frame, :sample_method, :target_sample_size,
                         :quota_policy, :response_rate_basis, :analysis_plan, :weighting_policy, :margin_error_note, :methodology_disclosure, :ethics_note,
                         :sensitive_data_policy, :recontact_policy, :withdrawal_policy, :vendor_name, :external_channel_policy, :invite_token_policy,
                         :qa_status, :qa_note, 1, :revision_locked,
                         :organizer_name, :contact_text, :consent_required, :consent_text, :privacy_notice, :anonymous_allowed, :login_required,
                         :public_listed, :robots_policy, :status, :starts_at, :ends_at, :response_limit_policy, :response_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :reaction_preset_key, :reaction_comment_preset_key, :reward_enabled,
                         :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
                )->execute([
                    'survey_key' => $surveyKey,
                    'title' => $title,
                    'description' => $description,
                    'cover_image_url' => $coverImageUrl,
                    'skin_key' => $skinKey,
                    'research_purpose' => $researchPurpose,
                    'target_population' => $targetPopulation,
                    'recruitment_method' => $recruitmentMethod,
                    'project_brief' => $projectBrief,
                    'sponsor_name' => $sponsorName,
                    'research_region' => $researchRegion,
                    'research_language' => $researchLanguage,
                    'fieldwork_method' => $fieldworkMethod,
                    'sample_frame' => $sampleFrame,
                    'sample_method' => $sampleMethod,
                    'target_sample_size' => $targetSampleSize,
                    'quota_policy' => $quotaPolicy,
                    'response_rate_basis' => $responseRateBasis,
                    'analysis_plan' => $analysisPlan,
                    'weighting_policy' => $weightingPolicy,
                    'margin_error_note' => $marginErrorNote,
                    'methodology_disclosure' => $methodologyDisclosure,
                    'ethics_note' => $ethicsNote,
                    'sensitive_data_policy' => $sensitiveDataPolicy,
                    'recontact_policy' => $recontactPolicy,
                    'withdrawal_policy' => $withdrawalPolicy,
                    'vendor_name' => $vendorName,
                    'external_channel_policy' => $externalChannelPolicy,
                    'invite_token_policy' => $inviteTokenPolicy,
                    'qa_status' => $qaStatus,
                    'qa_note' => $qaNote,
                    'revision_locked' => $revisionLocked ? 1 : 0,
                    'estimated_minutes' => $estimatedMinutes > 0 ? $estimatedMinutes : null,
                    'organizer_name' => $organizerName,
                    'contact_text' => $contactText,
                    'consent_required' => $consentRequired ? 1 : 0,
                    'consent_text' => $consentText,
                    'privacy_notice' => $privacyNotice,
                    'anonymous_allowed' => $anonymousAllowed ? 1 : 0,
                    'login_required' => $loginRequired ? 1 : 0,
                    'public_listed' => $publicListed ? 1 : 0,
                    'robots_policy' => $robotsPolicy,
                    'status' => $status,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'response_limit_policy' => $responseLimitPolicy,
                    'response_limit_period_seconds' => $responseLimitPolicy === 'per_period' ? $responseLimitPeriodSeconds : null,
                    'member_group_keys_json' => sr_survey_member_group_keys_json($memberGroupKeys),
                    'comments_enabled' => $commentsEnabled ? 1 : 0,
                    'secret_comments_enabled' => $secretCommentsEnabled ? 1 : 0,
                    'reaction_preset_key' => $reactionPresetKey,
                    'reaction_comment_preset_key' => $reactionCommentPresetKey,
                    'reward_enabled' => $rewardEnabled ? 1 : 0,
                    'created_by_account_id' => (int) ($account['id'] ?? 0),
                    'updated_by_account_id' => (int) ($account['id'] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $surveyId = (int) $pdo->lastInsertId();
            }
            sr_survey_replace_questions($pdo, $surveyId, $questions, $now);
            sr_survey_replace_reward_policy($pdo, $surveyId, $rewardEnabled, $rewardProvider, $rewardModule, $rewardCouponDefinitionId, $rewardAmount, $rewardDedupeScope, $now);
            $afterCoverImageUrl = sr_survey_clean_cover_image_url($coverImageUrl);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) ($account['id'] ?? 0),
                'actor_type' => 'admin',
                'event_type' => 'survey.form.saved',
                'target_type' => 'survey.form',
                'target_id' => (string) $surveyId,
                'result' => 'success',
                'message' => 'Survey form saved.',
                'metadata' => [
                    'survey_key' => $surveyKey,
                    'status' => $status,
                    'qa_status' => $qaStatus,
                    'member_group_keys' => $memberGroupKeys,
                    'comments_enabled' => $commentsEnabled,
                    'secret_comments_enabled' => $secretCommentsEnabled,
                    'reaction_preset_key' => $reactionPresetKey,
                    'reaction_comment_preset_key' => $reactionCommentPresetKey,
                    'reward_enabled' => $rewardEnabled,
                    'cover_image_changed' => $beforeCoverImageUrl !== $afterCoverImageUrl,
                    'cover_image_uploaded' => sr_survey_cover_image_upload_was_provided($coverImageUploadFile),
                    'cover_image_deleted' => $beforeCoverImageUrl !== '' && $afterCoverImageUrl === '',
                ],
            ]);
            $pdo->commit();
            sr_url_embed_mark_target_url_cache_stale($pdo, 'survey', 'survey_form', $surveyId);
            if ($beforeCoverImageUrl !== '' && $beforeCoverImageUrl !== $afterCoverImageUrl) {
                sr_survey_delete_cover_image_storage($pdo, $beforeCoverImageUrl, $surveyId);
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_array($uploadedCoverImage)) {
                sr_storage_delete((string) ($uploadedCoverImage['driver'] ?? 'local'), (string) ($uploadedCoverImage['key'] ?? ''));
            }
            throw $exception;
        }
        sr_admin_flash_result(sr_admin_action_result([], '설문을 저장했습니다.'));
        sr_redirect('/admin/surveys?mode=edit&id=' . (string) $surveyId);
    } elseif (is_array($uploadedCoverImage)) {
        sr_storage_delete((string) ($uploadedCoverImage['driver'] ?? 'local'), (string) ($uploadedCoverImage['key'] ?? ''));
    }

    $redirectPath = $surveyId > 0
        ? '/admin/surveys?mode=edit&id=' . (string) $surveyId
        : '/admin/surveys?mode=new';
    sr_admin_flash_result(sr_admin_action_result($errors, ''));
    sr_redirect($redirectPath);
}
$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result($errors, $notice);
$errors = array_merge($errors, (array) ($flashResult['errors'] ?? []));
$notice = (string) ($flashResult['notice'] ?? $notice);
$requestedMode = sr_get_string('mode', 20);
$mode = in_array($requestedMode, ['edit', 'new', 'copy'], true) ? $requestedMode : 'list';
$editSurvey = in_array($mode, ['edit', 'copy'], true) ? sr_survey_by_id($pdo, (int) sr_get_string('id', 20)) : null;
if (in_array($mode, ['edit', 'copy'], true) && !is_array($editSurvey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
$editQuestions = is_array($editSurvey) ? sr_survey_questions_with_choices($pdo, (int) $editSurvey['id']) : [];
$editPolicy = $mode === 'edit' && is_array($editSurvey) ? sr_survey_active_reward_policy($pdo, (int) $editSurvey['id']) : null;
if ($mode === 'copy' && is_array($editSurvey)) {
    $editSurvey['id'] = 0;
    $editSurvey['survey_key'] = sr_survey_clean_key((string) ($editSurvey['survey_key'] ?? '') . '_copy', 64);
    $editSurvey['title'] = (string) ($editSurvey['title'] ?? '') . ' 복사본';
    $editSurvey['status'] = 'draft';
    $editSurvey['reward_enabled'] = 0;
    $editSurvey['public_listed'] = 0;
    $editSurvey['questionnaire_version'] = 1;
    $editSurvey['revision_locked'] = 0;
}

$adminPageTitle = $mode === 'list' ? '설문 관리' : ($mode === 'edit' ? '설문 수정' : ($mode === 'copy' ? '설문 복사' : '설문 생성'));
$adminPageTitleUrl = sr_admin_page_title_reset_url($mode === 'list', '/admin/surveys');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($mode === 'list'): ?>
    <?php
    $listStatus = sr_survey_clean_key(sr_get_string('status', 30), 30);
    if ($listStatus !== '' && !in_array($listStatus, sr_survey_statuses(), true)) {
        $listStatus = '';
    }
    $listAvailability = sr_survey_clean_key(sr_get_string('availability', 30), 30);
    if (!in_array($listAvailability, ['open', 'closed'], true)) {
        $listAvailability = '';
    }
    $listKeyword = sr_survey_clean_single_line(sr_get_string('q', 120), 120);
    $listWhere = ['s.deleted_at IS NULL'];
    $listParams = [];
    if ($listStatus !== '') {
        $listWhere[] = 's.status = :status';
        $listParams['status'] = $listStatus;
    }
    if ($listAvailability === 'open') {
        $listParams['now_start'] = sr_now();
        $listParams['now_end'] = sr_now();
        $listWhere[] = "s.status = 'active'";
        $listWhere[] = '(s.starts_at IS NULL OR s.starts_at <= :now_start)';
        $listWhere[] = '(s.ends_at IS NULL OR s.ends_at >= :now_end)';
    } elseif ($listAvailability === 'closed') {
        $listParams['now_start'] = sr_now();
        $listParams['now_end'] = sr_now();
        $listWhere[] = "(s.status <> 'active' OR (s.starts_at IS NOT NULL AND s.starts_at > :now_start) OR (s.ends_at IS NOT NULL AND s.ends_at < :now_end))";
    }
    if ($listKeyword !== '') {
        $listWhere[] = '(s.survey_key LIKE :keyword OR s.title LIKE :keyword OR s.description LIKE :keyword)';
        $listParams['keyword'] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $listKeyword) . '%';
    }
    $surveyListFilterOpen = $listStatus !== '' || $listAvailability !== '';
    $surveyStatusOptions = [];
    foreach (sr_survey_statuses() as $statusKey) {
        $surveyStatusOptions[$statusKey] = sr_survey_status_label($statusKey);
    }
    $surveySortOptions = sr_survey_admin_survey_sort_options();
    $surveyDefaultSort = sr_survey_admin_survey_default_sort();
    $surveySort = sr_admin_sort_from_request($surveySortOptions, $surveyDefaultSort);
    $surveyOrderSql = sr_admin_sort_order_sql($surveySortOptions, $surveySort, $surveyDefaultSort);
    if ($surveyOrderSql === '') {
        $surveyOrderSql = ' ORDER BY s.updated_at DESC, s.id DESC';
    } else {
        $surveyOrderSql .= ', s.id DESC';
    }
    $stmt = $pdo->prepare(
        'SELECT s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at, COUNT(r.id) AS response_count
         FROM sr_survey_forms s
         LEFT JOIN sr_survey_responses r ON r.survey_id = s.id
         WHERE ' . implode(' AND ', $listWhere) . '
         GROUP BY s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at
         ' . $surveyOrderSql . '
         LIMIT 200'
    );
    $stmt->execute($listParams);
    $surveys = $stmt->fetchAll();
    $surveyCanDelete = sr_admin_has_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys', 'delete');
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="filtering-form admin-survey-filter ui-form-theme">
        <div class="filtering filtering-card<?php echo $surveyListFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields">
                <div class="filtering-field filtering-field-fill admin-survey-filter-keyword">
                    <label for="survey_list_keyword" class="filtering-label">검색어</label>
                    <input id="survey_list_keyword" type="text" name="q" value="<?php echo sr_e($listKeyword); ?>" class="form-input filtering-input" maxlength="120" placeholder="Key, 제목, 설명">
                </div>
            </div>
            <div id="survey_detail_filters" class="filtering-body" data-filtering-body<?php echo $surveyListFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field">
                    <span class="filtering-label">상태</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('survey_status_filter', 'status', $surveyStatusOptions, [$listStatus], '전체'); ?>
                </div>
                <div class="filtering-field">
                    <span class="filtering-label">응답 가능</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('survey_availability_filter', 'availability', ['open' => '가능', 'closed' => '불가'], [$listAvailability], '전체'); ?>
                </div>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $surveyListFilterOpen ? 'true' : 'false'; ?>" aria-controls="survey_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </form>
    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title">설문 목록</h2>
            <div class="card-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys?mode=new')); ?>">새 설문</a>
            </div>
        </div>
        <?php if (empty($surveySort['is_default'])) { ?>
            <div class="admin-list-summary-row">
                <a href="<?php echo sr_e(sr_admin_sort_url($surveySortOptions, $surveyDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="설문 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            </div>
        <?php } ?>
        <div class="table-wrapper">
            <table class="table table-list admin-survey-table">
                <thead>
                    <tr>
                        <th<?php echo sr_admin_sort_aria('survey_key', $surveySort); ?>><?php echo sr_admin_sort_header_html('Key', 'survey_key', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('title', $surveySort); ?>><?php echo sr_admin_sort_header_html('제목', 'title', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('status', $surveySort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th>기간</th>
                        <th>대상</th>
                        <th<?php echo sr_admin_sort_aria('qa_status', $surveySort); ?>><?php echo sr_admin_sort_header_html('QA', 'qa_status', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('response_count', $surveySort); ?>><?php echo sr_admin_sort_header_html('응답', 'response_count', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('view_count', $surveySort); ?>><?php echo sr_admin_sort_header_html('조회수', 'view_count', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('reward_enabled', $surveySort); ?>><?php echo sr_admin_sort_header_html('보상', 'reward_enabled', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th<?php echo sr_admin_sort_aria('updated_at', $surveySort); ?>><?php echo sr_admin_sort_header_html('수정일', 'updated_at', $surveySort, $surveySortOptions, $surveyDefaultSort); ?></th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($surveys === []): ?>
                        <tr><td colspan="11" class="admin-empty-state">등록된 설문이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surveys as $survey): ?>
                        <?php
                        $surveyStatus = (string) ($survey['status'] ?? '');
                        $surveyQaStatus = (string) ($survey['qa_status'] ?? 'unchecked');
                        $listGroupKeys = sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]');
                        $rewardEnabled = (int) ($survey['reward_enabled'] ?? 0) === 1;
                        $periodLabel = trim((string) ($survey['starts_at'] ?? '') . ' ~ ' . (string) ($survey['ends_at'] ?? ''));
                        $publicSurveyUrl = sr_url('/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '?preview=admin');
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><code><?php echo sr_e((string) $survey['survey_key']); ?></code></td>
                            <td class="admin-table-break">
                                <strong><a href="<?php echo sr_e($publicSurveyUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e((string) $survey['title']); ?></a></strong><br>
                                <span class="admin-summary-meta">회원 조건 <?php echo sr_e(number_format(count($listGroupKeys))); ?>개</span>
                            </td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_survey_admin_status_class($surveyStatus)); ?>"><?php echo sr_e(sr_survey_status_label($surveyStatus)); ?></span></td>
                            <td class="admin-table-break"><?php echo $periodLabel === '~' || $periodLabel === '' ? '상시' : sr_e($periodLabel); ?></td>
                            <td class="admin-table-break"><?php echo $listGroupKeys === [] ? '전체' : sr_e(implode(', ', $listGroupKeys)); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e(sr_survey_admin_status_class($surveyQaStatus)); ?>"><?php echo sr_e(sr_survey_qa_status_label($surveyQaStatus)); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) $survey['response_count'])); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($survey['view_count'] ?? 0))); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo $rewardEnabled ? 'is-normal' : 'is-blocked'; ?>"><?php echo $rewardEnabled ? '사용' : '미사용'; ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_survey_time_html((string) $survey['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a class="btn btn-sm btn-icon btn-solid-light" href="<?php echo sr_e($publicSurveyUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="사용자 화면 미리보기" title="사용자 화면 미리보기"><?php echo sr_material_icon_html('visibility'); ?></a>
                                    <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys?mode=copy&id=' . (string) (int) $survey['id'])); ?>" aria-label="설문 복사" title="복사"><?php echo sr_material_icon_html('content_copy'); ?></a>
                                    <a class="btn btn-sm btn-icon btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys?mode=edit&id=' . (string) (int) $survey['id'])); ?>" aria-label="설문 수정" title="수정"><?php echo sr_material_icon_html('edit'); ?></a>
                                    <?php if ($surveyCanDelete): ?>
                                        <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="admin-inline-form">
                                            <?php echo sr_csrf_field(); ?>
                                            <input type="hidden" name="intent" value="delete">
                                            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) $survey['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="설문 삭제" title="삭제" data-confirm="<?php echo sr_e('설문을 삭제할까요? 기존 응답과 보상 이력은 보관됩니다.'); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('visibility'); ?> 사용자 화면 미리보기</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('content_copy'); ?> 복사</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> 삭제</span>
        </div>
        <?php echo sr_admin_status_description_list_html('survey_status', array_combine(sr_survey_statuses(), array_map('sr_survey_status_label', sr_survey_statuses())) ?: [], [], '설문 상태 설명'); ?>
        <?php echo sr_admin_status_description_list_html('survey_qa_status', array_combine(sr_survey_qa_statuses(), array_map('sr_survey_qa_status_label', sr_survey_qa_statuses())) ?: [], [], '점검 상태 설명'); ?>
        <?php echo sr_admin_status_description_list_html('survey_reward_enabled', ['enabled' => '사용', 'disabled' => '미사용'], [], '보상 사용 설명'); ?>
    </section>
<?php else: ?>
    <?php
    $values = is_array($editSurvey) ? $editSurvey : [
        ...sr_survey_settings($pdo),
        'id' => 0,
        'survey_key' => '',
        'title' => '',
        'description' => '',
        'cover_image_url' => '',
        'skin_key' => '',
        'research_purpose' => '',
        'target_population' => '',
        'recruitment_method' => '',
        'project_brief' => '',
        'sponsor_name' => '',
        'research_region' => '',
        'research_language' => '',
        'fieldwork_method' => '',
        'sample_frame' => '',
        'sample_method' => '',
        'target_sample_size' => '',
        'quota_policy' => '',
        'response_rate_basis' => '',
        'analysis_plan' => '',
        'weighting_policy' => '',
        'margin_error_note' => '',
        'methodology_disclosure' => '',
        'ethics_note' => '',
        'sensitive_data_policy' => '',
        'recontact_policy' => '',
        'withdrawal_policy' => '',
        'vendor_name' => '',
        'external_channel_policy' => '',
        'invite_token_policy' => '',
        'qa_status' => 'unchecked',
        'qa_note' => '',
        'questionnaire_version' => 1,
        'revision_locked' => 0,
        'estimated_minutes' => '',
        'organizer_name' => '',
        'contact_text' => '',
        'consent_required' => (int) sr_survey_settings($pdo)['default_consent_required'],
        'consent_text' => '',
        'privacy_notice' => '',
        'anonymous_allowed' => 0,
        'login_required' => (int) sr_survey_settings($pdo)['default_login_required'],
        'public_listed' => 1,
        'robots_policy' => 'auto',
        'status' => (string) sr_survey_settings($pdo)['default_status'],
        'starts_at' => '',
        'ends_at' => '',
        'response_limit_policy' => (string) sr_survey_settings($pdo)['default_response_limit_policy'],
        'response_limit_period_seconds' => (string) sr_survey_settings($pdo)['default_response_limit_period_seconds'],
        'comments_enabled' => 0,
        'secret_comments_enabled' => 0,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'reward_enabled' => 0,
    ];
    $selectedMemberGroupKeys = sr_survey_member_group_keys_from_json($values['member_group_keys_json'] ?? '[]');
    if ($editQuestions === []) {
        $editQuestions = [
            [
                'question_key' => 'q1',
                'question_type' => 'single_choice',
                'prompt' => '',
                'analysis_note' => '',
                'required' => 1,
                'min_choices' => null,
                'max_choices' => null,
                'scale_points' => null,
                'scale_min_label' => '',
                'scale_max_label' => '',
                'number_unit' => '',
                'number_min' => null,
                'number_max' => null,
                'allow_decimal' => 0,
                'allow_other' => 0,
                'nonresponse_policy' => 'none',
                'choices' => [['label' => ''], ['label' => '']],
            ],
        ];
    }
    $surveyHelpOpenLabel = '설명 보기';
    $surveyHelpBodyHtml = static function (array $items): string {
        $html = '';
        foreach ($items as $item) {
            $html .= '<p>' . sr_e((string) $item) . '</p>';
        }
        return $html;
    };
    $surveyHelp = [
        'survey_key' => [
            'id' => 'survey-help-key-modal',
            'title' => '설문 Key',
            'body_html' => $surveyHelpBodyHtml([
                '공개 URL과 내부 연결에 쓰는 고유 식별자입니다.',
                '소문자, 숫자, 밑줄만 사용하고 첫 글자는 소문자로 시작해야 합니다.',
            ]),
        ],
        'status' => [
            'id' => 'survey-help-status-modal',
            'title' => '상태',
            'body_html' => $surveyHelpBodyHtml([
                '공개 상태는 설문 목록 노출과 상세 진입 가능 여부에 영향을 줍니다.',
                '공개 기간, 로그인, 회원 그룹, 응답 제한은 제출 직전에 서버에서 다시 확인합니다.',
            ]),
        ],
        'response_limit' => [
            'id' => 'survey-help-response-limit-modal',
            'title' => '응답 제한',
            'body_html' => $surveyHelpBodyHtml([
                '같은 참여자가 설문에 다시 응답할 수 있는 기준입니다.',
                '기간당 1회를 선택하면 제한 기간을 초 단위로 입력해야 합니다.',
            ]),
        ],
        'member_groups' => [
            'id' => 'survey-help-member-groups-modal',
            'title' => '참여 대상 회원 그룹',
            'body_html' => $surveyHelpBodyHtml([
                '선택한 활성 회원 그룹에 속한 로그인 회원만 참여할 수 있습니다.',
                '그룹을 선택하지 않으면 로그인 조건에 맞는 전체 회원이 참여할 수 있습니다.',
            ]),
        ],
        'comments' => [
            'id' => 'survey-help-comments-modal',
            'title' => '댓글',
            'body_html' => $surveyHelpBodyHtml([
                '공개 설문 화면에서 로그인 회원이 댓글을 작성할 수 있게 합니다.',
                '비밀 댓글은 작성자와 댓글 관리 권한이 있는 관리자만 본문을 볼 수 있습니다.',
                '댓글 본문의 @닉네임 멘션은 회원 알림과 표시 스타일에 연결됩니다.',
            ]),
        ],
        'display' => [
            'id' => 'survey-help-display-modal',
            'title' => '공개 화면 표시',
            'body_html' => $surveyHelpBodyHtml([
                '스킨은 목록, 상세/응답, 완료 같은 공개 화면 본문의 출력 방식입니다.',
                '선택안함으로 두면 설문 환경설정의 기본 스킨을 사용합니다.',
            ]),
        ],
        'consent' => [
            'id' => 'survey-help-consent-modal',
            'title' => '참여 동의',
            'body_html' => $surveyHelpBodyHtml([
                '동의 필요를 켜면 참여자가 제출 전에 동의 문구를 확인해야 합니다.',
                '동의 문구와 개인정보 안내는 응답 스냅샷에도 함께 보관됩니다.',
            ]),
        ],
        'reward' => [
            'id' => 'survey-help-reward-modal',
            'title' => '보상',
            'body_html' => $surveyHelpBodyHtml([
                '보상 사용을 켜면 설문 제출 완료 후 포인트/금액 또는 쿠폰 지급을 시도합니다.',
                '보상 설문은 로그인 필요 상태에서만 저장되며, 지급 직전에 공급자 상태를 다시 확인합니다.',
            ]),
        ],
        'questions' => [
            'id' => 'survey-help-questions-modal',
            'title' => '문항 관리',
            'body_html' => $surveyHelpBodyHtml([
                '문항 표에서 등록된 문항의 Key, 유형, 필수 여부, 선택지 수를 확인합니다.',
                '문항 등록과 수정은 모달에서 처리하고, 저장 시 서버가 Key, 내용, 선택지, 범위를 다시 검증합니다.',
                '저장된 문항은 개수 제한 없이 모두 표시하고, 새 문항은 문항 등록 버튼으로 여는 모달에서 추가합니다.',
            ]),
        ],
        'question_key' => [
            'id' => 'survey-help-question-key-modal',
            'title' => '문항 Key',
            'body_html' => $surveyHelpBodyHtml([
                '한 설문 안에서 문항을 구분하는 내부 식별자입니다.',
                '통계, 분석 CSV, 개인정보 사본에서 문항을 안정적으로 찾는 기준이 됩니다.',
            ]),
        ],
        'question_type' => [
            'id' => 'survey-help-question-type-modal',
            'title' => '문항 유형',
            'body_html' => $surveyHelpBodyHtml([
                '선택형, 텍스트, 숫자, 별점, 척도 등 답변 저장 방식과 검증 기준을 결정합니다.',
                '선택형 문항은 선택지를 2개 이상 입력해야 합니다.',
            ]),
        ],
        'choices' => [
            'id' => 'survey-help-choices-modal',
            'title' => '선택지',
            'body_html' => $surveyHelpBodyHtml([
                '선택형 문항에서 보여줄 선택지를 줄마다 하나씩 입력합니다.',
                '기타 답변 허용은 선택지와 별개로 기타 텍스트 입력을 받을지 결정합니다.',
            ]),
        ],
    ];
    $surveyHelpButtonHtml = static function (string $label, string $modalId) use ($surveyHelpOpenLabel): string {
        $modalId = trim($modalId);

        return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $surveyHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
            . sr_material_icon_html('help')
            . '</button>';
    };
    $emptyQuestionSlot = ['question_key' => '', 'question_type' => 'single_choice', 'prompt' => '', 'analysis_note' => '', 'required' => 1, 'min_choices' => null, 'max_choices' => null, 'scale_points' => null, 'scale_min_label' => '', 'scale_max_label' => '', 'number_unit' => '', 'number_min' => null, 'number_max' => null, 'allow_decimal' => 0, 'allow_other' => 0, 'nonresponse_policy' => 'none', 'choices' => []];
    $questionSlots = [];
    foreach ($editQuestions as $questionSlot) {
        if (is_array($questionSlot)) {
            $questionSlots[] = array_merge($emptyQuestionSlot, $questionSlot);
        }
    }
    $newQuestionIndex = count($questionSlots);
    $questionModalSlots = $questionSlots;
    $questionModalSlots[$newQuestionIndex] = $emptyQuestionSlot;
    $surveySectionNavItems = [
        'survey-section-basic' => '기본 정보',
        'survey-section-disclosure' => '분석/윤리',
        'survey-section-qa' => 'QA/버전',
        'survey-section-consent' => '참여 동의',
        'survey-section-reward' => '보상',
        'survey-section-questions' => '문항',
    ];
    ?>
    <nav class="sticky-tabs anchor-tabs tab-nav-justified" aria-label="설문 설정 섹션">
        <?php $surveySectionNavIndex = 0; ?>
        <?php foreach ($surveySectionNavItems as $surveySectionId => $surveySectionLabel): ?>
            <a href="#<?php echo sr_e((string) $surveySectionId); ?>" class="tab-trigger-underline-justified<?php echo $surveySectionNavIndex === 0 ? ' active' : ''; ?>"<?php echo $surveySectionNavIndex === 0 ? ' aria-current="location"' : ''; ?>>
                <?php echo sr_e((string) $surveySectionLabel); ?>
            </a>
            <?php $surveySectionNavIndex++; ?>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="admin-form admin-survey-form ui-form-theme" enctype="multipart/form-data">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save">
            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
        <section id="survey-section-basic" class="card admin-list-card admin-list-form" data-admin-section-anchor>
            <div class="card-header"><h2 class="card-title">기본 정보</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('survey_key', '설문 Key', $surveyHelp['survey_key']['id'], $surveyHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <input id="survey_key" type="text" name="survey_key" value="<?php echo sr_e((string) ($values['survey_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" required data-admin-key-input>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_title">제목 <span class="sr-required-label">(필수)</span></label>
                    <div class="form-field">
                        <input id="survey_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="190" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_description">설명</label>
                    <div class="form-field">
                        <textarea id="survey_description" name="description" class="form-textarea"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_cover_image_url">대표/OG 이미지</label>
                    <div class="form-field">
                        <input id="survey_cover_image_url" type="text" name="cover_image_url" value="<?php echo sr_e(sr_survey_clean_cover_image_url((string) ($values['cover_image_url'] ?? ''))); ?>" class="form-input form-control-full" maxlength="255" placeholder="/storage/... 또는 https://...">
                        <p class="form-help">공개 설문 목록, 상세 화면 상단, 공유 미리보기에서 사용합니다. 비워 두면 공유 미리보기에는 사이트 기본 OG 이미지를 사용합니다.</p>
                        <input id="survey_cover_image_upload" type="file" name="cover_image_upload" class="form-input form-control-full" accept="image/jpeg,image/png,image/webp">
                        <p class="form-help">JPG, PNG, WebP 이미지를 업로드할 수 있습니다. 최대 <?php echo sr_e(sr_format_bytes(sr_survey_cover_image_upload_max_bytes())); ?>.</p>
                        <?php if (sr_survey_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')) !== '') { ?>
                            <?php echo sr_admin_checkbox_toggle_html('survey_cover_image_delete', 'cover_image_delete', '1', false, '현재 대표/OG 이미지 삭제'); ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('survey_skin_key', '스킨', $surveyHelp['display']['id'], $surveyHelpOpenLabel); ?>
                    <div class="form-field">
                        <select id="survey_skin_key" name="skin_key" class="form-select">
                            <option value="">환경설정 기본값 사용</option>
                            <?php foreach (sr_survey_skin_options() as $skinKey => $skinLabel): ?>
                                <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo (string) ($values['skin_key'] ?? '') === (string) $skinKey ? ' selected' : ''; ?>><?php echo sr_e((string) $skinLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">설문 상세, 응답, 완료 화면에 사용할 출력 방식을 고릅니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_research_purpose">연구 목적</label>
                    <div class="form-field">
                        <textarea id="survey_research_purpose" name="research_purpose" class="form-textarea"><?php echo sr_e((string) ($values['research_purpose'] ?? '')); ?></textarea>
                        <p class="form-help">공개 화면과 응답 스냅샷에 남길 설문 목적입니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_target_population">대상자</label>
                    <div class="form-field">
                        <textarea id="survey_target_population" name="target_population" class="form-textarea"><?php echo sr_e((string) ($values['target_population'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_recruitment_method">모집 방법</label>
                    <div class="form-field">
                        <textarea id="survey_recruitment_method" name="recruitment_method" class="form-textarea"><?php echo sr_e((string) ($values['recruitment_method'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_project_brief">프로젝트 개요</label>
                    <div class="form-field">
                        <textarea id="survey_project_brief" name="project_brief" class="form-textarea"><?php echo sr_e((string) ($values['project_brief'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_sponsor_name">의뢰/후원</label>
                    <div class="form-field">
                        <input id="survey_sponsor_name" type="text" name="sponsor_name" value="<?php echo sr_e((string) ($values['sponsor_name'] ?? '')); ?>" class="form-input" maxlength="190">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_research_region">조사 지역</label>
                    <div class="form-field">
                        <input id="survey_research_region" type="text" name="research_region" value="<?php echo sr_e((string) ($values['research_region'] ?? '')); ?>" class="form-input" maxlength="120">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_research_language">조사 언어</label>
                    <div class="form-field">
                        <input id="survey_research_language" type="text" name="research_language" value="<?php echo sr_e((string) ($values['research_language'] ?? '')); ?>" class="form-input" maxlength="60">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_fieldwork_method">실사 방식</label>
                    <div class="form-field">
                        <input id="survey_fieldwork_method" type="text" name="fieldwork_method" value="<?php echo sr_e((string) ($values['fieldwork_method'] ?? '')); ?>" class="form-input" maxlength="120">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_sample_method">표본 추출</label>
                    <div class="form-field">
                        <input id="survey_sample_method" type="text" name="sample_method" value="<?php echo sr_e((string) ($values['sample_method'] ?? '')); ?>" class="form-input" maxlength="190">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_target_sample_size">목표 표본 수</label>
                    <div class="form-field">
                        <input id="survey_target_sample_size" type="number" name="target_sample_size" value="<?php echo sr_e((string) ($values['target_sample_size'] ?? '')); ?>" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_sample_frame">표본틀</label>
                    <div class="form-field">
                        <textarea id="survey_sample_frame" name="sample_frame" class="form-textarea"><?php echo sr_e((string) ($values['sample_frame'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_quota_policy">쿼터/마감 기준</label>
                    <div class="form-field">
                        <textarea id="survey_quota_policy" name="quota_policy" class="form-textarea"><?php echo sr_e((string) ($values['quota_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_response_rate_basis">응답률 산정 기준</label>
                    <div class="form-field">
                        <textarea id="survey_response_rate_basis" name="response_rate_basis" class="form-textarea"><?php echo sr_e((string) ($values['response_rate_basis'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_estimated_minutes">예상 소요 시간</label>
                    <div class="form-field">
                        <input id="survey_estimated_minutes" type="number" name="estimated_minutes" value="<?php echo sr_e((string) ($values['estimated_minutes'] ?? '')); ?>" class="form-input" min="0" max="10080">
                        <p class="form-help">분 단위로 입력합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_organizer_name">주관자</label>
                    <div class="form-field">
                        <input id="survey_organizer_name" type="text" name="organizer_name" value="<?php echo sr_e((string) ($values['organizer_name'] ?? '')); ?>" class="form-input" maxlength="120">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_contact_text">문의처</label>
                    <div class="form-field">
                        <input id="survey_contact_text" type="text" name="contact_text" value="<?php echo sr_e((string) ($values['contact_text'] ?? '')); ?>" class="form-input" maxlength="190">
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('survey_status', '상태', $surveyHelp['status']['id'], $surveyHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="survey_status" name="status" class="form-select" required>
                            <?php foreach (sr_survey_statuses() as $status): ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_status_label($status)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_starts_at">공개 시작일시</label>
                    <div class="form-field">
                        <input id="survey_starts_at" type="datetime-local" name="starts_at" value="<?php echo sr_e(sr_survey_datetime_local_value($values['starts_at'] ?? '')); ?>" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_ends_at">공개 종료일시</label>
                    <div class="form-field">
                        <input id="survey_ends_at" type="datetime-local" name="ends_at" value="<?php echo sr_e(sr_survey_datetime_local_value($values['ends_at'] ?? '')); ?>" class="form-input">
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">로그인 필요</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_login_required">
                            <input id="survey_login_required" type="checkbox" name="login_required" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['login_required'] ?? 1) === 1 ? ' checked' : ''; ?>>
                            필수
                        </label>
                        <p class="form-help">보상 설문은 로그인 필요 상태에서만 저장됩니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">익명 응답</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_anonymous_allowed">
                            <input id="survey_anonymous_allowed" type="checkbox" name="anonymous_allowed" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['anonymous_allowed'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            허용
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">공개 목록</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_public_listed">
                            <input id="survey_public_listed" type="checkbox" name="public_listed" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['public_listed'] ?? 1) === 1 ? ' checked' : ''; ?>>
                            노출
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $surveyHelpButtonHtml('참여 대상 회원 그룹', $surveyHelp['member_groups']['id']); ?><span>참여 대상 회원 그룹</span></div>
                    <div class="form-field">
                        <?php echo sr_admin_member_group_key_badge_select_html('survey_member_group_keys', 'member_group_keys', $selectedMemberGroupKeys, $surveyMemberGroupsForAdmin); ?>
                        <p class="form-help">선택하면 해당 그룹에 속한 로그인 회원만 참여할 수 있습니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('survey_comments_enabled', '댓글', $surveyHelp['comments']['id'], $surveyHelpOpenLabel); ?>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_comments_enabled">
                            <input id="survey_comments_enabled" type="checkbox" name="comments_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['comments_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            사용
                        </label>
                        <label class="form-check form-label" for="survey_secret_comments_enabled">
                            <input id="survey_secret_comments_enabled" type="checkbox" name="secret_comments_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['secret_comments_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            비밀 댓글 허용
                        </label>
                        <p class="form-help">활성화하면 공개 설문 화면에 로그인 회원용 댓글 목록과 작성 폼을 표시합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reaction_preset_key">설문 리액션 프리셋</label>
                    <div class="form-field">
                        <select id="survey_reaction_preset_key" name="reaction_preset_key" class="form-select">
                            <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel): ?>
                                <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($values['reaction_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">비워두면 설문 환경설정의 프리셋을 사용합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reaction_comment_preset_key">댓글 리액션 프리셋</label>
                    <div class="form-field">
                        <select id="survey_reaction_comment_preset_key" name="reaction_comment_preset_key" class="form-select">
                            <?php foreach ($reactionPresetOptions as $presetKey => $presetLabel): ?>
                                <option value="<?php echo sr_e((string) $presetKey); ?>"<?php echo (string) ($values['reaction_comment_preset_key'] ?? '') === (string) $presetKey ? ' selected' : ''; ?>><?php echo sr_e((string) $presetLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">비워두면 설문 환경설정의 댓글 프리셋을 사용합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('survey_response_limit_policy', '응답 제한', $surveyHelp['response_limit']['id'], $surveyHelpOpenLabel); ?>
                    <div class="form-field">
                        <select id="survey_response_limit_policy" name="response_limit_policy" class="form-select">
                            <?php foreach (sr_survey_response_limit_policies() as $limitPolicy): ?>
                                <option value="<?php echo sr_e($limitPolicy); ?>"<?php echo (string) ($values['response_limit_policy'] ?? 'per_survey_once') === $limitPolicy ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_response_limit_policy_label($limitPolicy)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_response_limit_period_seconds">제한 기간</label>
                    <div class="form-field">
                        <input id="survey_response_limit_period_seconds" type="number" name="response_limit_period_seconds" value="<?php echo sr_e((string) ($values['response_limit_period_seconds'] ?? '')); ?>" class="form-input" min="0">
                        <p class="form-help">기간당 1회 제한일 때 초 단위로 입력합니다.</p>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_robots_policy">검색 로봇</label>
                    <div class="form-field">
                        <select id="survey_robots_policy" name="robots_policy" class="form-select">
                            <option value="auto"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'auto' ? ' selected' : ''; ?>>자동</option>
                            <option value="index"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'index' ? ' selected' : ''; ?>>색인 허용</option>
                            <option value="noindex"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'noindex' ? ' selected' : ''; ?>>색인 제외</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>
        <section id="survey-section-disclosure" class="card admin-list-card admin-list-form" data-admin-section-anchor>
            <div class="card-header"><h2 class="card-title">분석·공표·윤리</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <label class="form-label" for="survey_analysis_plan">분석 계획</label>
                    <div class="form-field">
                        <textarea id="survey_analysis_plan" name="analysis_plan" class="form-textarea"><?php echo sr_e((string) ($values['analysis_plan'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_weighting_policy">가중치 기준</label>
                    <div class="form-field">
                        <textarea id="survey_weighting_policy" name="weighting_policy" class="form-textarea"><?php echo sr_e((string) ($values['weighting_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_margin_error_note">오차/한계</label>
                    <div class="form-field">
                        <textarea id="survey_margin_error_note" name="margin_error_note" class="form-textarea"><?php echo sr_e((string) ($values['margin_error_note'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_methodology_disclosure">방법론 공표 문구</label>
                    <div class="form-field">
                        <textarea id="survey_methodology_disclosure" name="methodology_disclosure" class="form-textarea"><?php echo sr_e((string) ($values['methodology_disclosure'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_ethics_note">윤리 검토</label>
                    <div class="form-field">
                        <textarea id="survey_ethics_note" name="ethics_note" class="form-textarea"><?php echo sr_e((string) ($values['ethics_note'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_sensitive_data_policy">민감정보 기준</label>
                    <div class="form-field">
                        <textarea id="survey_sensitive_data_policy" name="sensitive_data_policy" class="form-textarea"><?php echo sr_e((string) ($values['sensitive_data_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_recontact_policy">재연락 기준</label>
                    <div class="form-field">
                        <textarea id="survey_recontact_policy" name="recontact_policy" class="form-textarea"><?php echo sr_e((string) ($values['recontact_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_withdrawal_policy">철회 기준</label>
                    <div class="form-field">
                        <textarea id="survey_withdrawal_policy" name="withdrawal_policy" class="form-textarea"><?php echo sr_e((string) ($values['withdrawal_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_vendor_name">외부 패널/벤더</label>
                    <div class="form-field">
                        <input id="survey_vendor_name" type="text" name="vendor_name" value="<?php echo sr_e((string) ($values['vendor_name'] ?? '')); ?>" class="form-input" maxlength="190">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_external_channel_policy">외부 채널 기준</label>
                    <div class="form-field">
                        <textarea id="survey_external_channel_policy" name="external_channel_policy" class="form-textarea"><?php echo sr_e((string) ($values['external_channel_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_invite_token_policy">초대 토큰 기준</label>
                    <div class="form-field">
                        <textarea id="survey_invite_token_policy" name="invite_token_policy" class="form-textarea"><?php echo sr_e((string) ($values['invite_token_policy'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
        </section>
        <section id="survey-section-qa" class="card admin-list-card admin-list-form" data-admin-section-anchor>
            <div class="card-header"><h2 class="card-title">QA·버전</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <label class="form-label" for="survey_qa_status">QA 상태</label>
                    <div class="form-field">
                        <select id="survey_qa_status" name="qa_status" class="form-select">
                            <?php foreach (sr_survey_qa_statuses() as $statusKey): ?>
                                <option value="<?php echo sr_e($statusKey); ?>"<?php echo (string) ($values['qa_status'] ?? 'unchecked') === $statusKey ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_qa_status_label($statusKey)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">설문지 버전</span>
                    <div class="form-field">
                        <p class="form-help"><?php echo sr_e((string) (int) ($values['questionnaire_version'] ?? 1)); ?></p>
                    </div>
                </div>
                <div class="form-row">
                    <span class="form-label">수정 잠금</span>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_revision_locked">
                            <input id="survey_revision_locked" type="checkbox" name="revision_locked" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['revision_locked'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            잠금
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_qa_note">QA 메모</label>
                    <div class="form-field">
                        <textarea id="survey_qa_note" name="qa_note" class="form-textarea"><?php echo sr_e((string) ($values['qa_note'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
        </section>
        <section id="survey-section-consent" class="card admin-list-card admin-list-form" data-admin-section-anchor>
            <div class="card-header"><h2 class="card-title">참여 동의</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $surveyHelpButtonHtml('참여 동의', $surveyHelp['consent']['id']); ?><span>동의 필요</span></div>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_consent_required">
                            <input id="survey_consent_required" type="checkbox" name="consent_required" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['consent_required'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            필수
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_consent_text">동의 문구</label>
                    <div class="form-field">
                        <textarea id="survey_consent_text" name="consent_text" class="form-textarea"><?php echo sr_e((string) ($values['consent_text'] ?? '')); ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_privacy_notice">개인정보 안내</label>
                    <div class="form-field">
                        <textarea id="survey_privacy_notice" name="privacy_notice" class="form-textarea"><?php echo sr_e((string) ($values['privacy_notice'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>
        </section>
        <section id="survey-section-reward" class="card admin-list-card admin-list-form" data-admin-section-anchor>
            <div class="card-header"><h2 class="card-title">보상</h2></div>
            <div class="form-grid">
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $surveyHelpButtonHtml('보상', $surveyHelp['reward']['id']); ?><span>보상 사용</span></div>
                    <div class="form-field">
                        <label class="form-check form-label" for="survey_reward_enabled">
                            <input id="survey_reward_enabled" type="checkbox" name="reward_enabled" value="1" class="form-switch form-switch-light"<?php echo (int) ($values['reward_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                            지급
                        </label>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reward_provider">보상 공급자</label>
                    <div class="form-field">
                        <?php $policyProvider = is_array($editPolicy) ? (string) ($editPolicy['reward_provider'] ?? 'ledger_asset') : 'ledger_asset'; ?>
                        <select id="survey_reward_provider" name="reward_provider" class="form-select">
                            <option value="ledger_asset"<?php echo $policyProvider === 'ledger_asset' ? ' selected' : ''; ?>>포인트/금액</option>
                            <option value="coupon"<?php echo $policyProvider === 'coupon' ? ' selected' : ''; ?>>쿠폰</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reward_module">보상 자산</label>
                    <div class="form-field">
                        <select id="survey_reward_module" name="reward_module" class="form-select">
                            <option value="">선택</option>
                            <?php foreach ($assetOptions as $moduleKey => $asset): ?>
                                <option value="<?php echo sr_e((string) $moduleKey); ?>"<?php echo is_array($editPolicy) && (string) ($editPolicy['reward_module'] ?? '') === (string) $moduleKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $moduleKey)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reward_amount">보상 금액</label>
                    <div class="form-field">
                        <input id="survey_reward_amount" type="number" name="reward_amount" value="<?php echo sr_e((string) (int) (is_array($editPolicy) ? ($editPolicy['reward_amount'] ?? 0) : 0)); ?>" class="form-input" min="0">
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reward_coupon_definition_id">보상 쿠폰</label>
                    <div class="form-field">
                        <select id="survey_reward_coupon_definition_id" name="reward_coupon_definition_id" class="form-select">
                            <option value="">선택</option>
                            <?php foreach ($couponDefinitions as $definition): ?>
                                <option value="<?php echo sr_e((string) (int) $definition['id']); ?>"<?php echo is_array($editPolicy) && (string) ($editPolicy['reward_code'] ?? '') === (string) (int) $definition['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $definition['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="survey_reward_dedupe_scope">중복 지급 기준</label>
                    <div class="form-field">
                        <?php $policyScope = is_array($editPolicy) ? (string) ($editPolicy['dedupe_scope'] ?? 'per_survey') : 'per_survey'; ?>
                        <select id="survey_reward_dedupe_scope" name="reward_dedupe_scope" class="form-select">
                            <option value="per_survey"<?php echo $policyScope === 'per_survey' ? ' selected' : ''; ?>>설문당 1회</option>
                            <option value="per_response"<?php echo $policyScope === 'per_response' ? ' selected' : ''; ?>>응답마다</option>
                        </select>
                    </div>
                </div>
            </div>
        </section>
        <section id="survey-section-questions" class="card admin-list-card admin-list-form admin-survey-question-list-card" data-admin-section-anchor>
            <div class="card-header">
                <h2 class="card-title form-label-help"><?php echo $surveyHelpButtonHtml('문항 관리', $surveyHelp['questions']['id']); ?><span>문항</span></h2>
                <div class="card-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="survey-question-modal-<?php echo sr_e((string) $newQuestionIndex); ?>" data-overlay="#survey-question-modal-<?php echo sr_e((string) $newQuestionIndex); ?>">문항 등록</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="table table-list admin-survey-question-table">
                    <thead>
                        <tr>
                            <th>순서</th>
                            <th>Key</th>
                            <th>문항</th>
                            <th>유형</th>
                            <th>필수</th>
                            <th>선택지</th>
                            <th class="text-end">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($questionSlots === []): ?>
                            <tr><td colspan="7" class="admin-empty-state">등록된 문항이 없습니다.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($questionSlots as $index => $question): ?>
                            <?php
                            $questionKey = trim((string) ($question['question_key'] ?? ''));
                            $questionPrompt = trim((string) ($question['prompt'] ?? ''));
                            $questionRegistered = $questionKey !== '' || $questionPrompt !== '';
                            $questionChoices = array_values(array_filter((array) ($question['choices'] ?? []), static fn (array $choice): bool => (int) ($choice['is_other'] ?? 0) !== 1 && (int) ($choice['is_nonresponse'] ?? 0) !== 1));
                            $questionModalId = 'survey-question-modal-' . (string) $index;
                            ?>
                            <tr>
                                <td class="admin-table-nowrap"><?php echo sr_e((string) ($index + 1)); ?></td>
                                <td class="admin-table-nowrap"><?php echo $questionKey !== '' ? '<code>' . sr_e($questionKey) . '</code>' : '<span class="admin-summary-meta">미등록</span>'; ?></td>
                                <td class="admin-table-break"><strong><?php echo sr_e($questionPrompt !== '' ? $questionPrompt : '문항 없음'); ?></strong></td>
                                <td class="admin-table-nowrap"><?php echo sr_e(sr_survey_question_type_label((string) ($question['question_type'] ?? 'single_choice'))); ?></td>
                                <td class="admin-table-nowrap"><span class="admin-status <?php echo (int) ($question['required'] ?? 1) === 1 ? 'is-normal' : 'is-left'; ?>"><?php echo (int) ($question['required'] ?? 1) === 1 ? '필수' : '선택'; ?></span></td>
                                <td class="admin-table-nowrap"><?php echo sr_e(number_format(count($questionChoices))); ?></td>
                                <td class="admin-table-actions-cell">
                                    <div class="admin-row-actions">
                                        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo $questionRegistered ? '문항 수정' : '문항 등록'; ?>" title="<?php echo $questionRegistered ? '수정' : '등록'; ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($questionModalId); ?>" data-overlay="#<?php echo sr_e($questionModalId); ?>"><?php echo sr_material_icon_html($questionRegistered ? 'edit' : 'add'); ?></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php foreach ($questionModalSlots as $index => $question): ?>
            <?php
            $questionModalId = 'survey-question-modal-' . (string) $index;
            $questionKey = trim((string) ($question['question_key'] ?? ''));
            $questionPrompt = trim((string) ($question['prompt'] ?? ''));
            $questionRegistered = $questionKey !== '' || $questionPrompt !== '';
            $choiceLines = array_map(static fn (array $choice): string => (string) ($choice['label'] ?? ''), array_values(array_filter((array) ($question['choices'] ?? []), static fn (array $choice): bool => (int) ($choice['is_other'] ?? 0) !== 1 && (int) ($choice['is_nonresponse'] ?? 0) !== 1)));
            ?>
            <div id="<?php echo sr_e($questionModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($questionModalId); ?>-label" aria-hidden="true" inert>
                <div class="modal-dialog modal-dialog-lg">
                    <div class="modal-content ui-form-theme">
                        <div class="modal-header">
                            <h3 id="<?php echo sr_e($questionModalId); ?>-label" class="modal-title form-label-help"><?php echo $surveyHelpButtonHtml('문항 관리', $surveyHelp['questions']['id']); ?><span><?php echo $questionRegistered ? '문항 ' . sr_e((string) ($index + 1)) . ' 수정' : '문항 등록'; ?></span></h3>
                            <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($questionModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                        </div>
                        <div class="modal-body">
                            <div class="form-row">
                                <?php echo sr_admin_form_label_help_html('question_type_' . (string) $index, '유형', $surveyHelp['question_type']['id'], $surveyHelpOpenLabel); ?>
                                <div class="form-field">
                                    <select id="question_type_<?php echo sr_e((string) $index); ?>" name="question_type[]" class="form-select">
                                        <?php foreach (sr_survey_question_types() as $type): ?>
                                            <option value="<?php echo sr_e($type); ?>"<?php echo (string) ($question['question_type'] ?? '') === $type ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_question_type_label($type)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <?php echo sr_admin_form_label_help_html('question_key_' . (string) $index, '문항 Key', $surveyHelp['question_key']['id'], $surveyHelpOpenLabel); ?>
                                <div class="form-field">
                                    <input id="question_key_<?php echo sr_e((string) $index); ?>" type="text" name="question_key[]" value="<?php echo sr_e((string) ($question['question_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input data-admin-key-suggest-source="#question_prompt_<?php echo sr_e((string) $index); ?>" data-admin-key-suggest-fallback="question_<?php echo sr_e((string) ($index + 1)); ?>" data-overlay-focus>
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label" for="question_prompt_<?php echo sr_e((string) $index); ?>">문항 내용</label>
                                <div class="form-field">
                                    <textarea id="question_prompt_<?php echo sr_e((string) $index); ?>" name="question_prompt[]" class="form-textarea" rows="3"><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></textarea>
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label" for="question_analysis_note_<?php echo sr_e((string) $index); ?>">분석 메모</label>
                                <div class="form-field">
                                    <textarea id="question_analysis_note_<?php echo sr_e((string) $index); ?>" name="question_analysis_note[]" class="form-textarea" rows="3"><?php echo sr_e((string) ($question['analysis_note'] ?? '')); ?></textarea>
                                    <p class="form-help">분석 코드북이나 품질 판정에 참고할 내부 메모입니다.</p>
                                </div>
                            </div>
                            <div class="form-row">
                                <span class="form-label">검증 옵션</span>
                                <div class="form-field admin-survey-question-option-grid">
                                    <label class="form-check form-label" for="question_required_<?php echo sr_e((string) $index); ?>">
                                        <input id="question_required_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_required[]" value="<?php echo sr_e((string) $index); ?>" class="form-switch form-switch-light"<?php echo (int) ($question['required'] ?? 1) === 1 ? ' checked' : ''; ?>>
                                        필수
                                    </label>
                                    <label class="form-check form-label" for="question_allow_decimal_<?php echo sr_e((string) $index); ?>">
                                        <input id="question_allow_decimal_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_allow_decimal[]" value="<?php echo sr_e((string) $index); ?>" class="form-switch form-switch-light"<?php echo (int) ($question['allow_decimal'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                        소수 허용
                                    </label>
                                    <label class="form-check form-label" for="question_allow_other_<?php echo sr_e((string) $index); ?>">
                                        <input id="question_allow_other_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_allow_other[]" value="<?php echo sr_e((string) $index); ?>" class="form-switch form-switch-light"<?php echo (int) ($question['allow_other'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                        기타 답변 허용
                                    </label>
                                </div>
                            </div>
                            <div class="form-row">
                                <span class="form-label">수치/선택 범위</span>
                                <div class="form-field admin-survey-question-option-grid">
                                    <input id="question_min_choices_<?php echo sr_e((string) $index); ?>" type="number" name="question_min_choices[]" value="<?php echo sr_e((string) ($question['min_choices'] ?? '')); ?>" class="form-input" min="0" placeholder="최소 선택 수">
                                    <input id="question_max_choices_<?php echo sr_e((string) $index); ?>" type="number" name="question_max_choices[]" value="<?php echo sr_e((string) ($question['max_choices'] ?? '')); ?>" class="form-input" min="0" placeholder="최대 선택 수">
                                    <input id="question_scale_points_<?php echo sr_e((string) $index); ?>" type="number" name="question_scale_points[]" value="<?php echo sr_e((string) ($question['scale_points'] ?? '')); ?>" class="form-input" min="2" max="11" placeholder="척도 단계">
                                    <input id="question_number_min_<?php echo sr_e((string) $index); ?>" type="number" name="question_number_min[]" value="<?php echo sr_e((string) ($question['number_min'] ?? '')); ?>" class="form-input" step="any" placeholder="최소값">
                                    <input id="question_number_max_<?php echo sr_e((string) $index); ?>" type="number" name="question_number_max[]" value="<?php echo sr_e((string) ($question['number_max'] ?? '')); ?>" class="form-input" step="any" placeholder="최대값">
                                </div>
                            </div>
                            <div class="form-row">
                                <span class="form-label">표시 라벨</span>
                                <div class="form-field admin-survey-question-option-grid">
                                    <input id="question_scale_min_label_<?php echo sr_e((string) $index); ?>" type="text" name="question_scale_min_label[]" value="<?php echo sr_e((string) ($question['scale_min_label'] ?? '')); ?>" class="form-input" maxlength="120" placeholder="낮은 값 라벨">
                                    <input id="question_scale_max_label_<?php echo sr_e((string) $index); ?>" type="text" name="question_scale_max_label[]" value="<?php echo sr_e((string) ($question['scale_max_label'] ?? '')); ?>" class="form-input" maxlength="120" placeholder="높은 값 라벨">
                                    <input id="question_number_unit_<?php echo sr_e((string) $index); ?>" type="text" name="question_number_unit[]" value="<?php echo sr_e((string) ($question['number_unit'] ?? '')); ?>" class="form-input" maxlength="60" placeholder="숫자 단위">
                                </div>
                            </div>
                            <div class="form-row">
                                <label class="form-label" for="question_nonresponse_policy_<?php echo sr_e((string) $index); ?>">무응답 옵션</label>
                                <div class="form-field">
                                    <select id="question_nonresponse_policy_<?php echo sr_e((string) $index); ?>" name="question_nonresponse_policy[]" class="form-select">
                                        <option value="none"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'none' ? ' selected' : ''; ?>>없음</option>
                                        <option value="allow_na"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_na' ? ' selected' : ''; ?>>해당 없음 허용</option>
                                        <option value="allow_unknown"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_unknown' ? ' selected' : ''; ?>>모름 허용</option>
                                        <option value="allow_refusal"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_refusal' ? ' selected' : ''; ?>>응답 거부 허용</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <span class="form-label form-label-help"><?php echo $surveyHelpButtonHtml('선택지', $surveyHelp['choices']['id']); ?><span>선택지</span></span>
                                <div class="form-field">
                                    <textarea id="choice_labels_<?php echo sr_e((string) $index); ?>" name="choice_labels[]" class="form-textarea" rows="6"><?php echo sr_e(implode("\n", $choiceLines)); ?></textarea>
                                    <p class="form-help">선택형 문항만 줄마다 하나씩 입력합니다.</p>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#<?php echo sr_e($questionModalId); ?>"><?php echo $questionRegistered ? '확인' : '등록'; ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/surveys')); ?>">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
    <?php if ((int) ($values['id'] ?? 0) > 0): ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="card admin-survey-delete-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="delete">
            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
            <div class="card-header">
                <h2 class="card-title">삭제</h2>
            </div>
            <div class="card-body">
                <p class="form-help">설문은 목록에서 숨겨지지만 기존 응답과 보상 이력은 보관됩니다.</p>
                <button type="submit" class="btn btn-outline-danger" data-confirm="<?php echo sr_e('설문을 삭제할까요? 기존 응답과 보상 이력은 보관됩니다.'); ?>"><?php echo sr_material_icon_html('delete'); ?>삭제</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php if (isset($surveyHelp) && is_array($surveyHelp)): ?>
    <?php foreach ($surveyHelp as $surveyHelpModal): ?>
        <?php echo sr_admin_help_modal_html((string) $surveyHelpModal['id'], (string) $surveyHelpModal['title'], (string) $surveyHelpModal['body_html']); ?>
    <?php endforeach; ?>
<?php endif; ?>
<?php
include SR_ROOT . '/modules/admin/views/layout-footer.php';
