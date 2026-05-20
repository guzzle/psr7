<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * @internal
 */
final class Deprecation
{
    /**
     * @param mixed $value
     */
    public static function invalidArgument(string $method, string $expected, $value): void
    {
        \trigger_error(\sprintf(
            'Passing %s to %s is deprecated and will throw in guzzlehttp/psr7 3.0; expected %s.',
            self::describeType($value),
            $method,
            $expected
        ), \E_USER_DEPRECATED);
    }

    /**
     * @param mixed $value
     */
    public static function isArray($value): bool
    {
        return \is_array($value);
    }

    /**
     * @param mixed $value
     */
    public static function isBool($value): bool
    {
        return \is_bool($value);
    }

    /**
     * @param mixed $value
     */
    public static function isInt($value): bool
    {
        return \is_int($value);
    }

    /**
     * @param mixed $value
     */
    public static function isIntLike($value): bool
    {
        return \filter_var($value, \FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @param mixed $value
     */
    public static function isNull($value): bool
    {
        return $value === null;
    }

    /**
     * @param mixed $value
     */
    public static function isObject($value): bool
    {
        return \is_object($value);
    }

    /**
     * @param mixed $value
     */
    public static function isScalar($value): bool
    {
        return \is_scalar($value);
    }

    /**
     * @param mixed $value
     */
    public static function isString($value): bool
    {
        return \is_string($value);
    }

    /**
     * @param mixed $value
     */
    public static function isStringableObject($value): bool
    {
        return \is_object($value) && \method_exists($value, '__toString');
    }

    /**
     * @param mixed $value
     */
    private static function describeType($value): string
    {
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }
}
