<?php

declare(strict_types=1);

if (!isset($communityThemeFallbackViewFile) || !is_string($communityThemeFallbackViewFile) || !is_file($communityThemeFallbackViewFile)) {
    throw new RuntimeException('Community skin list view is missing.');
}

include $communityThemeFallbackViewFile;
