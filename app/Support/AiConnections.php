<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

class AiConnections
{
    /**
     * The apps a user has connected, one entry per app with when it was first connected.
     *
     * @return array<int, array{clientId: string, name: string, connectedOn: string}>
     */
    public static function forUser(int $userId): array
    {
        return self::liveTokens($userId)
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
    }

    /**
     * Revoke every token one app holds for the user, so it must ask for permission again.
     */
    public static function revoke(int $userId, string $clientId): void
    {
        self::revokeTokens(Token::query()
            ->where('user_id', $userId)
            ->where('client_id', $clientId));
    }

    /**
     * Revoke every token any app holds for the user.
     */
    public static function revokeAll(int $userId): void
    {
        self::revokeTokens(Token::query()->where('user_id', $userId));
    }

    /**
     * @param  Builder<Token>  $tokens
     */
    private static function revokeTokens(Builder $tokens): void
    {
        $tokenIds = $tokens->pluck('id');

        Token::query()->whereKey($tokenIds)->update(['revoked' => true]);
        RefreshToken::query()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
    }

    /**
     * Tokens the app can still use, directly or by refreshing them.
     *
     * @return Builder<Token>
     */
    private static function liveTokens(int $userId): Builder
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
}
