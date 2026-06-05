<?php

$pageTitle = (string) $board['title'];
$baseListPath = '/community/board?key=' . rawurlencode((string) $board['board_key'])
    . (isset($selectedCategory) && is_array($selectedCategory) ? '&category=' . rawurlencode((string) $selectedCategory['category_key']) : '')
    . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : '');
$seo = [
    'title' => $pageTitle,
    'description' => (string) ($board['description'] ?? ''),
    'canonical' => $baseListPath . ($page > 1 ? '&page=' . (string) $page : ''),
    'robots' => !empty($categoryInvalid)
        ? 'noindex, follow'
        : ((string) ($board['effective_read_policy'] ?? $board['read_policy']) !== 'public'
        ? 'noindex, nofollow'
        : ($keyword === '' ? 'index, follow' : 'noindex, follow')),
];
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
$memberSettings = sr_member_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => array_merge(sr_community_skin_stylesheets($skinKey ?? 'basic'), [
        '/modules/banner/assets/public.css',
        '/modules/popup_layer/assets/public.css',
    ]),
]));
?>
    <main>
        <?php if (function_exists('sr_popup_layer_render_public_layer') && sr_module_enabled($pdo, 'popup_layer')) { ?>
            <?php echo sr_popup_layer_render_public_layer($pdo, (int) ($board['popup_layer_list_id'] ?? 0)); ?>
        <?php } ?>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'before_list',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_before_list_id'] ?? 0)); ?>
        <?php } ?>

        <p><a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $board['description']); ?></p>
        <?php } ?>

        <?php if ($boardNotice !== '') { ?>
            <p><?php echo sr_e($boardNotice); ?></p>
        <?php } ?>

        <?php if ($canWriteBoard) { ?>
            <p>
                <a href="<?php echo sr_e(sr_url('/community/write?key=' . rawurlencode((string) $board['board_key']))); ?>"><?php echo sr_e(sr_t('community::ui.text.1f1955dd')); ?></a>
            </p>
        <?php } ?>

        <?php if (isset($categories) && is_array($categories) && $categories !== []) { ?>
            <nav aria-label="카테고리">
                <p>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : ''))); ?>"><?php echo sr_e('전체'); ?></a>
                    <?php foreach ($categories as $category) { ?>
                        <?php $categoryUrl = '/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $category['category_key']) . ($keyword !== '' ? '&q=' . rawurlencode($keyword) : ''); ?>
                        /
                        <a href="<?php echo sr_e(sr_url($categoryUrl)); ?>"<?php echo isset($selectedCategory) && is_array($selectedCategory) && (int) $selectedCategory['id'] === (int) $category['id'] ? ' aria-current="page"' : ''; ?>>
                            <?php echo sr_e((string) $category['title']); ?>
                        </a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <form method="get" action="<?php echo sr_e(sr_url('/community/board')); ?>">
            <input type="hidden" name="key" value="<?php echo sr_e((string) $board['board_key']); ?>">
            <p>
                <label for="modules_community_list_q">
                    <span><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></span>
                    <input id="modules_community_list_q" type="search" name="q" maxlength="100" value="<?php echo sr_e($keyword); ?>">
                </label>
                <button type="submit"><?php echo sr_e(sr_t('community::ui.search.4b8d541e')); ?></button>
                <?php if ($keyword !== '') { ?>
                    <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']))); ?>"><?php echo sr_e(sr_t('community::ui.text.893f3d94')); ?></a>
                <?php } ?>
            </p>
        </form>

        <?php if (!empty($categoryInvalid)) { ?>
            <p>카테고리를 찾을 수 없거나 현재 사용할 수 없습니다.</p>
        <?php } elseif ($posts === []) { ?>
            <p><?php echo $keyword !== '' ? sr_t('community::ui.search.58726bf2') : sr_t('community::ui.text.6a3d84bd'); ?></p>
        <?php } else { ?>
            <table>
                <thead>
                    <tr>
                        <th><?php echo sr_e(sr_t('community::ui.text.08b17e43')); ?></th>
                        <th><?php echo sr_e('카테고리'); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.f2ee20a7')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.26c8f2fa')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.c9fff683')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.353b76cf')); ?></th>
                        <th><?php echo sr_e(sr_t('community::ui.text.f8d240bf')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post) { ?>
                        <tr>
                            <td>
                                <a href="<?php echo sr_e(sr_url('/community/post?id=' . (string) $post['id'])); ?>">
                                    <?php echo sr_e((string) $post['title']); ?>
                                </a>
                            </td>
                            <td>
                                <?php if ((string) ($post['category_title'] ?? '') !== '') { ?>
                                    <?php if ((string) ($post['category_status'] ?? '') === 'enabled' && (string) ($post['category_key'] ?? '') !== '') { ?>
                                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode((string) $board['board_key']) . '&category=' . rawurlencode((string) $post['category_key']))); ?>"><?php echo sr_e((string) $post['category_title']); ?></a>
                                    <?php } else { ?>
                                        <?php echo sr_e((string) $post['category_title']); ?>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e(sr_community_author_label_from_row($post, $config, $canViewMemberIdentifiers, $memberSettings, $pdo)); ?></td>
                            <td><?php echo sr_e((string) $post['created_at']); ?></td>
                            <td><?php echo sr_e((string) ($post['published_comment_count'] ?? 0)); ?></td>
                            <td><?php echo sr_e((string) ($post['active_attachment_count'] ?? 0)); ?></td>
                            <td><?php echo sr_e((string) $post['view_count']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>

        <?php if ($totalPages > 1) { ?>
            <nav aria-label="<?php echo sr_e(sr_t('community::ui.page.13726597')); ?>">
                <p>
                    <?php if ($page > 1) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page - 1))); ?>"><?php echo sr_e(sr_t('community::ui.text.da7e61c6')); ?></a>
                    <?php } ?>
                    <?php echo sr_e((string) $page); ?> / <?php echo sr_e((string) $totalPages); ?>
                    <?php if ($page < $totalPages) { ?>
                        <a href="<?php echo sr_e(sr_url($baseListPath . '&page=' . (string) ($page + 1))); ?>"><?php echo sr_e(sr_t('community::ui.text.aef613c6')); ?></a>
                    <?php } ?>
                </p>
            </nav>
        <?php } ?>

        <?php echo sr_render_output_slot($pdo, [
            'module_key' => 'community',
            'point_key' => 'community.board.list',
            'slot_key' => 'after_list',
            'subject_id' => (string) $board['id'],
        ]); ?>
        <?php if (function_exists('sr_banner_render_public_banner') && sr_module_enabled($pdo, 'banner')) { ?>
            <?php echo sr_banner_render_public_banner($pdo, (int) ($board['banner_after_list_id'] ?? 0)); ?>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
