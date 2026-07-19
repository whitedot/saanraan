<?php

require_once __DIR__ . '/../../helpers.php';

$settings = sr_survey_settings($pdo);
$surveys = isset($surveys) && is_array($surveys) ? $surveys : sr_survey_public_forms($pdo, (int) ($settings['public_list_limit'] ?? 50));
$surveyListPagination = isset($surveyListPagination) && is_array($surveyListPagination) ? $surveyListPagination : ['page' => 1, 'total_pages' => 1];
$surveyScreenTarget = isset($surveyScreenTarget) && $surveyScreenTarget === 'survey.list' ? 'survey.list' : 'survey.home';
$surveyScreenIsList = $surveyScreenTarget === 'survey.list';
$surveyListGroup = isset($surveyListGroup) && is_array($surveyListGroup) ? $surveyListGroup : null;
$surveyListGroupKey = is_array($surveyListGroup) ? (string) ($surveyListGroup['group_key'] ?? '') : '';
$surveyScreenTitle = is_array($surveyListGroup) ? (string) ($surveyListGroup['title'] ?? '전체 설문·여론조사') : ($surveyScreenIsList ? '전체 설문·여론조사' : '설문·여론조사');
$surveyListUrl = '/survey/list' . ($surveyListGroupKey !== '' ? '?group=' . rawurlencode($surveyListGroupKey) : '');
$surveyPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$seo = [
    'title' => $surveyScreenTitle,
    'canonical' => $surveyScreenIsList ? $surveyListUrl : '/survey',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'consumer_target' => $surveyScreenTarget,
    'body_class' => 'sr-survey-page',
    'output_slots' => [
        ['module_key' => 'survey', 'point_key' => $surveyScreenTarget, 'slot_key' => 'screen'],
        ['module_key' => 'survey', 'point_key' => 'survey.sidebar.summary', 'slot_key' => 'after_summary'],
    ],
]));
?>
<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'survey',
    'point_key' => $surveyScreenTarget,
    'slot_key' => 'screen',
]); ?>

<main class="survey-page-main">
    <section class="survey-page-section sr-survey-home">
        <div class="survey-page-container">
            <header class="sr-survey-home-header">
                <h1><?php echo sr_e($surveyScreenTitle); ?></h1>
                <?php if (!$surveyScreenIsList): ?>
                    <p>현재 참여할 수 있는 설문과 여론조사를 확인하세요.</p>
                <?php endif; ?>
            </header>
            <?php if ($surveyScreenIsList): ?>
                <div class="survey-screen-frame">
                    <div class="survey-screen-main">
            <?php endif; ?>
            <?php if ($surveys === []): ?>
                <p class="sr-survey-home-empty">참여할 수 있는 설문이 없습니다.</p>
            <?php else: ?>
                <div class="sr-survey-card-grid">
                <?php foreach ($surveys as $survey): ?>
                    <?php
                    $surveyKey = (string) ($survey['survey_key'] ?? '');
                    $surveyTitle = (string) ($survey['title'] ?? $surveyKey);
                    $surveyDescription = (string) ($survey['description'] ?? '');
                    $surveyUrl = sr_url('/survey/' . rawurlencode($surveyKey));
                    $surveyCoverHtml = sr_survey_cover_image_html($survey, 'sr-survey-card-image card-img-top', $surveyTitle);
                    ?>
                    <article class="card sr-survey-card">
                        <a class="sr-survey-card-media" href="<?php echo sr_e($surveyUrl); ?>" aria-label="<?php echo sr_e($surveyTitle); ?>">
                            <?php if ($surveyCoverHtml !== ''): ?>
                                <?php echo $surveyCoverHtml; ?>
                            <?php else: ?>
                                <span class="sr-survey-card-placeholder card-img-top" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <div class="sr-survey-card-copy card-body">
                            <p class="sr-survey-card-meta">
                                <span><?php echo sr_e($surveyPublisherName); ?></span>
                            </p>
                            <h2>
                                <a href="<?php echo sr_e($surveyUrl); ?>">
                                    <span><?php echo sr_e($surveyTitle); ?></span>
                                </a>
                            </h2>
                            <?php if ($surveyDescription !== ''): ?>
                                <p class="sr-survey-card-summary"><?php echo sr_e($surveyDescription); ?></p>
                            <?php endif; ?>
                            <div class="sr-survey-card-footer">
                                <span><?php echo sr_e($surveyPublisherName); ?></span>
                                <?php if ((string) ($survey['updated_at'] ?? '') !== ''): ?>
                                    <?php echo sr_survey_time_html((string) $survey['updated_at']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($surveyScreenIsList): ?>
                <?php echo sr_public_pagination_html($surveyListPagination, $surveyListUrl, '설문 목록 페이지'); ?>
            <?php else: ?>
                <p><a class="btn btn-outline-primary" href="<?php echo sr_e(sr_url('/survey/list')); ?>">전체 설문 보기</a></p>
            <?php endif; ?>
            <?php if ($surveyScreenIsList): ?>
                    </div>
                    <?php $surveySidebarSubject = ['group_key' => $surveyListGroupKey]; ?>
                    <?php include SR_ROOT . '/modules/survey/views/sidebar.php'; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
