<?php

function sr_quiz_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_quiz_clean_key(string $value, int $maxLength = 64): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    $value = preg_replace('/\A[^a-z]+/', '', $value) ?? '';

    return substr($value, 0, $maxLength);
}

function sr_quiz_internal_return_path(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//')) {
        return '';
    }

    if (preg_match('/[\r\n]/', $value) === 1) {
        return '';
    }

    return substr($value, 0, 255);
}

function sr_quiz_clean_text(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_quiz_clean_single_line(string $value, int $maxLength): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_quiz_statuses(): array
{
    return ['draft', 'active', 'paused', 'archived'];
}

function sr_quiz_status_label(string $status): string
{
    return [
        'draft' => '초안',
        'active' => '공개',
        'paused' => '중지',
        'archived' => '보관',
    ][$status] ?? $status;
}

function sr_quiz_key_exists(PDO $pdo, string $quizKey, int $excludeId = 0): bool
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return false;
    }

    $sql = 'SELECT 1 FROM sr_quiz_sets WHERE quiz_key = :quiz_key AND deleted_at IS NULL';
    $params = ['quiz_key' => $quizKey];
    if ($excludeId > 0) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excludeId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sr_quiz_public_quizzes(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT id, quiz_key, title, description
         FROM sr_quiz_sets
         WHERE status = 'active'
           AND deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_quiz_by_key(PDO $pdo, string $quizKey): ?array
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE quiz_key = :quiz_key
           AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['quiz_key' => $quizKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_questions_with_choices(PDO $pdo, int $quizId): array
{
    if ($quizId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_questions
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $questions = $stmt->fetchAll();
    $choiceStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_choices
         WHERE question_id = :question_id
         ORDER BY sort_order ASC, id ASC'
    );
    foreach ($questions as $index => $question) {
        $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
        $questions[$index]['choices'] = $choiceStmt->fetchAll();
    }

    return $questions;
}

function sr_quiz_active_reward_policy(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_policies
         WHERE quiz_id = :quiz_id
           AND status = \'active\'
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_quiz_selected_choice_ids_from_post(): array
{
    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers)) {
        return [];
    }

    $selected = [];
    foreach ($answers as $questionId => $choiceId) {
        $selected[(int) $questionId] = (int) $choiceId;
    }

    return $selected;
}

function sr_quiz_source_context_from_request(): array
{
    $sourceModule = sr_quiz_clean_key(sr_post_string('source_module', 40), 40);
    $sourceType = sr_quiz_clean_key(sr_post_string('source_type', 40), 40);
    $sourceId = (int) sr_post_string('source_id', 20);

    if ($sourceModule === '' || $sourceType === '' || $sourceId < 1) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    return [
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ];
}

function sr_quiz_valid_source_context(PDO $pdo, int $quizId, array $sourceContext): array
{
    $sourceModule = (string) ($sourceContext['source_module'] ?? '');
    $sourceType = (string) ($sourceContext['source_type'] ?? '');
    $sourceId = (int) ($sourceContext['source_id'] ?? 0);

    if ($quizId < 1 || $sourceModule === '' || $sourceType === '' || $sourceId < 1) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_quiz_sources
         WHERE quiz_id = :quiz_id
           AND source_module = :source_module
           AND source_type = :source_type
           AND source_id = :source_id
           AND status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([
        'quiz_id' => $quizId,
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ]);

    if (!is_array($stmt->fetch())) {
        return [
            'source_module' => null,
            'source_type' => null,
            'source_id' => null,
        ];
    }

    return [
        'source_module' => $sourceModule,
        'source_type' => $sourceType,
        'source_id' => $sourceId,
    ];
}

function sr_quiz_submit_attempt(PDO $pdo, array $quiz, array $questions, int $accountId, array $selectedChoiceIds, array $assetOptions): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    if ($quizId < 1 || $accountId < 1) {
        throw new InvalidArgumentException('Quiz and account are required.');
    }

    $now = sr_now();
    $totalScore = 0;
    $answerRows = [];
    $allRequiredAnswered = true;
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $selectedChoiceId = (int) ($selectedChoiceIds[$questionId] ?? 0);
        $selectedChoice = null;
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            if ((int) ($choice['id'] ?? 0) === $selectedChoiceId) {
                $selectedChoice = $choice;
                break;
            }
        }
        if ((int) ($question['required'] ?? 1) === 1 && !is_array($selectedChoice)) {
            $allRequiredAnswered = false;
        }
        $scoreAwarded = is_array($selectedChoice) && (int) ($selectedChoice['is_correct'] ?? 0) === 1
            ? (int) ($question['score_value'] ?? 0)
            : 0;
        $totalScore += $scoreAwarded;
        $answerRows[] = [
            'question' => $question,
            'choice' => $selectedChoice,
            'score_awarded' => $scoreAwarded,
        ];
    }

    $passScore = $quiz['pass_score'] === null ? 0 : (int) $quiz['pass_score'];
    $passed = $allRequiredAnswered && $totalScore >= $passScore;
    $rewardGrant = null;
    $rewardError = '';

    $pdo->beginTransaction();
    try {
        $returnUrl = sr_quiz_internal_return_path(sr_post_string('return_to', 255));
        $sourceContext = sr_quiz_valid_source_context($pdo, $quizId, sr_quiz_source_context_from_request());
        $stmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempts
                (quiz_id, account_id, status, source_module, source_type, source_id, return_url, started_at, submitted_at, scored_at,
                 total_score, passed, answer_snapshot_json, scoring_snapshot_json, user_agent_hash, ip_hash, created_at, updated_at)
             VALUES
                (:quiz_id, :account_id, \'scored\', :source_module, :source_type, :source_id, :return_url, :started_at, :submitted_at, :scored_at,
                 :total_score, :passed, :answer_snapshot_json, :scoring_snapshot_json, :user_agent_hash, :ip_hash, :created_at, :updated_at)'
        );
        $answerSnapshotJson = json_encode($selectedChoiceIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scoringSnapshotJson = json_encode([
            'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
            'pass_score' => $passScore,
            'total_score' => $totalScore,
            'passed' => $passed,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt->execute([
            'quiz_id' => $quizId,
            'account_id' => $accountId,
            'source_module' => $sourceContext['source_module'],
            'source_type' => $sourceContext['source_type'],
            'source_id' => $sourceContext['source_id'],
            'return_url' => $returnUrl,
            'started_at' => $now,
            'submitted_at' => $now,
            'scored_at' => $now,
            'total_score' => $totalScore,
            'passed' => $passed ? 1 : 0,
            'answer_snapshot_json' => is_string($answerSnapshotJson) ? $answerSnapshotJson : '{}',
            'scoring_snapshot_json' => is_string($scoringSnapshotJson) ? $scoringSnapshotJson : '{}',
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attemptId = (int) $pdo->lastInsertId();

        $answerStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempt_answers
                (attempt_id, question_id, question_key, choice_id, choice_key, answer_snapshot_json, score_awarded, created_at)
             VALUES
                (:attempt_id, :question_id, :question_key, :choice_id, :choice_key, :answer_snapshot_json, :score_awarded, :created_at)'
        );
        foreach ($answerRows as $answerRow) {
            $question = (array) $answerRow['question'];
            $choice = is_array($answerRow['choice']) ? $answerRow['choice'] : [];
            $snapshotJson = json_encode([
                'question_prompt' => (string) ($question['prompt'] ?? ''),
                'choice_label' => (string) ($choice['label'] ?? ''),
                'is_correct' => (int) ($choice['is_correct'] ?? 0),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerStmt->execute([
                'attempt_id' => $attemptId,
                'question_id' => (int) ($question['id'] ?? 0),
                'question_key' => (string) ($question['question_key'] ?? ''),
                'choice_id' => isset($choice['id']) ? (int) $choice['id'] : null,
                'choice_key' => (string) ($choice['choice_key'] ?? ''),
                'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
                'score_awarded' => (int) $answerRow['score_awarded'],
                'created_at' => $now,
            ]);
        }

        if ($passed && (int) ($quiz['reward_enabled'] ?? 0) === 1) {
            $rewardPolicy = sr_quiz_active_reward_policy($pdo, $quizId);
            if (is_array($rewardPolicy)) {
                $rewardGrant = sr_quiz_issue_reward_grant($pdo, $quiz, $attemptId, $accountId, $rewardPolicy, $assetOptions, $now);
                $rewardError = (string) ($rewardGrant['error_message'] ?? '');
            }
        }

        $pdo->commit();

        return [
            'attempt_id' => $attemptId,
            'total_score' => $totalScore,
            'passed' => $passed,
            'reward_grant' => $rewardGrant,
            'reward_error' => $rewardError,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_quiz_issue_reward_grant(PDO $pdo, array $quiz, int $attemptId, int $accountId, array $policy, array $assetOptions, string $now): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    $policyId = (int) ($policy['id'] ?? 0);
    $rewardModule = (string) ($policy['reward_module'] ?? '');
    $rewardAmount = (int) ($policy['reward_amount'] ?? 0);
    $dedupeScope = (string) ($policy['dedupe_scope'] ?? 'per_quiz');
    $dedupeKey = 'quiz_reward:' . (string) $accountId . ':' . (string) $quizId;
    if ($dedupeScope !== 'per_quiz') {
        $dedupeKey .= ':' . (string) $policyId;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_quiz_reward_grants
            (quiz_id, attempt_id, reward_policy_id, account_id, reward_provider, reward_module, reward_code, reward_amount,
             dedupe_scope, dedupe_key, status, request_snapshot_json, created_at, updated_at)
         VALUES
            (:quiz_id, :attempt_id, :reward_policy_id, :account_id, \'ledger_asset\', :reward_module, :reward_code, :reward_amount,
             :dedupe_scope, :dedupe_key, \'pending\', :request_snapshot_json, :created_at, :updated_at)'
    );
    $snapshotJson = json_encode([
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'reward_module' => $rewardModule,
        'reward_amount' => $rewardAmount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->execute([
        'quiz_id' => $quizId,
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'account_id' => $accountId,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'dedupe_scope' => $dedupeScope,
        'dedupe_key' => $dedupeKey,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $grantStmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1 FOR UPDATE');
    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();
    if (!is_array($grant) || (string) ($grant['status'] ?? '') === 'granted') {
        return is_array($grant) ? $grant : [];
    }

    $grantId = (int) ($grant['id'] ?? 0);
    if (!isset($assetOptions[$rewardModule])) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 계약을 찾을 수 없습니다.', $now);
    }

    $transactionFunction = (string) ($assetOptions[$rewardModule]['transaction_function'] ?? '');
    if (!function_exists($transactionFunction)) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '보상 자산 거래 함수를 찾을 수 없습니다.', $now);
    }

    try {
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => $rewardAmount,
            'transaction_type' => (string) ($assetOptions[$rewardModule]['credit_type'] ?? 'grant'),
            'reason' => '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''),
            'reference_type' => 'quiz_reward',
            'reference_id' => (string) $grantId,
            'created_by_account_id' => null,
        ]);
        $resultJson = json_encode(['transaction_id' => $transactionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $pdo->prepare(
            'UPDATE sr_quiz_reward_grants
             SET status = \'granted\',
                 provider_reference_type = :provider_reference_type,
                 provider_reference_id = :provider_reference_id,
                 result_snapshot_json = :result_snapshot_json,
                 granted_at = :granted_at,
                 updated_at = :updated_at
             WHERE id = :id'
        )->execute([
            'provider_reference_type' => 'quiz_reward',
            'provider_reference_id' => (string) $grantId,
            'result_snapshot_json' => is_string($resultJson) ? $resultJson : '{}',
            'granted_at' => $now,
            'updated_at' => $now,
            'id' => $grantId,
        ]);
        $pdo->prepare('UPDATE sr_quiz_attempts SET rewarded_at = :rewarded_at, updated_at = :updated_at WHERE id = :id')->execute([
            'rewarded_at' => $now,
            'updated_at' => $now,
            'id' => $attemptId,
        ]);
    } catch (Throwable $exception) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }

    $grantStmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $grantStmt->fetch();

    return is_array($grant) ? $grant : [];
}

function sr_quiz_mark_reward_grant_failed(PDO $pdo, int $grantId, string $message, string $now): array
{
    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET status = \'failed\',
             error_message = :error_message,
             failed_at = :failed_at,
             updated_at = :updated_at
         WHERE id = :id'
    )->execute([
        'error_message' => $message,
        'failed_at' => $now,
        'updated_at' => $now,
        'id' => $grantId,
    ]);
    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : [];
}

function sr_quiz_admin_quizzes(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT q.id, q.quiz_key, q.title, q.status, q.quiz_mode, q.scoring_model, q.pass_score,
                q.reward_enabled, q.updated_at,
                COUNT(DISTINCT qs.id) AS question_count,
                COUNT(DISTINCT rp.id) AS reward_policy_count,
                COUNT(DISTINCT src.id) AS source_count
         FROM sr_quiz_sets q
         LEFT JOIN sr_quiz_questions qs ON qs.quiz_id = q.id
         LEFT JOIN sr_quiz_reward_policies rp ON rp.quiz_id = q.id AND rp.status = \'active\'
         LEFT JOIN sr_quiz_sources src ON src.quiz_id = q.id AND src.status = \'active\'
         WHERE q.deleted_at IS NULL
         GROUP BY q.id, q.quiz_key, q.title, q.status, q.quiz_mode, q.scoring_model, q.pass_score, q.reward_enabled, q.updated_at
         ORDER BY q.updated_at DESC, q.id DESC
         LIMIT 100'
    );

    return $stmt->fetchAll();
}

function sr_quiz_admin_quiz_by_id(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_sets WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $quizId]);
    $quiz = $stmt->fetch();
    if (!is_array($quiz)) {
        return null;
    }

    $questionStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_questions
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC'
    );
    $questionStmt->execute(['quiz_id' => $quizId]);
    $questions = $questionStmt->fetchAll();

    $choiceStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_choices
         WHERE question_id = :question_id
         ORDER BY sort_order ASC, id ASC'
    );
    foreach ($questions as $index => $question) {
        $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
        $questions[$index]['choices'] = $choiceStmt->fetchAll();
    }
    $quiz['questions'] = $questions;

    $policyStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_reward_policies
         WHERE quiz_id = :quiz_id
         ORDER BY sort_order ASC, id ASC
         LIMIT 1'
    );
    $policyStmt->execute(['quiz_id' => $quizId]);
    $policy = $policyStmt->fetch();
    $quiz['reward_policy'] = is_array($policy) ? $policy : null;

    $sourceStmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sources
         WHERE quiz_id = :quiz_id
           AND status = \'active\'
         ORDER BY sort_order ASC, id ASC'
    );
    $sourceStmt->execute(['quiz_id' => $quizId]);
    $quiz['sources'] = $sourceStmt->fetchAll();

    return $quiz;
}

function sr_quiz_default_admin_values(): array
{
    return [
        'id' => 0,
        'quiz_key' => '',
        'title' => '',
        'description' => '',
        'status' => 'draft',
        'pass_score' => 1,
        'reward_enabled' => 0,
        'reward_module' => '',
        'reward_amount' => '',
        'content_source_ids' => '',
        'questions' => [
            [
                'question_key' => 'q1',
                'prompt' => '',
                'score_value' => 1,
                'choices' => [
                    ['choice_key' => '', 'label' => '', 'is_correct' => 1],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0],
                ],
            ],
        ],
    ];
}

function sr_quiz_admin_values_from_row(array $quiz): array
{
    $policy = is_array($quiz['reward_policy'] ?? null) ? $quiz['reward_policy'] : [];
    $contentSourceIds = [];
    foreach ((array) ($quiz['sources'] ?? []) as $source) {
        if ((string) ($source['source_module'] ?? '') === 'content' && (string) ($source['source_type'] ?? '') === 'content_item') {
            $contentSourceIds[] = (string) (int) ($source['source_id'] ?? 0);
        }
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
            ];
        }
        $questions[] = [
            'id' => (int) ($question['id'] ?? 0),
            'question_key' => (string) ($question['question_key'] ?? ''),
            'prompt' => (string) ($question['prompt'] ?? ''),
            'score_value' => (int) ($question['score_value'] ?? 0),
            'choices' => $choices,
        ];
    }

    return [
        'id' => (int) ($quiz['id'] ?? 0),
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'title' => (string) ($quiz['title'] ?? ''),
        'description' => (string) ($quiz['description'] ?? ''),
        'status' => (string) ($quiz['status'] ?? 'draft'),
        'pass_score' => (string) ($quiz['pass_score'] ?? ''),
        'reward_enabled' => (int) ($quiz['reward_enabled'] ?? 0),
        'reward_module' => (string) ($policy['reward_module'] ?? ''),
        'reward_amount' => (string) ($policy['reward_amount'] ?? ''),
        'content_source_ids' => implode("\n", $contentSourceIds),
        'questions' => $questions === [] ? sr_quiz_default_admin_values()['questions'] : $questions,
    ];
}

function sr_quiz_admin_values_from_post(): array
{
    $questionUids = $_POST['question_uid'] ?? [];
    $questionKeys = $_POST['question_key'] ?? [];
    $questionPrompts = $_POST['question_prompt'] ?? [];
    $questionScores = $_POST['question_score'] ?? [];
    $choiceKeys = $_POST['choice_key'] ?? [];
    $choiceLabels = $_POST['choice_label'] ?? [];
    $correctChoices = $_POST['correct_choice'] ?? [];
    $questions = [];

    if (!is_array($questionUids)) {
        $questionUids = [];
    }
    foreach ($questionUids as $index => $uidValue) {
        $uid = is_string($uidValue) ? $uidValue : (string) $uidValue;
        $questionKey = is_array($questionKeys) && isset($questionKeys[$index]) ? sr_quiz_clean_key((string) $questionKeys[$index]) : '';
        $prompt = is_array($questionPrompts) && isset($questionPrompts[$index]) ? sr_quiz_clean_text((string) $questionPrompts[$index], 2000) : '';
        $score = is_array($questionScores) && isset($questionScores[$index]) ? (int) $questionScores[$index] : 1;
        $rowChoiceKeys = is_array($choiceKeys[$uid] ?? null) ? $choiceKeys[$uid] : [];
        $rowChoiceLabels = is_array($choiceLabels[$uid] ?? null) ? $choiceLabels[$uid] : [];
        $correctIndex = is_array($correctChoices) ? (string) ($correctChoices[$uid] ?? '') : '';
        $choices = [];
        foreach ($rowChoiceLabels as $choiceIndex => $choiceLabelValue) {
            $choiceLabel = sr_quiz_clean_single_line((string) $choiceLabelValue, 255);
            $choiceKey = isset($rowChoiceKeys[$choiceIndex]) ? sr_quiz_clean_key((string) $rowChoiceKeys[$choiceIndex]) : '';
            if ($choiceLabel === '' && $choiceKey === '') {
                continue;
            }
            if ($choiceLabel !== '' && $choiceKey === '') {
                $choiceKey = 'c' . (string) ((int) $choiceIndex + 1);
            }
            $choices[] = [
                'choice_key' => $choiceKey,
                'label' => $choiceLabel,
                'is_correct' => (string) $choiceIndex === $correctIndex ? 1 : 0,
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
            'prompt' => $prompt,
            'score_value' => max(0, $score),
            'choices' => $choices,
        ];
    }

    return [
        'id' => (int) sr_post_string('quiz_id', 20),
        'quiz_key' => sr_quiz_clean_key(sr_post_string('quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('title', 190), 190),
        'description' => sr_quiz_clean_text(sr_post_string('description', 2000), 2000),
        'status' => sr_post_string('status', 20),
        'pass_score' => sr_post_string('pass_score', 20),
        'reward_enabled' => ($_POST['reward_enabled'] ?? '') === '1' ? 1 : 0,
        'reward_module' => sr_quiz_clean_key(sr_post_string('reward_module', 40), 40),
        'reward_amount' => sr_post_string('reward_amount', 20),
        'content_source_ids' => sr_quiz_clean_text(sr_post_string('content_source_ids', 1000), 1000),
        'questions' => $questions,
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
        $errors[] = '퀴즈 key는 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용할 수 있습니다.';
    } elseif (sr_quiz_key_exists($pdo, $quizKey, $quizId)) {
        $errors[] = '이미 사용 중인 퀴즈 key입니다.';
    }
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '퀴즈 제목을 입력하세요.';
    }
    if (!in_array((string) ($values['status'] ?? ''), sr_quiz_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }
    if ((string) ($values['pass_score'] ?? '') !== '' && (int) $values['pass_score'] < 0) {
        $errors[] = '통과 점수는 0 이상이어야 합니다.';
    }

    $questions = (array) ($values['questions'] ?? []);
    if ($questions === []) {
        $errors[] = '문제를 1개 이상 입력하세요.';
    }
    $questionKeys = [];
    foreach ($questions as $questionIndex => $question) {
        $number = $questionIndex + 1;
        $questionKey = (string) ($question['question_key'] ?? '');
        if (!sr_quiz_key_is_valid($questionKey)) {
            $errors[] = '문제 ' . (string) $number . '의 key가 올바르지 않습니다.';
        } elseif (isset($questionKeys[$questionKey])) {
            $errors[] = '문제 key가 중복되었습니다: ' . $questionKey;
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
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 key가 올바르지 않습니다.';
            } elseif (isset($choiceKeys[$choiceKey])) {
                $errors[] = '문제 ' . (string) $number . '의 선택지 key가 중복되었습니다: ' . $choiceKey;
            }
            $choiceKeys[$choiceKey] = true;
            if ((string) ($choice['label'] ?? '') === '') {
                $errors[] = '문제 ' . (string) $number . ' 선택지 ' . (string) $choiceNumber . '의 내용을 입력하세요.';
            }
            if ((int) ($choice['is_correct'] ?? 0) === 1) {
                $correctCount++;
            }
        }
        if ($correctCount !== 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 정확히 1개 지정해야 합니다.';
        }
    }

    if ((int) ($values['reward_enabled'] ?? 0) === 1) {
        $rewardModule = (string) ($values['reward_module'] ?? '');
        $rewardAmount = (int) ($values['reward_amount'] ?? 0);
        if ($rewardModule === '' || !isset($assetOptions[$rewardModule])) {
            $errors[] = '보상 자산을 선택하세요.';
        }
        if ($rewardAmount <= 0) {
            $errors[] = '보상 금액은 1 이상이어야 합니다.';
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

    return array_values(array_unique($errors));
}

function sr_quiz_save_admin_quiz(PDO $pdo, array $values, int $accountId): int
{
    $quizId = (int) ($values['id'] ?? 0);
    $now = sr_now();
    $passScore = (string) ($values['pass_score'] ?? '') === '' ? null : (int) $values['pass_score'];

    $pdo->beginTransaction();
    try {
        if ($quizId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE sr_quiz_sets
                 SET quiz_key = :quiz_key,
                     title = :title,
                     description = :description,
                     status = :status,
                     quiz_mode = \'scored\',
                     scoring_model = \'correct_answer\',
                     pass_score = :pass_score,
                     reward_enabled = :reward_enabled,
                     updated_by_account_id = :updated_by_account_id,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'status' => (string) $values['status'],
                'pass_score' => $passScore,
                'reward_enabled' => (int) $values['reward_enabled'],
                'updated_by_account_id' => $accountId,
                'updated_at' => $now,
                'id' => $quizId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_sets
                    (quiz_key, title, description, status, quiz_mode, scoring_model, pass_score, reward_enabled,
                     created_by_account_id, updated_by_account_id, created_at, updated_at)
                 VALUES
                    (:quiz_key, :title, :description, :status, \'scored\', \'correct_answer\', :pass_score, :reward_enabled,
                     :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'status' => (string) $values['status'],
                'pass_score' => $passScore,
                'reward_enabled' => (int) $values['reward_enabled'],
                'created_by_account_id' => $accountId,
                'updated_by_account_id' => $accountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $quizId = (int) $pdo->lastInsertId();
        }

        $pdo->prepare('DELETE FROM sr_quiz_reward_policies WHERE quiz_id = :quiz_id')->execute(['quiz_id' => $quizId]);
        $pdo->prepare(
            'DELETE FROM sr_quiz_sources
             WHERE quiz_id = :quiz_id
               AND source_module = \'content\'
               AND source_type = \'content_item\''
        )->execute(['quiz_id' => $quizId]);
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
                (:quiz_id, :question_key, \'single_choice\', :prompt, 1, :score_value, :sort_order, :created_at, :updated_at)'
        );
        $choiceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_choices
                (question_id, choice_key, label, is_correct, score_value, sort_order, created_at, updated_at)
             VALUES
                (:question_id, :choice_key, :label, :is_correct, :score_value, :sort_order, :created_at, :updated_at)'
        );
        foreach ((array) ($values['questions'] ?? []) as $questionIndex => $question) {
            $questionStmt->execute([
                'quiz_id' => $quizId,
                'question_key' => (string) $question['question_key'],
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
                    'sort_order' => $choiceIndex,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ((int) ($values['reward_enabled'] ?? 0) === 1) {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_reward_policies
                    (quiz_id, status, trigger_type, reward_provider, reward_module, reward_code, reward_amount, dedupe_scope, sort_order, created_at, updated_at)
                 VALUES
                    (:quiz_id, \'active\', \'passed\', \'ledger_asset\', :reward_module, \'quiz_reward\', :reward_amount, \'per_quiz\', 0, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_id' => $quizId,
                'reward_module' => (string) $values['reward_module'],
                'reward_amount' => (int) $values['reward_amount'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sourceStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_sources
                (quiz_id, source_module, source_type, source_id, status, sort_order, cta_label, created_at, updated_at)
             VALUES
                (:quiz_id, \'content\', \'content_item\', :source_id, \'active\', :sort_order, :cta_label, :created_at, :updated_at)'
        );
        foreach (sr_quiz_content_source_ids_from_value((string) ($values['content_source_ids'] ?? '')) as $sourceIndex => $contentId) {
            $sourceStmt->execute([
                'quiz_id' => $quizId,
                'source_id' => $contentId,
                'sort_order' => $sourceIndex,
                'cta_label' => '퀴즈 풀기',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pdo->commit();
        return $quizId;
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function sr_quiz_soft_delete(PDO $pdo, int $quizId, int $accountId): bool
{
    if ($quizId < 1) {
        return false;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'UPDATE sr_quiz_sets
         SET status = \'archived\',
             updated_by_account_id = :account_id,
             updated_at = :updated_at,
             deleted_at = :deleted_at
         WHERE id = :id
           AND deleted_at IS NULL'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'updated_at' => $now,
        'deleted_at' => $now,
        'id' => $quizId,
    ]);

    return $stmt->rowCount() > 0;
}

function sr_quiz_content_quizzes(PDO $pdo, int $contentId): array
{
    if ($contentId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT q.id, q.quiz_key, q.title, q.description, s.cta_label
         FROM sr_quiz_sources s
         INNER JOIN sr_quiz_sets q ON q.id = s.quiz_id
         WHERE s.source_module = \'content\'
           AND s.source_type = \'content_item\'
           AND s.source_id = :content_id
           AND s.status = \'active\'
           AND q.status = \'active\'
           AND q.deleted_at IS NULL
         ORDER BY s.sort_order ASC, q.updated_at DESC, q.id DESC'
    );
    $stmt->execute(['content_id' => $contentId]);

    return $stmt->fetchAll();
}
