# Error Scopes

## `GuzzleHttp\Psr7\ErrorScope`

Captures the PHP errors raised beneath a call.

PHP keeps one error handler stack per process, shared by every coroutine and
fiber, so an error raised on another call stack is passed on to the previously
installed handler instead of being captured. That handler is called directly, so
it also receives error levels it was not registered for.

```php
use GuzzleHttp\Psr7\DiagnosticValue;
use GuzzleHttp\Psr7\ErrorScope;

$scope = new ErrorScope();
$handle = $scope->run(static fn () => fopen('/path/to/file', 'r'));

if ($handle === false) {
    throw new RuntimeException(
        DiagnosticValue::escape((string) $scope->getError())
    );
}
```

## `GuzzleHttp\Psr7\ErrorScope::__construct`

`public function __construct(int $levels = E_ALL, bool $silenced = true)`

Creates a scope that captures errors of the given levels.

Errors of other levels are passed on to the previously installed handler, which
may be an enclosing scope. When `$silenced` is false, so are errors silenced
inside the call with the `@` operator, which are those whose level was reported
as the call started and is not reported when they are raised.

## `GuzzleHttp\Psr7\ErrorScope::run`

`public function run(callable $operation): mixed`

Runs the operation and returns its result, capturing the errors raised beneath
it.

A scope runs one operation at a time, so calling this again before it returns
throws a `LogicException`.

## `GuzzleHttp\Psr7\ErrorScope::getError`

`public function getError(): ?string`

Returns the message of the last error captured, if any.

## `GuzzleHttp\Psr7\ErrorScope::getErrors`

`public function getErrors(): array`

Returns the errors captured, in the order they were raised.

Each error is an array with `message`, `file`, and `line` keys.

## Related

- [Diagnostic Values](diagnostic-values.md)
- [Stream Helpers](stream-helpers.md)
