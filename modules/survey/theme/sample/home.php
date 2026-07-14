<?php

$settings = isset($settings) && is_array($settings) ? $settings : sr_survey_settings($pdo);
$surveys = isset($surveys) && is_array($surveys) ? $surveys : sr_survey_public_forms($pdo, (int) ($settings['public_list_limit'] ?? 50));
$surveyListPagination = isset($surveyListPagination) && is_array($surveyListPagination) ? $surveyListPagination : ['page' => 1, 'total_pages' => 1];
$surveyPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$seo = [
    'title' => '설문·여론조사',
    'canonical' => '/survey',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'consumer_target' => 'survey.home',
    'body_class' => 'example-survey-body',
    'include_skin_assets' => false,
    'stylesheets' => sr_enabled_module_asset_paths($pdo ?? null, [
        'popup_layer' => '/modules/popup_layer/assets/module.css',
    ]),
    'output_slots' => [
        ['module_key' => 'survey', 'point_key' => 'survey.home', 'slot_key' => 'screen'],
    ],
]));
?>

<?php echo sr_render_output_slot($pdo, [
    'module_key' => 'survey',
    'point_key' => 'survey.home',
    'slot_key' => 'screen',
]); ?>

<main class="example-survey-theme example-survey-home" data-example-theme-view="survey.home">
    <section class="example-survey-hero" aria-labelledby="example_survey_home_title">
        <p class="example-content-kicker">SURVEY MODULE VIEW THEME</p>
        <h1 id="example_survey_home_title">Survey Lab</h1>
        <p>설문 홈은 skin이 아니라 <code>modules/survey/theme/sample/home.php</code>에서 렌더링됩니다.</p>
    </section>

    <section class="example-survey-grid" aria-label="설문 목록">
        <?php if ($surveys === []) { ?>
            <p class="example-survey-panel">참여할 수 있는 설문이 없습니다.</p>
        <?php } else { ?>
            <?php foreach ($surveys as $survey) { ?>
                <?php
                $surveyKey = (string) ($survey['survey_key'] ?? '');
                $surveyTitle = (string) ($survey['title'] ?? $surveyKey);
                $surveyUrl = sr_url('/survey/' . rawurlencode($surveyKey));
                ?>
                <article class="example-survey-card">
                    <?php echo sr_survey_cover_image_html($survey, 'example-survey-card-image', $surveyTitle); ?>
                    <p class="example-content-kicker"><?php echo sr_e($surveyPublisherName); ?></p>
                    <h2><a href="<?php echo sr_e($surveyUrl); ?>"><?php echo sr_e($surveyTitle); ?></a></h2>
                    <?php if ((string) ($survey['description'] ?? '') !== '') { ?>
                        <p><?php echo sr_e((string) $survey['description']); ?></p>
                    <?php } ?>
                    <?php echo sr_survey_time_html((string) ($survey['updated_at'] ?? '')); ?>
                </article>
            <?php } ?>
        <?php } ?>
    </section>
    <?php echo sr_public_pagination_html($surveyListPagination, '/survey', '설문 목록 페이지'); ?>
</main>

<?php sr_public_layout_end(); ?>
