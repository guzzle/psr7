<?php

declare(strict_types=1);

namespace GuzzleHttp\Tests\Psr7;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\ServerRequestGlobalsFactory;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;

class SensitiveParameterTest extends TestCase
{
    /**
     * @dataProvider sensitiveParameterProvider
     */
    public function testParameterHasSensitiveAttribute(
        string $class,
        string $method,
        string $parameter
    ): void {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        $reflection = new \ReflectionParameter([$class, $method], $parameter);
        $attributes = $reflection->getAttributes(\SensitiveParameter::class);

        self::assertCount(1, $attributes);
        self::assertInstanceOf(\SensitiveParameter::class, $attributes[0]->newInstance());
    }

    public static function sensitiveParameterProvider(): iterable
    {
        yield 'URI input' => [Uri::class, '__construct', 'uri'];
        yield 'URI parts' => [Uri::class, 'fromParts', 'parts'];
        yield 'URI password' => [Uri::class, 'withUserInfo', 'password'];
        yield 'applied URI parts' => [Uri::class, 'applyParts', 'parts'];
        yield 'userinfo component' => [Uri::class, 'filterUserInfoComponent', 'component'];
        yield 'filtered component' => [Uri::class, 'filterComponent', 'component'];
        yield 'userinfo URI object' => [Utils::class, 'redactUserInfo', 'uri'];
        yield 'server request params' => [ServerRequest::class, '__construct', 'serverParams'];
        yield 'cookie params' => [ServerRequest::class, 'withCookieParams', 'cookies'];
        yield 'server globals' => [ServerRequestGlobalsFactory::class, 'fromArrays', 'server'];
        yield 'cookie globals' => [ServerRequestGlobalsFactory::class, 'fromArrays', 'cookies'];
        yield 'server URI params' => [ServerRequestGlobalsFactory::class, 'getUriFromServerParams', 'server'];
        yield 'server header params' => [ServerRequestGlobalsFactory::class, 'getAllHeaders', 'server'];
        yield 'raw headers' => [ServerRequestGlobalsFactory::class, 'normalizeHeaderValues', 'headers'];
        yield 'server authority params' => [ServerRequestGlobalsFactory::class, 'getAuthorityUriFromServer', 'server'];
        yield 'server target params' => [ServerRequestGlobalsFactory::class, 'getUriAndRequestTargetFromServer', 'server'];
        yield 'absolute request target' => [ServerRequestGlobalsFactory::class, 'getAbsoluteFormUriAndRequestTarget', 'requestUri'];
        yield 'PSR-17 server params' => [HttpFactory::class, 'createServerRequest', 'serverParams'];
    }

    public function testSourceContainsExactSensitiveAttributeInventory(): void
    {
        $count = 0;
        foreach (['HttpFactory.php', 'ServerRequest.php', 'ServerRequestGlobalsFactory.php', 'Uri.php', 'Utils.php'] as $file) {
            $source = file_get_contents(__DIR__.'/../src/'.$file);

            self::assertIsString($source);
            $count += preg_match_all('/#\[\\\\SensitiveParameter\]/', $source);
        }

        self::assertSame(18, $count);
    }

    public function testPureStringRedactorParametersAreNotSensitive(): void
    {
        if (PHP_VERSION_ID < 80000) {
            self::markTestSkipped('Attributes are not reflected before PHP 8.0.');
        }

        $reflection = new \ReflectionMethod(Utils::class, 'redactUserInfoInString');

        foreach ($reflection->getParameters() as $parameter) {
            self::assertCount(0, $parameter->getAttributes(\SensitiveParameter::class));
        }
    }

    public function testCredentialedUriIsRedactedFromExceptionTrace(): void
    {
        if (PHP_VERSION_ID < 80200) {
            self::markTestSkipped('Native sensitive-parameter redaction requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        if (ini_get('zend.exception_ignore_args') !== '0') {
            self::markTestSkipped('Trace arguments cannot be enabled.');
        }

        try {
            new Uri('http://user:password@example.com:70000');
            self::fail('Expected URI parsing to throw.');
        } catch (\Throwable $exception) {
            $frame = null;
            foreach ($exception->getTrace() as $candidate) {
                if (($candidate['class'] ?? null) === Uri::class
                    && ($candidate['function'] ?? null) === '__construct') {
                    $frame = $candidate;
                    break;
                }
            }

            self::assertNotNull($frame);
            self::assertCount(1, $frame['args']);
            self::assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][0]);
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }
}
