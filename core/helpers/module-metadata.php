<?php

declare(strict_types=1);

function sr_php_string_array_value(string $content, string $key): string
{
    foreach (['\'', '"'] as $quote) {
        $quotedKey = preg_quote($key, '/');
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . $quotedKey . $quotedQuote . '\s*=>\s*' . $quotedQuote . '((?:\\\\.|[^' . $quotedQuote . '\\\\])*)' . $quotedQuote . '/';
        if (preg_match($pattern, $content, $matches) === 1) {
            return stripcslashes((string) $matches[1]);
        }
    }

    return '';
}

function sr_php_array_block(string $content, string $key): string
{
    foreach (['\'', '"'] as $quote) {
        $quotedKey = preg_quote($key, '/');
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . $quotedKey . $quotedQuote . '\s*=>\s*(\[|array\s*\()/i';
        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            continue;
        }

        $token = (string) $matches[1][0];
        $offset = (int) $matches[1][1];
        $openOffset = $offset;
        if ($token !== '[') {
            $parenOffset = strpos($token, '(');
            if ($parenOffset === false) {
                continue;
            }

            $openOffset += $parenOffset;
        }
        $block = sr_php_balanced_block($content, $openOffset, $token === '[' ? '[' : '(', $token === '[' ? ']' : ')');
        if ($block !== '') {
            return $block;
        }
    }

    return '';
}

function sr_php_balanced_block(string $content, int $openOffset, string $openChar, string $closeChar): string
{
    $length = strlen($content);
    if ($openOffset < 0 || $openOffset >= $length || $content[$openOffset] !== $openChar) {
        return '';
    }

    $depth = 0;
    $quote = '';
    $lineComment = false;
    $blockComment = false;
    for ($i = $openOffset; $i < $length; $i++) {
        $char = $content[$i];
        $next = $i + 1 < $length ? $content[$i + 1] : '';

        if ($lineComment) {
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            if ($char === '*' && $next === '/') {
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== '') {
            if ($char === '\\' && $next !== '') {
                $i++;
                continue;
            }

            if ($char === $quote) {
                $quote = '';
            }
            continue;
        }

        if ($char === '\'' || $char === '"') {
            $quote = $char;
            continue;
        }

        if (($char === '/' && $next === '/') || $char === '#') {
            $lineComment = true;
            if ($char === '/') {
                $i++;
            }
            continue;
        }

        if ($char === '/' && $next === '*') {
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === $openChar) {
            $depth++;
            continue;
        }

        if ($char === $closeChar) {
            $depth--;
            if ($depth === 0) {
                return substr($content, $openOffset, $i - $openOffset + 1);
            }
        }
    }

    return '';
}

function sr_php_string_list_array_value(string $content, string $key): array
{
    $block = sr_php_array_block($content, $key);
    if ($block === '') {
        return [];
    }

    $values = [];
    foreach (['\'', '"'] as $quote) {
        $quotedQuote = preg_quote($quote, '/');
        $pattern = '/' . $quotedQuote . '((?:\\\\.|[^' . $quotedQuote . '\\\\])*)' . $quotedQuote . '/';
        if (preg_match_all($pattern, $block, $matches) !== false) {
            foreach ($matches[1] as $value) {
                $values[] = stripcslashes((string) $value);
            }
        }
    }

    return array_values(array_unique($values));
}

function sr_php_saanraan_metadata(string $content): array
{
    $block = sr_php_array_block($content, 'saanraan');
    if ($block === '') {
        return [];
    }

    $saanraan = [];
    foreach (['min_version', 'module_contract'] as $key) {
        $value = sr_php_string_array_value($block, $key);
        if ($value !== '') {
            $saanraan[$key] = $value;
        }
    }

    $testedWith = sr_php_string_list_array_value($block, 'tested_with');
    if ($testedWith !== []) {
        $saanraan['tested_with'] = $testedWith;
    }

    return $saanraan;
}

function sr_load_module_metadata_from_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $content = file_get_contents($file);
    if (!is_string($content) || preg_match('/\breturn\s+(?:\[|array\s*\()/i', $content) !== 1) {
        return [];
    }

    $metadata = [];
    foreach (['name', 'version', 'type', 'description'] as $key) {
        $value = sr_php_string_array_value($content, $key);
        if ($value !== '') {
            $metadata[$key] = $value;
        }
    }

    $saanraan = sr_php_saanraan_metadata($content);
    if ($saanraan !== []) {
        $metadata['saanraan'] = $saanraan;
    }

    return $metadata;
}

