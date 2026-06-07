<?php

require_once __DIR__ . '/../helpers.php';

$surveys = sr_survey_public_forms($pdo, 50);
$seo = [
    'title' => '설문',
    'canonical' => '/survey',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'stylesheets' => ['/modules/survey/assets/public.css'],
]);
?>
<main class="sr-public-main">
    <section class="sr-public-section">
        <div class="sr-public-container">
            <h1>설문</h1>
            <div class="sr-survey-list">
                <?php if ($surveys === []): ?>
                    <p>참여할 수 있는 설문이 없습니다.</p>
                <?php endif; ?>
                <?php foreach ($surveys as $survey): ?>
                    <article class="sr-survey-item">
                        <h2><a href="<?php echo sr_e(sr_url('/survey/' . (string) $survey['survey_key'])); ?>"><?php echo sr_e((string) $survey['title']); ?></a></h2>
                        <?php if ((string) ($survey['description'] ?? '') !== ''): ?>
                            <p><?php echo sr_e((string) $survey['description']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<?php
sr_public_layout_end();
