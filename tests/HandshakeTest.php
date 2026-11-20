<?php

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

declare(strict_types=1);

use localzet\Server\Connection\TcpConnection;
use localzet\Server\Protocols\Http;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Triangle\Engine\Config;
use Triangle\Engine\Container;
use Triangle\Engine\Context;
use Triangle\Middleware\MiddlewareInterface;
use Triangle\Router;
use Triangle\Router\Dispatcher;
use Triangle\Ws\App;
use Triangle\Request as WsRequest;

final class HandshakeProbeApp extends App
{
    public static bool $frameHandlerCalled = false;
    public static array $frameConnectionAtStart = [];
    public static array $frameConnectionAfterResume = [];

    public static function resetCallbacks(): void
    {
        foreach (['callbacks', 'connectionsMap', 'connectionUuids'] as $propertyName) {
            $property = new ReflectionProperty(App::class, $propertyName);
            $property->setValue(null, []);
        }

        self::$frameConnectionAtStart = [];
        self::$frameConnectionAfterResume = [];
    }

    public function onMessage(mixed &$connection, mixed $request): void
    {
        parent::onMessage($connection, $request);
    }
}

final class HandshakeProbeConnection extends TcpConnection
{
    public function __construct()
    {
        $this->id = 1;
        $this->context = new stdClass();
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        return true;
    }
}

final class PassHandshakeMiddleware implements MiddlewareInterface
{
    public function process($request, callable $handler): mixed
    {
        return $handler($request);
    }
}

final class RejectHandshakeMiddleware implements MiddlewareInterface
{
    public function process($request, callable $handler): Http\Response
    {
        return new \Triangle\Response(401, [], 'Unauthorized');
    }
}

final class HandshakeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $router = new ReflectionClass(Router::class);
        $generatorProperty = $router->getProperty('dataGenerator');
        $generatorProperty->setValue(null, new \Triangle\Router\DataGenerator\GroupCountBased());
        require_once __DIR__ . '/fixtures/config/route.php';
        $property = $router->getProperty('instance');
        $property->getValue()?->getData();
        $instance = $property->getValue();
        $instance = $instance ?? Router::instance();
        $routes = $instance->getData();
        $routeObjects = $instance->getRoutes();
        foreach ($routes[0] as $methods) {
            foreach ($methods as $routeStatic) {
                $routeStatic->object = $routeObjects[array_search($routeStatic->path, array_map(static fn ($route): string => $route->path, $routeObjects), true)];
            }
        }
        $dispatcher = new \Triangle\Router\Dispatcher\GroupCountBased($routes);
        $dispatcherProperty = new ReflectionProperty($instance, 'dispatcher');
        $dispatcherProperty->setValue($instance, $dispatcher);
    }

    public function testRouteMiddlewareCanRejectUpgradeWithoutInvokingFrameHandler(): void
    {
        self::assertSame(Dispatcher::FOUND, Router::dispatch('GET', '/private')[0]);
        $this->configureApp();
        HandshakeProbeApp::$frameHandlerCalled = false;

        $app = $this->createApp();
        $connection = new HandshakeProbeConnection();
        $request = $this->request('/private');

        $response = $app->onWebsocketConnect($connection, $request);

        self::assertInstanceOf(\Triangle\Response::class, $response);
        self::assertSame(401, $response->getStatusCode());
        self::assertFalse(HandshakeProbeApp::$frameHandlerCalled);
        self::assertFalse(Context::has(TcpConnection::class));
    }

    public function testAuthorizedHandshakeRunsMiddlewareButDefersRouteHandler(): void
    {
        $this->configureApp();
        HandshakeProbeApp::$frameHandlerCalled = false;

        $app = $this->createApp();
        $connection = new HandshakeProbeConnection();
        $request = $this->request('/echo');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            self::assertNull($app->onWebsocketConnect($connection, $request));
        } finally {
            restore_error_handler();
        }
        self::assertInstanceOf(WsRequest::class, $connection->context->triangleRequest);
        self::assertFalse(HandshakeProbeApp::$frameHandlerCalled);
        self::assertFalse(Context::has(TcpConnection::class));
    }

    public function testFirstFrameUsesSavedHandshakeRequest(): void
    {
        $this->configureApp();
        HandshakeProbeApp::$frameHandlerCalled = false;

        $app = $this->createApp();
        $connection = new HandshakeProbeConnection();
        self::assertNull($app->onWebsocketConnect($connection, $this->request('/echo')));

        $buffer = 'hello';
        $app->onMessage($connection, $buffer);

        self::assertTrue(HandshakeProbeApp::$frameHandlerCalled);
        self::assertFalse(Context::has(TcpConnection::class));
    }

    public function testMissingFrameRequestStillClearsConnectionContext(): void
    {
        $this->configureApp();
        $app = $this->createApp();
        $connection = new HandshakeProbeConnection();
        $buffer = 'unexpected';

        $app->onMessage($connection, $buffer);

        self::assertFalse(Context::has(TcpConnection::class));
    }

    public function testFiberCallbacksKeepTheirOwnConnectionContext(): void
    {
        $this->configureApp();
        $app = $this->createApp();
        $connectionA = new HandshakeProbeConnection();
        $connectionB = new HandshakeProbeConnection();
        self::assertNull($app->onWebsocketConnect($connectionA, $this->request('/echo')));
        self::assertNull($app->onWebsocketConnect($connectionB, $this->request('/echo')));

        $bufferA = 'first';
        $bufferB = 'second';
        $fiberA = new Fiber(static function () use ($app, &$connectionA, &$bufferA): void {
            $app->onMessage($connectionA, $bufferA);
        });
        $fiberB = new Fiber(static function () use ($app, &$connectionB, &$bufferB): void {
            $app->onMessage($connectionB, $bufferB);
        });

        if (class_exists(\localzet\Server\Events\Linux::class)) {
            \localzet\Server::$eventLoopClass = \localzet\Server\Events\Linux::class;
        }

        $fiberA->start();
        self::assertTrue($fiberA->isSuspended(), 'The first frame callback did not suspend.');
        $fiberB->start();
        self::assertTrue($fiberB->isSuspended(), 'The second frame callback did not suspend.');
        $fiberA->resume();
        $fiberB->resume();
        \localzet\Server::$eventLoopClass = null;

        self::assertSame($connectionA, HandshakeProbeApp::$frameConnectionAtStart[spl_object_id($connectionA)]);
        self::assertSame($connectionB, HandshakeProbeApp::$frameConnectionAtStart[spl_object_id($connectionB)]);
        self::assertSame($connectionA, HandshakeProbeApp::$frameConnectionAfterResume[spl_object_id($connectionA)]);
        self::assertSame($connectionB, HandshakeProbeApp::$frameConnectionAfterResume[spl_object_id($connectionB)]);
        self::assertFalse(Context::has(TcpConnection::class));
    }

    private function configureApp(): void
    {
        HandshakeProbeApp::resetCallbacks();
        Config::clear();
        Config::set([
            'app' => [
                'plugin_uri' => 'app',
                'plugin_alias' => 'plugin',
                'debug' => false,
                'controller_reuse' => true,
            ],
        ]);
        Context::init();
        Config::set(['container' => new Container()]);
        \localzet\Server::$eventLoopClass = null;
    }

    private function createApp(): HandshakeProbeApp
    {
        return new HandshakeProbeApp(WsRequest::class, new Logger('test', [new NullHandler()]));
    }

    private function request(string $path): Http\Request
    {
        return new Http\Request("GET $path HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n\r\n");
    }
}
