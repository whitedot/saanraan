<?php

declare(strict_types=1);

function sr_tools_coupon_helper_source(string $root): string
{
    $source = '';
    $files = [$root . '/modules/coupon/helpers.php'];
    foreach (glob($root . '/modules/coupon/helpers/*.php') ?: [] as $file) {
        $files[] = $file;
    }

    foreach ($files as $file) {
        $part = file_get_contents($file);
        if (is_string($part)) {
            $source .= "\n" . $part;
        }
    }

    return $source;
}
