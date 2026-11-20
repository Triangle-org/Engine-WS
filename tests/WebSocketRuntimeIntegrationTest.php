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

use PHPUnit\Framework\TestCase;
use Composer\InstalledVersions;

final class WebSocketRuntimeIntegrationTest extends TestCase
{
    public function testServerAcceptsUpgradeAndDispatchesWebSocketFrame(): void
    {
        if (PHP_OS_FAMILY === 'Windows'
            || !function_exists('proc_open')
            || !function_exists('pcntl_fork')
            || !function_exists('posix_kill')
            || !function_exists('posix_setpgid')) {
            self::markTestSkipped('The WebSocket integration fixture requires Unix process support.');
        }

        $engineVersion = InstalledVersions::getPrettyVersion('triangle/engine') ?? '';
        $serverVersion = InstalledVersions::getPrettyVersion('localzet/server') ?? '';
        if (!InstalledVersions::isInstalled('triangle/engine')
            || !InstalledVersions::isInstalled('localzet/server')
            || (int)explode('.', ltrim($engineVersion, 'v'))[0] < 9
            || (int)explode('.', ltrim($serverVersion, 'v'))[0] < 7) {
            self::markTestSkipped('Run tools/test-server7-local.sh to exercise the Engine 9 and Server 7 integration.');
        }

        $runtimeDirectory = sys_get_temp_dir() . '/triangle-engine-ws-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($runtimeDirectory, 0700));
        $process = null;
        $socket = null;

        try {
            $port = $this->reservePort();
            $logFile = $runtimeDirectory . '/server.log';
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $logFile, 'a'],
                2 => ['file', $logFile, 'a'],
            ];

            $process = proc_open(
                [PHP_BINARY, __DIR__ . '/fixtures/server7_ws.php', 'start', (string)$port, $runtimeDirectory],
                $descriptors,
                $pipes,
                dirname(__DIR__),
            );
            self::assertIsResource($process);
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $this->waitForServer($port, $process, $logFile);
            $socket = stream_socket_client("tcp://127.0.0.1:$port", $errorCode, $errorMessage, 2);
            self::assertIsResource($socket, "Could not connect to WebSocket Server: $errorMessage ($errorCode)");
            stream_set_timeout($socket, 3);

            $key = base64_encode(random_bytes(16));
            $this->writeAll($socket, "GET /echo HTTP/1.1\r\n"
                . "Host: localhost\r\n"
                . "Upgrade: websocket\r\n"
                . "Connection: Upgrade\r\n"
                . "Sec-WebSocket-Key: $key\r\n"
                . "Sec-WebSocket-Version: 13\r\n\r\n");

            try {
                $handshake = $this->readUntil($socket, "\r\n\r\n");
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException(
                    $exception->getMessage() . PHP_EOL
                    . $this->readLog($logFile) . PHP_EOL
                    . $this->readLog($runtimeDirectory . '/stdout.log') . PHP_EOL
                    . $this->readLog($runtimeDirectory . '/callbacks.log'),
                    0,
                    $exception,
                );
            }
            self::assertStringStartsWith('HTTP/1.1 101', $handshake);
            self::assertStringContainsString(
                'Sec-WebSocket-Accept: ' . base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)),
                $handshake,
            );

            $payload = 'ping';
            $mask = random_bytes(4);
            $maskedPayload = '';
            for ($index = 0; $index < strlen($payload); $index++) {
                $maskedPayload .= $payload[$index] ^ $mask[$index % 4];
            }
            $this->writeAll($socket, chr(0x81) . chr(0x80 | strlen($payload)) . $mask . $maskedPayload);

            $frameHeader = $this->readExact($socket, 2);
            self::assertSame(0x81, ord($frameHeader[0]));
            $payloadLength = ord($frameHeader[1]);
            self::assertSame(0, $payloadLength & 0x80);
            self::assertLessThan(126, $payloadLength);
            self::assertSame('echo:ping', $this->readExact($socket, $payloadLength));
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
            if (is_resource($process)) {
                $this->stopServer($process, $runtimeDirectory);
            }
            $this->removeRuntimeDirectory($runtimeDirectory);
        }
    }

    private function reservePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if (!is_resource($socket)) {
            throw new RuntimeException("Could not reserve an integration port: $errorMessage ($errorCode)");
        }

        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int)substr(strrchr((string)$address, ':'), 1);
    }

    private function waitForServer(int $port, $process, string $logFile): void
    {
        $deadline = microtime(true) + 5;
        do {
            $socket = @stream_socket_client("tcp://127.0.0.1:$port", $errorCode, $errorMessage, 0.05);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                throw new RuntimeException('WebSocket Server exited during startup: ' . $this->readLog($logFile));
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('WebSocket Server did not open its listener: ' . $this->readLog($logFile));
    }

    private function stopServer($process, string $runtimeDirectory): void
    {
        $status = proc_get_status($process);
        $masterPid = (int)$status['pid'];
        if ($status['running']) {
            proc_terminate($process, SIGTERM);
            $deadline = microtime(true) + 5;
            do {
                usleep(50_000);
                $status = proc_get_status($process);
            } while ($status['running'] && microtime(true) < $deadline);
        }

        $processGroup = (int)@file_get_contents($runtimeDirectory . '/process-group');
        if ($processGroup === $masterPid && ($status['running'] || $this->hasRunningWorker($runtimeDirectory, $masterPid))) {
            @posix_kill(-$processGroup, SIGKILL);
        } elseif ($status['running']) {
            proc_terminate($process, SIGKILL);
        }

        proc_close($process);
    }

    private function hasRunningWorker(string $runtimeDirectory, int $masterPid): bool
    {
        foreach (glob($runtimeDirectory . '/server.status.d/worker-*.json') ?: [] as $file) {
            $worker = json_decode((string)file_get_contents($file), true);
            $workerPid = (int)($worker['pid'] ?? 0);

            if (($worker['master_pid'] ?? null) === $masterPid
                && $workerPid > 1
                && @posix_kill($workerPid, 0)
                && @posix_getpgid($workerPid) === $masterPid) {
                return true;
            }
        }

        return false;
    }

    private function readUntil($socket, string $delimiter): string
    {
        $buffer = '';
        while (!str_contains($buffer, $delimiter)) {
            $chunk = fread($socket, 1024);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('WebSocket handshake ended before the headers were complete.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function readExact($socket, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($socket, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('WebSocket frame ended before the payload was complete.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function writeAll($socket, string $data): void
    {
        while ($data !== '') {
            $written = fwrite($socket, $data);
            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the complete WebSocket payload.');
            }
            $data = substr($data, $written);
        }
    }

    private function readLog(string $logFile): string
    {
        return is_file($logFile) ? (string)file_get_contents($logFile) : '';
    }

    private function removeRuntimeDirectory(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                foreach (glob($item . '/*') ?: [] as $child) {
                    @unlink($child);
                }
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($directory);
    }
}
