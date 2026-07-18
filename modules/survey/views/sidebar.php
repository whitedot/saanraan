<?php
$surveySidebarContext = isset($surveySidebarContext) && is_array($surveySidebarContext)
    ? $surveySidebarContext
    : sr_survey_sidebar_context($pdo, $settings ?? sr_survey_settings($pdo), $surveySidebarSubject ?? []);
if (!empty($surveySidebarContext['enabled'])) {
    $surveySidebarMenu = is_array($surveySidebarContext['menu'] ?? null) ? $surveySidebarContext['menu'] : [];
    $surveySidebarPopular = is_array($surveySidebarContext['popular'] ?? null) ? $surveySidebarContext['popular'] : [];
    $surveySidebarComments = is_array($surveySidebarContext['comments'] ?? null) ? $surveySidebarContext['comments'] : [];
    ?>
    <aside class="survey-sidebar" aria-label="설문 사이드">
        <?php if ((string) ($surveySidebarMenu['html'] ?? '') !== '') { ?>
            <section class="card survey-sidebar-section">
                <div class="card-header"><h2 class="card-title"><?php echo sr_e((string) ($surveySidebarMenu['title'] ?? '메뉴')); ?></h2></div>
                <div class="card-body"><?php echo (string) $surveySidebarMenu['html']; ?></div>
            </section>
        <?php } ?>
        <?php if ($surveySidebarPopular !== []) { ?>
            <section class="card survey-sidebar-section">
                <div class="card-header"><h2 class="card-title">인기 설문</h2></div>
                <div class="card-body"><ol class="survey-sidebar-list">
                    <?php foreach ($surveySidebarPopular as $popularSurvey) { ?>
                        <li><a href="<?php echo sr_e(sr_url('/survey/' . rawurlencode((string) ($popularSurvey['survey_key'] ?? '')))); ?>"><?php echo sr_e((string) ($popularSurvey['title'] ?? '')); ?></a><span>조회 <?php echo sr_e(number_format((int) ($popularSurvey['view_count'] ?? 0))); ?></span></li>
                    <?php } ?>
                </ol></div>
            </section>
        <?php } ?>
        <?php if ($surveySidebarComments !== []) { ?>
            <section class="card survey-sidebar-section">
                <div class="card-header"><h2 class="card-title">최신댓글</h2></div>
                <div class="card-body"><ul class="survey-sidebar-list survey-sidebar-comment-list">
                    <?php foreach ($surveySidebarComments as $sidebarComment) { ?>
                        <li>
                            <a href="<?php echo sr_e(sr_url('/survey/' . rawurlencode((string) ($sidebarComment['survey_key'] ?? '')) . '?submitted=1#survey-comment-' . (string) (int) ($sidebarComment['id'] ?? 0))); ?>"><?php echo sr_e((string) ($sidebarComment['excerpt'] ?? '')); ?></a>
                            <span><?php echo sr_e((string) ($sidebarComment['author_public_name'] ?? '회원')); ?> · <?php echo sr_survey_time_html((string) ($sidebarComment['created_at'] ?? '')); ?></span>
                        </li>
                    <?php } ?>
                </ul></div>
            </section>
        <?php } ?>
        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'survey',
            'point_key' => 'survey.sidebar.summary',
            'slot_key' => 'after_summary',
            'subject_id' => (string) (int) (($surveySidebarSubject['id'] ?? 0)),
        ]); ?>
    </aside>
    <?php
}
?>
