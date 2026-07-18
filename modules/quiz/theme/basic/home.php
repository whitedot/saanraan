<?php

require_once SR_ROOT . '/modules/quiz/helpers.php';

$quizSettings = sr_quiz_settings($pdo);
$quizzes = isset($quizzes) && is_array($quizzes) ? $quizzes : sr_quiz_public_quizzes($pdo);
$quizListPagination = isset($quizListPagination) && is_array($quizListPagination) ? $quizListPagination : ['page' => 1, 'total_pages' => 1];
$quizScreenTarget = isset($quizScreenTarget) && $quizScreenTarget === 'quiz.list' ? 'quiz.list' : 'quiz.home';
$quizScreenIsList = $quizScreenTarget === 'quiz.list';
$quizListGroup = isset($quizListGroup) && is_array($quizListGroup) ? $quizListGroup : null;
$quizListGroupKey = is_array($quizListGroup) ? (string) ($quizListGroup['group_key'] ?? '') : '';
$quizScreenTitle = is_array($quizListGroup) ? (string) ($quizListGroup['title'] ?? '전체 퀴즈·테스트') : ($quizScreenIsList ? '전체 퀴즈·테스트' : '퀴즈·테스트');
$quizListUrl = '/quiz/list' . ($quizListGroupKey !== '' ? '?group=' . rawurlencode($quizListGroupKey) : '');
$quizPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$seo = [
    'title' => $quizScreenTitle,
    'canonical' => $quizScreenIsList ? $quizListUrl : '/quiz',
    'og' => [
        'title' => '퀴즈·테스트',
        'type' => 'website',
    ],
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_quiz_public_layout_context($quizSettings, [
    'consumer_target' => $quizScreenTarget,
    'body_class' => 'sr-quiz-page',
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'quiz', 'point_key' => $quizScreenTarget, 'slot_key' => 'screen'],
        ['module_key' => 'quiz', 'point_key' => 'quiz.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
]));
?>
<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'quiz',
    'point_key' => $quizScreenTarget,
    'slot_key' => 'screen',
]); ?>

<main class="quiz-page-main">
    <section class="quiz-page-section sr-quiz-home">
        <div class="quiz-page-container">
            <header class="sr-quiz-home-header">
                <h1><?php echo sr_e($quizScreenTitle); ?></h1>
                <?php if (!$quizScreenIsList): ?>
                    <p>새로운 퀴즈와 테스트를 만나보세요.</p>
                <?php endif; ?>
            </header>
            <?php if ($quizScreenIsList): ?>
                <div class="quiz-screen-frame">
                    <div class="quiz-screen-main">
            <?php endif; ?>
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
            <?php if ($quizScreenIsList): ?>
                <?php echo sr_public_pagination_html($quizListPagination, $quizListUrl, '퀴즈 목록 페이지'); ?>
            <?php else: ?>
                <p><a class="btn btn-outline-primary" href="<?php echo sr_e(sr_url('/quiz/list')); ?>">전체 퀴즈 보기</a></p>
            <?php endif; ?>
            <?php if ($quizScreenIsList): ?>
                    </div>
                    <?php $quizSidebarSubject = ['group_key' => $quizListGroupKey]; ?>
                    <?php include SR_ROOT . '/modules/quiz/views/sidebar.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
