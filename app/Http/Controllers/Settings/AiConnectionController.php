<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AiConnections;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AiConnectionController extends Controller
{
    /** Where the farm MCP server is mounted, see routes/ai.php. */
    private const MCP_PATH = '/mcp/farm';

    /**
     * Show my Claude connections and, for the owner, the setup steps and every member's connections.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isOwner = Gate::forUser($user)->allows('manage-members');
        $appUrl = rtrim((string) config('app.url'), '/');

        return Inertia::render('settings/ai', [
            'myConnections' => AiConnections::forUser($user->id),
            'setup' => $isOwner ? [
                'mcpUrl' => $appUrl.self::MCP_PATH,
                'isPubliclyReachable' => $this->isPubliclyReachable($appUrl),
            ] : null,
            'members' => $isOwner ? $this->membersWithConnections() : null,
        ]);
    }

    /**
     * Revoke every token one app holds for a user, so it must ask for permission again. Owners may do this for anyone.
     */
    public function destroy(Request $request, User $user, string $client): RedirectResponse
    {
        abort_unless(
            $request->user()->is($user) || Gate::forUser($request->user())->allows('manage-members'),
            403,
        );

        AiConnections::revoke($user->id, $client);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ยกเลิกการเชื่อมต่อแล้ว']);

        return to_route('ai-connections.index');
    }

    /**
     * @return array<int, array{userId: int, name: string, connections: array<int, array{clientId: string, name: string, connectedOn: string}>}>
     */
    private function membersWithConnections(): array
    {
        return User::query()
            ->whereNull('deactivated_at')
            ->orderByDesc('is_owner')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $member): array => [
                'userId' => $member->id,
                'name' => $member->name,
                'connections' => AiConnections::forUser($member->id),
            ])
            ->all();
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
