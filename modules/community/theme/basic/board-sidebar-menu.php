<?php
$communityBoardSidebarMenu = isset($communityBoardSidebarMenu) && is_array($communityBoardSidebarMenu)
    ? $communityBoardSidebarMenu
    : ['type' => '', 'title' => '', 'html' => ''];
$communityBoardSidebarMenuTitle = trim((string) ($communityBoardSidebarMenu['title'] ?? ''));
$communityBoardSidebarMenuHtml = (string) ($communityBoardSidebarMenu['html'] ?? '');
?>
<?php if ($communityBoardSidebarMenuTitle !== '' && $communityBoardSidebarMenuHtml !== '') { ?>
    <section class="card community-home-aside-section community-board-sidebar-menu" aria-labelledby="community_board_sidebar_menu_title" data-community-board-sidebar-menu>
        <div class="card-header">
            <h2 id="community_board_sidebar_menu_title" class="community-home-aside-title"><?php echo sr_e($communityBoardSidebarMenuTitle); ?></h2>
        </div>
        <div class="card-body community-home-aside-body">
            <?php echo $communityBoardSidebarMenuHtml; ?>
        </div>
    </section>
<?php } ?>
