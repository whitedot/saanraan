<?php

declare(strict_types=1);

function sr_survey_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_survey_key_is_reserved(string $key): bool
{
    return in_array($key, ['comment', 'ui_kit', 'admin'], true);
}

function sr_survey_internal_return_path(string $value): string
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

function sr_survey_admin_status_class(string $status): string
{
    return match ($status) {
        'active', 'approved', 'accepted' => 'is-normal',
        'paused', 'needs_fix', 'flagged' => 'is-blocked',
        'draft', 'unchecked' => 'is-left',
        default => 'is-left',
    };
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

function sr_survey_qa_statuses(): array
{
    return ['unchecked', 'needs_fix', 'approved'];
}

function sr_survey_qa_status_label(string $status): string
{
    return [
        'unchecked' => '미점검',
        'needs_fix' => '수정 필요',
        'approved' => '승인',
    ][$status] ?? $status;
}

function sr_survey_normalize_member_group_keys(mixed $groupKeys): array
{
    require_once SR_ROOT . '/modules/member/helpers/groups.php';
    $values = is_array($groupKeys) ? $groupKeys : preg_split('/[\s,]+/', (string) $groupKeys);
    $normalized = [];
    foreach ($values ?: [] as $groupKey) {
        $groupKey = strtolower(trim((string) $groupKey));
        if ($groupKey !== '' && sr_member_group_key_is_valid($groupKey)) {
            $normalized[$groupKey] = true;
        }
    }

    return array_keys($normalized);
}

function sr_survey_member_group_keys_from_json(mixed $json): array
{
    $decoded = json_decode((string) $json, true);
    return sr_survey_normalize_member_group_keys(is_array($decoded) ? $decoded : []);
}

function sr_survey_member_group_keys_json(array $groupKeys): string
{
    $json = json_encode(array_values(sr_survey_normalize_member_group_keys($groupKeys)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function sr_survey_default_settings(): array
{
    return [
        'skin_key' => 'basic',
        'default_status' => 'draft',
        'default_login_required' => 1,
        'default_consent_required' => 0,
        'default_response_limit_policy' => 'per_survey_once',
        'default_response_limit_period_seconds' => 0,
        'public_list_limit' => 50,
    ];
}

function sr_survey_skin_options(): array
{
    return [
        'basic' => '기본형',
    ];
}

function sr_survey_skin_views(): array
{
    return ['home', 'view', 'complete'];
}

function sr_survey_skin_key(string $value): string
{
    $value = strtolower(trim($value));
    return isset(sr_survey_skin_options()[$value]) ? $value : 'basic';
}

function sr_survey_normalize_settings(array $settings): array
{
    $defaults = sr_survey_default_settings();
    $normalized = array_merge($defaults, $settings);
    $normalized['skin_key'] = sr_survey_skin_key((string) ($normalized['skin_key'] ?? $defaults['skin_key']));
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
    $skinKey = sr_survey_clean_key(sr_post_string('skin_key', 40), 40);
    $settings = sr_survey_normalize_settings([
        'skin_key' => $skinKey,
        'default_status' => sr_post_string('default_status', 20),
        'default_login_required' => ($_POST['default_login_required'] ?? '') === '1',
        'default_consent_required' => ($_POST['default_consent_required'] ?? '') === '1',
        'default_response_limit_policy' => sr_survey_clean_key(sr_post_string('default_response_limit_policy', 30), 30),
        'default_response_limit_period_seconds' => sr_post_string('default_response_limit_period_seconds', 20),
        'public_list_limit' => sr_post_string('public_list_limit', 20),
    ]);
    $settings['skin_key'] = $skinKey;

    return $settings;
}

function sr_survey_settings_validation_errors(array $settings): array
{
    $errors = [];
    if (!isset(sr_survey_skin_options()[(string) ($settings['skin_key'] ?? '')])) {
        $errors[] = '설문 스킨 값이 올바르지 않습니다.';
    }
    if ((string) ($settings['default_response_limit_policy'] ?? '') === 'per_period' && (int) ($settings['default_response_limit_period_seconds'] ?? 0) < 1) {
        $errors[] = '기본 응답 제한이 기간당 1회이면 제한 기간을 1초 이상 입력해야 합니다.';
    }

    return $errors;
}

function sr_survey_public_layout_context(array $settings, array $context = []): array
{
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = '/modules/survey/assets/public.css';
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $skinKey = sr_survey_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $bodyClass = sr_ui_icon_class_attr((string) ($context['body_class'] ?? ''));
    $context['body_class'] = trim($bodyClass . ' survey-theme-basic survey-skin-' . $skinKey);

    return $context;
}

function sr_survey_skin_view_file(array $settings, string $view): string
{
    if (!in_array($view, sr_survey_skin_views(), true)) {
        throw new InvalidArgumentException('Unknown survey skin view.');
    }

    $skinKey = sr_survey_skin_key((string) ($settings['skin_key'] ?? 'basic'));
    $file = SR_ROOT . '/modules/survey/skins/' . $skinKey . '/' . $view . '.php';
    if ($skinKey !== 'basic' && is_file($file)) {
        return $file;
    }

    $fallback = SR_ROOT . '/modules/survey/skins/basic/' . $view . '.php';
    if ($skinKey !== 'basic' || !is_file($file)) {
        error_log('survey_skin_fallback module=survey skin_key=' . $skinKey . ' view=' . $view . ' fallback_file=' . $fallback);
    }
    if (!is_file($fallback)) {
        throw new RuntimeException('Default survey skin view is missing: ' . $view);
    }

    return $fallback;
}

function sr_survey_render_skin(PDO $pdo, array $settings, string $view): void
{
    include sr_survey_skin_view_file($settings, $view);
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

function sr_survey_coupon_definition_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_survey_coupon_definition_reference_rows($pdo, $target, $context));
}

function sr_survey_coupon_definition_reference_rows(PDO $pdo, array $target, array $context): array
{
    $definitionId = (int) ($target['target_id'] ?? 0);
    if ($definitionId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT rp.id AS reward_policy_id, rp.status AS reward_policy_status, s.id AS survey_id, s.survey_key, s.title, s.status AS survey_status, s.updated_at
         FROM sr_survey_reward_policies rp
         INNER JOIN sr_survey_forms s ON s.id = rp.survey_id
         WHERE rp.reward_provider = \'coupon\'
           AND rp.reward_code = :definition_id
           AND s.deleted_at IS NULL
         ORDER BY s.updated_at DESC, s.id DESC'
    );
    $stmt->execute(['definition_id' => (string) $definitionId]);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[] = [
            'consumer_module_key' => 'survey',
            'reference_type' => 'survey_reward_coupon',
            'reference_id' => (string) (int) ($row['reward_policy_id'] ?? 0),
            'survey_id' => (int) ($row['survey_id'] ?? 0),
            'target_type' => 'coupon_definition',
            'target_id' => (string) $definitionId,
            'title' => (string) ($row['title'] ?? ''),
            'status' => (string) ($row['survey_status'] ?? ''),
            'summary' => '설문 보상: ' . (string) ($row['survey_key'] ?? ''),
            'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) (int) ($row['survey_id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_survey_coupon_definition_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $surveyId = (int) ($row['survey_id'] ?? 0);
    if ($surveyId < 1) {
        return ['status' => 'unknown', 'message' => '설문 참조를 확인할 수 없습니다.'];
    }

    return ['status' => 'ok'];
}

function sr_survey_coupon_definition_reference_admin_url(array $row, array $context): string
{
    $surveyId = (int) ($row['survey_id'] ?? 0);

    return $surveyId > 0 ? '/admin/surveys?mode=edit&id=' . rawurlencode((string) $surveyId) : '/admin/surveys';
}

function sr_survey_member_group_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_survey_member_group_reference_rows($pdo, $target, $context));
}

function sr_survey_member_group_reference_rows(PDO $pdo, array $target, array $context): array
{
    $targetKey = (string) ($target['target_key'] ?? '');
    if ($targetKey === '') {
        require_once SR_ROOT . '/modules/member/helpers/groups.php';
        $groupId = (int) ($target['target_id'] ?? 0);
        if ($groupId > 0 && sr_member_groups_table_exists($pdo)) {
            $stmt = $pdo->prepare('SELECT group_key FROM sr_member_groups WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $groupId]);
            $targetKey = (string) $stmt->fetchColumn();
        }
    }
    $targetKey = strtolower(trim($targetKey));
    if ($targetKey === '') {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT id, survey_key, title, status, member_group_keys_json, updated_at
         FROM sr_survey_forms
         WHERE member_group_keys_json IS NOT NULL
           AND member_group_keys_json <> \'\'
           AND deleted_at IS NULL
         ORDER BY updated_at DESC, id DESC
         LIMIT 500'
    );
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $groupKeys = sr_survey_member_group_keys_from_json($row['member_group_keys_json'] ?? '[]');
        if (!in_array($targetKey, $groupKeys, true)) {
            continue;
        }
        $rows[] = [
            'consumer_module_key' => 'survey',
            'reference_type' => 'survey_member_group_target',
            'reference_id' => 'survey_form:' . (string) (int) ($row['id'] ?? 0),
            'target_type' => 'member_group',
            'target_id' => (string) ($target['target_id'] ?? ''),
            'target_key' => $targetKey,
            'title' => (string) ($row['title'] ?? ''),
            'policy_status' => (string) ($row['status'] ?? ''),
            'summary' => '설문 참여 대상: ' . (string) ($row['survey_key'] ?? ''),
            'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    return $rows;
}

function sr_survey_member_group_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $status = (string) ($row['policy_status'] ?? '');

    return $status === 'active'
        ? ['status' => 'ok', 'policy_status' => $status]
        : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '설문이 공개 상태가 아닙니다.'];
}

function sr_survey_member_group_reference_admin_url(array $row, array $context): string
{
    return (string) ($row['admin_url'] ?? '/admin/surveys');
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

function sr_survey_time_html(string $value): string
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

function sr_survey_homepage_candidates(PDO $pdo): array
{
    return [
        [
            'module_key' => 'survey',
            'label' => '설문 메인',
            'path' => '/survey',
            'detail' => '/survey',
            'available' => true,
        ],
    ];
}

function sr_survey_homepage_path_is_available(PDO $pdo, string $homePath): ?bool
{
    if ($homePath === '/survey') {
        return true;
    }

    if (str_starts_with($homePath, '/survey/')) {
        $surveyKey = rawurldecode(substr($homePath, strlen('/survey/')));
        $survey = sr_survey_by_key($pdo, $surveyKey);
        return is_array($survey)
            && (string) ($survey['status'] ?? '') === 'active'
            && (int) ($survey['public_listed'] ?? 1) === 1
            && (int) ($survey['login_required'] ?? 1) !== 1
            && sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]') === []
            && sr_survey_public_window_is_open($survey);
    }

    return null;
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

function sr_survey_comments_table_exists(PDO $pdo): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }

    try {
        $pdo->query('SELECT 1 FROM sr_survey_comments LIMIT 1');
        $exists = true;
    } catch (Throwable $exception) {
        $exists = false;
    }

    return $exists;
}

function sr_survey_comment_statuses(): array
{
    return ['published', 'hidden', 'deleted'];
}

function sr_survey_comment_status_label(string $status): string
{
    return [
        'published' => '게시',
        'hidden' => '숨김',
        'deleted' => '삭제',
    ][$status] ?? $status;
}

function sr_survey_comment_author_public_name_snapshot(PDO $pdo, int $accountId): string
{
    $name = trim(sr_member_public_name_for_account_id($pdo, $accountId, '회원'));

    return function_exists('mb_substr') ? mb_substr($name, 0, 120) : substr($name, 0, 120);
}

function sr_survey_comment_thread_columns_exist(PDO $pdo): bool
{
    static $existsByConnection = [];
    $key = (string) spl_object_id($pdo);
    if (array_key_exists($key, $existsByConnection)) {
        return $existsByConnection[$key];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME IN (\'parent_comment_id\', \'thread_root_id\', \'depth\')'
        );
        $stmt->execute(['table_name' => 'sr_survey_comments']);
        $existsByConnection[$key] = (int) $stmt->fetchColumn() === 3;
    } catch (Throwable $exception) {
        $existsByConnection[$key] = false;
    }

    return $existsByConnection[$key];
}

function sr_survey_comments(PDO $pdo, int $surveyId, int $limit = 100): array
{
    if ($surveyId < 1 || !sr_survey_comments_table_exists($pdo)) {
        return [];
    }

    $orderSql = sr_survey_comment_thread_columns_exist($pdo)
        ? 'COALESCE(c.thread_root_id, c.id) ASC, c.depth ASC, c.id ASC'
        : 'c.id ASC';
    $stmt = $pdo->prepare(
        "SELECT c.*, a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_survey_comments c
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.survey_id = :survey_id
           AND c.status = 'published'
         ORDER BY " . $orderSql . "
         LIMIT :limit_value"
    );
    $stmt->bindValue('survey_id', $surveyId, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    $settings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], $settings, '회원');
        $comments[] = $comment;
    }

    return $comments;
}

function sr_survey_comment_input_values(): array
{
    $parentCommentIdValue = sr_post_string('parent_comment_id', 20);

    return [
        'body_text' => sr_survey_clean_text(sr_post_string('body_text', 4000), 4000),
        'is_secret' => ($_POST['is_secret'] ?? '') === '1' ? 1 : 0,
        'parent_comment_id' => preg_match('/\A[1-9][0-9]*\z/', $parentCommentIdValue) === 1 ? (int) $parentCommentIdValue : 0,
    ];
}

function sr_survey_validate_comment_input(array $values): array
{
    $errors = [];
    $bodyText = trim((string) ($values['body_text'] ?? ''));
    if ($bodyText === '') {
        $errors[] = '댓글 내용을 입력하세요.';
    }
    if ((function_exists('mb_strlen') ? mb_strlen($bodyText) : strlen($bodyText)) > 4000) {
        $errors[] = '댓글은 4000자 이내로 입력하세요.';
    }

    return $errors;
}

function sr_survey_validate_comment_parent(PDO $pdo, int $surveyId, array $values): array
{
    $parentCommentId = (int) ($values['parent_comment_id'] ?? 0);
    if ($parentCommentId < 1) {
        return ['parent_comment' => null, 'errors' => []];
    }
    if (!sr_survey_comment_thread_columns_exist($pdo)) {
        return ['parent_comment' => null, 'errors' => ['답글 기능을 사용할 수 없습니다. 업데이트를 먼저 적용해 주세요.']];
    }

    $parentComment = sr_survey_comment_by_id($pdo, $parentCommentId);
    if (!is_array($parentComment) || (int) ($parentComment['survey_id'] ?? 0) !== $surveyId || (string) ($parentComment['status'] ?? '') !== 'published') {
        return ['parent_comment' => null, 'errors' => ['답글을 작성할 댓글을 찾을 수 없습니다.']];
    }
    if ((int) ($parentComment['depth'] ?? 1) >= 3) {
        return ['parent_comment' => null, 'errors' => ['답글은 3단계까지만 작성할 수 있습니다.']];
    }

    return ['parent_comment' => $parentComment, 'errors' => []];
}

function sr_survey_create_comment(PDO $pdo, int $surveyId, int $accountId, array $values): int
{
    if ($surveyId < 1 || $accountId < 1 || !sr_survey_comments_table_exists($pdo)) {
        throw new RuntimeException('Survey comment cannot be created.');
    }

    $now = sr_now();
    $threadColumnSql = sr_survey_comment_thread_columns_exist($pdo) ? 'parent_comment_id, thread_root_id, depth, ' : '';
    $threadValueSql = $threadColumnSql !== '' ? ':parent_comment_id, :thread_root_id, :depth, ' : '';
    $parentComment = is_array($values['parent_comment'] ?? null) ? $values['parent_comment'] : null;
    $parentCommentId = is_array($parentComment) ? (int) ($parentComment['id'] ?? 0) : 0;
    $depth = is_array($parentComment) ? min(3, max(2, (int) ($parentComment['depth'] ?? 1) + 1)) : 1;
    $threadRootId = is_array($parentComment) ? (int) (($parentComment['thread_root_id'] ?? 0) ?: ($parentComment['id'] ?? 0)) : null;
    $stmt = $pdo->prepare(
        'INSERT INTO sr_survey_comments
            (survey_id, ' . $threadColumnSql . 'author_account_id, author_public_name_snapshot, body_text, is_secret, status, created_at, updated_at)
         VALUES
            (:survey_id, ' . $threadValueSql . ':author_account_id, :author_public_name_snapshot, :body_text, :is_secret, \'published\', :created_at, :updated_at)'
    );
    $params = [
        'survey_id' => $surveyId,
        'author_account_id' => $accountId,
        'author_public_name_snapshot' => sr_survey_comment_author_public_name_snapshot($pdo, $accountId),
        'body_text' => (string) ($values['body_text'] ?? ''),
        'is_secret' => (int) ($values['is_secret'] ?? 0),
        'created_at' => $now,
        'updated_at' => $now,
    ];
    if ($threadColumnSql !== '') {
        $params['parent_comment_id'] = $parentCommentId > 0 ? $parentCommentId : null;
        $params['thread_root_id'] = $threadRootId;
        $params['depth'] = $depth;
    }
    $stmt->execute($params);

    $commentId = (int) $pdo->lastInsertId();
    if ($threadColumnSql !== '' && $parentCommentId < 1) {
        $stmt = $pdo->prepare(
            'UPDATE sr_survey_comments
             SET thread_root_id = :thread_root_id
             WHERE id = :id'
        );
        $stmt->execute([
            'thread_root_id' => $commentId,
            'id' => $commentId,
        ]);
    }

    return $commentId;
}

function sr_survey_comment_by_id(PDO $pdo, int $commentId): ?array
{
    if ($commentId < 1 || !sr_survey_comments_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_survey_comments WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $commentId]);
    $comment = $stmt->fetch();

    return is_array($comment) ? $comment : null;
}

function sr_survey_account_can_edit_comment(array $comment, array $account): bool
{
    return (string) ($comment['status'] ?? '') === 'published'
        && (int) ($comment['author_account_id'] ?? 0) > 0
        && (int) ($comment['author_account_id'] ?? 0) === (int) ($account['id'] ?? 0);
}

function sr_survey_account_can_manage_comments(PDO $pdo, int $accountId): bool
{
    return $accountId > 0
        && function_exists('sr_admin_has_permission')
        && (
            sr_admin_has_permission($pdo, $accountId, '/admin/surveys/comments', 'view')
            || sr_admin_has_permission($pdo, $accountId, '/admin/surveys', 'edit')
        );
}

function sr_survey_account_can_view_comment_body(array $comment, ?array $account, PDO $pdo): bool
{
    if ((int) ($comment['is_secret'] ?? 0) !== 1) {
        return true;
    }
    if (!is_array($account)) {
        return false;
    }
    $accountId = (int) ($account['id'] ?? 0);

    return $accountId > 0
        && (
            $accountId === (int) ($comment['author_account_id'] ?? 0)
            || sr_survey_account_owns_comment_target($pdo, $comment, $accountId)
            || sr_survey_account_can_manage_comments($pdo, $accountId)
        );
}

function sr_survey_account_owns_comment_target(PDO $pdo, array $comment, int $accountId): bool
{
    $surveyId = (int) ($comment['survey_id'] ?? 0);
    if ($surveyId < 1 || $accountId < 1) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT created_by_account_id
         FROM sr_survey_forms
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $surveyId]);

    return (int) $stmt->fetchColumn() === $accountId;
}

function sr_survey_account_can_delete_comment(array $comment, array $account, PDO $pdo): bool
{
    if (!in_array((string) ($comment['status'] ?? ''), ['published', 'hidden'], true)) {
        return false;
    }
    if (sr_survey_account_can_edit_comment($comment, $account)) {
        return true;
    }

    return sr_survey_account_can_manage_comments($pdo, (int) ($account['id'] ?? 0));
}

function sr_survey_update_comment_content(PDO $pdo, int $commentId, array $values): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_survey_comments
         SET body_text = :body_text,
             is_secret = :is_secret,
             updated_at = :updated_at
         WHERE id = :id
           AND status = \'published\''
    );
    $stmt->execute([
        'body_text' => (string) ($values['body_text'] ?? ''),
        'is_secret' => (int) ($values['is_secret'] ?? 0),
        'updated_at' => sr_now(),
        'id' => $commentId,
    ]);
}

function sr_survey_update_comment_status(PDO $pdo, int $commentId, string $status): void
{
    if (!in_array($status, sr_survey_comment_statuses(), true)) {
        throw new RuntimeException('Invalid survey comment status.');
    }
    if ($status === 'deleted') {
        sr_survey_delete_comment_redacted($pdo, $commentId);
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_survey_comments
         SET status = :status,
             deleted_at = CASE WHEN :deleted_status = \'deleted\' THEN :deleted_at ELSE deleted_at END,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    $stmt->execute([
        'status' => $status,
        'deleted_status' => $status,
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_survey_delete_comment_redacted(PDO $pdo, int $commentId): void
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_survey_comments
         SET author_public_name_snapshot = '',
             body_text = :body_text,
             status = 'deleted',
             deleted_at = :deleted_at,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'body_text' => '삭제된 댓글입니다.',
        'deleted_at' => $now,
        'updated_at' => $now,
        'id' => $commentId,
    ]);
}

function sr_survey_soft_delete_redacted(PDO $pdo, int $surveyId, int $accountId): bool
{
    if ($surveyId < 1) {
        return false;
    }

    $now = sr_now();
    $deletedTitle = '삭제된 설문';
    $deletedBody = '삭제된 설문입니다.';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_survey_forms
             SET title = :title,
                 description = :description,
                 research_purpose = '',
                 target_population = '',
                 recruitment_method = '',
                 project_brief = '',
                 sponsor_name = '',
                 research_region = '',
                 research_language = '',
                 fieldwork_method = '',
                 sample_frame = '',
                 sample_method = '',
                 quota_policy = '',
                 response_rate_basis = '',
                 analysis_plan = '',
                 weighting_policy = '',
                 margin_error_note = '',
                 methodology_disclosure = '',
                 ethics_note = '',
                 sensitive_data_policy = '',
                 recontact_policy = '',
                 withdrawal_policy = '',
                 vendor_name = '',
                 external_channel_policy = '',
                 invite_token_policy = '',
                 qa_note = '',
                 organizer_name = '',
                 contact_text = '',
                 consent_text = '',
                 privacy_notice = '',
                 comments_enabled = 0,
                 secret_comments_enabled = 0,
                 reward_enabled = 0,
                 updated_by_account_id = :account_id,
                 updated_at = :updated_at,
                 deleted_at = :deleted_at
             WHERE id = :id
               AND deleted_at IS NULL"
        );
        $stmt->execute([
            'title' => $deletedTitle,
            'description' => $deletedBody,
            'account_id' => $accountId,
            'updated_at' => $now,
            'deleted_at' => $now,
            'id' => $surveyId,
        ]);
        if ($stmt->rowCount() < 1) {
            $pdo->rollBack();
            return false;
        }

        $pdo->prepare(
            "UPDATE sr_survey_questions
             SET prompt = :prompt,
                 analysis_note = '',
                 scale_min_label = '',
                 scale_max_label = '',
                 number_unit = '',
                 settings_json = NULL,
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'prompt' => $deletedBody,
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            'UPDATE sr_survey_choices c
             INNER JOIN sr_survey_questions q ON q.id = c.question_id
             SET c.label = :label,
                 c.settings_json = NULL,
                 c.updated_at = :updated_at
             WHERE q.survey_id = :survey_id'
        )->execute([
            'label' => '삭제된 선택지',
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_comments
             SET author_public_name_snapshot = '',
                 body_text = :body_text,
                 status = 'deleted',
                 deleted_at = COALESCE(deleted_at, :deleted_at),
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'body_text' => '삭제된 댓글입니다.',
            'deleted_at' => $now,
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_responses
             SET quality_note = '',
                 consent_snapshot_json = '{}',
                 metadata_snapshot_json = '{}',
                 answer_snapshot_json = '{}',
                 updated_at = :updated_at
             WHERE survey_id = :survey_id"
        )->execute([
            'updated_at' => $now,
            'survey_id' => $surveyId,
        ]);
        $pdo->prepare(
            "UPDATE sr_survey_response_answers ra
             INNER JOIN sr_survey_responses r ON r.id = ra.response_id
             SET ra.answer_text = NULL,
                 ra.answer_number = NULL,
                 ra.other_text = NULL,
                 ra.answer_snapshot_json = '{}'
             WHERE r.survey_id = :survey_id"
        )->execute(['survey_id' => $surveyId]);
        $pdo->prepare(
            "UPDATE sr_survey_reward_grants
             SET request_snapshot_json = '{}',
                 result_snapshot_json = '{}',
                 error_message = ''
             WHERE survey_id = :survey_id"
        )->execute(['survey_id' => $surveyId]);

        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function sr_survey_notification_event_function(PDO $pdo): ?string
{
    return sr_module_contract_function($pdo, 'notification', 'notification-events.php', 'create_account_event_function');
}

function sr_survey_create_account_event_notification(PDO $pdo, int $accountId, string $eventKey, array $metadata, ?int $createdByAccountId = null): bool
{
    if ($accountId < 1) {
        return false;
    }
    $function = sr_survey_notification_event_function($pdo);
    if ($function === null || !function_exists($function)) {
        return false;
    }

    $notificationId = $function($pdo, [
        'account_id' => $accountId,
        'module_key' => 'survey',
        'event_key' => $eventKey,
        'metadata' => $metadata,
        'created_by_account_id' => $createdByAccountId,
    ]);

    return (int) $notificationId > 0;
}

function sr_survey_mentioned_account_ids(PDO $pdo, string $bodyText, array $excludeAccountIds = []): array
{
    if (!function_exists('sr_member_mention_account_ids')) {
        return [];
    }

    return sr_member_mention_account_ids($pdo, sr_runtime_config(), $bodyText, $excludeAccountIds);
}

function sr_survey_create_comment_mention_notifications(
    PDO $pdo,
    array $survey,
    int $commentId,
    string $bodyText,
    int $createdByAccountId,
    array $excludeAccountIds = [],
    string $previousBodyText = ''
): array {
    $surveyId = (int) ($survey['id'] ?? 0);
    $mentionedAccountIds = sr_survey_mentioned_account_ids($pdo, $bodyText, $excludeAccountIds);
    if ($previousBodyText !== '') {
        $previousAccountIds = sr_survey_mentioned_account_ids($pdo, $previousBodyText, $excludeAccountIds);
        $mentionedAccountIds = array_values(array_diff($mentionedAccountIds, $previousAccountIds));
    }
    $result = [
        'mention_candidate_count' => count($mentionedAccountIds),
        'mention_notification_count' => 0,
        'mention_account_hashes' => [],
    ];
    if ($mentionedAccountIds === []) {
        return $result;
    }

    $config = sr_runtime_config();
    $metadata = [
        'survey_id' => $surveyId,
        'comment_id' => $commentId,
        'member_name' => sr_member_public_name_for_account_id($pdo, $createdByAccountId, '회원'),
        'link_url' => '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')) . '#survey-comments',
        'created_at' => sr_now(),
    ];
    foreach ($mentionedAccountIds as $accountId) {
        $result['mention_account_hashes'][] = sr_member_public_account_hash($config, (int) $accountId);
    }
    foreach ($mentionedAccountIds as $accountId) {
        if (sr_survey_create_account_event_notification($pdo, (int) $accountId, 'comment.mention', $metadata, $createdByAccountId)) {
            $result['mention_notification_count']++;
        }
    }

    return $result;
}

function sr_survey_admin_comment_filters_from_request(): array
{
    return [
        'q' => sr_survey_clean_single_line(sr_get_string('q', 120), 120),
        'status' => sr_survey_clean_key(sr_get_string('status', 20), 20),
        'secret' => sr_survey_clean_key(sr_get_string('secret', 10), 10),
    ];
}

function sr_survey_admin_comments(PDO $pdo, array $filters = [], int $limit = 100): array
{
    if (!sr_survey_comments_table_exists($pdo)) {
        return [];
    }

    $where = ['1 = 1'];
    $params = [];
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $where[] = '(s.survey_key LIKE :keyword OR s.title LIKE :keyword OR c.body_text LIKE :keyword OR c.author_public_name_snapshot LIKE :keyword)';
        $params['keyword'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
    }
    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && in_array($status, sr_survey_comment_statuses(), true)) {
        $where[] = 'c.status = :status';
        $params['status'] = $status;
    }
    $secret = (string) ($filters['secret'] ?? '');
    if ($secret === 'yes' || $secret === 'no') {
        $where[] = 'c.is_secret = :is_secret';
        $params['is_secret'] = $secret === 'yes' ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        'SELECT c.*, s.survey_key, s.title AS survey_title
         FROM sr_survey_comments c
         INNER JOIN sr_survey_forms s ON s.id = c.survey_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT ' . (string) max(1, min(200, $limit))
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
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
        $locked = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
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
