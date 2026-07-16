<?php

declare(strict_types=1);

require_once __DIR__ . '/groups.php';

function sr_survey_admin_list_count(PDO $pdo, array $where, array $params): int
{
    if ($where === []) {
        $where = ['1 = 1'];
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_survey_forms s
         WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);

    return max(0, (int) $stmt->fetchColumn());
}

function sr_survey_admin_list_rows(PDO $pdo, array $where, array $params, string $orderSql, int $limit = 100, int $offset = 0): array
{
    if ($where === []) {
        $where = ['1 = 1'];
    }
    $limit = max(1, min(200, $limit));
    $offset = max(0, $offset);
    $groupSchemaAvailable = sr_survey_groups_table_exists($pdo);
    $groupSelectSql = $groupSchemaAvailable ? 's.survey_group_id, sg.title AS survey_group_title,' : '0 AS survey_group_id, \'\' AS survey_group_title,';
    $groupJoinSql = $groupSchemaAvailable ? ' LEFT JOIN sr_survey_groups sg ON sg.id = s.survey_group_id' : '';
    $groupBySql = $groupSchemaAvailable ? ', s.survey_group_id, sg.title' : '';
    $stmt = $pdo->prepare(
        'SELECT s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at, s.deleted_at, ' . $groupSelectSql . '
                COUNT(r.id) AS response_count,
                COALESCE(srg.reward_grant_count, 0) AS reward_grant_count,
                COALESCE(sscf.cleanup_pending_count, 0) AS cleanup_pending_count
         FROM sr_survey_forms s
         ' . $groupJoinSql . '
         LEFT JOIN sr_survey_responses r ON r.survey_id = s.id
         LEFT JOIN (
             SELECT survey_id, COUNT(*) AS reward_grant_count
             FROM sr_survey_reward_grants
             GROUP BY survey_id
         ) srg ON srg.survey_id = s.id
         LEFT JOIN (
             SELECT source_id, COUNT(*) AS cleanup_pending_count
             FROM sr_survey_storage_cleanup_failures
             WHERE status <> \'cleaned\'
             GROUP BY source_id
         ) sscf ON sscf.source_id = s.id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY s.id, s.survey_key, s.title, s.status, s.starts_at, s.ends_at, s.qa_status, s.member_group_keys_json, s.view_count, s.reward_enabled, s.updated_at, s.deleted_at' . $groupBySql . ', srg.reward_grant_count, sscf.cleanup_pending_count
         ' . $orderSql . '
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_survey_admin_list_state(PDO $pdo): array
{
    $listStatus = sr_survey_clean_key(sr_get_string('status', 30), 30);
    if ($listStatus !== '' && !in_array($listStatus, sr_survey_statuses(), true)) {
        $listStatus = '';
    }
    $listAvailability = sr_survey_clean_key(sr_get_string('availability', 30), 30);
    if (!in_array($listAvailability, ['open', 'closed'], true)) {
        $listAvailability = '';
    }
    $listKeyword = sr_survey_clean_single_line(sr_get_string('q', 120), 120);
    $deletedView = sr_get_string('deleted', 1) === '1';
    $listWhere = [$deletedView ? 's.deleted_at IS NOT NULL' : 's.deleted_at IS NULL'];
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

    $pagination = sr_admin_pagination_from_total($pdo, sr_survey_admin_list_count($pdo, $listWhere, $listParams));

    return [
        'list_status' => $listStatus,
        'list_availability' => $listAvailability,
        'list_keyword' => $listKeyword,
        'deleted_view' => $deletedView,
        'filter_open' => $listStatus !== '' || $listAvailability !== '',
        'status_options' => $surveyStatusOptions,
        'sort_options' => $surveySortOptions,
        'default_sort' => $surveyDefaultSort,
        'sort' => $surveySort,
        'pagination' => $pagination,
        'surveys' => sr_survey_admin_list_rows(
            $pdo,
            $listWhere,
            $listParams,
            $surveyOrderSql,
            (int) $pagination['per_page'],
            sr_admin_pagination_offset($pagination)
        ),
    ];
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
        $extraSortOrder = count((array) $question['choices']);
        if ((int) ($question['allow_other'] ?? 0) === 1 && in_array((string) $question['question_type'], ['single_choice', 'multiple_choice'], true)) {
            $pdo->prepare(
                'INSERT INTO sr_survey_choices
                    (question_id, choice_key, label, is_other, sort_order, created_at, updated_at)
                 VALUES
                    (:question_id, \'other\', \'기타\', 1, :sort_order, :created_at, :updated_at)'
            )->execute([
                'question_id' => $questionId,
                'sort_order' => $extraSortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $extraSortOrder++;
        }
        $nonresponseLabels = [
            'allow_na' => '해당 없음',
            'allow_unknown' => '모름',
            'allow_refusal' => '응답하지 않음',
        ];
        $nonresponsePolicy = (string) ($question['nonresponse_policy'] ?? 'none');
        if (isset($nonresponseLabels[$nonresponsePolicy]) && in_array((string) $question['question_type'], ['single_choice', 'multiple_choice'], true)) {
            $pdo->prepare(
                'INSERT INTO sr_survey_choices
                    (question_id, choice_key, label, is_nonresponse, sort_order, created_at, updated_at)
                 VALUES
                    (:question_id, :choice_key, :label, 1, :sort_order, :created_at, :updated_at)'
            )->execute([
                'question_id' => $questionId,
                'choice_key' => str_replace('allow_', '', $nonresponsePolicy),
                'label' => $nonresponseLabels[$nonresponsePolicy],
                'sort_order' => $extraSortOrder,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

function sr_survey_admin_question_signature(array $questions): string
{
    $signature = [];
    foreach ($questions as $question) {
        $choices = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            if ((int) ($choice['is_other'] ?? 0) === 1 || (int) ($choice['is_nonresponse'] ?? 0) === 1) {
                continue;
            }
            $choices[] = [
                'choice_key' => (string) ($choice['choice_key'] ?? ''),
                'label' => (string) ($choice['label'] ?? ''),
            ];
        }
        $signature[] = [
            'question_key' => (string) ($question['question_key'] ?? ''),
            'question_type' => (string) ($question['question_type'] ?? ''),
            'prompt' => (string) ($question['prompt'] ?? ''),
            'analysis_note' => (string) ($question['analysis_note'] ?? ''),
            'required' => (int) ($question['required'] ?? 0),
            'min_choices' => $question['min_choices'] === null ? null : (int) $question['min_choices'],
            'max_choices' => $question['max_choices'] === null ? null : (int) $question['max_choices'],
            'scale_points' => $question['scale_points'] === null ? null : (int) $question['scale_points'],
            'scale_min_label' => (string) ($question['scale_min_label'] ?? ''),
            'scale_max_label' => (string) ($question['scale_max_label'] ?? ''),
            'number_unit' => (string) ($question['number_unit'] ?? ''),
            'number_min' => sr_survey_admin_number_signature_value($question['number_min'] ?? null),
            'number_max' => sr_survey_admin_number_signature_value($question['number_max'] ?? null),
            'allow_decimal' => (int) ($question['allow_decimal'] ?? 0),
            'allow_other' => (int) ($question['allow_other'] ?? 0),
            'nonresponse_policy' => (string) ($question['nonresponse_policy'] ?? 'none'),
            'choices' => $choices,
        ];
    }
    $json = json_encode($signature, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($json) ? $json : '';
}

function sr_survey_admin_questions_from_draft_payload(array $payload): array
{
    $questionKeys = is_array($payload['question_key'] ?? null) ? array_values($payload['question_key']) : [];
    $questionTypes = is_array($payload['question_type'] ?? null) ? array_values($payload['question_type']) : [];
    $questionPrompts = is_array($payload['question_prompt'] ?? null) ? array_values($payload['question_prompt']) : [];
    $questionAnalysisNotes = is_array($payload['question_analysis_note'] ?? null) ? array_values($payload['question_analysis_note']) : [];
    $questionMinChoices = is_array($payload['question_min_choices'] ?? null) ? array_values($payload['question_min_choices']) : [];
    $questionMaxChoices = is_array($payload['question_max_choices'] ?? null) ? array_values($payload['question_max_choices']) : [];
    $questionScalePoints = is_array($payload['question_scale_points'] ?? null) ? array_values($payload['question_scale_points']) : [];
    $questionScaleMinLabels = is_array($payload['question_scale_min_label'] ?? null) ? array_values($payload['question_scale_min_label']) : [];
    $questionScaleMaxLabels = is_array($payload['question_scale_max_label'] ?? null) ? array_values($payload['question_scale_max_label']) : [];
    $questionNumberUnits = is_array($payload['question_number_unit'] ?? null) ? array_values($payload['question_number_unit']) : [];
    $questionNumberMins = is_array($payload['question_number_min'] ?? null) ? array_values($payload['question_number_min']) : [];
    $questionNumberMaxes = is_array($payload['question_number_max'] ?? null) ? array_values($payload['question_number_max']) : [];
    $questionNonresponsePolicies = is_array($payload['question_nonresponse_policy'] ?? null) ? array_values($payload['question_nonresponse_policy']) : [];
    $choiceLabels = is_array($payload['choice_labels'] ?? null) ? array_values($payload['choice_labels']) : [];
    $requiredIndexes = is_array($payload['question_required'] ?? null) ? array_map('strval', $payload['question_required']) : [];
    $decimalIndexes = is_array($payload['question_allow_decimal'] ?? null) ? array_map('strval', $payload['question_allow_decimal']) : [];
    $otherIndexes = is_array($payload['question_allow_other'] ?? null) ? array_map('strval', $payload['question_allow_other']) : [];
    $questions = [];

    foreach (array_slice($questionKeys, 0, 200) as $index => $questionKey) {
        $prompt = (string) ($questionPrompts[$index] ?? '');
        if (trim((string) $questionKey) === '' && trim($prompt) === '') {
            continue;
        }
        $choices = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) ($choiceLabels[$index] ?? '')) ?: [] as $choiceLabel) {
            if (trim((string) $choiceLabel) !== '') {
                $choices[] = ['label' => (string) $choiceLabel];
            }
        }
        $questions[] = [
            'question_key' => (string) $questionKey,
            'question_type' => (string) ($questionTypes[$index] ?? 'single_choice'),
            'prompt' => $prompt,
            'analysis_note' => (string) ($questionAnalysisNotes[$index] ?? ''),
            'required' => in_array((string) $index, $requiredIndexes, true) ? 1 : 0,
            'min_choices' => trim((string) ($questionMinChoices[$index] ?? '')) === '' ? null : (string) $questionMinChoices[$index],
            'max_choices' => trim((string) ($questionMaxChoices[$index] ?? '')) === '' ? null : (string) $questionMaxChoices[$index],
            'scale_points' => trim((string) ($questionScalePoints[$index] ?? '')) === '' ? null : (string) $questionScalePoints[$index],
            'scale_min_label' => (string) ($questionScaleMinLabels[$index] ?? ''),
            'scale_max_label' => (string) ($questionScaleMaxLabels[$index] ?? ''),
            'number_unit' => (string) ($questionNumberUnits[$index] ?? ''),
            'number_min' => trim((string) ($questionNumberMins[$index] ?? '')) === '' ? null : (string) $questionNumberMins[$index],
            'number_max' => trim((string) ($questionNumberMaxes[$index] ?? '')) === '' ? null : (string) $questionNumberMaxes[$index],
            'allow_decimal' => in_array((string) $index, $decimalIndexes, true) ? 1 : 0,
            'allow_other' => in_array((string) $index, $otherIndexes, true) ? 1 : 0,
            'nonresponse_policy' => (string) ($questionNonresponsePolicies[$index] ?? 'none'),
            'choices' => $choices,
        ];
    }

    return $questions;
}

function sr_survey_admin_number_signature_value(mixed $value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }
    if (!is_numeric((string) $value)) {
        return (string) $value;
    }

    return rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.');
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

function sr_survey_admin_handle_save_post(PDO $pdo, array $account, array $assetOptions, array $enabledMemberGroupKeys, ?callable $afterSave = null): void
{
    $errors = [];
    $surveyId = (int) sr_post_string('survey_id', 20);
    $surveyGroupId = max(0, (int) sr_post_string('survey_group_id', 20));
    $settingSources = [];
    foreach (array_keys(sr_survey_group_setting_bundles()) as $settingKey) {
        $settingSources[$settingKey] = sr_survey_setting_scope(sr_post_string('source_' . $settingKey, 20));
    }
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
    $commentExtraFieldsInput = sr_post_string_without_truncation('comment_extra_fields_json', 20000);
    $commentExtraFieldsInput = is_string($commentExtraFieldsInput) ? $commentExtraFieldsInput : '[]';
    $reactionPresetKey = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_preset_key', 80)) : '';
    $reactionCommentPresetKey = sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, sr_post_string('reaction_comment_preset_key', 80)) : '';
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
    if ($surveyGroupId > 0 && !is_array(sr_survey_group_by_id($pdo, $surveyGroupId))) {
        $errors[] = '설문 그룹을 확인하세요.';
    }
    foreach ($settingSources as $settingSource) {
        if ($settingSource === 'group' && $surveyGroupId < 1) {
            $errors[] = '그룹 적용을 사용하려면 설문 그룹을 선택하세요.';
            break;
        }
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
    $errors = array_merge($errors, sr_comment_extra_field_definition_errors($commentExtraFieldsInput));
    if ($rewardEnabled) {
        if (!in_array($rewardProvider, sr_survey_reward_providers(), true)) {
            $errors[] = '보상 종류 값이 올바르지 않습니다.';
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
        $surveyValues = [
            'survey_group_id' => $surveyGroupId > 0 ? $surveyGroupId : null,
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
            'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($commentExtraFieldsInput),
            'reaction_preset_key' => $reactionPresetKey,
            'reaction_comment_preset_key' => $reactionCommentPresetKey,
            'reward_enabled' => $rewardEnabled ? 1 : 0,
            'updated_by_account_id' => (int) ($account['id'] ?? 0),
        ];
        $pdo->beginTransaction();
        try {
            if ($surveyId > 0) {
                $existingSurvey = is_array($existingSurveyForSave) ? $existingSurveyForSave : sr_survey_by_id($pdo, $surveyId);
                $nextQuestionnaireVersion = max(1, (int) ($existingSurvey['questionnaire_version'] ?? 1)) + 1;
                $pdo->prepare(
                    'UPDATE sr_survey_forms
                     SET survey_group_id = :survey_group_id, survey_key = :survey_key, title = :title, description = :description,
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
                         comment_extra_fields_json = :comment_extra_fields_json,
                         reaction_preset_key = :reaction_preset_key, reaction_comment_preset_key = :reaction_comment_preset_key,
                         reward_enabled = :reward_enabled,
                         updated_by_account_id = :updated_by_account_id, updated_at = :updated_at
                     WHERE id = :id AND deleted_at IS NULL'
                )->execute(array_merge($surveyValues, [
                    'questionnaire_version' => $nextQuestionnaireVersion,
                    'updated_at' => $now,
                    'id' => $surveyId,
                ]));
            } else {
                $pdo->prepare(
                    'INSERT INTO sr_survey_forms
                        (survey_group_id, survey_key, title, description, cover_image_url, skin_key, research_purpose, target_population, recruitment_method, estimated_minutes,
                         project_brief, sponsor_name, research_region, research_language, fieldwork_method, sample_frame, sample_method, target_sample_size,
                         quota_policy, response_rate_basis, analysis_plan, weighting_policy, margin_error_note, methodology_disclosure, ethics_note,
                         sensitive_data_policy, recontact_policy, withdrawal_policy, vendor_name, external_channel_policy, invite_token_policy,
                         qa_status, qa_note, questionnaire_version, revision_locked,
                         organizer_name, contact_text, consent_required, consent_text, privacy_notice, anonymous_allowed, login_required,
                         public_listed, robots_policy, status, starts_at, ends_at, response_limit_policy, response_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, comment_extra_fields_json, reaction_preset_key, reaction_comment_preset_key, reward_enabled,
                         created_by_account_id, updated_by_account_id, created_at, updated_at)
                     VALUES
                        (:survey_group_id, :survey_key, :title, :description, :cover_image_url, :skin_key, :research_purpose, :target_population, :recruitment_method, :estimated_minutes,
                         :project_brief, :sponsor_name, :research_region, :research_language, :fieldwork_method, :sample_frame, :sample_method, :target_sample_size,
                         :quota_policy, :response_rate_basis, :analysis_plan, :weighting_policy, :margin_error_note, :methodology_disclosure, :ethics_note,
                         :sensitive_data_policy, :recontact_policy, :withdrawal_policy, :vendor_name, :external_channel_policy, :invite_token_policy,
                         :qa_status, :qa_note, 1, :revision_locked,
                         :organizer_name, :contact_text, :consent_required, :consent_text, :privacy_notice, :anonymous_allowed, :login_required,
                         :public_listed, :robots_policy, :status, :starts_at, :ends_at, :response_limit_policy, :response_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :comment_extra_fields_json, :reaction_preset_key, :reaction_comment_preset_key, :reward_enabled,
                         :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
                )->execute(array_merge($surveyValues, [
                    'created_by_account_id' => (int) ($account['id'] ?? 0),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $surveyId = (int) $pdo->lastInsertId();
            }
            sr_survey_replace_questions($pdo, $surveyId, $questions, $now);
            sr_survey_replace_reward_policy($pdo, $surveyId, $rewardEnabled, $rewardProvider, $rewardModule, $rewardCouponDefinitionId, $rewardAmount, $rewardDedupeScope, $now);
            $scopeValues = array_merge($surveyValues, [
                'member_group_keys' => $memberGroupKeys,
                'starts_at' => $startsAtInput,
                'ends_at' => $endsAtInput,
                'response_limit_period_seconds' => $responseLimitPeriodSeconds,
                'reward_provider' => $rewardProvider,
                'reward_module' => $rewardModule,
                'reward_coupon_definition_id' => $rewardCouponDefinitionId,
                'reward_amount' => $rewardAmount,
                'reward_dedupe_scope' => $rewardDedupeScope,
            ]);
            foreach ($settingSources as $settingKey => $settingSource) {
                $scopeValues['source_' . $settingKey] = $settingSource;
            }
            sr_survey_apply_group_setting_scopes($pdo, $surveyId, $surveyGroupId, $scopeValues, (int) ($account['id'] ?? 0), $now);
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
        if ($afterSave !== null) {
            try {
                $afterSave($surveyId);
            } catch (Throwable $exception) {
                if (function_exists('sr_log_exception')) {
                    sr_log_exception($exception, 'survey_admin_draft_cleanup_failed');
                }
            }
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

function sr_survey_permanently_delete(PDO $pdo, int $surveyId, string $confirmationPhrase, int $accountId): array
{
    if ($surveyId < 1) {
        return ['ok' => false, 'message' => '영구 삭제할 설문을 찾을 수 없습니다.'];
    }

    $driverName = '';
    try {
        $driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    } catch (Throwable $exception) {
        $driverName = '';
    }
    $forUpdateSql = $driverName === 'sqlite' ? '' : ' FOR UPDATE';

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, survey_key, title, deleted_at FROM sr_survey_forms WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1' . $forUpdateSql);
        $stmt->execute(['id' => $surveyId]);
        $survey = $stmt->fetch();
        if (!is_array($survey)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '이미 영구 삭제되었거나 삭제 상태가 아닌 설문입니다.'];
        }

        $surveyKey = (string) ($survey['survey_key'] ?? '');
        $confirmationPhrase = trim($confirmationPhrase);
        if ($confirmationPhrase !== $surveyKey && $confirmationPhrase !== (string) $surveyId) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '확인 문구가 설문 ID 또는 Key와 일치하지 않습니다.'];
        }

        $commentIdsStmt = $pdo->prepare('SELECT id FROM sr_survey_comments WHERE survey_id = :survey_id');
        $commentIdsStmt->execute(['survey_id' => $surveyId]);
        $commentIds = array_values(array_filter(array_map('intval', array_column($commentIdsStmt->fetchAll(), 'id'))));
        $questionIdsStmt = $pdo->prepare('SELECT id FROM sr_survey_questions WHERE survey_id = :survey_id');
        $questionIdsStmt->execute(['survey_id' => $surveyId]);
        $questionIds = array_values(array_filter(array_map('intval', array_column($questionIdsStmt->fetchAll(), 'id'))));
        $urlEmbedCacheDeleted = function_exists('sr_url_embed_delete_owner_or_target_url_cache')
            ? sr_url_embed_delete_owner_or_target_url_cache($pdo, 'survey', 'survey_form', $surveyId)
            : 0;
        $reactionRecordsDeleted = 0;
        if (function_exists('sr_reaction_delete_target_records')) {
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'survey', 'survey_form', [$surveyId]);
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'survey', 'comment', $commentIds);
        }
        if ($questionIds !== []) {
            $pdo->exec('DELETE FROM sr_survey_choices WHERE question_id IN (' . implode(',', $questionIds) . ')');
        }
        $pdo->prepare('DELETE FROM sr_survey_comments WHERE survey_id = :survey_id')->execute(['survey_id' => $surveyId]);
        $pdo->prepare('DELETE FROM sr_survey_questions WHERE survey_id = :survey_id')->execute(['survey_id' => $surveyId]);
        $pdo->prepare('DELETE FROM sr_survey_setting_sources WHERE survey_id = :survey_id')->execute(['survey_id' => $surveyId]);
        $deleteStmt = $pdo->prepare('DELETE FROM sr_survey_forms WHERE id = :id AND deleted_at IS NOT NULL');
        $deleteStmt->execute(['id' => $surveyId]);
        if ($deleteStmt->rowCount() < 1) {
            throw new RuntimeException('Survey permanent delete did not remove row.');
        }

        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'survey.form.permanently_deleted',
            'target_type' => 'survey.form',
            'target_id' => (string) $surveyId,
            'result' => 'success',
            'message' => 'Deleted survey body rows permanently removed.',
            'metadata' => [
                'survey_key' => $surveyKey,
                'deleted_at' => (string) ($survey['deleted_at'] ?? ''),
                'questions_deleted' => count($questionIds),
                'comments_deleted' => count($commentIds),
                'url_embed_cache_deleted' => $urlEmbedCacheDeleted,
                'reaction_records_deleted' => $reactionRecordsDeleted,
            ],
        ]);

        $pdo->commit();

        return ['ok' => true, 'message' => '설문을 영구 삭제했습니다. 응답과 보상 이력은 보존됩니다.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
