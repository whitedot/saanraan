<?php

$groupTitle = trim((string) ($boardGroup['title'] ?? ''));
$groupKey = (string) ($boardGroup['group_key'] ?? '');
$pageTitle = $groupTitle !== '' ? $groupTitle : $groupKey;
$groupDescription = (string) ($boardGroup['description'] ?? '');
$seo = [
    'title' => $pageTitle,
    'description' => sr_community_seo_text($groupDescription, 200),
    'canonical' => sr_community_board_group_path($groupKey),
    'robots' => 'index, follow',
    'og' => [
        'title' => $pageTitle,
        'description' => sr_community_seo_text($groupDescription, 200),
        'type' => 'website',
    ],
];
$communityLayoutSettings = isset($settings) && is_array($settings) ? $settings : sr_community_settings($pdo);
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, sr_community_public_layout_context($communityLayoutSettings, [
    'stylesheets' => [
        '/modules/banner/assets/public.css',
        '/modules/popup_layer/assets/public.css',
    ],
]));
?>
    <main class="community-screen">
        <p><a href="<?php echo sr_e(sr_url('/community')); ?>"><?php echo sr_e(sr_t('community::ui.community.4a285775')); ?></a></p>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <?php if ($groupDescription !== '') { ?>
            <p><?php echo nl2br(sr_e($groupDescription)); ?></p>
        <?php } ?>

        <?php if ($groupBoards === []) { ?>
            <p>현재 볼 수 있는 게시판이 없습니다.</p>
        <?php } else { ?>
            <ul>
                <?php foreach ($groupBoards as $board) { ?>
                    <?php $boardKey = (string) ($board['board_key'] ?? ''); ?>
                    <?php if (!sr_community_board_key_is_valid($boardKey)) { ?>
                        <?php continue; ?>
                    <?php } ?>
                    <li>
                        <a href="<?php echo sr_e(sr_url('/community/board?key=' . rawurlencode($boardKey))); ?>">
                            <?php echo sr_e((string) ($board['title'] ?? $boardKey)); ?>
                        </a>
                        <?php if ((string) ($board['description'] ?? '') !== '') { ?>
                            <br><?php echo sr_e((string) $board['description']); ?>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
