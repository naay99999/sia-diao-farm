<?php

namespace App\Http\Middleware;

use App\Support\Provenance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class MarkMcpRequest
{
    /**
     * Flag the request so records saved while handling it are attributed to MCP.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);

        return $next($request);
    }
}
