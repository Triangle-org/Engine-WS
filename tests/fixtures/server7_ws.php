<?php declare(strict_types=1);

/**
 * @package Triangle WebSocket Component
 * @link https://github.com/Triangle-org/Engine-WS
 * @author Ivan Zorin <creator@localzet.com>
 * @copyright Copyright © 2016-2026 Localzet Group. All rights reserved.
 * @license https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use localzet\Server;
use localzet\Server\Connection\TcpConnection;
use localzet\Server\Protocols\Http;
use localzet\Server\Protocols\Http\Request as HttpRequest;
use localzet\Server\Protocols\Http\Response as HttpResponse;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Triangle\Engine\Config;
use Triangle\Engine\Container;
use Triangle\Engine\Context;
use Triangle\Engine\Response as EngineResponse;
use Triangle\Router;
use Triangle\Ws\App;

$port = (int)($argv[2] ?? 0);
$runtimeDirectory = (string)($argv[3] ?? '');

if (!function_exists('posix_setpgid') || !posix_setpgid(0, 0) || posix_getpgrp() !== getmypid()) {
    fwrite(STDERR, "Could not isolate the WebSocket integration process group.\n");
    exit(1);
}
file_put_contents($runtimeDirectory . '/process-group', (string)posix_getpgrp(), LOCK_EX);

$autoload = getenv('ENGINE_WS_VENDOR_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
require $autoload;

final class WebSocketFixtureApp extends App
{
    public static string $diagnosticLog = '';

    protected static function exceptionResponse(Throwable $exception, mixed $request): EngineResponse
    {
        file_put_contents(self::$diagnosticLog, 'application exception ' . $exception::class . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
        return parent::exceptionResponse($exception, $request);
    }
}

Server::$pidFile = $runtimeDirectory . '/server.pid';
Server::$statusFile = $runtimeDirectory . '/server.status';
Server::$stdoutFile = $runtimeDirectory . '/stdout.log';
Server::$logFile = $runtimeDirectory . '/server.log';

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

Router::any('/echo', static function (): string {
    return 'echo:' . (App::request()?->ws_buffer ?? '');
});
Router::collect([]);

Http::requestClass(\Triangle\Request::class);
$app = new WebSocketFixtureApp(\Triangle\Request::class, new Logger('websocket-fixture', [new NullHandler()]));
$server = new Server("websocket://127.0.0.1:$port");
$server->count = 1;
$callbackLog = $runtimeDirectory . '/callbacks.log';
WebSocketFixtureApp::$diagnosticLog = $callbackLog;
$server->onWebSocketConnect = static function (TcpConnection &$connection, HttpRequest $request) use ($app, $callbackLog): ?HttpResponse {
    file_put_contents($callbackLog, "handshake {$request->path()}\n", FILE_APPEND);
    try {
        $response = $app->onWebsocketConnect($connection, $request);
    } catch (Throwable $exception) {
        file_put_contents($callbackLog, 'handshake exception ' . $exception::class . ': ' . $exception->getMessage() . "\n", FILE_APPEND);
        throw $exception;
    }
    file_put_contents($callbackLog, 'handshake result ' . get_debug_type($response) . "\n", FILE_APPEND);
    file_put_contents($callbackLog, 'connection status ' . $connection->getStatus() . "\n", FILE_APPEND);
    return $response;
};
$server->onMessage = static function (TcpConnection &$connection, mixed $request) use ($app, $callbackLog): void {
    file_put_contents($callbackLog, "frame\n", FILE_APPEND);
    $app->onMessage($connection, $request);
};
$server->onWebSocketConnected = static function (TcpConnection &$connection) use ($callbackLog): void {
    file_put_contents($callbackLog, "upgraded\n", FILE_APPEND);
};
$server->onError = static function (TcpConnection &$connection, int $code, string $message) use ($callbackLog): void {
    file_put_contents($callbackLog, "error $code $message\n", FILE_APPEND);
};
$server->onClose = [$app, 'onClose'];

Server::runAll();
