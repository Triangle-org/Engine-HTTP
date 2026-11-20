<p align="center"><a href="https://www.localzet.com" target="_blank">
  <img src="https://static.zorin.space/media/logos/LocalzetGroup.png" width="400">
</a></p>

<p align="center">
  <a href="https://packagist.org/packages/triangle/engine-http">
  <img src="https://img.shields.io/packagist/dt/triangle/engine-http?label=%D0%A1%D0%BA%D0%B0%D1%87%D0%B8%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F" alt="Скачивания">
</a>
  <a href="https://github.com/Triangle-org/Engine-HTTP">
  <img src="https://img.shields.io/github/commit-activity/t/Triangle-org/Engine-HTTP?label=%D0%9A%D0%BE%D0%BC%D0%BC%D0%B8%D1%82%D1%8B" alt="Коммиты">
</a>
  <a href="https://packagist.org/packages/triangle/engine-http">
  <img src="https://img.shields.io/packagist/v/triangle/engine-http?label=%D0%92%D0%B5%D1%80%D1%81%D0%B8%D1%8F" alt="Версия">
</a>
  <a href="https://packagist.org/packages/triangle/engine-http">
  <img src="https://img.shields.io/packagist/dependency-v/triangle/engine-http/php?label=PHP" alt="Версия PHP">
</a>
  <a href="https://github.com/Triangle-org/Engine-HTTP">
  <img src="https://img.shields.io/github/license/Triangle-org/Engine-HTTP?label=%D0%9B%D0%B8%D1%86%D0%B5%D0%BD%D0%B7%D0%B8%D1%8F" alt="Лицензия">
</a>
</p>

# Triangle Engine HTTP

HTTP adapter for Triangle applications. It connects request handling to Localzet Server 7, Triangle Router, and Middleware; the application bootstrap remains responsible for configuration, routes, and listener startup.

## Compatibility

Release 3.x targets PHP 8.1+, Localzet Server 7.1+, Triangle Engine 9, Router 2, Middleware 2, and the PHP cURL extension. Release 2.x remains on the earlier Engine compatibility line. Do not mix the two dependency sets in one lock file.

## Install

```bash
composer require triangle/engine-http:^3.0
```

Follow the [installation guide](https://server.localzet.com/projects/engine-http/install) for the complete bootstrap and version checks.

## Request and response flow

Register routes before collecting the Router dispatcher:

```php
use localzet\Server\Protocols\Http\Response;
use Triangle\Router;

Router::any('/health', static fn () => new Response(
    200,
    ['Content-Type' => 'application/json'],
    '{"status":"ok"}',
));

Router::collect([]);
```

Engine HTTP sends requests through route middleware and controller resolution. A Localzet Server HTTP response keeps its status, headers, and body. Other return values become a `200` response with a string body.

## Security and limits

- Authentication and authorization belong to the application or its middleware.
- CORS is not enabled by default. Configure `app.http_headers` with explicit origins and headers when cross-origin access is required.
- PHP files under a public path are disabled by default; enable them only when the application has a clear need and a reviewable execution boundary.
- Listener, HTTP framing, worker lifecycle, and backpressure are provided by Localzet Server.

See the [request guide](https://server.localzet.com/projects/engine-http/requests), [configuration](https://server.localzet.com/projects/engine-http/architecture), and [security guide](https://server.localzet.com/projects/engine-http/security) for production setup.

## Checks

```bash
composer validate --strict --no-check-publish --no-interaction
composer run test --no-interaction
composer audit --abandoned=report
```

The test command covers response compatibility and a loopback HTTP request against Server 7.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
