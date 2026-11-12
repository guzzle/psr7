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
 */
final class ErrorScope
{
    private int $levels;

    private bool $silenced;

    private int $reporting = 0;

    private bool $running = false;

    /**
     * @var list<array{message: string, file: string, line: int}>
     */
    private array $errors = [];

    /**
     * Creates a scope that captures errors of the given levels.
     *
     * Errors of other levels are passed on to the previously installed handler,
     * which may be an enclosing scope. When `$silenced` is false, so are errors
     * silenced inside the call with the `@` operator, which are those whose
     * level was reported as the call started and is not reported when they are
     * raised.
     *
     * @param int  $levels   Error levels to capture
     * @param bool $silenced Whether to capture errors silenced inside the call
     */
    public function __construct(int $levels = \E_ALL, bool $silenced = true)
    {
        $this->levels = $levels;
        $this->silenced = $silenced;
    }

    /**
     * Runs the operation and returns its result, capturing the errors raised
     * beneath it.
     *
     * A scope runs one operation at a time, so calling this again before it
     * returns throws a `LogicException`.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     *
     * @throws \LogicException if the scope is already running an operation
     */
    public function run(callable $operation)
    {
        if ($this->running) {
            throw new \LogicException(static::class.' is already running an operation');
        }

        $owner = $this;
        $previous = null;
        $previous = set_error_handler(static function (int $errno, string $errstr, string $errfile = '', int $errline = 0, ...$rest) use ($owner, &$previous): bool {
            foreach (debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
                $scope = $frame['object'] ?? null;
                if (!$scope instanceof self) {
                    continue;
                }

                if ($scope->captures($errno)) {
                    $scope->errors[] = [
                        'message' => $errstr,
                        'file' => $errfile,
                        'line' => $errline,
                    ];

                    return true;
                }

                // Outer scopes are reached through the previous handler.
                if ($scope === $owner) {
                    break;
                }
            }

            return $previous !== null && $previous($errno, $errstr, $errfile, $errline, ...$rest) !== false;
        });

        $this->reporting = error_reporting();
        $this->running = true;

        try {
            return $operation();
        } finally {
            $this->running = false;
            restore_error_handler();
        }
    }

    /**
     * Returns the message of the last error captured, if any.
     */
    public function getError(): ?string
    {
        $error = end($this->errors);

        return $error === false ? null : $error['message'];
    }

    /**
     * Returns the errors captured, in the order they were raised.
     *
     * @return list<array{message: string, file: string, line: int}>
     */
    public function getErrors(): array
    {
        return $this->errors;
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
