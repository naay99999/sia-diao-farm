<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Mcp\Server\Testing\TestResponse as McpTestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Issue an access token (and its refresh token) for the user, the way the OAuth flow would.
 *
 * @param  array<string, mixed>  $attributes
 */
function issueToken(User $user, Client $client, array $attributes = [], ?string $refreshExpiresAt = '+30 days'): Token
{
    $token = Token::forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);

    if ($refreshExpiresAt !== null) {
        RefreshToken::forceCreate([
            'id' => Str::random(80),
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->modify($refreshExpiresAt),
        ]);
    }

    return $token;
}

/*
| Decodes the JSON body an MCP tool returned through Response::json(), so tests can assert on the payload.
| Usage: FarmServer::actingAs($user)->tool(SomeTool::class)->assertOk()->payload()
*/
McpTestResponse::macro('payload', function (): array {
    /** @var McpTestResponse $this */
    return json_decode($this->content()[0] ?? '[]', true, flags: JSON_THROW_ON_ERROR);
});
