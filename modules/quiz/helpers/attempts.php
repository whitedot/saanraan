<?php

declare(strict_types=1);

function sr_quiz_attempt_limit_policies(): array
{
    return ['unlimited', 'per_quiz_once', 'per_period'];
}

function sr_quiz_attempt_limit_policy_label(string $policy): string
{
    return [
        'unlimited' => '제한 없음',
        'per_quiz_once' => '회원당 1회',
        'per_period' => '기간당 1회',
    ][$policy] ?? $policy;
}

function sr_quiz_scoring_models(): array
{
    return ['correct_answer', 'total_score', 'category_score'];
}

function sr_quiz_scoring_model_label(string $model): string
{
    return [
        'correct_answer' => '정답 통과',
        'total_score' => '총점 결과',
        'category_score' => '카테고리 진단',
    ][$model] ?? $model;
}

function sr_quiz_question_types(): array
{
    return ['single_choice', 'multiple_choice'];
}

function sr_quiz_question_type_label(string $type): string
{
    return [
        'single_choice' => '단일 선택',
        'multiple_choice' => '복수 선택',
    ][$type] ?? $type;
}

function sr_quiz_attempt_status_label(string $status): string
{
    return [
        'submitted' => '제출',
        'scored' => '채점 완료',
        'rewarded' => '보상 완료',
        'failed' => '실패',
    ][$status] ?? $status;
}

function sr_quiz_public_window_is_open(array $quiz, ?string $now = null): bool
{
    $now = $now ?? sr_now();
    $startsAt = trim((string) ($quiz['starts_at'] ?? ''));
    $endsAt = trim((string) ($quiz['ends_at'] ?? ''));

    return ($startsAt === '' || $startsAt <= $now)
        && ($endsAt === '' || $endsAt >= $now);
}

function sr_quiz_account_can_attempt(PDO $pdo, array $quiz, int $accountId): array
{
    if ($accountId < 1) {
        return ['allowed' => false, 'message' => '로그인 후 퀴즈를 풀 수 있습니다.'];
    }
    if ((string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
        return ['allowed' => false, 'message' => '현재 응시할 수 없는 퀴즈입니다.'];
    }

    $requiredGroupKeys = sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? '');
    if ($requiredGroupKeys !== [] && (!function_exists('sr_member_account_in_any_group') || !sr_member_account_in_any_group($pdo, $accountId, $requiredGroupKeys))) {
        return ['allowed' => false, 'message' => '응시 권한이 없는 퀴즈입니다.'];
    }

    $policy = (string) ($quiz['attempt_limit_policy'] ?? 'unlimited');
    if (!in_array($policy, sr_quiz_attempt_limit_policies(), true)) {
        $policy = 'unlimited';
    }
    if ($policy === 'unlimited') {
        return ['allowed' => true, 'message' => ''];
    }

    $params = [
        'quiz_id' => (int) ($quiz['id'] ?? 0),
        'account_id' => $accountId,
    ];
    $sql = 'SELECT COUNT(*) FROM sr_quiz_attempts WHERE quiz_id = :quiz_id AND account_id = :account_id AND submitted_at IS NOT NULL';
    if ($policy === 'per_period') {
        $seconds = (int) ($quiz['attempt_limit_period_seconds'] ?? 0);
        if ($seconds < 1) {
            return ['allowed' => false, 'message' => '응시 제한 기간 설정을 확인해야 합니다.'];
        }
        $since = date('Y-m-d H:i:s', max(0, time() - $seconds));
        $sql .= ' AND submitted_at >= :since';
        $params['since'] = $since;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    if ((int) $stmt->fetchColumn() > 0) {
        return ['allowed' => false, 'message' => '응시 제한에 따라 다시 제출할 수 없습니다.'];
    }

    return ['allowed' => true, 'message' => ''];
}

function sr_quiz_lock_quiz_for_attempt(PDO $pdo, int $quizId): ?array
{
    if ($quizId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE id = :id
           AND deleted_at IS NULL
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute(['id' => $quizId]);
    $quiz = $stmt->fetch();

    return is_array($quiz) ? $quiz : null;
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

function sr_quiz_selected_choice_ids_from_post(): array
{
    $answers = $_POST['answers'] ?? [];
    if (!is_array($answers)) {
        return [];
    }

    $selected = [];
    foreach ($answers as $questionId => $choiceValue) {
        $questionId = (int) $questionId;
        if (is_array($choiceValue)) {
            $choiceIds = [];
            foreach ($choiceValue as $choiceId) {
                $choiceId = (int) $choiceId;
                if ($choiceId > 0) {
                    $choiceIds[$choiceId] = true;
                }
            }
            $selected[$questionId] = array_keys($choiceIds);
        } else {
            $choiceId = (int) $choiceValue;
            $selected[$questionId] = $choiceId > 0 ? [$choiceId] : [];
        }
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

    if (!is_array($stmt->fetch()) || !sr_quiz_source_context_is_accessible($pdo, $sourceModule, $sourceType, $sourceId)) {
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

function sr_quiz_source_context_is_accessible(PDO $pdo, string $sourceModule, string $sourceType, int $sourceId): bool
{
    if ($sourceModule === 'content' && $sourceType === 'content_item') {
        if (!sr_module_enabled($pdo, 'content') || !is_file(SR_ROOT . '/modules/content/helpers.php')) {
            return false;
        }

        require_once SR_ROOT . '/modules/content/helpers.php';
        $page = sr_content_by_id($pdo, $sourceId);
        if (!is_array($page) || (string) ($page['status'] ?? '') !== 'published') {
            return false;
        }

        if (!sr_content_asset_access_required($page)) {
            return true;
        }

        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        if (!is_array($account) || (int) ($account['id'] ?? 0) < 1) {
            return false;
        }

        $assetModules = sr_content_asset_module_keys_from_value($page['asset_module'] ?? '');

        return sr_content_once_access_already_granted($pdo, $assetModules, (int) $account['id'], $sourceId);
    }

    if ($sourceModule === 'community' && $sourceType === 'community_post') {
        if (!sr_module_enabled($pdo, 'community') || !is_file(SR_ROOT . '/modules/community/helpers.php')) {
            return false;
        }
        require_once SR_ROOT . '/modules/community/helpers.php';
        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        $post = sr_community_post_for_read($pdo, $sourceId, is_array($account) ? $account : null);

        return is_array($post);
    }

    return false;
}

function sr_quiz_submit_attempt(PDO $pdo, array $quiz, array $questions, int $accountId, array $selectedChoiceIds, array $assetOptions): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    if ($quizId < 1 || $accountId < 1) {
        throw new InvalidArgumentException('Quiz and account are required.');
    }
    $attemptAccess = sr_quiz_account_can_attempt($pdo, $quiz, $accountId);
    if (empty($attemptAccess['allowed'])) {
        throw new RuntimeException((string) ($attemptAccess['message'] ?? 'Quiz attempt is not allowed.'));
    }

    $now = sr_now();
    $passScore = 0;
    $passed = false;
    $rewardGrant = null;
    $rewardError = '';

    $pdo->beginTransaction();
    try {
        $lockedQuiz = sr_quiz_lock_quiz_for_attempt($pdo, $quizId);
        if (!is_array($lockedQuiz)) {
            throw new RuntimeException('현재 응시할 수 없는 퀴즈입니다.');
        }
        $attemptAccess = sr_quiz_account_can_attempt($pdo, $lockedQuiz, $accountId);
        if (empty($attemptAccess['allowed'])) {
            throw new RuntimeException((string) ($attemptAccess['message'] ?? 'Quiz attempt is not allowed.'));
        }
        $quiz = $lockedQuiz;
        $questions = sr_quiz_questions_with_choices($pdo, $quizId);
        if ($questions === []) {
            throw new RuntimeException('응시 가능한 문제가 없습니다.');
        }
        $scoredAnswers = sr_quiz_score_answers($questions, $selectedChoiceIds);
        $totalScore = (int) $scoredAnswers['total_score'];
        $categoryScores = (array) $scoredAnswers['category_scores'];
        $answerRows = (array) $scoredAnswers['answer_rows'];
        $allRequiredAnswered = !empty($scoredAnswers['all_required_answered']);
        $passScore = $quiz['pass_score'] === null ? 0 : (int) $quiz['pass_score'];
        $scoringModel = (string) ($quiz['scoring_model'] ?? 'correct_answer');
        if (!in_array($scoringModel, sr_quiz_scoring_models(), true)) {
            $scoringModel = 'correct_answer';
        }
        $selectedResult = sr_quiz_select_result($pdo, $quizId, $scoringModel, $totalScore, $categoryScores);
        $displayScore = sr_quiz_attempt_display_score($scoringModel, $totalScore, $categoryScores, $selectedResult);
        $passed = $allRequiredAnswered && ($scoringModel === 'category_score' || $totalScore >= $passScore);

        $returnUrl = sr_quiz_internal_return_path(sr_post_string('return_to', 255));
        $sourceContext = sr_quiz_valid_source_context($pdo, $quizId, sr_quiz_source_context_from_request());
        $sourceSnapshot = sr_quiz_source_snapshot($pdo, $sourceContext, $returnUrl);
        $stmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempts
                (quiz_id, account_id, status, source_module, source_type, source_id, return_url, started_at, submitted_at, scored_at,
                 source_title_snapshot, source_url_snapshot, total_score, passed, selected_result_id, answer_snapshot_json, scoring_snapshot_json, result_snapshot_json, user_agent_hash, ip_hash, created_at, updated_at)
             VALUES
                (:quiz_id, :account_id, \'scored\', :source_module, :source_type, :source_id, :return_url, :started_at, :submitted_at, :scored_at,
                 :source_title_snapshot, :source_url_snapshot, :total_score, :passed, :selected_result_id, :answer_snapshot_json, :scoring_snapshot_json, :result_snapshot_json, :user_agent_hash, :ip_hash, :created_at, :updated_at)'
        );
        $answerSnapshotJson = json_encode($selectedChoiceIds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scoringSnapshotJson = json_encode([
            'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
            'scoring_model' => $scoringModel,
            'pass_score' => $passScore,
            'total_score' => $totalScore,
            'category_scores' => $categoryScores,
            'passed' => $passed,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resultSnapshotJson = json_encode($selectedResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            'source_title_snapshot' => $sourceSnapshot['title'],
            'source_url_snapshot' => $sourceSnapshot['url'],
            'total_score' => $totalScore,
            'passed' => $passed ? 1 : 0,
            'selected_result_id' => isset($selectedResult['id']) ? (int) $selectedResult['id'] : null,
            'answer_snapshot_json' => is_string($answerSnapshotJson) ? $answerSnapshotJson : '{}',
            'scoring_snapshot_json' => is_string($scoringSnapshotJson) ? $scoringSnapshotJson : '{}',
            'result_snapshot_json' => is_string($resultSnapshotJson) ? $resultSnapshotJson : '{}',
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $attemptId = (int) $pdo->lastInsertId();

        $answerStmt = $pdo->prepare(
            'INSERT INTO sr_quiz_attempt_answers
                (attempt_id, question_id, question_key, choice_id, choice_key, answer_snapshot_json, score_awarded, category_scores_json, created_at)
             VALUES
                (:attempt_id, :question_id, :question_key, :choice_id, :choice_key, :answer_snapshot_json, :score_awarded, :category_scores_json, :created_at)'
        );
        foreach ($answerRows as $answerRow) {
            $question = (array) $answerRow['question'];
            $selectedChoices = (array) ($answerRow['choices'] ?? []);
            $firstChoice = is_array($selectedChoices[0] ?? null) ? $selectedChoices[0] : [];
            $choiceLabels = [];
            $choiceKeys = [];
            foreach ($selectedChoices as $selectedChoice) {
                if (is_array($selectedChoice)) {
                    $choiceLabels[] = (string) ($selectedChoice['label'] ?? '');
                    $choiceKeys[] = (string) ($selectedChoice['choice_key'] ?? '');
                }
            }
            $snapshotJson = json_encode([
                'question_prompt' => (string) ($question['prompt'] ?? ''),
                'choice_labels' => $choiceLabels,
                'choice_keys' => $choiceKeys,
                'selected_choice_ids' => (array) ($answerRow['selected_choice_ids'] ?? []),
                'correct_choice_ids' => (array) ($answerRow['correct_choice_ids'] ?? []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerCategoryJson = json_encode(sr_quiz_answer_category_scores($selectedChoices), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerStmt->execute([
                'attempt_id' => $attemptId,
                'question_id' => (int) ($question['id'] ?? 0),
                'question_key' => (string) ($question['question_key'] ?? ''),
                'choice_id' => isset($firstChoice['id']) ? (int) $firstChoice['id'] : null,
                'choice_key' => count(array_filter($choiceKeys)) === 1 ? (string) array_values(array_filter($choiceKeys))[0] : null,
                'answer_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
                'score_awarded' => (int) $answerRow['score_awarded'],
                'category_scores_json' => is_string($answerCategoryJson) ? $answerCategoryJson : '{}',
                'created_at' => $now,
            ]);
        }

        sr_quiz_save_attempt_result_scores($pdo, $attemptId, $selectedResult, $categoryScores, $now);

        if ($passed && (int) ($quiz['reward_enabled'] ?? 0) === 1) {
            $rewardPolicy = sr_quiz_active_reward_policy($pdo, $quizId);
            if (is_array($rewardPolicy)) {
                $rewardGrant = sr_quiz_issue_reward_grant($pdo, $quiz, $attemptId, $accountId, $rewardPolicy, $assetOptions, $now, $sourceContext);
                $rewardError = (string) ($rewardGrant['error_message'] ?? '');
            }
        }

        $pdo->commit();

        return [
            'attempt_id' => $attemptId,
            'total_score' => $totalScore,
            'display_score' => $displayScore,
            'category_scores' => $categoryScores,
            'passed' => $passed,
            'selected_result' => $selectedResult,
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

function sr_quiz_latest_attempt_result(PDO $pdo, int $quizId, int $accountId): ?array
{
    if ($quizId < 1 || $accountId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, total_score, passed, scoring_snapshot_json, result_snapshot_json
         FROM sr_quiz_attempts
         WHERE quiz_id = :quiz_id
           AND account_id = :account_id
           AND submitted_at IS NOT NULL
         ORDER BY submitted_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'quiz_id' => $quizId,
        'account_id' => $accountId,
    ]);
    $attempt = $stmt->fetch();
    if (!is_array($attempt)) {
        return null;
    }

    $scoringSnapshot = json_decode((string) ($attempt['scoring_snapshot_json'] ?? ''), true);
    $resultSnapshot = json_decode((string) ($attempt['result_snapshot_json'] ?? ''), true);
    $categoryScores = is_array($scoringSnapshot) && is_array($scoringSnapshot['category_scores'] ?? null)
        ? (array) $scoringSnapshot['category_scores']
        : [];

    $selectedResult = is_array($resultSnapshot) ? $resultSnapshot : [];
    $totalScore = (int) ($attempt['total_score'] ?? 0);
    $scoringModel = is_array($scoringSnapshot) ? (string) ($scoringSnapshot['scoring_model'] ?? 'correct_answer') : 'correct_answer';
    if (!in_array($scoringModel, sr_quiz_scoring_models(), true)) {
        $scoringModel = 'correct_answer';
    }

    return [
        'attempt_id' => (int) ($attempt['id'] ?? 0),
        'total_score' => $totalScore,
        'display_score' => sr_quiz_attempt_display_score($scoringModel, $totalScore, $categoryScores, $selectedResult),
        'category_scores' => $categoryScores,
        'passed' => (int) ($attempt['passed'] ?? 0) === 1,
        'selected_result' => $selectedResult,
        'reward_grant' => null,
        'reward_error' => '',
    ];
}

function sr_quiz_attempt_display_score(string $scoringModel, int $totalScore, array $categoryScores, array $selectedResult): int
{
    if ($scoringModel !== 'category_score') {
        return $totalScore;
    }

    $categoryKey = (string) ($selectedResult['category_key'] ?? '');
    if ($categoryKey !== '' && isset($categoryScores[$categoryKey])) {
        return (int) $categoryScores[$categoryKey];
    }

    if ($categoryScores === []) {
        return 0;
    }

    return max(array_map('intval', array_values($categoryScores)));
}

function sr_quiz_answer_category_scores(array $choices): array
{
    $scores = [];
    foreach ($choices as $choice) {
        if (!is_array($choice)) {
            continue;
        }
        $categoryKey = (string) ($choice['category_key'] ?? '');
        $categoryWeight = (int) ($choice['category_weight'] ?? 0);
        if ($categoryKey !== '' && $categoryWeight !== 0) {
            $scores[$categoryKey] = (int) ($scores[$categoryKey] ?? 0) + $categoryWeight;
        }
    }

    return $scores;
}

function sr_quiz_score_answers(array $questions, array $selectedChoiceIds): array
{
    $totalScore = 0;
    $categoryScores = [];
    $answerRows = [];
    $allRequiredAnswered = true;
    foreach ($questions as $question) {
        $questionId = (int) ($question['id'] ?? 0);
        $questionType = (string) ($question['question_type'] ?? 'single_choice');
        if (!in_array($questionType, sr_quiz_question_types(), true)) {
            $questionType = 'single_choice';
        }
        $selectedIds = $selectedChoiceIds[$questionId] ?? [];
        if (!is_array($selectedIds)) {
            $selectedIds = [(int) $selectedIds];
        }
        $selectedIds = array_values(array_filter(array_unique(array_map('intval', $selectedIds)), static fn (int $choiceId): bool => $choiceId > 0));
        if ($questionType === 'single_choice' && count($selectedIds) > 1) {
            $selectedIds = [(int) $selectedIds[0]];
        }
        $selectedChoices = [];
        $correctIds = [];
        foreach ((array) ($question['choices'] ?? []) as $choice) {
            $choiceId = (int) ($choice['id'] ?? 0);
            if ((int) ($choice['is_correct'] ?? 0) === 1) {
                $correctIds[] = $choiceId;
            }
            if (in_array($choiceId, $selectedIds, true)) {
                $selectedChoices[] = $choice;
                $categoryKey = (string) ($choice['category_key'] ?? '');
                $categoryWeight = (int) ($choice['category_weight'] ?? 0);
                if ($categoryKey !== '' && $categoryWeight !== 0) {
                    $categoryScores[$categoryKey] = (int) ($categoryScores[$categoryKey] ?? 0) + $categoryWeight;
                }
            }
        }
        if ((int) ($question['required'] ?? 1) === 1 && $selectedChoices === []) {
            $allRequiredAnswered = false;
        }
        sort($selectedIds);
        sort($correctIds);
        $scoreAwarded = $selectedIds !== [] && $selectedIds === $correctIds ? (int) ($question['score_value'] ?? 0) : 0;
        $totalScore += $scoreAwarded;
        $answerRows[] = [
            'question' => $question,
            'choices' => $selectedChoices,
            'selected_choice_ids' => $selectedIds,
            'correct_choice_ids' => $correctIds,
            'score_awarded' => $scoreAwarded,
        ];
    }

    return [
        'total_score' => $totalScore,
        'category_scores' => $categoryScores,
        'answer_rows' => $answerRows,
        'all_required_answered' => $allRequiredAnswered,
    ];
}

function sr_quiz_select_result(PDO $pdo, int $quizId, string $scoringModel, int $totalScore, array $categoryScores): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, rr.rule_type, rr.min_score, rr.max_score, rr.category_key, rr.threshold_value, rr.priority
         FROM sr_quiz_result_rules rr
         INNER JOIN sr_quiz_results r ON r.id = rr.result_id
         WHERE rr.quiz_id = :quiz_id
           AND r.status = \'active\'
         ORDER BY rr.priority DESC, r.sort_order ASC, r.id ASC'
    );
    $stmt->execute(['quiz_id' => $quizId]);
    $fallback = [];
    foreach ($stmt->fetchAll() as $row) {
        if ($fallback === []) {
            $fallback = $row;
        }
        $ruleType = (string) ($row['rule_type'] ?? '');
        if ($scoringModel === 'category_score' || $ruleType === 'category_threshold') {
            $categoryKey = (string) ($row['category_key'] ?? '');
            $threshold = $row['threshold_value'] === null ? null : (int) $row['threshold_value'];
            if ($categoryKey !== '' && $threshold !== null && (int) ($categoryScores[$categoryKey] ?? 0) >= $threshold) {
                return sr_quiz_result_snapshot($row);
            }
            continue;
        }

        $min = $row['min_score'] === null ? null : (int) $row['min_score'];
        $max = $row['max_score'] === null ? null : (int) $row['max_score'];
        if (($min === null || $totalScore >= $min) && ($max === null || $totalScore <= $max)) {
            return sr_quiz_result_snapshot($row);
        }
    }

    return $fallback !== [] ? sr_quiz_result_snapshot($fallback) : [];
}

function sr_quiz_result_snapshot(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'result_key' => (string) ($row['result_key'] ?? ''),
        'title' => (string) ($row['title'] ?? ''),
        'summary' => (string) ($row['summary'] ?? ''),
        'rule_type' => (string) ($row['rule_type'] ?? ''),
        'category_key' => (string) ($row['category_key'] ?? ''),
    ];
}

function sr_quiz_save_attempt_result_scores(PDO $pdo, int $attemptId, array $selectedResult, array $categoryScores, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_quiz_attempt_result_scores
            (attempt_id, result_id, category_key, score_value, is_selected, snapshot_json, created_at)
         VALUES
            (:attempt_id, :result_id, :category_key, :score_value, :is_selected, :snapshot_json, :created_at)'
    );
    $selectedJson = json_encode($selectedResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($selectedResult !== []) {
        $stmt->execute([
            'attempt_id' => $attemptId,
            'result_id' => (int) ($selectedResult['id'] ?? 0),
            'category_key' => (string) ($selectedResult['category_key'] ?? '') ?: null,
            'score_value' => 0,
            'is_selected' => 1,
            'snapshot_json' => is_string($selectedJson) ? $selectedJson : '{}',
            'created_at' => $now,
        ]);
    }
    foreach ($categoryScores as $categoryKey => $scoreValue) {
        $stmt->execute([
            'attempt_id' => $attemptId,
            'result_id' => null,
            'category_key' => (string) $categoryKey,
            'score_value' => (int) $scoreValue,
            'is_selected' => 0,
            'snapshot_json' => '{}',
            'created_at' => $now,
        ]);
    }
}

function sr_quiz_source_snapshot(PDO $pdo, array $sourceContext, string $returnUrl): array
{
    $sourceModule = (string) ($sourceContext['source_module'] ?? '');
    $sourceType = (string) ($sourceContext['source_type'] ?? '');
    $sourceId = (int) ($sourceContext['source_id'] ?? 0);
    $snapshot = ['title' => null, 'url' => $returnUrl !== '' ? $returnUrl : null];

    if ($sourceId < 1) {
        return $snapshot;
    }

    if ($sourceModule === 'content' && $sourceType === 'content_item' && function_exists('sr_content_by_id')) {
        $page = sr_content_by_id($pdo, $sourceId);
        if (is_array($page)) {
            $snapshot['title'] = (string) ($page['title'] ?? '');
        }
    } elseif ($sourceModule === 'community' && $sourceType === 'community_post' && function_exists('sr_community_post_for_read')) {
        $account = function_exists('sr_member_current_account') ? sr_member_current_account($pdo) : null;
        $post = sr_community_post_for_read($pdo, $sourceId, is_array($account) ? $account : null);
        if (is_array($post)) {
            $snapshot['title'] = (string) ($post['title'] ?? '');
        }
    }

    return $snapshot;
}
