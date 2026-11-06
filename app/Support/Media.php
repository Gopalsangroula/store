<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Uploaded files live on the public disk under stores/{id}/. URLs are root-relative
 * ("/storage/...") so the same record works on every domain a store is reached at.
 */
final class Media
{
    public const IMAGE_RULE = 'image|mimes:jpg,jpeg,png,webp|max:4096';

    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return str_starts_with($path, 'http') || str_starts_with($path, '/') ? $path : '/storage/'.ltrim($path, '/');
    }

    /** Store an upload under the store's folder with a random name; never the client's filename. */
    public static function store(UploadedFile $file, int $storeId, string $folder, string $disk = 'public'): string
    {
        $extension = strtolower($file->guessExtension() ?: 'bin');

        return $file->storeAs("stores/{$storeId}/{$folder}", Str::random(32).'.'.$extension, $disk);
    }
}
