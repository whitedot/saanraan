<?php
$quizSidebarContext = isset($quizSidebarContext) && is_array($quizSidebarContext)
    ? $quizSidebarContext
    : sr_quiz_sidebar_context($pdo, $quizSettings ?? sr_quiz_settings($pdo), $quizSidebarSubject ?? []);
if (!empty($quizSidebarContext['enabled'])) {
    $quizSidebarMenu = is_array($quizSidebarContext['menu'] ?? null) ? $quizSidebarContext['menu'] : [];
    $quizSidebarPopular = is_array($quizSidebarContext['popular'] ?? null) ? $quizSidebarContext['popular'] : [];
    $quizSidebarComments = is_array($quizSidebarContext['comments'] ?? null) ? $quizSidebarContext['comments'] : [];
    ?>
    <aside class="quiz-sidebar" aria-label="퀴즈 사이드">
        <?php if ((string) ($quizSidebarMenu['html'] ?? '') !== '') { ?>
            <section class="card quiz-sidebar-section">
                <div class="card-header"><h2 class="card-title"><?php echo sr_e((string) ($quizSidebarMenu['title'] ?? '메뉴')); ?></h2></div>
                <div class="card-body"><?php echo (string) $quizSidebarMenu['html']; ?></div>
            </section>
        <?php } ?>
        <?php if ($quizSidebarPopular !== []) { ?>
            <section class="card quiz-sidebar-section quiz-sidebar-summary-section">
                <div class="card-header"><h2 class="card-title">인기 퀴즈</h2></div>
                <div class="card-body quiz-sidebar-summary-body"><ol class="quiz-sidebar-list quiz-sidebar-popular-list">
                    <?php foreach ($quizSidebarPopular as $popularQuiz) { ?>
                        <li>
                            <a class="quiz-sidebar-summary-title" href="<?php echo sr_e(sr_url('/quiz/' . rawurlencode((string) ($popularQuiz['quiz_key'] ?? '')))); ?>"><?php echo sr_e((string) ($popularQuiz['title'] ?? '')); ?></a>
                            <span class="quiz-sidebar-summary-meta">
                                조회 <?php echo sr_e(number_format((int) ($popularQuiz['view_count'] ?? 0))); ?>
                                <?php if ((string) ($popularQuiz['created_at'] ?? '') !== '') { ?>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_quiz_time_html((string) $popularQuiz['created_at']); ?>
                                <?php } ?>
                            </span>
                        </li>
                    <?php } ?>
                </ol></div>
            </section>
        <?php } ?>
        <?php if ($quizSidebarComments !== []) { ?>
            <section class="card quiz-sidebar-section quiz-sidebar-summary-section">
                <div class="card-header"><h2 class="card-title">최신댓글</h2></div>
                <div class="card-body quiz-sidebar-summary-body"><ul class="quiz-sidebar-list quiz-sidebar-comment-list">
                    <?php foreach ($quizSidebarComments as $sidebarComment) { ?>
                        <?php $quizSidebarCommentBaseUrl = sr_url('/quiz/' . rawurlencode((string) ($sidebarComment['quiz_key'] ?? '')) . '?result=1'); ?>
                        <li>
                            <a class="quiz-sidebar-comment-excerpt" href="<?php echo sr_e($quizSidebarCommentBaseUrl . '#quiz-comment-' . (string) (int) ($sidebarComment['id'] ?? 0)); ?>"><?php echo sr_e((string) ($sidebarComment['excerpt'] ?? '')); ?></a>
                            <span class="quiz-sidebar-comment-meta">
                                <span class="quiz-sidebar-comment-byline">
                                    <span><?php echo sr_e((string) ($sidebarComment['author_public_name'] ?? '회원')); ?></span>
                                    <span aria-hidden="true">&middot;</span>
                                    <?php echo sr_quiz_time_html((string) ($sidebarComment['created_at'] ?? '')); ?>
                                </span>
                                <?php if ((string) ($sidebarComment['quiz_title'] ?? '') !== '') { ?>
                                    <span class="quiz-sidebar-comment-separator" aria-hidden="true">&middot;</span>
                                    <a class="quiz-sidebar-comment-content" href="<?php echo sr_e($quizSidebarCommentBaseUrl); ?>"><?php echo sr_e((string) $sidebarComment['quiz_title']); ?></a>
                                <?php } ?>
                            </span>
                        </li>
                    <?php } ?>
                </ul></div>
            </section>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'quiz',
            'point_key' => 'quiz.sidebar.summary',
            'slot_key' => 'after_summary',
            'subject_id' => (string) (int) (($quizSidebarSubject['id'] ?? 0)),
        ]); ?>
    </aside>
    <?php
}
?>
