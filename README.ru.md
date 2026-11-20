# Triangle Engine WS

WebSocket-адаптер для приложений Triangle на Localzet Server. Он проверяет маршрут и его middleware во время HTTP upgrade, а после открытия соединения вызывает обработчик для каждого WebSocket frame.

## Совместимость и установка

Версия 2.x рассчитана на PHP 8.1+, Localzet Server 7.1+, Triangle Engine 9, Router 2 и Middleware 2. Версия 1.x относится к прежней ветке совместимости Engine. Engine HTTP и Engine WS конфликтуют в Composer и предназначены для разных приложений.

```bash
composer require triangle/engine-ws:^2.0
```

## Минимальный bootstrap

Выполняйте этот код после загрузки Composer autoloader приложения Triangle:

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

В существующем Triangle-проекте используйте его bootstrap конфигурации и контейнера, не подменяйте их значениями из примера.

## Жизненный цикл и ограничения

- Middleware маршрута выполняется до upgrade и может отклонить handshake HTTP-ответом.
- `Request::ws_buffer` содержит payload входящего frame. Строка, возвращённая обработчиком, становится телом исходящего frame.
- `sendToAll()` и `sendToGroup()` работают с соединениями текущего процесса. Для рассылки между worker-ами и узлами нужен брокер или IPC.
- Настройте аутентификацию, проверку origin, лимиты частоты и размера сообщений в приложении и Localzet Server.
- Engine HTTP и Engine WS нельзя установить вместе: Composer-манифесты объявляют конфликт.

## Проверки

```bash
composer validate --strict --no-check-publish --no-interaction
composer run test --no-interaction
composer audit --abandoned=report
```

Набор проверок выполняет настоящий loopback handshake и обмен frame с Localzet Server 7. Полные инструкции по запуску, сообщениям, безопасности и переходу с 1.x доступны в [документации](https://server.localzet.com/projects/engine-ws/install).

## Лицензия

AGPL-3.0-or-later. Текст лицензии — в файле [LICENSE](LICENSE).
