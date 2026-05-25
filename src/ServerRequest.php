<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class ServerRequest extends Request implements ServerRequestInterface
{
    private array $attributes = [];

    private array $cookieParams = [];

    /**
     * @var array|object|null
     */
    private $parsedBody;

    private array $queryParams = [];

    private array $serverParams;

    private array $uploadedFiles = [];

    /**
     * @param string                               $method       HTTP method
     * @param string|UriInterface                  $uri          URI
     * @param (string|string[])[]                  $headers      Request headers
     * @param string|resource|StreamInterface|null $body         Request body
     * @param string                               $version      Protocol version
     * @param array                                $serverParams Typically the $_SERVER superglobal
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        array $serverParams = []
    ) {
        $this->serverParams = $serverParams;

        parent::__construct($method, $uri, $headers, $body, $version);
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files An array which respect $_FILES structure
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    public static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && array_key_exists('tmp_name', $value)) {
                $normalized[$key] = self::createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalizeFiles($value);
                continue;
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     *
     * @return UploadedFileInterface|UploadedFileInterface[]
     */
    private static function createUploadedFileFromSpec(array $value)
    {
        self::assertFileSpec($value);

        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile(
            $value['tmp_name'],
            (int) $value['size'],
            (int) $value['error'],
            $value['name'] ?? null,
            $value['type'] ?? null
        );
    }

    private static function assertFileSpec(array $value): void
    {
        if (!isset($value['tmp_name'], $value['size'], $value['error'])) {
            throw new InvalidArgumentException(
                'Invalid file specification; expected keys "tmp_name", "size", and "error".'
            );
        }
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @return UploadedFileInterface[]
     */
    private static function normalizeNestedFileSpec(array $files = []): array
    {
        self::assertNestedFileSpec($files);

        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            if (!array_key_exists($key, $files['size']) || !array_key_exists($key, $files['error'])) {
                throw new InvalidArgumentException(
                    'Invalid nested file specification; expected "tmp_name", "size", and "error" arrays to have matching keys.'
                );
            }

            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key] ?? null,
                'type' => $files['type'][$key] ?? null,
            ];
            $normalizedFiles[$key] = self::createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }

    private static function assertNestedFileSpec(array $files): void
    {
        foreach (['tmp_name', 'size', 'error'] as $key) {
            if (!isset($files[$key]) || !is_array($files[$key])) {
                throw new InvalidArgumentException(
                    'Invalid nested file specification; expected keys "tmp_name", "size", and "error" to be arrays.'
                );
            }
        }

        foreach (['name', 'type'] as $key) {
            if (isset($files[$key]) && !is_array($files[$key])) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid nested file specification; expected key "%s" to be an array when present.',
                    $key
                ));
            }
        }
    }

    /**
     * Return a ServerRequest populated with superglobals:
     * $_GET
     * $_POST
     * $_COOKIE
     * $_FILES
     * $_SERVER
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        $method = self::getRequestMethodFromGlobals();
        $headers = self::removeInvalidHostHeader(self::getAllHeaders());
        [$uri, $requestTarget] = self::getUriAndRequestTargetFromGlobals($method);
        $body = new CachingStream(new LazyOpenStream('php://input', 'r+'));
        $serverProtocol = self::getServerParam('SERVER_PROTOCOL');
        $protocol = '1.1';
        if ($serverProtocol !== null) {
            $protocol = strpos($serverProtocol, 'HTTP/') === 0 ? substr($serverProtocol, 5) : $serverProtocol;
        }

        $serverRequest = new ServerRequest($method, $uri, $headers, $body, $protocol, $_SERVER);
        if ($requestTarget !== null) {
            /** @var ServerRequestInterface $serverRequest */
            $serverRequest = $serverRequest->withRequestTarget($requestTarget);
        }

        return $serverRequest
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withUploadedFiles(self::normalizeFiles($_FILES));
    }

    /**
     * @return array<array-key, string>
     */
    private static function getAllHeaders(): array
    {
        $headers = self::getApacheRequestHeaders();

        if (!is_array($headers)) {
            $headers = self::getHeadersFromServer($_SERVER);
        }

        return self::normalizeHeaderValues($headers);
    }

    /**
     * @return mixed
     */
    private static function getApacheRequestHeaders()
    {
        if (!\function_exists('apache_request_headers')) {
            return false;
        }

        return \apache_request_headers();
    }

    /**
     * @param array<array-key, mixed> $headers
     *
     * @return array<array-key, string>
     */
    private static function normalizeHeaderValues(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            if (is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $normalized[$name] = (string) $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array $server Typically the $_SERVER superglobal
     *
     * @return array<array-key, string>
     */
    private static function getHeadersFromServer(array $server): array
    {
        $headers = [];

        $copyServer = [
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        ];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_string($value)) {
                continue;
            }

            if (substr($key, 0, 5) === 'HTTP_') {
                $header = substr($key, 5);

                if (isset($copyServer[$header], $server[$header]) && is_string($server[$header])) {
                    continue;
                }

                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $header))));
                $headers[$header] = $value;

                continue;
            }

            if (isset($copyServer[$key])) {
                $headers[$copyServer[$key]] = $value;
            }
        }

        if (!isset($headers['Authorization'])) {
            if (isset($server['REDIRECT_HTTP_AUTHORIZATION']) && is_string($server['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($server['PHP_AUTH_USER']) && is_string($server['PHP_AUTH_USER'])) {
                $password = isset($server['PHP_AUTH_PW']) && is_string($server['PHP_AUTH_PW'])
                    ? $server['PHP_AUTH_PW']
                    : '';

                $headers['Authorization'] = 'Basic '.base64_encode($server['PHP_AUTH_USER'].':'.$password);
            } elseif (isset($server['PHP_AUTH_DIGEST']) && is_string($server['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $server['PHP_AUTH_DIGEST'];
            }
        }

        return $headers;
    }

    /**
     * @param array<array-key, string> $headers
     *
     * @return array<array-key, string>
     */
    private static function removeInvalidHostHeader(array $headers): array
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'host') {
                continue;
            }

            [$host] = self::extractHostAndPortFromAuthority($value);
            if ($host === null) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }

    private static function getServerParam(string $key): ?string
    {
        return isset($_SERVER[$key]) && is_string($_SERVER[$key]) ? $_SERVER[$key] : null;
    }

    private static function getRequestMethodFromGlobals(): string
    {
        return strtoupper(self::getServerParam('REQUEST_METHOD') ?? 'GET');
    }

    /**
     * @return array{0: string|null, 1: int|null}
     */
    private static function extractHostAndPortFromAuthority(string $authority): array
    {
        return Rfc7230::parseHostHeader($authority) ?? [null, null];
    }

    private static function parseServerPort(string $port): int
    {
        if ($port === '' || !ctype_digit($port)) {
            throw new InvalidArgumentException('Invalid SERVER_PORT; expected an integer between 1 and 65535.');
        }

        $port = ltrim($port, '0');
        if ($port === '') {
            throw new InvalidArgumentException('Invalid SERVER_PORT; expected an integer between 1 and 65535.');
        }

        if (strlen($port) > 5 || (int) $port > 0xFFFF) {
            throw new InvalidArgumentException('Invalid SERVER_PORT; expected an integer between 1 and 65535.');
        }

        return (int) $port;
    }

    private static function withHostFromGlobals(UriInterface $uri, ?string $host): ?UriInterface
    {
        if ($host === null) {
            return null;
        }

        try {
            return $uri->withHost($host);
        } catch (InvalidArgumentException $e) {
            return null;
        }
    }

    private static function getAuthorityUriFromGlobals(): UriInterface
    {
        $uri = new Uri('');

        $https = self::getServerParam('HTTPS');
        $uri = $uri->withScheme(!empty($https) && $https !== 'off' ? 'https' : 'http');

        $hasPort = false;
        $hasHost = false;
        $authority = self::getServerParam('HTTP_HOST');
        if ($authority !== null) {
            [$host, $port] = self::extractHostAndPortFromAuthority($authority);
            if ($host !== null) {
                $hostUri = self::withHostFromGlobals($uri, $host);
                if ($hostUri !== null) {
                    $uri = $hostUri;
                    $hasHost = true;

                    if ($port !== null) {
                        $hasPort = true;
                        $uri = $uri->withPort($port);
                    }
                }
            }
        }

        foreach (['SERVER_NAME', 'SERVER_ADDR'] as $serverParam) {
            if ($hasHost) {
                continue;
            }

            $hostUri = self::withHostFromGlobals($uri, self::getServerParam($serverParam));
            if ($hostUri !== null) {
                $uri = $hostUri;
                $hasHost = true;
            }
        }

        $serverPort = self::getServerParam('SERVER_PORT');
        if (!$hasPort && $serverPort !== null) {
            $uri = $uri->withPort(self::parseServerPort($serverPort));
        }

        return $uri;
    }

    /**
     * @return array{0: UriInterface, 1: string|null}
     */
    private static function getUriAndRequestTargetFromGlobals(string $method): array
    {
        $uri = self::getAuthorityUriFromGlobals();
        $requestUri = self::getServerParam('REQUEST_URI');
        $queryString = self::getServerParam('QUERY_STRING');

        if ($requestUri === null) {
            if ($queryString !== null) {
                $uri = $uri->withQuery($queryString);
            }

            return [$uri, null];
        }

        if (self::isAsteriskFormRequestTarget($method, $requestUri)) {
            return [$uri->withPath('')->withQuery(''), '*'];
        }

        $connectAuthority = self::parseConnectAuthorityFormRequestTarget($method, $requestUri);
        if ($connectAuthority !== null) {
            [$host, $port] = $connectAuthority;

            return [
                $uri->withHost($host)->withPort($port)->withPath('')->withQuery(''),
                $requestUri,
            ];
        }

        if (self::isAbsoluteFormRequestTarget($requestUri)) {
            try {
                $targetUri = (new Uri($requestUri))->withFragment('');
            } catch (InvalidArgumentException $e) {
                $targetUri = null;
            }

            if ($targetUri !== null && $targetUri->getHost() !== '') {
                $requestTarget = self::removeRequestTargetFragment($requestUri);
                if (strpos($requestTarget, '?') === false && $queryString !== null && $queryString !== '') {
                    $targetUri = $targetUri->withQuery($queryString);
                    $requestTarget .= '?'.$queryString;
                }

                // Preserve the received absolute-form target unless it cannot be used
                // as a PSR-7 request target without normalization.
                return [$targetUri, preg_match('/[\x00-\x20\x7F]/', $requestTarget) ? (string) $targetUri : $requestTarget];
            }
        }

        [$path, $query, $hasQuery] = self::splitRequestTargetQuery($requestUri);
        $uri = $uri->withPath(self::normalizeOriginFormPathFromGlobals($path));

        if ($hasQuery) {
            $uri = $uri->withQuery($query);
        } elseif ($queryString !== null) {
            $uri = $uri->withQuery($queryString);
        }

        return [$uri, null];
    }

    private static function isAbsoluteFormRequestTarget(string $target): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//D', $target) === 1;
    }

    private static function isAsteriskFormRequestTarget(string $method, string $target): bool
    {
        return $method === 'OPTIONS' && $target === '*';
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    private static function parseConnectAuthorityFormRequestTarget(string $method, string $target): ?array
    {
        if ($method !== 'CONNECT' || strpbrk($target, '/?#') !== false) {
            return null;
        }

        [$host, $port] = self::extractHostAndPortFromAuthority($target);
        if ($host === null || $port === null) {
            return null;
        }

        return [$host, $port];
    }

    private static function removeRequestTargetFragment(string $target): string
    {
        return explode('#', $target, 2)[0];
    }

    /**
     * @return array{0: string, 1: string, 2: bool}
     */
    private static function splitRequestTargetQuery(string $target): array
    {
        $parts = explode('?', $target, 2);

        return [$parts[0], $parts[1] ?? '', isset($parts[1])];
    }

    private static function normalizeOriginFormPathFromGlobals(string $path): string
    {
        if ($path === '' || $path[0] === '/') {
            return $path;
        }

        return '/'.$path;
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     */
    public static function getUriFromGlobals(): UriInterface
    {
        $method = self::getRequestMethodFromGlobals();

        return self::getUriAndRequestTargetFromGlobals($method)[0];
    }

    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface
    {
        $stack = [$uploadedFiles];

        for ($i = 0; $i < \count($stack); ++$i) {
            foreach ($stack[$i] as $uploadedFile) {
                if ($uploadedFile instanceof UploadedFileInterface) {
                    continue;
                }

                if (\is_array($uploadedFile)) {
                    $stack[] = $uploadedFile;
                    continue;
                }

                throw new InvalidArgumentException(sprintf(
                    'Invalid uploaded file tree; expected UploadedFileInterface instances but %s provided.',
                    \get_debug_type($uploadedFile)
                ));
            }
        }

        $new = clone $this;
        $new->uploadedFiles = $uploadedFiles;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookieParams = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->queryParams = $query;

        return $new;
    }

    /**
     * @return array|object|null
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        if ($data !== null && !\is_array($data) && !\is_object($data)) {
            throw new InvalidArgumentException('Parsed body must be an array, object, or null.');
        }

        $new = clone $this;
        $new->parsedBody = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    public function withAttribute(string $name, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        if (false === array_key_exists($name, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$name]);

        return $new;
    }
}
