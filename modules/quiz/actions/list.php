<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizListGroupKey = sr_get_string('group', 64);
$quizListGroup = $quizListGroupKey !== '' ? sr_quiz_group_by_key($pdo, $quizListGroupKey, true) : null;
if ($quizListGroupKey !== '' && !is_array($quizListGroup)) {
    sr_render_error(404, '퀴즈 그룹을 찾을 수 없습니다.');
}
$quizListGroupId = is_array($quizListGroup) ? (int) ($quizListGroup['id'] ?? 0) : 0;
$quizListPerPage = max(1, min(100, (int) ($quizSettings['public_list_limit'] ?? 50)));
$quizListPageInput = sr_get_string('page', 20);
$quizListPage = preg_match('/\A[1-9][0-9]*\z/', $quizListPageInput) === 1 ? (int) $quizListPageInput : 1;
$quizListCount = sr_quiz_public_quiz_count($pdo, $quizListGroupId);
$quizListTotalPages = max(1, (int) ceil($quizListCount / $quizListPerPage));
$quizListPage = min(max(1, $quizListPage), $quizListTotalPages);
$quizListPagination = ['page' => $quizListPage, 'total_pages' => $quizListTotalPages];
$quizzes = sr_quiz_public_quizzes($pdo, $quizListPerPage, ($quizListPage - 1) * $quizListPerPage, $quizListGroupId);
$quizThemeFallbackViewFile = sr_quiz_skin_view_file($quizSettings, 'list');
include sr_quiz_public_view_file($pdo, $quizSettings, 'list');
