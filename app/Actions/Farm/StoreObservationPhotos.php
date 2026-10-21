<?php

namespace App\Actions\Farm;

use App\Models\Observation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreObservationPhotos
{
    public const DISK = 'photos';

    /**
     * Store the photos of an observation on the photos disk and record their paths.
     * Files already written are removed again if anything fails, so a rolled back
     * transaction leaves no orphans behind.
     *
     * @param  array<int, UploadedFile>  $photos
     */
    public function handle(Observation $observation, array $photos): void
    {
        $disk = Storage::disk(self::DISK);
        $storedPaths = [];

        try {
            foreach ($photos as $photo) {
                $path = $disk->putFile("observations/{$observation->id}", $photo);

                if ($path === false) {
                    throw new RuntimeException('Unable to store observation photo.');
                }

                $storedPaths[] = $path;
                $observation->photos()->create(['path' => $path]);
            }
        } catch (Throwable $exception) {
            $disk->delete($storedPaths);

            throw $exception;
        }
    }
}
