<?php

namespace App\Domain\Media;

use App\Jobs\ProcessMedia;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Enregistre une photo déposée dans le répertoire d'entrée du disque média,
 * après vérification de son type réel, puis lance le ré-encodage.
 */
class StoreUploadedMedia
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function handle(Site $site, string $incomingPath, string $originalName): Media
    {
        $config = config('vitrines.media');
        $disk = Storage::disk($config['disk']);
        $absolutePath = $disk->path($incomingPath);

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolutePath);

        if (! in_array($mime, $config['accepted_mimes'], true)) {
            $disk->delete($incomingPath);

            throw ValidationException::withMessages([
                'photos' => sprintf('« %s » n\'est pas une image JPEG, PNG ou WebP.', $originalName),
            ]);
        }

        $sha256 = hash_file('sha256', $absolutePath);

        $existing = $site->media()->where('sha256', $sha256)->first();

        if ($existing !== null) {
            $disk->delete($incomingPath);

            return $existing;
        }

        $originalPath = sprintf('originals/%s/%s.%s', $site->directoryName(), $sha256, self::EXTENSIONS[$mime]);
        $disk->move($incomingPath, $originalPath);

        $media = $site->media()->create([
            'sha256' => $sha256,
            'original_path' => $originalPath,
            'original_name' => mb_substr(basename($originalName), 0, 255),
            'mime' => $mime,
            'bytes' => $disk->size($originalPath),
            'sort_order' => (int) $site->media()->max('sort_order') + 1,
        ]);

        ProcessMedia::dispatch($media);

        return $media;
    }
}
