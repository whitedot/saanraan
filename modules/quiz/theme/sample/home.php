<?php

$quizSettings = isset($quizSettings) && is_array($quizSettings) ? $quizSettings : sr_quiz_settings($pdo);
$quizzes = isset($quizzes) && is_array($quizzes) ? $quizzes : sr_quiz_public_quizzes($pdo);
$quizListPagination = isset($quizListPagination) && is_array($quizListPagination) ? $quizListPagination : ['page' => 1, 'total_pages' => 1];
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
    'consumer_target' => 'quiz.home',
    'body_class' => 'example-quiz-body',
    'include_skin_assets' => false,
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'quiz', 'point_key' => 'quiz.home', 'slot_key' => 'screen'],
    ],
]));
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'quiz',
    'point_key' => 'quiz.home',
    'slot_key' => 'screen',
]); ?>

<main class="example-quiz-theme example-quiz-home" data-example-theme-view="quiz.home">
    <section class="example-quiz-hero" aria-labelledby="example_quiz_home_title">
        <p class="example-content-kicker">QUIZ MODULE VIEW THEME</p>
        <h1 id="example_quiz_home_title">Quiz Console</h1>
        <p>퀴즈 홈은 선택된 skin 파일이 아니라 <code>modules/quiz/theme/sample/home.php</code>에서 렌더링됩니다.</p>
    </section>

    <section class="example-quiz-grid" aria-label="퀴즈 목록">
        <?php if ($quizzes === []) { ?>
            <p class="example-quiz-panel">현재 공개된 퀴즈가 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($quizzes as $quiz) { ?>
                <?php
                $quizKey = (string) ($quiz['quiz_key'] ?? '');
                $quizTitle = (string) ($quiz['title'] ?? $quizKey);
                $quizUrl = sr_url('/quiz/' . rawurlencode($quizKey));
                ?>
                <article class="example-quiz-card">
                    <?php echo sr_quiz_cover_image_html($quiz, 'example-quiz-card-image', $quizTitle); ?>
                    <p class="example-content-kicker"><?php echo sr_e($quizPublisherName); ?></p>
                    <h2><a href="<?php echo sr_e($quizUrl); ?>"><?php echo sr_e($quizTitle); ?></a></h2>
                    <?php if ((string) ($quiz['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $quiz['description']); ?></p>
                    <?php } ?>
                    <?php echo sr_quiz_time_html((string) ($quiz['created_at'] ?? '')); ?>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
    <?php echo sr_public_pagination_html($quizListPagination, '/quiz', '퀴즈 목록 페이지'); ?>
</main>

<?php sr_public_layout_end(); ?>
