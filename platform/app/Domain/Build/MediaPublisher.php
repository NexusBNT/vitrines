<?php

namespace App\Domain\Build;

use App\Enums\MediaStatus;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Copie dans le build les variantes des seules photos utilisées par la spécification.
 */
class MediaPublisher
{
    public static function publicPath(Media $media, int $width, string $format): string
    {
        return sprintf('media/%d/%d.%s', $media->getKey(), $width, $format);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, Media> Médias publiés, indexés par identifiant
     */
    public function publish(Site $site, array $spec, string $outputDirectory): array
    {
        $ids = $this->referencedIds($spec);

        $media = $site->media()
            ->whereKey($ids)
            ->where('status', MediaStatus::Ready)
            ->get()
            ->keyBy('id');

        $disk = Storage::disk(config('vitrines.media.disk'));

        foreach ($media as $item) {
            foreach ($item->variants as $variant) {
                foreach ($variant['files'] as $format => $path) {
                    $target = $outputDirectory.'/'.self::publicPath($item, $variant['width'], $format);
                    File::ensureDirectoryExists(dirname($target));
                    File::copy($disk->path($path), $target);
                }
            }
        }

        return $media->all();
    }

    /**
     * Identifiants de médias cités dans les sections (image, images, items[].image).
     *
     * @param  array<string, mixed>  $spec
     * @return list<int>
     */
    public function referencedIds(array $spec): array
    {
        $ids = [];

        foreach ($spec['pages'] as $page) {
            foreach ($page['sections'] as $section) {
                $ids[] = $section['image'] ?? null;
                array_push($ids, ...($section['images'] ?? []));

                foreach ($section['items'] ?? [] as $item) {
                    $ids[] = $item['image'] ?? null;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, is_int(...))));
    }
}
