<?php

declare(strict_types=1);

function sr_survey_account_can_respond(PDO $pdo, array $survey, int $accountId): array
{
    if ((int) ($survey['login_required'] ?? 1) === 1 && $accountId < 1) {
        return ['allowed' => false, 'message' => '로그인 후 설문에 참여할 수 있습니다.'];
    }
    $groupKeys = sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]');
    if ($groupKeys !== []) {
        if ($accountId < 1) {
            return ['allowed' => false, 'message' => '참여 대상 회원 그룹에 속한 회원만 참여할 수 있습니다.'];
        }
        require_once SR_ROOT . '/modules/member/helpers/groups.php';
        sr_member_group_evaluate_account($pdo, $accountId);
        if (!sr_member_account_in_any_group($pdo, $accountId, $groupKeys)) {
            return ['allowed' => false, 'message' => '참여 대상 회원 그룹에 속한 회원만 참여할 수 있습니다.'];
        }
    }
    if ((string) ($survey['status'] ?? '') !== 'active' || !sr_survey_public_window_is_open($survey)) {
        return ['allowed' => false, 'message' => '현재 참여할 수 없는 설문입니다.'];
    }

    $policy = (string) ($survey['response_limit_policy'] ?? 'per_survey_once');
    if (!in_array($policy, sr_survey_response_limit_policies(), true)) {
        $policy = 'per_survey_once';
    }
    if ($accountId > 0 && $policy !== 'unlimited') {
        $params = [
            'survey_id' => (int) ($survey['id'] ?? 0),
            'account_id' => $accountId,
        ];
        $sql = 'SELECT COUNT(*) FROM sr_survey_responses WHERE survey_id = :survey_id AND account_id = :account_id';
        if ($policy === 'per_period') {
            $seconds = (int) ($survey['response_limit_period_seconds'] ?? 0);
            if ($seconds < 1) {
                return ['allowed' => false, 'message' => '응답 제한 기간 설정을 확인해야 합니다.'];
            }
            $sql .= ' AND submitted_at >= :since';
            $params['since'] = date('Y-m-d H:i:s', max(0, time() - $seconds));
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['allowed' => false, 'message' => '이미 참여한 설문입니다.'];
        }
    }
    if ($accountId < 1 && (int) ($survey['anonymous_allowed'] ?? 0) === 1 && $policy !== 'unlimited') {
        $params = [
            'survey_id' => (int) ($survey['id'] ?? 0),
            'user_agent_hash' => sr_survey_current_user_agent_hash(),
            'ip_hash' => sr_survey_current_ip_hash(),
        ];
        $sql = 'SELECT COUNT(*) FROM sr_survey_responses
                WHERE survey_id = :survey_id
                  AND account_id IS NULL
                  AND user_agent_hash = :user_agent_hash
                  AND ip_hash = :ip_hash';
        if ($policy === 'per_period') {
            $seconds = (int) ($survey['response_limit_period_seconds'] ?? 0);
            if ($seconds < 1) {
                return ['allowed' => false, 'message' => '응답 제한 기간 설정을 확인해야 합니다.'];
            }
            $sql .= ' AND submitted_at >= :since';
            $params['since'] = date('Y-m-d H:i:s', max(0, time() - $seconds));
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['allowed' => false, 'message' => '이미 참여한 설문입니다.'];
        }
    }

    return ['allowed' => true, 'message' => ''];
}

function sr_survey_current_user_agent_hash(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function sr_survey_current_ip_hash(): string
{
    return hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function sr_survey_asset_options(PDO $pdo): array
{
    require_once SR_ROOT . '/modules/member/helpers/assets.php';
    $options = [];
    foreach (sr_member_ledger_asset_definitions($pdo) as $moduleKey => $asset) {
        $transactionFunction = (string) ($asset['transaction_function'] ?? '');
        if (function_exists($transactionFunction)) {
            $options[$moduleKey] = $asset;
        }
    }

    return $options;
}

function sr_survey_coupon_definition_is_available(PDO $pdo, int $definitionId): bool
{
    if ($definitionId < 1 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return false;
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return false;
    }
    if (function_exists('sr_coupon_usage_enabled') && !sr_coupon_usage_enabled($pdo)) {
        return false;
    }
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT id FROM sr_coupon_definitions
         WHERE id = :id
           AND status = 'active'
           AND (valid_from IS NULL OR valid_from <= :now_from)
           AND (valid_until IS NULL OR valid_until >= :now_until)
         LIMIT 1"
    );
    $stmt->execute(['id' => $definitionId, 'now_from' => $now, 'now_until' => $now]);

    return is_array($stmt->fetch());
}

function sr_survey_coupon_definitions(PDO $pdo, int $includeDefinitionId = 0): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return [];
    }
    if (function_exists('sr_coupon_usage_enabled') && !sr_coupon_usage_enabled($pdo)) {
        return [];
    }
    $stmt = $pdo->query("SELECT id, coupon_key, title FROM sr_coupon_definitions WHERE status = 'active' ORDER BY title ASC, id ASC LIMIT 200");
    $definitions = $stmt->fetchAll();
    if ($includeDefinitionId < 1) {
        return $definitions;
    }
    foreach ($definitions as $definition) {
        if ((int) ($definition['id'] ?? 0) === $includeDefinitionId) {
            return $definitions;
        }
    }

    $currentStmt = $pdo->prepare('SELECT id, coupon_key, title FROM sr_coupon_definitions WHERE id = :id LIMIT 1');
    $currentStmt->execute(['id' => $includeDefinitionId]);
    $current = $currentStmt->fetch();
    if (is_array($current)) {
        $definitions[] = $current;
    }

    return $definitions;
}

function sr_survey_selected_answers_from_post(array $questions): array
{
    $posted = $_POST['answers'] ?? [];
    $answers = [];
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $type = (string) ($question['question_type'] ?? 'single_choice');
        $value = is_array($posted) ? ($posted[$questionId] ?? null) : null;
        if (in_array($type, ['text', 'short_text', 'long_text'], true)) {
            $answers[$questionId] = sr_survey_clean_text((string) $value, 2000);
            continue;
        }
        if (in_array($type, ['number', 'rating', 'scale'], true)) {
            $answers[$questionId] = trim((string) $value);
            continue;
        }
        $ids = is_array($value) ? array_map('intval', $value) : [(int) $value];
        $ids = array_values(array_filter(array_unique($ids), static fn (int $id): bool => $id > 0));
        if ($type === 'single_choice' && count($ids) > 1) {
            $ids = [(int) $ids[0]];
        }
        $answers[$questionId] = $ids;
    }

    return $answers;
}

function sr_survey_other_answers_from_post(array $questions): array
{
    $posted = $_POST['other_answers'] ?? [];
    $answers = [];
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        if (!in_array((string) ($question['question_type'] ?? ''), ['single_choice', 'multiple_choice'], true)) {
            continue;
        }

        $postedQuestion = is_array($posted) ? ($posted[$questionId] ?? []) : [];
        if (!is_array($postedQuestion)) {
            continue;
        }

        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $choiceId = (int) ($choice['id'] ?? 0);
            if ($choiceId < 1 || (int) ($choice['is_other'] ?? 0) !== 1) {
                continue;
            }

            $answers[$questionId][$choiceId] = sr_survey_clean_text((string) ($postedQuestion[$choiceId] ?? ''), 500);
        }
    }

    return $answers;
}

function sr_survey_submit_response(PDO $pdo, array $survey, array $questions, int $accountId, array $answers, array $assetOptions, bool $consentAccepted = false, bool $isTest = false, array $otherAnswers = []): array
{
    $surveyId = (int) ($survey['id'] ?? 0);
    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $lockClause = '';
        try {
            $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        } catch (Throwable $exception) {
            $lockClause = ' FOR UPDATE';
        }
        $locked = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1' . $lockClause);
        $locked->execute(['id' => $surveyId]);
        $survey = $locked->fetch();
        if (!is_array($survey)) {
            throw new RuntimeException('현재 참여할 수 없는 설문입니다.');
        }
        if (!$isTest) {
            $access = sr_survey_account_can_respond($pdo, $survey, $accountId);
            if (empty($access['allowed'])) {
                throw new RuntimeException((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.'));
            }
        } elseif ($accountId < 1) {
            throw new RuntimeException('관리자 테스트 제출은 로그인 계정이 필요합니다.');
        }
        $questions = sr_survey_questions_with_choices($pdo, $surveyId);
        if ($questions === []) {
            throw new RuntimeException('응답 가능한 문항이 없습니다.');
        }
        if ((int) ($survey['consent_required'] ?? 0) === 1 && !$consentAccepted) {
            throw new RuntimeException('참여 동의가 필요합니다.');
        }
        foreach ($questions as $question) {
            $questionId = (int) ($question['id'] ?? 0);
            $answer = $answers[$questionId] ?? null;
            $empty = is_array($answer) ? $answer === [] : trim((string) $answer) === '';
            if ((int) ($question['required'] ?? 1) === 1 && $empty) {
                throw new RuntimeException('필수 문항에 답변해 주세요.');
            }
            sr_survey_validate_answer($question, $answer, $otherAnswers[$questionId] ?? []);
        }

        $snapshotJson = json_encode(['answers' => $answers, 'other_answers' => $otherAnswers], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $consentSnapshotJson = json_encode([
            'consent_required' => (int) ($survey['consent_required'] ?? 0) === 1,
            'consent_accepted' => $consentAccepted,
            'consent_text' => (string) ($survey['consent_text'] ?? ''),
            'privacy_notice' => (string) ($survey['privacy_notice'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $metadataSnapshotJson = json_encode([
            'research_purpose' => (string) ($survey['research_purpose'] ?? ''),
            'target_population' => (string) ($survey['target_population'] ?? ''),
            'recruitment_method' => (string) ($survey['recruitment_method'] ?? ''),
            'project_brief' => (string) ($survey['project_brief'] ?? ''),
            'sponsor_name' => (string) ($survey['sponsor_name'] ?? ''),
            'research_region' => (string) ($survey['research_region'] ?? ''),
            'research_language' => (string) ($survey['research_language'] ?? ''),
            'fieldwork_method' => (string) ($survey['fieldwork_method'] ?? ''),
            'sample_frame' => (string) ($survey['sample_frame'] ?? ''),
            'sample_method' => (string) ($survey['sample_method'] ?? ''),
            'target_sample_size' => (int) ($survey['target_sample_size'] ?? 0),
            'quota_policy' => (string) ($survey['quota_policy'] ?? ''),
            'response_rate_basis' => (string) ($survey['response_rate_basis'] ?? ''),
            'analysis_plan' => (string) ($survey['analysis_plan'] ?? ''),
            'weighting_policy' => (string) ($survey['weighting_policy'] ?? ''),
            'margin_error_note' => (string) ($survey['margin_error_note'] ?? ''),
            'methodology_disclosure' => (string) ($survey['methodology_disclosure'] ?? ''),
            'ethics_note' => (string) ($survey['ethics_note'] ?? ''),
            'sensitive_data_policy' => (string) ($survey['sensitive_data_policy'] ?? ''),
            'recontact_policy' => (string) ($survey['recontact_policy'] ?? ''),
            'withdrawal_policy' => (string) ($survey['withdrawal_policy'] ?? ''),
            'vendor_name' => (string) ($survey['vendor_name'] ?? ''),
            'external_channel_policy' => (string) ($survey['external_channel_policy'] ?? ''),
            'invite_token_policy' => (string) ($survey['invite_token_policy'] ?? ''),
            'qa_status' => (string) ($survey['qa_status'] ?? 'unchecked'),
            'questionnaire_version' => (int) ($survey['questionnaire_version'] ?? 1),
            'member_group_keys' => sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]'),
            'estimated_minutes' => (int) ($survey['estimated_minutes'] ?? 0),
            'organizer_name' => (string) ($survey['organizer_name'] ?? ''),
            'contact_text' => (string) ($survey['contact_text'] ?? ''),
            'anonymous_allowed' => (int) ($survey['anonymous_allowed'] ?? 0) === 1,
            'login_required' => (int) ($survey['login_required'] ?? 1) === 1,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare(
            'INSERT INTO sr_survey_responses
                (survey_id, account_id, status, quality_status, consent_snapshot_json, metadata_snapshot_json, is_test, submitted_at, answer_snapshot_json, user_agent_hash, ip_hash, created_at, updated_at)
             VALUES
                (:survey_id, :account_id, \'submitted\', \'accepted\', :consent_snapshot_json, :metadata_snapshot_json, :is_test, :submitted_at, :answer_snapshot_json, :user_agent_hash, :ip_hash, :created_at, :updated_at)'
        );
        $stmt->execute([
            'survey_id' => $surveyId,
            'account_id' => $accountId > 0 ? $accountId : null,
            'consent_snapshot_json' => is_string($consentSnapshotJson) ? $consentSnapshotJson : '{}',
            'metadata_snapshot_json' => is_string($metadataSnapshotJson) ? $metadataSnapshotJson : '{}',
            'is_test' => $isTest ? 1 : 0,
            'submitted_at' => $now,
            'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
            'user_agent_hash' => sr_survey_current_user_agent_hash(),
            'ip_hash' => sr_survey_current_ip_hash(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $responseId = (int) $pdo->lastInsertId();

        sr_survey_insert_response_answers($pdo, $responseId, $questions, $answers, $now, $otherAnswers);
        $rewardGrant = null;
        if (!$isTest && $accountId > 0 && (int) ($survey['reward_enabled'] ?? 0) === 1) {
            $policy = sr_survey_active_reward_policy($pdo, $surveyId);
            if (is_array($policy)) {
                $rewardGrant = sr_survey_issue_reward_grant($pdo, $survey, $responseId, $accountId, $policy, $assetOptions, $now);
            }
        }
        $pdo->commit();

        return ['response_id' => $responseId, 'reward_grant' => $rewardGrant];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_survey_validate_answer(array $question, mixed $answer, array $otherAnswers = []): void
{
    $type = (string) ($question['question_type'] ?? 'single_choice');
    if (in_array($type, ['single_choice', 'multiple_choice'], true) && is_array($answer) && $answer !== []) {
        $validChoiceIds = array_map(static fn (array $choice): int => (int) ($choice['id'] ?? 0), (array) ($question['choices'] ?? []));
        $validChoiceIds = array_values(array_filter($validChoiceIds, static fn (int $choiceId): bool => $choiceId > 0));
        $nonresponseChoiceIds = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            if ((int) ($choice['is_nonresponse'] ?? 0) === 1) {
                $nonresponseChoiceIds[] = (int) ($choice['id'] ?? 0);
            }
            if ((int) ($choice['is_other'] ?? 0) === 1 && in_array((int) ($choice['id'] ?? 0), array_map('intval', $answer), true)) {
                $otherText = trim((string) ($otherAnswers[(int) ($choice['id'] ?? 0)] ?? ''));
                if ($otherText === '') {
                    throw new RuntimeException('기타 답변을 입력해 주세요.');
                }
            }
        }
        foreach ($answer as $choiceId) {
            if (!in_array((int) $choiceId, $validChoiceIds, true)) {
                throw new RuntimeException('선택지 값을 확인해 주세요.');
            }
        }
        if ($type === 'multiple_choice' && count($answer) > 1 && array_intersect(array_map('intval', $answer), $nonresponseChoiceIds) !== []) {
            throw new RuntimeException('무응답 선택지는 다른 선택지와 함께 고를 수 없습니다.');
        }
    }
    if ($type === 'multiple_choice') {
        $count = is_array($answer) ? count($answer) : 0;
        $min = $question['min_choices'] === null ? null : (int) $question['min_choices'];
        $max = $question['max_choices'] === null ? null : (int) $question['max_choices'];
        if ($min !== null && $count < $min) {
            throw new RuntimeException('복수 선택 문항의 최소 선택 수를 확인해 주세요.');
        }
        if ($max !== null && $max > 0 && $count > $max) {
            throw new RuntimeException('복수 선택 문항의 최대 선택 수를 확인해 주세요.');
        }
    }
    if (in_array($type, ['number', 'rating', 'scale'], true) && trim((string) $answer) !== '') {
        if (!is_numeric((string) $answer)) {
            throw new RuntimeException('숫자 문항에는 숫자만 입력해 주세요.');
        }
        $number = (float) $answer;
        if ((int) ($question['allow_decimal'] ?? 0) !== 1 && floor($number) != $number) {
            throw new RuntimeException('정수만 입력할 수 있는 문항입니다.');
        }
        if ($question['number_min'] !== null && $number < (float) $question['number_min']) {
            throw new RuntimeException('숫자 문항의 최소값을 확인해 주세요.');
        }
        if ($question['number_max'] !== null && $number > (float) $question['number_max']) {
            throw new RuntimeException('숫자 문항의 최대값을 확인해 주세요.');
        }
        if ($type === 'rating') {
            $scalePoints = max(1, (int) ($question['scale_points'] ?? 5));
            if ($number < 1 || $number > $scalePoints) {
                throw new RuntimeException('별점 범위를 확인해 주세요.');
            }
        }
    }
}

function sr_survey_insert_response_answers(PDO $pdo, int $responseId, array $questions, array $answers, string $now, array $otherAnswers = []): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_survey_response_answers
            (response_id, question_id, question_key, choice_id, choice_key, answer_text, answer_number, other_text, answer_snapshot_json, created_at)
         VALUES
            (:response_id, :question_id, :question_key, :choice_id, :choice_key, :answer_text, :answer_number, :other_text, :answer_snapshot_json, :created_at)'
    );
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $type = (string) ($question['question_type'] ?? 'single_choice');
        $answer = $answers[$questionId] ?? (in_array($type, ['text', 'short_text', 'long_text', 'number', 'rating', 'scale'], true) ? '' : []);
        $choices = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            if (is_array($answer) && in_array((int) ($choice['id'] ?? 0), $answer, true)) {
                $choices[] = $choice;
            }
        }
        $firstChoice = is_array($choices[0] ?? null) ? $choices[0] : [];
        $snapshotJson = json_encode([
            'question_prompt' => (string) ($question['prompt'] ?? ''),
            'choice_labels' => array_map(static fn (array $choice): string => (string) ($choice['label'] ?? ''), $choices),
            'answer_text' => in_array($type, ['text', 'short_text', 'long_text'], true) ? (string) $answer : '',
            'answer_number' => in_array($type, ['number', 'rating', 'scale'], true) && trim((string) $answer) !== '' ? (float) $answer : null,
            'other_answers' => $otherAnswers[$questionId] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (in_array($type, ['single_choice', 'multiple_choice'], true) && $choices !== []) {
            foreach ($choices as $choice) {
                $choiceId = (int) ($choice['id'] ?? 0);
                $stmt->execute([
                    'response_id' => $responseId,
                    'question_id' => $questionId,
                    'question_key' => (string) ($question['question_key'] ?? ''),
                    'choice_id' => $choiceId,
                    'choice_key' => (string) ($choice['choice_key'] ?? ''),
                    'answer_text' => null,
                    'answer_number' => null,
                    'other_text' => (int) ($choice['is_other'] ?? 0) === 1 ? (string) (($otherAnswers[$questionId][$choiceId] ?? '')) : null,
                    'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
                    'created_at' => $now,
                ]);
            }
            continue;
        }
        $stmt->execute([
            'response_id' => $responseId,
            'question_id' => $questionId,
            'question_key' => (string) ($question['question_key'] ?? ''),
            'choice_id' => isset($firstChoice['id']) ? (int) $firstChoice['id'] : null,
            'choice_key' => implode(',', array_filter(array_map(static fn (array $choice): string => (string) ($choice['choice_key'] ?? ''), $choices))),
            'answer_text' => in_array($type, ['text', 'short_text', 'long_text'], true) ? (string) $answer : null,
            'answer_number' => in_array($type, ['number', 'rating', 'scale'], true) && trim((string) $answer) !== '' ? (string) $answer : null,
            'other_text' => null,
            'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
            'created_at' => $now,
        ]);
    }
}

function sr_survey_active_reward_policy(PDO $pdo, int $surveyId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM sr_survey_reward_policies WHERE survey_id = :survey_id AND status = 'active' ORDER BY sort_order ASC, id ASC LIMIT 1");
    $stmt->execute(['survey_id' => $surveyId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_survey_reward_log_statuses(): array
{
    return ['pending', 'granted', 'failed', 'duplicate'];
}

function sr_survey_reward_log_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
        'duplicate' => '중복',
    ][$status] ?? $status;
}

function sr_survey_reward_log_filters_from_request(): array
{
    $status = sr_survey_clean_key(sr_get_string('status', 30), 30);
    $provider = sr_survey_clean_key(sr_get_string('provider', 30), 30);
    $surveyId = max(0, (int) sr_get_string('survey_id', 20));

    return [
        'survey_id' => $surveyId,
        'status' => in_array($status, sr_survey_reward_log_statuses(), true) ? $status : '',
        'provider' => in_array($provider, sr_survey_reward_providers(), true) ? $provider : '',
        'q' => sr_survey_clean_single_line(sr_get_string('q', 120), 120),
    ];
}

function sr_survey_reward_log_where_sql(array $filters, array &$params): string
{
    $where = ['s.deleted_at IS NULL'];
    $surveyId = (int) ($filters['survey_id'] ?? 0);
    if ($surveyId > 0) {
        $where[] = 'g.survey_id = :survey_id';
        $params['survey_id'] = $surveyId;
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status !== '') {
        $where[] = 'g.status = :status';
        $params['status'] = $status;
    }

    $provider = (string) ($filters['provider'] ?? '');
    if ($provider !== '') {
        $where[] = 'g.reward_provider = :provider';
        $params['provider'] = $provider;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $qAccountId = (int) ($filters['q_account_id'] ?? 0);
        if (preg_match('/\A[1-9][0-9]*\z/', $q) === 1) {
            $where[] = '(g.response_id = :q_id OR g.provider_reference_id = :q_text)';
            $params['q_id'] = (int) $q;
            $params['q_text'] = $q;
        } else {
            $keywordWhere = ['s.survey_key LIKE :q_like', 's.title LIKE :q_like', 'g.reward_module LIKE :q_like', 'g.reward_code LIKE :q_like', 'g.error_message LIKE :q_like'];
            $params['q_like'] = '%' . $q . '%';
            if ($qAccountId > 0) {
                $keywordWhere[] = 'g.account_id = :q_account_id';
                $params['q_account_id'] = $qAccountId;
            }
            $where[] = '(' . implode(' OR ', $keywordWhere) . ')';
        }
    }

    return implode(' AND ', $where);
}

function sr_survey_reward_log_count(PDO $pdo, array $filters = []): int
{
    $params = [];
    $whereSql = sr_survey_reward_log_where_sql($filters, $params);
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM sr_survey_reward_grants g
         INNER JOIN sr_survey_forms s ON s.id = g.survey_id
         LEFT JOIN sr_survey_responses r ON r.id = g.response_id
         WHERE ' . $whereSql
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_survey_reward_logs(PDO $pdo, int $limit = 50, int $offset = 0, array $filters = []): array
{
    $params = [];
    $whereSql = sr_survey_reward_log_where_sql($filters, $params);
    $params['limit_value'] = max(1, min(200, $limit));
    $params['offset_value'] = max(0, $offset);
    $stmt = $pdo->prepare(
        'SELECT g.*, s.survey_key, s.title AS survey_title, r.quality_status, r.submitted_at, r.is_test,
                account.email AS account_email, account.display_name AS account_display_name
         FROM sr_survey_reward_grants g
         INNER JOIN sr_survey_forms s ON s.id = g.survey_id
         LEFT JOIN sr_survey_responses r ON r.id = g.response_id
         LEFT JOIN sr_member_accounts account ON account.id = g.account_id
         WHERE ' . $whereSql . '
         ORDER BY g.id DESC
         LIMIT :limit_value OFFSET :offset_value'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, in_array($key, ['limit_value', 'offset_value', 'q_id'], true) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_survey_issue_reward_grant(PDO $pdo, array $survey, int $responseId, int $accountId, array $policy, array $assetOptions, string $now): array
{
    $surveyId = (int) ($survey['id'] ?? 0);
    $policyId = (int) ($policy['id'] ?? 0);
    $provider = (string) ($policy['reward_provider'] ?? 'ledger_asset');
    $module = (string) ($policy['reward_module'] ?? '');
    $amount = (int) ($policy['reward_amount'] ?? 0);
    $scope = in_array((string) ($policy['dedupe_scope'] ?? 'per_survey'), sr_survey_reward_dedupe_scopes(), true) ? (string) $policy['dedupe_scope'] : 'per_survey';
    $dedupeParts = ['survey.reward', 'account', (string) $accountId, 'survey', (string) $surveyId, 'policy', (string) $policyId, 'provider', $provider, 'module', $module];
    if ($scope === 'per_response') {
        $dedupeParts[] = 'response';
        $dedupeParts[] = (string) $responseId;
    }
    $dedupeKey = implode(':', $dedupeParts);
    $snapshotJson = json_encode(['survey_key' => (string) ($survey['survey_key'] ?? ''), 'reward_provider' => $provider, 'reward_module' => $module, 'reward_amount' => $amount], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $insertVerb = 'INSERT IGNORE';
    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $insertVerb = 'INSERT OR IGNORE';
    }
    $pdo->prepare(
        $insertVerb . ' INTO sr_survey_reward_grants
            (survey_id, response_id, reward_policy_id, account_id, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, dedupe_key, status, request_snapshot_json, created_at, updated_at)
         VALUES
            (:survey_id, :response_id, :reward_policy_id, :account_id, :reward_provider, :reward_module, :reward_code, :reward_amount, :dedupe_scope, :dedupe_key, \'pending\', :request_snapshot_json, :created_at, :updated_at)'
    )->execute([
        'survey_id' => $surveyId,
        'response_id' => $responseId,
        'reward_policy_id' => $policyId,
        'account_id' => $accountId,
        'reward_provider' => $provider,
        'reward_module' => $module,
        'reward_code' => (string) ($policy['reward_code'] ?? 'survey_reward'),
        'reward_amount' => $amount,
        'dedupe_scope' => $scope,
        'dedupe_key' => $dedupeKey,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1' . $lockClause);
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $stmt->fetch();
    if (!is_array($grant)) {
        return [];
    }
    if ((int) ($grant['response_id'] ?? 0) !== $responseId && $scope !== 'per_response') {
        $grant['status'] = 'duplicate';
        $grant['error_message'] = '';
        return $grant;
    }
    if ((string) ($grant['status'] ?? '') === 'granted') {
        return $grant;
    }

    $grantId = (int) ($grant['id'] ?? 0);
    sr_survey_refresh_reward_grant_for_retry($pdo, $grantId, [
        'response_id' => $responseId,
        'reward_policy_id' => $policyId,
        'reward_provider' => $provider,
        'reward_module' => $module,
        'reward_code' => (string) ($policy['reward_code'] ?? 'survey_reward'),
        'reward_amount' => $amount,
        'dedupe_scope' => $scope,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'updated_at' => $now,
    ]);
    if ($provider === 'coupon') {
        return sr_survey_issue_coupon_reward_grant($pdo, $survey, $grantId, $responseId, $accountId, $policy, $now);
    }
    if ($provider !== 'ledger_asset' || !isset($assetOptions[$module])) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, '보상 종류 또는 자산 계약을 찾을 수 없습니다.', $now);
    }
    $transactionFunction = (string) ($assetOptions[$module]['transaction_function'] ?? '');
    if (!function_exists($transactionFunction)) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, '보상 자산 거래 함수를 찾을 수 없습니다.', $now);
    }
    try {
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => (string) ($assetOptions[$module]['credit_type'] ?? 'grant'),
            'reason' => '설문 보상: ' . (string) ($survey['title'] ?? ''),
            'reference_type' => 'survey_reward',
            'reference_id' => (string) $grantId,
            'created_by_account_id' => null,
        ]);
        sr_survey_mark_reward_grant_granted($pdo, $grantId, $responseId, 'survey_reward', (string) $grantId, ['transaction_id' => $transactionId], $now);
    } catch (Throwable $exception) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $stmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_survey_refresh_reward_grant_for_retry(PDO $pdo, int $grantId, array $values): void
{
    if ($grantId < 1) {
        return;
    }

    $pdo->prepare(
        'UPDATE sr_survey_reward_grants
         SET response_id = :response_id,
             reward_policy_id = :reward_policy_id,
             reward_provider = :reward_provider,
             reward_module = :reward_module,
             reward_code = :reward_code,
             reward_amount = :reward_amount,
             dedupe_scope = :dedupe_scope,
             request_snapshot_json = :request_snapshot_json,
             status = \'pending\',
             error_message = NULL,
             failed_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'granted\''
    )->execute([
        'response_id' => (int) ($values['response_id'] ?? 0),
        'reward_policy_id' => (int) ($values['reward_policy_id'] ?? 0),
        'reward_provider' => (string) ($values['reward_provider'] ?? ''),
        'reward_module' => (string) ($values['reward_module'] ?? ''),
        'reward_code' => (string) ($values['reward_code'] ?? ''),
        'reward_amount' => $values['reward_amount'] ?? null,
        'dedupe_scope' => (string) ($values['dedupe_scope'] ?? 'per_survey'),
        'request_snapshot_json' => (string) ($values['request_snapshot_json'] ?? '{}'),
        'updated_at' => (string) ($values['updated_at'] ?? sr_now()),
        'id' => $grantId,
    ]);
}

function sr_survey_issue_coupon_reward_grant(PDO $pdo, array $survey, int $grantId, int $responseId, int $accountId, array $policy, string $now): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, '쿠폰 모듈이 활성화되어 있지 않습니다.', $now);
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    $definitionId = (int) ($policy['reward_code'] ?? 0);
    if (!function_exists('sr_coupon_issue_to_account') || !sr_survey_coupon_definition_is_available($pdo, $definitionId)) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, '사용 가능한 보상 쿠폰이 아닙니다.', $now);
    }
    try {
        $issueId = sr_coupon_issue_to_account($pdo, $definitionId, $accountId, '설문 보상: ' . (string) ($survey['title'] ?? ''), null, null);
        sr_survey_mark_reward_grant_granted($pdo, $grantId, $responseId, 'coupon_issue', (string) $issueId, ['coupon_issue_id' => $issueId, 'coupon_definition_id' => $definitionId], $now);
    } catch (Throwable $exception) {
        return sr_survey_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $grant = $stmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_survey_mark_reward_grant_granted(PDO $pdo, int $grantId, int $responseId, string $referenceType, string $referenceId, array $result, string $now): void
{
    $resultJson = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $pdo->prepare(
        'UPDATE sr_survey_reward_grants
         SET status = \'granted\', provider_reference_type = :provider_reference_type, provider_reference_id = :provider_reference_id,
             result_snapshot_json = :result_snapshot_json, error_message = NULL, failed_at = NULL, granted_at = :granted_at, updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'provider_reference_type' => $referenceType,
        'provider_reference_id' => $referenceId,
        'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
        'granted_at' => $now,
        'updated_at' => $now,
        'id' => $grantId,
    ]);
    $pdo->prepare('UPDATE sr_survey_responses SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
        'rewarded_at' => $now,
        'updated_at' => $now,
        'id' => $responseId,
    ]);
}

function sr_survey_mark_reward_grant_failed(PDO $pdo, int $grantId, string $message, string $now): array
{
    $pdo->prepare(
        'UPDATE sr_survey_reward_grants
         SET status = \'failed\', error_message = :error_message, failed_at = :failed_at, updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'error_message' => $message,
        'failed_at' => $now,
        'updated_at' => $now,
        'id' => $grantId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}
