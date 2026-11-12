<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

/**
 * Captures the PHP errors raised beneath a call.
 *
 * PHP keeps one error handler stack per process, shared by every coroutine and
 * fiber, so an error raised on another call stack is passed on to the
 * previously installed handler instead of being captured. That handler is
 * called directly, so it also receives error levels it was not registered for.
 *
 * @internal
 */
final class ErrorScope
{
    private int $levels;

    private bool $silenced;

    private int $reporting = 0;

    private ?string $error = null;

    /**
     * @param int  $levels   Error levels to capture
     * @param bool $silenced Whether to capture errors silenced inside the call
     */
    public function __construct(int $levels = \E_ALL, bool $silenced = true)
    {
        $this->levels = $levels;
        $this->silenced = $silenced;
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function run(callable $operation)
    {
        $owner = $this;
        $previous = null;
        $previous = set_error_handler(static function (int $errno, string $errstr, ...$rest) use ($owner, &$previous): bool {
            foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                $scope = $frame['object'] ?? null;
                if (!$scope instanceof self) {
                    continue;
                }

                if ($scope->captures($errno)) {
                    $scope->error = $errstr;

                    return true;
                }

                // Outer scopes are reached through the previous handler.
                if ($scope === $owner) {
                    break;
                }
            }

            return $previous !== null && $previous($errno, $errstr, ...$rest) !== false;
        });

        $this->reporting = error_reporting();

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

    private function captures(int $errno): bool
    {
        if (($this->levels & $errno) === 0) {
            return false;
        }

        // An error is silenced inside the call when its level was reported as
        // the call started and is not reported now.
        return $this->silenced || (error_reporting() & $errno) !== 0 || ($this->reporting & $errno) === 0;
    }
}
