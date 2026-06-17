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
    $inner = substr($block, 1, -1);
    if (!is_string($inner)) {
        return [];
    }

    foreach (sr_php_split_top_level_array_segments($inner) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        if (preg_match('/\A(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")\z/s', $segment) !== 1) {
            return [];
        }

        $values[] = sr_php_decode_quoted_string($segment);
    }

    return array_values(array_unique($values));
}

function sr_php_return_array_block(string $content): string
{
    if (!sr_php_starts_with_return_array($content)) {
        return '';
    }

    $tokens = token_get_all($content);
    $count = count($tokens);
    $index = 0;
    $offset = 0;
    while ($index < $count) {
        $token = $tokens[$index];
        if (is_array($token) && $token[0] === T_RETURN) {
            break;
        }

        $offset += strlen(sr_php_token_text($token));
        $index++;
    }

    if ($index >= $count) {
        return '';
    }

    $offset += strlen(sr_php_token_text($tokens[$index]));
    $index++;
    sr_php_skip_ignored_tokens_with_offset($tokens, $index, $offset);
    $token = $tokens[$index] ?? null;
    if ($token === '[') {
        return sr_php_balanced_block($content, $offset, '[', ']');
    }

    if (!is_array($token) || $token[0] !== T_ARRAY) {
        return '';
    }

    $offset += strlen(sr_php_token_text($token));
    $index++;
    sr_php_skip_ignored_tokens_with_offset($tokens, $index, $offset);
    if (($tokens[$index] ?? null) !== '(') {
        return '';
    }

    return sr_php_balanced_block($content, $offset, '(', ')');
}

function sr_php_top_level_array_entries(string $content): ?array
{
    $block = sr_php_return_array_block($content);
    if ($block === '') {
        return null;
    }

    $inner = substr($block, 1, -1);
    if (!is_string($inner)) {
        return null;
    }

    $entries = [];
    foreach (sr_php_split_top_level_array_segments($inner) as $segment) {
        $segment = trim($segment);
        if ($segment === '') {
            continue;
        }

        if (preg_match('/\A((?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"))\s*=>\s*(.+)\z/s', $segment, $matches) !== 1) {
            return null;
        }

        $key = sr_php_decode_quoted_string((string) $matches[1]);
        if ($key === '') {
            return null;
        }

        $entries[$key] = trim((string) $matches[2]);
    }

    return $entries;
}

function sr_php_split_top_level_array_segments(string $content): array
{
    $segments = [];
    $start = 0;
    $length = strlen($content);
    $depth = 0;
    $quote = '';
    $lineComment = false;
    $blockComment = false;
    for ($i = 0; $i < $length; $i++) {
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

        if ($char === '[' || $char === '(' || $char === '{') {
            $depth++;
            continue;
        }

        if ($char === ']' || $char === ')' || $char === '}') {
            $depth--;
            continue;
        }

        if ($char === ',' && $depth === 0) {
            $segments[] = substr($content, $start, $i - $start);
            $start = $i + 1;
        }
    }

    $segments[] = substr($content, $start);
    return $segments;
}

function sr_php_top_level_string_value(array $entries, string $key): string
{
    $value = trim((string) ($entries[$key] ?? ''));
    if (preg_match('/\A(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")\z/s', $value) !== 1) {
        return '';
    }

    return sr_php_decode_quoted_string($value);
}

function sr_php_skip_ignored_tokens_with_offset(array $tokens, int &$index, int &$offset): void
{
    while (isset($tokens[$index]) && sr_php_token_is_ignored($tokens[$index])) {
        $offset += strlen(sr_php_token_text($tokens[$index]));
        $index++;
    }
}

function sr_php_decode_quoted_string(string $value): string
{
    $quote = substr($value, 0, 1);
    if (($quote !== '\'' && $quote !== '"') || substr($value, -1) !== $quote) {
        return '';
    }

    return stripcslashes(substr($value, 1, -1));
}

function sr_php_return_string_map(string $content): ?array
{
    if (!sr_php_starts_with_return_array($content)) {
        return null;
    }

    $tokens = token_get_all($content);
    $count = count($tokens);
    $index = 0;
    while ($index < $count) {
        $token = $tokens[$index];
        if (is_array($token) && $token[0] === T_RETURN) {
            break;
        }

        $index++;
    }

    if ($index >= $count) {
        return null;
    }

    $index++;
    sr_php_skip_ignored_tokens($tokens, $index);
    $openToken = $tokens[$index] ?? null;
    $closeToken = null;
    if ($openToken === '[') {
        $closeToken = ']';
    } elseif (is_array($openToken) && $openToken[0] === T_ARRAY) {
        $index++;
        sr_php_skip_ignored_tokens($tokens, $index);
        if (($tokens[$index] ?? null) !== '(') {
            return null;
        }

        $closeToken = ')';
    } else {
        return null;
    }

    $index++;
    $map = [];
    $state = 'key_or_close';
    $key = '';
    while ($index < $count) {
        sr_php_skip_ignored_tokens($tokens, $index);
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return null;
        }

        if ($token === $closeToken && ($state === 'key_or_close' || $state === 'comma_or_close')) {
            $index++;
            sr_php_skip_ignored_tokens($tokens, $index);
            if (($tokens[$index] ?? null) === ';') {
                $index++;
                sr_php_skip_ignored_tokens($tokens, $index);
            }

            while ($index < $count) {
                $tailToken = $tokens[$index];
                if (is_array($tailToken) && $tailToken[0] === T_CLOSE_TAG) {
                    $index++;
                    continue;
                }

                if (!sr_php_token_is_ignored($tailToken)) {
                    return null;
                }

                $index++;
            }

            return $map;
        }

        if ($state === 'key_or_close') {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                return null;
            }

            $key = sr_php_decode_quoted_string($token[1]);
            $state = 'arrow';
            $index++;
            continue;
        }

        if ($state === 'arrow') {
            if (!is_array($token) || $token[0] !== T_DOUBLE_ARROW) {
                return null;
            }

            $state = 'value';
            $index++;
            continue;
        }

        if ($state === 'value') {
            if (!is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                return null;
            }

            $map[$key] = sr_php_decode_quoted_string($token[1]);
            $key = '';
            $state = 'comma_or_close';
            $index++;
            continue;
        }

        if ($state === 'comma_or_close') {
            if ($token !== ',') {
                return null;
            }

            $state = 'key_or_close';
            $index++;
            continue;
        }
    }

    return null;
}

function sr_php_starts_with_return_array(string $content): bool
{
    $tokens = token_get_all($content);
    $count = count($tokens);
    $index = 0;

    while ($index < $count) {
        $token = $tokens[$index];
        if (is_array($token) && $token[0] === T_OPEN_TAG) {
            $index++;
            continue;
        }

        if (sr_php_token_is_ignored($token)) {
            $index++;
            continue;
        }

        break;
    }

    $token = $tokens[$index] ?? null;
    if (!is_array($token) || $token[0] !== T_RETURN) {
        return false;
    }

    $index++;
    sr_php_skip_ignored_tokens($tokens, $index);
    $token = $tokens[$index] ?? null;
    if ($token === '[') {
        return true;
    }

    if (!is_array($token) || $token[0] !== T_ARRAY) {
        return false;
    }

    $index++;
    sr_php_skip_ignored_tokens($tokens, $index);
    return ($tokens[$index] ?? null) === '(';
}

function sr_php_skip_ignored_tokens(array $tokens, int &$index): void
{
    while (isset($tokens[$index]) && sr_php_token_is_ignored($tokens[$index])) {
        $index++;
    }
}

function sr_php_token_is_ignored(mixed $token): bool
{
    return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
}

function sr_php_token_text(mixed $token): string
{
    return is_array($token) ? (string) $token[1] : (string) $token;
}

function sr_php_saanraan_metadata(string $content): array
{
    $entries = sr_php_top_level_array_entries($content);
    $block = is_array($entries) ? trim((string) ($entries['saanraan'] ?? '')) : '';
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

function sr_php_contracts_metadata(string $content): array
{
    $entries = sr_php_top_level_array_entries($content);
    $block = is_array($entries) ? trim((string) ($entries['contracts'] ?? '')) : '';
    if ($block === '') {
        return [];
    }

    $contracts = [];
    foreach (['provides', 'consumes'] as $key) {
        $values = sr_php_string_list_array_value($block, $key);
        if ($values !== []) {
            $contracts[$key] = $values;
        }
    }

    return $contracts;
}

function sr_load_module_metadata_from_file(string $file): array
{
    if (!is_file($file)) {
        return [];
    }

    $content = file_get_contents($file);
    if (!is_string($content) || !sr_php_starts_with_return_array($content)) {
        return [];
    }

    $entries = sr_php_top_level_array_entries($content);
    if (!is_array($entries)) {
        return [];
    }

    $metadata = [];
    foreach (['name', 'version', 'type', 'description'] as $key) {
        $value = sr_php_top_level_string_value($entries, $key);
        if ($value !== '') {
            $metadata[$key] = $value;
        }
    }

    $saanraan = sr_php_saanraan_metadata($content);
    if ($saanraan !== []) {
        $metadata['saanraan'] = $saanraan;
    }

    $contracts = sr_php_contracts_metadata($content);
    if ($contracts !== []) {
        $metadata['contracts'] = $contracts;
    }

    return $metadata;
}
