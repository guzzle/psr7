<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\ErrorScope;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\ErrorScope
 */
class ErrorScopeTest extends TestCase
{
    public function testReturnsTheOperationResult(): void
    {
        $scope = new ErrorScope();

        self::assertSame('result', $scope->run(static fn (): string => 'result'));
        self::assertNull($scope->getError());
    }

    public function testCapturesErrorsRaisedBeneathTheCall(): void
    {
        $passedOn = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$passedOn): bool {
            $passedOn[] = $errstr;

            return true;
        });

        try {
            $scope = new ErrorScope();
            $scope->run(static function (): void {
                trigger_error('first', E_USER_WARNING);
                trigger_error('second', E_USER_NOTICE);
            });
        } finally {
            restore_error_handler();
        }

        self::assertSame('second', $scope->getError());
        self::assertSame([], $passedOn);
    }

    public function testReturnsTheCapturedErrorsInOrder(): void
    {
        $scope = new ErrorScope();
        $scope->run(static function (): void {
            trigger_error('first', E_USER_WARNING);
            trigger_error('second', E_USER_NOTICE);
        });

        self::assertSame(['first', 'second'], array_column($scope->getErrors(), 'message'));
        self::assertSame([__FILE__, __FILE__], array_column($scope->getErrors(), 'file'));
        self::assertSame([__LINE__ - 6, __LINE__ - 5], array_column($scope->getErrors(), 'line'));
    }

    public function testPassesOnErrorsOutsideItsLevels(): void
    {
        $passedOn = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$passedOn): bool {
            $passedOn[] = $errstr;

            return true;
        });

        try {
            $scope = new ErrorScope(E_ALL & ~E_USER_DEPRECATED);
            $scope->run(static function (): void {
                trigger_error('deprecated', E_USER_DEPRECATED);
                trigger_error('warning', E_USER_WARNING);
            });
        } finally {
            restore_error_handler();
        }

        self::assertSame('warning', $scope->getError());
        self::assertSame(['deprecated'], $passedOn);
    }

    public function testPassesOnErrorsToAnEnclosingScope(): void
    {
        $outer = new ErrorScope(E_USER_NOTICE);
        $inner = new ErrorScope(E_USER_WARNING);
        $outer->run(static function () use ($inner): void {
            $inner->run(static function (): void {
                trigger_error('notice', E_USER_NOTICE);
            });
        });

        self::assertNull($inner->getError());
        self::assertSame('notice', $outer->getError());
    }

    public function testPassesOnErrorsToAHandlerInstalledInsideAnEnclosingScope(): void
    {
        $passedOn = [];
        $outer = new ErrorScope(E_USER_NOTICE);
        $inner = new ErrorScope(E_USER_WARNING);
        $outer->run(static function () use ($inner, &$passedOn): void {
            set_error_handler(static function (int $errno, string $errstr) use (&$passedOn): bool {
                $passedOn[] = $errstr;

                return true;
            });

            try {
                $inner->run(static function (): void {
                    trigger_error('notice', E_USER_NOTICE);
                });
            } finally {
                restore_error_handler();
            }
        });

        self::assertNull($outer->getError());
        self::assertSame(['notice'], $passedOn);
    }

    public function testCapturesErrorsSilencedInsideTheCallByDefault(): void
    {
        $scope = new ErrorScope();
        $scope->run(static function (): void {
            @trigger_error('silenced', E_USER_WARNING);
        });

        self::assertSame('silenced', $scope->getError());
    }

    public function testPassesOnErrorsSilencedInsideTheCallOnRequest(): void
    {
        $passedOn = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$passedOn): bool {
            $passedOn[] = $errstr;

            return true;
        });

        try {
            $scope = new ErrorScope(E_ALL, false);
            $scope->run(static function (): void {
                @trigger_error('silenced', E_USER_WARNING);
            });
        } finally {
            restore_error_handler();
        }

        self::assertNull($scope->getError());
        self::assertSame(['silenced'], $passedOn);
    }

    public function testCapturesErrorsSilencedBeforeTheCall(): void
    {
        $scope = new ErrorScope(E_ALL, false);
        @$scope->run(static function (): void {
            trigger_error('silenced', E_USER_WARNING);
        });

        self::assertSame('silenced', $scope->getError());
    }

    public function testRestoresTheErrorHandlerWhenTheOperationThrows(): void
    {
        $handler = static fn (): bool => true;
        set_error_handler($handler);

        try {
            (new ErrorScope())->run(static function (): void {
                throw new \LogicException('failed');
            });
            self::fail('Expected the operation exception.');
        } catch (\LogicException $e) {
            self::assertSame($handler, self::currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @requires PHP 8.1
     */
    public function testPassesOnErrorsRaisedOnAnotherCallStack(): void
    {
        $passedOn = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$passedOn): bool {
            $passedOn[] = $errstr;

            return true;
        });

        try {
            $scope = new ErrorScope();
            $fiber = new \Fiber(static function () use ($scope): void {
                $scope->run(static function (): void {
                    \Fiber::suspend();
                });
            });
            $fiber->start();
            trigger_error('unrelated', E_USER_WARNING);
            $fiber->resume();
        } finally {
            restore_error_handler();
        }

        self::assertNull($scope->getError());
        self::assertSame(['unrelated'], $passedOn);
    }

    /**
     * @requires PHP 8.1
     */
    public function testCapturesErrorsForOverlappingScopesThatFinishOutOfOrder(): void
    {
        $first = new ErrorScope();
        $second = new ErrorScope();
        $start = static function (ErrorScope $scope, string $message): \Fiber {
            $fiber = new \Fiber(static function () use ($scope, $message): void {
                $scope->run(static function () use ($message): void {
                    \Fiber::suspend();
                    trigger_error($message, E_USER_WARNING);
                });
            });
            $fiber->start();

            return $fiber;
        };

        $firstFiber = $start($first, 'first');
        $secondFiber = $start($second, 'second');
        $firstFiber->resume();
        $secondFiber->resume();

        self::assertSame('first', $first->getError());
        self::assertSame('second', $second->getError());
    }

    /**
     * @requires PHP 8.1
     */
    public function testRestoresTheErrorHandlerWhenItsFiberIsDestroyed(): void
    {
        $handler = static fn (): bool => true;
        set_error_handler($handler);

        try {
            $fiber = new \Fiber(static function (): void {
                (new ErrorScope())->run(static function (): void {
                    \Fiber::suspend();
                });
            });
            $fiber->start();
            unset($fiber);

            self::assertSame($handler, self::currentErrorHandler());
        } finally {
            restore_error_handler();
        }
    }

    private static function currentErrorHandler(): ?callable
    {
        $handler = set_error_handler(null);
        restore_error_handler();

        return $handler;
    }
}
