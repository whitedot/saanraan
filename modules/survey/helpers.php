<?php

declare(strict_types=1);

function sr_survey_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_survey_clean_key(string $value, int $maxLength = 64): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    $value = preg_replace('/\A[^a-z]+/', '', $value) ?? '';

    return substr($value, 0, $maxLength);
}

function sr_survey_clean_text(string $value, int $maxLength): string
{
    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_survey_clean_single_line(string $value, int $maxLength): string
{
    $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
}

function sr_survey_statuses(): array
{
    return ['draft', 'active', 'paused', 'archived'];
}

function sr_survey_status_label(string $status): string
{
    return [
        'draft' => '초안',
        'active' => '공개',
        'paused' => '중지',
        'archived' => '보관',
    ][$status] ?? $status;
}

function sr_survey_question_types(): array
{
    return ['single_choice', 'multiple_choice', 'short_text', 'long_text', 'number', 'rating', 'scale'];
}

function sr_survey_question_type_label(string $type): string
{
    return [
        'single_choice' => '단일 선택',
        'multiple_choice' => '복수 선택',
        'text' => '짧은 답변',
        'short_text' => '짧은 답변',
        'long_text' => '긴 답변',
        'number' => '숫자',
        'rating' => '별점',
        'scale' => '만족도/척도',
    ][$type] ?? $type;
}

function sr_survey_response_limit_policies(): array
{
    return ['per_survey_once', 'per_period', 'unlimited'];
}

function sr_survey_response_limit_policy_label(string $policy): string
{
    return [
        'per_survey_once' => '회원당 1회',
        'per_period' => '기간당 1회',
        'unlimited' => '제한 없음',
    ][$policy] ?? $policy;
}

function sr_survey_quality_statuses(): array
{
    return ['accepted', 'flagged', 'excluded'];
}

function sr_survey_quality_status_label(string $status): string
{
    return [
        'accepted' => '포함',
        'flagged' => '검토',
        'excluded' => '제외',
    ][$status] ?? $status;
}

function sr_survey_default_settings(): array
{
    return [
        'default_status' => 'draft',
        'default_login_required' => 1,
        'default_consent_required' => 0,
        'default_response_limit_policy' => 'per_survey_once',
        'default_response_limit_period_seconds' => 0,
        'public_list_limit' => 50,
    ];
}

function sr_survey_normalize_settings(array $settings): array
{
    $defaults = sr_survey_default_settings();
    $normalized = array_merge($defaults, $settings);
    $normalized['default_status'] = in_array((string) $normalized['default_status'], sr_survey_statuses(), true) ? (string) $normalized['default_status'] : (string) $defaults['default_status'];
    $normalized['default_login_required'] = !empty($normalized['default_login_required']) ? 1 : 0;
    $normalized['default_consent_required'] = !empty($normalized['default_consent_required']) ? 1 : 0;
    $normalized['default_response_limit_policy'] = in_array((string) $normalized['default_response_limit_policy'], sr_survey_response_limit_policies(), true) ? (string) $normalized['default_response_limit_policy'] : (string) $defaults['default_response_limit_policy'];
    $normalized['default_response_limit_period_seconds'] = max(0, (int) $normalized['default_response_limit_period_seconds']);
    $normalized['public_list_limit'] = max(1, min(100, (int) $normalized['public_list_limit']));

    return $normalized;
}

function sr_survey_settings(PDO $pdo): array
{
    return sr_survey_normalize_settings(sr_module_settings($pdo, 'survey'));
}

function sr_survey_settings_from_post(): array
{
    return sr_survey_normalize_settings([
        'default_status' => sr_post_string('default_status', 20),
        'default_login_required' => ($_POST['default_login_required'] ?? '') === '1',
        'default_consent_required' => ($_POST['default_consent_required'] ?? '') === '1',
        'default_response_limit_policy' => sr_survey_clean_key(sr_post_string('default_response_limit_policy', 30), 30),
        'default_response_limit_period_seconds' => sr_post_string('default_response_limit_period_seconds', 20),
        'public_list_limit' => sr_post_string('public_list_limit', 20),
    ]);
}

function sr_survey_settings_validation_errors(array $settings): array
{
    $errors = [];
    if ((string) ($settings['default_response_limit_policy'] ?? '') === 'per_period' && (int) ($settings['default_response_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기본 응답 제한이 기간당 1회이면 제한 기간을 1초 이상 입력해야 합니다.';
    }

    return $errors;
}

function sr_survey_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'survey' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Survey module was not found.');
    }
    $settings = sr_survey_normalize_settings($settings);
    $now = sr_now();
    $save = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ($settings as $key => $value) {
        $valueType = is_int($value) ? 'int' : 'string';
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $key,
            'setting_value' => (string) $value,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('survey');
}

function sr_survey_reward_providers(): array
{
    return ['ledger_asset', 'coupon'];
}

function sr_survey_reward_dedupe_scopes(): array
{
    return ['per_survey', 'per_response'];
}

function sr_survey_clean_admin_datetime(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value) !== 1) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date instanceof DateTimeImmutable || (is_array($errors) && ((int) ($errors['warning_count'] ?? 0) > 0 || (int) ($errors['error_count'] ?? 0) > 0))) {
        return null;
    }

    return $date->format('Y-m-d H:i:s');
}

function sr_survey_datetime_local_value(mixed $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
}

function sr_survey_public_window_is_open(array $survey, ?string $now = null): bool
{
    $now = $now ?? sr_now();
    $startsAt = trim((string) ($survey['starts_at'] ?? ''));
    $endsAt = trim((string) ($survey['ends_at'] ?? ''));

    return ($startsAt === '' || $startsAt <= $now) && ($endsAt === '' || $endsAt >= $now);
}

function sr_survey_public_forms(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT id, survey_key, title, description, updated_at
         FROM sr_survey_forms
         WHERE status = 'active'
           AND deleted_at IS NULL
           AND public_listed = 1
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

function sr_survey_by_key(PDO $pdo, string $surveyKey): ?array
{
    if (!sr_survey_key_is_valid($surveyKey)) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE survey_key = :survey_key AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['survey_key' => $surveyKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_survey_by_id(PDO $pdo, int $surveyId): ?array
{
    if ($surveyId < 1) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $surveyId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_survey_questions_with_choices(PDO $pdo, int $surveyId): array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_questions WHERE survey_id = :survey_id ORDER BY sort_order ASC, id ASC');
    $stmt->execute(['survey_id' => $surveyId]);
    $questions = $stmt->fetchAll();
    $choiceStmt = $pdo->prepare('SELECT * FROM sr_survey_choices WHERE question_id = :question_id ORDER BY sort_order ASC, id ASC');
    foreach ($questions as $index => $question) {
        $choiceStmt->execute(['question_id' => (int) ($question['id'] ?? 0)]);
        $questions[$index]['choices'] = $choiceStmt->fetchAll();
    }

    return $questions;
}

function sr_survey_account_can_respond(PDO $pdo, array $survey, int $accountId): array
{
    if ((int) ($survey['login_required'] ?? 1) === 1 && $accountId < 1) {
        return ['allowed' => false, 'message' => '로그인 후 설문에 참여할 수 있습니다.'];
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

    return ['allowed' => true, 'message' => ''];
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

function sr_survey_coupon_definitions(PDO $pdo): array
{
    if (!sr_module_enabled($pdo, 'coupon') || !is_file(SR_ROOT . '/modules/coupon/helpers.php')) {
        return [];
    }
    require_once SR_ROOT . '/modules/coupon/helpers.php';
    if (!function_exists('sr_coupon_tables_available') || !sr_coupon_tables_available($pdo)) {
        return [];
    }
    $stmt = $pdo->query("SELECT id, coupon_key, title FROM sr_coupon_definitions WHERE status = 'active' ORDER BY title ASC, id ASC LIMIT 200");
    return $stmt->fetchAll();
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

function sr_survey_submit_response(PDO $pdo, array $survey, array $questions, int $accountId, array $answers, array $assetOptions, bool $consentAccepted = false, bool $isTest = false): array
{
    $surveyId = (int) ($survey['id'] ?? 0);
    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $locked = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
        $locked->execute(['id' => $surveyId]);
        $survey = $locked->fetch();
        if (!is_array($survey)) {
            throw new RuntimeException('현재 참여할 수 없는 설문입니다.');
        }
        $access = sr_survey_account_can_respond($pdo, $survey, $accountId);
        if (empty($access['allowed'])) {
            throw new RuntimeException((string) ($access['message'] ?? '현재 참여할 수 없는 설문입니다.'));
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
            sr_survey_validate_answer($question, $answer);
        }

        $snapshotJson = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            'user_agent_hash' => hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
            'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '')),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $responseId = (int) $pdo->lastInsertId();

        sr_survey_insert_response_answers($pdo, $responseId, $questions, $answers, $now);
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

function sr_survey_validate_answer(array $question, mixed $answer): void
{
    $type = (string) ($question['question_type'] ?? 'single_choice');
    if (in_array($type, ['single_choice', 'multiple_choice'], true) && is_array($answer) && $answer !== []) {
        $validChoiceIds = array_map(static fn (array $choice): int => (int) ($choice['id'] ?? 0), (array) ($question['choices'] ?? []));
        $validChoiceIds = array_values(array_filter($validChoiceIds, static fn (int $choiceId): bool => $choiceId > 0));
        foreach ($answer as $choiceId) {
            if (!in_array((int) $choiceId, $validChoiceIds, true)) {
                throw new RuntimeException('선택지 값을 확인해 주세요.');
            }
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

function sr_survey_insert_response_answers(PDO $pdo, int $responseId, array $questions, array $answers, string $now): void
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    $pdo->prepare(
        'INSERT IGNORE INTO sr_survey_reward_grants
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
    $stmt = $pdo->prepare('SELECT * FROM sr_survey_reward_grants WHERE dedupe_key = :dedupe_key LIMIT 1 FOR UPDATE');
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $stmt->fetch();
    if (!is_array($grant)) {
        return [];
    }
    if ((int) ($grant['response_id'] ?? 0) !== $responseId && $scope !== 'per_response') {
        $grant['status'] = 'duplicate';
        return $grant;
    }
    if ((string) ($grant['status'] ?? '') === 'granted') {
        return $grant;
    }

    if ($provider === 'coupon') {
        return sr_survey_issue_coupon_reward_grant($pdo, $survey, (int) $grant['id'], $responseId, $accountId, $policy, $now);
    }
    if ($provider !== 'ledger_asset' || !isset($assetOptions[$module])) {
        return sr_survey_mark_reward_grant_failed($pdo, (int) $grant['id'], '보상 공급자 또는 자산 계약을 찾을 수 없습니다.', $now);
    }
    $transactionFunction = (string) ($assetOptions[$module]['transaction_function'] ?? '');
    if (!function_exists($transactionFunction)) {
        return sr_survey_mark_reward_grant_failed($pdo, (int) $grant['id'], '보상 자산 거래 함수를 찾을 수 없습니다.', $now);
    }
    try {
        $transactionId = (int) $transactionFunction($pdo, [
            'account_id' => $accountId,
            'amount' => $amount,
            'transaction_type' => (string) ($assetOptions[$module]['credit_type'] ?? 'grant'),
            'reason' => '설문 보상: ' . (string) ($survey['title'] ?? ''),
            'reference_type' => 'survey_reward',
            'reference_id' => (string) (int) $grant['id'],
            'created_by_account_id' => null,
        ]);
        sr_survey_mark_reward_grant_granted($pdo, (int) $grant['id'], $responseId, 'survey_reward', (string) (int) $grant['id'], ['transaction_id' => $transactionId], $now);
    } catch (Throwable $exception) {
        return sr_survey_mark_reward_grant_failed($pdo, (int) $grant['id'], sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)), $now);
    }
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $grant = $stmt->fetch();

    return is_array($grant) ? $grant : [];
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
             result_snapshot_json = :result_snapshot_json, granted_at = :granted_at, updated_at = :updated_at
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
