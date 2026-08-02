<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';

function sr_pwa_head_tags(?PDO $pdo, ?array $site): string
{
    return '<link rel="manifest" href="' . sr_e(sr_url('/manifest.webmanifest')) . '">' . "\n"
        . '    <meta name="theme-color" content="' . sr_e(sr_pwa_theme_color($pdo, $site)) . '">' . "\n"
        . '    <meta name="mobile-web-app-capable" content="yes">' . "\n"
        . '    <meta name="apple-mobile-web-app-capable" content="yes">';
}

function sr_pwa_registration_script(): string
{
    $serviceWorkerUrl = sr_url('/service-worker.js');
    $scopeUrl = sr_url('/');
    return '<script' . sr_csp_nonce_attribute() . '>(function(){if(!("serviceWorker" in navigator)){return;}window.addEventListener("load",function(){navigator.serviceWorker.register('
        . sr_js_json_encode($serviceWorkerUrl)
        . ',{scope:'
        . sr_js_json_encode($scopeUrl)
        . '}).catch(function(){});});})();</script>';
}

function sr_pwa_theme_color(?PDO $pdo, ?array $site): string
{
    return '#111827';
}

function sr_pwa_manifest_payload(?PDO $pdo, ?array $site): array
{
    $siteName = sr_site_display_name($site, $pdo);

    return [
        'name' => $siteName,
        'short_name' => function_exists('mb_substr') ? mb_substr($siteName, 0, 12) : substr($siteName, 0, 12),
        'description' => $siteName,
        'start_url' => sr_url('/'),
        'scope' => sr_url('/'),
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => sr_pwa_theme_color($pdo, $site),
        'icons' => [
            [
                'src' => sr_url('/assets/pwa-icon.svg'),
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any maskable',
            ],
        ],
    ];
}

function sr_pwa_service_worker_source(): string
{
    $basePath = sr_base_path();
    $assetPrefix = rtrim($basePath, '/') . '/assets/';
    $moduleAssetPattern = '^' . preg_quote(rtrim($basePath, '/') . '/modules/', '#') . '[a-z][a-z0-9_]{1,39}/assets/';
    $ckeditorJs = rtrim($basePath, '/') . '/modules/ckeditor/vendor/ckeditor5/ckeditor5.umd.js';
    $ckeditorCss = rtrim($basePath, '/') . '/modules/ckeditor/vendor/ckeditor5/ckeditor5.css';

    return '"use strict";' . "\n"
        . 'const SR_PWA_CACHE = "saanraan-static-v1";' . "\n"
        . 'const SR_ASSET_PREFIX = ' . sr_js_json_encode($assetPrefix) . ';' . "\n"
        . 'const SR_MODULE_ASSET_RE = new RegExp(' . sr_js_json_encode($moduleAssetPattern) . ');' . "\n"
        . 'const SR_CKEDITOR_ASSETS = new Set([' . sr_js_json_encode($ckeditorJs) . ',' . sr_js_json_encode($ckeditorCss) . ']);' . "\n"
        . 'self.addEventListener("install", function(event) { self.skipWaiting(); });' . "\n"
        . 'self.addEventListener("activate", function(event) { event.waitUntil(self.clients.claim()); });' . "\n"
        . 'function srShouldCache(request) {' . "\n"
        . '  if (request.method !== "GET" || request.mode === "navigate") { return false; }' . "\n"
        . '  const url = new URL(request.url);' . "\n"
        . '  if (url.origin !== self.location.origin) { return false; }' . "\n"
        . '  if (url.pathname.indexOf("/admin") === 0 || url.pathname.indexOf("/account/privacy") === 0 || url.pathname.indexOf("/storage") === 0) { return false; }' . "\n"
        . '  return url.pathname.indexOf(SR_ASSET_PREFIX) === 0 || SR_MODULE_ASSET_RE.test(url.pathname) || SR_CKEDITOR_ASSETS.has(url.pathname);' . "\n"
        . '}' . "\n"
        . 'self.addEventListener("fetch", function(event) {' . "\n"
        . '  if (!srShouldCache(event.request)) { return; }' . "\n"
        . '  event.respondWith(caches.match(event.request).then(function(cached) {' . "\n"
        . '    return fetch(event.request).then(function(response) {' . "\n"
        . '      const copy = response.clone();' . "\n"
        . '      if (response.ok && response.type === "basic") {' . "\n"
        . '        caches.open(SR_PWA_CACHE).then(function(cache) { cache.put(event.request, copy); });' . "\n"
        . '      }' . "\n"
        . '      return response;' . "\n"
        . '    }).catch(function() {' . "\n"
        . '      return cached || Response.error();' . "\n"
        . '    });' . "\n"
        . '  }));' . "\n"
        . '});' . "\n";
}
