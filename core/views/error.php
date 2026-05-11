<?php

toy_public_layout_begin($pdo ?? null, $site ?? null, [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
]);
?>
    <main>
        <h1><?php echo toy_e($pageTitle); ?></h1>
        <p><?php echo toy_e($message); ?></p>

        <?php if (!empty($debug) && $exception instanceof Throwable) { ?>
            <pre><?php echo toy_e(toy_log_sensitive_text_sanitize(toy_log_line_value($exception->getMessage(), 1000))); ?></pre>
        <?php } ?>
    </main>
<?php toy_public_layout_end(); ?>
