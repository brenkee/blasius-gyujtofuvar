<?php

declare(strict_types=1);

/**
 * Hash a password using a strong algorithm (Argon2id when available).
 */
function auth_password_hash(string $password): string
{
    if (defined('PASSWORD_ARGON2ID')) {
        $options = [
            'memory_cost' => 1 << 16, // 64 MB
            'time_cost' => 4,
            'threads' => 2,
        ];
        return password_hash($password, PASSWORD_ARGON2ID, $options);
    }

    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify a password against a stored hash.
 */
function auth_password_verify(string $password, string $hash): bool
{
    if ($hash === '') {
        return false;
    }

    return password_verify($password, $hash);
}

/**
 * Determine whether a stored hash should be upgraded.
 */
function auth_password_needs_rehash(string $hash): bool
{
    if (defined('PASSWORD_ARGON2ID')) {
        $options = [
            'memory_cost' => 1 << 16,
            'time_cost' => 4,
            'threads' => 2,
        ];
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $options);
    }

    return password_needs_rehash($hash, PASSWORD_DEFAULT);
}
