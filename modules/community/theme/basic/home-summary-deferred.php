<aside class="community-home-aside community-home-aside-deferred" aria-label="커뮤니티 요약" aria-live="polite" data-community-summary-deferred data-community-summary-url="<?php echo sr_e(sr_url('/community/summary')); ?>" data-community-summary-fallback-url="<?php echo sr_e(sr_url('/community')); ?>">
    <?php include SR_ROOT . '/modules/community/theme/basic/board-sidebar-menu.php'; ?>
    <section class="card community-home-aside-section">
        <div class="card-body community-home-aside-body">
            <p data-community-summary-status>커뮤니티 요약을 불러오는 중입니다.</p>
            <noscript>
                <p><a href="<?php echo sr_e(sr_url('/community')); ?>">커뮤니티 요약 보기</a></p>
            </noscript>
        </div>
    </section>
</aside>
