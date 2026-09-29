<?php

namespace Tests\Feature\Domain\Media;

use App\Domain\Media\ImagePipeline;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_avif_webp_and_jpeg_variants_without_upscaling(): void
    {
        $disk = Storage::fake('media');
        $media = Media::factory()->create(['original_path' => 'originals/photo.jpg']);
        $disk->put('originals/photo.jpg', $this->jpeg(1000, 600));

        $result = (new ImagePipeline)->process($media);

        $this->assertSame(1000, $result['width']);
        $this->assertSame([480, 800, 1000], array_column($result['variants'], 'width'));
        $this->assertSame(288, $result['variants'][0]['height']);

        foreach ($result['variants'] as $variant) {
            $this->assertSame(['avif', 'webp', 'jpg'], array_keys($variant['files']));

            foreach ($variant['files'] as $extension => $path) {
                $disk->assertExists($path);
                $this->assertStringStartsWith($media->variantsDirectory().'/', $path);
            }
        }

        $this->assertSame('image/avif', $disk->mimeType($result['variants'][0]['files']['avif']));
    }

    public function test_rejects_images_above_the_pixel_limit(): void
    {
        config(['vitrines.media.max_pixels' => 100 * 100]);
        $disk = Storage::fake('media');
        $media = Media::factory()->create(['original_path' => 'originals/photo.jpg']);
        $disk->put('originals/photo.jpg', $this->jpeg(200, 200));

        $this->expectException(RuntimeException::class);

        (new ImagePipeline)->process($media);
    }

    /**
     * @param  list<int>  $expected
     */
    #[DataProvider('widthCases')]
    public function test_selects_widths_without_upscaling(int $originalWidth, array $expected): void
    {
        $this->assertSame($expected, (new ImagePipeline)->widthsFor($originalWidth, [480, 800, 1200, 1600]));
    }

    /**
     * @return array<string, array{int, list<int>}>
     */
    public static function widthCases(): array
    {
        return [
            'large original' => [4000, [480, 800, 1200, 1600]],
            'original matching a step' => [1600, [480, 800, 1200, 1600]],
            'original between steps' => [1000, [480, 800, 1000]],
            'original smaller than every step' => [300, [300]],
        ];
    }

    private function jpeg(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 90, 200));
        ob_start();
        imagejpeg($image);

        return ob_get_clean();
    }
}
