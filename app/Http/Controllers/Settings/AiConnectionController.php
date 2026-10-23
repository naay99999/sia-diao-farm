<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\RecentAiRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

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

        $connections = $this->liveTokens($request->user()->id)
            ->with('client')
            ->get()
            ->filter(fn (Token $token): bool => $token->client !== null && ! $token->client->revoked)
            ->groupBy('client_id')
            ->map(fn ($tokens): array => [
                'clientId' => $tokens->first()->client_id,
                'name' => $tokens->first()->client->name,
                'connectedOn' => $tokens->min('created_at')->toDateString(),
            ])
            ->sortBy('connectedOn')
            ->values()
            ->all();

        return Inertia::render('settings/ai-connection', [
            'mcpUrl' => $appUrl.self::MCP_PATH,
            'isPubliclyReachable' => $this->isPubliclyReachable($appUrl),
            'connections' => $connections,
            'recentAiRecords' => RecentAiRecords::latest(),
        ]);
    }

    /**
     * Revoke every token one app holds for the current user, so it must ask for permission again.
     */
    public function destroy(Request $request, string $client): RedirectResponse
    {
        $tokenIds = Token::query()
            ->where('user_id', $request->user()->id)
            ->where('client_id', $client)
            ->pluck('id');

        Token::query()->whereKey($tokenIds)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'ยกเลิกการเชื่อมต่อแล้ว']);

        return to_route('ai-connection.edit');
    }

    /**
     * Tokens the app can still use, directly or by refreshing them.
     *
     * @return Builder<Token>
     */
    private function liveTokens(int $userId): Builder
    {
        return Token::query()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn (Builder $refresh) => $refresh
                    ->where('revoked', false)
                    ->where('expires_at', '>', now())));
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
