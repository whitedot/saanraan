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
    <main>
        <p><a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <?php if ($scraps === []) { ?>
            <p><?php echo sr_e(sr_t('community::ui.text.78d4e2f7')); ?></p>
        <?php } else { ?>
            <table>
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
                                    <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $scrap['post_id'])); ?>">
                                        <?php echo sr_e((string) $scrap['title']); ?>
                                    </a>
                                <?php } else { ?>
                                    <?php echo sr_e(sr_t('community::ui.delete.029fd110')); ?>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e((string) $scrap['created_at']); ?></td>
                            <td>
                                <form method="post" action="<?php echo sr_e(sr_url('/community/scrap')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="post_id" value="<?php echo sr_e((string) $scrap['post_id']); ?>">
                                    <input type="hidden" name="intent" value="remove">
                                    <button type="submit"><?php echo sr_e(sr_t('community::ui.text.293182ec')); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
