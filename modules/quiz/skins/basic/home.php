<?php

require_once __DIR__ . '/../../helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizzes = sr_quiz_public_quizzes($pdo);
$quizPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$seo = [
    'title' => '퀴즈·테스트',
    'canonical' => '/quiz',
    'og' => [
        'title' => '퀴즈·테스트',
        'type' => 'website',
    ],
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_quiz_public_layout_context($quizSettings, [
    'body_class' => 'sr-quiz-page',
    'stylesheets' => ['/modules/popup_layer/assets/module.css'],
]));
?>
<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'quiz',
    'point_key' => 'quiz.home',
    'slot_key' => 'screen',
]); ?>

<main class="quiz-page-main">
    <section class="quiz-page-section sr-quiz-home">
        <div class="quiz-page-container">
            <header class="sr-quiz-home-header">
                <h1>퀴즈·테스트</h1>
            </header>
            <?php if ($quizzes === []): ?>
                <p class="sr-quiz-home-empty">현재 공개된 퀴즈가 없습니다.</p>
            <?php else: ?>
                <div class="sr-quiz-card-grid">
                    <?php foreach ($quizzes as $quiz): ?>
                        <?php
                        $quizKey = (string) ($quiz['quiz_key'] ?? '');
                        $quizTitle = (string) ($quiz['title'] ?? $quizKey);
                        $quizDescription = (string) ($quiz['description'] ?? '');
                        $quizUrl = sr_url('/quiz/' . rawurlencode($quizKey));
                        $quizCoverHtml = sr_quiz_cover_image_html($quiz, 'sr-quiz-card-image card-img-top', $quizTitle);
                        ?>
                        <article class="card sr-quiz-card">
                            <a class="sr-quiz-card-media" href="<?php echo sr_e($quizUrl); ?>" aria-label="<?php echo sr_e($quizTitle); ?>">
                                <?php if ($quizCoverHtml !== ''): ?>
                                    <?php echo $quizCoverHtml; ?>
                                <?php else: ?>
                                    <span class="sr-quiz-card-placeholder card-img-top" aria-hidden="true"></span>
                                <?php endif; ?>
                            </a>
                            <div class="sr-quiz-card-copy card-body">
                                <p class="sr-quiz-card-meta">
                                    <span><?php echo sr_e($quizPublisherName); ?></span>
                                </p>
                                <h2>
                                    <a href="<?php echo sr_e($quizUrl); ?>">
                                        <span><?php echo sr_e($quizTitle); ?></span>
                                    </a>
                                </h2>
                                <?php if ($quizDescription !== ''): ?>
                                    <p class="sr-quiz-card-summary"><?php echo sr_e($quizDescription); ?></p>
                                <?php endif; ?>
                                <div class="sr-quiz-card-footer">
                                    <span><?php echo sr_e($quizPublisherName); ?></span>
                                    <?php if ((string) ($quiz['created_at'] ?? '') !== ''): ?>
                                        <?php echo sr_quiz_time_html((string) $quiz['created_at']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
