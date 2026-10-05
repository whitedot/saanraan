<?php

$pageTitle = $box === 'sent' ? '보낸 쪽지' : '받은 쪽지';
$seo = [
    'title' => $pageTitle,
    'canonical' => $box === 'sent' ? '/messages?box=sent' : '/messages',
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'consumer_domain' => 'message',
    'module_home_url' => sr_url('/messages'),
    'module_label' => '쪽지',
]);
?>
    <main class="ui-page message-screen">
        <header class="ui-page-header">
            <h1 class="type-page-title"><?php echo sr_e($pageTitle); ?></h1>
            <a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url('/message/write')); ?>">쪽지 쓰기</a>
        </header>
        <nav class="ui-actions" aria-label="쪽지함">
            <a class="btn <?php echo $box === 'sent' ? 'btn-outline-default' : 'btn-soft-primary'; ?>" href="<?php echo sr_e(sr_url('/messages')); ?>">받은 쪽지</a>
            <a class="btn <?php echo $box === 'sent' ? 'btn-soft-primary' : 'btn-outline-default'; ?>" href="<?php echo sr_e(sr_url('/messages?box=sent')); ?>">보낸 쪽지</a>
        </nav>

        <?php echo sr_public_feedback_toasts('message', $notice, []); ?>

        <section id="message-list" class="card">
        <div class="card-body ui-card-body-stack">
        <?php if ($messages === []) { ?>
            <p>표시할 쪽지가 없습니다.</p>
        <?php } else { ?>
            <ul class="ui-reading-list">
                <?php foreach ($messages as $message) { ?>
                    <li>
                        <a href="<?php echo sr_e(sr_url('/message?id=' . (string) $message['id'])); ?>">
                            <strong><?php echo sr_e(sr_message_account_label($message['other_display_name'] ?? null, $box === 'sent' ? (int) $message['recipient_account_id'] : (int) $message['sender_account_id'], $canViewMemberIdentifiers, $config, $message['other_account_status'] ?? null)); ?></strong>
                            <?php if ((string) ($message['read_at'] ?? '') === '') { ?><span class="badge badge-soft-primary"><?php echo $box === 'sent' ? '읽지 않음' : '새 쪽지'; ?></span><?php } ?>
                            <p><?php echo sr_e((function_exists('mb_substr') ? mb_substr(trim((string) ($message['body_text'] ?? '')), 0, 140, 'UTF-8') : substr(trim((string) ($message['body_text'] ?? '')), 0, 140))); ?></p>
                            <small><?php echo sr_message_time_html((string) $message['created_at']); ?></small>
                        </a>
                        <details>
                            <summary class="btn btn-icon btn-ghost-light" aria-label="쪽지 메뉴"><?php echo sr_material_icon_html('more_horiz'); ?></summary>
                            <div><form method="post" action="<?php echo sr_e(sr_url('/message/delete')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="message_id" value="<?php echo sr_e((string) $message['id']); ?>">
                                <input type="hidden" name="return_page" value="<?php echo sr_e((string) $messagePage); ?>">
                                <button type="submit" class="dropdown-item">삭제</button>
                            </form></div>
                        </details>
                    </li>
                <?php } ?>
            </ul>
        <?php } ?>
        <?php echo sr_public_pagination_html($messagePagination, $messagePaginationBasePath, '쪽지 목록 페이지', 'page', 'message-list'); ?>
        </div>
        </section>
    </main>
<?php sr_public_layout_end(); ?>
