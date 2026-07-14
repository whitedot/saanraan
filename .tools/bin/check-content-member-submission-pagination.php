#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$source = static function (string $file) use ($root, &$errors): string {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read content submission source: ' . $file;
        return '';
    }

    return $contents;
};
$assertContains = static function (string $file, array $markers) use ($source, &$errors): void {
    $contents = $source($file);
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing member submission pagination marker: ' . $marker;
        }
    }
};

$assertContains('modules/content/helpers/member-submissions.php', [
    'function sr_content_member_submission_count(',
    'int $limit = 20, int $offset = 0',
    'LIMIT :limit_value OFFSET :offset_value',
]);
$assertContains('modules/content/actions/account-content.php', [
    "sr_get_string('page'",
    'sr_content_member_submission_count(',
    '$contentSubmissionPagination',
    '$contentSubmissionFormPath',
    '$contentSubmissionPageQuery',
]);
$assertContains('modules/content/views/account-content.php', [
    'id="content-submission-history"',
    'sr_url($contentSubmissionFormPath)',
    'sr_public_pagination_html($contentSubmissionPagination',
    "'&page=' . (string) \$contentSubmissionPage",
]);
require_once $root . '/modules/content/helpers/member-submissions.php';
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE sr_content_groups (id INTEGER PRIMARY KEY, title TEXT)');
$pdo->exec("INSERT INTO sr_content_groups VALUES (1, 'Group')");
$pdo->exec(
    'CREATE TABLE sr_content_submissions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_account_id INTEGER NOT NULL,
        content_group_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        summary TEXT NOT NULL,
        body_text TEXT NOT NULL,
        review_status TEXT NOT NULL
    )'
);
$insert = $pdo->prepare(
    "INSERT INTO sr_content_submissions
     (author_account_id, content_group_id, title, summary, body_text, review_status)
     VALUES (1, 1, :title, '', '', 'member_draft')"
);
for ($rowNumber = 1; $rowNumber <= 45; $rowNumber++) {
    $insert->execute(['title' => 'Submission ' . (string) $rowNumber]);
}

if (sr_content_member_submission_count($pdo, 1) !== 45) {
    $errors[] = 'member submission count must include every account row';
}
$secondPage = sr_content_member_submissions($pdo, 1, 20, 20);
if (count($secondPage) !== 20 || (int) ($secondPage[0]['id'] ?? 0) !== 25 || (int) ($secondPage[19]['id'] ?? 0) !== 6) {
    $errors[] = 'member submission pagination must return the requested ordered slice';
}
$finalPage = sr_content_member_submissions($pdo, 1, 20, 40);
if (count($finalPage) !== 5 || (int) ($finalPage[0]['id'] ?? 0) !== 5 || (int) ($finalPage[4]['id'] ?? 0) !== 1) {
    $errors[] = 'member submission pagination must expose the final partial page';
}

if ($errors !== []) {
    fwrite(STDERR, "content member submission pagination checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "content member submission pagination checks completed.\n";
