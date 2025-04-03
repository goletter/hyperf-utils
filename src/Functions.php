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
use Hyperf\Redis\Redis;
use Hyperf\Server\ServerFactory;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Swoole\WebSocket\Server as WebSocketServer;
use Ip2Region;

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
 * WebSocketServer 实例.
 *
 * @return mixed|WebSocketServer
 */
function websocket()
{
    return di()->get(WebSocketServer::class);
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

function app($className)
{
    return di()->get($className);
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
