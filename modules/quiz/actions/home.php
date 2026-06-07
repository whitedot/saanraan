<?php

require_once __DIR__ . '/../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizzes = sr_quiz_public_quizzes($pdo);
$seo = [
    'title' => '퀴즈',
    'canonical' => '/quiz',
    'og' => [
        'title' => '퀴즈',
        'type' => 'website',
    ],
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_quiz_public_layout_context($quizSettings, [
    'body_class' => 'sr-quiz-page',
]));
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1>퀴즈</h1>
            <?php if ($quizzes === []): ?>
                <p>현재 공개된 퀴즈가 없습니다.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($quizzes as $quiz): ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url('/quiz/' . rawurlencode((string) $quiz['quiz_key']))); ?>">
                                <span class="sr-quiz-list-title"><?php echo sr_e((string) $quiz['title']); ?></span>
                                <?php if ((string) ($quiz['created_at'] ?? '') !== ''): ?>
                                    <span class="sr-quiz-list-date"><?php echo sr_quiz_time_html((string) $quiz['created_at']); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
