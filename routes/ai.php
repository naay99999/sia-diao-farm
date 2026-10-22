<?php

use App\Http\Middleware\MarkMcpRequest;
use App\Mcp\Servers\FarmServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware('throttle:10,1')->group(function (): void {
    Mcp::oauthRoutes();
});

Mcp::web('/mcp/farm', FarmServer::class)
    ->middleware(['auth:api', MarkMcpRequest::class]);
