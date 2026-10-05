<?php
namespace Goletter\Utils;

use Hyperf\Context\Context;
use RuntimeException;

/**
 * 当前请求/协程的租户上下文（共享库 + tenant_id）。
 */
final class TenantContext
{
    public const CONTEXT_KEY = 'tenant_id';

    public static function set(int|string|null $tenantId): void
    {
        if ($tenantId === null || $tenantId === '') {
            Context::set(self::CONTEXT_KEY, null);
            return;
        }

        Context::set(self::CONTEXT_KEY, (int) $tenantId);
    }

    public static function id(): ?int
    {
        $id = Context::get(self::CONTEXT_KEY);
        return $id === null ? null : (int) $id;
    }

    public static function idOrFail(): int
    {
        $id = self::id();
        if ($id === null || $id <= 0) {
            throw new RuntimeException('Tenant context is missing.');
        }

        return $id;
    }

    public static function clear(): void
    {
        Context::set(self::CONTEXT_KEY, null);
    }

    /**
     * 在指定租户上下文中执行（Job / 命令行常用）。
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(int $tenantId, callable $callback): mixed
    {
        $previous = self::id();
        self::set($tenantId);
        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
