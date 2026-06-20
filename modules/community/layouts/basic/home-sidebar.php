<aside class="community-home-sidebar" aria-label="커뮤니티 보조 메뉴" data-community-home-accordion>
    <?php if (isset($homeMemberSummary) && is_array($homeMemberSummary)) { ?>
        <?php
        $homeMemberAvatarSrc = (string) ($homeMemberSummary['avatar_src'] ?? '');
        $homeMemberDisplayName = (string) ($homeMemberSummary['display_name'] ?? '내 계정');
        $homeMemberLevelValue = (int) ($homeMemberSummary['level_value'] ?? 0);
        $homeMemberLevelLabel = (string) ($homeMemberSummary['level_label'] ?? sr_community_level_label($homeMemberLevelValue));
        ?>
        <section class="community-home-member-card" aria-label="내 커뮤니티 활동">
            <div class="community-home-member-avatar-wrap">
                <?php if ($homeMemberAvatarSrc !== '') { ?>
                    <img class="community-home-member-avatar" src="<?php echo sr_e($homeMemberAvatarSrc); ?>" alt="" loading="lazy">
                <?php } else { ?>
                    <span class="member-default-avatar community-home-member-avatar <?php echo sr_e((string) ($homeMemberSummary['avatar_color_class'] ?? 'member-avatar-color-8')); ?>" aria-hidden="true"><?php echo sr_e((string) ($homeMemberSummary['initial'] ?? 'M')); ?></span>
                <?php } ?>
            </div>
            <strong class="community-home-member-name"><?php echo sr_e($homeMemberDisplayName); ?></strong>
            <?php if (!empty($homeMemberSummary['level_enabled'])) { ?>
                <span class="community-home-member-level-text"><?php echo sr_e($homeMemberLevelLabel); ?></span>
            <?php } ?>
            <dl class="community-home-member-stats">
                <div>
                    <dt><a href="<?php echo sr_e(sr_url('/community/my?type=posts')); ?>">내 글</a></dt>
                    <dd><a href="<?php echo sr_e(sr_url('/community/my?type=posts')); ?>"><?php echo sr_e(number_format((int) ($homeMemberSummary['post_count'] ?? 0))); ?></a></dd>
                </div>
                <div>
                    <dt><a href="<?php echo sr_e(sr_url('/community/my?type=comments')); ?>">내 댓글</a></dt>
                    <dd><a href="<?php echo sr_e(sr_url('/community/my?type=comments')); ?>"><?php echo sr_e(number_format((int) ($homeMemberSummary['comment_count'] ?? 0))); ?></a></dd>
                </div>
            </dl>
        </section>
    <?php } ?>
    <?php if (trim((string) ($homeSidebarMenuHtml ?? '')) !== '') { ?>
        <?php echo $homeSidebarMenuHtml; ?>
    <?php } else { ?>
        <p>보조 메뉴가 없습니다.</p>
    <?php } ?>
    <div class="community-home-secondary-banner-slot">
        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.home',
            'slot_key' => 'after_secondary_navigation',
        ]); ?>
    </div>
</aside>
