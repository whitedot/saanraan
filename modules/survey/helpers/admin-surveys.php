<?php

declare(strict_types=1);

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
