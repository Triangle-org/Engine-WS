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

use Triangle\Router;
use Triangle\Ws\App;

Router::any('/echo', static function (): void {
    HandshakeProbeApp::$frameHandlerCalled = true;
    $connectionId = spl_object_id(App::connection());
    HandshakeProbeApp::$frameConnectionAtStart[$connectionId] = App::connection();
    if (Fiber::getCurrent() !== null) {
        Fiber::suspend();
    }
    HandshakeProbeApp::$frameConnectionAfterResume[$connectionId] = App::connection();
})->middleware(PassHandshakeMiddleware::class);

Router::any('/private', static function (): void {
    HandshakeProbeApp::$frameHandlerCalled = true;
})->middleware(RejectHandshakeMiddleware::class);
