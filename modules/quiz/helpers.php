<?php

function sr_quiz_key_is_valid(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $key) === 1;
}

function sr_quiz_clean_key(string $value, int $maxLength = 64): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]/', '', $value) ?? '';
    $value = preg_replace('/\A[^a-z]+/', '', $value) ?? '';

    return substr($value, 0, $maxLength);
}

function sr_quiz_internal_return_path(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value[0] !== '/' || str_starts_with($value, '//')) {
        return '';
    }

    if (preg_match('/[\r\n]/', $value) === 1) {
        return '';
    }

    return substr($value, 0, 255);
}

function sr_quiz_public_quizzes(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        "SELECT id, quiz_key, title, description
         FROM sr_quiz_sets
         WHERE status = 'active'
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(100, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_quiz_by_key(PDO $pdo, string $quizKey): ?array
{
    if (!sr_quiz_key_is_valid($quizKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_quiz_sets
         WHERE quiz_key = :quiz_key
         LIMIT 1'
    );
    $stmt->execute(['quiz_key' => $quizKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}
