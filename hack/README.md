# Development Scripts

## Regenerating MIME Types

The `src/MimeType.php` class is generated from [jshttp/mime-db](https://github.com/jshttp/mime-db).

To update to the latest version:

1. Update the version and commit hash in the `repositories` section of `composer.json`
1. Run `composer update jshttp/mime-db`
1. Run `php hack/generate-mime-types.php`
1. Run the tests to verify: `vendor/bin/phpunit tests/MimeTypeTest.php`
