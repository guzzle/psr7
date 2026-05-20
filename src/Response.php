<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * PSR-7 response implementation.
 */
class Response implements ResponseInterface
{
    use MessageTrait;

    /** Map of standard HTTP status code/reason phrases */
    private const PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    /** @var string */
    private $reasonPhrase;

    /** @var int */
    private $statusCode;

    /**
     * @param int                                  $status  Status code
     * @param (string|string[])[]                  $headers Response headers
     * @param string|resource|StreamInterface|null $body    Response body
     * @param string                               $version Protocol version
     * @param string|null                          $reason  Reason phrase (when empty a default will be used based on the status code)
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        ?string $reason = null
    ) {
        $this->assertStatusCodeRange($status);

        $this->statusCode = $status;

        if ($body !== '' && $body !== null) {
            $this->stream = Utils::streamFor($body);
        }

        $this->setHeaders($headers);
        if ($reason == '' && isset(self::PHRASES[$this->statusCode])) {
            $this->reasonPhrase = self::PHRASES[$this->statusCode];
        } else {
            $this->reasonPhrase = (string) $reason;
        }

        $this->protocol = $version;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function withStatus($code, $reasonPhrase = ''): ResponseInterface
    {
        if (!$this->isInt($code) && $this->isIntLike($code)) {
            $this->triggerInvalidArgumentDeprecation('ResponseInterface::withStatus()', 'int for $code', $code);
        }

        if (!$this->isString($reasonPhrase) && ($this->isNull($reasonPhrase) || $this->isScalar($reasonPhrase) || $this->isStringableObject($reasonPhrase))) {
            $this->triggerInvalidArgumentDeprecation('ResponseInterface::withStatus()', 'string for $reasonPhrase', $reasonPhrase);
        }

        $this->assertStatusCodeIsInteger($code);
        $code = (int) $code;
        $this->assertStatusCodeRange($code);

        $new = clone $this;
        $new->statusCode = $code;
        if ($reasonPhrase == '' && isset(self::PHRASES[$new->statusCode])) {
            $reasonPhrase = self::PHRASES[$new->statusCode];
        }
        $new->reasonPhrase = (string) $reasonPhrase;

        return $new;
    }

    /**
     * @param mixed $statusCode
     */
    private function assertStatusCodeIsInteger($statusCode): void
    {
        if (filter_var($statusCode, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Status code must be an integer value.');
        }
    }

    private function assertStatusCodeRange(int $statusCode): void
    {
        if ($statusCode < 100 || $statusCode >= 600) {
            throw new \InvalidArgumentException('Status code must be an integer value between 1xx and 5xx.');
        }
    }

    /**
     * @param mixed $value
     */
    private function isInt($value): bool
    {
        return \is_int($value);
    }

    /**
     * @param mixed $value
     */
    private function isIntLike($value): bool
    {
        return \filter_var($value, \FILTER_VALIDATE_INT) !== false;
    }

    /**
     * @param mixed $value
     */
    private function isNull($value): bool
    {
        return $value === null;
    }

    /**
     * @param mixed $value
     */
    private function isScalar($value): bool
    {
        return \is_scalar($value);
    }

    /**
     * @param mixed $value
     */
    private function isString($value): bool
    {
        return \is_string($value);
    }

    /**
     * @param mixed $value
     */
    private function isStringableObject($value): bool
    {
        return \is_object($value) && \method_exists($value, '__toString');
    }

    /**
     * @param mixed $value
     */
    private function triggerInvalidArgumentDeprecation(string $method, string $expected, $value): void
    {
        \trigger_error(\sprintf(
            'Passing %s to %s is deprecated and will throw in guzzlehttp/psr7 3.0; expected %s.',
            $this->describeType($value),
            $method,
            $expected
        ), \E_USER_DEPRECATED);
    }

    /**
     * @param mixed $value
     */
    private function describeType($value): string
    {
        return \is_object($value) ? \get_class($value) : \gettype($value);
    }
}
