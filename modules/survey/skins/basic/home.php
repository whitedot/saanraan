<?php

require_once __DIR__ . '/../../helpers.php';

$settings = sr_survey_settings($pdo);
$surveys = sr_survey_public_forms($pdo, (int) ($settings['public_list_limit'] ?? 50));
$seo = [
    'title' => '설문',
    'canonical' => '/survey',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_survey_public_layout_context($settings, [
    'body_class' => 'sr-survey-page',
]));
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1>설문</h1>
            <?php if ($surveys === []): ?>
                <p>참여할 수 있는 설문이 없습니다.</p>
            <?php else: ?>
                <ul class="sr-survey-list">
                <?php foreach ($surveys as $survey): ?>
                    <li>
                        <a href="<?php echo sr_e(sr_url('/survey/' . rawurlencode((string) $survey['survey_key']))); ?>">
                            <?php echo sr_survey_cover_image_html($survey, 'sr-survey-list-cover', (string) ($survey['title'] ?? '')); ?>
                            <span class="sr-survey-list-title"><?php echo sr_e((string) $survey['title']); ?></span>
                            <?php if ((string) ($survey['description'] ?? '') !== ''): ?>
                                <span class="sr-survey-list-summary"><?php echo sr_e((string) $survey['description']); ?></span>
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
