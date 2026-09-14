<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7;
use PHPUnit\Framework\TestCase;

final class DomainUtilitiesTest extends TestCase
{
    public function testStreamsExposeExtractedOperations(): void
    {
        $source = Psr7\Streams::streamFor("first\nsecond");
        self::assertSame("first\n", Psr7\Streams::readLine($source));
        self::assertSame('second', Psr7\Streams::copyToString($source));

        $source = Psr7\Streams::streamFor('abc');
        $dest = Psr7\Streams::streamFor('');
        self::assertSame(3, Psr7\Streams::copyToStream($source, $dest));
        $dest->rewind();
        self::assertSame('abc', $dest->getContents());

        $stream = Psr7\Streams::streamFor('abc');
        self::assertSame(hash('sha256', 'abc'), Psr7\Streams::hash($stream, 'sha256'));

        $resource = Psr7\Streams::tryFopen('php://temp', 'r+');
        fwrite($resource, 'contents');
        rewind($resource);
        self::assertSame('contents', Psr7\Streams::tryGetContents($resource));
    }

    public function testRequestModifierExposesExtractedOperation(): void
    {
        $request = new Psr7\Request('GET', 'https://example.com/path', ['X-Old' => '1']);

        $modified = Psr7\RequestModifier::apply($request, [
            'method' => 'POST',
            'query' => 'page=2',
            'set_headers' => ['X-New' => '2'],
            'body' => 'payload',
        ]);

        self::assertSame('POST', $modified->getMethod());
        self::assertSame('page=2', $modified->getUri()->getQuery());
        self::assertSame('2', $modified->getHeaderLine('X-New'));
        self::assertSame('payload', (string) $modified->getBody());
    }

    public function testUriRedactorExposesExtractedOperations(): void
    {
        $uri = new Psr7\Uri('https://user:secret@example.com/path');

        self::assertSame(
            'https://***@example.com/path',
            (string) Psr7\UriRedactor::redactUserInfo($uri)
        );
        self::assertSame(
            'request failed for https://***@example.com/path',
            Psr7\UriRedactor::redactUserInfoInString(
                'request failed for https://user:secret@example.com/path',
                'https://user:secret@example.com/path'
            )
        );
    }
}
