#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read content admin workflow source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing content admin workflow pagination marker: ' . $marker;
        }
    }
};

$assertContains('modules/content/helpers/member-submissions.php', [
    'function sr_content_author_application_count(',
    'function sr_content_author_permission_count(',
    'function sr_content_admin_submission_count(',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/content/actions/admin-content-author-applications.php', [
    'sr_admin_pagination_from_total(',
    'sr_content_author_application_count(',
    'sr_admin_pagination_offset($contentAuthorApplicationPagination)',
]);
$assertContains('modules/content/actions/admin-content-authors.php', [
    'sr_admin_pagination_from_total(',
    'sr_content_author_permission_count(',
    'sr_admin_pagination_offset($contentAuthorPagination)',
]);
$assertContains('modules/content/actions/admin-content-submissions.php', [
    'sr_admin_pagination_from_total(',
    'sr_content_admin_submission_count(',
    'sr_admin_pagination_offset($contentSubmissionPagination)',
]);
$assertContains('modules/content/views/admin-content-author-applications.php', [
    'sr_admin_pagination_summary_html($contentAuthorApplicationPagination)',
    'sr_admin_pagination_html($contentAuthorApplicationPagination',
    "sr_admin_current_get_url('/admin/content/author-applications')",
]);
$assertContains('modules/content/views/admin-content-authors.php', [
    'sr_admin_pagination_summary_html($contentAuthorPagination)',
    'sr_admin_pagination_html($contentAuthorPagination',
    "sr_admin_current_get_url('/admin/content/authors')",
    '차단해도 회원 그룹을 통해 받은 제출 권한은 유지될 수 있습니다.',
    '항상 검수와 검수 면제는 사이트·콘텐츠 그룹 설정보다 우선합니다.',
    '이미 처리된 제출본의 상태는 바꾸지 않습니다.',
]);
$assertContains('modules/content/views/admin-content-submissions.php', [
    'sr_admin_pagination_summary_html($contentSubmissionPagination)',
    'sr_admin_pagination_html($contentSubmissionPagination',
    "sr_admin_current_get_url('/admin/content/submissions')",
]);

require_once $root . '/modules/content/helpers/member-submissions.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec(
    'CREATE TABLE sr_member_accounts (
        id INTEGER PRIMARY KEY,
        email TEXT NOT NULL,
        display_name TEXT NOT NULL,
        status TEXT NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE sr_content_author_applications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        application_note TEXT NOT NULL,
        review_note TEXT NOT NULL,
        created_at TEXT NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE sr_content_author_permissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        account_id INTEGER NOT NULL,
        status TEXT NOT NULL,
        review_required_override TEXT NOT NULL,
        note TEXT NOT NULL,
        updated_at TEXT NOT NULL
    )'
);
$pdo->exec('CREATE TABLE sr_content_groups (id INTEGER PRIMARY KEY, title TEXT NOT NULL)');
$pdo->exec("INSERT INTO sr_content_groups VALUES (1, 'Group')");
$pdo->exec(
    'CREATE TABLE sr_content_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_account_id INTEGER NOT NULL,
        content_group_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        body_text TEXT NOT NULL,
        review_status TEXT NOT NULL
    )'
);

$insertAccount = $pdo->prepare(
    "INSERT INTO sr_member_accounts (id, email, display_name, status)
     VALUES (:id, :email, :display_name, 'active')"
);
$insertApplication = $pdo->prepare(
    "INSERT INTO sr_content_author_applications
     (account_id, status, application_note, review_note, created_at)
     VALUES (:account_id, :status, '', '', '2026-07-14 00:00:00')"
);
$insertPermission = $pdo->prepare(
    "INSERT INTO sr_content_author_permissions
     (account_id, status, review_required_override, note, updated_at)
     VALUES (:account_id, :status, :review_override, '', '2026-07-14 00:00:00')"
);
$insertSubmission = $pdo->prepare(
    "INSERT INTO sr_content_submissions
     (author_account_id, content_group_id, title, body_text, review_status)
     VALUES (:account_id, 1, :title, '', :status)"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insertAccount->execute([
        'id' => $rowNumber,
        'email' => 'member' . (string) $rowNumber . '@example.test',
        'display_name' => 'Member ' . (string) $rowNumber,
    ]);
    $insertApplication->execute([
        'account_id' => $rowNumber,
        'status' => $rowNumber % 2 === 0 ? 'approved' : 'pending',
    ]);
    $insertPermission->execute([
        'account_id' => $rowNumber,
        'status' => $rowNumber % 2 === 0 ? 'blocked' : 'allowed',
        'review_override' => $rowNumber % 3 === 0 ? 'exempt' : 'inherit',
    ]);
    $insertSubmission->execute([
        'account_id' => $rowNumber,
        'title' => 'Submission ' . (string) $rowNumber,
        'status' => $rowNumber % 2 === 0 ? 'approved' : 'pending_review',
    ]);
}

$assertSlice = static function (array $rows, string $label) use (&$errors): void {
    if (count($rows) !== 5 || (int) ($rows[0]['id'] ?? 0) !== 5 || (int) ($rows[4]['id'] ?? 0) !== 1) {
        $errors[] = $label . ' must expose the final ordered partial page';
    }
};

if (sr_content_author_application_count($pdo, []) !== 45) {
    $errors[] = 'author application count must include every row';
}
$assertSlice(sr_content_author_applications($pdo, [], 0, 20, 40), 'author applications');
if (sr_content_author_application_count($pdo, ['pending']) !== 23) {
    $errors[] = 'author application count must apply the status filter';
}
if (sr_content_author_application_count($pdo, [], 41) !== 1) {
    $errors[] = 'author application count must apply the application ID filter';
}

if (sr_content_author_permission_count($pdo) !== 45) {
    $errors[] = 'author permission count must include every row';
}
$assertSlice(sr_content_author_permissions($pdo, [], [], 20, 40), 'author permissions');
if (sr_content_author_permission_count($pdo, ['allowed'], ['exempt']) !== 8) {
    $errors[] = 'author permission count must apply status and review filters together';
}

if (sr_content_admin_submission_count($pdo, []) !== 45) {
    $errors[] = 'admin submission count must include every row';
}
$assertSlice(sr_content_admin_submissions($pdo, [], 20, 40), 'admin submissions');
if (sr_content_admin_submission_count($pdo, ['pending_review']) !== 23) {
    $errors[] = 'admin submission count must apply the review status filter';
}

if ($errors !== []) {
    fwrite(STDERR, "content admin workflow pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "content admin workflow pagination checks completed.\n";
