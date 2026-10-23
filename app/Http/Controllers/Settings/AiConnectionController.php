<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\AiConnections;
use App\Support\RecentAiRecords;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiConnectionController extends Controller
{
    /** Where the farm MCP server is mounted, see routes/ai.php. */
    private const MCP_PATH = '/mcp/farm';

    /**
     * Show how to connect Claude, which apps are connected, and what they recorded lately.
     */
    public function edit(Request $request): Response
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        return Inertia::render('settings/ai-connection', [
            'mcpUrl' => $appUrl.self::MCP_PATH,
            'isPubliclyReachable' => $this->isPubliclyReachable($appUrl),
            'connections' => AiConnections::forUser($request->user()->id),
            'recentAiRecords' => RecentAiRecords::latest(),
        ]);
    }

    /**
     * Revoke every token one app holds for the current user, so it must ask for permission again.
     */
    public function destroy(Request $request, string $client): RedirectResponse
    {
        AiConnections::revoke($request->user()->id, $client);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ยกเลิกการเชื่อมต่อแล้ว']);

        return to_route('ai-connection.edit');
    }

    /**
     * Claude connects from the internet, so it needs an https address that is not this machine.
     */
    private function isPubliclyReachable(string $appUrl): bool
    {
        $host = (string) parse_url($appUrl, PHP_URL_HOST);

        return parse_url($appUrl, PHP_URL_SCHEME) === 'https'
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            && ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.local');
    }
}
