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

        <?php echo sr_public_feedback_toasts('message-report', $reportNotice, $reportErrors); ?>

        <form method="post" action="<?php echo sr_e(sr_url('/community/report')); ?>" class="card">
            <div class="card-header"><h2 class="card-title">쪽지 신고</h2></div>
            <div class="card-body ui-card-body-stack">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="target_type" value="message">
            <input type="hidden" name="target_id" value="<?php echo sr_e((string) $message['id']); ?>">
            <p>
                <label class="ui-field" for="modules_message_view_reason_key">
                    <span>신고 사유 <span class="sr-required-label">(필수)</span></span>
                    <select id="modules_message_view_reason_key" name="reason_key" required class="form-select">
                        <?php foreach ($reportReasonKeys as $reasonKey) { ?>
                            <option value="<?php echo sr_e($reasonKey); ?>"><?php echo sr_e(sr_community_report_reason_label($reasonKey)); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label class="ui-field" for="modules_message_view_memo_text">
                    <span>메모</span>
                    <textarea id="modules_message_view_memo_text" name="memo_text" rows="3" cols="60" class="form-textarea"></textarea>
                </label>
            </p>
            <button type="submit" class="btn btn-solid-primary">신고</button>
            </div>
        </form>

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
