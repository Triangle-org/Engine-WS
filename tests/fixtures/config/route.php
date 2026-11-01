<?php

use Triangle\Router;

Router::any('/echo', static function (): void {
    HandshakeProbeApp::$frameHandlerCalled = true;
})->middleware(PassHandshakeMiddleware::class);

Router::any('/private', static function (): void {
    HandshakeProbeApp::$frameHandlerCalled = true;
})->middleware(RejectHandshakeMiddleware::class);
