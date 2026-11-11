# GeoIP Updater

Framework-agnostic PHP 8.3+ updater for MaxMind GeoLite2/GeoIP2 databases. It uses validated staged downloads, an inter-process lock, safe archive checks and rollback of installed files. No download runs during Composer installation.

## Why this package exists

This dependency was created because the official MaxMind updater binary could not be used on the author's shared-hosting environment. It provides the same essential update workflow as a Composer library that runs with PHP itself: no external binary, shell command, Docker container, or resident process is required. It remains useful for Laravel applications and other constrained PHP hosting environments, while the official MaxMind tooling may still be the preferred option where it can be installed and managed.

## Install

```bash
composer require luvittor/geoip-updater
```

## PHP usage

```php
use Luvittor\GeoIpUpdater\{Edition, MaxMindDatabaseValidator, MaxMindTransport, UpdateOptions, Updater};

$directory = '/var/lib/my-app/geoip'; // persistent, absolute and writable
$updater = new Updater(
    new MaxMindTransport($_ENV['MAXMIND_ACCOUNT_ID'], $_ENV['MAXMIND_LICENSE_KEY']),
    new MaxMindDatabaseValidator(),
);
$results = $updater->update(new UpdateOptions($directory, [
    new Edition('City', 'City', $directory.'/GeoLite2-City.mmdb'),
    new Edition('ASN', 'ASN', $directory.'/GeoLite2-ASN.mmdb'),
]));
```

Each result has `edition`, `status` (`updated`, `current`, or `failed`), path, remote version and a sanitized failure reason. Failed results also expose stable structured diagnostics: `errorCode`, `phase`, `httpStatus`, and `attempts`. Use these fields instead of parsing error text. Treat a `failed` result as an operational failure. Supply credentials only through environment/configuration; never log the license key.

## Laravel adaptation

Bind `MaxMindTransport` and `MaxMindDatabaseValidator` in a service provider from `config/services.php`, then invoke `Updater` from an Artisan command. The package intentionally includes no Laravel dependency or command.

## Guarantees and limits

Before treating a local version marker as current, the updater opens the MMDB with MaxMind's reader, checks edition and build age. Archives are staged in the target filesystem and reject traversal or links. Prepared editions are validated before installation. Replacements have per-file rename/backup rollback; filesystems do **not** provide a global atomic transaction across City and ASN, so a crash or OS-level I/O failure can still require operator recovery. `Phar` support must be enabled in PHP; no shell tools, Docker, or daemon are required.

HTTP redirects are followed manually so the final response is evaluated; HTTPS-to-HTTP downgrades are blocked and `Authorization` is stripped whenever the scheme, host, or port changes. Downloads are currently buffered in memory rather than streamed; streaming is planned for a future release.

## Development

```bash
composer validate --strict
composer install
composer test
```

CI runs these checks on PHP 8.3 and 8.4. Tests use fake transports and isolated temporary directories; add a legally distributable valid `.mmdb` fixture for integration tests in downstream packaging if desired.

The validation tests include MaxMind's `GeoIP2-City-Test.mmdb` fixture under `tests/fixtures/`; its Apache 2.0 license is retained in `tests/fixtures-license.txt`.

## Release checklist

1. Review CI and package metadata; create a GitHub release/tag `v0.1.0` only after approval.
2. Push the public repository to `https://github.com/luvittor/geoip-updater` with an authorized `luvittor` identity.
3. Submit its GitHub URL at [Packagist](https://packagist.org/packages/submit), enable the GitHub webhook, then verify `composer require luvittor/geoip-updater` in an empty project.
