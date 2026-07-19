#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
chdir($root);

$errors = [];

function sr_survey_check_read(string $path): string
{
    global $errors;

    $contents = file_get_contents($path);
    if (!is_string($contents)) {
        $errors[] = 'Cannot read file: ' . $path;
        return '';
    }

    return $contents;
}

function sr_survey_check_contains(string $path, string $needle, string $message): void
{
    global $errors;

    $contents = sr_survey_check_read($path);
    if ($contents === '' || !str_contains($contents, $needle)) {
        $errors[] = $message . ': ' . $path;
    }
}

function sr_survey_check_not_contains(string $path, string $needle, string $message): void
{
    global $errors;

    $contents = sr_survey_check_read($path);
    if ($contents !== '' && str_contains($contents, $needle)) {
        $errors[] = $message . ': ' . $path;
    }
}

function sr_survey_check_save_binding_parity(): void
{
    global $errors;

    $contents = sr_survey_check_read('modules/survey/helpers/admin-surveys.php');
    $valuesStart = strpos($contents, '        $surveyValues = [');
    $valuesEnd = $valuesStart === false ? false : strpos($contents, "\n        ];", $valuesStart);
    if ($valuesStart === false || $valuesEnd === false) {
        $errors[] = 'Survey create and update must share one normalized value map.';
        return;
    }

    $valuesBlock = substr($contents, $valuesStart, $valuesEnd - $valuesStart);
    preg_match_all('/^\s*\'([a-z0-9_]+)\'\s*=>/m', $valuesBlock, $keyMatches);
    $valueKeys = array_values(array_unique($keyMatches[1] ?? []));
    foreach ([
        'UPDATE sr_survey_forms' => ['questionnaire_version', 'updated_at', 'id'],
        'INSERT INTO sr_survey_forms' => ['created_by_account_id', 'created_at', 'updated_at'],
    ] as $sqlMarker => $branchKeys) {
        $sqlStart = strpos($contents, $sqlMarker, $valuesEnd);
        $sqlEnd = $sqlStart === false ? false : strpos($contents, ')->execute(array_merge($surveyValues', $sqlStart);
        if ($sqlStart === false || $sqlEnd === false) {
            $errors[] = 'Survey save SQL must use the shared value map: ' . $sqlMarker;
            continue;
        }

        preg_match_all('/:([a-z0-9_]+)/', substr($contents, $sqlStart, $sqlEnd - $sqlStart), $placeholderMatches);
        $commonPlaceholders = array_values(array_diff(array_unique($placeholderMatches[1] ?? []), $branchKeys));
        $missing = array_values(array_diff($commonPlaceholders, $valueKeys));
        $unused = array_values(array_diff($valueKeys, $commonPlaceholders));
        if ($missing !== [] || $unused !== []) {
            $errors[] = $sqlMarker . ' shared bindings differ. missing=' . implode(',', $missing) . ' unused=' . implode(',', $unused);
        }
    }
}

sr_survey_check_save_binding_parity();

sr_survey_check_contains(
    'modules/survey/install.sql',
    'CREATE TABLE IF NOT EXISTS sr_survey_reward_grants',
    'Survey reward grants table must exist'
);
sr_survey_check_contains(
    'modules/survey/install.sql',
    "comment_editor_key VARCHAR(40) NOT NULL DEFAULT 'inherit'",
    'Survey items must store an individual comment editor override'
);
foreach ([
    "'GET /admin/surveys/reward-logs' => 'actions/admin-reward-logs.php'",
    "'label' => '보상 로그'",
    "'path' => '/admin/surveys/reward-logs'",
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/' . (str_contains($needle, 'actions/admin-reward-logs') ? 'paths.php' : 'admin-menu.php'),
        $needle,
        'Survey admin navigation must expose reward logs'
    );
}
foreach ([
    'sr_admin_require_permission($pdo, (int) ($account[\'id\'] ?? 0), \'/admin/surveys/reward-logs\', \'view\')',
    'sr_survey_reward_log_filters_from_request',
    'sr_survey_reward_logs',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-reward-logs.php',
        $needle,
        'Survey reward log action must query reward logs with admin permission'
    );
}
foreach ([
    'sr_survey_reward_log_statuses',
    'sr_survey_reward_log_count',
    'sr_survey_reward_logs',
    'FROM sr_survey_reward_grants g',
    'g.error_message LIKE :q_like',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers/responses.php',
        $needle,
        'Survey reward log helper must read grant rows as operator reward logs'
    );
}
foreach ([
    '$adminPageTitle = \'설문 보상 로그\'',
    'action="<?php echo sr_e(sr_url(\'/admin/surveys/reward-logs\')); ?>"',
    'filtering filtering-card',
    'survey_reward_log_detail_filters',
    'data-filtering-toggle',
    'data-filtering-reset',
    'name="survey_id"',
    '보상 로그가 없습니다.',
    'sr_survey_reward_log_status_label',
    'sr_survey_reward_provider_label',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/views/admin-reward-logs.php',
        $needle,
        'Survey reward log view must render searchable operator log'
    );
}
sr_survey_check_contains(
    'modules/survey/install.sql',
    'CREATE TABLE IF NOT EXISTS sr_survey_comments',
    'Survey comments table must exist'
);
sr_survey_check_contains(
    'modules/survey/install.sql',
    'comments_enabled TINYINT(1)',
    'Survey forms must expose comments_enabled'
);
foreach (['skin_key VARCHAR(40) NOT NULL DEFAULT \'\''] as $needle) {
    sr_survey_check_contains(
        'modules/survey/install.sql',
        $needle,
        'Survey forms must store individual skin override key'
    );
}
sr_survey_check_contains(
    'modules/survey/module.php',
    "'version' => '2026.07.008'",
    'Survey module version must include the item comment editor schema update'
);
foreach (['CREATE TABLE IF NOT EXISTS sr_survey_groups', 'CREATE TABLE IF NOT EXISTS sr_survey_setting_sources', 'survey_group_id BIGINT UNSIGNED NULL'] as $needle) {
    sr_survey_check_contains('modules/survey/install.sql', $needle, 'Survey group schema must be installed');
}
foreach (["'GET /admin/surveys/groups'", "'POST /admin/surveys/groups'"] as $needle) {
    sr_survey_check_contains('modules/survey/paths.php', $needle, 'Survey group admin routes must be registered');
}
foreach ([
    'function sr_survey_admin_group_count',
    'function sr_survey_admin_group_sort_options',
    'function sr_survey_admin_group_default_sort',
    'function sr_survey_admin_groups',
    'LIMIT :limit_value OFFSET :offset_value',
] as $needle) {
    sr_survey_check_contains('modules/survey/helpers/groups.php', $needle, 'Survey group admin list must use bounded sortable queries');
}
foreach ([
    'admin-list-card admin-list-form',
    'sr_admin_pagination_summary_html($groupPagination)',
    "sr_admin_sort_header_html('식별값'",
    '<th class="text-end">관리</th>',
    'class="admin-empty-state"',
    'class="badge-status <?php echo sr_e($groupStatusClass); ?>"',
    'class="admin-table-actions-cell"',
    'class="admin-row-actions"',
    'class="admin-icon-button-legend"',
    "sr_admin_pagination_html(\$groupPagination, '설문 그룹 목록 페이지')",
] as $needle) {
    sr_survey_check_contains('modules/survey/actions/admin-groups.php', $needle, 'Survey group admin list must use the shared list UI');
}
foreach (['<th>Key</th>', '<th>작업</th>', 'class="table-empty"'] as $needle) {
    sr_survey_check_not_contains('modules/survey/actions/admin-groups.php', $needle, 'Survey group admin list must not keep legacy list markup');
}
foreach ([
    "'item' => '단독'",
    "'group' => '그룹'",
    "'all' => '전체'",
    "\$surveyScopeRadioHtml('research_purpose'",
    "\$surveyScopeRadioHtml('status'",
    "\$surveyScopeRadioHtml('comments_enabled'",
    "\$surveyScopeRadioHtml('consent_required'",
    'source_comment_extra_fields_json',
] as $needle) {
    sr_survey_check_contains('modules/survey/actions/admin-surveys.php', $needle, 'Survey setting scope UI must expose item, group, and all');
}
foreach ([
    "\$surveyScopeRadioHtml('display'",
    "\$surveyScopeRadioHtml('publication'",
    "\$surveyScopeRadioHtml('participation'",
    "\$surveyScopeRadioHtml('comments'",
    "\$surveyScopeRadioHtml('reactions'",
    "\$surveyScopeRadioHtml('consent'",
] as $needle) {
    sr_survey_check_not_contains('modules/survey/actions/admin-surveys.php', $needle, 'Survey scope UI must use setting-level controls instead of legacy bundles');
}
foreach (['ALTER TABLE sr_survey_forms', 'ADD COLUMN skin_key VARCHAR(40) NOT NULL DEFAULT \'\''] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.009.sql',
        $needle,
        'Survey display override update must add individual skin column'
    );
}
sr_survey_check_not_contains(
    'modules/survey/install.sql',
    'theme_key',
    'Survey initial schema must not keep legacy theme key'
);
foreach (['ALTER TABLE sr_survey_forms', 'ADD COLUMN reaction_preset_key VARCHAR(80) NOT NULL DEFAULT \'\'', 'ADD COLUMN reaction_comment_preset_key VARCHAR(80) NOT NULL DEFAULT \'\''] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.011.sql',
        $needle,
        'Survey reaction preset update must add preset columns'
    );
}
foreach (['ALTER TABLE sr_survey_forms', 'ADD COLUMN cover_image_url VARCHAR(255) NOT NULL DEFAULT \'\''] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.012.sql',
        $needle,
        'Survey cover image update must add cover image column'
    );
}
foreach (['ADD COLUMN view_count BIGINT UNSIGNED NOT NULL DEFAULT 0', 'ADD KEY idx_sr_survey_forms_view_count (view_count, id)'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.013.sql',
        $needle,
        'Survey view count update must add view count column and index'
    );
}
foreach (["name = '설문·여론조사'", "version = '2026.06.014'"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.014.sql',
        $needle,
        'Survey display name update must sync installed module record'
    );
}
foreach (['DELETE FROM {{SR_TABLE_PREFIX}}admin_account_permissions', "WHERE menu_path = '/admin/surveys/manual'", "version = '2026.06.016'"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.016.sql',
        $needle,
        'Survey 2026.06.016 update must remove legacy manual permissions using current admin permission schema'
    );
}
foreach (['admin_permissions', 'WHERE path ='] as $needle) {
    sr_survey_check_not_contains(
        'modules/survey/updates/2026.06.016.sql',
        $needle,
        'Survey 2026.06.016 update must not use legacy admin permission schema'
    );
}
sr_survey_check_contains(
    'modules/survey/updates/2026.06.005.sql',
    'CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}survey_comments',
    'Survey comments update must create comments table'
);
sr_survey_check_contains(
    'modules/survey/install.sql',
    'account_id BIGINT UNSIGNED NULL',
    'Survey reward grants account_id must allow privacy cleanup nulling'
);
sr_survey_check_contains(
    'modules/survey/updates/2026.06.004.sql',
    'MODIFY COLUMN account_id BIGINT UNSIGNED NULL',
    'Survey reward grants privacy cleanup schema update must be present'
);
foreach (['INFORMATION_SCHEMA.COLUMNS', 'member_group_keys_json', 'DO 0', 'INFORMATION_SCHEMA.STATISTICS', 'idx_sr_survey_forms_qa'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.003.sql',
        $needle,
        'Survey 2026.06.003 update must be safe to retry after partial schema drift'
    );
}
foreach (['$skinKey = sr_survey_clean_key', '$settings[\'skin_key\'] = $skinKey'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers.php',
        $needle,
        'Survey settings POST validation must preserve submitted skin key until validation'
    );
}
foreach (['sr_survey_display_settings_for_survey', 'sr_survey_optional_option_key_from_post', 'survey-skin-\' . $skinKey'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers.php',
        $needle,
        'Survey helpers must validate and apply global and individual skin settings'
    );
}
foreach (['$surveyMemberGroupsForAdmin', 'sr_admin_member_group_key_badge_select_html', "'survey_member_group_keys'", "'member_group_keys'"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-surveys.php',
        $needle,
        'Survey admin form must use the shared member group select badge picker'
    );
}
sr_survey_check_not_contains(
    'modules/survey/actions/admin-surveys.php',
    'name="member_group_keys[]" value="<?php echo sr_e($groupKey); ?>" class="form-checkbox"',
    'Survey admin form must not render every member group as a checkbox list'
);
sr_survey_check_contains(
    'modules/survey/privacy-cleanup.php',
    "dedupe_key = CONCAT(\\'anonymized:survey_reward:\\', id)",
    'Survey reward dedupe keys must be anonymized during privacy cleanup'
);
sr_survey_check_contains(
    'modules/quiz/privacy-cleanup.php',
    "dedupe_key = CONCAT(\\'anonymized:quiz_reward:\\', id)",
    'Quiz reward dedupe keys must be anonymized during privacy cleanup'
);

foreach (['answer_snapshot_json', 'consent_snapshot_json', 'metadata_snapshot_json', 'sr_survey_response_answers', "'answers' =>"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/privacy-export.php',
        $needle,
        'Survey privacy export must include response snapshots and answer rows'
    );
}
foreach (['sr_survey_comments', 'survey.comments', 'body_text'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/privacy-export.php',
        $needle,
        'Survey privacy export must include authored comments'
    );
}
foreach (['UPDATE sr_survey_comments', 'author_account_id = NULL', 'survey_comment_anonymized_count'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/privacy-cleanup.php',
        $needle,
        'Survey privacy cleanup must anonymize authored comments'
    );
}

foreach (['SELECT r.id AS response_id, a.question_key, a.choice_key', '$choiceResponseStats[$questionKey][$choiceKey][$responseId] = true'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers.php',
        $needle,
        'Survey choice statistics must use stable question/choice keys and count each response once per choice'
    );
}
foreach (['GROUP BY a.question_id', 'choiceStats[(int)', 'choice_id IS NOT NULL'] as $needle) {
    sr_survey_check_not_contains(
        'modules/survey/actions/admin-statistics.php',
        $needle,
        'Survey choice statistics must not depend on regenerated numeric IDs'
    );
}
foreach (['sr_survey_statistics_summary', 'sr_survey_statistics_choice_counts', 'sr_survey_statistics_number_stats'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-statistics.php',
        $needle,
        'Survey admin statistics action must use runtime-tested statistics helpers'
    );
}

foreach ([
    'foreach ($choices as $choice)',
    'sr_survey_other_answers_from_post',
    "'other_text' => (int) (\$choice['is_other'] ?? 0) === 1",
    '기타 답변을 입력해 주세요.',
    '$lockClause = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === \'sqlite\' ? \'\' : \' FOR UPDATE\';',
    'sr_survey_current_user_agent_hash',
    'sr_survey_current_ip_hash',
    'account_id IS NULL',
    'user_agent_hash',
    'ip_hash',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers/responses.php',
        $needle,
        'Survey response helpers must preserve multi-choice answers and anonymous duplicate checks'
    );
}

foreach ([
    'sr_survey_submit_response($pdo',
    'Anonymous duplicate responses must be blocked by user agent and IP hash.',
    'Selected other choice must require other text.',
    'Multiple choice answer must enforce max choices.',
    'Number answer must enforce maximum value.',
] as $needle) {
    sr_survey_check_contains(
        '.tools/bin/check-survey-response-runtime.php',
        $needle,
        'Survey response runtime fixture must cover submit, duplicate, other, multiple choice, and number validation'
    );
}

foreach (['member-assets.php', 'notification-events.php'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/module.php',
        $needle,
        'Survey module metadata must declare consumed contracts'
    );
}

foreach (['other_answers[', 'sr-survey-other-input'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/skins/basic/view.php',
        $needle,
        'Survey public form must collect text for selected other choices'
    );
}
foreach (['survey-comments', 'sr_survey_comments', 'sr_survey_comment_body_html', 'data-sr-mention-input'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/skins/basic/view.php',
        $needle,
        'Survey public page must render comment mentions and mention input'
    );
}
foreach (['$surveyCommentsEnabled && ($submittedScreen || $submitResult !== null)', 'sr_survey_account_has_submitted_response', '?submitted=1#survey-comments'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/skins/basic/view.php',
        $needle,
        'Survey comments must stay on the completion screen'
    );
}
foreach (['sr_reaction_render_widget($pdo, \'survey\', \'survey_form\'', '$submittedScreen || $submitResult !== null'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/skins/basic/view.php',
        $needle,
        'Survey reactions must stay on the completion screen'
    );
}
foreach (['sr_survey_account_has_submitted_response', "'?submitted=1'", 'sr_survey_comment_page_for_comment'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/comment.php',
        $needle,
        'Survey comment creation must require a submitted response and return to completion comments'
    );
}
foreach (['sr_survey_account_has_submitted_response', '?submitted=1', 'can_write'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/reaction-targets.php',
        $needle,
        'Survey reaction target writes must require a submitted response'
    );
}

foreach ([
    'sr_survey_admin_question_signature',
    '설문지 잠금 상태에서는 문항을 수정할 수 없습니다.',
    '수정할 설문을 찾을 수 없습니다.',
    'skin_key = :skin_key',
    'comments_enabled',
    'sr_survey_key_is_reserved',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/helpers/admin-surveys.php',
        $needle,
        'Survey admin save/delete validation must remain enforced'
    );
}
sr_survey_check_contains(
    'modules/survey/actions/admin-surveys.php',
    'name="skin_key"',
    'Survey admin form must keep individual skin key input'
);
foreach (['POST /survey/comment', 'POST /survey/comment/edit', 'POST /survey/comment/delete', 'GET /admin/surveys/comments'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/paths.php',
        $needle,
        'Survey comment paths must be registered before wildcard public path'
    );
}
foreach (['/admin/surveys/comments', '댓글 관리'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/admin-menu.php',
        $needle,
        'Survey admin comments menu must be registered'
    );
}
foreach (['sr_survey_create_comment', 'sr_survey_create_comment_mention_notifications', 'sr_survey_public_window_is_open'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/comment.php',
        $needle,
        'Survey comment create action must validate and notify mentions'
    );
}
foreach (['sr_survey_update_comment_content', 'sr_survey_create_comment_mention_notifications', 'sr_survey_account_can_edit_comment'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/comment-edit.php',
        $needle,
        'Survey comment edit action must validate ownership and mention changes'
    );
}
foreach (['sr_survey_update_comment_status', 'sr_survey_account_can_delete_comment'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/comment-delete.php',
        $needle,
        'Survey comment delete action must validate ownership or manager permission'
    );
}
foreach (['/admin/surveys/comments', 'sr_survey_admin_comments', 'sr_survey_update_comment_status', 'sr_member_mention_plain_text_html'] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-comments.php',
        $needle,
        'Survey admin comments action must list and moderate comments'
    );
}

sr_survey_check_not_contains(
    'modules/survey/skins/basic/complete.php',
    "sr_get_string('reward'",
    'Survey completion page must not trust reward status from query string'
);
sr_survey_check_contains(
    'modules/survey/skins/basic/complete.php',
    'is_array($submitResult ?? null)',
    'Survey completion page must tolerate redirected completion without a submit result'
);

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . PHP_EOL);
    }
    exit(1);
}

echo 'survey consistency checks completed.' . PHP_EOL;
