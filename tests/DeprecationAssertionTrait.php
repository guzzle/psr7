<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

trait DeprecationAssertionTrait
{
    /**
     * @return mixed
     */
    private static function assertUserDeprecation(string $expectedMessage, callable $callback)
    {
        $deprecations = [];

        \set_error_handler(static function (int $severity, string $message) use (&$deprecations): bool {
            if ($severity !== \E_USER_DEPRECATED) {
                return false;
            }

            $deprecations[] = $message;

            return true;
        });

        try {
            $result = $callback();
        } finally {
            \restore_error_handler();
        }

        self::assertNotSame([], $deprecations);
        self::assertStringContainsString($expectedMessage, $deprecations[0]);

        return $result;
    }
}
