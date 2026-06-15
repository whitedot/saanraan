<?php

require_once __DIR__ . '/../../helpers.php';

$settings = sr_survey_settings($pdo);
$surveys = sr_survey_public_forms($pdo, (int) ($settings['public_list_limit'] ?? 50));
$surveyPublisherName = sr_site_display_name(is_array($site ?? null) ? $site : null, $pdo ?? null);
$seo = [
    'title' => '설문',
    'canonical' => '/survey',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'body_class' => 'sr-survey-page',
]));
?>
<main class="sr-public-main">
    <section class="sr-public-section sr-survey-home">
        <div class="sr-public-container">
            <header class="sr-survey-home-header">
                <h1>설문</h1>
            </header>
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
                    $surveyCoverHtml = sr_survey_cover_image_html($survey, 'sr-survey-card-image', $surveyTitle);
                    ?>
                    <article class="sr-survey-card">
                        <a class="sr-survey-card-media" href="<?php echo sr_e($surveyUrl); ?>" aria-label="<?php echo sr_e($surveyTitle); ?>">
                            <?php if ($surveyCoverHtml !== ''): ?>
                                <?php echo $surveyCoverHtml; ?>
                            <?php else: ?>
                                <span class="sr-survey-card-placeholder" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                        <div class="sr-survey-card-copy">
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
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
