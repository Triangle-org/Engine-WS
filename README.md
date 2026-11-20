<p align="center"><a href="https://www.localzet.com" target="_blank">
  <img src="https://static.zorin.space/media/logos/LocalzetGroup.png" width="400">
</a></p>

<p align="center">
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/dt/triangle/engine-ws?label=%D0%A1%D0%BA%D0%B0%D1%87%D0%B8%D0%B2%D0%B0%D0%BD%D0%B8%D1%8F" alt="Скачивания">
</a>
  <a href="https://github.com/Triangle-org/Engine-WS">
  <img src="https://img.shields.io/github/commit-activity/t/Triangle-org/Engine-WS?label=%D0%9A%D0%BE%D0%BC%D0%BC%D0%B8%D1%82%D1%8B" alt="Коммиты">
</a>
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/v/triangle/engine-ws?label=%D0%92%D0%B5%D1%80%D1%81%D0%B8%D1%8F" alt="Версия">
</a>
  <a href="https://packagist.org/packages/triangle/engine-ws">
  <img src="https://img.shields.io/packagist/dependency-v/triangle/engine-ws/php?label=PHP" alt="Версия PHP">
</a>
  <a href="https://github.com/Triangle-org/Engine-WS">
  <img src="https://img.shields.io/github/license/Triangle-org/Engine-WS?label=%D0%9B%D0%B8%D1%86%D0%B5%D0%BD%D0%B7%D0%B8%D1%8F" alt="Лицензия">
</a>
</p>

# Triangle Engine WS

WebSocket adapter for Triangle applications on Localzet Server. It checks routes and route middleware during the HTTP upgrade handshake, then invokes the matching callback for each WebSocket frame.

## Compatibility and installation

Release 2.x targets PHP 8.1+, Localzet Server 7.1+, Triangle Engine 9, Router 2, and Middleware 2. Release 1.x remains on the earlier Engine compatibility line. Engine HTTP and Engine WS conflict in Composer and belong in separate applications.

```bash
composer require triangle/engine-ws:^2.0
```

## Minimal application bootstrap

Run this after the Triangle application has loaded its Composer autoloader:

```php
use localzet\Server;
use localzet\Server\Protocols\Http;
use Monolog\Logger;
use Triangle\Engine\Config;
use Triangle\Engine\Container;
use Triangle\Engine\Context;
use Triangle\Router;
use Triangle\Ws\App;

Context::init();
Config::set([
    'app' => [
        'plugin_uri' => 'app',
        'plugin_alias' => 'plugin',
        'debug' => false,
        'controller_reuse' => true,
    ],
    'container' => new Container(),
]);

Router::any('/echo', static fn () => 'echo:' . (App::request()?->ws_buffer ?? ''));
Router::collect([]);
Http::requestClass(\Triangle\Request::class);

$app = new App(\Triangle\Request::class, new Logger('triangle-ws'));
$server = new Server('websocket://0.0.0.0:8081');
$server->count = 1;
$server->onWebSocketConnect = [$app, 'onWebsocketConnect'];
$server->onMessage = [$app, 'onMessage'];
$server->onClose = [$app, 'onClose'];

Server::runAll();
```

In a Triangle project, keep its existing configuration and container bootstrap instead of replacing them with the example values above.

## Lifecycle and limits

- Route middleware runs before upgrade and may reject the handshake with an HTTP response.
- `Request::ws_buffer` contains the incoming frame payload. A string callback result is sent as the outgoing frame body.
- `sendToAll()` and `sendToGroup()` use connections in the current process. They do not provide cross-worker or cross-host fan-out; use a broker or IPC layer for that.
- Configure authentication, origin checks, rate limits, and message-size limits in the application and Localzet Server.
- Engine HTTP and Engine WS cannot be installed together because their Composer manifests declare a conflict.

## Проверка

```bash
composer validate --strict --no-check-publish --no-interaction
composer run test --no-interaction
composer audit --abandoned=report
```

The suite includes a real loopback handshake and frame exchange on Localzet Server 7. See the [full documentation](https://server.localzet.com/projects/engine-ws/install) for setup, messages, security, and migration from 1.x.

## License

AGPL-3.0-or-later. See [LICENSE](LICENSE).
