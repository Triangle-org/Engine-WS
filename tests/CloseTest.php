<?php declare(strict_types=1);

/**
 * @package     Triangle Websocket Component
 * @link        https://github.com/Triangle-org/Engine-WS
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2023-2024 Triangle Framework Team
 * @license     https://www.gnu.org/licenses/agpl-3.0 GNU Affero General Public License v3.0
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as published
 *              by the Free Software Foundation, either version 3 of the License, or
 *              (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use localzet\Server\Connection\TcpConnection;
use PHPUnit\Framework\TestCase;
use Triangle\Ws\App;

final class CloseProbeConnection extends TcpConnection
{
    public mixed $closedData = null;

    public function __construct()
    {
        $this->context = new stdClass();
    }

    public function close(mixed $data = null, bool $raw = false): void
    {
        $this->closedData = $data;
    }

    public function send(mixed $sendBuffer, bool $raw = false): bool|null
    {
        return true;
    }
}

final class CloseTest extends TestCase
{
    public function testCloseSendsJsonPayload(): void
    {
        $connection = new CloseProbeConnection();

        App::close($connection, 403, 'Forbidden');

        self::assertIsString($connection->closedData);
        self::assertSame(
            ['status' => 403, 'data' => 'Forbidden'],
            json_decode($connection->closedData, true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testCloseHttpSendsJsonPayloadInHttpResponse(): void
    {
        $connection = new CloseProbeConnection();

        App::close_http($connection, 401, 'Unauthorized');

        self::assertIsString($connection->closedData);
        [$headers, $body] = explode("\r\n\r\n", $connection->closedData, 2);
        self::assertStringContainsString('HTTP/1.1 401 Unauthorized', $headers);
        self::assertSame(
            ['status' => 401, 'data' => 'Unauthorized'],
            json_decode($body, true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
