<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePassport();
        $this->configureAuthorization();
        $this->configurePhotoDelivery();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        Model::preventLazyLoading(! app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Use the Thai authorization screen for the MCP OAuth flow. Access tokens are short-lived and MCP clients
     * refresh them on their own, so a leaked token stops working within the hour.
     */
    protected function configurePassport(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(CarbonInterval::hour());
        Passport::refreshTokensExpireIn(CarbonInterval::days(30));
    }

    /**
     * Only the owner may manage family member accounts.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('manage-members', fn (User $user): bool => $user->is_owner);
    }

    /**
     * Once Laravel has checked a photo's signed URL, hand the file to the web server with X-Accel-Redirect
     * instead of streaming it through PHP. The path is relative to the photos disk root.
     */
    protected function configurePhotoDelivery(): void
    {
        if (! config('filesystems.disks.photos.accel_redirect')) {
            return;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('photos');

        $disk->serveUsing(fn (Request $request, string $path, array $headers): Response => new Response('', 200, [
            ...$headers,
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
            'X-Accel-Redirect' => '/'.implode('/', array_map(rawurlencode(...), explode('/', ltrim($path, '/')))),
        ]));
    }
}
