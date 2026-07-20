<?php

$pageTitle = sr_t('community::ui.text.b255d86c');
$seo = [
    'title' => $pageTitle,
    'canonical' => '/community/scraps',
    'robots' => 'noindex, nofollow',
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings));
?>
    <main class="community-screen">
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php echo sr_public_feedback_toasts('community', $notice, []); ?>

        <?php if ($scraps === [] && $seriesScraps === []) { ?>
            <p><?php echo sr_e(sr_t('community::ui.text.78d4e2f7')); ?></p>
        <?php } ?>

        <?php if ($scraps !== []) { ?>
            <section id="community-post-scraps" class="card">
            <div class="card-header"><h2 class="card-title">게시글 스크랩</h2></div>
            <div class="card-body ui-card-body-stack">
            <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></th>
                        <th>카테고리</th>
                        <th><?php echo sr_e(sr_t('community::ui.text.08b17e43')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.4bd3d310')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scraps as $scrap) { ?>
                        <tr>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($scrap)) { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $scrap['board_key']))); ?>">
                                        <?php echo sr_e((string) ($scrap['board_title'] ?? '')); ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('community::ui.delete.7df929b7')); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($scrap) && (string) ($scrap['category_title'] ?? '') !== '') { ?>
                                    <?php if ((string) ($scrap['category_status'] ?? '') === 'enabled' && (string) ($scrap['category_key'] ?? '') !== '') { ?>
                                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $scrap['board_key']) . '&category=' . rawurlencode((string) $scrap['category_key']))); ?>"><?php echo sr_e((string) $scrap['category_title']); ?></a>
                                    <?php } else { ?>
                                        <?php echo sr_e((string) $scrap['category_title']); ?>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($scrap)) { ?>
                                    <a class="community-post-title community-post-scrap-title" href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $scrap['post_id'])); ?>">
                                        <?php echo sr_e((string) $scrap['title']); ?>
                                    </a>
                                    <?php echo sr_community_post_comment_count_html($scrap); ?>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('community::ui.delete.029fd110')); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_community_time_html((string) $scrap['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $scrap['post_id']); ?>">
                                    <input type="hidden" name="intent" value="remove">
                                    <input type="hidden" name="return_to" value="scraps">
                                    <input type="hidden" name="return_post_page" value="<?php echo sr_e((string) $scrapPage); ?>">
                                    <input type="hidden" name="return_series_page" value="<?php echo sr_e((string) $seriesScrapPage); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('community::ui.text.293182ec')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
            <?php echo sr_public_pagination_html($scrapPagination, $scrapPaginationBasePath, '게시글 스크랩 페이지', 'post_page', 'community-post-scraps'); ?>
            </div>
            </section>
        <?php } ?>

        <?php if ($seriesScraps !== []) { ?>
            <section id="community-series-scraps" class="card">
            <div class="card-header"><h2 class="card-title">시리즈 스크랩</h2></div>
            <div class="card-body ui-card-body-stack">
            <div class="table-wrapper">
            <table class="table table-list">
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('community::ui.text.4732a58f')); ?></th>
                        <th>시리즈</th>
                        <th>공개 범위</th>
                        <th><?php echo sr_e(sr_t('community::ui.text.4bd3d310')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.29ae8f30')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($seriesScraps as $seriesScrap) { ?>
                        <tr>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($seriesScrap)) { ?>
                                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $seriesScrap['board_key']))); ?>">
                                        <?php echo sr_e((string) ($seriesScrap['board_title'] ?? '')); ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('community::ui.delete.7df929b7')); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($seriesScrap)) { ?>
                                    <a class="community-post-title community-post-scrap-title" href="<?php echo sr_e(sr_url('/community/series?id=' . rawurlencode((string) (int) $seriesScrap['series_id']))); ?>">
                                        <?php echo sr_e((string) ($seriesScrap['title'] ?? '')); ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo sr_e('열람할 수 없는 시리즈'); ?>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (sr_community_scrap_row_can_view($seriesScrap)) { ?>
                                    <?php echo sr_e(sr_community_series_visibility_label((string) ($seriesScrap['visibility'] ?? ''))); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_community_time_html((string) $seriesScrap['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="target_type" value="series">
                                    <input type="hidden" name="series_id" value="<?php echo sr_e((string) $seriesScrap['series_id']); ?>">
                                    <input type="hidden" name="intent" value="remove">
                                    <input type="hidden" name="return_to" value="scraps">
                                    <input type="hidden" name="return_post_page" value="<?php echo sr_e((string) $scrapPage); ?>">
                                    <input type="hidden" name="return_series_page" value="<?php echo sr_e((string) $seriesScrapPage); ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('community::ui.text.293182ec')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            </div>
            <?php echo sr_public_pagination_html($seriesScrapPagination, $seriesScrapPaginationBasePath, '시리즈 스크랩 페이지', 'series_page', 'community-series-scraps'); ?>
            </div>
            </section>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
