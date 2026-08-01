<?php

$pageTitle = '쪽지 보기';
$seo = [
    'title' => $pageTitle,
    'canonical' => '/message?id=' . (string) $message['id'],
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
            <a class="btn btn-outline-default" href="<?php echo sr_e(sr_url($messageBox === 'sent' ? '/messages?box=sent' : '/messages')); ?>">
                <?php echo sr_e($messageBox === 'sent' ? '보낸 쪽지' : '받은 쪽지'); ?>
            </a>
        </header>
        <article class="card">
        <div class="card-body ui-card-body-stack">
        <dl class="ui-description-list">
            <div>
            <dt>발신자</dt>
            <dd><?php echo sr_e(sr_message_account_label(
                is_string($message['sender_display_name'] ?? null) ? $message['sender_display_name'] : null,
                (int) $message['sender_account_id'],
                $canViewMemberIdentifiers,
                $config,
                is_string($message['sender_account_status'] ?? null) ? $message['sender_account_status'] : null
            )); ?></dd>
            </div>
            <div>
            <dt>수신자</dt>
            <dd><?php echo sr_e(sr_message_account_label(
                is_string($message['recipient_display_name'] ?? null) ? $message['recipient_display_name'] : null,
                (int) $message['recipient_account_id'],
                $canViewMemberIdentifiers,
                $config,
                is_string($message['recipient_account_status'] ?? null) ? $message['recipient_account_status'] : null
            )); ?></dd>
            </div>
            <div>
            <dt>작성일</dt>
            <dd><?php echo sr_message_time_html((string) $message['created_at']); ?></dd>
            </div>
            <div>
            <dt>읽은 시각</dt>
            <dd><?php echo sr_message_time_html((string) ($message['read_at'] ?? ''), '-'); ?></dd>
            </div>
        </dl>
        <div class="type-body">
            <?php echo sr_message_plain_text_html((string) $message['body_text']); ?>
        </div>
        </div>
        </article>

        <?php if ($messageReportAvailable) { ?>
            <?php echo sr_public_feedback_toasts('message-report', (string) ($messageReportFeedback['notice'] ?? ''), (array) ($messageReportFeedback['errors'] ?? [])); ?>
            <?php echo sr_community_public_report_form_html($messageReportContext); ?>
        <?php } ?>

        <div class="ui-actions">
        <?php if ($replyAccountHash !== '') { ?>
            <a class="btn btn-outline-primary" href="<?php echo sr_e(sr_url('/message/write?to_account=' . rawurlencode($replyAccountHash))); ?>">답장하기</a>
        <?php } ?>
        <form method="post" action="<?php echo sr_e(sr_url('/message/delete')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="message_id" value="<?php echo sr_e((string) $message['id']); ?>">
            <button type="submit" class="btn btn-outline-danger">삭제</button>
        </form>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
