<?php

declare(strict_types=1);

function sr_quiz_default_admin_values(?array $settings = null): array
{
    $settings = sr_quiz_normalize_settings(is_array($settings) ? $settings : []);
    $choiceCount = (int) ($settings['default_question_choice_count'] ?? 4);
    $defaultChoices = [];
    for ($index = 0; $index < $choiceCount; $index++) {
        $defaultChoices[] = [
            'choice_key' => '',
            'label' => '',
            'is_correct' => $index === 0 ? 1 : 0,
            'category_key' => '',
            'category_weight' => 0,
        ];
    }

    return [
        'id' => 0,
        'quiz_group_id' => 0,
        'quiz_key' => '',
        'title' => '',
        'description' => '',
        'cover_image_url' => '',
        'skin_key' => '',
        'status' => (string) $settings['default_status'],
        'quiz_mode' => (string) $settings['default_quiz_mode'],
        'scoring_model' => (string) $settings['default_scoring_model'],
        'pass_score' => (int) $settings['default_pass_score'],
        'starts_at' => '',
        'ends_at' => '',
        'attempt_limit_policy' => (string) $settings['default_attempt_limit_policy'],
        'attempt_limit_period_seconds' => (string) $settings['default_attempt_limit_period_seconds'],
        'member_group_keys' => [],
        'comments_enabled' => 0,
        'secret_comments_enabled' => 0,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($settings['comment_extra_fields_json'] ?? '[]'),
        'reward_enabled' => !empty($settings['default_reward_enabled']) ? 1 : 0,
        'reward_provider' => (string) $settings['default_reward_provider'],
        'reward_module' => (string) $settings['default_reward_module'],
        'reward_coupon_definition_id' => (string) $settings['default_reward_coupon_definition_id'],
        'reward_amount' => (string) $settings['default_reward_amount'],
        'reward_dedupe_scope' => (string) $settings['default_reward_dedupe_scope'],
        'content_source_ids' => '',
        'community_source_ids' => '',
        'result_rules' => '',
        'questions' => [
            [
                'question_key' => 'q1',
                'question_type' => 'single_choice',
                'prompt' => '',
                'score_value' => (int) $settings['default_question_score'],
                'choices' => $defaultChoices,
            ],
        ],
        'source_display' => 'item',
        'source_publication' => 'item',
        'source_scoring' => 'item',
        'source_attempt' => 'item',
        'source_comments' => 'item',
        'source_reactions' => 'item',
        'source_comment_extra_fields_json' => 'item',
        'source_reward' => 'item',
    ];
}

function sr_quiz_admin_values_from_row(array $quiz): array
{
    $policy = is_array($quiz['reward_policy'] ?? null) ? $quiz['reward_policy'] : [];
    $contentSourceIds = [];
    $communitySourceIds = [];
    foreach ((array) ($quiz['sources'] ?? []) as $source) {
        if ((string) ($source['source_module'] ?? '') === 'content' && (string) ($source['source_type'] ?? '') === 'content_item') {
            $contentSourceIds[] = (string) (int) ($source['source_id'] ?? 0);
        } elseif ((string) ($source['source_module'] ?? '') === 'community' && (string) ($source['source_type'] ?? '') === 'community_post') {
            $communitySourceIds[] = (string) (int) ($source['source_id'] ?? 0);
        }
    }
    $resultRules = [];
    foreach ((array) ($quiz['result_rules'] ?? []) as $rule) {
        $resultRules[] = implode('|', [
            (string) ($rule['result_key'] ?? ''),
            (string) ($rule['title'] ?? ''),
            (string) ($rule['min_score'] ?? ''),
            (string) ($rule['max_score'] ?? ''),
            (string) ($rule['category_key'] ?? ''),
            (string) ($rule['threshold_value'] ?? ''),
            (string) ($rule['summary'] ?? ''),
        ]);
    }
    $questions = [];
    foreach ((array) ($quiz['questions'] ?? []) as $question) {
        $choices = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $choices[] = [
                'id' => (int) ($choice['id'] ?? 0),
                'choice_key' => (string) ($choice['choice_key'] ?? ''),
                'label' => (string) ($choice['label'] ?? ''),
                'is_correct' => (int) ($choice['is_correct'] ?? 0),
                'category_key' => (string) ($choice['category_key'] ?? ''),
                'category_weight' => (int) ($choice['category_weight'] ?? 0),
            ];
        }
        $questions[] = [
            'id' => (int) ($question['id'] ?? 0),
            'question_key' => (string) ($question['question_key'] ?? ''),
            'question_type' => (string) ($question['question_type'] ?? 'single_choice'),
            'prompt' => (string) ($question['prompt'] ?? ''),
            'score_value' => (int) ($question['score_value'] ?? 0),
            'choices' => $choices,
        ];
    }

    return [
        'id' => (int) ($quiz['id'] ?? 0),
        'quiz_group_id' => (int) ($quiz['quiz_group_id'] ?? 0),
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'title' => (string) ($quiz['title'] ?? ''),
        'description' => (string) ($quiz['description'] ?? ''),
        'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($quiz['cover_image_url'] ?? '')),
        'skin_key' => sr_quiz_clean_optional_skin_key((string) ($quiz['skin_key'] ?? '')),
        'status' => (string) ($quiz['status'] ?? 'draft'),
        'quiz_mode' => (string) ($quiz['quiz_mode'] ?? 'scored'),
        'scoring_model' => (string) ($quiz['scoring_model'] ?? 'correct_answer'),
        'pass_score' => (string) ($quiz['pass_score'] ?? ''),
        'starts_at' => sr_quiz_datetime_local_value($quiz['starts_at'] ?? ''),
        'ends_at' => sr_quiz_datetime_local_value($quiz['ends_at'] ?? ''),
        'attempt_limit_policy' => (string) ($quiz['attempt_limit_policy'] ?? 'unlimited'),
        'attempt_limit_period_seconds' => (string) ($quiz['attempt_limit_period_seconds'] ?? ''),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? ''),
        'comments_enabled' => (int) ($quiz['comments_enabled'] ?? 0),
        'secret_comments_enabled' => (int) ($quiz['secret_comments_enabled'] ?? 0),
        'reaction_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $quiz['reaction_preset_key'] ?? '') : '',
        'reaction_comment_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], $quiz['reaction_comment_preset_key'] ?? '') : '',
        'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($quiz['comment_extra_fields_json'] ?? '[]'),
        'reward_enabled' => (int) ($quiz['reward_enabled'] ?? 0),
        'reward_provider' => (string) ($policy['reward_provider'] ?? 'ledger_asset'),
        'reward_module' => (string) ($policy['reward_module'] ?? ''),
        'reward_coupon_definition_id' => (string) ((string) ($policy['reward_provider'] ?? '') === 'coupon' ? ($policy['reward_code'] ?? '') : ''),
        'reward_amount' => (string) ($policy['reward_amount'] ?? ''),
        'reward_dedupe_scope' => (string) ($policy['dedupe_scope'] ?? 'per_quiz'),
        'content_source_ids' => implode("\n", $contentSourceIds),
        'community_source_ids' => implode("\n", $communitySourceIds),
        'result_rules' => implode("\n", $resultRules),
        'questions' => $questions === [] ? sr_quiz_default_admin_values()['questions'] : $questions,
        'source_display' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'display') : 'item',
        'source_publication' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'publication') : 'item',
        'source_scoring' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'scoring') : 'item',
        'source_attempt' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'attempt') : 'item',
        'source_comments' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'comments') : 'item',
        'source_reactions' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'reactions') : 'item',
        'source_comment_extra_fields_json' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'comment_extra_fields_json') : 'item',
        'source_reward' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO ? sr_quiz_setting_source($GLOBALS['pdo'], (int) ($quiz['id'] ?? 0), 'reward') : 'item',
    ];
}

function sr_quiz_admin_values_from_post(): array
{
    $skinKey = sr_quiz_optional_option_key_from_post(sr_post_string('skin_key', 40), sr_quiz_skin_options());

    $resultRuleKeys = $_POST['result_rule_key'] ?? [];
    $resultRuleTitles = $_POST['result_rule_title'] ?? [];
    $resultRuleMinScores = $_POST['result_rule_min_score'] ?? [];
    $resultRuleMaxScores = $_POST['result_rule_max_score'] ?? [];
    $resultRuleCategoryKeys = $_POST['result_rule_category_key'] ?? [];
    $resultRuleThresholdValues = $_POST['result_rule_threshold_value'] ?? [];
    $resultRuleSummaries = $_POST['result_rule_summary'] ?? [];
    $resultRuleLines = [];

    if (is_array($resultRuleKeys)) {
        foreach ($resultRuleKeys as $index => $keyValue) {
            $resultKey = sr_quiz_clean_key((string) $keyValue, 64);
            $title = is_array($resultRuleTitles) && isset($resultRuleTitles[$index])
                ? sr_quiz_clean_single_line((string) $resultRuleTitles[$index], 190)
                : '';
            $minScore = is_array($resultRuleMinScores) && isset($resultRuleMinScores[$index]) ? trim((string) $resultRuleMinScores[$index]) : '';
            $maxScore = is_array($resultRuleMaxScores) && isset($resultRuleMaxScores[$index]) ? trim((string) $resultRuleMaxScores[$index]) : '';
            $categoryKey = is_array($resultRuleCategoryKeys) && isset($resultRuleCategoryKeys[$index])
                ? sr_quiz_clean_key((string) $resultRuleCategoryKeys[$index], 64)
                : '';
            $thresholdValue = is_array($resultRuleThresholdValues) && isset($resultRuleThresholdValues[$index]) ? trim((string) $resultRuleThresholdValues[$index]) : '';
            $summary = is_array($resultRuleSummaries) && isset($resultRuleSummaries[$index])
                ? sr_quiz_clean_text((string) $resultRuleSummaries[$index], 1000)
                : '';

            if ($resultKey === '' && $title === '' && $minScore === '' && $maxScore === '' && $categoryKey === '' && $thresholdValue === '' && $summary === '') {
                continue;
            }

            $resultRuleLines[] = implode('|', [
                $resultKey,
                $title,
                $minScore,
                $maxScore,
                $categoryKey,
                $thresholdValue,
                str_replace(["\r\n", "\r", "\n"], ' ', $summary),
            ]);
        }
    }

    $questionUids = $_POST['question_uid'] ?? [];
    $questionKeys = $_POST['question_key'] ?? [];
    $questionTypes = $_POST['question_type'] ?? [];
    $questionPrompts = $_POST['question_prompt'] ?? [];
    $questionScores = $_POST['question_score'] ?? [];
    $choiceKeys = $_POST['choice_key'] ?? [];
    $choiceLabels = $_POST['choice_label'] ?? [];
    $choiceCategoryKeys = $_POST['choice_category_key'] ?? [];
    $choiceCategoryWeights = $_POST['choice_category_weight'] ?? [];
    $correctChoices = $_POST['correct_choice'] ?? [];
    $questions = [];

    if (!is_array($questionUids)) {
        $questionUids = [];
    }
    foreach ($questionUids as $index => $uidValue) {
        $uid = is_string($uidValue) ? $uidValue : (string) $uidValue;
        $questionKey = is_array($questionKeys) && isset($questionKeys[$index]) ? sr_quiz_clean_key((string) $questionKeys[$index]) : '';
        $questionType = is_array($questionTypes) && isset($questionTypes[$index]) ? (string) $questionTypes[$index] : 'single_choice';
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $questionType = 'single_choice';
        }
        $prompt = is_array($questionPrompts) && isset($questionPrompts[$index]) ? sr_quiz_clean_text((string) $questionPrompts[$index], 2000) : '';
        $score = is_array($questionScores) && isset($questionScores[$index]) ? (int) $questionScores[$index] : 1;
        $rowChoiceKeys = is_array($choiceKeys[$uid] ?? null) ? $choiceKeys[$uid] : [];
        $rowChoiceLabels = is_array($choiceLabels[$uid] ?? null) ? $choiceLabels[$uid] : [];
        $rowCategoryKeys = is_array($choiceCategoryKeys[$uid] ?? null) ? $choiceCategoryKeys[$uid] : [];
        $rowCategoryWeights = is_array($choiceCategoryWeights[$uid] ?? null) ? $choiceCategoryWeights[$uid] : [];
        $correctValues = is_array($correctChoices[$uid] ?? null) ? array_map('strval', $correctChoices[$uid]) : [is_array($correctChoices) ? (string) ($correctChoices[$uid] ?? '') : ''];
        $choices = [];
        foreach ($rowChoiceLabels as $choiceIndex => $choiceLabelValue) {
            $choiceLabel = sr_quiz_clean_single_line((string) $choiceLabelValue, 255);
            $choiceKey = isset($rowChoiceKeys[$choiceIndex]) ? sr_quiz_clean_key((string) $rowChoiceKeys[$choiceIndex]) : '';
            $categoryKey = isset($rowCategoryKeys[$choiceIndex]) ? sr_quiz_clean_key((string) $rowCategoryKeys[$choiceIndex]) : '';
            $categoryWeight = isset($rowCategoryWeights[$choiceIndex]) ? (int) $rowCategoryWeights[$choiceIndex] : 0;
            if ($choiceLabel === '' && $choiceKey === '') {
                continue;
            }
            if ($choiceLabel !== '' && $choiceKey === '') {
                $choiceKey = 'c' . (string) ((int) $choiceIndex + 1);
            }
            $choices[] = [
                'choice_key' => $choiceKey,
                'label' => $choiceLabel,
                'is_correct' => in_array((string) $choiceIndex, $correctValues, true) ? 1 : 0,
                'category_key' => $categoryKey,
                'category_weight' => $categoryWeight,
            ];
        }
        if ($prompt === '' && $questionKey === '' && $choices === []) {
            continue;
        }
        if ($prompt !== '' && $questionKey === '') {
            $questionKey = 'q' . (string) ((int) $index + 1);
        }
        $questions[] = [
            'question_key' => $questionKey,
            'question_type' => $questionType,
            'prompt' => $prompt,
            'score_value' => max(0, $score),
            'choices' => $choices,
        ];
    }

    $memberGroupKeys = $_POST['member_group_keys'] ?? [];
    if (!is_array($memberGroupKeys)) {
        $memberGroupKeys = [];
    }

    return [
        'id' => (int) sr_post_string('quiz_id', 20),
        'quiz_group_id' => max(0, (int) sr_post_string('quiz_group_id', 20)),
        'quiz_key' => sr_quiz_clean_key(sr_post_string('quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('title', 190), 190),
        'description' => sr_quiz_clean_text(sr_post_string('description', 2000), 2000),
        'cover_image_url' => sr_quiz_clean_cover_image_url(sr_post_string('cover_image_url', 255)),
        'skin_key' => $skinKey,
        'status' => sr_post_string('status', 20),
        'quiz_mode' => sr_post_string('quiz_mode', 30),
        'scoring_model' => sr_post_string('scoring_model', 40),
        'pass_score' => sr_post_string('pass_score', 20),
        'starts_at' => sr_post_string('starts_at', 30),
        'ends_at' => sr_post_string('ends_at', 30),
        'attempt_limit_policy' => sr_post_string('attempt_limit_policy', 30),
        'attempt_limit_period_seconds' => sr_post_string('attempt_limit_period_seconds', 20),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($memberGroupKeys),
        'comments_enabled' => ($_POST['comments_enabled'] ?? '') === '1' ? 1 : 0,
        'secret_comments_enabled' => ($_POST['secret_comments_enabled'] ?? '') === '1' ? 1 : 0,
        'reaction_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_preset_key', 80)) : '',
        'reaction_comment_preset_key' => isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO && sr_module_enabled($GLOBALS['pdo'], 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($GLOBALS['pdo'], sr_post_string('reaction_comment_preset_key', 80)) : '',
        'comment_extra_fields_json' => sr_post_string_without_truncation('comment_extra_fields_json', 20000) ?? '[]',
        'reward_enabled' => ($_POST['reward_enabled'] ?? '') === '1' ? 1 : 0,
        'reward_provider' => sr_quiz_clean_key(sr_post_string('reward_provider', 30), 30),
        'reward_module' => sr_quiz_clean_key(sr_post_string('reward_module', 40), 40),
        'reward_coupon_definition_id' => sr_post_string('reward_coupon_definition_id', 20),
        'reward_amount' => sr_post_string('reward_amount', 20),
        'reward_dedupe_scope' => sr_quiz_clean_key(sr_post_string('reward_dedupe_scope', 20), 20),
        'content_source_ids' => sr_quiz_clean_text(sr_post_string('content_source_ids', 1000), 1000),
        'community_source_ids' => sr_quiz_clean_text(sr_post_string('community_source_ids', 1000), 1000),
        'result_rules' => sr_quiz_clean_text($resultRuleLines === [] ? sr_post_string('result_rules', 4000) : implode("\n", $resultRuleLines), 4000),
        'questions' => $questions,
        'source_display' => sr_quiz_setting_scope(sr_post_string('source_display', 20)),
        'source_publication' => sr_quiz_setting_scope(sr_post_string('source_publication', 20)),
        'source_scoring' => sr_quiz_setting_scope(sr_post_string('source_scoring', 20)),
        'source_attempt' => sr_quiz_setting_scope(sr_post_string('source_attempt', 20)),
        'source_comments' => sr_quiz_setting_scope(sr_post_string('source_comments', 20)),
        'source_reactions' => sr_quiz_setting_scope(sr_post_string('source_reactions', 20)),
        'source_comment_extra_fields_json' => sr_quiz_setting_scope(sr_post_string('source_comment_extra_fields_json', 20)),
        'source_reward' => sr_quiz_setting_scope(sr_post_string('source_reward', 20)),
    ];
}

function sr_quiz_content_source_ids_from_value(string $value): array
{
    $ids = [];
    foreach (preg_split('/[\s,]+/', $value) ?: [] as $part) {
        $id = (int) trim((string) $part);
        if ($id > 0) {
            $ids[$id] = true;
        }
    }

    return array_keys($ids);
}

function sr_quiz_content_source_ids_exist(PDO $pdo, array $contentIds): array
{
    $contentIds = array_values(array_filter(array_map('intval', $contentIds), static fn (int $id): bool => $id > 0));
    if ($contentIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($contentIds as $index => $contentId) {
        $placeholder = ':id_' . (string) $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $contentId;
    }
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_items
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND status <> \'deleted\''
    );
    $stmt->execute($params);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[(int) ($row['id'] ?? 0)] = true;
    }

    return array_keys($found);
}

function sr_quiz_community_source_ids_exist(PDO $pdo, array $postIds): array
{
    $postIds = array_values(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0));
    if ($postIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($postIds as $index => $postId) {
        $placeholder = ':id_' . (string) $index;
        $placeholders[] = $placeholder;
        $params[$placeholder] = $postId;
    }
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_community_posts
         WHERE id IN (' . implode(', ', $placeholders) . ')
           AND status <> \'deleted\''
    );
    $stmt->execute($params);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[(int) ($row['id'] ?? 0)] = true;
    }

    return array_keys($found);
}

function sr_quiz_result_rules_from_value(string $value): array
{
    $rules = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $line) {
        $line = trim((string) $line);
        if ($line === '') {
            continue;
        }
        $parts = array_pad(array_map('trim', explode('|', $line, 7)), 7, '');
        $resultKey = sr_quiz_clean_key($parts[0], 64);
        $title = sr_quiz_clean_single_line($parts[1], 190);
        if ($resultKey === '' && $title === '') {
            continue;
        }
        $rules[] = [
            'result_key' => $resultKey,
            'title' => $title,
            'min_score' => $parts[2] === '' ? null : (int) $parts[2],
            'max_score' => $parts[3] === '' ? null : (int) $parts[3],
            'category_key' => sr_quiz_clean_key($parts[4], 64),
            'threshold_value' => $parts[5] === '' ? null : (int) $parts[5],
            'summary' => sr_quiz_clean_text($parts[6], 1000),
        ];
    }

    return $rules;
}

function sr_quiz_asset_options(PDO $pdo): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';

    $options = [];
    foreach (sr_member_ledger_asset_definitions($pdo) as $moduleKey => $asset) {
        $transactionFunction = (string) ($asset['transaction_function'] ?? '');
        $lookupFunction = (string) ($asset['transaction_lookup_function'] ?? '');
        if (!function_exists($transactionFunction) || !function_exists($lookupFunction)) {
            continue;
        }
        $options[$moduleKey] = $asset;
    }

    return $options;
}

function sr_quiz_admin_validation_errors(PDO $pdo, array $values, array $assetOptions): array
{
    $errors = [];
    $quizId = (int) ($values['id'] ?? 0);
    $quizKey = (string) ($values['quiz_key'] ?? '');
    if (!sr_quiz_key_is_valid($quizKey)) {
        $errors[] = '퀴즈 Key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_quiz_key_is_reserved($quizKey)) {
        $errors[] = '예약된 퀴즈 Key는 사용할 수 없습니다.';
    } elseif (sr_quiz_key_exists($pdo, $quizKey, $quizId)) {
        $errors[] = '이미 사용 중인 퀴즈 Key입니다.';
    }
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '퀴즈 제목을 입력하세요.';
    }
    $quizGroupId = (int) ($values['quiz_group_id'] ?? 0);
    if ($quizGroupId > 0 && !is_array(sr_quiz_group_by_id($pdo, $quizGroupId))) {
        $errors[] = '퀴즈 그룹을 확인하세요.';
    }
    foreach (array_keys(sr_quiz_group_setting_bundles()) as $settingKey) {
        if (sr_quiz_setting_scope((string) ($values['source_' . $settingKey] ?? 'item')) === 'group' && $quizGroupId < 1) {
            $errors[] = '그룹 적용을 사용하려면 퀴즈 그룹을 선택하세요.';
            break;
        }
    }
    $skinKey = (string) ($values['skin_key'] ?? '');
    if ($skinKey !== '' && !isset(sr_quiz_skin_options()[$skinKey])) {
        $errors[] = '퀴즈 스킨 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['status'] ?? ''), sr_quiz_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['quiz_mode'] ?? ''), sr_quiz_modes(), true)) {
        $errors[] = '퀴즈 모드 값이 올바르지 않습니다.';
    }
    if (!in_array((string) ($values['scoring_model'] ?? ''), sr_quiz_scoring_models(), true)) {
        $errors[] = '채점 모델 값이 올바르지 않습니다.';
    }
    if ((string) ($values['pass_score'] ?? '') !== '' && (int) $values['pass_score'] < 0) {
        $errors[] = '통과 점수는 0 이상이어야 합니다.';
    }
    $startsAtInput = (string) ($values['starts_at'] ?? '');
    $endsAtInput = (string) ($values['ends_at'] ?? '');
    $startsAt = sr_quiz_clean_admin_datetime($startsAtInput);
    $endsAt = sr_quiz_clean_admin_datetime($endsAtInput);
    if (trim($startsAtInput) !== '' && $startsAt === null) {
        $errors[] = '공개 시작일시 형식이 올바르지 않습니다.';
    }
    if (trim($endsAtInput) !== '' && $endsAt === null) {
        $errors[] = '공개 종료일시 형식이 올바르지 않습니다.';
    }
    if ($startsAt !== null && $endsAt !== null && $startsAt > $endsAt) {
        $errors[] = '공개 종료일시는 시작일시 이후여야 합니다.';
    }
    $attemptLimitPolicy = (string) ($values['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($attemptLimitPolicy, sr_quiz_attempt_limit_policies(), true)) {
        $errors[] = '응시 제한 정책 값이 올바르지 않습니다.';
    }
    if ($attemptLimitPolicy === 'per_period' && (int) ($values['attempt_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기간당 1회 제한은 제한 기간을 1초 이상 입력해야 합니다.';
    }
    foreach (sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []) as $groupKey) {
        if (!function_exists('sr_member_group_exists') || !sr_member_group_exists($pdo, $groupKey)) {
            $errors[] = '응시 가능 회원 그룹을 찾을 수 없습니다: ' . $groupKey;
        }
    }
    $errors = array_merge($errors, sr_comment_extra_field_definition_errors($values['comment_extra_fields_json'] ?? '[]'));

    $questions = (array) ($values['questions'] ?? []);
    if ($questions === []) {
        $errors[] = '문제를 1개 이상 입력하세요.';
    }
    $questionKeys = [];
    foreach ($questions as $questionIndex => $question) {
        $number = $questionIndex + 1;
        $questionKey = (string) ($question['question_key'] ?? '');
        $questionType = (string) ($question['question_type'] ?? 'single_choice');
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $errors[] = '문제 ' . (string) $number . '의 유형이 올바르지 않습니다.';
        }
        if (!sr_quiz_key_is_valid($questionKey)) {
            $errors[] = '문제 ' . (string) $number . '의 Key가 올바르지 않습니다.';
        } elseif (isset($questionKeys[$questionKey])) {
            $errors[] = '문제 Key가 중복되었습니다: ' . $questionKey;
        }
        $questionKeys[$questionKey] = true;
        if ((string) ($question['prompt'] ?? '') === '') {
            $errors[] = '문제 ' . (string) $number . '의 내용을 입력하세요.';
        }
        $choices = (array) ($question['choices'] ?? []);
        if (count($choices) < 2) {
            $errors[] = '문제 ' . (string) $number . '에는 선택지를 2개 이상 입력하세요.';
        }
        $choiceKeys = [];
        $correctCount = 0;
        foreach ($choices as $choiceIndex => $choice) {
            $choiceNumber = $choiceIndex + 1;
            $choiceKey = (string) ($choice['choice_key'] ?? '');
            if (!sr_quiz_key_is_valid($choiceKey)) {
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 Key가 올바르지 않습니다.';
            } elseif (isset($choiceKeys[$choiceKey])) {
                $errors[] = '문제 ' . (string) $number . '의 선택지 Key가 중복되었습니다: ' . $choiceKey;
            }
            $choiceKeys[$choiceKey] = true;
            if ((string) ($choice['label'] ?? '') === '') {
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 내용을 입력하세요.';
            }
            if ((int) ($choice['is_correct'] ?? 0) === 1) {
                $correctCount++;
            }
        }
        if ($questionType === 'single_choice' && $correctCount !== 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 정확히 1개 지정해야 합니다.';
        } elseif ($questionType === 'multiple_choice' && $correctCount < 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 1개 이상 지정해야 합니다.';
        }
    }

    $resultRuleKeys = [];
    foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $ruleIndex => $rule) {
        $number = $ruleIndex + 1;
        $resultKey = (string) ($rule['result_key'] ?? '');
        if (!sr_quiz_key_is_valid($resultKey)) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 Key가 올바르지 않습니다.';
        } elseif (isset($resultRuleKeys[$resultKey])) {
            $errors[] = '결과 규칙 Key가 중복되었습니다: ' . $resultKey;
        }
        $resultRuleKeys[$resultKey] = true;
        if ((string) ($rule['title'] ?? '') === '') {
            $errors[] = '결과 규칙 ' . (string) $number . '의 제목을 입력하세요.';
        }
        if (($rule['min_score'] ?? null) !== null && ($rule['max_score'] ?? null) !== null && (int) $rule['min_score'] > (int) $rule['max_score']) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 최대 점수는 최소 점수 이상이어야 합니다.';
        }
    }

    if ((int) ($values['reward_enabled'] ?? 0) === 1) {
        $rewardProvider = (string) ($values['reward_provider'] ?? 'ledger_asset');
        if (!in_array($rewardProvider, sr_quiz_reward_providers(), true)) {
            $errors[] = '보상 종류 값이 올바르지 않습니다.';
        }
        if (!in_array((string) ($values['reward_dedupe_scope'] ?? 'per_quiz'), sr_quiz_reward_dedupe_scopes(), true)) {
            $errors[] = '보상 중복 제한 값이 올바르지 않습니다.';
        }
        if ($rewardProvider === 'ledger_asset') {
            $rewardModule = (string) ($values['reward_module'] ?? '');
            $rewardAmount = (int) ($values['reward_amount'] ?? 0);
            if ($rewardModule === '' || !isset($assetOptions[$rewardModule])) {
                $errors[] = '보상 자산을 선택하세요.';
            }
            if ($rewardAmount <= 0) {
                $errors[] = '보상 금액은 1 이상이어야 합니다.';
            }
        } elseif ($rewardProvider === 'coupon') {
            $definitionId = (int) ($values['reward_coupon_definition_id'] ?? 0);
            if (!sr_quiz_reward_coupon_definition_is_available($pdo, $definitionId)) {
                $errors[] = '보상으로 사용할 수 있는 쿠폰을 선택하세요.';
            }
        }
    }

    $contentSourceIds = sr_quiz_content_source_ids_from_value((string) ($values['content_source_ids'] ?? ''));
    if ($contentSourceIds !== []) {
        $foundIds = array_fill_keys(sr_quiz_content_source_ids_exist($pdo, $contentSourceIds), true);
        foreach ($contentSourceIds as $contentSourceId) {
            if (!isset($foundIds[$contentSourceId])) {
                $errors[] = '연결 콘텐츠 ID를 찾을 수 없습니다: ' . (string) $contentSourceId;
            }
        }
    }
    $communitySourceIds = sr_quiz_content_source_ids_from_value((string) ($values['community_source_ids'] ?? ''));
    if ($communitySourceIds !== []) {
        if (!sr_module_enabled($pdo, 'community')) {
            $errors[] = '커뮤니티 모듈이 활성화되어 있지 않습니다.';
        } else {
            $foundIds = array_fill_keys(sr_quiz_community_source_ids_exist($pdo, $communitySourceIds), true);
            foreach ($communitySourceIds as $communitySourceId) {
                if (!isset($foundIds[$communitySourceId])) {
                    $errors[] = '연결 커뮤니티 게시글 ID를 찾을 수 없습니다: ' . (string) $communitySourceId;
                }
            }
        }
    }
    foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $index => $rule) {
        $number = $index + 1;
        if (!sr_quiz_key_is_valid((string) ($rule['result_key'] ?? ''))) {
            $errors[] = '결과 규칙 ' . (string) $number . '의 결과 Key가 올바르지 않습니다.';
        }
        if ((string) ($rule['title'] ?? '') === '') {
            $errors[] = '결과 규칙 ' . (string) $number . '의 제목을 입력하세요.';
        }
    }

    return array_values(array_unique($errors));
}

function sr_quiz_copy_options_from_post(): array
{
    return [
        'source_quiz_id' => (int) sr_post_string('source_quiz_id', 20),
        'quiz_key' => sr_quiz_clean_key(sr_post_string('copy_quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('copy_title', 190), 190),
        'copy_dates' => ($_POST['copy_dates'] ?? '') === '1',
        'copy_member_groups' => ($_POST['copy_member_groups'] ?? '') === '1',
        'copy_reward_policy' => ($_POST['copy_reward_policy'] ?? '') === '1',
        'copy_sources' => ($_POST['copy_sources'] ?? '') === '1',
        'copy_status' => ($_POST['copy_status'] ?? '') === '1',
    ];
}

function sr_quiz_copy_validation_errors(PDO $pdo, array $options): array
{
    $errors = [];
    $sourceQuizId = (int) ($options['source_quiz_id'] ?? 0);
    if ($sourceQuizId < 1) {
        $errors[] = '복사할 원본 퀴즈를 선택하세요.';
    } else {
        $sourceStmt = $pdo->prepare('SELECT id FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $sourceStmt->execute(['id' => $sourceQuizId]);
        if (!is_array($sourceStmt->fetch())) {
            $errors[] = '삭제되었거나 찾을 수 없는 퀴즈는 복사할 수 없습니다.';
        }
    }
    $quizKey = (string) ($options['quiz_key'] ?? '');
    if (!sr_quiz_key_is_valid($quizKey)) {
        $errors[] = '새 퀴즈 Key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_quiz_key_exists($pdo, $quizKey, 0)) {
        $errors[] = '이미 사용 중인 퀴즈 Key입니다.';
    }
    if ((string) ($options['title'] ?? '') === '') {
        $errors[] = '새 퀴즈 제목을 입력하세요.';
    }

    return $errors;
}

function sr_quiz_copy_admin_quiz(PDO $pdo, int $sourceQuizId, array $options, int $accountId): array
{
    $warnings = [];
    $skippedSources = [];
    $skippedMemberGroupKeys = [];
    $skippedRewardPolicy = '';
    $newQuizId = 0;
    $newQuizKey = (string) ($options['quiz_key'] ?? '');
    $now = sr_now();

    $pdo->beginTransaction();
    try {
        $sourceStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_sets
             WHERE id = :id
               AND deleted_at IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        $sourceStmt->execute(['id' => $sourceQuizId]);
        $sourceQuiz = $sourceStmt->fetch();
        if (!is_array($sourceQuiz)) {
            throw new RuntimeException('Source quiz was not found.');
        }
        if (!sr_quiz_key_is_valid($newQuizKey) || sr_quiz_key_exists($pdo, $newQuizKey, 0)) {
            throw new RuntimeException('New quiz key is invalid or duplicated.');
        }

        $memberGroupKeys = [];
        if (!empty($options['copy_member_groups'])) {
            foreach (sr_quiz_member_group_keys_from_value($sourceQuiz['member_group_keys_json'] ?? '') as $groupKey) {
                if (function_exists('sr_member_group_exists') && sr_member_group_exists($pdo, $groupKey)) {
                    $memberGroupKeys[] = $groupKey;
                } else {
                    $skippedMemberGroupKeys[] = $groupKey;
                }
            }
            if ($skippedMemberGroupKeys !== []) {
                $warnings[] = '존재하지 않는 회원 그룹 Key는 복사하지 않았습니다.';
            }
        }

        $rewardPolicy = null;
        if (!empty($options['copy_reward_policy']) && (int) ($sourceQuiz['reward_enabled'] ?? 0) === 1) {
            $policyStmt = $pdo->prepare(
                'SELECT *
                 FROM sr_quiz_reward_policies
                 WHERE quiz_id = :quiz_id
                   AND status = \'active\'
                 ORDER BY sort_order ASC, id ASC
                 LIMIT 1'
            );
            $policyStmt->execute(['quiz_id' => $sourceQuizId]);
            $policy = $policyStmt->fetch();
            if (is_array($policy)) {
                $provider = (string) ($policy['reward_provider'] ?? '');
                if ($provider === 'coupon') {
                    if (sr_quiz_reward_coupon_definition_is_available($pdo, (int) ($policy['reward_code'] ?? 0))) {
                        $rewardPolicy = $policy;
                    } else {
                        $skippedRewardPolicy = 'coupon_unavailable';
                    }
                } elseif ($provider === 'ledger_asset') {
                    $assetOptions = sr_quiz_asset_options($pdo);
                    $moduleKey = (string) ($policy['reward_module'] ?? '');
                    if (isset($assetOptions[$moduleKey]) && (int) ($policy['reward_amount'] ?? 0) > 0) {
                        $rewardPolicy = $policy;
                    } else {
                        $skippedRewardPolicy = 'asset_unavailable';
                    }
                } else {
                    $skippedRewardPolicy = 'provider_unsupported';
                }
            } else {
                $skippedRewardPolicy = 'active_policy_missing';
            }
            if ($skippedRewardPolicy !== '') {
                $warnings[] = '보상 정책은 현재 사용할 수 없어 복사하지 않았습니다.';
            }
        }

        $insertQuiz = $pdo->prepare(
            'INSERT INTO sr_quiz_sets
                (quiz_key, title, description, cover_image_url, skin_key, status, quiz_mode, scoring_model, pass_score, starts_at, ends_at,
                 attempt_limit_policy, attempt_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, comment_extra_fields_json, reward_enabled,
                 created_by_account_id, updated_by_account_id, created_at, updated_at)
             VALUES
                (:quiz_key, :title, :description, :cover_image_url, :skin_key, :status, :quiz_mode, :scoring_model, :pass_score, :starts_at, :ends_at,
                 :attempt_limit_policy, :attempt_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :comment_extra_fields_json, :reward_enabled,
                 :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
        );
        $memberGroupKeysJson = json_encode(array_values($memberGroupKeys), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $insertQuiz->execute([
            'quiz_key' => $newQuizKey,
            'title' => (string) ($options['title'] ?? ''),
            'description' => (string) ($sourceQuiz['description'] ?? ''),
            'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($sourceQuiz['cover_image_url'] ?? '')),
            'skin_key' => sr_quiz_clean_optional_skin_key((string) ($sourceQuiz['skin_key'] ?? '')),
            'status' => !empty($options['copy_status']) ? (string) ($sourceQuiz['status'] ?? 'draft') : 'draft',
            'quiz_mode' => (string) ($sourceQuiz['quiz_mode'] ?? 'scored'),
            'scoring_model' => (string) ($sourceQuiz['scoring_model'] ?? 'correct_answer'),
            'pass_score' => $sourceQuiz['pass_score'] ?? null,
            'starts_at' => !empty($options['copy_dates']) ? ($sourceQuiz['starts_at'] ?? null) : null,
            'ends_at' => !empty($options['copy_dates']) ? ($sourceQuiz['ends_at'] ?? null) : null,
            'attempt_limit_policy' => (string) ($sourceQuiz['attempt_limit_policy'] ?? 'unlimited'),
            'attempt_limit_period_seconds' => $sourceQuiz['attempt_limit_period_seconds'] ?? null,
            'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
            'comments_enabled' => (int) ($sourceQuiz['comments_enabled'] ?? 0),
            'secret_comments_enabled' => (int) ($sourceQuiz['secret_comments_enabled'] ?? 0),
            'comment_extra_fields_json' => sr_comment_extra_field_definitions_json($sourceQuiz['comment_extra_fields_json'] ?? '[]'),
            'reward_enabled' => is_array($rewardPolicy) ? 1 : 0,
            'created_by_account_id' => $accountId,
            'updated_by_account_id' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $newQuizId = (int) $pdo->lastInsertId();

        $questionIdMap = [];
        $questionStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_questions
             WHERE quiz_id = :quiz_id
             ORDER BY sort_order ASC, id ASC'
        );
        $questionStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertQuestion = $pdo->prepare(
            'INSERT INTO sr_quiz_questions
                (quiz_id, question_key, question_type, prompt, help_text, required, score_value, sort_order, settings_json, created_at, updated_at)
             VALUES
                (:quiz_id, :question_key, :question_type, :prompt, :help_text, :required, :score_value, :sort_order, :settings_json, :created_at, :updated_at)'
        );
        $insertChoice = $pdo->prepare(
            'INSERT INTO sr_quiz_choices
                (question_id, choice_key, label, description, is_correct, score_value, category_key, category_weight, sort_order, settings_json, created_at, updated_at)
             VALUES
                (:question_id, :choice_key, :label, :description, :is_correct, :score_value, :category_key, :category_weight, :sort_order, :settings_json, :created_at, :updated_at)'
        );
        $choiceStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_choices
             WHERE question_id = :question_id
             ORDER BY sort_order ASC, id ASC'
        );
        foreach ($questionStmt->fetchAll() as $question) {
            $insertQuestion->execute([
                'quiz_id' => $newQuizId,
                'question_key' => (string) ($question['question_key'] ?? ''),
                'question_type' => (string) ($question['question_type'] ?? 'single_choice'),
                'prompt' => (string) ($question['prompt'] ?? ''),
                'help_text' => $question['help_text'] ?? null,
                'required' => (int) ($question['required'] ?? 1),
                'score_value' => (int) ($question['score_value'] ?? 0),
                'sort_order' => (int) ($question['sort_order'] ?? 0),
                'settings_json' => $question['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newQuestionId = (int) $pdo->lastInsertId();
            $questionIdMap[(int) ($question['id'] ?? 0)] = $newQuestionId;
            $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
            foreach ($choiceStmt->fetchAll() as $choice) {
                $insertChoice->execute([
                    'question_id' => $newQuestionId,
                    'choice_key' => (string) ($choice['choice_key'] ?? ''),
                    'label' => (string) ($choice['label'] ?? ''),
                    'description' => $choice['description'] ?? null,
                    'is_correct' => (int) ($choice['is_correct'] ?? 0),
                    'score_value' => (int) ($choice['score_value'] ?? 0),
                    'category_key' => (string) ($choice['category_key'] ?? '') !== '' ? (string) $choice['category_key'] : null,
                    'category_weight' => (int) ($choice['category_weight'] ?? 0),
                    'sort_order' => (int) ($choice['sort_order'] ?? 0),
                    'settings_json' => $choice['settings_json'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $resultIdMap = [];
        $resultStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_results
             WHERE quiz_id = :quiz_id
             ORDER BY sort_order ASC, id ASC'
        );
        $resultStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertResult = $pdo->prepare(
            'INSERT INTO sr_quiz_results
                (quiz_id, result_key, title, summary, body, status, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :result_key, :title, :summary, :body, :status, :sort_order, :created_at, :updated_at)'
        );
        foreach ($resultStmt->fetchAll() as $result) {
            $insertResult->execute([
                'quiz_id' => $newQuizId,
                'result_key' => (string) ($result['result_key'] ?? ''),
                'title' => (string) ($result['title'] ?? ''),
                'summary' => $result['summary'] ?? null,
                'body' => $result['body'] ?? null,
                'status' => (string) ($result['status'] ?? 'active'),
                'sort_order' => (int) ($result['sort_order'] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $resultIdMap[(int) ($result['id'] ?? 0)] = (int) $pdo->lastInsertId();
        }

        $ruleStmt = $pdo->prepare(
            'SELECT *
             FROM sr_quiz_result_rules
             WHERE quiz_id = :quiz_id
             ORDER BY priority DESC, id ASC'
        );
        $ruleStmt->execute(['quiz_id' => $sourceQuizId]);
        $insertRule = $pdo->prepare(
            'INSERT INTO sr_quiz_result_rules
                (quiz_id, result_id, rule_type, min_score, max_score, category_key, threshold_value, priority, settings_json, created_at, updated_at)
             VALUES
                (:quiz_id, :result_id, :rule_type, :min_score, :max_score, :category_key, :threshold_value, :priority, :settings_json, :created_at, :updated_at)'
        );
        foreach ($ruleStmt->fetchAll() as $rule) {
            $oldResultId = (int) ($rule['result_id'] ?? 0);
            if (!isset($resultIdMap[$oldResultId])) {
                continue;
            }
            $insertRule->execute([
                'quiz_id' => $newQuizId,
                'result_id' => $resultIdMap[$oldResultId],
                'rule_type' => (string) ($rule['rule_type'] ?? 'score_range'),
                'min_score' => $rule['min_score'] ?? null,
                'max_score' => $rule['max_score'] ?? null,
                'category_key' => (string) ($rule['category_key'] ?? '') !== '' ? (string) $rule['category_key'] : null,
                'threshold_value' => $rule['threshold_value'] ?? null,
                'priority' => (int) ($rule['priority'] ?? 0),
                'settings_json' => $rule['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (is_array($rewardPolicy)) {
            $insertPolicy = $pdo->prepare(
                'INSERT INTO sr_quiz_reward_policies
                    (quiz_id, status, trigger_type, result_id, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, settings_json, created_at, updated_at)
                 VALUES
                    (:quiz_id, :status, :trigger_type, NULL, :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, :sort_order, :settings_json, :created_at, :updated_at)'
            );
            $insertPolicy->execute([
                'quiz_id' => $newQuizId,
                'status' => (string) ($rewardPolicy['status'] ?? 'active'),
                'trigger_type' => (string) ($rewardPolicy['trigger_type'] ?? 'passed'),
                'reward_provider' => (string) ($rewardPolicy['reward_provider'] ?? 'ledger_asset'),
                'reward_module' => (string) ($rewardPolicy['reward_module'] ?? ''),
                'reward_code' => (string) ($rewardPolicy['reward_code'] ?? ''),
                'reward_amount' => $rewardPolicy['reward_amount'] ?? null,
                'dedupe_scope' => (string) ($rewardPolicy['dedupe_scope'] ?? 'per_quiz'),
                'sort_order' => (int) ($rewardPolicy['sort_order'] ?? 0),
                'settings_json' => $rewardPolicy['settings_json'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!empty($options['copy_sources'])) {
            $sourceStmt = $pdo->prepare(
                'SELECT *
                 FROM sr_quiz_sources
                 WHERE quiz_id = :quiz_id
                   AND status = \'active\'
                 ORDER BY sort_order ASC, id ASC'
            );
            $sourceStmt->execute(['quiz_id' => $sourceQuizId]);
            $insertSource = $pdo->prepare(
                'INSERT INTO sr_quiz_sources
                    (quiz_id, source_module, source_type, source_id, status, sort_order, cta_label, created_at, updated_at)
                 VALUES
                    (:quiz_id, :source_module, :source_type, :source_id, \'active\', :sort_order, :cta_label, :created_at, :updated_at)'
            );
            foreach ($sourceStmt->fetchAll() as $source) {
                $sourceModule = (string) ($source['source_module'] ?? '');
                $sourceType = (string) ($source['source_type'] ?? '');
                $sourceId = (int) ($source['source_id'] ?? 0);
                $valid = false;
                if ($sourceModule === 'content' && $sourceType === 'content_item' && sr_module_enabled($pdo, 'content')) {
                    $valid = in_array($sourceId, sr_quiz_content_source_ids_exist($pdo, [$sourceId]), true);
                } elseif ($sourceModule === 'community' && $sourceType === 'community_post' && sr_module_enabled($pdo, 'community')) {
                    $valid = in_array($sourceId, sr_quiz_community_source_ids_exist($pdo, [$sourceId]), true);
                }
                if (!$valid) {
                    $skippedSources[] = [
                        'source_module' => $sourceModule,
                        'source_type' => $sourceType,
                        'source_id' => $sourceId,
                    ];
                    continue;
                }
                $insertSource->execute([
                    'quiz_id' => $newQuizId,
                    'source_module' => $sourceModule,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'sort_order' => (int) ($source['sort_order'] ?? 0),
                    'cta_label' => (string) ($source['cta_label'] ?? '') !== '' ? (string) $source['cta_label'] : '퀴즈 풀기',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($skippedSources !== []) {
                $warnings[] = '삭제되었거나 사용할 수 없는 연결 대상은 복사하지 않았습니다.';
            }
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'quiz_id' => $newQuizId,
        'quiz_key' => $newQuizKey,
        'warnings' => $warnings,
        'skipped_sources' => $skippedSources,
        'skipped_reward_policy' => $skippedRewardPolicy,
        'skipped_member_group_keys' => $skippedMemberGroupKeys,
    ];
}

function sr_quiz_save_admin_quiz(PDO $pdo, array $values, int $accountId): int
{
    $quizId = (int) ($values['id'] ?? 0);
    $quizGroupId = (int) ($values['quiz_group_id'] ?? 0);
    $now = sr_now();
    $passScore = (string) ($values['pass_score'] ?? '') === '' ? null : (int) $values['pass_score'];
    $quizMode = in_array((string) ($values['quiz_mode'] ?? 'scored'), sr_quiz_modes(), true) ? (string) $values['quiz_mode'] : 'scored';
    $scoringModel = in_array((string) ($values['scoring_model'] ?? 'correct_answer'), sr_quiz_scoring_models(), true) ? (string) $values['scoring_model'] : 'correct_answer';
    $startsAt = sr_quiz_clean_admin_datetime((string) ($values['starts_at'] ?? ''));
    $endsAt = sr_quiz_clean_admin_datetime((string) ($values['ends_at'] ?? ''));
    $attemptLimitPolicy = (string) ($values['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($attemptLimitPolicy, sr_quiz_attempt_limit_policies(), true)) {
        $attemptLimitPolicy = 'unlimited';
    }
    $attemptLimitPeriodSeconds = $attemptLimitPolicy === 'per_period'
        ? max(1, (int) ($values['attempt_limit_period_seconds'] ?? 0))
        : null;
    $memberGroupKeysJson = json_encode(sr_quiz_member_group_keys_from_value($values['member_group_keys'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $commentsEnabled = !empty($values['comments_enabled']) ? 1 : 0;
    $secretCommentsEnabled = !empty($values['secret_comments_enabled']) ? 1 : 0;
    $commentExtraFieldsJson = sr_comment_extra_field_definitions_json($values['comment_extra_fields_json'] ?? '[]');
    $skinKey = sr_quiz_clean_optional_skin_key((string) ($values['skin_key'] ?? ''));
    $settings = sr_quiz_settings($pdo);
    $defaultCtaLabel = (string) ($settings['default_cta_label'] ?? '퀴즈 풀기');

    $pdo->beginTransaction();
    try {
        if ($quizId > 0) {
            $existingStmt = $pdo->prepare(
                'SELECT id
                 FROM sr_quiz_sets
                 WHERE id = :id
                   AND deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
            );
            $existingStmt->execute(['id' => $quizId]);
            if (!is_array($existingStmt->fetch())) {
                throw new RuntimeException('Quiz to update was not found.');
            }

            $stmt = $pdo->prepare(
                'UPDATE sr_quiz_sets
                 SET quiz_key = :quiz_key,
                     quiz_group_id = :quiz_group_id,
                     title = :title,
                     description = :description,
                     cover_image_url = :cover_image_url,
                     skin_key = :skin_key,
                     status = :status,
                     quiz_mode = :quiz_mode,
                     scoring_model = :scoring_model,
                     pass_score = :pass_score,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     attempt_limit_policy = :attempt_limit_policy,
                     attempt_limit_period_seconds = :attempt_limit_period_seconds,
                     member_group_keys_json = :member_group_keys_json,
                     comments_enabled = :comments_enabled,
                     secret_comments_enabled = :secret_comments_enabled,
                     reaction_preset_key = :reaction_preset_key,
                     reaction_comment_preset_key = :reaction_comment_preset_key,
                     comment_extra_fields_json = :comment_extra_fields_json,
                     reward_enabled = :reward_enabled,
                     updated_by_account_id = :updated_by_account_id,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'quiz_group_id' => $quizGroupId > 0 ? $quizGroupId : null,
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')),
                'skin_key' => $skinKey,
                'status' => (string) $values['status'],
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
                'comments_enabled' => $commentsEnabled,
                'secret_comments_enabled' => $secretCommentsEnabled,
                'reaction_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'comment_extra_fields_json' => $commentExtraFieldsJson,
                'reward_enabled' => (int) $values['reward_enabled'],
                'updated_by_account_id' => $accountId,
                'updated_at' => $now,
                'id' => $quizId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_sets
                    (quiz_group_id, quiz_key, title, description, cover_image_url, skin_key, status, quiz_mode, scoring_model, pass_score, starts_at, ends_at,
                     attempt_limit_policy, attempt_limit_period_seconds, member_group_keys_json, comments_enabled, secret_comments_enabled, reaction_preset_key, reaction_comment_preset_key, comment_extra_fields_json, reward_enabled,
                     created_by_account_id, updated_by_account_id, created_at, updated_at)
                 VALUES
                    (:quiz_group_id, :quiz_key, :title, :description, :cover_image_url, :skin_key, :status, :quiz_mode, :scoring_model, :pass_score, :starts_at, :ends_at,
                     :attempt_limit_policy, :attempt_limit_period_seconds, :member_group_keys_json, :comments_enabled, :secret_comments_enabled, :reaction_preset_key, :reaction_comment_preset_key, :comment_extra_fields_json, :reward_enabled,
                     :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'quiz_group_id' => $quizGroupId > 0 ? $quizGroupId : null,
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'cover_image_url' => sr_quiz_clean_cover_image_url((string) ($values['cover_image_url'] ?? '')),
                'skin_key' => $skinKey,
                'status' => (string) $values['status'],
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
                'comments_enabled' => $commentsEnabled,
                'secret_comments_enabled' => $secretCommentsEnabled,
                'reaction_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_preset_key'] ?? '') : '',
                'reaction_comment_preset_key' => sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $values['reaction_comment_preset_key'] ?? '') : '',
                'comment_extra_fields_json' => $commentExtraFieldsJson,
                'reward_enabled' => (int) $values['reward_enabled'],
                'created_by_account_id' => $accountId,
                'updated_by_account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $quizId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM sr_quiz_reward_policies WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_sources WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_result_rules WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_results WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $questionIds = $pdo->prepare('SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id');
        $questionIds->execute(['quiz_id' => $quizId]);
        foreach ($questionIds->fetchAll() as $questionRow) {
            $pdo->prepare('DELETE FROM sr_quiz_choices WHERE question_id = :question_id')->execute(['question_id' => (int) $questionRow['id']]);
        }
        $pdo->prepare('DELETE FROM sr_quiz_questions WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);

        $questionStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_questions
                (quiz_id, question_key, question_type, prompt, required, score_value, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :question_key, :question_type, :prompt, 1, :score_value, :sort_order, :created_at, :updated_at)'
        );
        $choiceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_choices
                (question_id, choice_key, label, is_correct, score_value, category_key, category_weight, sort_order, created_at, updated_at)
             VALUES
                (:question_id, :choice_key, :label, :is_correct, :score_value, :category_key, :category_weight, :sort_order, :created_at, :updated_at)'
        );
        foreach ((array) ($values['questions'] ?? []) as $questionIndex => $question) {
            $questionType = (string) ($question['question_type'] ?? 'single_choice');
            if (!in_array($questionType, sr_quiz_question_types(), true)) {
                $questionType = 'single_choice';
            }
            $questionStmt->execute([
                'quiz_id' => $quizId,
                'question_key' => (string) $question['question_key'],
                'question_type' => $questionType,
                'prompt' => (string) $question['prompt'],
                'score_value' => (int) $question['score_value'],
                'sort_order' => $questionIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $questionId = (int) $pdo->lastInsertId();
            foreach ((array) ($question['choices'] ?? []) as $choiceIndex => $choice) {
                $choiceStmt->execute([
                    'question_id' => $questionId,
                    'choice_key' => (string) $choice['choice_key'],
                    'label' => (string) $choice['label'],
                    'is_correct' => (int) $choice['is_correct'],
                    'score_value' => (int) $choice['is_correct'] === 1 ? (int) $question['score_value'] : 0,
                    'category_key' => (string) ($choice['category_key'] ?? '') !== '' ? (string) $choice['category_key'] : null,
                    'category_weight' => (int) ($choice['category_weight'] ?? 0),
                    'sort_order' => $choiceIndex,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ((int) ($values['reward_enabled'] ?? 0) === 1) {
            $rewardProvider = (string) ($values['reward_provider'] ?? 'ledger_asset');
            $rewardDedupeScope = in_array((string) ($values['reward_dedupe_scope'] ?? 'per_quiz'), sr_quiz_reward_dedupe_scopes(), true)
                ? (string) $values['reward_dedupe_scope']
                : 'per_quiz';
            $rewardModule = $rewardProvider === 'coupon' ? 'coupon' : (string) $values['reward_module'];
            $rewardCode = $rewardProvider === 'coupon' ? (string) (int) ($values['reward_coupon_definition_id'] ?? 0) : 'quiz_reward';
            $rewardAmount = $rewardProvider === 'coupon' ? 1 : (int) $values['reward_amount'];
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_reward_policies
                    (quiz_id, status, trigger_type, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, created_at, updated_at)
                 VALUES
                    (:quiz_id, \'active\', \'passed\', :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, 0, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_id' => $quizId,
                'reward_provider' => $rewardProvider,
                'reward_module' => $rewardModule,
                'reward_code' => $rewardCode,
                'reward_amount' => $rewardAmount,
                'dedupe_scope' => $rewardDedupeScope,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $resultStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_results
                (quiz_id, result_key, title, summary, body, status, sort_order, created_at, updated_at)
             VALUES
                (:quiz_id, :result_key, :title, :summary, NULL, \'active\', :sort_order, :created_at, :updated_at)'
        );
        $ruleStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_result_rules
                (quiz_id, result_id, rule_type, min_score, max_score, category_key, threshold_value, priority, created_at, updated_at)
             VALUES
                (:quiz_id, :result_id, :rule_type, :min_score, :max_score, :category_key, :threshold_value, :priority, :created_at, :updated_at)'
        );
        foreach (sr_quiz_result_rules_from_value((string) ($values['result_rules'] ?? '')) as $ruleIndex => $rule) {
            $resultStmt->execute([
                'quiz_id' => $quizId,
                'result_key' => (string) $rule['result_key'],
                'title' => (string) $rule['title'],
                'summary' => (string) $rule['summary'],
                'sort_order' => $ruleIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $resultId = (int) $pdo->lastInsertId();
            $ruleStmt->execute([
                'quiz_id' => $quizId,
                'result_id' => $resultId,
                'rule_type' => (string) ($rule['category_key'] ?? '') !== '' ? 'category_threshold' : 'score_range',
                'min_score' => $rule['min_score'],
                'max_score' => $rule['max_score'],
                'category_key' => (string) ($rule['category_key'] ?? '') !== '' ? (string) $rule['category_key'] : null,
                'threshold_value' => $rule['threshold_value'],
                'priority' => 1000 - $ruleIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sourceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_sources
                (quiz_id, source_module, source_type, source_id, status, sort_order, cta_label, created_at, updated_at)
             VALUES
                (:quiz_id, :source_module, :source_type, :source_id, \'active\', :sort_order, :cta_label, :created_at, :updated_at)'
        );
        foreach (sr_quiz_content_source_ids_from_value((string) ($values['content_source_ids'] ?? '')) as $sourceIndex => $contentId) {
            $sourceStmt->execute([
                'quiz_id' => $quizId,
                'source_module' => 'content',
                'source_type' => 'content_item',
                'source_id' => $contentId,
                'sort_order' => $sourceIndex,
                'cta_label' => $defaultCtaLabel,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        foreach (sr_quiz_content_source_ids_from_value((string) ($values['community_source_ids'] ?? '')) as $sourceIndex => $postId) {
            $sourceStmt->execute([
                'quiz_id' => $quizId,
                'source_module' => 'community',
                'source_type' => 'community_post',
                'source_id' => $postId,
                'sort_order' => $sourceIndex,
                'cta_label' => $defaultCtaLabel,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        sr_quiz_apply_group_setting_scopes($pdo, $quizId, $quizGroupId, $values, $accountId, $now);
        $pdo->commit();
        if (function_exists('sr_url_embed_mark_target_url_cache_stale')) {
            sr_url_embed_mark_target_url_cache_stale($pdo, 'quiz', 'quiz_set', $quizId);
        }
        return $quizId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function sr_quiz_permanently_delete(PDO $pdo, int $quizId, string $confirmationPhrase, int $accountId): array
{
    if ($quizId < 1) {
        return ['ok' => false, 'message' => '영구 삭제할 퀴즈를 찾을 수 없습니다.'];
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
        $stmt = $pdo->prepare('SELECT id, quiz_key, title, deleted_at FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NOT NULL LIMIT 1' . $forUpdateSql);
        $stmt->execute(['id' => $quizId]);
        $quiz = $stmt->fetch();
        if (!is_array($quiz)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '이미 영구 삭제되었거나 삭제 상태가 아닌 퀴즈입니다.'];
        }

        $quizKey = (string) ($quiz['quiz_key'] ?? '');
        $confirmationPhrase = trim($confirmationPhrase);
        if ($confirmationPhrase !== $quizKey && $confirmationPhrase !== (string) $quizId) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => '확인 문구가 퀴즈 ID 또는 Key와 일치하지 않습니다.'];
        }

        $commentIdsStmt = $pdo->prepare('SELECT id FROM sr_quiz_comments WHERE quiz_id = :quiz_id');
        $commentIdsStmt->execute(['quiz_id' => $quizId]);
        $commentIds = array_values(array_filter(array_map('intval', array_column($commentIdsStmt->fetchAll(), 'id'))));
        $questionIdsStmt = $pdo->prepare('SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id');
        $questionIdsStmt->execute(['quiz_id' => $quizId]);
        $questionIds = array_values(array_filter(array_map('intval', array_column($questionIdsStmt->fetchAll(), 'id'))));
        $urlEmbedCacheDeleted = function_exists('sr_url_embed_delete_owner_or_target_url_cache')
            ? sr_url_embed_delete_owner_or_target_url_cache($pdo, 'quiz', 'quiz_set', $quizId)
            : 0;
        $reactionRecordsDeleted = 0;
        if (function_exists('sr_reaction_delete_target_records')) {
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'quiz', 'quiz_set', [$quizId]);
            $reactionRecordsDeleted += sr_reaction_delete_target_records($pdo, 'quiz', 'comment', $commentIds);
        }
        if ($questionIds !== []) {
            $pdo->exec('DELETE FROM sr_quiz_choices WHERE question_id IN (' . implode(',', $questionIds) . ')');
        }
        $pdo->prepare('DELETE FROM sr_quiz_result_rules WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_results WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_sources WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_comments WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_questions WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare('DELETE FROM sr_quiz_setting_sources WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $deleteStmt = $pdo->prepare('DELETE FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NOT NULL');
        $deleteStmt->execute(['id' => $quizId]);
        if ($deleteStmt->rowCount() < 1) {
            throw new RuntimeException('Quiz permanent delete did not remove row.');
        }

        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'quiz.permanently_deleted',
            'target_type' => 'quiz',
            'target_id' => (string) $quizId,
            'result' => 'success',
            'message' => 'Deleted quiz body rows permanently removed.',
            'metadata' => [
                'quiz_key' => $quizKey,
                'deleted_at' => (string) ($quiz['deleted_at'] ?? ''),
                'questions_deleted' => count($questionIds),
                'comments_deleted' => count($commentIds),
                'url_embed_cache_deleted' => $urlEmbedCacheDeleted,
                'reaction_records_deleted' => $reactionRecordsDeleted,
            ],
        ]);

        $pdo->commit();

        return ['ok' => true, 'message' => '퀴즈를 영구 삭제했습니다. 응시와 보상 이력은 보존됩니다.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_quiz_soft_delete(PDO $pdo, int $quizId, int $accountId): bool
{
    if ($quizId < 1) {
        return false;
    }

    $now = sr_now();
    $deletedTitle = '삭제된 퀴즈';
    $deletedBody = '삭제된 퀴즈입니다.';
    $coverImageCleanupFailureId = 0;
    $oldStmt = $pdo->prepare('SELECT cover_image_url FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $oldStmt->execute(['id' => $quizId]);
    $oldRow = $oldStmt->fetch();
    $oldCoverImageUrl = is_array($oldRow) ? sr_quiz_clean_cover_image_url((string) ($oldRow['cover_image_url'] ?? '')) : '';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_quiz_sets
             SET title = :title,
                 description = :description,
                 cover_image_url = \'\',
                 status = \'archived\',
                 comments_enabled = 0,
                 secret_comments_enabled = 0,
                 reward_enabled = 0,
                 updated_by_account_id = :account_id,
                 updated_at = :updated_at,
                 deleted_at = :deleted_at
             WHERE id = :id
               AND deleted_at IS NULL'
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'description' => $deletedBody,
            'account_id' => $accountId,
            'updated_at' => $now,
            'deleted_at' => $now,
            'id' => $quizId,
        ]);
        $deleted = $stmt->rowCount() > 0;
        if (!$deleted) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare('UPDATE sr_quiz_questions SET prompt = :prompt, help_text = NULL, settings_json = NULL, updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'prompt' => $deletedBody,
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $driverName = '';
        try {
            $driverName = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable $exception) {
            $driverName = '';
        }
        if ($driverName === 'sqlite') {
            $pdo->prepare(
                'UPDATE sr_quiz_choices
                 SET label = :label,
                     description = NULL,
                     settings_json = NULL,
                     updated_at = :updated_at
                 WHERE question_id IN (SELECT id FROM sr_quiz_questions WHERE quiz_id = :quiz_id)'
            )->execute([
                'label' => '삭제된 선택지',
                'updated_at' => $now,
                'quiz_id' => $quizId,
            ]);
        } else {
            $pdo->prepare(
                'UPDATE sr_quiz_choices c
                 INNER JOIN sr_quiz_questions q ON q.id = c.question_id
                 SET c.label = :label,
                     c.description = NULL,
                     c.settings_json = NULL,
                     c.updated_at = :updated_at
                 WHERE q.quiz_id = :quiz_id'
            )->execute([
                'label' => '삭제된 선택지',
                'updated_at' => $now,
                'quiz_id' => $quizId,
            ]);
        }
        $pdo->prepare('UPDATE sr_quiz_results SET title = :title, summary = \'\', body = \'\', updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'title' => '삭제된 결과',
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_comments SET author_public_name_snapshot = \'\', body_text = :body_text, status = \'deleted\', deleted_at = COALESCE(deleted_at, :deleted_at), updated_at = :updated_at WHERE quiz_id = :quiz_id')->execute([
            'body_text' => '삭제된 댓글입니다.',
            'deleted_at' => $now,
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        $pdo->prepare(
            "UPDATE sr_quiz_attempts
             SET source_title_snapshot = '',
                 source_url_snapshot = '',
                 return_url = '',
                 answer_snapshot_json = '{}',
                 scoring_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 updated_at = :updated_at
             WHERE quiz_id = :quiz_id"
        )->execute([
            'updated_at' => $now,
            'quiz_id' => $quizId,
        ]);
        if ($driverName === 'sqlite') {
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_answers
                 SET answer_text = NULL,
                     answer_snapshot_json = '{}',
                     category_scores_json = NULL
                 WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)"
            )->execute(['quiz_id' => $quizId]);
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_result_scores
                 SET snapshot_json = '{}'
                 WHERE attempt_id IN (SELECT id FROM sr_quiz_attempts WHERE quiz_id = :quiz_id)"
            )->execute(['quiz_id' => $quizId]);
        } else {
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_answers aa
                 INNER JOIN sr_quiz_attempts a ON a.id = aa.attempt_id
                 SET aa.answer_text = NULL,
                     aa.answer_snapshot_json = '{}',
                     aa.category_scores_json = NULL
                 WHERE a.quiz_id = :quiz_id"
            )->execute(['quiz_id' => $quizId]);
            $pdo->prepare(
                "UPDATE sr_quiz_attempt_result_scores ars
                 INNER JOIN sr_quiz_attempts a ON a.id = ars.attempt_id
                 SET ars.snapshot_json = '{}'
                 WHERE a.quiz_id = :quiz_id"
            )->execute(['quiz_id' => $quizId]);
        }
        $pdo->prepare(
            "UPDATE sr_quiz_reward_grants
             SET request_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 error_message = '',
                 resolution_note = ''
             WHERE quiz_id = :quiz_id"
        )->execute(['quiz_id' => $quizId]);
        if ($oldCoverImageUrl !== '') {
            $storage = sr_quiz_cover_image_storage_reference_from_url($oldCoverImageUrl);
            if (is_array($storage)) {
                $driver = (string) ($storage['driver'] ?? 'local');
                $key = (string) ($storage['key'] ?? '');
                if ($key !== '' && sr_quiz_cover_image_reference_count($pdo, $oldCoverImageUrl, $quizId) < 1) {
                    $coverImageCleanupFailureId = sr_quiz_record_storage_cleanup_pending($pdo, 'quiz_cover_image', $quizId, $driver, $key);
                }
            }
        }

        $pdo->commit();
        if (function_exists('sr_url_embed_mark_target_url_cache_stale')) {
            sr_url_embed_mark_target_url_cache_stale($pdo, 'quiz', 'quiz_set', $quizId);
        }
        if ($oldCoverImageUrl !== '') {
            sr_quiz_delete_cover_image_storage($pdo, $oldCoverImageUrl, $quizId, $coverImageCleanupFailureId);
        }
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
