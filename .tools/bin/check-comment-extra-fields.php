#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

if (!function_exists('sr_e')) {
    function sr_e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('sr_now')) {
    function sr_now(): string
    {
        return '2026-07-15 12:00:00';
    }
}
if (!function_exists('sr_material_icon_html')) {
    function sr_material_icon_html(string $icon): string
    {
        return '<span>' . sr_e($icon) . '</span>';
    }
}
if (!function_exists('sr_admin_choice_label_html')) {
    function sr_admin_choice_label_html(string $label): string
    {
        return sr_e($label);
    }
}

require_once $root . '/core/helpers/comment-extra-fields.php';
require_once $root . '/modules/admin/helpers/comment-extra-fields.php';

$errors = [];

function sr_check_comment_extra_fields_assert(bool $condition, string $message): void
{
    global $errors;
    if (!$condition) {
        $errors[] = $message;
    }
}

$definitions = [
    [
        'key' => 'field_name',
        'label' => '참여자 이름',
        'type' => 'text',
        'required' => true,
        'options' => [],
        'privacy_purpose' => '댓글 참여자 확인',
        'show_privacy_purpose' => true,
        'export_policy' => 'include',
        'cleanup_policy' => 'anonymize',
    ],
    [
        'key' => 'field_group',
        'label' => '소속',
        'type' => 'select',
        'required' => false,
        'options' => ['기획', '개발'],
        'privacy_purpose' => '내부 분류',
        'show_privacy_purpose' => false,
        'export_policy' => 'exclude',
        'cleanup_policy' => 'retain',
    ],
    [
        'key' => 'field_agree',
        'label' => '확인',
        'type' => 'checkbox',
        'required' => true,
        'options' => [],
        'privacy_purpose' => '',
        'export_policy' => 'include',
        'cleanup_policy' => 'retain',
    ],
];

sr_check_comment_extra_fields_assert(sr_comment_extra_field_definition_errors($definitions) === [], 'valid definitions must pass validation.');
sr_check_comment_extra_fields_assert(sr_comment_extra_field_definition_errors([['key' => 'bad-key', 'label' => '', 'type' => 'select', 'options' => []]]) !== [], 'invalid key, label, and select options must fail validation.');
sr_check_comment_extra_fields_assert(sr_comment_extra_field_definition_errors([['key' => 'field_test', 'label' => '테스트', 'type' => 'text', 'show_privacy_purpose' => 'invalid']]) !== [], 'invalid collection-purpose visibility must fail validation.');

$_POST['comment_extra_fields'] = ['field_name' => '', 'field_group' => '운영', 'field_agree' => ''];
$invalidInput = sr_comment_extra_field_values_from_post($definitions);
sr_check_comment_extra_fields_assert(count($invalidInput['errors'] ?? []) === 3, 'required text, invalid select, and required checkbox must all fail server validation.');

$_POST['comment_extra_fields'] = ['field_name' => '홍길동', 'field_group' => '개발', 'field_agree' => '1'];
$validInput = sr_comment_extra_field_values_from_post($definitions);
sr_check_comment_extra_fields_assert(($validInput['errors'] ?? ['error']) === [], 'valid posted values must pass validation.');
sr_check_comment_extra_fields_assert(($validInput['values']['field_group'] ?? '') === '개발', 'valid select value must be preserved.');

$snapshotJson = sr_comment_extra_field_snapshot_json($definitions, (array) ($validInput['values'] ?? []));
$snapshot = json_decode($snapshotJson, true);
sr_check_comment_extra_fields_assert(is_array($snapshot) && count($snapshot) === 3, 'snapshot must preserve all field definitions.');
sr_check_comment_extra_fields_assert(($snapshot[0]['privacy_purpose'] ?? '') === '댓글 참여자 확인', 'snapshot must preserve collection purpose.');
sr_check_comment_extra_fields_assert(($snapshot[1]['show_privacy_purpose'] ?? true) === false, 'snapshot must preserve the collection-purpose visibility choice.');

$formHtml = sr_comment_extra_fields_form_html($definitions, (array) ($validInput['values'] ?? []));
sr_check_comment_extra_fields_assert(str_contains($formHtml, '수집·이용 목적: 댓글 참여자 확인'), 'public form must show collection purpose below its field.');
sr_check_comment_extra_fields_assert(!str_contains($formHtml, '수집·이용 목적: 내부 분류'), 'public form must hide a collection purpose when its visibility choice is off.');
sr_check_comment_extra_fields_assert(str_contains($formHtml, 'name="comment_extra_fields[field_name]"'), 'public form must use the comment extra field POST namespace.');
sr_check_comment_extra_fields_assert(str_contains($formHtml, 'required'), 'public form must expose browser required validation.');

$displayHtml = sr_comment_extra_fields_display_html($snapshotJson);
sr_check_comment_extra_fields_assert(str_contains($displayHtml, '홍길동') && str_contains($displayHtml, '개발'), 'saved values must be displayable at the comment location.');

$exported = json_decode(sr_comment_extra_field_export_json($snapshotJson), true);
sr_check_comment_extra_fields_assert(is_array($exported) && count($exported) === 2, 'privacy export must omit fields configured for exclusion.');
sr_check_comment_extra_fields_assert(!in_array('field_group', array_column($exported, 'key'), true), 'excluded field must not appear in privacy export.');

$cleaned = json_decode(sr_comment_extra_field_cleanup_json($snapshotJson), true);
sr_check_comment_extra_fields_assert(($cleaned[0]['value'] ?? null) === '', 'anonymize field value must be cleared during account cleanup.');
sr_check_comment_extra_fields_assert(($cleaned[1]['value'] ?? '') === '개발' && ($cleaned[2]['value'] ?? '') === '1', 'retained field values must remain during account cleanup.');

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    $errors[] = 'SQLite PDO driver is required for the comment extra field cleanup fixture.';
} else {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE sr_content_comments (id INTEGER PRIMARY KEY, author_account_id INTEGER NULL, extra_values_json TEXT NULL, updated_at TEXT NOT NULL)');
    $stmt = $pdo->prepare('INSERT INTO sr_content_comments (id, author_account_id, extra_values_json, updated_at) VALUES (1, 7, :json, \'\')');
    $stmt->execute(['json' => $snapshotJson]);
    sr_check_comment_extra_fields_assert(sr_comment_extra_field_cleanup_account_snapshots($pdo, 'sr_content_comments', 7) === 1, 'account cleanup must update the target comment snapshot once.');
    $stored = (string) $pdo->query('SELECT extra_values_json FROM sr_content_comments WHERE id = 1')->fetchColumn();
    sr_check_comment_extra_fields_assert($stored === sr_comment_extra_field_cleanup_json($snapshotJson), 'account cleanup must persist the policy-filtered snapshot.');
}

foreach (['community', 'content', 'quiz', 'survey'] as $moduleKey) {
    $action = file_get_contents($root . '/modules/' . $moduleKey . '/actions/comment.php');
    $commentsHelper = file_get_contents($root . '/modules/' . $moduleKey . '/helpers/' . ($moduleKey === 'community' ? 'posts-comments.php' : 'comments.php'));
    sr_check_comment_extra_fields_assert(is_string($action) && str_contains($action, "['extra_values_json'] = sr_comment_extra_field_snapshot_json"), $moduleKey . ' comment action must snapshot extra values before create.');
    sr_check_comment_extra_fields_assert(is_string($commentsHelper) && str_contains($commentsHelper, ':extra_values_json'), $moduleKey . ' comment INSERT must save the snapshot atomically.');
}

$communityBoardHelpers = file_get_contents($root . '/modules/community/helpers/boards.php');
sr_check_comment_extra_fields_assert(is_string($communityBoardHelpers) && str_contains($communityBoardHelpers, "\$defaults['comment_extra_fields_json'] = sr_comment_extra_field_definitions_json(\$settings['comment_extra_fields_json'] ?? '[]');"), 'community settings must initialize only the new-board form defaults.');
sr_check_comment_extra_fields_assert(is_string($communityBoardHelpers) && str_contains($communityBoardHelpers, "'comment_extra_fields_json' => '[]'"), 'community group/runtime fallback must remain empty instead of inheriting the new-board form default.');

$contentDefaults = file_get_contents($root . '/modules/content/helpers.php');
$quizAdmin = file_get_contents($root . '/modules/quiz/helpers/admin.php');
$surveyAdminView = file_get_contents($root . '/modules/survey/actions/admin-surveys.php');
sr_check_comment_extra_fields_assert(is_string($contentDefaults) && str_contains($contentDefaults, "sr_content_settings(\$pdo)['comment_extra_fields_json']"), 'content settings must initialize the new-content form.');
sr_check_comment_extra_fields_assert(is_string($quizAdmin) && str_contains($quizAdmin, "'comment_extra_fields_json' => sr_comment_extra_field_definitions_json(\$settings['comment_extra_fields_json'] ?? '[]')"), 'quiz settings must initialize the new-quiz form.');
sr_check_comment_extra_fields_assert(is_string($surveyAdminView) && str_contains($surveyAdminView, '...sr_survey_settings($pdo)'), 'survey settings must initialize the new-survey form.');

foreach (['content' => 'page', 'quiz' => 'quiz', 'survey' => 'survey'] as $moduleKey => $entityVariable) {
    $action = file_get_contents($root . '/modules/' . $moduleKey . '/actions/comment.php');
    $expected = "sr_comment_extra_field_definitions(\$" . $entityVariable . "['comment_extra_fields_json'] ?? '[]')";
    sr_check_comment_extra_fields_assert(is_string($action) && str_contains($action, $expected), $moduleKey . ' comment runtime must use the saved entity definition.');
    sr_check_comment_extra_fields_assert(is_string($action) && !str_contains($action, "Settings['comment_extra_fields_json']"), $moduleKey . ' comment runtime must not use the module default directly.');

    $installSql = file_get_contents($root . '/modules/' . $moduleKey . '/install.sql');
    sr_check_comment_extra_fields_assert(is_string($installSql) && str_contains($installSql, 'comment_extra_fields_json LONGTEXT NULL'), $moduleKey . ' entity schema must store its independent definition.');
}

foreach (['content' => '2026.07.005', 'quiz' => '2026.07.004', 'survey' => '2026.07.004'] as $moduleKey => $version) {
    $updateSql = file_get_contents($root . '/modules/' . $moduleKey . '/updates/' . $version . '.sql');
    sr_check_comment_extra_fields_assert(is_string($updateSql) && str_contains($updateSql, 'ADD COLUMN comment_extra_fields_json LONGTEXT NULL'), $moduleKey . ' update must add the entity definition column.');
    sr_check_comment_extra_fields_assert(is_string($updateSql) && !preg_match('/UPDATE\s+\{\{SR_TABLE_PREFIX\}\}(?:content_items|quiz_sets|survey_forms)\s+SET\s+comment_extra_fields_json/i', $updateSql), $moduleKey . ' update must not backfill existing entities from the module default.');
}

$editorScript = file_get_contents($root . '/modules/admin/assets/comment-extra-fields.js');
$editorView = file_get_contents($root . '/modules/admin/helpers/comment-extra-fields.php');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, 'show_privacy_purpose'), 'admin editor script must save collection-purpose visibility.');
sr_check_comment_extra_fields_assert(is_string($editorView) && str_contains($editorView, '입력 항목 아래에 표시'), 'admin editor must expose collection-purpose visibility in plain language.');

$editorHtml = sr_admin_comment_extra_fields_editor_html('content_item_comment_extra_fields_json', 'comment_extra_fields_json', [], '댓글 추가 입력 항목', '새 콘텐츠에 적용할 기본값입니다.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'id="content-item-comment-extra-fields-json-section"'), 'admin editor section IDs must use the same hyphenated form as section navigation links.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'id="content-item-comment-extra-fields-json-modal"'), 'admin editor modal IDs must follow the normalized section ID.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0"'), 'admin editor modals must use the shared hidden overlay state.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'aria-hidden="true" inert data-overlay-stack="true"'), 'admin editor modals must expose the shared overlay accessibility contract.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'admin-extra-field-editor admin-comment-extra-fields'), 'admin editor must use the shared extra field card layout.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, 'data-admin-comment-extra-field-table'), 'admin editor must expose the same table contract as the board form editor.');
sr_check_comment_extra_fields_assert(str_contains($editorHtml, '<p class="form-help">'), 'admin editor guidance must follow the board form table.');
sr_check_comment_extra_fields_assert(!str_contains($editorHtml, 'maxlength="120" required class="form-input'), 'closed editor modal inputs must not participate in the parent settings form native validation.');
sr_check_comment_extra_fields_assert(!str_contains($editorHtml, '-field-type" required class="form-select'), 'closed editor modal selects must not participate in the parent settings form native validation.');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, 'function reportTemporaryValidity(control, message)'), 'admin editor must scope required validation to the modal apply action.');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, "control.setCustomValidity('');"), 'admin editor must clear modal custom validity after reporting it.');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, "editButton.setAttribute('data-overlay', modalSelector)"), 'admin editor edit actions must open their shared modal.');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, "editButton.innerHTML = '<span class=\"material-symbols-outlined\" aria-hidden=\"true\">edit</span>';"), 'admin editor edit actions must use the board form icon pattern.');
sr_check_comment_extra_fields_assert(is_string($editorScript) && str_contains($editorScript, "removeButton.innerHTML = '<span class=\"material-symbols-outlined\" aria-hidden=\"true\">delete</span>';"), 'admin editor remove actions must use the board form icon pattern.');

unset($_POST['comment_extra_fields']);

if ($errors !== []) {
    fwrite(STDERR, "comment extra field checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "comment extra field checks completed.\n";
