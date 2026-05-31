<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\Integers;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\Psr7\Integers
 */
class IntegersTest extends TestCase
{
    public function testAddAllowsMaximumIntegerResult(): void
    {
        self::assertSame(\PHP_INT_MAX, Integers::add(\PHP_INT_MAX - 1, 1));
    }

    public function testAddRejectsOverflow(): void
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Stream byte count exceeds the maximum integer size supported on this platform');

        Integers::add(\PHP_INT_MAX, 1);
    }

    public function testAddRejectsNegativeOperands(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Integer operands must be non-negative');

        Integers::add(-1, 0);
    }

    public function testAddSignedAllowsNegativeDeltaWithinBounds(): void
    {
        self::assertSame(3, Integers::addSigned(5, -2));
    }

    public function testAddSignedRejectsNegativeBase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream offset must be non-negative');

        Integers::addSigned(-1, 1);
    }

    public function testAddSignedRejectsPositiveOverflow(): void
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Stream offset exceeds the maximum integer size supported on this platform');

        Integers::addSigned(\PHP_INT_MAX, 1);
    }

    public function testAddSignedRejectsNegativeResult(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream offset must be non-negative');

        Integers::addSigned(0, -1);
    }

    public function testAssertEngineIntegerAcceptsUnavailableValues(): void
    {
        self::assertNull(Integers::assertEngineInteger(false, 'Stream size'));
        self::assertNull(Integers::assertEngineInteger(null, 'Stream size'));
    }

    public function testAssertEngineIntegerAcceptsNonNegativeInteger(): void
    {
        self::assertSame(10, Integers::assertEngineInteger(10, 'Stream size'));
    }

    /**
     * @dataProvider invalidEngineIntegers
     *
     * @param mixed $value
     */
    public function testAssertEngineIntegerRejectsInvalidValues($value): void
    {
        $this->expectException(\OverflowException::class);
        $this->expectExceptionMessage('Stream size exceeds the maximum integer size supported on this platform');

        Integers::assertEngineInteger($value, 'Stream size');
    }

    public static function invalidEngineIntegers(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'string integer' => ['1'];
        yield 'float' => [1.0];
    }

    public function testAssertOptionalNonNegativeSizeAcceptsNullAndIntegers(): void
    {
        self::assertNull(Integers::assertOptionalNonNegativeSize(null, 'Stream size'));
        self::assertSame(0, Integers::assertOptionalNonNegativeSize(0, 'Stream size'));
        self::assertSame(10, Integers::assertOptionalNonNegativeSize(10, 'Stream size'));
    }

    /**
     * @dataProvider invalidNonNegativeIntegers
     *
     * @param mixed $value
     */
    public function testAssertOptionalNonNegativeSizeRejectsInvalidValues($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stream size must be a non-negative integer or null');

        Integers::assertOptionalNonNegativeSize($value, 'Stream size');
    }

    /**
     * @dataProvider invalidNonNegativeIntegers
     *
     * @param mixed $value
     */
    public function testAssertNonNegativeIntegerRejectsInvalidValues($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('High water mark must be a non-negative integer');

        Integers::assertNonNegativeInteger($value, 'High water mark');
    }

    public static function invalidNonNegativeIntegers(): iterable
    {
        yield 'negative integer' => [-1];
        yield 'string integer' => ['1'];
        yield 'float' => [1.0];
        yield 'false' => [false];
    }

    public function testAssertLimitIntegerAcceptsMinusOneAndNonNegativeIntegers(): void
    {
        self::assertSame(-1, Integers::assertLimitInteger(-1, 'Limit'));
        self::assertSame(0, Integers::assertLimitInteger(0, 'Limit'));
        self::assertSame(10, Integers::assertLimitInteger(10, 'Limit'));
    }

    /**
     * @dataProvider invalidLimits
     *
     * @param mixed $value
     */
    public function testAssertLimitIntegerRejectsInvalidValues($value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be -1 or a non-negative integer');

        Integers::assertLimitInteger($value, 'Limit');
    }

    public static function invalidLimits(): iterable
    {
        yield 'below minus one' => [-2];
        yield 'string integer' => ['1'];
        yield 'float' => [1.0];
    }
}
