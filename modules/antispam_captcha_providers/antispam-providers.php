<?php

return [
    'hcaptcha' => [
        'label' => 'hCaptcha',
        'site_key_setting' => 'hcaptcha_site_key',
        'secret_key_setting' => 'hcaptcha_secret_key',
        'response_field' => 'h-captcha-response',
        'endpoint' => 'https://hcaptcha.com/siteverify',
        'script_url' => 'https://js.hcaptcha.com/1/api.js',
        'widget_class' => 'h-captcha',
    ],
    'recaptcha' => [
        'label' => 'reCAPTCHA',
        'site_key_setting' => 'recaptcha_site_key',
        'secret_key_setting' => 'recaptcha_secret_key',
        'response_field' => 'g-recaptcha-response',
        'endpoint' => 'https://www.google.com/recaptcha/api/siteverify',
        'script_url' => 'https://www.google.com/recaptcha/api.js',
        'widget_class' => 'g-recaptcha',
        'score_setting' => 'recaptcha_min_score',
    ],
    'turnstile' => [
        'label' => 'Cloudflare Turnstile',
        'site_key_setting' => 'turnstile_site_key',
        'secret_key_setting' => 'turnstile_secret_key',
        'response_field' => 'cf-turnstile-response',
        'endpoint' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'script_url' => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
        'widget_class' => 'cf-turnstile',
    ],
];
