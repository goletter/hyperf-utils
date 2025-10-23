<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Goletter\Utils;

use Hyperf\Collection\Arr;
use Hyperf\Context\ApplicationContext;
use Hyperf\Context\RequestContext;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Redis\Redis;
use Hyperf\Server\ServerFactory;
use Hyperf\Snowflake\IdGeneratorInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Hyperf\HttpServer\Router\Router;
use Ip2Region;
use Countable;

/**
 * 容器实例.
 */
function di(): ContainerInterface
{
    return ApplicationContext::getContainer();
}

/**
 * Redis 客户端实例.
 *
 * @return mixed|Redis
 */
function redis()
{
    return di()->get(Redis::class);
}

/**
 * Server 实例 基于 Swoole Server.
 */
function server()
{
    return di()->get(ServerFactory::class)->getServer()->getServer();
}

/**
 * 缓存实例 简单的缓存.
 *
 * @return CacheInterface|mixed
 */
function cache()
{
    return di()->get(CacheInterface::class);
}

/**
 * Dispatch an event and call the listeners.
 *
 * @return EventDispatcherInterface|mixed
 */
function event()
{
    return di()->get(EventDispatcherInterface::class);
}

function config()
{
    return di()->get(ConfigInterface::class);
}

/**
 * 控制台日志输出
 * StdoutLogger.
 *
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function stdoutLogger(): StdoutLoggerInterface
{
    return di()->get(StdoutLoggerInterface::class);
}

/**
 * 获取App.
 */
function app($className)
{
    return di()->get($className);
}

/**
 * 过滤数组.
 */
function arrayFilterFilled(array $array): array
{
    return array_filter($array, static fn($item) => !isBlank($item));
}

/**
 * 是否空白.
 */
function isBlank(mixed $value): bool
{
    if (is_null($value)) {
        return true;
    }

    if (is_string($value)) {
        return trim($value) === '';
    }

    if (is_numeric($value) || is_bool($value)) {
        return false;
    }

    if ($value instanceof Countable) {
        return count($value) === 0;
    }

    return empty($value);
}

/**
 * 推送消息到 Redis 订阅中.
 * @param array|string $message
 */
function push_redis_subscribe(string $chan, $message)
{
    redis()->publish($chan, is_string($message) ? $message : json_encode($message));
}

/**
 * 请求
 * @return mixed
 */
function request()
{
    return di()->get(ServerRequestInterface::class);
}

/**
 * 参数解析.
 * @param null|mixed $includes
 * @return array
 */
function parseIncludes($includes = null)
{
    if (is_null($includes)) {
        $includes = Arr::get(request()->getQueryParams(), 'include', '');
    }

    if (! is_array($includes)) {
        $includes = array_filter(explode(',', $includes));
    }

    $parsed = [];
    foreach ($includes as $include) {
        $nested = explode('.', $include);

        $part = array_shift($nested);
        $parsed[] = $part;

        while (count($nested) > 0) {
            $part .= '.' . array_shift($nested);
            $parsed[] = $part;
        }
    }

    return array_values(array_unique($parsed));
}

/**
 * 获取IP
 * @param string $default
 * @return string
 */
function getUserIp(string $default = ''): string
{
    $request = RequestContext::getOrNull();
    if (! $request) {
        return $default;
    }

    $ip = $request->getHeaderLine('x-forwarded-for');
    if (! empty($ip)) {
        $ip = trim(explode(',', $ip)[0] ?? '');
    }

    if (! $ip) {
        $ip = $request->getHeaderLine('x-real-ip');
    }

    return $ip ?: $default;
}

/**
 * 获取真实ip.
 */
function realIp(mixed $request = null): string
{
    $request = $request ?? request();
    /** @var RequestInterface $request */
    return $request->getHeaderLine('X-Forwarded-For')
        ?: $request->getHeaderLine('X-Real-IP')
            ?: ($request->getServerParams()['remote_addr'] ?? '')
                ?: '127.0.0.1';
}

/**
 * 解析Ip获取省市
 * @param $lastIp
 * @return array
 * @throws \Exception
 */
function getRegion($lastIp): array
{
    $province = $city = '';
    if ($lastIp) {
        $ip2region = new Ip2Region();
        $address = $ip2region->simple($lastIp);
        preg_match('/^.{6}(.*?省|自治区|北京|天津|上海|重庆)(.*?市|自治州|地区|区划|县)/', $address, $matches);
        $province = Arr::get($matches, '1', '');
        $city = Arr::get($matches, '2', '');
    }

    return ['province' => $province, 'city' => $city];
}

/**
 * 雪花ID.
 */
function snowflakeId(): int
{
    return app(IdGeneratorInterface::class)->generate();
}

/**
 * restful路由
 */
function apiResource(string $name, string $controller, array $middleware = []) {
    $prefix = '/' . trim($name, '/');

    Router::addGroup($prefix, function () use ($controller) {
        Router::get('', [$controller, 'index']);       // GET /resource
        Router::get('/{id}', [$controller, 'show']);   // GET /resource/{id}
        Router::post('', [$controller, 'store']);      // POST /resource
        Router::put('/{id}', [$controller, 'update']); // PUT /resource/{id}
        Router::delete('/{id}', [$controller, 'destroy']); // DELETE /resource/{id}
    }, ['middleware' => $middleware]);
}
