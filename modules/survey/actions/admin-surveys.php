<?php

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/member/helpers/groups.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once __DIR__ . '/../helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/surveys', 'view');

$assetOptions = sr_survey_asset_options($pdo);
$couponDefinitions = sr_survey_coupon_definitions($pdo);
$memberGroups = sr_member_groups($pdo);
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
        $pdo->prepare('UPDATE sr_survey_forms SET deleted_at = :deleted_at, updated_at = :updated_at, updated_by_account_id = :account_id WHERE id = :id')->execute([
            'deleted_at' => sr_now(),
            'updated_at' => sr_now(),
            'account_id' => (int) ($account['id'] ?? 0),
            'id' => $surveyId,
        ]);
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
    $rewardEnabled = ($_POST['reward_enabled'] ?? '') === '1';
    $rewardProvider = sr_survey_clean_key(sr_post_string('reward_provider', 30), 30);
    $rewardModule = sr_survey_clean_key(sr_post_string('reward_module', 40), 40);
    $rewardCouponDefinitionId = (int) sr_post_string('reward_coupon_definition_id', 20);
    $rewardAmount = (int) sr_post_string('reward_amount', 20);
    $rewardDedupeScope = sr_survey_clean_key(sr_post_string('reward_dedupe_scope', 20), 20);

    if (!sr_survey_key_is_valid($surveyKey)) {
        $errors[] = '설문 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM sr_survey_forms WHERE survey_key = :survey_key AND id <> :id LIMIT 1');
        $stmt->execute(['survey_key' => $surveyKey, 'id' => $surveyId]);
        if (is_array($stmt->fetch())) {
            $errors[] = '이미 사용 중인 설문 key입니다.';
        }
    }
    if ($title === '') {
        $errors[] = '설문 제목을 입력하세요.';
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
            $errors[] = '문항 ' . (string) ($index + 1) . '의 key가 올바르지 않습니다.';
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

    if ($errors === []) {
        $now = sr_now();
        $pdo->beginTransaction();
        try {
            if ($surveyId > 0) {
                $existingSurvey = sr_survey_by_id($pdo, $surveyId);
                $nextQuestionnaireVersion = max(1, (int) ($existingSurvey['questionnaire_version'] ?? 1)) + 1;
                $pdo->prepare(
                    'UPDATE sr_survey_forms
                     SET survey_key = :survey_key, title = :title, description = :description,
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
                         reward_enabled = :reward_enabled,
                         updated_by_account_id = :updated_by_account_id, updated_at = :updated_at
                     WHERE id = :id AND deleted_at IS NULL'
                )->execute([
                    'survey_key' => $surveyKey,
                    'title' => $title,
                    'description' => $description,
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
                    'reward_enabled' => $rewardEnabled ? 1 : 0,
                    'updated_by_account_id' => (int) ($account['id'] ?? 0),
                    'updated_at' => $now,
                    'id' => $surveyId,
                ]);
            } else {
                $pdo->prepare(
                    'INSERT INTO sr_survey_forms
                        (survey_key, title, description, research_purpose, target_population, recruitment_method, estimated_minutes,
                         project_brief, sponsor_name, research_region, research_language, fieldwork_method, sample_frame, sample_method, target_sample_size,
                         quota_policy, response_rate_basis, analysis_plan, weighting_policy, margin_error_note, methodology_disclosure, ethics_note,
                         sensitive_data_policy, recontact_policy, withdrawal_policy, vendor_name, external_channel_policy, invite_token_policy,
                         qa_status, qa_note, questionnaire_version, revision_locked,
                         organizer_name, contact_text, consent_required, consent_text, privacy_notice, anonymous_allowed, login_required,
                         public_listed, robots_policy, status, starts_at, ends_at, response_limit_policy, response_limit_period_seconds, member_group_keys_json, reward_enabled,
                         created_by_account_id, updated_by_account_id, created_at, updated_at)
                     VALUES
                        (:survey_key, :title, :description, :research_purpose, :target_population, :recruitment_method, :estimated_minutes,
                         :project_brief, :sponsor_name, :research_region, :research_language, :fieldwork_method, :sample_frame, :sample_method, :target_sample_size,
                         :quota_policy, :response_rate_basis, :analysis_plan, :weighting_policy, :margin_error_note, :methodology_disclosure, :ethics_note,
                         :sensitive_data_policy, :recontact_policy, :withdrawal_policy, :vendor_name, :external_channel_policy, :invite_token_policy,
                         :qa_status, :qa_note, 1, :revision_locked,
                         :organizer_name, :contact_text, :consent_required, :consent_text, :privacy_notice, :anonymous_allowed, :login_required,
                         :public_listed, :robots_policy, :status, :starts_at, :ends_at, :response_limit_policy, :response_limit_period_seconds, :member_group_keys_json, :reward_enabled,
                         :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
                )->execute([
                    'survey_key' => $surveyKey,
                    'title' => $title,
                    'description' => $description,
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
                    'reward_enabled' => $rewardEnabled,
                ],
            ]);
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        sr_admin_redirect_with_result(sr_admin_action_result([], '설문을 저장했습니다.'), '/admin/surveys?mode=edit&id=' . (string) $surveyId);
    }
}

function sr_survey_replace_questions(PDO $pdo, int $surveyId, array $questions, string $now): void
{
    $oldStmt = $pdo->prepare('SELECT id FROM sr_survey_questions WHERE survey_id = :survey_id');
    $oldStmt->execute(['survey_id' => $surveyId]);
    $oldIds = array_map('intval', array_column($oldStmt->fetchAll(), 'id'));
    if ($oldIds !== []) {
        $pdo->exec('DELETE FROM sr_survey_choices WHERE question_id IN (' . implode(',', $oldIds) . ')');
    }
    $pdo->prepare('DELETE FROM sr_survey_questions WHERE survey_id = :survey_id')->execute(['survey_id' => $surveyId]);
    $questionStmt = $pdo->prepare(
        'INSERT INTO sr_survey_questions
            (survey_id, question_key, question_type, prompt, analysis_note, required, min_choices, max_choices, scale_points,
             scale_min_label, scale_max_label, number_unit, number_min, number_max, allow_decimal, allow_other, nonresponse_policy,
             sort_order, created_at, updated_at)
         VALUES
            (:survey_id, :question_key, :question_type, :prompt, :analysis_note, :required, :min_choices, :max_choices, :scale_points,
             :scale_min_label, :scale_max_label, :number_unit, :number_min, :number_max, :allow_decimal, :allow_other, :nonresponse_policy,
             :sort_order, :created_at, :updated_at)'
    );
    $choiceStmt = $pdo->prepare(
        'INSERT INTO sr_survey_choices
            (question_id, choice_key, label, sort_order, created_at, updated_at)
         VALUES
            (:question_id, :choice_key, :label, :sort_order, :created_at, :updated_at)'
    );
    foreach ($questions as $index => $question) {
        $questionStmt->execute([
            'survey_id' => $surveyId,
            'question_key' => (string) $question['question_key'],
            'question_type' => (string) $question['question_type'],
            'prompt' => (string) $question['prompt'],
            'analysis_note' => (string) ($question['analysis_note'] ?? ''),
            'required' => (int) $question['required'],
            'min_choices' => $question['min_choices'] ?? null,
            'max_choices' => $question['max_choices'] ?? null,
            'scale_points' => $question['scale_points'] ?? null,
            'scale_min_label' => (string) ($question['scale_min_label'] ?? ''),
            'scale_max_label' => (string) ($question['scale_max_label'] ?? ''),
            'number_unit' => (string) ($question['number_unit'] ?? ''),
            'number_min' => $question['number_min'] ?? null,
            'number_max' => $question['number_max'] ?? null,
            'allow_decimal' => (int) ($question['allow_decimal'] ?? 0),
            'allow_other' => (int) ($question['allow_other'] ?? 0),
            'nonresponse_policy' => in_array((string) ($question['nonresponse_policy'] ?? 'none'), ['none', 'allow_na', 'allow_unknown', 'allow_refusal'], true) ? (string) $question['nonresponse_policy'] : 'none',
            'sort_order' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $questionId = (int) $pdo->lastInsertId();
        foreach ((array) $question['choices'] as $choiceIndex => $choice) {
            $choiceStmt->execute([
                'question_id' => $questionId,
                'choice_key' => (string) $choice['choice_key'],
                'label' => (string) $choice['label'],
                'sort_order' => $choiceIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

function sr_survey_replace_reward_policy(PDO $pdo, int $surveyId, bool $enabled, string $provider, string $module, int $couponDefinitionId, int $amount, string $scope, string $now): void
{
    $pdo->prepare("UPDATE sr_survey_reward_policies SET status = 'disabled', updated_at = :updated_at WHERE survey_id = :survey_id")->execute([
        'updated_at' => $now,
        'survey_id' => $surveyId,
    ]);
    if (!$enabled) {
        return;
    }
    $rewardCode = $provider === 'coupon' ? (string) $couponDefinitionId : 'survey_reward';
    $rewardModule = $provider === 'coupon' ? 'coupon' : $module;
    $pdo->prepare(
        'INSERT INTO sr_survey_reward_policies
            (survey_id, status, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, created_at, updated_at)
         VALUES
            (:survey_id, \'active\', :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, 0, :created_at, :updated_at)'
    )->execute([
        'survey_id' => $surveyId,
        'reward_provider' => $provider,
        'reward_module' => $rewardModule,
        'reward_code' => $rewardCode,
        'reward_amount' => $provider === 'coupon' ? null : $amount,
        'dedupe_scope' => $scope,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

$flashResult = sr_request_method() === 'GET' ? sr_admin_pop_flash_result() : sr_admin_action_result($errors, $notice);
$errors = array_merge($errors, (array) ($flashResult['errors'] ?? []));
$notice = (string) ($flashResult['notice'] ?? $notice);
$mode = sr_get_string('mode', 20) === 'edit' ? 'edit' : (sr_get_string('mode', 20) === 'new' ? 'new' : 'list');
$editSurvey = $mode === 'edit' ? sr_survey_by_id($pdo, (int) sr_get_string('id', 20)) : null;
if ($mode === 'edit' && !is_array($editSurvey)) {
    sr_render_error(404, '설문을 찾을 수 없습니다.');
}
$editQuestions = is_array($editSurvey) ? sr_survey_questions_with_choices($pdo, (int) $editSurvey['id']) : [];
$editPolicy = is_array($editSurvey) ? sr_survey_active_reward_policy($pdo, (int) $editSurvey['id']) : null;

$adminPageTitle = $mode === 'list' ? '설문 관리' : ($mode === 'edit' ? '설문 수정' : '설문 생성');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($mode === 'list'): ?>
    <?php
    $stmt = $pdo->query(
        'SELECT s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.reward_enabled, s.updated_at, COUNT(r.id) AS response_count
         FROM sr_survey_forms s
         LEFT JOIN sr_survey_responses r ON r.survey_id = s.id
         WHERE s.deleted_at IS NULL
         GROUP BY s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.reward_enabled, s.updated_at
         ORDER BY s.updated_at DESC, s.id DESC
         LIMIT 200'
    );
    $surveys = $stmt->fetchAll();
    ?>
    <section class="admin-card card admin-list-card">
        <div class="card-header">
            <h2 class="card-title">설문 목록</h2>
            <div class="card-actions">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys?mode=new')); ?>">새 설문</a>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>Key</th>
                        <th>제목</th>
                        <th>상태</th>
                        <th>기간</th>
                        <th>대상</th>
                        <th>QA</th>
                        <th>응답</th>
                        <th>보상</th>
                        <th>수정일</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($surveys === []): ?>
                        <tr><td colspan="10" class="admin-empty-state">등록된 설문이 없습니다.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surveys as $survey): ?>
                        <tr>
                            <td><?php echo sr_e((string) $survey['survey_key']); ?></td>
                            <td><?php echo sr_e((string) $survey['title']); ?></td>
                            <td><?php echo sr_e(sr_survey_status_label((string) $survey['status'])); ?></td>
                            <td><?php echo sr_e(trim((string) ($survey['starts_at'] ?? '') . ' ~ ' . (string) ($survey['ends_at'] ?? ''))); ?></td>
                            <td><?php $listGroupKeys = sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]'); echo $listGroupKeys === [] ? '전체' : sr_e(implode(', ', $listGroupKeys)); ?></td>
                            <td><?php echo sr_e(sr_survey_qa_status_label((string) ($survey['qa_status'] ?? 'unchecked'))); ?></td>
                            <td><?php echo sr_e((string) (int) $survey['response_count']); ?></td>
                            <td><?php echo (int) $survey['reward_enabled'] === 1 ? '사용' : '미사용'; ?></td>
                            <td><?php echo sr_e((string) $survey['updated_at']); ?></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys?mode=edit&id=' . (string) (int) $survey['id'])); ?>">수정</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'])); ?>" target="_blank">보기</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php else: ?>
    <?php
    $values = is_array($editSurvey) ? $editSurvey : [
        ...sr_survey_settings($pdo),
        'id' => 0,
        'survey_key' => '',
        'title' => '',
        'description' => '',
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
    ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="admin-card card admin-form admin-survey-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save">
        <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
        <div class="card-header"><h2 class="card-title">기본 정보</h2></div>
        <div class="card-body">
            <div class="form-field">
                <label class="form-label" for="survey_key">설문 key <span class="required">(필수)</span></label>
                <input id="survey_key" type="text" name="survey_key" value="<?php echo sr_e((string) ($values['survey_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_title">제목 <span class="required">(필수)</span></label>
                <input id="survey_title" type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" class="form-input form-control-full" maxlength="190">
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_description">설명</label>
                <textarea id="survey_description" name="description" class="form-textarea"><?php echo sr_e((string) ($values['description'] ?? '')); ?></textarea>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_research_purpose">연구 목적</label>
                <textarea id="survey_research_purpose" name="research_purpose" class="form-textarea"><?php echo sr_e((string) ($values['research_purpose'] ?? '')); ?></textarea>
                <p class="admin-form-help">공개 화면과 응답 스냅샷에 남길 설문 목적입니다.</p>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_target_population">대상자</label>
                    <textarea id="survey_target_population" name="target_population" class="form-textarea"><?php echo sr_e((string) ($values['target_population'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_recruitment_method">모집 방법</label>
                    <textarea id="survey_recruitment_method" name="recruitment_method" class="form-textarea"><?php echo sr_e((string) ($values['recruitment_method'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_project_brief">프로젝트 개요</label>
                <textarea id="survey_project_brief" name="project_brief" class="form-textarea"><?php echo sr_e((string) ($values['project_brief'] ?? '')); ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_sponsor_name">의뢰/후원</label>
                    <input id="survey_sponsor_name" type="text" name="sponsor_name" value="<?php echo sr_e((string) ($values['sponsor_name'] ?? '')); ?>" class="form-input" maxlength="190">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_research_region">조사 지역</label>
                    <input id="survey_research_region" type="text" name="research_region" value="<?php echo sr_e((string) ($values['research_region'] ?? '')); ?>" class="form-input" maxlength="120">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_research_language">조사 언어</label>
                    <input id="survey_research_language" type="text" name="research_language" value="<?php echo sr_e((string) ($values['research_language'] ?? '')); ?>" class="form-input" maxlength="60">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_fieldwork_method">실사 방식</label>
                    <input id="survey_fieldwork_method" type="text" name="fieldwork_method" value="<?php echo sr_e((string) ($values['fieldwork_method'] ?? '')); ?>" class="form-input" maxlength="120">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_sample_method">표본 추출</label>
                    <input id="survey_sample_method" type="text" name="sample_method" value="<?php echo sr_e((string) ($values['sample_method'] ?? '')); ?>" class="form-input" maxlength="190">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_target_sample_size">목표 표본 수</label>
                    <input id="survey_target_sample_size" type="number" name="target_sample_size" value="<?php echo sr_e((string) ($values['target_sample_size'] ?? '')); ?>" class="form-input" min="0">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_sample_frame">표본틀</label>
                    <textarea id="survey_sample_frame" name="sample_frame" class="form-textarea"><?php echo sr_e((string) ($values['sample_frame'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_quota_policy">쿼터/마감 기준</label>
                    <textarea id="survey_quota_policy" name="quota_policy" class="form-textarea"><?php echo sr_e((string) ($values['quota_policy'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_response_rate_basis">응답률 산정 기준</label>
                <textarea id="survey_response_rate_basis" name="response_rate_basis" class="form-textarea"><?php echo sr_e((string) ($values['response_rate_basis'] ?? '')); ?></textarea>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_estimated_minutes">예상 소요 시간</label>
                    <input id="survey_estimated_minutes" type="number" name="estimated_minutes" value="<?php echo sr_e((string) ($values['estimated_minutes'] ?? '')); ?>" class="form-input" min="0" max="10080">
                    <p class="admin-form-help">분 단위로 입력합니다.</p>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_organizer_name">주관자</label>
                    <input id="survey_organizer_name" type="text" name="organizer_name" value="<?php echo sr_e((string) ($values['organizer_name'] ?? '')); ?>" class="form-input" maxlength="120">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_contact_text">문의처</label>
                    <input id="survey_contact_text" type="text" name="contact_text" value="<?php echo sr_e((string) ($values['contact_text'] ?? '')); ?>" class="form-input" maxlength="190">
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_status">상태 <span class="required">(필수)</span></label>
                <select id="survey_status" name="status" class="form-select">
                    <?php foreach (sr_survey_statuses() as $status): ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_status_label($status)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_starts_at">공개 시작일시</label>
                    <input id="survey_starts_at" type="datetime-local" name="starts_at" value="<?php echo sr_e(sr_survey_datetime_local_value($values['starts_at'] ?? '')); ?>" class="form-input">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_ends_at">공개 종료일시</label>
                    <input id="survey_ends_at" type="datetime-local" name="ends_at" value="<?php echo sr_e(sr_survey_datetime_local_value($values['ends_at'] ?? '')); ?>" class="form-input">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="admin-form-check form-label" for="survey_login_required">
                        <input id="survey_login_required" type="checkbox" name="login_required" value="1" class="form-checkbox"<?php echo (int) ($values['login_required'] ?? 1) === 1 ? ' checked' : ''; ?>>
                        로그인 필요
                    </label>
                    <p class="admin-form-help">보상 설문은 로그인 필요 상태에서만 저장됩니다.</p>
                </div>
                <div class="form-field">
                    <label class="admin-form-check form-label" for="survey_anonymous_allowed">
                        <input id="survey_anonymous_allowed" type="checkbox" name="anonymous_allowed" value="1" class="form-checkbox"<?php echo (int) ($values['anonymous_allowed'] ?? 0) === 1 ? ' checked' : ''; ?>>
                        익명 응답 허용
                    </label>
                </div>
                <div class="form-field">
                    <label class="admin-form-check form-label" for="survey_public_listed">
                        <input id="survey_public_listed" type="checkbox" name="public_listed" value="1" class="form-checkbox"<?php echo (int) ($values['public_listed'] ?? 1) === 1 ? ' checked' : ''; ?>>
                        공개 목록 노출
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label">참여 대상 회원 그룹</label>
                <div class="admin-checkbox-list">
                    <?php if ($memberGroups === []): ?>
                        <p class="admin-form-help">선택 가능한 회원 그룹이 없습니다.</p>
                    <?php endif; ?>
                    <?php foreach ($memberGroups as $memberGroup): ?>
                        <?php $groupKey = (string) ($memberGroup['group_key'] ?? ''); ?>
                        <label class="admin-form-check form-label">
                            <input type="checkbox" name="member_group_keys[]" value="<?php echo sr_e($groupKey); ?>" class="form-checkbox"<?php echo in_array($groupKey, $selectedMemberGroupKeys, true) ? ' checked' : ''; ?><?php echo (string) ($memberGroup['status'] ?? '') === 'enabled' ? '' : ' disabled'; ?>>
                            <?php echo sr_e((string) ($memberGroup['title'] ?? $groupKey)); ?> (<?php echo sr_e($groupKey); ?>)
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="admin-form-help">선택하면 해당 그룹에 속한 로그인 회원만 참여할 수 있습니다.</p>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_response_limit_policy">응답 제한</label>
                    <select id="survey_response_limit_policy" name="response_limit_policy" class="form-select">
                        <?php foreach (sr_survey_response_limit_policies() as $limitPolicy): ?>
                            <option value="<?php echo sr_e($limitPolicy); ?>"<?php echo (string) ($values['response_limit_policy'] ?? 'per_survey_once') === $limitPolicy ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_response_limit_policy_label($limitPolicy)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_response_limit_period_seconds">제한 기간</label>
                    <input id="survey_response_limit_period_seconds" type="number" name="response_limit_period_seconds" value="<?php echo sr_e((string) ($values['response_limit_period_seconds'] ?? '')); ?>" class="form-input" min="0">
                    <p class="admin-form-help">기간당 1회 제한일 때 초 단위로 입력합니다.</p>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_robots_policy">검색 로봇</label>
                    <select id="survey_robots_policy" name="robots_policy" class="form-select">
                        <option value="auto"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'auto' ? ' selected' : ''; ?>>자동</option>
                        <option value="index"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'index' ? ' selected' : ''; ?>>색인 허용</option>
                        <option value="noindex"<?php echo (string) ($values['robots_policy'] ?? 'auto') === 'noindex' ? ' selected' : ''; ?>>색인 제외</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-header"><h2 class="card-title">분석·공표·윤리</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_analysis_plan">분석 계획</label>
                    <textarea id="survey_analysis_plan" name="analysis_plan" class="form-textarea"><?php echo sr_e((string) ($values['analysis_plan'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_weighting_policy">가중치 기준</label>
                    <textarea id="survey_weighting_policy" name="weighting_policy" class="form-textarea"><?php echo sr_e((string) ($values['weighting_policy'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_margin_error_note">오차/한계</label>
                    <textarea id="survey_margin_error_note" name="margin_error_note" class="form-textarea"><?php echo sr_e((string) ($values['margin_error_note'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_methodology_disclosure">방법론 공표 문구</label>
                    <textarea id="survey_methodology_disclosure" name="methodology_disclosure" class="form-textarea"><?php echo sr_e((string) ($values['methodology_disclosure'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_ethics_note">윤리 검토</label>
                    <textarea id="survey_ethics_note" name="ethics_note" class="form-textarea"><?php echo sr_e((string) ($values['ethics_note'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_sensitive_data_policy">민감정보 기준</label>
                    <textarea id="survey_sensitive_data_policy" name="sensitive_data_policy" class="form-textarea"><?php echo sr_e((string) ($values['sensitive_data_policy'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_recontact_policy">재연락 기준</label>
                    <textarea id="survey_recontact_policy" name="recontact_policy" class="form-textarea"><?php echo sr_e((string) ($values['recontact_policy'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_withdrawal_policy">철회 기준</label>
                    <textarea id="survey_withdrawal_policy" name="withdrawal_policy" class="form-textarea"><?php echo sr_e((string) ($values['withdrawal_policy'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_vendor_name">외부 패널/벤더</label>
                    <input id="survey_vendor_name" type="text" name="vendor_name" value="<?php echo sr_e((string) ($values['vendor_name'] ?? '')); ?>" class="form-input" maxlength="190">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_external_channel_policy">외부 채널 기준</label>
                    <textarea id="survey_external_channel_policy" name="external_channel_policy" class="form-textarea"><?php echo sr_e((string) ($values['external_channel_policy'] ?? '')); ?></textarea>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_invite_token_policy">초대 토큰 기준</label>
                    <textarea id="survey_invite_token_policy" name="invite_token_policy" class="form-textarea"><?php echo sr_e((string) ($values['invite_token_policy'] ?? '')); ?></textarea>
                </div>
            </div>
        </div>
        <div class="card-header"><h2 class="card-title">QA·버전</h2></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_qa_status">QA 상태</label>
                    <select id="survey_qa_status" name="qa_status" class="form-select">
                        <?php foreach (sr_survey_qa_statuses() as $statusKey): ?>
                            <option value="<?php echo sr_e($statusKey); ?>"<?php echo (string) ($values['qa_status'] ?? 'unchecked') === $statusKey ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_qa_status_label($statusKey)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">설문지 버전</label>
                    <p class="admin-form-help"><?php echo sr_e((string) (int) ($values['questionnaire_version'] ?? 1)); ?></p>
                </div>
                <div class="form-field">
                    <label class="admin-form-check form-label" for="survey_revision_locked">
                        <input id="survey_revision_locked" type="checkbox" name="revision_locked" value="1" class="form-checkbox"<?php echo (int) ($values['revision_locked'] ?? 0) === 1 ? ' checked' : ''; ?>>
                        설문지 잠금
                    </label>
                </div>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_qa_note">QA 메모</label>
                <textarea id="survey_qa_note" name="qa_note" class="form-textarea"><?php echo sr_e((string) ($values['qa_note'] ?? '')); ?></textarea>
            </div>
        </div>
        <div class="card-header"><h2 class="card-title">참여 동의</h2></div>
        <div class="card-body">
            <label class="admin-form-check form-label" for="survey_consent_required">
                <input id="survey_consent_required" type="checkbox" name="consent_required" value="1" class="form-checkbox"<?php echo (int) ($values['consent_required'] ?? 0) === 1 ? ' checked' : ''; ?>>
                동의 필요
            </label>
            <div class="form-field">
                <label class="form-label" for="survey_consent_text">동의 문구</label>
                <textarea id="survey_consent_text" name="consent_text" class="form-textarea"><?php echo sr_e((string) ($values['consent_text'] ?? '')); ?></textarea>
            </div>
            <div class="form-field">
                <label class="form-label" for="survey_privacy_notice">개인정보 안내</label>
                <textarea id="survey_privacy_notice" name="privacy_notice" class="form-textarea"><?php echo sr_e((string) ($values['privacy_notice'] ?? '')); ?></textarea>
            </div>
        </div>
        <div class="card-header"><h2 class="card-title">보상</h2></div>
        <div class="card-body">
            <label class="admin-form-check form-label" for="survey_reward_enabled">
                <input id="survey_reward_enabled" type="checkbox" name="reward_enabled" value="1" class="form-checkbox"<?php echo (int) ($values['reward_enabled'] ?? 0) === 1 ? ' checked' : ''; ?>>
                보상 사용
            </label>
            <div class="form-grid">
                <div class="form-field">
                    <label class="form-label" for="survey_reward_provider">보상 공급자</label>
                    <?php $policyProvider = is_array($editPolicy) ? (string) ($editPolicy['reward_provider'] ?? 'ledger_asset') : 'ledger_asset'; ?>
                    <select id="survey_reward_provider" name="reward_provider" class="form-select">
                        <option value="ledger_asset"<?php echo $policyProvider === 'ledger_asset' ? ' selected' : ''; ?>>포인트/금액</option>
                        <option value="coupon"<?php echo $policyProvider === 'coupon' ? ' selected' : ''; ?>>쿠폰</option>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_reward_module">보상 자산</label>
                    <select id="survey_reward_module" name="reward_module" class="form-select">
                        <option value="">선택</option>
                        <?php foreach ($assetOptions as $moduleKey => $asset): ?>
                            <option value="<?php echo sr_e((string) $moduleKey); ?>"<?php echo is_array($editPolicy) && (string) ($editPolicy['reward_module'] ?? '') === (string) $moduleKey ? ' selected' : ''; ?>><?php echo sr_e((string) ($asset['label'] ?? $moduleKey)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_reward_amount">보상 금액</label>
                    <input id="survey_reward_amount" type="number" name="reward_amount" value="<?php echo sr_e((string) (int) (is_array($editPolicy) ? ($editPolicy['reward_amount'] ?? 0) : 0)); ?>" class="form-input" min="0">
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_reward_coupon_definition_id">보상 쿠폰</label>
                    <select id="survey_reward_coupon_definition_id" name="reward_coupon_definition_id" class="form-select">
                        <option value="">선택</option>
                        <?php foreach ($couponDefinitions as $definition): ?>
                            <option value="<?php echo sr_e((string) (int) $definition['id']); ?>"<?php echo is_array($editPolicy) && (string) ($editPolicy['reward_code'] ?? '') === (string) (int) $definition['id'] ? ' selected' : ''; ?>><?php echo sr_e((string) $definition['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label" for="survey_reward_dedupe_scope">중복 지급 기준</label>
                    <?php $policyScope = is_array($editPolicy) ? (string) ($editPolicy['dedupe_scope'] ?? 'per_survey') : 'per_survey'; ?>
                    <select id="survey_reward_dedupe_scope" name="reward_dedupe_scope" class="form-select">
                        <option value="per_survey"<?php echo $policyScope === 'per_survey' ? ' selected' : ''; ?>>설문당 1회</option>
                        <option value="per_response"<?php echo $policyScope === 'per_response' ? ' selected' : ''; ?>>응답마다</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-header"><h2 class="card-title">문항</h2></div>
        <div class="card-body">
            <?php for ($index = 0; $index < 10; $index++): ?>
                <?php $question = is_array($editQuestions[$index] ?? null) ? $editQuestions[$index] : ['question_key' => '', 'question_type' => 'single_choice', 'prompt' => '', 'analysis_note' => '', 'required' => 1, 'min_choices' => null, 'max_choices' => null, 'scale_points' => null, 'scale_min_label' => '', 'scale_max_label' => '', 'number_unit' => '', 'number_min' => null, 'number_max' => null, 'allow_decimal' => 0, 'allow_other' => 0, 'nonresponse_policy' => 'none', 'choices' => []]; ?>
                <div class="admin-survey-question-row">
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="question_key_<?php echo sr_e((string) $index); ?>">문항 key</label>
                            <input id="question_key_<?php echo sr_e((string) $index); ?>" type="text" name="question_key[]" value="<?php echo sr_e((string) ($question['question_key'] ?? '')); ?>" class="form-input" maxlength="64" pattern="[a-z][a-z0-9_]{1,63}" data-admin-key-input>
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_type_<?php echo sr_e((string) $index); ?>">유형</label>
                            <select id="question_type_<?php echo sr_e((string) $index); ?>" name="question_type[]" class="form-select">
                                <?php foreach (sr_survey_question_types() as $type): ?>
                                    <option value="<?php echo sr_e($type); ?>"<?php echo (string) ($question['question_type'] ?? '') === $type ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_question_type_label($type)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <label class="admin-form-check form-label" for="question_required_<?php echo sr_e((string) $index); ?>">
                                <input id="question_required_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_required[]" value="<?php echo sr_e((string) $index); ?>" class="form-checkbox"<?php echo (int) ($question['required'] ?? 1) === 1 ? ' checked' : ''; ?>>
                                필수
                            </label>
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="question_prompt_<?php echo sr_e((string) $index); ?>">문항 내용</label>
                        <textarea id="question_prompt_<?php echo sr_e((string) $index); ?>" name="question_prompt[]" class="form-textarea"><?php echo sr_e((string) ($question['prompt'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="question_analysis_note_<?php echo sr_e((string) $index); ?>">분석 메모</label>
                        <textarea id="question_analysis_note_<?php echo sr_e((string) $index); ?>" name="question_analysis_note[]" class="form-textarea"><?php echo sr_e((string) ($question['analysis_note'] ?? '')); ?></textarea>
                        <p class="admin-form-help">분석 코드북이나 품질 판정에 참고할 내부 메모입니다.</p>
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="question_min_choices_<?php echo sr_e((string) $index); ?>">최소 선택 수</label>
                            <input id="question_min_choices_<?php echo sr_e((string) $index); ?>" type="number" name="question_min_choices[]" value="<?php echo sr_e((string) ($question['min_choices'] ?? '')); ?>" class="form-input" min="0">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_max_choices_<?php echo sr_e((string) $index); ?>">최대 선택 수</label>
                            <input id="question_max_choices_<?php echo sr_e((string) $index); ?>" type="number" name="question_max_choices[]" value="<?php echo sr_e((string) ($question['max_choices'] ?? '')); ?>" class="form-input" min="0">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_scale_points_<?php echo sr_e((string) $index); ?>">척도 단계</label>
                            <input id="question_scale_points_<?php echo sr_e((string) $index); ?>" type="number" name="question_scale_points[]" value="<?php echo sr_e((string) ($question['scale_points'] ?? '')); ?>" class="form-input" min="2" max="11">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="question_scale_min_label_<?php echo sr_e((string) $index); ?>">낮은 값 라벨</label>
                            <input id="question_scale_min_label_<?php echo sr_e((string) $index); ?>" type="text" name="question_scale_min_label[]" value="<?php echo sr_e((string) ($question['scale_min_label'] ?? '')); ?>" class="form-input" maxlength="120">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_scale_max_label_<?php echo sr_e((string) $index); ?>">높은 값 라벨</label>
                            <input id="question_scale_max_label_<?php echo sr_e((string) $index); ?>" type="text" name="question_scale_max_label[]" value="<?php echo sr_e((string) ($question['scale_max_label'] ?? '')); ?>" class="form-input" maxlength="120">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_number_unit_<?php echo sr_e((string) $index); ?>">숫자 단위</label>
                            <input id="question_number_unit_<?php echo sr_e((string) $index); ?>" type="text" name="question_number_unit[]" value="<?php echo sr_e((string) ($question['number_unit'] ?? '')); ?>" class="form-input" maxlength="60">
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="form-label" for="question_number_min_<?php echo sr_e((string) $index); ?>">최소값</label>
                            <input id="question_number_min_<?php echo sr_e((string) $index); ?>" type="number" name="question_number_min[]" value="<?php echo sr_e((string) ($question['number_min'] ?? '')); ?>" class="form-input" step="any">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_number_max_<?php echo sr_e((string) $index); ?>">최대값</label>
                            <input id="question_number_max_<?php echo sr_e((string) $index); ?>" type="number" name="question_number_max[]" value="<?php echo sr_e((string) ($question['number_max'] ?? '')); ?>" class="form-input" step="any">
                        </div>
                        <div class="form-field">
                            <label class="form-label" for="question_nonresponse_policy_<?php echo sr_e((string) $index); ?>">무응답 옵션</label>
                            <select id="question_nonresponse_policy_<?php echo sr_e((string) $index); ?>" name="question_nonresponse_policy[]" class="form-select">
                                <option value="none"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'none' ? ' selected' : ''; ?>>없음</option>
                                <option value="allow_na"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_na' ? ' selected' : ''; ?>>해당 없음 허용</option>
                                <option value="allow_unknown"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_unknown' ? ' selected' : ''; ?>>모름 허용</option>
                                <option value="allow_refusal"<?php echo (string) ($question['nonresponse_policy'] ?? 'none') === 'allow_refusal' ? ' selected' : ''; ?>>응답 거부 허용</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-field">
                            <label class="admin-form-check form-label" for="question_allow_decimal_<?php echo sr_e((string) $index); ?>">
                                <input id="question_allow_decimal_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_allow_decimal[]" value="<?php echo sr_e((string) $index); ?>" class="form-checkbox"<?php echo (int) ($question['allow_decimal'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                소수 허용
                            </label>
                        </div>
                        <div class="form-field">
                            <label class="admin-form-check form-label" for="question_allow_other_<?php echo sr_e((string) $index); ?>">
                                <input id="question_allow_other_<?php echo sr_e((string) $index); ?>" type="checkbox" name="question_allow_other[]" value="<?php echo sr_e((string) $index); ?>" class="form-checkbox"<?php echo (int) ($question['allow_other'] ?? 0) === 1 ? ' checked' : ''; ?>>
                                기타 답변 허용
                            </label>
                        </div>
                    </div>
                    <div class="form-field">
                        <label class="form-label" for="choice_labels_<?php echo sr_e((string) $index); ?>">선택지</label>
                        <?php $choiceLines = array_map(static fn (array $choice): string => (string) ($choice['label'] ?? ''), (array) ($question['choices'] ?? [])); ?>
                        <textarea id="choice_labels_<?php echo sr_e((string) $index); ?>" name="choice_labels[]" class="form-textarea"><?php echo sr_e(implode("\n", $choiceLines)); ?></textarea>
                        <p class="admin-form-help">선택형 문항만 줄마다 하나씩 입력합니다.</p>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
        <div class="card-footer form-actions">
            <button type="submit" class="btn btn-solid-primary">저장</button>
            <a class="btn btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/surveys')); ?>">목록</a>
        </div>
    </form>
    <?php if ((int) ($values['id'] ?? 0) > 0): ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/surveys')); ?>" class="admin-card card admin-form">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="delete">
            <input type="hidden" name="survey_id" value="<?php echo sr_e((string) (int) ($values['id'] ?? 0)); ?>">
            <button type="submit" class="btn btn-outline-danger">삭제</button>
        </form>
    <?php endif; ?>
<?php endif; ?>
<?php
include SR_ROOT . '/modules/admin/views/layout-footer.php';
