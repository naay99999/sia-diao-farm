<?php

use App\Providers\AppServiceProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::fake('photos');
    Storage::disk('photos')->put('observations/1/leaf.jpg', UploadedFile::fake()->image('leaf.jpg')->getContent());
});

/**
 * The signed link the app hands out for a photo (the faked disk builds plain links, so sign the route directly).
 */
function signedPhotoUrl(string $path): string
{
    return URL::temporarySignedRoute('storage.photos', now()->addMinutes(5), ['path' => $path], absolute: false);
}

function usePhotoAccelRedirect(): void
{
    config(['filesystems.disks.photos.accel_redirect' => true]);
    app()->getProvider(AppServiceProvider::class)->boot();
}

test('a signed photo url is handed to the web server instead of streamed by php', function () {
    usePhotoAccelRedirect();

    $this->get(signedPhotoUrl('observations/1/leaf.jpg'))
        ->assertOk()
        ->assertHeader('X-Accel-Redirect', '/observations/1/leaf.jpg')
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('Content-Security-Policy')
        ->assertHeader('Cache-Control')
        ->assertContent('');
});

test('an unsigned or missing photo is refused before anything is handed over', function () {
    usePhotoAccelRedirect();

    $this->get('/photos/observations/1/leaf.jpg')->assertForbidden()->assertHeaderMissing('X-Accel-Redirect');
    $this->get(signedPhotoUrl('observations/1/missing.jpg'))
        ->assertNotFound()
        ->assertHeaderMissing('X-Accel-Redirect');
});

test('without the web server setting photos are streamed by php as before', function () {
    $response = $this->get(signedPhotoUrl('observations/1/leaf.jpg'))
        ->assertOk()
        ->assertHeaderMissing('X-Accel-Redirect');

    expect($response->streamedContent())->toBe(Storage::disk('photos')->get('observations/1/leaf.jpg'));
});
