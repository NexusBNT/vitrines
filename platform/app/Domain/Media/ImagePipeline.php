<?php

namespace App\Domain\Media;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\AvifEncoder;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncoderInterface;
use RuntimeException;

/**
 * Ré-encode une photo déposée en variantes AVIF, WebP et JPEG à plusieurs largeurs.
 * Le ré-encodage supprime les métadonnées EXIF et neutralise les fichiers piégés.
 */
class ImagePipeline
{
    /**
     * @return array{width: int, height: int, variants: list<array{width: int, height: int, files: array<string, string>}>}
     */
    public function process(Media $media): array
    {
        $config = config('vitrines.media');
        $disk = Storage::disk($config['disk']);

        $this->assertPixelCountIsAcceptable($disk->path($media->original_path), $config['max_pixels']);

        $manager = new ImageManager(GdDriver::class, decodeAnimation: false, strip: true);
        $original = $manager->decodePath($disk->path($media->original_path));

        $directory = $media->variantsDirectory();
        $disk->deleteDirectory($directory);

        $variants = [];

        foreach ($this->widthsFor($original->width(), $config['widths']) as $width) {
            $image = (clone $original)->scaleDown(width: $width);
            $files = [];

            foreach ($this->encoders($config['quality']) as $extension => $encoder) {
                $path = sprintf('%s/%d.%s', $directory, $image->width(), $extension);
                $disk->put($path, $image->encode($encoder)->toString());
                $files[$extension] = $path;
            }

            $variants[] = ['width' => $image->width(), 'height' => $image->height(), 'files' => $files];
        }

        return ['width' => $original->width(), 'height' => $original->height(), 'variants' => $variants];
    }

    /**
     * Largeurs à produire : celles du barème inférieures à l'original, plus l'original
     * lui-même s'il se situe entre deux paliers (on n'agrandit jamais une image).
     *
     * @param  list<int>  $widths
     * @return list<int>
     */
    public function widthsFor(int $originalWidth, array $widths): array
    {
        $selected = array_values(array_filter($widths, fn (int $width): bool => $width <= $originalWidth));

        if ($selected === [] || ($originalWidth < max($widths) && ! in_array($originalWidth, $selected, true))) {
            $selected[] = $originalWidth;
        }

        return $selected;
    }

    /**
     * @param  array{avif: int, webp: int, jpg: int}  $quality
     * @return array<string, EncoderInterface>
     */
    private function encoders(array $quality): array
    {
        return [
            'avif' => new AvifEncoder(quality: $quality['avif'], strip: true),
            'webp' => new WebpEncoder(quality: $quality['webp'], strip: true),
            'jpg' => new JpegEncoder(quality: $quality['jpg'], progressive: true, strip: true),
        ];
    }

    /**
     * Refuse les images dont la taille décompressée ferait exploser la mémoire.
     */
    private function assertPixelCountIsAcceptable(string $path, int $maxPixels): void
    {
        $size = @getimagesize($path);

        if ($size === false) {
            throw new RuntimeException('Fichier image illisible.');
        }

        if ($maxPixels < $size[0] * $size[1]) {
            throw new RuntimeException(sprintf('Image trop grande (%d × %d pixels).', $size[0], $size[1]));
        }
    }
}
