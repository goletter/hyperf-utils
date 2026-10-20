<?php

declare(strict_types=1);
namespace Goletter\Utils;

/**
 * Hash 密码加密辅助类.
 */
final class HashHelper
{
    /**
     * Hash the given value.
     */
    public static function make(string $value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * Check the given plain value against a hash.
     */
    public static function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }
}
