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

use localzet\Server\Connection\TcpConnection;
use localzet\Server\Protocols\Http\Response as ServerResponse;
use PHPUnit\Framework\TestCase;
use Triangle\Response;
use Triangle\Ws\App;

final class ResponseCompatibilityProbeApp extends App
{
    public static function normalizeResponseForTest(mixed $response): Response
    {
        return static::normalizeResponse($response);
    }

    public static function sendForTest(TcpConnection $connection, string|ServerResponse|null $data): void
    {
        parent::send($connection, $data);
    }

    public static function closeHttpForTest(TcpConnection $connection, int|ServerResponse $response): void
    {
        parent::close_http($connection, $response);
    }
}

final class ResponseCompatibilityConnection extends TcpConnection
{
    public mixed $sentData = null;
    public mixed $closedData = null;

    public function __construct()
    {
        $this->id = 1;
        $this->context = new stdClass();
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        $this->sentData = $sendBuffer;

        return true;
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closedData = $data;
    }
}

final class ResponseCompatibilityTest extends TestCase
{
    public function testServerResponseIsConvertedWithoutLosingStatusHeadersOrBody(): void
    {
        $serverResponse = new ServerResponse(202, ['X-Adapter' => 'preserved'], 'original body');

        $response = ResponseCompatibilityProbeApp::normalizeResponseForTest($serverResponse);

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('preserved', $response->getHeader('X-Adapter'));
        self::assertSame('original body', $response->rawBody());
    }

    public function testServerResponseSendsOnlyItsBodyAsAWebSocketMessage(): void
    {
        $connection = new ResponseCompatibilityConnection();
        $response = new ServerResponse(202, ['X-Adapter' => 'not-a-frame'], 'frame payload');

        ResponseCompatibilityProbeApp::sendForTest($connection, $response);

        self::assertSame('frame payload', $connection->sentData);
    }

    public function testServerResponseCanBeUsedForHttpHandshakeRejection(): void
    {
        $connection = new ResponseCompatibilityConnection();
        $response = new ServerResponse(401, [
            'WWW-Authenticate' => 'Basic realm="websocket"',
            'X-Adapter' => 'preserved',
            'Content-Length' => '3',
            'Transfer-Encoding' => 'chunked',
        ], 'access denied');

        ResponseCompatibilityProbeApp::closeHttpForTest($connection, $response);

        self::assertIsString($connection->closedData);
        [$headers, $body] = explode("\r\n\r\n", $connection->closedData, 2);
        self::assertStringStartsWith('HTTP/1.1 401', $headers);
        self::assertStringContainsString('WWW-Authenticate: Basic realm="websocket"', $headers);
        self::assertStringContainsString('X-Adapter: preserved', $headers);
        self::assertStringNotContainsString('Transfer-Encoding:', $headers);
        self::assertMatchesRegularExpression('/\r\nContent-Length: ' . strlen($body) . '(?:\r\n|$)/', $headers);
        self::assertStringContainsString('"data": "access denied"', $body);
    }
}
