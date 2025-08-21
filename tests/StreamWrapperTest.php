<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \GuzzleHttp\Psr7\StreamWrapper
 */
class StreamWrapperTest extends TestCase
{
    public function testResource(): void
    {
        $stream = Utils::streamFor('foo');
        $handle = StreamWrapper::getResource($stream);
        self::assertSame('foo', fread($handle, 3));
        self::assertSame(3, ftell($handle));
        self::assertSame(3, fwrite($handle, 'bar'));
        self::assertSame(0, fseek($handle, 0));
        self::assertSame('foobar', fread($handle, 6));
        self::assertSame('', fread($handle, 1));
        self::assertTrue(feof($handle));

        $stBlksize = defined('PHP_WINDOWS_VERSION_BUILD') ? -1 : 0;

        self::assertEquals([
            'dev' => 0,
            'ino' => 0,
            'mode' => 33206,
            'nlink' => 0,
            'uid' => 0,
            'gid' => 0,
            'rdev' => 0,
            'size' => 6,
            'atime' => 0,
            'mtime' => 0,
            'ctime' => 0,
            'blksize' => $stBlksize,
            'blocks' => $stBlksize,
            0 => 0,
            1 => 0,
            2 => 33206,
            3 => 0,
            4 => 0,
            5 => 0,
            6 => 0,
            7 => 6,
            8 => 0,
            9 => 0,
            10 => 0,
            11 => $stBlksize,
            12 => $stBlksize,
        ], fstat($handle));

        self::assertTrue(fclose($handle));
        self::assertSame('foobar', (string) $stream);
    }

    public function testStreamContext(): void
    {
        $stream = Utils::streamFor('foo');

        self::assertSame('foo', file_get_contents('guzzle://stream', false, StreamWrapper::createStreamContext($stream)));
    }

    public function testStreamCast(): void
    {
        $streams = [
            StreamWrapper::getResource(Utils::streamFor('foo')),
            StreamWrapper::getResource(Utils::streamFor('bar')),
        ];
        $write = null;
        $except = null;
        self::assertIsInt(stream_select($streams, $write, $except, 0));
    }

    public function testValidatesStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('isReadable')
            ->willReturn(false);
        $stream->expects(self::once())
            ->method('isWritable')
            ->willReturn(false);

        $this->expectException(\InvalidArgumentException::class);
        StreamWrapper::getResource($stream);
    }

    public function testReturnsFalseWhenStreamDoesNotExist(): void
    {
        $result = @fopen('guzzle://foo', 'r');
        self::assertFalse($result);
    }

    public function testCanOpenReadonlyStream(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('isReadable')
            ->willReturn(false);
        $stream->expects(self::once())
            ->method('isWritable')
            ->willReturn(true);
        $r = StreamWrapper::getResource($stream);
        self::assertIsResource($r);
        fclose($r);
    }

    public function testUrlStat(): void
    {
        StreamWrapper::register();

        $stBlksize = defined('PHP_WINDOWS_VERSION_BUILD') ? -1 : 0;

        self::assertEquals(
            [
                'dev' => 0,
                'ino' => 0,
                'mode' => 0,
                'nlink' => 0,
                'uid' => 0,
                'gid' => 0,
                'rdev' => 0,
                'size' => 0,
                'atime' => 0,
                'mtime' => 0,
                'ctime' => 0,
                'blksize' => $stBlksize,
                'blocks' => $stBlksize,
                0 => 0,
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0,
                6 => 0,
                7 => 0,
                8 => 0,
                9 => 0,
                10 => 0,
                11 => $stBlksize,
                12 => $stBlksize,
            ],
            stat('guzzle://stream')
        );
    }

    /**
     * @requires extension xmlreader
     */
    public function testXmlReaderWithStream(): void
    {
        $stream = Utils::streamFor('<?xml version="1.0" encoding="utf-8"?><foo />');

        StreamWrapper::register();
        libxml_set_streams_context(StreamWrapper::createStreamContext($stream));
        $reader = new \XMLReader();

        self::assertTrue($reader->open('guzzle://stream'));
        self::assertTrue($reader->read());
        self::assertSame('foo', $reader->name);
    }

    /**
     * @requires extension xmlreader
     */
    public function testXmlWriterWithStream(): void
    {
        $stream = Utils::streamFor(fopen('php://memory', 'wb'));

        StreamWrapper::register();
        libxml_set_streams_context(StreamWrapper::createStreamContext($stream));
        $writer = new \XMLWriter();

        self::assertTrue($writer->openURI('guzzle://stream'));
        self::assertTrue($writer->startDocument());
        self::assertTrue($writer->writeElement('foo'));
        self::assertTrue($writer->endDocument());

        $stream->rewind();
        self::assertXmlStringEqualsXmlString('<?xml version="1.0"?><foo />', (string) $stream);
    }

    public function testWrappedNullSizedStreamStaysNullSized(): void
    {
        $nullSizedStream = new Psr7\PumpStream(function () { return ''; });
        $this->assertNull($nullSizedStream->getSize());

        $resource = StreamWrapper::getResource($nullSizedStream);
        $stream = Utils::streamFor($resource);

        $this->assertNull($stream->getSize());
    }
}
