<?php

$layoutPdo = isset($pdo) && $pdo instanceof PDO ? $pdo : null;
$pageTitle = sr_site_display_name(is_array($site ?? null) ? $site : null, $layoutPdo);
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/'),
];

sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'body_class' => 'public-layout-home sr-site-home site-view-theme-sample',
    'style_profile' => 'kit',
    'stylesheets' => [
        '/assets/module.css',
        '/assets/theme/sample.css',
        '/modules/banner/assets/module.css',
    ],
    'output_slots' => [
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content'],
        ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content'],
    ],
]);
?>

<main class="example-site-theme example-site-home" data-view-theme="core.sample.home">
    <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'before_content']); ?>

    <section class="example-site-hero" aria-labelledby="example_site_home_title">
        <p class="example-content-kicker">CORE VIEW THEME</p>
        <h1 id="example_site_home_title"><?php echo sr_e($pageTitle); ?></h1>
        <p>이 초기화면은 <code>core/views/home.php</code>가 아니라 <code>core/views/theme/sample/home.php</code>에서 렌더링됩니다.</p>
        <p>
            <a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/content')); ?>">콘텐츠</a>
            <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티</a>
        </p>
    </section>

    <section class="example-site-grid" aria-label="공개 모듈">
        <article class="example-site-panel">
            <p class="example-content-kicker">CONTENT</p>
            <h2><a href="<?php echo sr_e(sr_url('/content')); ?>">콘텐츠 화면</a></h2>
            <p>콘텐츠 모듈은 <code>modules/content/theme/sample</code>을 선택할 수 있습니다.</p>
        </article>
        <article class="example-site-panel">
            <p class="example-content-kicker">COMMUNITY</p>
            <h2><a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티 화면</a></h2>
            <p>커뮤니티 테마는 게시판 스킨과 별개로 공개 view DOM을 바꿉니다.</p>
        </article>
        <article class="example-site-panel">
            <p class="example-content-kicker">QUIZ</p>
            <h2><a href="<?php echo sr_e(sr_url('/quiz')); ?>">퀴즈 화면</a></h2>
            <p>퀴즈 테마는 기존 skin fallback보다 먼저 view 파일을 찾습니다.</p>
        </article>
        <article class="example-site-panel">
            <p class="example-content-kicker">SURVEY</p>
            <h2><a href="<?php echo sr_e(sr_url('/survey')); ?>">설문 화면</a></h2>
            <p>설문 테마도 모듈 내부 theme 디렉터리에서만 선택됩니다.</p>
        </article>
    </section>

    <?php echo sr_render_output_slot($pdo, ['module_key' => 'core', 'point_key' => 'site.home', 'slot_key' => 'after_content']); ?>
</main>

<?php sr_public_layout_end(); ?>
