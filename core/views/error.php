<?php
?>
<!doctype html>
<html lang="<?php echo sr_e(sr_locale()); ?>" data-color-scheme="system">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo sr_e($pageTitle); ?></title>
    <?php echo sr_stylesheet_tag(['/assets/theme.css']); ?>
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background: var(--sr-bg, #f6f7f9);
            color: var(--sr-text, #20242a);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(100% - 48px, 560px);
            text-align: center;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(3rem, 12vw, 6rem);
            line-height: 1;
            letter-spacing: 0;
        }

        p {
            margin: 0;
            color: var(--sr-muted, #47505c);
            font-size: 1rem;
            line-height: 1.7;
        }

        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 28px;
            min-height: 44px;
            padding: 0 18px;
            border: 1px solid var(--sr-border, #d8dde6);
            color: var(--sr-text, #20242a);
            text-decoration: none;
            font-weight: 600;
        }

        a:hover,
        a:focus {
            border-color: var(--sr-control-border, #b9c2d0);
            color: var(--sr-link, #2458a6);
        }

        pre {
            overflow: auto;
            margin: 24px 0 0;
            padding: 16px;
            border: 1px solid var(--sr-border, #d8dde6);
            background: var(--sr-surface, #ffffff);
            color: var(--sr-text, #20242a);
            font-size: 0.875rem;
            line-height: 1.6;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>
        <p><?php echo sr_e($message); ?></p>
        <a href="<?php echo sr_e(sr_url('/')); ?>"><?php echo sr_e('메인으로'); ?></a>

        <?php if (!empty($debug) && $exception instanceof Throwable) { ?>
            <pre><?php echo sr_e(sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 1000))); ?></pre>
        <?php } ?>
    </main>
</body>
</html>
