<?php

sr_public_layout_begin($pdo ?? null, $site ?? null, [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
]);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><?php echo sr_e($message); ?></p>

        <?php if (!empty($debug) && $exception instanceof Throwable) { ?>
            <pre><?php echo sr_e(sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 1000))); ?></pre>
        <?php } ?>
    </main>
<?php sr_public_layout_end(); ?>
