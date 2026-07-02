<?php

declare(strict_types=1);

function sr_member_login(PDO $pdo, array $account): bool
{
    sr_member_cleanup_sessions($pdo);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    sr_member_mfa_clear_challenge();
    $_SESSION['sr_account_id'] = (int) $account['id'];
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));
    $sessionTokenHash = sr_member_create_session($pdo, (int) $account['id']);
    if ($sessionTokenHash !== '') {
        $_SESSION['sr_session_token_hash'] = $sessionTokenHash;
    } else {
        unset($_SESSION['sr_session_token_hash']);
        unset($_SESSION['sr_account_id']);
        if (sr_member_sessions_table_exists($pdo)) {
            return false;
        }
    }

    $stmt = $pdo->prepare('UPDATE sr_member_accounts SET last_login_at = :last_login_at, updated_at = :updated_at WHERE id = :id');
    $stmt->execute([
        'last_login_at' => sr_now(),
        'updated_at' => sr_now(),
        'id' => (int) $account['id'],
    ]);

    return true;
}

function sr_member_login_or_start_mfa(PDO $pdo, array $account, string $primaryMethod, string $nextPath, array $context = []): string
{
    if (sr_member_mfa_login_required($pdo, $account)) {
        sr_member_mfa_start_challenge($account, $primaryMethod, $nextPath, $context);
        return 'mfa_required';
    }

    return sr_member_login($pdo, $account) ? 'logged_in' : 'session_failed';
}

function sr_member_mfa_login_required(PDO $pdo, array $account): bool
{
    return sr_member_mfa_active_factor_exists($pdo, (int) ($account['id'] ?? 0));
}

function sr_member_mfa_active_factor_exists(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);

    return (int) $stmt->fetchColumn() > 0;
}

function sr_member_mfa_active_totp_factor(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, factor_type, status, issuer, label, last_used_step, activated_at, created_at, updated_at
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);
    $factor = $stmt->fetch();

    return is_array($factor) ? $factor : null;
}

function sr_member_mfa_pending_totp_factor(PDO $pdo, int $accountId): ?array
{
    if ($accountId < 1) {
        return null;
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, factor_type, status, issuer, label, created_at, updated_at
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute(['account_id' => $accountId]);
    $factor = $stmt->fetch();

    return is_array($factor) ? $factor : null;
}

function sr_member_mfa_totp_secret_purpose(): string
{
    return 'member.mfa.totp';
}

function sr_member_mfa_totp_period_seconds(): int
{
    return 30;
}

function sr_member_mfa_totp_digits(): int
{
    return 6;
}

function sr_member_mfa_totp_window_steps(): int
{
    return 1;
}

function sr_member_mfa_normalize_code(string $code): string
{
    return preg_replace('/[\s-]+/', '', trim($code)) ?? '';
}

function sr_member_mfa_code_is_valid_format(string $code): bool
{
    return preg_match('/\A[0-9]{6,12}\z/', $code) === 1;
}

function sr_member_mfa_recovery_code_count(): int
{
    return 10;
}

function sr_member_mfa_base32_encode(string $value): string
{
    if ($value === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $buffer = 0;
    $bits = 0;
    $encoded = '';
    $length = strlen($value);
    for ($index = 0; $index < $length; $index++) {
        $buffer = ($buffer << 8) | ord($value[$index]);
        $bits += 8;
        while ($bits >= 5) {
            $encoded .= $alphabet[($buffer >> ($bits - 5)) & 31];
            $bits -= 5;
        }
    }

    if ($bits > 0) {
        $encoded .= $alphabet[($buffer << (5 - $bits)) & 31];
    }

    return $encoded;
}

function sr_member_mfa_base32_decode(string $value): ?string
{
    $clean = strtoupper(preg_replace('/[\s-]+/', '', trim($value)) ?? '');
    $clean = rtrim($clean, '=');
    if ($clean === '') {
        return '';
    }

    $alphabet = array_flip(str_split('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'));
    $buffer = 0;
    $bits = 0;
    $decoded = '';
    $length = strlen($clean);
    for ($index = 0; $index < $length; $index++) {
        $char = $clean[$index];
        if (!isset($alphabet[$char])) {
            return null;
        }
        $buffer = ($buffer << 5) | (int) $alphabet[$char];
        $bits += 5;
        if ($bits >= 8) {
            $decoded .= chr(($buffer >> ($bits - 8)) & 255);
            $bits -= 8;
        }
    }

    return $decoded;
}

function sr_member_mfa_recovery_code_normalize(string $code): string
{
    return strtoupper(preg_replace('/[\s-]+/', '', trim($code)) ?? '');
}

function sr_member_mfa_recovery_code_is_valid_format(string $code): bool
{
    return preg_match('/\A[A-Z2-7]{10,32}\z/', $code) === 1;
}

function sr_member_mfa_recovery_code_format(string $code): string
{
    $normalized = sr_member_mfa_recovery_code_normalize($code);
    if ($normalized === '') {
        return '';
    }

    return implode('-', str_split($normalized, 4));
}

function sr_member_mfa_generate_recovery_code(): string
{
    return substr(sr_member_mfa_base32_encode(random_bytes(10)), 0, 16);
}

function sr_member_mfa_recovery_code_hash(string $code, ?array $config = null): string
{
    $config = is_array($config) ? $config : sr_runtime_config();
    return sr_hmac_hash('member.mfa.recovery|' . sr_member_mfa_recovery_code_normalize($code), $config);
}

function sr_member_mfa_totp_display_text(string $value, string $fallback, int $maxLength = 80): string
{
    $clean = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '');
    $clean = str_replace(':', ' ', $clean);
    $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
    if ($clean === '') {
        $clean = $fallback;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($clean) > $maxLength ? mb_substr($clean, 0, $maxLength) : $clean;
    }

    return strlen($clean) > $maxLength ? substr($clean, 0, $maxLength) : $clean;
}

function sr_member_mfa_totp_otpauth_uri(string $issuer, string $label, string $secretBase32): string
{
    $issuer = sr_member_mfa_totp_display_text($issuer, 'Saanraan', 64);
    $label = sr_member_mfa_totp_display_text($label, 'member', 120);
    $path = rawurlencode($issuer . ':' . $label);
    $query = http_build_query([
        'secret' => $secretBase32,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => sr_member_mfa_totp_digits(),
        'period' => sr_member_mfa_totp_period_seconds(),
    ], '', '&', PHP_QUERY_RFC3986);

    return 'otpauth://totp/' . $path . '?' . $query;
}

function sr_member_mfa_totp_qr_svg_data_uri(string $otpauthUri): string
{
    $svg = sr_member_mfa_qr_svg($otpauthUri);
    if ($svg === '') {
        return '';
    }

    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}

function sr_member_mfa_qr_svg(string $data): string
{
    $version = 10;
    $size = $version * 4 + 17;
    $eccCodewordsPerBlock = 18;
    $numBlocks = 4;
    $rawCodewords = 346;
    $dataCodewords = $rawCodewords - ($eccCodewordsPerBlock * $numBlocks);
    $dataLength = strlen($data);

    if ($dataLength < 1 || $dataLength > $dataCodewords - 3) {
        return '';
    }

    $bits = [];
    sr_member_mfa_qr_append_bits($bits, 0x4, 4);
    sr_member_mfa_qr_append_bits($bits, $dataLength, 16);
    for ($i = 0; $i < $dataLength; $i++) {
        sr_member_mfa_qr_append_bits($bits, ord($data[$i]), 8);
    }

    $capacityBits = $dataCodewords * 8;
    sr_member_mfa_qr_append_bits($bits, 0, min(4, $capacityBits - count($bits)));
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }

    $dataBytes = [];
    for ($i = 0, $count = count($bits); $i < $count; $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | (int) $bits[$i + $j];
        }
        $dataBytes[] = $byte;
    }

    for ($pad = 0xec; count($dataBytes) < $dataCodewords; $pad ^= 0xfd) {
        $dataBytes[] = $pad;
    }

    $codewords = sr_member_mfa_qr_add_error_correction($dataBytes, $rawCodewords, $eccCodewordsPerBlock, $numBlocks);
    $modules = sr_member_mfa_qr_blank_matrix($size);
    $functionModules = sr_member_mfa_qr_blank_matrix($size);
    sr_member_mfa_qr_draw_function_patterns($modules, $functionModules, $version);
    sr_member_mfa_qr_draw_codewords($modules, $functionModules, $codewords);
    sr_member_mfa_qr_apply_mask($modules, $functionModules, 0);
    sr_member_mfa_qr_draw_format_bits($modules, $functionModules, 0);

    $border = 4;
    $dimension = $size + ($border * 2);
    $path = '';
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!empty($modules[$y][$x])) {
                $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
            }
        }
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dimension . ' ' . $dimension . '" role="img" aria-label="TOTP QR code" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path fill="#000" d="' . $path . '"/></svg>';
}

function sr_member_mfa_qr_blank_matrix(int $size): array
{
    $matrix = [];
    for ($y = 0; $y < $size; $y++) {
        $matrix[$y] = array_fill(0, $size, false);
    }

    return $matrix;
}

function sr_member_mfa_qr_append_bits(array &$bits, int $value, int $length): void
{
    for ($i = $length - 1; $i >= 0; $i--) {
        $bits[] = (($value >> $i) & 1) !== 0;
    }
}

function sr_member_mfa_qr_add_error_correction(array $dataBytes, int $rawCodewords, int $eccCodewordsPerBlock, int $numBlocks): array
{
    $shortBlockTotalLength = intdiv($rawCodewords, $numBlocks);
    $numShortBlocks = $numBlocks - ($rawCodewords % $numBlocks);
    $shortDataLength = $shortBlockTotalLength - $eccCodewordsPerBlock;
    $generator = sr_member_mfa_qr_reed_solomon_generator($eccCodewordsPerBlock);
    $blocks = [];
    $offset = 0;

    for ($i = 0; $i < $numBlocks; $i++) {
        $dataLength = $shortDataLength + ($i < $numShortBlocks ? 0 : 1);
        $data = array_slice($dataBytes, $offset, $dataLength);
        $offset += $dataLength;
        $blocks[] = [
            'data' => $data,
            'ecc' => sr_member_mfa_qr_reed_solomon_remainder($data, $generator),
        ];
    }

    $result = [];
    $maxDataLength = $shortDataLength + 1;
    for ($i = 0; $i < $maxDataLength; $i++) {
        foreach ($blocks as $block) {
            if (isset($block['data'][$i])) {
                $result[] = (int) $block['data'][$i];
            }
        }
    }
    for ($i = 0; $i < $eccCodewordsPerBlock; $i++) {
        foreach ($blocks as $block) {
            $result[] = (int) $block['ecc'][$i];
        }
    }

    return $result;
}

function sr_member_mfa_qr_reed_solomon_generator(int $degree): array
{
    $poly = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($poly) + 1, 0);
        $root = sr_member_mfa_qr_gf_pow($i);
        foreach ($poly as $j => $coefficient) {
            $next[$j] ^= sr_member_mfa_qr_gf_multiply((int) $coefficient, 1);
            $next[$j + 1] ^= sr_member_mfa_qr_gf_multiply((int) $coefficient, $root);
        }
        $poly = $next;
    }

    return array_slice($poly, 1);
}

function sr_member_mfa_qr_reed_solomon_remainder(array $data, array $generator): array
{
    $result = array_fill(0, count($generator), 0);
    foreach ($data as $byte) {
        $factor = ((int) $byte) ^ array_shift($result);
        $result[] = 0;
        foreach ($generator as $i => $coefficient) {
            $result[$i] ^= sr_member_mfa_qr_gf_multiply((int) $coefficient, $factor);
        }
    }

    return $result;
}

function sr_member_mfa_qr_gf_pow(int $exponent): int
{
    $result = 1;
    for ($i = 0; $i < $exponent; $i++) {
        $result = sr_member_mfa_qr_gf_multiply($result, 2);
    }

    return $result;
}

function sr_member_mfa_qr_gf_multiply(int $x, int $y): int
{
    $result = 0;
    while ($y > 0) {
        if (($y & 1) !== 0) {
            $result ^= $x;
        }
        $x <<= 1;
        if (($x & 0x100) !== 0) {
            $x ^= 0x11d;
        }
        $y >>= 1;
    }

    return $result & 0xff;
}

function sr_member_mfa_qr_draw_function_patterns(array &$modules, array &$functionModules, int $version): void
{
    $size = count($modules);
    sr_member_mfa_qr_draw_finder_pattern($modules, $functionModules, 3, 3);
    sr_member_mfa_qr_draw_finder_pattern($modules, $functionModules, $size - 4, 3);
    sr_member_mfa_qr_draw_finder_pattern($modules, $functionModules, 3, $size - 4);

    $alignmentPositions = [6, 28, 50];
    foreach ($alignmentPositions as $x) {
        foreach ($alignmentPositions as $y) {
            if (($x === 6 && $y === 6) || ($x === 6 && $y === $size - 7) || ($x === $size - 7 && $y === 6)) {
                continue;
            }
            sr_member_mfa_qr_draw_alignment_pattern($modules, $functionModules, $x, $y);
        }
    }

    for ($i = 8; $i < $size - 8; $i++) {
        $dark = $i % 2 === 0;
        sr_member_mfa_qr_set_function_module($modules, $functionModules, 6, $i, $dark);
        sr_member_mfa_qr_set_function_module($modules, $functionModules, $i, 6, $dark);
    }

    sr_member_mfa_qr_draw_format_bits($modules, $functionModules, 0);
    sr_member_mfa_qr_draw_version_bits($modules, $functionModules, $version);
    sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, 4 * $version + 9, true);
}

function sr_member_mfa_qr_draw_finder_pattern(array &$modules, array &$functionModules, int $centerX, int $centerY): void
{
    for ($dy = -4; $dy <= 4; $dy++) {
        for ($dx = -4; $dx <= 4; $dx++) {
            $distance = max(abs($dx), abs($dy));
            $dark = $distance !== 2 && $distance <= 3;
            sr_member_mfa_qr_set_function_module($modules, $functionModules, $centerX + $dx, $centerY + $dy, $dark);
        }
    }
}

function sr_member_mfa_qr_draw_alignment_pattern(array &$modules, array &$functionModules, int $centerX, int $centerY): void
{
    for ($dy = -2; $dy <= 2; $dy++) {
        for ($dx = -2; $dx <= 2; $dx++) {
            $dark = max(abs($dx), abs($dy)) === 2 || ($dx === 0 && $dy === 0);
            sr_member_mfa_qr_set_function_module($modules, $functionModules, $centerX + $dx, $centerY + $dy, $dark);
        }
    }
}

function sr_member_mfa_qr_draw_format_bits(array &$modules, array &$functionModules, int $mask): void
{
    $bits = sr_member_mfa_qr_format_bits($mask);
    $size = count($modules);

    for ($i = 0; $i <= 5; $i++) {
        sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, $i, sr_member_mfa_qr_bit($bits, $i));
    }
    sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, 7, sr_member_mfa_qr_bit($bits, 6));
    sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, 8, sr_member_mfa_qr_bit($bits, 7));
    sr_member_mfa_qr_set_function_module($modules, $functionModules, 7, 8, sr_member_mfa_qr_bit($bits, 8));
    for ($i = 9; $i < 15; $i++) {
        sr_member_mfa_qr_set_function_module($modules, $functionModules, 14 - $i, 8, sr_member_mfa_qr_bit($bits, $i));
    }
    for ($i = 0; $i < 8; $i++) {
        sr_member_mfa_qr_set_function_module($modules, $functionModules, $size - 1 - $i, 8, sr_member_mfa_qr_bit($bits, $i));
    }
    for ($i = 8; $i < 15; $i++) {
        sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, $size - 15 + $i, sr_member_mfa_qr_bit($bits, $i));
    }
    sr_member_mfa_qr_set_function_module($modules, $functionModules, 8, $size - 8, true);
}

function sr_member_mfa_qr_format_bits(int $mask): int
{
    $data = (1 << 3) | $mask;
    $remainder = $data;
    for ($i = 0; $i < 10; $i++) {
        $remainder = ($remainder << 1) ^ ((($remainder >> 9) & 1) !== 0 ? 0x537 : 0);
    }

    return (($data << 10) | ($remainder & 0x3ff)) ^ 0x5412;
}

function sr_member_mfa_qr_draw_version_bits(array &$modules, array &$functionModules, int $version): void
{
    $bits = sr_member_mfa_qr_version_bits($version);
    $size = count($modules);
    for ($i = 0; $i < 18; $i++) {
        $bit = sr_member_mfa_qr_bit($bits, $i);
        $a = $size - 11 + ($i % 3);
        $b = intdiv($i, 3);
        sr_member_mfa_qr_set_function_module($modules, $functionModules, $a, $b, $bit);
        sr_member_mfa_qr_set_function_module($modules, $functionModules, $b, $a, $bit);
    }
}

function sr_member_mfa_qr_version_bits(int $version): int
{
    $remainder = $version;
    for ($i = 0; $i < 12; $i++) {
        $remainder = ($remainder << 1) ^ ((($remainder >> 11) & 1) !== 0 ? 0x1f25 : 0);
    }

    return ($version << 12) | ($remainder & 0xfff);
}

function sr_member_mfa_qr_draw_codewords(array &$modules, array $functionModules, array $codewords): void
{
    $size = count($modules);
    $bitIndex = 0;
    $bitLength = count($codewords) * 8;
    $upward = true;

    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) {
            $right--;
        }
        for ($vertical = 0; $vertical < $size; $vertical++) {
            $y = $upward ? $size - 1 - $vertical : $vertical;
            for ($j = 0; $j < 2; $j++) {
                $x = $right - $j;
                if (!empty($functionModules[$y][$x])) {
                    continue;
                }
                $dark = false;
                if ($bitIndex < $bitLength) {
                    $dark = (((int) $codewords[intdiv($bitIndex, 8)] >> (7 - ($bitIndex % 8))) & 1) !== 0;
                }
                $modules[$y][$x] = $dark;
                $bitIndex++;
            }
        }
        $upward = !$upward;
    }
}

function sr_member_mfa_qr_apply_mask(array &$modules, array $functionModules, int $mask): void
{
    $size = count($modules);
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            if (!empty($functionModules[$y][$x])) {
                continue;
            }
            if (sr_member_mfa_qr_mask_bit($mask, $x, $y)) {
                $modules[$y][$x] = !$modules[$y][$x];
            }
        }
    }
}

function sr_member_mfa_qr_mask_bit(int $mask, int $x, int $y): bool
{
    if ($mask === 0) {
        return (($x + $y) % 2) === 0;
    }

    return false;
}

function sr_member_mfa_qr_set_function_module(array &$modules, array &$functionModules, int $x, int $y, bool $dark): void
{
    $size = count($modules);
    if ($x < 0 || $y < 0 || $x >= $size || $y >= $size) {
        return;
    }
    $modules[$y][$x] = $dark;
    $functionModules[$y][$x] = true;
}

function sr_member_mfa_qr_bit(int $value, int $index): bool
{
    return (($value >> $index) & 1) !== 0;
}

function sr_member_mfa_create_pending_totp_factor(PDO $pdo, int $accountId, string $issuer, string $label, ?array $config = null): array
{
    if ($accountId < 1) {
        return [
            'created' => false,
            'reason' => 'invalid_account',
        ];
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return [
            'created' => false,
            'reason' => 'storage_unavailable',
        ];
    }

    if (sr_member_mfa_active_factor_exists($pdo, $accountId)) {
        return [
            'created' => false,
            'reason' => 'active_exists',
        ];
    }

    $issuer = sr_member_mfa_totp_display_text($issuer, 'Saanraan', 64);
    $label = sr_member_mfa_totp_display_text($label, 'member' . $accountId, 120);
    $secret = random_bytes(20);
    $secretBase32 = sr_member_mfa_base32_encode($secret);
    $now = sr_now();
    $secretCiphertext = sr_member_mfa_totp_secret_ciphertext($secret, $config);
    $secretFingerprint = sr_member_mfa_totp_secret_fingerprint($secret, $config);

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM sr_member_mfa_factors
             WHERE account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'"
        );
        $stmt->execute(['account_id' => $accountId]);

        $stmt = $pdo->prepare(
            "INSERT INTO sr_member_mfa_factors (
                account_id, factor_type, status, secret_ciphertext, secret_fingerprint,
                issuer, label, last_used_step, activated_at, disabled_at, created_at, updated_at
             ) VALUES (
                :account_id, 'totp', 'pending', :secret_ciphertext, :secret_fingerprint,
                :issuer, :label, NULL, NULL, NULL, :created_at, :updated_at
             )"
        );
        $stmt->execute([
            'account_id' => $accountId,
            'secret_ciphertext' => $secretCiphertext,
            'secret_fingerprint' => $secretFingerprint,
            'issuer' => $issuer,
            'label' => $label,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $factorId = (int) $pdo->lastInsertId();

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    $otpauthUri = sr_member_mfa_totp_otpauth_uri($issuer, $label, $secretBase32);

    return [
        'created' => true,
        'reason' => '',
        'factor_id' => $factorId,
        'issuer' => $issuer,
        'label' => $label,
        'secret_base32' => $secretBase32,
        'otpauth_uri' => $otpauthUri,
        'otpauth_qr_svg_data_uri' => sr_member_mfa_totp_qr_svg_data_uri($otpauthUri),
    ];
}

function sr_member_mfa_totp_code(string $secret, ?int $time = null): string
{
    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $step = intdiv(max(0, $time), $period);

    return sr_member_mfa_hotp_code($secret, $step);
}

function sr_member_mfa_hotp_code(string $secret, int $counter): string
{
    $counter = max(0, $counter);
    $high = intdiv($counter, 4294967296);
    $low = $counter % 4294967296;
    $binaryCounter = pack('N2', $high, $low);
    $hash = hash_hmac('sha1', $binaryCounter, $secret, true);
    $offset = ord($hash[19]) & 0x0f;
    $binary =
        ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff);
    $modulus = 10 ** sr_member_mfa_totp_digits();

    return str_pad((string) ($binary % $modulus), sr_member_mfa_totp_digits(), '0', STR_PAD_LEFT);
}

function sr_member_mfa_totp_secret_ciphertext(string $secret, ?array $config = null): string
{
    return sr_secret_at_rest_encrypt($secret, sr_member_mfa_totp_secret_purpose(), $config);
}

function sr_member_mfa_totp_secret_fingerprint(string $secret, ?array $config = null): string
{
    return sr_secret_at_rest_fingerprint($secret, sr_member_mfa_totp_secret_purpose(), $config);
}

function sr_member_mfa_activate_pending_totp_factor(PDO $pdo, int $accountId, int $factorId, string $code, ?int $time = null, ?array $config = null): array
{
    $code = sr_member_mfa_normalize_code($code);
    if ($accountId < 1 || $factorId < 1 || !sr_member_mfa_code_is_valid_format($code)) {
        return [
            'activated' => false,
            'reason' => 'invalid_code',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return [
            'activated' => false,
            'reason' => 'factor_unavailable',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $currentStep = intdiv(max(0, $time), $period);
    $window = sr_member_mfa_totp_window_steps();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $activeFactor = sr_member_mfa_active_totp_factor($pdo, $accountId);
        if (is_array($activeFactor)) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'active_exists',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $stmt = $pdo->prepare(
            "SELECT id, secret_ciphertext
             FROM sr_member_mfa_factors
             WHERE id = :id
               AND account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'
             LIMIT 1"
        );
        $stmt->execute([
            'id' => $factorId,
            'account_id' => $accountId,
        ]);
        $factor = $stmt->fetch();
        if (!is_array($factor)) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'factor_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        try {
            $secret = sr_secret_at_rest_decrypt(
                (string) ($factor['secret_ciphertext'] ?? ''),
                sr_member_mfa_totp_secret_purpose(),
                $config
            );
        } catch (Throwable $exception) {
            $secret = null;
        }
        if ($secret === null || $secret === '') {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'secret_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $matchedStep = null;
        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            if ($step < 0) {
                continue;
            }
            if (hash_equals(sr_member_mfa_hotp_code($secret, $step), $code)) {
                $matchedStep = $step;
                break;
            }
        }

        if ($matchedStep === null) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'invalid_code',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        $stmt = $pdo->prepare(
            "UPDATE sr_member_mfa_factors
             SET status = 'active',
                 last_used_step = :last_used_step,
                 activated_at = :activated_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND account_id = :account_id
               AND factor_type = 'totp'
               AND status = 'pending'"
        );
        $now = sr_now();
        $stmt->execute([
            'last_used_step' => $matchedStep,
            'activated_at' => $now,
            'updated_at' => $now,
            'id' => $factorId,
            'account_id' => $accountId,
        ]);

        if ($stmt->rowCount() !== 1) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return [
                'activated' => false,
                'reason' => 'factor_unavailable',
                'factor_id' => 0,
                'step' => null,
            ];
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return [
            'activated' => true,
            'reason' => '',
            'factor_id' => $factorId,
            'step' => $matchedStep,
        ];
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_member_mfa_rotate_recovery_codes(PDO $pdo, int $accountId, ?int $factorId = null, int $count = 0, ?array $config = null): array
{
    if ($accountId < 1) {
        return [
            'rotated' => false,
            'reason' => 'invalid_account',
            'codes' => [],
            'batch_uid' => '',
        ];
    }

    if (!sr_member_mfa_recovery_codes_table_exists($pdo)) {
        return [
            'rotated' => false,
            'reason' => 'storage_unavailable',
            'codes' => [],
            'batch_uid' => '',
        ];
    }

    $count = $count > 0 ? min($count, 20) : sr_member_mfa_recovery_code_count();
    $config = is_array($config) ? $config : sr_runtime_config();
    $batchUid = bin2hex(random_bytes(16));
    $now = sr_now();
    $codes = [];
    $hashes = [];
    while (count($codes) < $count) {
        $code = sr_member_mfa_generate_recovery_code();
        $hash = sr_member_mfa_recovery_code_hash($code, $config);
        if (isset($hashes[$hash])) {
            continue;
        }
        $hashes[$hash] = true;
        $codes[] = [
            'code' => $code,
            'formatted' => sr_member_mfa_recovery_code_format($code),
            'hash' => $hash,
        ];
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_member_mfa_recovery_codes
             SET status = 'revoked',
                 revoked_at = :revoked_at
             WHERE account_id = :account_id
               AND status = 'unused'"
        );
        $stmt->execute([
            'revoked_at' => $now,
            'account_id' => $accountId,
        ]);

        $stmt = $pdo->prepare(
            "INSERT INTO sr_member_mfa_recovery_codes (
                account_id, factor_id, code_hash, status, batch_uid, used_at, revoked_at, created_at
             ) VALUES (
                :account_id, :factor_id, :code_hash, 'unused', :batch_uid, NULL, NULL, :created_at
             )"
        );
        foreach ($codes as $codeRow) {
            $stmt->execute([
                'account_id' => $accountId,
                'factor_id' => $factorId !== null && $factorId > 0 ? $factorId : null,
                'code_hash' => (string) $codeRow['hash'],
                'batch_uid' => $batchUid,
                'created_at' => $now,
            ]);
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'rotated' => true,
        'reason' => '',
        'codes' => array_map(static fn (array $codeRow): string => (string) $codeRow['formatted'], $codes),
        'batch_uid' => $batchUid,
    ];
}

function sr_member_mfa_consume_recovery_code(PDO $pdo, int $accountId, string $code, ?array $config = null): array
{
    $normalizedCode = sr_member_mfa_recovery_code_normalize($code);
    if ($accountId < 1 || !sr_member_mfa_recovery_code_is_valid_format($normalizedCode)) {
        return [
            'verified' => false,
            'reason' => 'invalid_code',
            'recovery_code_id' => 0,
            'factor_id' => 0,
            'remaining_unused' => null,
        ];
    }

    if (!sr_member_mfa_recovery_codes_table_exists($pdo)) {
        return [
            'verified' => false,
            'reason' => 'storage_unavailable',
            'recovery_code_id' => 0,
            'factor_id' => 0,
            'remaining_unused' => null,
        ];
    }

    try {
        $codeHash = sr_member_mfa_recovery_code_hash($normalizedCode, $config);
    } catch (Throwable $exception) {
        return [
            'verified' => false,
            'reason' => 'secret_unavailable',
            'recovery_code_id' => 0,
            'factor_id' => 0,
            'remaining_unused' => null,
        ];
    }
    $stmt = $pdo->prepare(
        "SELECT id, factor_id
         FROM sr_member_mfa_recovery_codes
         WHERE account_id = :account_id
           AND code_hash = :code_hash
           AND status = 'unused'
         ORDER BY id ASC
         LIMIT 1"
    );
    $stmt->execute([
        'account_id' => $accountId,
        'code_hash' => $codeHash,
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [
            'verified' => false,
            'reason' => 'invalid_code',
            'recovery_code_id' => 0,
            'factor_id' => 0,
            'remaining_unused' => null,
        ];
    }

    $recoveryCodeId = (int) ($row['id'] ?? 0);
    $stmt = $pdo->prepare(
        "UPDATE sr_member_mfa_recovery_codes
         SET status = 'used',
             used_at = :used_at
         WHERE id = :id
           AND account_id = :account_id
           AND status = 'unused'"
    );
    $stmt->execute([
        'used_at' => sr_now(),
        'id' => $recoveryCodeId,
        'account_id' => $accountId,
    ]);
    if ($stmt->rowCount() !== 1) {
        return [
            'verified' => false,
            'reason' => 'invalid_code',
            'recovery_code_id' => 0,
            'factor_id' => 0,
            'remaining_unused' => null,
        ];
    }

    return [
        'verified' => true,
        'reason' => '',
        'recovery_code_id' => $recoveryCodeId,
        'factor_id' => $row['factor_id'] === null ? 0 : (int) $row['factor_id'],
        'remaining_unused' => sr_member_mfa_unused_recovery_code_count($pdo, $accountId),
    ];
}

function sr_member_mfa_recovery_code_counts(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_member_mfa_recovery_codes_table_exists($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS code_count
         FROM sr_member_mfa_recovery_codes
         WHERE account_id = :account_id
         GROUP BY status
         ORDER BY status ASC"
    );
    $stmt->execute(['account_id' => $accountId]);
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(string) ($row['status'] ?? '')] = (int) ($row['code_count'] ?? 0);
    }

    return $counts;
}

function sr_member_mfa_unused_recovery_code_count(PDO $pdo, int $accountId): int
{
    $counts = sr_member_mfa_recovery_code_counts($pdo, $accountId);
    return (int) ($counts['unused'] ?? 0);
}

function sr_member_mfa_disable(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [
            'disabled' => false,
            'reason' => 'invalid_account',
            'factors_disabled' => 0,
            'recovery_codes_revoked' => 0,
        ];
    }

    $hasFactorTable = sr_member_mfa_factors_table_exists($pdo);
    $hasRecoveryCodeTable = sr_member_mfa_recovery_codes_table_exists($pdo);
    if (!$hasFactorTable && !$hasRecoveryCodeTable) {
        return [
            'disabled' => false,
            'reason' => 'storage_unavailable',
            'factors_disabled' => 0,
            'recovery_codes_revoked' => 0,
        ];
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        $now = sr_now();
        $factorsDisabled = 0;
        if ($hasFactorTable) {
            $stmt = $pdo->prepare(
                "UPDATE sr_member_mfa_factors
                 SET status = 'disabled',
                     disabled_at = :disabled_at,
                     updated_at = :updated_at
                 WHERE account_id = :account_id
                   AND factor_type = 'totp'
                   AND status IN ('active', 'pending')"
            );
            $stmt->execute([
                'disabled_at' => $now,
                'updated_at' => $now,
                'account_id' => $accountId,
            ]);
            $factorsDisabled = $stmt->rowCount();
        }

        $recoveryCodesRevoked = 0;
        if ($hasRecoveryCodeTable) {
            $stmt = $pdo->prepare(
                "UPDATE sr_member_mfa_recovery_codes
                 SET status = 'revoked',
                     revoked_at = :revoked_at
                 WHERE account_id = :account_id
                   AND status = 'unused'"
            );
            $stmt->execute([
                'revoked_at' => $now,
                'account_id' => $accountId,
            ]);
            $recoveryCodesRevoked = $stmt->rowCount();
        }

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'disabled' => $factorsDisabled > 0,
        'reason' => $factorsDisabled > 0 ? '' : 'factor_unavailable',
        'factors_disabled' => $factorsDisabled,
        'recovery_codes_revoked' => $recoveryCodesRevoked,
    ];
}

function sr_member_mfa_management_reauth(PDO $pdo, array $account, string $currentPassword, string $mfaCode, ?array $config = null): array
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return [
            'verified' => false,
            'method' => '',
            'reason' => 'invalid_account',
        ];
    }

    if (trim((string) ($account['password_hash'] ?? '')) !== '') {
        $passwordVerified = password_verify($currentPassword, (string) $account['password_hash']);
        return [
            'verified' => $passwordVerified,
            'method' => 'password',
            'reason' => $passwordVerified ? '' : 'invalid_password',
        ];
    }

    $normalizedCode = sr_member_mfa_normalize_code($mfaCode);
    if (sr_member_mfa_code_is_valid_format($normalizedCode)) {
        $totpResult = sr_member_mfa_verify_totp_code($pdo, $accountId, $normalizedCode, null, $config);
        if (!empty($totpResult['verified'])) {
            return [
                'verified' => true,
                'method' => 'totp',
                'reason' => '',
                'factor_id' => (int) ($totpResult['factor_id'] ?? 0),
            ];
        }
        if ((string) ($totpResult['reason'] ?? '') === 'secret_unavailable') {
            return [
                'verified' => false,
                'method' => 'totp',
                'reason' => 'secret_unavailable',
            ];
        }
    }

    $normalizedRecoveryCode = sr_member_mfa_recovery_code_normalize($mfaCode);
    if (sr_member_mfa_recovery_code_is_valid_format($normalizedRecoveryCode)) {
        $recoveryResult = sr_member_mfa_consume_recovery_code($pdo, $accountId, $normalizedRecoveryCode, $config);
        return [
            'verified' => !empty($recoveryResult['verified']),
            'method' => 'backup',
            'reason' => !empty($recoveryResult['verified']) ? '' : (string) ($recoveryResult['reason'] ?? 'invalid_code'),
            'recovery_code_id' => (int) ($recoveryResult['recovery_code_id'] ?? 0),
            'remaining_unused' => $recoveryResult['remaining_unused'] ?? null,
        ];
    }

    return [
        'verified' => false,
        'method' => 'mfa',
        'reason' => 'invalid_code',
    ];
}

function sr_member_mfa_verify_totp_code(PDO $pdo, int $accountId, string $code, ?int $time = null, ?array $config = null): array
{
    $code = sr_member_mfa_normalize_code($code);
    if ($accountId < 1 || !sr_member_mfa_code_is_valid_format($code)) {
        return [
            'verified' => false,
            'reason' => 'invalid_code',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    if (!sr_member_mfa_factors_table_exists($pdo)) {
        return [
            'verified' => false,
            'reason' => 'factor_unavailable',
            'factor_id' => 0,
            'step' => null,
        ];
    }

    $time = $time ?? time();
    $period = sr_member_mfa_totp_period_seconds();
    $currentStep = intdiv(max(0, $time), $period);
    $window = sr_member_mfa_totp_window_steps();
    $stmt = $pdo->prepare(
        "SELECT id, secret_ciphertext, last_used_step
         FROM sr_member_mfa_factors
         WHERE account_id = :account_id
           AND factor_type = 'totp'
           AND status = 'active'
         ORDER BY id ASC"
    );
    $stmt->execute(['account_id' => $accountId]);

    $matchedReplay = false;
    $secretUnavailable = false;
    foreach ($stmt->fetchAll() as $factor) {
        $factorId = (int) ($factor['id'] ?? 0);
        try {
            $secret = sr_secret_at_rest_decrypt(
                (string) ($factor['secret_ciphertext'] ?? ''),
                sr_member_mfa_totp_secret_purpose(),
                $config
            );
        } catch (Throwable $exception) {
            $secretUnavailable = true;
            $secret = null;
        }
        if ($factorId < 1 || $secret === null || $secret === '') {
            $secretUnavailable = true;
            continue;
        }

        for ($offset = -$window; $offset <= $window; $offset++) {
            $step = $currentStep + $offset;
            if ($step < 0) {
                continue;
            }
            if (!hash_equals(sr_member_mfa_hotp_code($secret, $step), $code)) {
                continue;
            }

            $stmt = $pdo->prepare(
                "UPDATE sr_member_mfa_factors
                 SET last_used_step = :last_used_step,
                     updated_at = :updated_at
                 WHERE id = :id
                   AND account_id = :account_id
                   AND factor_type = 'totp'
                   AND status = 'active'
                   AND (last_used_step IS NULL OR last_used_step < :last_used_step_compare)"
            );
            $stmt->execute([
                'last_used_step' => $step,
                'last_used_step_compare' => $step,
                'updated_at' => sr_now(),
                'id' => $factorId,
                'account_id' => $accountId,
            ]);

            if ($stmt->rowCount() === 1) {
                return [
                    'verified' => true,
                    'reason' => '',
                    'factor_id' => $factorId,
                    'step' => $step,
                ];
            }

            $matchedReplay = true;
        }
    }

    return [
        'verified' => false,
        'reason' => $matchedReplay ? 'replayed_code' : ($secretUnavailable ? 'secret_unavailable' : 'invalid_code'),
        'factor_id' => 0,
        'step' => null,
    ];
}

function sr_member_mfa_privacy_metadata(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [
            'factors' => [],
            'recovery_code_counts' => [],
        ];
    }

    $factors = [];
    if (sr_member_mfa_factors_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT id, factor_type, status, issuer, label, last_used_step, activated_at, disabled_at, created_at, updated_at
             FROM sr_member_mfa_factors
             WHERE account_id = :account_id
             ORDER BY id ASC'
        );
        $stmt->execute(['account_id' => $accountId]);
        foreach ($stmt->fetchAll() as $factor) {
            $factors[] = [
                'id' => (int) ($factor['id'] ?? 0),
                'factor_type' => (string) ($factor['factor_type'] ?? ''),
                'status' => (string) ($factor['status'] ?? ''),
                'issuer' => (string) ($factor['issuer'] ?? ''),
                'label' => (string) ($factor['label'] ?? ''),
                'last_used_step' => $factor['last_used_step'] === null ? null : (int) $factor['last_used_step'],
                'activated_at' => $factor['activated_at'],
                'disabled_at' => $factor['disabled_at'],
                'created_at' => (string) ($factor['created_at'] ?? ''),
                'updated_at' => (string) ($factor['updated_at'] ?? ''),
            ];
        }
    }

    $recoveryCodeCounts = [];
    if (sr_member_mfa_recovery_codes_table_exists($pdo)) {
        $stmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS code_count
             FROM sr_member_mfa_recovery_codes
             WHERE account_id = :account_id
             GROUP BY status
             ORDER BY status ASC'
        );
        $stmt->execute(['account_id' => $accountId]);
        foreach ($stmt->fetchAll() as $row) {
            $recoveryCodeCounts[(string) ($row['status'] ?? '')] = (int) ($row['code_count'] ?? 0);
        }
    }

    return [
        'factors' => $factors,
        'recovery_code_counts' => $recoveryCodeCounts,
    ];
}

function sr_member_delete_mfa(PDO $pdo, int $accountId): array
{
    if ($accountId < 1) {
        return [
            'factors_deleted' => 0,
            'recovery_codes_deleted' => 0,
        ];
    }

    $recoveryCodesDeleted = 0;
    if (sr_member_mfa_recovery_codes_table_exists($pdo)) {
        $stmt = $pdo->prepare('DELETE FROM sr_member_mfa_recovery_codes WHERE account_id = :account_id');
        $stmt->execute(['account_id' => $accountId]);
        $recoveryCodesDeleted = $stmt->rowCount();
    }

    $factorsDeleted = 0;
    if (sr_member_mfa_factors_table_exists($pdo)) {
        $stmt = $pdo->prepare('DELETE FROM sr_member_mfa_factors WHERE account_id = :account_id');
        $stmt->execute(['account_id' => $accountId]);
        $factorsDeleted = $stmt->rowCount();
    }

    return [
        'factors_deleted' => $factorsDeleted,
        'recovery_codes_deleted' => $recoveryCodesDeleted,
    ];
}

function sr_member_mfa_start_challenge(array $account, string $primaryMethod, string $nextPath, array $context = []): void
{
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    unset($_SESSION['sr_account_id'], $_SESSION['sr_session_token_hash']);
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));

    $now = time();
    $_SESSION['sr_member_mfa_challenge'] = [
        'account_id' => $accountId,
        'primary_method' => sr_member_mfa_primary_method($primaryMethod),
        'next_path' => sr_member_safe_next_path($nextPath),
        'created_at' => $now,
        'expires_at' => $now + sr_member_mfa_challenge_ttl_seconds(),
        'context' => sr_member_mfa_challenge_context($context),
    ];
}

function sr_member_mfa_challenge(): ?array
{
    $challenge = $_SESSION['sr_member_mfa_challenge'] ?? null;
    if (!is_array($challenge)) {
        return null;
    }

    $accountId = (int) ($challenge['account_id'] ?? 0);
    $expiresAt = (int) ($challenge['expires_at'] ?? 0);
    if ($accountId < 1 || $expiresAt < time()) {
        sr_member_mfa_clear_challenge();
        return null;
    }

    $challenge['account_id'] = $accountId;
    $challenge['primary_method'] = sr_member_mfa_primary_method((string) ($challenge['primary_method'] ?? ''));
    $challenge['next_path'] = sr_member_safe_next_path((string) ($challenge['next_path'] ?? ''));
    $challenge['created_at'] = (int) ($challenge['created_at'] ?? 0);
    $challenge['expires_at'] = $expiresAt;
    $challenge['context'] = isset($challenge['context']) && is_array($challenge['context'])
        ? sr_member_mfa_challenge_context($challenge['context'])
        : [];

    return $challenge;
}

function sr_member_mfa_clear_challenge(): void
{
    unset($_SESSION['sr_member_mfa_challenge']);
}

function sr_member_mfa_challenge_ttl_seconds(): int
{
    return 300;
}

function sr_member_mfa_primary_method(string $method): string
{
    return in_array($method, ['password', 'register', 'oauth', 'oauth_completion'], true) ? $method : 'password';
}

function sr_member_mfa_challenge_context(array $context): array
{
    $clean = [];
    foreach ($context as $key => $value) {
        if (!is_string($key) || preg_match('/\A[a-z0-9_.:-]{1,80}\z/', $key) !== 1) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $clean[$key] = $value === null ? '' : (string) $value;
        }
    }

    return $clean;
}

function sr_member_create_session(PDO $pdo, int $accountId): string
{
    $sessionTokenHash = hash('sha256', bin2hex(random_bytes(32)));
    $now = sr_now();
    $expiresAt = date('Y-m-d H:i:s', time() + 86400);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_member_sessions
                (account_id, session_token_hash, remember_token_hash, ip_address, user_agent, expires_at, created_at, last_seen_at)
             VALUES
                (:account_id, :session_token_hash, NULL, :ip_address, :user_agent, :expires_at, :created_at, :last_seen_at)'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
            'ip_address' => sr_client_ip(),
            'user_agent' => sr_client_user_agent(),
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'last_seen_at' => $now,
        ]);
    } catch (PDOException $exception) {
        return '';
    }

    return $sessionTokenHash;
}

function sr_member_cleanup_sessions(PDO $pdo, int $revokedRetentionDays = 30): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    $now = sr_now();
    $revokedBefore = date('Y-m-d H:i:s', time() - max(1, $revokedRetentionDays) * 86400);

    try {
        $stmt = $pdo->prepare(
            'DELETE FROM sr_member_sessions
             WHERE expires_at < :now
                OR (revoked_at IS NOT NULL AND revoked_at < :revoked_before)'
        );
        $stmt->execute([
            'now' => $now,
            'revoked_before' => $revokedBefore,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_session_is_current(PDO $pdo, int $accountId): bool
{
    if (random_int(1, 100) === 1) {
        sr_member_cleanup_sessions($pdo);
    }

    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return !sr_member_sessions_table_exists($pdo);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, expires_at, revoked_at, last_seen_at
             FROM sr_member_sessions
             WHERE account_id = :account_id
               AND session_token_hash = :session_token_hash
             LIMIT 1'
        );
        $stmt->execute([
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
        ]);
        $session = $stmt->fetch();
    } catch (PDOException $exception) {
        return false;
    }

    if (!is_array($session) || $session['revoked_at'] !== null || (string) $session['expires_at'] < sr_now()) {
        return false;
    }

    $lastSeenAt = strtotime((string) $session['last_seen_at']);
    if ($lastSeenAt === false || $lastSeenAt <= time() - 300) {
        $stmt = $pdo->prepare('UPDATE sr_member_sessions SET last_seen_at = :last_seen_at WHERE id = :id');
        $stmt->execute([
            'last_seen_at' => sr_now(),
            'id' => (int) $session['id'],
        ]);
    }

    return true;
}

function sr_member_sessions_table_exists(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT 1 FROM sr_member_sessions LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_member_mfa_factors_table_exists(PDO $pdo): bool
{
    try {
        return $pdo->query('SELECT 1 FROM sr_member_mfa_factors LIMIT 1') !== false;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_member_mfa_recovery_codes_table_exists(PDO $pdo): bool
{
    try {
        return $pdo->query('SELECT 1 FROM sr_member_mfa_recovery_codes LIMIT 1') !== false;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_member_revoke_current_session(PDO $pdo): int
{
    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return 0;
    }

    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare('UPDATE sr_member_sessions SET revoked_at = :revoked_at WHERE session_token_hash = :session_token_hash AND revoked_at IS NULL');
        $stmt->execute([
            'revoked_at' => sr_now(),
            'session_token_hash' => $sessionTokenHash,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_revoke_account_sessions(PDO $pdo, int $accountId): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_sessions
             SET revoked_at = :revoked_at
             WHERE account_id = :account_id
               AND revoked_at IS NULL'
        );
        $stmt->execute([
            'revoked_at' => sr_now(),
            'account_id' => $accountId,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_revoke_other_sessions(PDO $pdo, int $accountId): int
{
    if (!sr_member_sessions_table_exists($pdo)) {
        return 0;
    }

    $sessionTokenHash = $_SESSION['sr_session_token_hash'] ?? '';
    if (!is_string($sessionTokenHash) || preg_match('/\A[a-f0-9]{64}\z/', $sessionTokenHash) !== 1) {
        return sr_member_revoke_account_sessions($pdo, $accountId);
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE sr_member_sessions
             SET revoked_at = :revoked_at
             WHERE account_id = :account_id
               AND session_token_hash <> :session_token_hash
               AND revoked_at IS NULL'
        );
        $stmt->execute([
            'revoked_at' => sr_now(),
            'account_id' => $accountId,
            'session_token_hash' => $sessionTokenHash,
        ]);
    } catch (PDOException $exception) {
        return -1;
    }

    return $stmt->rowCount();
}

function sr_member_rotate_current_session(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    if (sr_member_revoke_current_session($pdo) < 0) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['sr_csrf_token'] = bin2hex(random_bytes(32));

    $sessionTokenHash = sr_member_create_session($pdo, $accountId);
    if ($sessionTokenHash === '') {
        unset($_SESSION['sr_session_token_hash']);
        if (!sr_member_sessions_table_exists($pdo)) {
            return true;
        }

        return false;
    }

    $_SESSION['sr_session_token_hash'] = $sessionTokenHash;
    return true;
}

function sr_member_current_session_account_id(): ?int
{
    $accountId = $_SESSION['sr_account_id'] ?? null;
    if (!is_int($accountId) && !ctype_digit((string) $accountId)) {
        return null;
    }

    $accountId = (int) $accountId;
    return $accountId > 0 ? $accountId : null;
}

function sr_member_logout_current_session_if_account(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1 || sr_member_current_session_account_id() !== $accountId) {
        return false;
    }

    return sr_member_logout($pdo);
}

function sr_member_logout(?PDO $pdo = null): bool
{
    $sessionRevoked = true;
    if ($pdo instanceof PDO) {
        $sessionRevoked = sr_member_revoke_current_session($pdo) >= 0;
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string) ($params['path'] ?? '/'),
            'domain' => (string) ($params['domain'] ?? ''),
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }

    session_destroy();
    return $sessionRevoked;
}
