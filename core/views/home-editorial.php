<section class="public-home-editorial" data-public-home-particles>
    <canvas class="public-home-particle-canvas" aria-hidden="true" data-public-home-particle-canvas></canvas>
    <div class="public-home-heading">
        <h1 class="public-home-title"><?php echo sr_e($homeSiteName); ?></h1>
        <p class="public-home-tagline" aria-label="<?php echo sr_e('시작해 볼까요?'); ?>">
            <span class="public-home-tagline-character" aria-hidden="true">시</span>
            <span class="public-home-tagline-character" aria-hidden="true">작</span>
            <span class="public-home-tagline-character" aria-hidden="true">해</span>
            <span class="public-home-tagline-character public-home-tagline-space" aria-hidden="true">&nbsp;</span>
            <span class="public-home-tagline-character" aria-hidden="true">볼</span>
            <span class="public-home-tagline-character" aria-hidden="true">까</span>
            <span class="public-home-tagline-character" aria-hidden="true">요</span>
            <span class="public-home-tagline-character" aria-hidden="true">?</span>
        </p>
    </div>
    <?php if (($homePdo ?? null) instanceof PDO) { ?>
        <div class="public-home-explore">
            <p>관심 있는 이야기부터 시작해 보세요.</p>
            <nav aria-label="사이트 둘러보기"><?php echo sr_render_output_slot($homePdo, ['module_key' => 'core', 'point_key' => 'site.header', 'slot_key' => 'primary_navigation', 'menu_key' => 'header']); ?></nav>
        </div>
    <?php } ?>
</section>
