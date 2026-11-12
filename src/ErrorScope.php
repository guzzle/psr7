<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * Captures the PHP errors raised beneath a call.
 *
 * PHP keeps one error handler stack per process, shared by every coroutine and
 * fiber, so an error raised on another call stack is passed on to the
 * previously installed handler instead of being captured.
 *
 * @internal
 */
final class ErrorScope
{
    private ?string $error = null;

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation)
    {
        $previous = null;
        $previous = set_error_handler(static function (int $errno, string $errstr, ...$rest) use (&$previous): bool {
            foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                $scope = $frame['object'] ?? null;
                if ($scope instanceof self) {
                    $scope->error = $errstr;

                    return true;
                }
            }

            return $previous !== null && $previous($errno, $errstr, ...$rest) !== false;
        });

        try {
            return $operation();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Returns the message of the last error captured, if any.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
