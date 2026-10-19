<?php
namespace Goletter\Utils;

use DivisionByZeroError;
use InvalidArgumentException;

/**
 * 高精度金额换算助手：分 <-> 元。
 *
 * 全程 string + bcmath，绝不把业务数值转成 float 参与运算。
 * 不用 bcscale()：那是进程级全局状态，协程之间会互相污染，scale 一律显式传参。
 * 所有方法都是纯函数（无静态状态、无 I/O、无挂起），协程下天然安全。
 */
final class FloatHelper
{
    /** 金额默认保留 2 位小数 */
    public const DEFAULT_SCALE = 2;

    /** 中间计算多留的保护位，防止 bcmath 截断累积 */
    private const GUARD = 8;

    /** scale 上限，防止误传导致内存 / 性能问题 */
    private const MAX_SCALE = 100;

    private function __construct()
    {
    }

    /**
     * 精确加法，返回定点小数字符串。
     */
    public static function add(mixed $a, mixed $b, int $scale = self::DEFAULT_SCALE): string
    {
        $scale = self::normalizeScale($scale);

        return self::round(bcadd(self::normalize($a), self::normalize($b), $scale + self::GUARD), $scale);
    }

    /**
     * 精确减法。
     */
    public static function sub(mixed $a, mixed $b, int $scale = self::DEFAULT_SCALE): string
    {
        $scale = self::normalizeScale($scale);

        return self::round(bcsub(self::normalize($a), self::normalize($b), $scale + self::GUARD), $scale);
    }

    /**
     * 精确乘法。
     */
    public static function mul(mixed $a, mixed $b, int $scale = self::DEFAULT_SCALE): string
    {
        $scale = self::normalizeScale($scale);

        return self::round(bcmul(self::normalize($a), self::normalize($b), $scale + self::GUARD), $scale);
    }

    /**
     * 精确除法。
     *
     * @throws DivisionByZeroError 除数为 0
     */
    public static function div(mixed $a, mixed $b, int $scale = self::DEFAULT_SCALE): string
    {
        $scale = self::normalizeScale($scale);
        $dividend = self::normalize($a);
        $divisor = self::normalize($b);

        if (bccomp($divisor, '0', $scale + self::GUARD) === 0) {
            throw new DivisionByZeroError('FloatHelper: division by zero');
        }

        return self::round(bcdiv($dividend, $divisor, $scale + self::GUARD), $scale);
    }

    /**
     * 四舍五入（half up）。负数按绝对值舍入后补回符号；位数不足补 0。
     */
    public static function round(mixed $value, int $scale = self::DEFAULT_SCALE): string
    {
        $scale = self::normalizeScale($scale);
        $number = self::normalize($value);

        $negative = false;
        if (str_starts_with($number, '-')) {
            $negative = true;
            $number = substr($number, 1);
        }

        if (! str_contains($number, '.')) {
            $number .= '.';
        }

        [$intPart, $fracPart] = explode('.', $number, 2);

        $intPart = ltrim($intPart, '0');
        $intPart = $intPart === '' ? '0' : $intPart;

        // 多留一位用于判断进位
        $fracPart = str_pad($fracPart, $scale + 1, '0', STR_PAD_RIGHT);
        $kept = substr($fracPart, 0, $scale);
        $nextDigit = (int) $fracPart[$scale];

        $result = $scale > 0 ? $intPart . '.' . $kept : $intPart;

        if ($nextDigit >= 5) {
            $unit = $scale > 0 ? '0.' . str_repeat('0', $scale - 1) . '1' : '1';
            $result = bcadd($result, $unit, $scale);
        }

        // 避免出现 "-0.00"
        if ($negative && bccomp($result, '0', $scale) !== 0) {
            $result = '-' . $result;
        }

        return $result;
    }

    /**
     * 比较大小：$a > $b 返回 1，$a < $b 返回 -1，相等返回 0。
     */
    public static function compare(mixed $a, mixed $b, int $scale = self::DEFAULT_SCALE): int
    {
        $scale = self::normalizeScale($scale);

        return bccomp(self::round($a, $scale), self::round($b, $scale), $scale);
    }

    /**
     * 分 -> 元。库里 bigint 存的「分」从这里转出去展示 / 写给访问器。
     */
    public static function fenToYuan(mixed $fen, int $scale = self::DEFAULT_SCALE): string
    {
        return self::div($fen, '100', $scale);
    }

    /**
     * 元 -> 分，返回整数字符串，可安全塞进 bigint。
     *
     * 注意不要退化成 (int) ($yuan * 100)：float 乘 100 会掉到整数下方
     * （29 分反出来的元是 0.28999999999999998，裸截断得 28，少 1 分），
     * 必须走 bcmath 精确乘再 half up。
     */
    public static function yuanToFen(mixed $yuan): string
    {
        return self::round(self::mul($yuan, '100', 0), 0);
    }

    /**
     * 归一化任意输入为合法数字字符串（不含科学计数法）。
     */
    private static function normalize(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                throw new InvalidArgumentException('FloatHelper: empty numeric string');
            }
            if (! is_numeric($value)) {
                throw new InvalidArgumentException("FloatHelper: invalid numeric string [{$value}]");
            }
            if ($value[0] === '+') {
                $value = substr($value, 1);
            }
            if (stripos($value, 'e') !== false) {
                return self::expandScientific($value);
            }

            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('FloatHelper: non-finite float value');
            }

            $string = (string) $value;
            if (stripos($string, 'e') !== false) {
                return self::expandScientific($string);
            }

            return $string;
        }

        throw new InvalidArgumentException('FloatHelper: unsupported value type ' . get_debug_type($value));
    }

    /**
     * 科学计数法展开成普通小数（纯字符串处理，不经过 float）。
     *
     * 注意这条分支可达：PHP 的 (string) 转换在 0.00001 这种量级就会输出 1.0E-5，
     * 小额金额 / 费率会命中，不是死代码。
     */
    private static function expandScientific(string $value): string
    {
        $value = strtolower($value);
        [$mantissa, $exponent] = explode('e', $value, 2);
        $exponent = (int) $exponent;

        $negative = str_starts_with($mantissa, '-');
        $mantissa = ltrim($mantissa, '+-');

        [$intPart, $fracPart] = str_contains($mantissa, '.')
            ? explode('.', $mantissa, 2)
            : [$mantissa, ''];

        $digits = $intPart . $fracPart;
        $pointPos = strlen($intPart) + $exponent;

        if ($pointPos <= 0) {
            $result = '0.' . str_repeat('0', -$pointPos) . $digits;
        } elseif ($pointPos >= strlen($digits)) {
            $result = $digits . str_repeat('0', $pointPos - strlen($digits));
        } else {
            $result = substr($digits, 0, $pointPos) . '.' . substr($digits, $pointPos);
        }

        // 清理前导 / 后导 0
        if (str_contains($result, '.')) {
            [$i, $f] = explode('.', $result, 2);
            $i = ltrim($i, '0');
            $i = $i === '' ? '0' : $i;
            $f = rtrim($f, '0');
            $result = $f === '' ? $i : $i . '.' . $f;
        } else {
            $trimmed = ltrim($result, '0');
            $result = $trimmed === '' ? '0' : $trimmed;
        }

        return $negative ? '-' . $result : $result;
    }

    /**
     * 合法化 scale。
     */
    private static function normalizeScale(int $scale): int
    {
        if ($scale < 0) {
            return 0;
        }

        return $scale > self::MAX_SCALE ? self::MAX_SCALE : $scale;
    }
}
