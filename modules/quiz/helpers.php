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

function sr_quiz_modes(): array
{
    return ['scored', 'diagnostic', 'survey'];
}

function sr_quiz_mode_label(string $mode): string
{
    return [
        'scored' => '점수형',
        'diagnostic' => '진단형',
        'survey' => '설문형',
    ][$mode] ?? $mode;
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

function sr_quiz_reward_providers(): array
{
    return ['ledger_asset', 'coupon'];
}

function sr_quiz_reward_provider_label(string $provider): string
{
    return [
        'ledger_asset' => '자산 장부',
        'coupon' => '쿠폰 발급',
    ][$provider] ?? $provider;
}

function sr_quiz_reward_dedupe_scopes(): array
{
    return ['per_quiz', 'per_source', 'per_attempt'];
}

function sr_quiz_reward_dedupe_scope_label(string $scope): string
{
    return [
        'per_quiz' => '퀴즈당 1회',
        'per_source' => '출처당 1회',
        'per_attempt' => '응시마다',
    ][$scope] ?? $scope;
}

function sr_quiz_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value) !== 1) {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        !$date instanceof DateTimeImmutable
        || (is_array($dateErrors) && ((int) ($dateErrors['warning_count'] ?? 0) > 0 || (int) ($dateErrors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d\TH:i') !== $value
    ) {
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

function sr_quiz_datetime_local_value(mixed $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

function sr_quiz_time_html(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return sr_e($value);
    }

    $diff = time() - $timestamp;
    if ($diff < 0) {
        $relative = date('Y-m-d H:i', $timestamp);
    } elseif ($diff < 60) {
        $relative = '방금 전';
    } elseif ($diff < 3600) {
        $relative = floor($diff / 60) . '분 전';
    } elseif ($diff < 86400) {
        $relative = floor($diff / 3600) . '시간 전';
    } elseif ($diff < 2592000) {
        $relative = floor($diff / 86400) . '일 전';
    } elseif ($diff < 31536000) {
        $relative = floor($diff / 2592000) . '개월 전';
    } else {
        $relative = floor($diff / 31536000) . '년 전';
    }

    return '<time datetime="' . sr_e($value) . '" title="' . sr_e($value) . '">' . sr_e($relative) . '</time>';
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

function sr_quiz_reward_grant_status_label(string $status): string
{
    return [
        'pending' => '대기',
        'granted' => '지급',
        'failed' => '실패',
    ][$status] ?? $status;
}

function sr_quiz_member_group_keys_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            $value = preg_split('/[\s,]+/', $value) ?: [];
        }
    }
    if (!is_array($value)) {
        return [];
    }

    $keys = [];
    foreach ($value as $groupKey) {
        $groupKey = sr_quiz_clean_key((string) $groupKey, 64);
        if ($groupKey !== '' && function_exists('sr_member_group_key_is_valid') && sr_member_group_key_is_valid($groupKey)) {
            $keys[$groupKey] = true;
        }
    }

    return array_keys($keys);
}

function sr_quiz_member_groups_for_admin(PDO $pdo): array
{
    if (!function_exists('sr_member_groups')) {
        return [];
    }

    return array_values(array_filter(sr_member_groups($pdo), static function (array $group): bool {
        return (string) ($group['status'] ?? '') === 'enabled';
    }));
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

function sr_quiz_key_exists(PDO $pdo, string $quizKey, int $excludeId = 0): bool
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return false;
    }

    $sql = 'SELECT 1 FROM sr_quiz_sets WHERE quiz_key = :quiz_key';
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
           AND (starts_at IS NULL OR starts_at <= :now_start)
           AND (ends_at IS NULL OR ends_at >= :now_end)
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
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
                'selected_choice_ids' => (array) ($answerRow['selected_choice_ids'] ?? []),
                'correct_choice_ids' => (array) ($answerRow['correct_choice_ids'] ?? []),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerCategoryJson = json_encode(sr_quiz_answer_category_scores($selectedChoices), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $answerStmt->execute([
                'attempt_id' => $attemptId,
                'question_id' => (int) ($question['id'] ?? 0),
                'question_key' => (string) ($question['question_key'] ?? ''),
                'choice_id' => isset($firstChoice['id']) ? (int) $firstChoice['id'] : null,
                'choice_key' => implode(',', array_filter($choiceKeys)),
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

function sr_quiz_issue_reward_grant(PDO $pdo, array $quiz, int $attemptId, int $accountId, array $policy, array $assetOptions, string $now, array $sourceContext = []): array
{
    $quizId = (int) ($quiz['id'] ?? 0);
    $policyId = (int) ($policy['id'] ?? 0);
    $rewardProvider = (string) ($policy['reward_provider'] ?? 'ledger_asset');
    $rewardModule = (string) ($policy['reward_module'] ?? '');
    $rewardAmount = (int) ($policy['reward_amount'] ?? 0);
    $dedupeScope = (string) ($policy['dedupe_scope'] ?? 'per_quiz');
    if (!in_array($dedupeScope, sr_quiz_reward_dedupe_scopes(), true)) {
        $dedupeScope = 'per_quiz';
    }
    $dedupeKey = 'quiz_reward:' . (string) $accountId . ':' . (string) $quizId;
    if ($dedupeScope === 'per_source') {
        $dedupeKey .= ':' . (string) ($sourceContext['source_module'] ?? '') . ':' . (string) ($sourceContext['source_type'] ?? '') . ':' . (string) ($sourceContext['source_id'] ?? '');
    } elseif ($dedupeScope === 'per_attempt') {
        $dedupeKey .= ':attempt:' . (string) $attemptId;
    }

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_quiz_reward_grants
            (quiz_id, attempt_id, reward_policy_id, account_id, reward_provider, reward_module, reward_code, reward_amount,
             source_module, source_type, source_id, dedupe_scope, dedupe_key, status, request_snapshot_json, created_at, updated_at)
         VALUES
            (:quiz_id, :attempt_id, :reward_policy_id, :account_id, :reward_provider, :reward_module, :reward_code, :reward_amount,
             :source_module, :source_type, :source_id, :dedupe_scope, :dedupe_key, \'pending\', :request_snapshot_json, :created_at, :updated_at)'
    );
    $snapshotJson = json_encode([
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_amount' => $rewardAmount,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt->execute([
        'quiz_id' => $quizId,
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'account_id' => $accountId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
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
    sr_quiz_refresh_reward_grant_for_retry($pdo, $grantId, [
        'attempt_id' => $attemptId,
        'reward_policy_id' => $policyId,
        'reward_provider' => $rewardProvider,
        'reward_module' => $rewardModule,
        'reward_code' => (string) ($policy['reward_code'] ?? 'quiz_reward'),
        'reward_amount' => $rewardAmount,
        'source_module' => $sourceContext['source_module'] ?? null,
        'source_type' => $sourceContext['source_type'] ?? null,
        'source_id' => $sourceContext['source_id'] ?? null,
        'dedupe_scope' => $dedupeScope,
        'request_snapshot_json' => is_string($snapshotJson) ? $snapshotJson : '{}',
        'updated_at' => $now,
    ]);
    if ($rewardProvider === 'coupon') {
        return sr_quiz_issue_coupon_reward_grant($pdo, $quiz, $grantId, $attemptId, $accountId, $policy, $now);
    }
    if ($rewardProvider !== 'ledger_asset') {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '지원하지 않는 보상 공급자입니다.', $now);
    }
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

function sr_quiz_refresh_reward_grant_for_retry(PDO $pdo, int $grantId, array $values): void
{
    if ($grantId < 1) {
        return;
    }

    $pdo->prepare(
        'UPDATE sr_quiz_reward_grants
         SET attempt_id = :attempt_id,
             reward_policy_id = :reward_policy_id,
             reward_provider = :reward_provider,
             reward_module = :reward_module,
             reward_code = :reward_code,
             reward_amount = :reward_amount,
             source_module = :source_module,
             source_type = :source_type,
             source_id = :source_id,
             dedupe_scope = :dedupe_scope,
             request_snapshot_json = :request_snapshot_json,
             status = \'pending\',
             error_message = NULL,
             failed_at = NULL,
             updated_at = :updated_at
         WHERE id = :id
           AND status <> \'granted\''
    )->execute([
        'attempt_id' => (int) ($values['attempt_id'] ?? 0),
        'reward_policy_id' => (int) ($values['reward_policy_id'] ?? 0),
        'reward_provider' => (string) ($values['reward_provider'] ?? ''),
        'reward_module' => (string) ($values['reward_module'] ?? ''),
        'reward_code' => (string) ($values['reward_code'] ?? ''),
        'reward_amount' => $values['reward_amount'] ?? null,
        'source_module' => $values['source_module'] ?? null,
        'source_type' => $values['source_type'] ?? null,
        'source_id' => $values['source_id'] ?? null,
        'dedupe_scope' => (string) ($values['dedupe_scope'] ?? 'per_quiz'),
        'request_snapshot_json' => (string) ($values['request_snapshot_json'] ?? '{}'),
        'updated_at' => (string) ($values['updated_at'] ?? sr_now()),
        'id' => $grantId,
    ]);
}

function sr_quiz_issue_coupon_reward_grant(PDO $pdo, array $quiz, int $grantId, int $attemptId, int $accountId, array $policy, string $now): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 모듈이 활성화되어 있지 않습니다.', $now);
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_issue_to_account')) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 발급 함수를 찾을 수 없습니다.', $now);
    }

    $definitionId = (int) ($policy['reward_code'] ?? 0);
    if ($definitionId < 1) {
        $definitionId = (int) ($policy['reward_module'] ?? 0);
    }
    if ($definitionId < 1) {
        return sr_quiz_mark_reward_grant_failed($pdo, $grantId, '쿠폰 정의 ID를 확인해야 합니다.', $now);
    }

    try {
        $issueId = sr_coupon_issue_to_account($pdo, $definitionId, $accountId, '퀴즈 보상: ' . (string) ($quiz['title'] ?? ''), null, null);
        $resultJson = json_encode(['coupon_issue_id' => $issueId, 'coupon_definition_id' => $definitionId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            'provider_reference_type' => 'coupon_issue',
            'provider_reference_id' => (string) $issueId,
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

    $stmt = $pdo->prepare('SELECT * FROM sr_quiz_reward_grants WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $grantId]);
    $grant = $stmt->fetch();

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
                q.starts_at, q.ends_at, q.attempt_limit_policy, q.attempt_limit_period_seconds,
                q.member_group_keys_json, q.reward_enabled, q.updated_at,
                COUNT(DISTINCT qs.id) AS question_count,
                COUNT(DISTINCT rp.id) AS reward_policy_count,
                COUNT(DISTINCT src.id) AS source_count
         FROM sr_quiz_sets q
         LEFT JOIN sr_quiz_questions qs ON qs.quiz_id = q.id
         LEFT JOIN sr_quiz_reward_policies rp ON rp.quiz_id = q.id AND rp.status = \'active\'
         LEFT JOIN sr_quiz_sources src ON src.quiz_id = q.id AND src.status = \'active\'
         WHERE q.deleted_at IS NULL
         GROUP BY q.id, q.quiz_key, q.title, q.status, q.quiz_mode, q.scoring_model, q.pass_score,
                  q.starts_at, q.ends_at, q.attempt_limit_policy, q.attempt_limit_period_seconds,
                  q.member_group_keys_json, q.reward_enabled, q.updated_at
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

    $ruleStmt = $pdo->prepare(
        'SELECT r.result_key, r.title, r.summary, rr.rule_type, rr.min_score, rr.max_score, rr.category_key, rr.threshold_value, rr.priority
         FROM sr_quiz_result_rules rr
         INNER JOIN sr_quiz_results r ON r.id = rr.result_id
         WHERE rr.quiz_id = :quiz_id
         ORDER BY rr.priority DESC, r.sort_order ASC, r.id ASC'
    );
    $ruleStmt->execute(['quiz_id' => $quizId]);
    $quiz['result_rules'] = $ruleStmt->fetchAll();

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
        'quiz_mode' => 'scored',
        'scoring_model' => 'correct_answer',
        'pass_score' => 1,
        'starts_at' => '',
        'ends_at' => '',
        'attempt_limit_policy' => 'unlimited',
        'attempt_limit_period_seconds' => '',
        'member_group_keys' => [],
        'reward_enabled' => 0,
        'reward_provider' => 'ledger_asset',
        'reward_module' => '',
        'reward_coupon_definition_id' => '',
        'reward_amount' => '',
        'reward_dedupe_scope' => 'per_quiz',
        'content_source_ids' => '',
        'community_source_ids' => '',
        'result_rules' => '',
        'questions' => [
            [
                'question_key' => 'q1',
                'question_type' => 'single_choice',
                'prompt' => '',
                'score_value' => 1,
                'choices' => [
                    ['choice_key' => '', 'label' => '', 'is_correct' => 1, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
                    ['choice_key' => '', 'label' => '', 'is_correct' => 0, 'category_key' => '', 'category_weight' => 0],
                ],
            ],
        ],
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
        'quiz_key' => (string) ($quiz['quiz_key'] ?? ''),
        'title' => (string) ($quiz['title'] ?? ''),
        'description' => (string) ($quiz['description'] ?? ''),
        'status' => (string) ($quiz['status'] ?? 'draft'),
        'quiz_mode' => (string) ($quiz['quiz_mode'] ?? 'scored'),
        'scoring_model' => (string) ($quiz['scoring_model'] ?? 'correct_answer'),
        'pass_score' => (string) ($quiz['pass_score'] ?? ''),
        'starts_at' => sr_quiz_datetime_local_value($quiz['starts_at'] ?? ''),
        'ends_at' => sr_quiz_datetime_local_value($quiz['ends_at'] ?? ''),
        'attempt_limit_policy' => (string) ($quiz['attempt_limit_policy'] ?? 'unlimited'),
        'attempt_limit_period_seconds' => (string) ($quiz['attempt_limit_period_seconds'] ?? ''),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? ''),
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
    ];
}

function sr_quiz_admin_values_from_post(): array
{
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
        'quiz_key' => sr_quiz_clean_key(sr_post_string('quiz_key', 64)),
        'title' => sr_quiz_clean_single_line(sr_post_string('title', 190), 190),
        'description' => sr_quiz_clean_text(sr_post_string('description', 2000), 2000),
        'status' => sr_post_string('status', 20),
        'quiz_mode' => sr_post_string('quiz_mode', 30),
        'scoring_model' => sr_post_string('scoring_model', 40),
        'pass_score' => sr_post_string('pass_score', 20),
        'starts_at' => sr_post_string('starts_at', 30),
        'ends_at' => sr_post_string('ends_at', 30),
        'attempt_limit_policy' => sr_post_string('attempt_limit_policy', 30),
        'attempt_limit_period_seconds' => sr_post_string('attempt_limit_period_seconds', 20),
        'member_group_keys' => sr_quiz_member_group_keys_from_value($memberGroupKeys),
        'reward_enabled' => ($_POST['reward_enabled'] ?? '') === '1' ? 1 : 0,
        'reward_provider' => sr_quiz_clean_key(sr_post_string('reward_provider', 30), 30),
        'reward_module' => sr_quiz_clean_key(sr_post_string('reward_module', 40), 40),
        'reward_coupon_definition_id' => sr_post_string('reward_coupon_definition_id', 20),
        'reward_amount' => sr_post_string('reward_amount', 20),
        'reward_dedupe_scope' => sr_quiz_clean_key(sr_post_string('reward_dedupe_scope', 20), 20),
        'content_source_ids' => sr_quiz_clean_text(sr_post_string('content_source_ids', 1000), 1000),
        'community_source_ids' => sr_quiz_clean_text(sr_post_string('community_source_ids', 1000), 1000),
        'result_rules' => sr_quiz_clean_text(sr_post_string('result_rules', 4000), 4000),
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
        if ($questionType === 'single_choice' && $correctCount !== 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 정확히 1개 지정해야 합니다.';
        } elseif ($questionType === 'multiple_choice' && $correctCount < 1) {
            $errors[] = '문제 ' . (string) $number . '는 정답 선택지를 1개 이상 지정해야 합니다.';
        }
    }

    if ((int) ($values['reward_enabled'] ?? 0) === 1) {
        $rewardProvider = (string) ($values['reward_provider'] ?? 'ledger_asset');
        if (!in_array($rewardProvider, sr_quiz_reward_providers(), true)) {
            $errors[] = '보상 공급자 값이 올바르지 않습니다.';
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
            if ($definitionId < 1 || !sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
                $errors[] = '보상 쿠폰 정의를 선택하세요.';
            } else {
                require_once SR_ROOT . '/modules/coupon/helpers.php';
                $definition = function_exists('sr_coupon_definition_by_id') ? sr_coupon_definition_by_id($pdo, $definitionId) : null;
                if (!is_array($definition) || (string) ($definition['status'] ?? '') !== 'active') {
                    $errors[] = '사용 중인 쿠폰 정의만 보상으로 선택할 수 있습니다.';
                }
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
            $errors[] = '결과 규칙 ' . (string) $number . '의 결과 key가 올바르지 않습니다.';
        }
        if ((string) ($rule['title'] ?? '') === '') {
            $errors[] = '결과 규칙 ' . (string) $number . '의 제목을 입력하세요.';
        }
    }

    return array_values(array_unique($errors));
}

function sr_quiz_save_admin_quiz(PDO $pdo, array $values, int $accountId): int
{
    $quizId = (int) ($values['id'] ?? 0);
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
                     title = :title,
                     description = :description,
                     status = :status,
                     quiz_mode = :quiz_mode,
                     scoring_model = :scoring_model,
                     pass_score = :pass_score,
                     starts_at = :starts_at,
                     ends_at = :ends_at,
                     attempt_limit_policy = :attempt_limit_policy,
                     attempt_limit_period_seconds = :attempt_limit_period_seconds,
                     member_group_keys_json = :member_group_keys_json,
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
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
                'reward_enabled' => (int) $values['reward_enabled'],
                'updated_by_account_id' => $accountId,
                'updated_at' => $now,
                'id' => $quizId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_quiz_sets
                    (quiz_key, title, description, status, quiz_mode, scoring_model, pass_score, starts_at, ends_at,
                     attempt_limit_policy, attempt_limit_period_seconds, member_group_keys_json, reward_enabled,
                     created_by_account_id, updated_by_account_id, created_at, updated_at)
                 VALUES
                    (:quiz_key, :title, :description, :status, :quiz_mode, :scoring_model, :pass_score, :starts_at, :ends_at,
                     :attempt_limit_policy, :attempt_limit_period_seconds, :member_group_keys_json, :reward_enabled,
                     :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
            );
            $stmt->execute([
                'quiz_key' => (string) $values['quiz_key'],
                'title' => (string) $values['title'],
                'description' => (string) $values['description'],
                'status' => (string) $values['status'],
                'quiz_mode' => $quizMode,
                'scoring_model' => $scoringModel,
                'pass_score' => $passScore,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'attempt_limit_policy' => $attemptLimitPolicy,
                'attempt_limit_period_seconds' => $attemptLimitPeriodSeconds,
                'member_group_keys_json' => is_string($memberGroupKeysJson) ? $memberGroupKeysJson : '[]',
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
                'cta_label' => '퀴즈 풀기',
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
        'SELECT q.id, q.quiz_key, q.title, q.description, q.member_group_keys_json, s.cta_label
         FROM sr_quiz_sources s
         INNER JOIN sr_quiz_sets q ON q.id = s.quiz_id
         WHERE s.source_module = \'content\'
           AND s.source_type = \'content_item\'
           AND s.source_id = :content_id
           AND s.status = \'active\'
           AND q.status = \'active\'
           AND q.deleted_at IS NULL
           AND (q.starts_at IS NULL OR q.starts_at <= :now_start)
           AND (q.ends_at IS NULL OR q.ends_at >= :now_end)
         ORDER BY s.sort_order ASC, q.updated_at DESC, q.id DESC'
    );
    $stmt->execute([
        'content_id' => $contentId,
        'now_start' => sr_now(),
        'now_end' => sr_now(),
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_community_post_quizzes(PDO $pdo, int $postId): array
{
    if ($postId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT q.id, q.quiz_key, q.title, q.description, q.member_group_keys_json, s.cta_label
         FROM sr_quiz_sources s
         INNER JOIN sr_quiz_sets q ON q.id = s.quiz_id
         WHERE s.source_module = \'community\'
           AND s.source_type = \'community_post\'
           AND s.source_id = :post_id
           AND s.status = \'active\'
           AND q.status = \'active\'
           AND q.deleted_at IS NULL
           AND (q.starts_at IS NULL OR q.starts_at <= :now_start)
           AND (q.ends_at IS NULL OR q.ends_at >= :now_end)
         ORDER BY s.sort_order ASC, q.updated_at DESC, q.id DESC'
    );
    $now = sr_now();
    $stmt->execute([
        'post_id' => $postId,
        'now_start' => $now,
        'now_end' => $now,
    ]);

    return $stmt->fetchAll();
}

function sr_quiz_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_quiz_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_quiz_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT rp.id AS reward_policy_id, rp.status AS reward_policy_status, q.id AS quiz_id, q.quiz_key, q.title, q.status AS quiz_status, q.updated_at
         FROM sr_quiz_reward_policies rp
         INNER JOIN sr_quiz_sets q ON q.id = rp.quiz_id
         WHERE rp.reward_provider = \'coupon\'
           AND rp.reward_code = :definition_id
           AND q.deleted_at IS NULL
         ORDER BY q.updated_at DESC, q.id DESC'
    );
    $stmt->execute(['definition_id' => (string) $definitionId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'quiz',
            'reference_type' => 'quiz_reward_coupon',
            'reference_id' => (string) (int) ($row['reward_policy_id'] ?? 0),
            'quiz_id' => (int) ($row['quiz_id'] ?? 0),
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['quiz_status'] ?? ''),
            'summary' => '퀴즈 보상: ' . (string) ($row['quiz_key'] ?? ''),
            'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode((string) (int) ($row['quiz_id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_quiz_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $quizId = (int) ($row['quiz_id'] ?? $row['reference_id'] ?? 0);
    if ($quizId < 1) {
        return ['status' => 'unknown', 'message' => '퀴즈 참조를 확인할 수 없습니다.'];
    }

    return ['status' => 'ok'];
}

function sr_quiz_coupon_definition_reference_admin_url(array $row, array $context): string
{
    $quizId = (int) ($row['quiz_id'] ?? 0);
    if ($quizId < 1 && preg_match('/\A[1-9][0-9]*\z/', (string) ($row['reference_id'] ?? '')) === 1) {
        $quizId = (int) $row['reference_id'];
    }

    return $quizId > 0 ? '/admin/quiz?mode=edit&id=' . rawurlencode((string) $quizId) : '/admin/quiz';
}
