<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizListPerPage = max(1, min(100, (int) ($quizSettings['public_list_limit'] ?? 50)));
$quizListPageInput = sr_get_string('page', 20);
$quizListPage = preg_match('/\A[1-9][0-9]*\z/', $quizListPageInput) === 1 ? (int) $quizListPageInput : 1;
$quizListCount = sr_quiz_public_quiz_count($pdo);
$quizListTotalPages = max(1, (int) ceil($quizListCount / $quizListPerPage));
$quizListPage = min(max(1, $quizListPage), $quizListTotalPages);
$quizListPagination = ['page' => $quizListPage, 'total_pages' => $quizListTotalPages];
$quizzes = sr_quiz_public_quizzes($pdo, $quizListPerPage, ($quizListPage - 1) * $quizListPerPage);
$quizThemeFallbackViewFile = sr_quiz_skin_view_file($quizSettings, 'home');
include sr_quiz_public_view_file($pdo, $quizSettings, 'home');
