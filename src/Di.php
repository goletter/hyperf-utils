<?php

declare(strict_types=1);

namespace Goletter\Utils;

use Psr\Container\ContainerInterface;

/**
 * 容器代理。get() 不声明 PSR 的 @throws，避免 IDE 把 di()->get() 标成未处理异常。
 * 运行时仍调用底层容器，异常行为不变。
 *
 * @method mixed make(string $name, array $parameters = [])
 * @method void set(string $name, mixed $entry)
 * @method void define(string $name, mixed $definition)
 */
final class Di
{
    public function __construct(private ContainerInterface $container)
    {
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id, mixed ...$parameters): mixed
    {
        /** @var callable $getter */
        $getter = [$this->container, 'get'];

        return $getter($id, ...$parameters);
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function __call(string $name, array $arguments): mixed
    {
        return $this->container->{$name}(...$arguments);
    }
}
