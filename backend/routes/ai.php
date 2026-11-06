<?php

use HiEvents\Http\Middleware\AddMcpBearerChallenge;
use HiEvents\Http\Middleware\ValidateMcpOrigin;
use HiEvents\Mcp\Servers\HiEventsServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Mcp::web('/mcp', HiEventsServer::class)
    ->withoutMiddleware(AddWwwAuthenticateHeader::class)
    ->middleware([
        AddMcpBearerChallenge::class,
        ValidateMcpOrigin::class,
        'auth:sanctum',
        'throttle:120,1,mcp',
    ]);
