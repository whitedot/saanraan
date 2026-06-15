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

sr_survey_check_contains(
    'modules/survey/install.sql',
    'CREATE TABLE IF NOT EXISTS sr_survey_reward_grants',
    'Survey reward grants table must exist'
);
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
    "'version' => '2026.06.012'",
    'Survey module version must include cover image update'
);
foreach (['ALTER TABLE sr_survey_forms', 'ADD COLUMN skin_key VARCHAR(40) NOT NULL DEFAULT \'\''] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.009.sql',
        $needle,
        'Survey display override update must add individual skin column'
    );
}
foreach (['ALTER TABLE {{SR_TABLE_PREFIX}}survey_forms DROP COLUMN theme_key', "s.setting_key = 'theme_key'", "version = '2026.06.010'"] as $needle) {
    sr_survey_check_contains(
        'modules/survey/updates/2026.06.010.sql',
        $needle,
        'Survey display override cleanup update must remove legacy theme key'
    );
}
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
sr_survey_check_contains(
    'modules/survey/helpers.php',
    '$site = is_array($GLOBALS[\'sr_runtime_site\'] ?? null) ? $GLOBALS[\'sr_runtime_site\'] : null;',
    'Survey skin renderer must pass the runtime site context to public layout views'
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
        'modules/survey/helpers.php',
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
foreach (['survey-comments', 'sr_survey_comments', 'sr_member_mention_plain_text_html', 'data-sr-mention-input'] as $needle) {
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
foreach (['sr_survey_account_has_submitted_response', '?submitted=1#survey-comments'] as $needle) {
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
    'name="skin_key"',
    'skin_key = :skin_key',
    'comments_enabled',
    'sr_survey_key_is_reserved',
] as $needle) {
    sr_survey_check_contains(
        'modules/survey/actions/admin-surveys.php',
        $needle,
        'Survey admin save/delete validation must remain enforced'
    );
}
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
