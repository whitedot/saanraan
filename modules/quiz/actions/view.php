<?php

require_once __DIR__ . '/../helpers.php';

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$basePath = rtrim(sr_base_path(), '/');
$quizPath = (string) $path;
if ($basePath !== '' && str_starts_with($quizPath, $basePath . '/')) {
    $quizPath = substr($quizPath, strlen($basePath));
}
$quizKey = trim(substr($quizPath, strlen('/quiz')), '/');
$quizKey = sr_quiz_clean_key(rawurldecode($quizKey));
$quiz = sr_quiz_by_key($pdo, $quizKey);

if (!is_array($quiz) || (string) ($quiz['status'] ?? '') !== 'active') {
    sr_render_error(404, '퀴즈를 찾을 수 없습니다.');
}
$seo = [
    'title' => (string) $quiz['title'],
    'canonical' => '/quiz/' . (string) $quiz['quiz_key'],
    'og' => [
        'title' => (string) $quiz['title'],
        'type' => 'article',
    ],
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'body_class' => 'sr-quiz-page',
]);
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1><?php echo sr_e((string) $quiz['title']); ?></h1>
            <?php if ((string) ($quiz['description'] ?? '') !== ''): ?>
                <p><?php echo sr_e((string) $quiz['description']); ?></p>
            <?php endif; ?>
            <p>퀴즈 응시 화면은 다음 구현 단계에서 연결됩니다.</p>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
